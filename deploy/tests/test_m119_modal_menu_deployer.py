#!/usr/bin/env python3
"""Two-file menu correction preserves the complete financial R11 closure. No VPS calls."""
import hashlib
import importlib.util
import os
from pathlib import Path
import sys
import tempfile
import types
import unittest
ROOT=Path(__file__).resolve().parents[2]
def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
h=load('menu_helper','deploy/releases/m119/modal_menu_update_helper.py')
i=load('menu_installer','deploy/releases/m119/install_modal_menu_helper.py')
f=load('menu_finance_tests','deploy/tests/test_m119_finance_deployer.py')
b=f.b;s=f.s;r11=f.h
r12=load('prior_menu_release','deploy/releases/m119/menu_update_helper.py')

def sha(raw):return hashlib.sha256(raw).hexdigest()
class MenuDeployerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.tmp=tempfile.TemporaryDirectory(prefix='m119-menu-release-')
        p=f.w.package_for(h.SOURCE,Path(cls.tmp.name)/'menu');before=f.w.package_for(r12.SOURCE,Path(cls.tmp.name)/'finance')
        cls.manifest=Path(p['manifest_path']).read_bytes();cls.artifact=Path(p['artifact']).read_bytes()
        cls.c=s.configured((ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes());cls.c.ALLOWLIST=frozenset(b.FINANCE_RUNTIME_FILES)|{'support/release.json'}
        cls.validator=h.pinned(Path(r11.__file__).read_bytes(),h.VALIDATOR,'menu_validator');cls.validator.SOURCE=h.SOURCE;cls.validator.ARTIFACT=h.ARTIFACT;cls.validator.MANIFEST=h.MANIFEST
        cls.files=cls.validator.validate_package(cls.c,cls.manifest,cls.artifact)
        prior=h.pinned(Path(r11.__file__).read_bytes(),h.VALIDATOR,'prior_menu_validator');prior.SOURCE=r12.SOURCE;prior.ARTIFACT=r12.ARTIFACT;prior.MANIFEST=r12.MANIFEST
        cls.before=prior.validate_package(cls.c,Path(before['manifest_path']).read_bytes(),Path(before['artifact']).read_bytes())
    @classmethod
    def tearDownClass(cls):cls.tmp.cleanup()
    def test_exact_pins_and_only_two_ui_files_change_above_compatible_finance(self):
        self.assertEqual(sha(Path(h.__file__).read_bytes()),i.NEW);self.assertEqual(h.PREVIOUS,i.OLD);self.assertEqual(sha(Path(r12.__file__).read_bytes()),h.PREVIOUS)
        changed={p[len(r11.MODULE):]for p in self.files if p.startswith(r11.MODULE)and self.files[p]!=self.before[p]}
        self.assertEqual(changed,set(h.CHANGED));self.assertEqual(changed,{'employee.css','workspace.js'});self.assertEqual(len(self.files),70)
        for p in self.files:
            if p.startswith(r11.MODULE+'schema/')or p==r11.MODULE+'finance-bridge.json'or '/class/OfficeExpenseFinancial' in p or '/class/OfficeExpenseNativeFinance' in p:self.assertEqual(self.files[p],self.before[p])
    def test_corrupt_packages_untrusted_helpers_and_unknown_actions_refused(self):
        with self.assertRaises(RuntimeError):self.validator.validate_package(self.c,self.manifest,self.artifact+b'x')
        with self.assertRaises(RuntimeError):h.pinned(b'raise SystemExit(99)',h.PREVIOUS,'bad')
        for args in [[],['shell'],['apply','extra'],['--force']]:
            with self.assertRaises(RuntimeError):h.cli(args)
    def test_frozen_replacer_cannot_silently_expand_the_two_file_delta(self):
        patch=h.pinned((ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes(),h.REPLACER,'menu_delta');patch.CHANGED=h.CHANGED
        u=patch.Update.__new__(patch.Update);u.old=types.SimpleNamespace(RUNTIME=r11.RUNTIME,MODULE=r11.MODULE);u.before=self.before;u.after=self.files;u.closure()
        u.after=dict(self.files,**{r11.MODULE+'class/OfficeExpenseFinancialService.php':b'foreign backend'})
        with self.assertRaises(RuntimeError):u.closure()
    @unittest.skipUnless(os.geteuid()==0 and os.environ.get('GITHUB_ACTIONS')=='true' and os.environ.get('CI')=='true','Full root-owned frozen chain only in isolated CI')
    def test_full_chain_retains_financial_verifier_and_only_two_file_rollback(self):
        self.assertNotEqual(os.uname().nodename,'srv1011043')
        root=Path('/var/lib/chinaplanetos-backup-erp-deploy');inbox=Path('/home/codex-deploy/staging-inbox');self.assertFalse(root.exists());self.assertFalse(inbox.exists());root.mkdir(mode=0o700,parents=True);inbox.mkdir(mode=0o700,parents=True)
        try:
            chain=[('m119-standalone-r01','legacy_helper.py','deploy_helper.py'),('m119-ui-r02','standalone_helper.py','standalone_deploy_helper.py'),('m119-sidebar-r03','ui_r02_helper.py','ui_update_helper.py'),('m119-workspace-r04','previous_helper.py','sidebar_update_helper.py'),('m119-receipt-r05','previous_helper.py','workspace_update_helper.py'),('m119-receipt-r06','previous_helper.py','receipt_update_helper.py'),('m119-design-r07','previous_helper.py','receipt_acceptance_helper.py'),('m119-design-r08','previous_helper.py','design_update_helper.py'),('m119-dropdown-r09','previous_helper.py','design_acceptance_helper.py'),('m119-entry-r10','previous_helper.py','dropdown_update_helper.py'),('m119-finance-r11','previous_helper.py','entry_update_helper.py'),('m119-menu-r12','previous_helper.py','finance_update_helper.py'),('m119-menu-r13','previous_helper.py','menu_update_helper.py')]
            for release,name,source in chain:
                directory=root/release;directory.mkdir(mode=0o700);r11.private_write(directory/name,(ROOT/'deploy/releases/m119'/source).read_bytes())
            commits=[s.SOURCE,'e59efa9a33e5c21b5bbd82e37caf6c1670713480','26ce07fdef53765121532837dbaf7dcc2f6057cf','6fa6caf5dde3e3762354ae8faaf4947c49bdb52d','0391d0cb637e807b6639990e11b6320d876413b8','1be58db8a0ccd6bd0608ed722c34f36d2ed82c08','759b74d40cb9b0c80d51e5bf5c9908a1ec7711ef','afeb72b1c0e69233c59e99afb18609fd7e3053dc',f.w.p5.SOURCE,f.e.SOURCE,r11.SOURCE,r12.SOURCE,h.SOURCE]
            for commit in commits:
                with tempfile.TemporaryDirectory()as tmp:
                    package=f.w.package_for(commit,Path(tmp))
                    for key in ['artifact','manifest_path']:
                        original=Path(package[key]);target=inbox/original.name;target.write_bytes(original.read_bytes());target.chmod(0o600);os.chown(target,1002,1002)
            c,u=h.setup(Path(r12.__file__).read_bytes());u.closure();self.assertEqual(c.COMMIT,h.SOURCE);self.assertEqual(c.STATE,h.STATE);self.assertEqual(len(u.after),70);self.assertEqual(len(u.before),70);self.assertEqual(len(u.new.RUNTIME),69)
            self.assertEqual(u.original.COMMIT,r12.SOURCE);self.assertEqual(u.original.STATE,r12.STATE)
            self.assertEqual(u.baseline.verify.__self__.c.COMMIT,r12.SOURCE);self.assertEqual(u.baseline.worker.__self__.c.STATE,r11.STATE)
            self.assertEqual(u.restore.__func__.__globals__['STATE'],h.STATE);self.assertEqual(u.restore.__func__.__globals__['CHANGED'],h.CHANGED)
        finally:
            import shutil
            shutil.rmtree(root);shutil.rmtree(inbox)

if __name__=='__main__':
    if len(sys.argv)==3 and sys.argv[1]=='--prepare-fixtures':
        destination=Path(sys.argv[2]);destination.mkdir(mode=0o700,exist_ok=True);b.build(h.SOURCE,destination/h.SOURCE,ROOT);print('Pinned menu fixture prepared')
    else:unittest.main()
