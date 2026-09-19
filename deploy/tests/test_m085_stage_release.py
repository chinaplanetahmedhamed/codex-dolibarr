"""Fault-injection contract tests. Entire filesystem/runtime is synthetic."""
import base64
import hashlib
import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

SOURCE=Path(__file__).resolve().parents[1]/'tools/m085_stage_release.py'
spec=importlib.util.spec_from_file_location('m085_release',SOURCE)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class ReleaseTest(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup)
  self.root=Path(self.tmp.name).resolve();m.ROOT=self.root/'custom';m.ROOT.mkdir();m.STATE=self.root/'state';m.STATE.mkdir(mode=0o700)
  (m.ROOT/'old.php').write_bytes(b'old');(m.ROOT/'dependency.php').write_bytes(b'dependency')
  m.PAYLOAD={'release':'M085-R01','commit':'synthetic','files':{n:{'before':m.digest(b'old') if n=='old.php' else None,'sha256':m.digest(data),'data':base64.b64encode(data).decode()} for n,data in [('old.php',b'new'),('added.php',b'added')]},'prerequisites':{'dependency.php':m.digest(b'dependency')}}
  for name in ('environment','schema','lint','health'):
   p=patch.object(m,name);p.start();self.addCleanup(p.stop)
  p=patch.object(m,'state_directory_valid',return_value=True);p.start();self.addCleanup(p.stop)
  p=patch.object(m.os,'geteuid',return_value=0);p.start();self.addCleanup(p.stop)
  p=patch.object(m.os,'fchown');p.start();self.addCleanup(p.stop)
 def run_action(self,action):
  with patch.object(sys,'argv',['helper',action]):m.main()
 def test_apply_verify_rollback_preserves_original(self):
  self.run_action('preflight');self.run_action('apply');self.run_action('verify')
  self.assertEqual((m.ROOT/'old.php').read_bytes(),b'new')
  self.run_action('rollback');self.assertEqual((m.ROOT/'old.php').read_bytes(),b'old');self.assertFalse((m.ROOT/'added.php').exists())
 def test_drift_fails_without_writes(self):
  (m.ROOT/'old.php').write_bytes(b'unrelated change')
  with self.assertRaisesRegex(RuntimeError,'BASELINE_MISMATCH'):self.run_action('apply')
  self.assertFalse((m.STATE/'before.json').exists());self.assertFalse((m.ROOT/'added.php').exists())
 def test_failed_acceptance_restores(self):
  m.health.side_effect=[None,RuntimeError('synthetic health failure'),None]
  with self.assertRaisesRegex(RuntimeError,'synthetic health'):self.run_action('apply')
  self.assertEqual((m.ROOT/'old.php').read_bytes(),b'old');self.assertFalse((m.ROOT/'added.php').exists())
  self.assertEqual(m.load_state()['status'],'AUTO_ROLLED_BACK')
 def test_corrupt_backup_refused(self):
  self.run_action('apply');(m.STATE/'before.json').write_bytes(b'{}')
  with self.assertRaisesRegex(RuntimeError,'BACKUP_CHECKSUM'):self.run_action('rollback')
  self.assertEqual((m.ROOT/'old.php').read_bytes(),b'new')
 def test_interrupted_apply_can_recover(self):
  data=json.dumps(m.snapshot(),sort_keys=True).encode();(m.STATE/'before.json').write_bytes(data)
  m.save_state({'status':'APPLYING','backup_sha256':m.digest(data)})
  (m.ROOT/'old.php').write_bytes(b'new');self.run_action('rollback')
  self.assertEqual((m.ROOT/'old.php').read_bytes(),b'old')
 def test_new_file_collision_and_symlink_refused(self):
  (m.ROOT/'added.php').symlink_to(m.ROOT/'old.php')
  with self.assertRaisesRegex(RuntimeError,'UNSAFE_FILE'):self.run_action('apply')
  self.assertEqual((m.ROOT/'old.php').read_bytes(),b'old')
 def test_atomic_pins_destination_when_parent_is_swapped(self):
  target=m.ROOT/'class';target.mkdir();outside=self.root/'outside';outside.mkdir()
  real_replace=m.os.replace
  def swapped(src,dst,**kwargs):
   target.rename(m.ROOT/'moved-class');target.symlink_to(outside,target_is_directory=True)
   return real_replace(src,dst,**kwargs)
  with patch.object(m.os,'replace',side_effect=swapped):m.atomic(target/'safe.php',b'reviewed')
  self.assertFalse((outside/'safe.php').exists())
  self.assertEqual((m.ROOT/'moved-class/safe.php').read_bytes(),b'reviewed')
 def test_missing_french_directory_is_scoped(self):
  (m.ROOT/'langs').mkdir()
  m.atomic(m.ROOT/'langs/fr_FR/new.lang',b'French')
  self.assertEqual((m.ROOT/'langs/fr_FR/new.lang').read_bytes(),b'French')
  with self.assertRaises(FileNotFoundError):m.atomic(m.ROOT/'unknown/new.php',b'bad')
 def test_recovery_identity_allows_unhealthy_running_container(self):
  raw=(json.dumps({'Running':True,'Health':{'Status':'unhealthy'}})+'|[]|{}|erp-staging').encode()
  with patch.object(m,'command',return_value=raw):
   with self.assertRaisesRegex(RuntimeError,'CONTAINER_NOT_HEALTHY'):m.inspect(m.CONTAINER)
   self.assertEqual(m.inspect(m.CONTAINER,False),([],{}))
 def test_exact_volume_owner_and_mode(self):
  from types import SimpleNamespace
  path=self.root/'ownership-check';path.mkdir()
  with patch.object(Path,'lstat',return_value=SimpleNamespace(st_uid=33,st_gid=33,st_mode=0o40755)):
   m.check_directory(path,33,33,0o755)
   with self.assertRaisesRegex(RuntimeError,'DIRECTORY_OWNERSHIP_MISMATCH'):m.check_directory(path,0,0,0o755)
  for info in [SimpleNamespace(st_uid=33,st_gid=33,st_mode=0o40777),SimpleNamespace(st_uid=33,st_gid=0,st_mode=0o40755),SimpleNamespace(st_uid=33,st_gid=33,st_mode=0o120755)]:
   with patch.object(Path,'lstat',return_value=info):
    with self.assertRaisesRegex(RuntimeError,'DIRECTORY_OWNERSHIP_MISMATCH'):m.check_directory(path,33,33,0o755)
 def test_exact_state_owner_and_mode(self):
  from types import SimpleNamespace
  with patch.object(Path,'lstat',return_value=SimpleNamespace(st_uid=0,st_gid=0,st_mode=0o40700)):
   m.check_directory(m.STATE,0,0,0o700)
  with patch.object(Path,'lstat',return_value=SimpleNamespace(st_uid=0,st_gid=0,st_mode=0o40755)):
   with self.assertRaisesRegex(RuntimeError,'DIRECTORY_OWNERSHIP_MISMATCH'):m.check_directory(m.STATE,0,0,0o700)
 def test_replay_refused(self):
  self.run_action('apply')
  with self.assertRaisesRegex(RuntimeError,'REPLAY_REFUSED'):self.run_action('apply')

if __name__=='__main__':unittest.main()
