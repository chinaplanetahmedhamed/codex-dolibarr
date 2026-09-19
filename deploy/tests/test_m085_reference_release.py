"""Exercise the unchanged release engine with the pinned one-file candidate."""
import importlib.util
from pathlib import Path
import types
import unittest

ROOT=Path(__file__).resolve().parents[1]
def load(name,path):
 spec=importlib.util.spec_from_file_location(name,path);module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module);return module
builder=load('builder',ROOT/'tools/build_m085_reference_release.py')
code,payload=builder.build()
engine=types.ModuleType('reference_release');exec(compile(code,'generated-reference-release','exec'),engine.__dict__)
base=load('base_tests',ROOT/'tests/test_m085_stage_release.py');base.m=engine
class ReferenceReleaseTest(base.ReleaseTest):
 def setUp(self):
  super().setUp();engine.PAYLOAD['release']='M085-REF-R01'
class ScopeTest(unittest.TestCase):
 def test_exact_one_file_and_separate_state(self):
  self.assertEqual(set(payload['files']),{'operations/public_requests.php'})
  self.assertEqual(payload['commit'],'7d0ca969a164867ff11fb0e8c034ffff02bfcf5b')
  self.assertEqual(str(engine.HELPER),'/usr/local/sbin/chinaplanetos-m085-ref-staging')
  self.assertIn("STATE = Path('/var/lib/chinaplanetos-m085-ref-staging')",code.decode())
  self.assertEqual(engine.CONTAINER,'erp-staging-dolibarr-1')
  self.assertEqual(builder.sha(code),'971c9276c2223c12319356f3229fd9efba487f099804e4b1a6dc916ca5de067d')
if __name__=='__main__':unittest.main()
