import importlib.util,tempfile,json,hashlib,stat,types,shutil,os,unittest
from pathlib import Path
from unittest.mock import patch
p=Path(__file__).with_name('release.py');s=importlib.util.spec_from_file_location('ui',p);u=importlib.util.module_from_spec(s);s.loader.exec_module(u)
class Tests(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.root=Path(self.tmp.name);u.STATE=self.root/'state';u.STATE.mkdir();self.dest=self.root/'custom';self.dest.mkdir();u.r=types.SimpleNamespace(m=types.SimpleNamespace(ROOT=self.dest,guarded=lambda p:p),atomic=lambda p,b,uid,gid,mode:p.write_bytes(b),health=lambda:None);u.preserved=lambda *a:93
  self.m={'files':[{'path':'custom/chinaplanetoperations/old.php','before_sha256':u.sha(b'old'),'sha256':u.sha(b'new')},{'path':'custom/chinaplanetoperations/new.php','before_sha256':None,'sha256':u.sha(b'added')}]};self.d=self.dest/'chinaplanetoperations';self.d.mkdir();(self.d/'old.php').write_bytes(b'old')
 def tearDown(self):self.tmp.cleanup()
 def backup(self):
  q=u.STATE/'code/custom/chinaplanetoperations';q.mkdir(parents=True);(q/'old.php').write_bytes(b'old');(u.STATE/'before-full-database.sql').write_bytes(b'private backup');u.save('backup.json',{'database_sha256':u.sha(b'private backup')});u.save('metadata.json',{'custom/chinaplanetoperations/old.php':{'uid':os.getuid(),'gid':os.getgid(),'mode':0o644}});u.save('apply-started.json',{})
 def test_guard_stops_drift(self):
  u.code_guard(self.m);(self.d/'old.php').write_bytes(b'unrelated');self.assertRaises(RuntimeError,u.code_guard,self.m)
 def test_new_path_must_be_absent(self):
  (self.d/'new.php').write_bytes(b'unrelated');self.assertRaises(RuntimeError,u.code_guard,self.m)
 def test_rollback_restores_code_quarantines_new_retains_db(self):
  self.backup();(self.d/'old.php').write_bytes(b'new');(self.d/'new.php').write_bytes(b'added');u.rollback(self.m);self.assertEqual((self.d/'old.php').read_bytes(),b'old');self.assertFalse((self.d/'new.php').exists());self.assertEqual((u.STATE/'quarantined-new-files/custom/chinaplanetoperations/new.php').read_bytes(),b'added');self.assertEqual((u.STATE/'before-full-database.sql').read_bytes(),b'private backup')
 def test_rollback_refuses_newer_changes(self):
  self.backup();(self.d/'old.php').write_bytes(b'newer');self.assertRaises(RuntimeError,u.rollback,self.m);self.assertEqual((self.d/'old.php').read_bytes(),b'newer')
 def test_backup_corruption_stops_before_restore(self):
  self.backup();(self.d/'old.php').write_bytes(b'new');(u.STATE/'before-full-database.sql').write_bytes(b'bad');self.assertRaises(RuntimeError,u.rollback,self.m);self.assertEqual((self.d/'old.php').read_bytes(),b'new')
 def test_unstarted_rollback_rejected(self):self.assertRaises(RuntimeError,u.rollback,self.m)
 def test_archive_pins_and_exact_allowlist(self):
  u.ARTIFACT=Path(__file__).with_name(u.ARTIFACT.name);m,data=u.payload();self.assertEqual(len(data),9);self.assertEqual(m['commit'],u.COMMIT)
 def test_atomic_failure_cleans_owned_temp_and_allows_retry(self):
  for call in ['fsync','fchown','fchmod','replace']:
   p=self.d/'old.php';p.write_bytes(b'old')
   with patch.object(u.os,call,side_effect=OSError('injected '+call)):
    self.assertRaises(OSError,u.atomic,p,b'new',os.getuid(),os.getgid(),0o644)
   self.assertEqual(p.read_bytes(),b'old');self.assertFalse(list(self.d.glob('.*m116-ui-*')))
   u.atomic(p,b'new',os.getuid(),os.getgid(),0o644);self.assertEqual(p.read_bytes(),b'new')
 def test_public_receipt_replaces_symlink_without_touching_target(self):
  inbox=self.root/'inbox';inbox.mkdir();u.r.m.INBOX=inbox;victim=self.root/'private';victim.write_bytes(b'KEEP');(inbox/'m116-ui-receipt.json').symlink_to(victim)
  u.receipt('LOCAL_TEST');self.assertEqual(victim.read_bytes(),b'KEEP');self.assertFalse((inbox/'m116-ui-receipt.json').is_symlink())
 def test_rollback_keeps_new_dependencies_until_all_callers_restored(self):
  self.backup();(self.d/'old.php').write_bytes(b'new');(self.d/'new.php').write_bytes(b'added');real=u.atomic;seen=[]
  def tracked(*args,**kw):seen.append((self.d/'new.php').exists());return real(*args,**kw)
  with patch.object(u,'atomic',side_effect=tracked):u.rollback(self.m)
  self.assertTrue(all(seen));self.assertFalse((self.d/'new.php').exists())
if __name__=='__main__':unittest.main()
