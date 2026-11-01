#!/usr/bin/env python3
import hashlib
import importlib.util
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[2]
def load(name,path):
 s=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
h=load('sidebar','deploy/releases/m119/sidebar_update_helper.py')
i=load('sidebar_installer','deploy/releases/m119/install_sidebar_helper.py')
b=load('builder','deploy/releases/m119/build_standalone_artifact.py')
class SidebarTests(unittest.TestCase):
 def test_pins_and_chained_three_file_closure_preserve_r02_contract(self):
  raw=(ROOT/'deploy/releases/m119/ui_update_helper.py').read_bytes();raw_standalone=(ROOT/'deploy/releases/m119/standalone_deploy_helper.py').read_bytes();legacy=(ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes()
  self.assertEqual(hashlib.sha256(raw).hexdigest(),h.PREVIOUS)
  self.assertEqual(hashlib.sha256((ROOT/'deploy/releases/m119/sidebar_update_helper.py').read_bytes()).hexdigest(),i.NEW)
  actual_engine=h.engine;factory=actual_engine(raw);old=factory.module(raw_standalone);old.frozen=lambda:legacy
  original=old.configured(legacy)
  current=factory.module(raw_standalone);current.SOURCE=factory.SOURCE;current.ARTIFACT=factory.ARTIFACT;current.MANIFEST=factory.MANIFEST;current.STATE=factory.STATE;c_current=current.configured(legacy)
  with tempfile.TemporaryDirectory()as tmp:
   packages={}
   for label,m in [('a',old),('b',current)]:
    result=b.build(m.SOURCE,Path(tmp)/label,ROOT);packages[m.SOURCE]=m.validate_package(m.configured(legacy),Path(result['manifest_path']).read_bytes(),Path(result['artifact']).read_bytes())
   next_source=factory.module(raw_standalone);next_source.SOURCE=h.SOURCE;next_source.ARTIFACT=h.ARTIFACT;next_source.MANIFEST=h.MANIFEST;next_source.STATE=h.STATE
   result=b.build(h.SOURCE,Path(tmp)/'c',ROOT)
   self.assertEqual(hashlib.sha256(Path(result['artifact']).read_bytes()).hexdigest(),h.ARTIFACT)
   self.assertEqual(hashlib.sha256(Path(result['manifest_path']).read_bytes()).hexdigest(),h.MANIFEST)
   packages[h.SOURCE]=next_source.validate_package(next_source.configured(legacy),Path(result['manifest_path']).read_bytes(),Path(result['artifact']).read_bytes())
   def engine(raw):
    m=actual_engine(raw);m.setup=lambda ignored:(old,original,current,c_current);m.package=lambda source,c:packages[source.SOURCE];return m
   with patch.object(h,'engine',side_effect=engine):c,u=h.configure(raw,raw_standalone)
   u.closure();self.assertEqual(c.STATE,h.STATE);self.assertEqual(c.COMMIT,h.SOURCE)
   self.assertEqual(u.baseline.verify.__self__.c.STATE,Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-ui-r02'))
   self.assertEqual(u.closure.__globals__['CHANGED'],h.CHANGED)
   self.assertEqual(u.baseline.verify.__self__.closure.__globals__['CHANGED'],factory.CHANGED)
   u.after[old.MODULE+'employee.js']=b'UNREVIEWED'
   with self.assertRaises(RuntimeError):u.closure()
 def test_unknown_engine_and_action_stop_before_server_access(self):
  with self.assertRaises(RuntimeError):h.engine(b'untrusted')
  for args in [[],['shell'],['apply','extra']]:
   with self.assertRaises(RuntimeError):h.cli(args)
if __name__=='__main__':unittest.main()
