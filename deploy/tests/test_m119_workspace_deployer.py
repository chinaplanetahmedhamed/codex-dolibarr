#!/usr/bin/env python3
"""Workspace release closure, ordered activation and fail-closed recovery; no VPS calls."""
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
h=load('workspace_helper','deploy/releases/m119/workspace_update_helper.py')
i=load('workspace_installer','deploy/releases/m119/install_workspace_helper.py')
b=load('workspace_builder','deploy/releases/m119/build_standalone_artifact.py')
s=load('workspace_engine','deploy/releases/m119/standalone_deploy_helper.py')

def package_for(commit,destination):
    fixtures=os.environ.get('M119_WORKSPACE_FIXTURES')
    if not fixtures:return b.build(commit,destination,ROOT)
    directory=Path(fixtures)/commit
    artifacts=list(directory.glob('artifact-*.tar.gz'));manifests=list(directory.glob('manifest-*.json'))
    if len(artifacts)!=1 or len(manifests)!=1:raise RuntimeError('Prebuilt CI fixture closure differs')
    return {'artifact':str(artifacts[0]),'manifest_path':str(manifests[0])}

class WorkspaceDeployerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp=tempfile.TemporaryDirectory(prefix='m119-workspace-release-')
        cls.package=package_for(h.SOURCE,Path(cls.tmp.name))
        cls.manifest=Path(cls.package['manifest_path']).read_bytes();cls.artifact=Path(cls.package['artifact']).read_bytes()
        cls.c=s.configured((ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes())
        cls.c.COMMIT=h.SOURCE;cls.c.ARTIFACT_SHA=h.ARTIFACT;cls.c.MANIFEST_SHA=h.MANIFEST
        cls.c.ALLOWLIST=frozenset(h.MODULE+p for p in h.RUNTIME)|{'support/release.json'}
    @classmethod
    def tearDownClass(cls):cls.tmp.cleanup()
    def test_exact_application_worker_installer_and_closure_pins(self):
        self.assertEqual(hashlib.sha256(Path(h.__file__).read_bytes()).hexdigest(),i.NEW)
        self.assertEqual(hashlib.sha256((ROOT/'deploy/releases/m119/workspace_native_operations.php').read_bytes()).hexdigest(),h.WORKER_SHA)
        self.assertEqual(h.WORKER_SHA,i.WORKER)
        self.assertEqual(h.PREVIOUS,i.OLD)
        self.assertEqual(tuple(h.MODULE+p for p in h.RUNTIME),b.WORKSPACE_RUNTIME_FILES)
        self.assertEqual(hashlib.sha256(self.manifest).hexdigest(),h.MANIFEST)
        self.assertEqual(hashlib.sha256(self.artifact).hexdigest(),h.ARTIFACT)
        self.assertEqual(set(h.validate_package(self.c,self.manifest,self.artifact)),self.c.ALLOWLIST)
    def test_corrupt_package_and_frozen_code_refused_before_execution(self):
        for manifest,artifact in [(self.manifest+b' ',self.artifact),(self.manifest,self.artifact+b'x')]:
            with self.assertRaises(RuntimeError):h.validate_package(self.c,manifest,artifact)
        with self.assertRaises(RuntimeError):h.pinned(b'raise SystemExit(99)',h.PREVIOUS,'bad')
    def test_only_fixed_actions_and_pinned_requests(self):
        for args in [[],['shell'],['apply','extra'],['--force']]:
            with self.assertRaises(RuntimeError):h.cli(args)
        value={'schema':1,'action':'apply','mission_id':'M119','commit':h.SOURCE,'artifact_sha256':h.ARTIFACT,'artifact_name':'artifact-'+h.ARTIFACT+'.tar.gz','manifest_name':'manifest-'+h.ARTIFACT+'.json'}
        self.c.validate_request(value,'apply')
        for key,v in [('commit','0'*40),('artifact_name','../file'),('schema',True),('action','verify')]:
            with self.assertRaises(RuntimeError):self.c.validate_request(dict(value,**{key:v}),'apply')
    def fake_update(self,events,fail_mode=None):
        state={'schema':1,'mission':'M119','commit':'old','artifact':'old','id':'1'*32,'phase':'active'}
        class Record:
            def read_state(self):return state
            def record(self,value,phase):events.append(phase);return dict(value,phase=phase)
            def context(self):events.append('context');return None
            def backup(self,database):
                events.append('database-backup')
                if fail_mode=='backup':raise RuntimeError('synthetic backup failed')
                return 'backup-hash'
        class Tree:
            def install(self,path,data):events.append('add:'+path)
            def close(self):events.append('close')
        c=types.SimpleNamespace(uuid=types.SimpleNamespace(uuid4=lambda:types.SimpleNamespace(hex='2'*32)),RuntimeTree=lambda **kw:Tree(),Deployment=lambda files:Record(),secure_read=lambda path,*args:path.read_bytes())
        before={h.MODULE+'index.php':b'before'}
        baseline=types.SimpleNamespace(record=Record(),after=before,c=c,new=types.SimpleNamespace(verify_tree=lambda *args:events.append('baseline-closure')),baseline=types.SimpleNamespace(health=lambda:events.append('login-wall')))
        u=h.Update(c,types.SimpleNamespace(verify_tree=lambda *args:events.append('closure')),baseline,{h.MODULE+p:b'after' for p in h.RUNTIME})
        u.record=Record();u.preflight=lambda:{'financial_fingerprint':'a'*64}
        def worker(mode):
            events.append('native:'+mode)
            if mode==fail_mode or (fail_mode=='disable_failed'and mode in ('activate','disable')):raise RuntimeError('synthetic worker failed')
            return {'financial_fingerprint':'b'*64 if fail_mode=='drift'and mode=='prepare' else'a'*64,'native_branch_checks':300}
        u.worker=worker
        return u
    def run_apply(self,mode=None):
        events=[];u=self.fake_update(events,mode)
        with tempfile.TemporaryDirectory()as tmp,patch.object(h,'STATE',Path(tmp)),patch.object(h,'replace',side_effect=lambda *a:events.append('replace:'+a[2])):
            if mode:
                with self.assertRaises(RuntimeError):u.apply()
            else:u.apply()
        return events
    def test_dependency_closed_install_then_schema_grants_and_readback(self):
        events=self.run_apply()
        self.assertLess(events.index('database-backup'),events.index('installing'))
        self.assertLess(max(j for j,e in enumerate(events)if e.startswith('add:')),events.index('replace:index.php'))
        self.assertLess(events.index('closure'),events.index('native:prepare'))
        self.assertLess(events.index('native:prepare'),events.index('native:activate'))
        self.assertLess(events.index('native:activate'),events.index('native:verify'))
        self.assertEqual(events[-2:],['active','close'])
    def test_failed_backup_never_reaches_code_schema_or_grants(self):
        events=self.run_apply('backup')
        self.assertNotIn('installing',events)
        self.assertFalse(any(e.startswith('native:')for e in events))
    def test_partial_activation_retains_compatible_code_and_disables(self):
        events=self.run_apply('activate')
        self.assertIn('native:disable',events);self.assertIn('workspace_disabled_retained',events)
        self.assertNotIn('active',events);self.assertNotIn('native:verify',events)
    def test_unconfirmed_disable_reports_required_reconciliation(self):
        events=self.run_apply('disable_failed')
        self.assertIn('failed_reconciliation_required',events);self.assertNotIn('workspace_disabled_retained',events)
    def test_financial_drift_stops_before_grant_activation(self):
        events=self.run_apply('drift')
        self.assertNotIn('native:activate',events);self.assertIn('workspace_disabled_retained',events)
    def test_rollback_refuses_foreign_closure_before_touching_flag(self):
        events=[];u=self.fake_update(events)
        def drift(*args):raise RuntimeError('foreign file')
        u.engine.verify_tree=drift
        with self.assertRaises(RuntimeError):u.rollback()
        self.assertNotIn('native:disable',events);self.assertEqual(events[-1],'close')
    def test_native_worker_unknown_mode_stops_before_bootstrap(self):
        for mode in ['shell','--force']:
            r=subprocess.run(['php',str(ROOT/'deploy/releases/m119/workspace_native_operations.php'),mode],capture_output=True)
            self.assertEqual(r.returncode,1);self.assertEqual(r.stdout,b'')
    @unittest.skipUnless(os.geteuid()==0 and os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('CI')=='true','Frozen root library integration runs only in isolated CI')
    def test_full_frozen_chain_keeps_prior_globals_and_new_closure_separate(self):
        self.assertNotEqual(os.uname().nodename,'srv1011043')
        root=Path('/var/lib/chinaplanetos-backup-erp-deploy');inbox=Path('/home/codex-deploy/staging-inbox')
        self.assertFalse(root.exists());self.assertFalse(inbox.exists())
        root.mkdir(mode=0o700,parents=True);inbox.mkdir(mode=0o700,parents=True)
        try:
            for release,name in [('m119-standalone-r01','legacy_helper.py'),('m119-ui-r02','standalone_helper.py'),('m119-sidebar-r03','ui_r02_helper.py'),('m119-workspace-r04','previous_helper.py')]:
                directory=root/release;directory.mkdir(mode=0o700)
                source={'legacy_helper.py':'deploy_helper.py','standalone_helper.py':'standalone_deploy_helper.py','ui_r02_helper.py':'ui_update_helper.py','previous_helper.py':'sidebar_update_helper.py'}[name]
                h.private_write(directory/name,(ROOT/'deploy/releases/m119'/source).read_bytes())
            for commit in [s.SOURCE,'e59efa9a33e5c21b5bbd82e37caf6c1670713480','26ce07fdef53765121532837dbaf7dcc2f6057cf',h.SOURCE]:
                with tempfile.TemporaryDirectory()as tmp:
                    package=package_for(commit,Path(tmp))
                    for key in ['artifact','manifest_path']:
                        original=Path(package[key]);target=inbox/original.name;target.write_bytes(original.read_bytes());target.chmod(0o600);os.chown(target,1002,1002)
            raw=(ROOT/'deploy/releases/m119/sidebar_update_helper.py').read_bytes()
            c,u=h.setup(raw)
            self.assertEqual(c.COMMIT,h.SOURCE);self.assertEqual(c.STATE,h.STATE)
            self.assertEqual(set(u.files),{h.MODULE+p for p in h.RUNTIME}|{'support/release.json'})
            self.assertEqual(u.baseline.c.COMMIT,'26ce07fdef53765121532837dbaf7dcc2f6057cf')
            self.assertEqual(len(u.baseline.after),46)
            self.assertEqual(len(u.engine.RUNTIME),59)
        finally:
            import shutil
            shutil.rmtree(root);shutil.rmtree(inbox)
    @unittest.skipUnless(os.geteuid()==0,'Root inode tests run inside the matching Linux CI container')
    def test_root_replacement_refuses_drift_and_symlink(self):
        with tempfile.TemporaryDirectory()as tmp:
            p=Path(tmp);(p/'users.php').write_bytes(b'before');(p/'users.php').chmod(0o644)
            fd=os.open(p,os.O_RDONLY|os.O_DIRECTORY);tree=types.SimpleNamespace(directory=lambda *args:os.dup(fd),assert_live=lambda:None)
            try:
                h.replace(self.c,tree,'users.php',b'after',b'before');self.assertEqual((p/'users.php').read_bytes(),b'after')
                with self.assertRaises(RuntimeError):h.replace(self.c,tree,'users.php',b'bad',b'before')
                (p/'users.php').unlink();(p/'foreign').write_bytes(b'safe');(p/'users.php').symlink_to(p/'foreign')
                with self.assertRaises(OSError):h.replace(self.c,tree,'users.php',b'bad',b'safe')
                self.assertEqual((p/'foreign').read_bytes(),b'safe')
            finally:os.close(fd)

if __name__=='__main__':
    if len(sys.argv)==3 and sys.argv[1]=='--prepare-fixtures':
        destination=Path(sys.argv[2]);destination.mkdir(mode=0o700)
        for commit in [s.SOURCE,'e59efa9a33e5c21b5bbd82e37caf6c1670713480','26ce07fdef53765121532837dbaf7dcc2f6057cf',h.SOURCE]:b.build(commit,destination/commit,ROOT)
        destination.chmod(0o755);print('Pinned workspace CI fixtures prepared')
    else:unittest.main()
