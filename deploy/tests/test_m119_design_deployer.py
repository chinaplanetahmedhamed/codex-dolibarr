#!/usr/bin/env python3
"""Receipt-only fixed release pins, closure and frozen compatibility; no VPS calls."""
import hashlib
import importlib.util
import os
from pathlib import Path
import sys
import tempfile
import types
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[2]
def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
h=load('receipt_helper','deploy/releases/m119/design_update_helper.py')
i=load('receipt_installer','deploy/releases/m119/install_design_helper.py')
w=load('receipt_predecessor','deploy/releases/m119/workspace_update_helper.py')
b=load('receipt_builder','deploy/releases/m119/build_standalone_artifact.py')
p5=load('design_r06','deploy/releases/m119/receipt_acceptance_helper.py')
s=load('receipt_standalone','deploy/releases/m119/standalone_deploy_helper.py')

def package_for(commit,destination):
    fixtures=os.environ.get('M119_WORKSPACE_FIXTURES')
    if not fixtures:return b.build(commit,destination,ROOT)
    directory=Path(fixtures)/commit
    artifacts=list(directory.glob('artifact-*.tar.gz'));manifests=list(directory.glob('manifest-*.json'))
    if len(artifacts)!=1 or len(manifests)!=1:raise RuntimeError('Prebuilt receipt fixture closure differs')
    return {'artifact':str(artifacts[0]),'manifest_path':str(manifests[0])}

class ReceiptDeployerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp=tempfile.TemporaryDirectory(prefix='m119-receipt-release-')
        cls.package=package_for(h.SOURCE,Path(cls.tmp.name)/'new');cls.prior=package_for(p5.SOURCE,Path(cls.tmp.name)/'old')
        cls.manifest=Path(cls.package['manifest_path']).read_bytes();cls.artifact=Path(cls.package['artifact']).read_bytes()
        cls.c=s.configured((ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes())
        cls.c.COMMIT=h.SOURCE;cls.c.ARTIFACT_SHA=h.ARTIFACT;cls.c.MANIFEST_SHA=h.MANIFEST
        cls.c.ALLOWLIST=frozenset(w.MODULE+p for p in w.RUNTIME)|{'support/release.json'}
        cls.validator=h.pinned(Path(w.__file__).read_bytes(),h.WORKSPACE,'test_validator')
        cls.validator.SOURCE=h.SOURCE;cls.validator.ARTIFACT=h.ARTIFACT;cls.validator.MANIFEST=h.MANIFEST
        cls.files=cls.validator.validate_package(cls.c,cls.manifest,cls.artifact)
    @classmethod
    def tearDownClass(cls):cls.tmp.cleanup()
    def test_exact_helper_installer_and_complete_package_pins(self):
        self.assertEqual(hashlib.sha256(Path(h.__file__).read_bytes()).hexdigest(),i.NEW)
        self.assertEqual(hashlib.sha256(Path(p5.__file__).read_bytes()).hexdigest(),h.PREVIOUS)
        self.assertEqual(h.PREVIOUS,i.OLD)
        self.assertEqual(hashlib.sha256((ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes()).hexdigest(),h.REPLACER)
        self.assertEqual(hashlib.sha256(self.manifest).hexdigest(),h.MANIFEST)
        self.assertEqual(hashlib.sha256(self.artifact).hexdigest(),h.ARTIFACT)
        self.assertEqual(set(self.files),self.c.ALLOWLIST)
    def test_only_nine_runtime_files_change_and_no_schema_permission_or_native_cash_change(self):
        prior_validator=h.pinned(Path(w.__file__).read_bytes(),h.WORKSPACE,'prior_validator');prior_validator.SOURCE=p5.SOURCE;prior_validator.ARTIFACT=p5.ARTIFACT;prior_validator.MANIFEST=p5.MANIFEST
        prior=prior_validator.validate_package(self.c,Path(self.prior['manifest_path']).read_bytes(),Path(self.prior['artifact']).read_bytes())
        changed={p[len(w.MODULE):]for p in self.files if p.startswith(w.MODULE)and self.files[p]!=prior[p]}
        self.assertEqual(changed,set(h.CHANGED));self.assertEqual(len(changed),9)
        self.assertFalse(any(p.startswith('schema/')or 'StaffAccess' in p or 'NativeTreasury' in p for p in changed))
        engine=h.pinned((ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes(),h.REPLACER,'test_replacer');engine.CHANGED=h.CHANGED
        u=engine.Update.__new__(engine.Update);u.old=types.SimpleNamespace(RUNTIME=w.RUNTIME,MODULE=w.MODULE);u.before=prior;u.after=self.files
        u.closure()
        u.after=dict(self.files,**{w.MODULE+'workspace.php':b'foreign'})
        with self.assertRaises(RuntimeError):u.closure()
    def test_corrupt_package_frozen_code_and_requests_are_refused(self):
        with self.assertRaises(RuntimeError):self.validator.validate_package(self.c,self.manifest,self.artifact+b'x')
        with self.assertRaises(RuntimeError):h.pinned(b'raise SystemExit(99)',h.PREVIOUS,'bad')
        for args in [[],['shell'],['apply','extra'],['--force']]:
            with self.assertRaises(RuntimeError):h.cli(args)
        value={'schema':1,'action':'apply','mission_id':'M119','commit':h.SOURCE,'artifact_sha256':h.ARTIFACT,'artifact_name':'artifact-'+h.ARTIFACT+'.tar.gz','manifest_name':'manifest-'+h.ARTIFACT+'.json'}
        self.c.validate_request(value,'apply')
        for key,val in [('commit',w.SOURCE),('artifact_name','../bad'),('schema',True),('action','verify')]:
            with self.assertRaises(RuntimeError):self.c.validate_request(dict(value,**{key:val}),'apply')
    @unittest.skipUnless(os.geteuid()==0 and os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('CI')=='true','Full root frozen-chain test only in isolated CI')
    def test_full_frozen_chain_keeps_r04_verifier_and_r06_rollback_separate(self):
        self.assertNotEqual(os.uname().nodename,'srv1011043')
        root=Path('/var/lib/chinaplanetos-backup-erp-deploy');inbox=Path('/home/codex-deploy/staging-inbox')
        self.assertFalse(root.exists());self.assertFalse(inbox.exists());root.mkdir(mode=0o700,parents=True);inbox.mkdir(mode=0o700,parents=True)
        try:
            for release,name,source in [('m119-standalone-r01','legacy_helper.py','deploy_helper.py'),('m119-ui-r02','standalone_helper.py','standalone_deploy_helper.py'),('m119-sidebar-r03','ui_r02_helper.py','ui_update_helper.py'),('m119-workspace-r04','previous_helper.py','sidebar_update_helper.py'),('m119-receipt-r05','previous_helper.py','workspace_update_helper.py'),('m119-receipt-r06','previous_helper.py','receipt_update_helper.py'),('m119-design-r07','previous_helper.py','receipt_acceptance_helper.py')]:
                directory=root/release;directory.mkdir(mode=0o700);w.private_write(directory/name,(ROOT/'deploy/releases/m119'/source).read_bytes())
            for commit in [s.SOURCE,'e59efa9a33e5c21b5bbd82e37caf6c1670713480','26ce07fdef53765121532837dbaf7dcc2f6057cf',w.SOURCE,'0391d0cb637e807b6639990e11b6320d876413b8',p5.SOURCE,h.SOURCE]:
                with tempfile.TemporaryDirectory()as tmp:
                    package=package_for(commit,Path(tmp))
                    for key in ['artifact','manifest_path']:
                        original=Path(package[key]);target=inbox/original.name;target.write_bytes(original.read_bytes());target.chmod(0o600);os.chown(target,1002,1002)
            c,u=h.setup(Path(p5.__file__).read_bytes())
            self.assertEqual(c.COMMIT,h.SOURCE);self.assertEqual(c.STATE,h.STATE)
            self.assertEqual(set(u.after),self.c.ALLOWLIST);self.assertEqual(len(u.before),60);self.assertEqual(len(u.old.RUNTIME),59)
            self.assertEqual(u.original.COMMIT,p5.SOURCE);self.assertEqual(u.original.STATE,p5.STATE)
            self.assertEqual(u.baseline.verify.__self__.c.COMMIT,p5.SOURCE)
            self.assertEqual(u.baseline.worker.__self__.c.STATE,w.STATE)
            self.assertEqual(u.restore.__func__.__globals__['STATE'],h.STATE)
            self.assertEqual(u.restore.__func__.__globals__['CHANGED'],h.CHANGED)
            u.closure()
        finally:
            import shutil
            shutil.rmtree(root);shutil.rmtree(inbox)
    @unittest.skipUnless(os.geteuid()==0,'Root inode tests run in the matching Linux CI container')
    def test_nine_file_replacer_refuses_unknown_content_symlink_and_restores_exact_bytes(self):
        engine=h.pinned((ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes(),h.REPLACER,'test_root_replacer');engine.CHANGED=h.CHANGED
        with tempfile.TemporaryDirectory()as tmp:
            root=Path(tmp)
            for relative in h.CHANGED:
                path=root/relative;path.parent.mkdir(exist_ok=True,parents=True);path.write_bytes(b'before');path.chmod(0o644)
            def directory(parts,unused):return os.open(root.joinpath(*parts),os.O_RDONLY|os.O_DIRECTORY)
            tree=types.SimpleNamespace(directory=directory,assert_live=lambda:None)
            for relative in h.CHANGED:
                engine.replace(self.c,tree,relative,b'after',(b'before',));self.assertEqual((root/relative).read_bytes(),b'after')
                with self.assertRaises(RuntimeError):engine.replace(self.c,tree,relative,b'foreign',(b'before',))
                engine.replace(self.c,tree,relative,b'before',(b'before',b'after'));self.assertEqual((root/relative).read_bytes(),b'before')
            path=root/'index.php';path.unlink();path.symlink_to(root/'workspace.css')
            with self.assertRaises(OSError):engine.replace(self.c,tree,'index.php',b'foreign',(b'before',))
            self.assertEqual((root/'workspace.css').read_bytes(),b'before')

if __name__=='__main__':
    if len(sys.argv)==3 and sys.argv[1]=='--prepare-fixtures':
        destination=Path(sys.argv[2]);destination.mkdir(mode=0o700,exist_ok=True);b.build(h.SOURCE,destination/h.SOURCE,ROOT);print('Pinned receipt CI fixture prepared')
    else:unittest.main()
