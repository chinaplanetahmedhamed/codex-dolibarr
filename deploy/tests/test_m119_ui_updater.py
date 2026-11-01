#!/usr/bin/env python3
"""Fixed UI-only package, preconditions and rollback fault coverage."""
import hashlib
import importlib.util
import os
from pathlib import Path
import tempfile
import types
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[2]
def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
h=load('ui','deploy/releases/m119/ui_update_helper.py')
i=load('installer','deploy/releases/m119/install_ui_helper.py')
b=load('builder','deploy/releases/m119/build_standalone_artifact.py')
old=h.module((ROOT/'deploy/releases/m119/standalone_deploy_helper.py').read_bytes())
legacy=(ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes()

class UiUpdaterTests(unittest.TestCase):
 def test_exact_frozen_package_pins_and_five_file_scope(self):
    self.assertEqual(h.digest((ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes()),i.NEW)
    self.assertEqual(h.digest((ROOT/'deploy/releases/m119/install_standalone_helper.py').read_bytes()),i.READER)
    self.assertEqual(h.digest((ROOT/'deploy/releases/m119/standalone_deploy_helper.py').read_bytes()),h.PREVIOUS_HELPER)
    with tempfile.TemporaryDirectory()as tmp:
      before=b.build(old.SOURCE,Path(tmp)/'a',ROOT);after=b.build(h.SOURCE,Path(tmp)/'b',ROOT)
      self.assertEqual(h.digest(Path(after['manifest_path']).read_bytes()),h.MANIFEST)
      self.assertEqual(h.digest(Path(after['artifact']).read_bytes()),h.ARTIFACT)
      raw=(ROOT/'deploy/releases/m119/standalone_deploy_helper.py').read_bytes()
      with patch.object(old,'frozen',return_value=legacy):
       c=old.configured(legacy);files=old.validate_package(c,Path(before['manifest_path']).read_bytes(),Path(before['artifact']).read_bytes())
      new=h.module(raw);new.SOURCE=h.SOURCE;new.ARTIFACT=h.ARTIFACT;new.MANIFEST=h.MANIFEST;new.STATE=h.STATE
      nc=new.configured(legacy);other=new.validate_package(nc,Path(after['manifest_path']).read_bytes(),Path(after['artifact']).read_bytes())
      u=h.Update.__new__(h.Update);u.old=old;u.before=files;u.after=other;u.closure()
      other[old.MODULE+'index.php']=b'UNREVIEWED'
      with self.assertRaises(RuntimeError):u.closure()
 def test_wrong_action_refused_before_host_or_files(self):
    for args in [[],['shell'],['apply','extra']]:
      with self.assertRaises(RuntimeError):h.cli(args)
 def test_corrupted_frozen_helper_rejected_before_execution(self):
    with self.assertRaises(RuntimeError):h.module(b'print("unsafe")')
 def test_rollback_failure_is_never_reported_active(self):
    events=[]
    u=h.Update.__new__(h.Update);u.record=types.SimpleNamespace(read_state=lambda:{'phase':'active'});u.baseline=types.SimpleNamespace(base=types.SimpleNamespace(context=lambda:events.append('context')))
    def fail(value):raise RuntimeError('synthetic restore failure')
    u.restore=fail
    with self.assertRaises(RuntimeError):u.rollback()
    self.assertEqual(events,['context'])
 @unittest.skipUnless(os.geteuid()==0,'Actual root-owned replacement runs inside the matching Linux CI container')
 def test_real_root_replacement_refuses_drift_and_symlink_and_restores(self):
    c=old.configured(legacy)
    with tempfile.TemporaryDirectory()as tmp:
      root=Path(tmp);(root/'users.php').write_bytes(b'before');(root/'users.php').chmod(0o644)
      fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
      tree=types.SimpleNamespace(directory=lambda parts,create:os.dup(fd),assert_live=lambda:None)
      try:
       h.replace(c,tree,'users.php',b'after',(b'before',));self.assertEqual((root/'users.php').read_bytes(),b'after')
       with self.assertRaises(RuntimeError):h.replace(c,tree,'users.php',b'unknown',(b'before',))
       h.replace(c,tree,'users.php',b'before',(b'after',));self.assertEqual((root/'users.php').read_bytes(),b'before')
       (root/'users.php').unlink();(root/'users.php').symlink_to(root/'foreign.php');(root/'foreign.php').write_bytes(b'foreign')
       with self.assertRaises(OSError):h.replace(c,tree,'users.php',b'bad',(b'foreign',))
       self.assertEqual((root/'foreign.php').read_bytes(),b'foreign')
      finally:os.close(fd)

if __name__=='__main__':unittest.main()
