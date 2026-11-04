#!/usr/bin/env python3
"""Pinned finance closure and data-preserving release/repair tests. No server access."""
import hashlib
import importlib.util
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import types
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[2]
def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
h=load('finance_helper','deploy/releases/m119/finance_update_helper.py')
i=load('finance_installer','deploy/releases/m119/install_finance_helper.py')
e=load('entry_helper','deploy/releases/m119/entry_update_helper.py')
b=load('finance_builder','deploy/releases/m119/build_standalone_artifact.py')
s=load('finance_engine','deploy/releases/m119/standalone_deploy_helper.py')
w=load('finance_prior_tests','deploy/tests/test_m119_entry_deployer.py')

class FinanceDeployerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp=tempfile.TemporaryDirectory(prefix='m119-finance-release-')
        package=w.package_for(h.SOURCE,Path(cls.tmp.name)/'finance');prior=w.package_for(e.SOURCE,Path(cls.tmp.name)/'prior')
        cls.manifest=Path(package['manifest_path']).read_bytes();cls.artifact=Path(package['artifact']).read_bytes()
        cls.c=s.configured((ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes());cls.c.COMMIT=h.SOURCE;cls.c.ARTIFACT_SHA=h.ARTIFACT;cls.c.MANIFEST_SHA=h.MANIFEST
        cls.c.ALLOWLIST=frozenset(h.MODULE+p for p in h.RUNTIME)|{'support/release.json'}
        cls.files=h.validate_package(cls.c,cls.manifest,cls.artifact)
        validator=h.pinned((ROOT/'deploy/releases/m119/workspace_update_helper.py').read_bytes(),e.WORKSPACE,'finance_prior_validator');validator.SOURCE=e.SOURCE;validator.ARTIFACT=e.ARTIFACT;validator.MANIFEST=e.MANIFEST
        old_c=types.SimpleNamespace(ALLOWLIST=frozenset(b.WORKSPACE_RUNTIME_FILES)|{'support/release.json'},decode_json=cls.c.decode_json)
        cls.before=validator.validate_package(old_c,Path(prior['manifest_path']).read_bytes(),Path(prior['artifact']).read_bytes())
    @classmethod
    def tearDownClass(cls):cls.tmp.cleanup()
    def test_exact_dependency_closed_package_and_installer_worker_pins(self):
        self.assertEqual(sha(Path(h.__file__).read_bytes()),i.NEW);self.assertEqual(sha(Path(e.__file__).read_bytes()),h.PREVIOUS);self.assertEqual(i.OLD,h.PREVIOUS)
        self.assertEqual(sha((ROOT/'deploy/releases/m119/finance_native_verify.php').read_bytes()),h.WORKER_SHA)
        self.assertEqual(tuple(h.MODULE+p for p in h.RUNTIME),b.FINANCE_RUNTIME_FILES);self.assertEqual(len(self.files),70)
        self.assertEqual(self.files[h.MODULE+h.GATE],h.ACTIVE)
        self.assertTrue(set(self.before)<set(self.files));self.assertEqual(len(self.files)-len(self.before),10)
        for p in self.before:
            if '/schema/'in p:self.assertEqual(self.before[p],self.files[p])
    def test_corrupt_package_untrusted_code_and_invalid_actions_fail_before_execution(self):
        with self.assertRaises(RuntimeError):h.validate_package(self.c,self.manifest,self.artifact+b'x')
        with self.assertRaises(RuntimeError):h.validate_package(self.c,self.manifest+b'x',self.artifact)
        with self.assertRaises(RuntimeError):h.pinned(b'raise SystemExit(99)',h.PREVIOUS,'bad')
        for args in [[],['shell'],['apply','extra'],['--force']]:
            with self.assertRaises(RuntimeError):h.cli(args)
    def fake_update(self,events,fail=None):
        state={'schema':1,'mission':'M119','commit':'old','artifact':'old','id':'1'*32,'phase':'active'}
        class Record:
            def read_state(self):return state
            def record(self,value,phase):events.append(phase);return dict(value,phase=phase)
            def context(self):events.append('context');return None
            def backup(self,database):
                events.append('database-backup')
                if fail=='backup':raise RuntimeError('synthetic backup failed')
                return 'backup-sha'
        class Tree:
            def close(self):events.append('close')
        c=types.SimpleNamespace(uuid=types.SimpleNamespace(uuid4=lambda:types.SimpleNamespace(hex='2'*32)),RuntimeTree=lambda **kw:Tree(),Deployment=lambda files:Record(),secure_read=lambda path,*args:self.before[sorted(self.before)[int(path.name.split('-')[-1])]])
        baseline=types.SimpleNamespace(after=self.before,record=Record(),new=types.SimpleNamespace(verify_tree=lambda *args:events.append('baseline-tree')),baseline=types.SimpleNamespace(health=lambda:events.append('health')))
        engine=types.SimpleNamespace(verify_tree=lambda *args:events.append('new-tree'))
        u=h.Update(c,engine,baseline,None,self.files,None);u.preflight=lambda:{'financial_fingerprint':'a'*64}
        def worker(mode):
            events.append('native:'+mode)
            if fail==mode:raise RuntimeError('synthetic verification failed')
            return {'financial_fingerprint':('b'*64 if fail=='drift'else'a'*64),'settings_disabled':True}
        u.worker=worker;u.complete_paused=lambda tree:events.append('compatible-paused');u.write_gate=lambda tree,enabled:events.append('gate:'+str(enabled))
        return u
    def run_apply(self,fail=None):
        events=[];u=self.fake_update(events,fail)
        with patch.object(h,'private_write',side_effect=lambda *args:events.append('source-backup')):
            if fail:
                with self.assertRaises(RuntimeError):u.apply()
            else:u.apply()
        return events
    def test_full_database_and_exact_source_backup_precede_compatible_install(self):
        events=self.run_apply();self.assertLess(events.index('database-backup'),events.index('compatible-paused'));self.assertLess(events.index('source-backup'),events.index('compatible-paused'));self.assertLess(events.index('native:paused'),events.index('gate:True'));self.assertLess(events.index('native:verify'),events.index('active'))
    def test_backup_or_native_drift_stops_before_runtime_mutation(self):
        for mode in ['backup','drift']:
            events=self.run_apply(mode);self.assertNotIn('compatible-paused',events);self.assertNotIn('gate:True',events)
    def test_post_apply_verification_failure_pauses_and_retains_compatible_closure(self):
        events=self.run_apply('verify');self.assertIn('finance_paused_retained',events);self.assertLess(events.index('gate:False'),events.index('finance_paused_retained'));self.assertNotIn('active',events)
    def test_failed_pause_verification_records_reconciliation_required(self):
        events=self.run_apply('paused');self.assertIn('failed_reconciliation_required',events);self.assertNotIn('active',events)
    def test_rollback_pauses_before_drain_and_never_invokes_old_restore_or_database_restore(self):
        events=[];u=self.fake_update(events);u.rollback();self.assertEqual(events,['context','gate:False','compatible-paused','native:paused','finance_paused_retained','close'])
    def test_native_verifier_unknown_mode_stops_before_bootstrap(self):
        for mode in ['shell','--force']:
            r=subprocess.run(['php',str(ROOT/'deploy/releases/m119/finance_native_verify.php'),mode],capture_output=True);self.assertEqual(r.returncode,1);self.assertEqual(r.stdout,b'')

    @unittest.skipUnless(os.geteuid()==0 and os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('CI')=='true','Frozen root chain runs only in isolated Linux CI')
    def test_full_frozen_chain_preserves_previous_globals_and_accepts_finance_closure(self):
        self.assertNotEqual(os.uname().nodename,'srv1011043')
        root=Path('/var/lib/chinaplanetos-backup-erp-deploy');inbox=Path('/home/codex-deploy/staging-inbox')
        self.assertFalse(root.exists());self.assertFalse(inbox.exists());root.mkdir(mode=0o700,parents=True);inbox.mkdir(mode=0o700,parents=True)
        try:
            chain=[('m119-standalone-r01','legacy_helper.py','deploy_helper.py'),('m119-ui-r02','standalone_helper.py','standalone_deploy_helper.py'),('m119-sidebar-r03','ui_r02_helper.py','ui_update_helper.py'),('m119-workspace-r04','previous_helper.py','sidebar_update_helper.py'),('m119-receipt-r05','previous_helper.py','workspace_update_helper.py'),('m119-receipt-r06','previous_helper.py','receipt_update_helper.py'),('m119-design-r07','previous_helper.py','receipt_acceptance_helper.py'),('m119-design-r08','previous_helper.py','design_update_helper.py'),('m119-dropdown-r09','previous_helper.py','design_acceptance_helper.py'),('m119-entry-r10','previous_helper.py','dropdown_update_helper.py'),('m119-finance-r11','previous_helper.py','entry_update_helper.py')]
            for release,name,source in chain:
                directory=root/release;directory.mkdir(mode=0o700);h.private_write(directory/name,(ROOT/'deploy/releases/m119'/source).read_bytes())
            commits=[s.SOURCE,'e59efa9a33e5c21b5bbd82e37caf6c1670713480','26ce07fdef53765121532837dbaf7dcc2f6057cf','6fa6caf5dde3e3762354ae8faaf4947c49bdb52d','0391d0cb637e807b6639990e11b6320d876413b8','1be58db8a0ccd6bd0608ed722c34f36d2ed82c08','759b74d40cb9b0c80d51e5bf5c9908a1ec7711ef','afeb72b1c0e69233c59e99afb18609fd7e3053dc','003fb825b5248457d89dd2dca782f43bf1445be7',e.SOURCE,h.SOURCE]
            # The R09 source remains pinned by the installed R10 predecessor.
            commits[-3]=w.p5.SOURCE
            for commit in commits:
                with tempfile.TemporaryDirectory()as tmp:
                    package=w.package_for(commit,Path(tmp))
                    for key in ['artifact','manifest_path']:
                        original=Path(package[key]);target=inbox/original.name;target.write_bytes(original.read_bytes());target.chmod(0o600);os.chown(target,1002,1002)
            c,u=h.setup(Path(e.__file__).read_bytes());self.assertEqual(c.COMMIT,h.SOURCE);self.assertEqual(c.STATE,h.STATE);self.assertEqual(len(u.files),70);self.assertEqual(len(u.baseline.after),60);self.assertEqual(len(u.engine.RUNTIME),69)
            self.assertEqual(u.baseline.c.COMMIT,e.SOURCE);self.assertEqual(u.baseline.c.STATE,e.STATE);self.assertEqual(u.baseline.new.RUNTIME,tuple(p[len(h.MODULE):]for p in b.WORKSPACE_RUNTIME_FILES))
        finally:
            import shutil
            shutil.rmtree(root);shutil.rmtree(inbox)

    @unittest.skipUnless(os.geteuid()==0,'Actual root-owned runtime inode checks run in isolated Linux CI')
    def test_partial_install_repair_is_known_bytes_only_and_retains_finance_pause(self):
        engine=h.pinned((ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes(),h.REPLACER,'finance_real_replacer');engine.CHANGED=h.RUNTIME
        u=h.Update(self.c,None,types.SimpleNamespace(after=self.before),None,self.files,engine)
        with tempfile.TemporaryDirectory()as tmp:
            root=Path(tmp)
            for p,data in self.before.items():
                if p.startswith(h.MODULE):
                    target=root/p[len(h.MODULE):];target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data);target.chmod(0o644)
            def directory(parts,unused=False):return os.open(root.joinpath(*parts),os.O_RDONLY|os.O_DIRECTORY)
            def install(p,data):
                target=root/p;target.parent.mkdir(exist_ok=True,parents=True)
                with target.open('xb')as f:f.write(data)
                target.chmod(0o644)
            fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY);tree=types.SimpleNamespace(directory=directory,assert_live=lambda:None,install=install,fd=fd)
            u.engine=types.SimpleNamespace(verify_tree=lambda c,t,files:None)
            try:
                # Interrupted compatible install recovers by completing the new parser with a false gate.
                install('class/OfficeExpenseFinancialService.php',self.files[h.MODULE+'class/OfficeExpenseFinancialService.php']);u.complete_paused(tree)
                self.assertEqual((root/h.GATE).read_bytes(),h.PAUSED)
                for relative in h.RUNTIME:self.assertEqual((root/relative).read_bytes(),u.paused_files()[h.MODULE+relative])
                (root/'workspace.php').write_bytes(b'foreign runtime change')
                with self.assertRaises(RuntimeError):u.complete_paused(tree)
                self.assertEqual((root/'workspace.php').read_bytes(),b'foreign runtime change');self.assertEqual((root/h.GATE).read_bytes(),h.PAUSED)
                (root/'workspace.php').unlink();(root/'workspace.php').symlink_to(root/'index.php')
                with self.assertRaises(OSError):u.complete_paused(tree)
            finally:os.close(fd)

    @unittest.skipUnless(os.geteuid()==0,'Root-owned private installer preparation is checked in Linux CI')
    def test_installer_resumes_only_exact_failed_preflight_and_preserves_prior_verifier(self):
        reader=load('finance_installer_reader','deploy/releases/m119/install_standalone_helper.py');old=Path(e.__file__).read_bytes();worker=(ROOT/'deploy/releases/m119/finance_native_verify.php').read_bytes()
        with tempfile.TemporaryDirectory(prefix='m119-finance-installer-',dir='/var/lib')as tmp:
            state=Path(tmp)/'private-state'
            with patch.object(i,'STATE',state):
                i.prepare_state(reader,old,worker);self.assertEqual((state/'previous_helper.py').read_bytes(),old)
                # Current or financially active state cannot be silently retried as an old preparation.
                with self.assertRaises(RuntimeError):i.prepare_state(reader,old,worker)
                with patch.object(i.hashlib,'sha256',return_value=types.SimpleNamespace(hexdigest=lambda:'0f19c6aecb92cf64e9b34bf62ae3c6071d26618288deb41331ce28c5c4b69b04')):
                    i.prepare_state(reader,old,worker)
                self.assertEqual((state/'finance_native_verify.before-readiness-fix.php').read_bytes(),worker)
                self.assertEqual((state/'finance_native_verify.php').read_bytes(),worker)
                (state/'state.json').write_text('synthetic recorded deployment')
                with self.assertRaises(RuntimeError):i.prepare_state(reader,old,worker)


def sha(raw):return hashlib.sha256(raw).hexdigest()
if __name__=='__main__':
    if len(sys.argv)==3 and sys.argv[1]=='--prepare-fixtures':
        destination=Path(sys.argv[2]);destination.mkdir(mode=0o700,exist_ok=True);b.build(h.SOURCE,destination/h.SOURCE,ROOT);print('Pinned finance fixture prepared')
    else:unittest.main()
