import copy,gzip,hashlib,importlib.util,io,json,os,stat,tarfile,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('controller',Path(__file__).with_name('controller_template.py'))
c=importlib.util.module_from_spec(spec);spec.loader.exec_module(c)

class ControllerTest(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.root=Path(self.temp.name).resolve();(self.root/'code').mkdir();self.path='code/a.php';(self.root/self.path).write_bytes(b'old');os.chmod(self.root/self.path,0o644)
  self.tree=c.Tree(self.root,[self.path,'code/new.php']);self.meta={'uid':os.getuid(),'gid':os.getgid(),'mode':'0644'}
 def tearDown(self):self.tree.close();self.temp.cleanup()
 def test_atomic_replace_and_restore(self):
  self.tree.replace(self.path,b'new',self.meta,{c.sha(b'old')});self.assertEqual((self.root/self.path).read_bytes(),b'new');self.assertEqual(stat.S_IMODE((self.root/self.path).stat().st_mode),0o644)
  self.tree.replace(self.path,b'old',self.meta,{c.sha(b'new')});self.assertEqual((self.root/self.path).read_bytes(),b'old')
 def test_drift_refused(self):
  with self.assertRaises(c.Refused):self.tree.replace(self.path,b'new',self.meta,{'bad'})
  self.assertEqual((self.root/self.path).read_bytes(),b'old')
 def test_traversal_refused(self):
  with self.assertRaises(c.Refused):self.tree.read('../outside')
 def test_symlink_file_refused(self):
  (self.root/'code/new.php').symlink_to(self.root/self.path)
  with self.assertRaises(OSError):self.tree.read('code/new.php')
 def test_symlink_parent_refused(self):
  (self.root/'other').symlink_to(self.root/'code');t=c.Tree(self.root,['other/a.php'])
  try:
   with self.assertRaises(OSError):t.read('other/a.php')
  finally:t.close()
 def test_temp_collision_not_removed(self):
  temp=self.root/'code/.a.php.boards-release-next';temp.write_bytes(b'human')
  with self.assertRaises(FileExistsError):self.tree.replace(self.path,b'new',self.meta,{c.sha(b'old')})
  self.assertEqual(temp.read_bytes(),b'human')
 def test_failed_replace_cleans_own_temp(self):
  with patch.object(c.os,'replace',side_effect=OSError('fixture failure')):
   with self.assertRaises(OSError):self.tree.replace(self.path,b'new',self.meta,{c.sha(b'old')})
  self.assertFalse((self.root/'code/.a.php.boards-release-next').exists());self.assertEqual((self.root/self.path).read_bytes(),b'old')
 def test_own_new_code_only_removal(self):
  self.tree.replace('code/new.php',b'new',self.meta,{None});self.tree.remove_new('code/new.php',{c.sha(b'new')});self.assertFalse((self.root/'code/new.php').exists())
 def release(self):
  r=object.__new__(c.Release);r.plan={'files':[{'path':p,'before_sha256':c.sha(b'old')if p!= 'code/new.php'else None,'sha256':c.sha(b'new')}for p in [c.ENTRIES[0],self.path,'code/new.php']]};r.saved=[];r.save_state=lambda v:r.saved.append(copy.deepcopy(v));r.native=lambda action=None:{'schema':{},'protected':{'rows':'unchanged'}};r.resources=lambda:None;r.check_sources=lambda **kw:None;r.health=lambda:None
  return r
 def test_restore_keeps_entries_last_new_dependencies_removed_after_old_callers(self):
  r=self.release();order=[];r.tree=type('T',(),{'read':lambda _,p:(b'new',None),'replace':lambda _,p,*a:order.append(('restore',p)),'remove_new':lambda _,p,*a:order.append(('remove',p))})();v={'before_native':{'protected':{'rows':'unchanged'}},'originals':{p:{'name':'old','sha256':item['before_sha256'],'metadata':self.meta}for item in r.plan['files']for p in[item['path']]}};(self.root/'old').write_bytes(b'old')
  with patch.object(c,'STATE',self.root):r.restore_before_exposure(v)
  self.assertEqual(order,[('restore',self.path),('remove','code/new.php'),('restore',c.ENTRIES[0])]);self.assertEqual(v['phase'],'failed_restored_before_exposure')
 def test_feature_data_blocks_old_parser_rollback(self):
  r=self.release();r.native=lambda action=None:{'schema':{c.TABLES[0]:{'rows':1}},'protected':{'rows':'unchanged'}}
  with self.assertRaises(c.Refused):r.restore_before_exposure({'before_native':{'protected':{'rows':'unchanged'}}})
  self.assertEqual(r.saved[-1]['phase'],'failed_retained_reconciliation_required')
 def test_backup_failure_precedes_application_writes(self):
  r=self.release();r.lease=lambda:{};r.preflight=lambda:{};r.backup=lambda *a:(_ for _ in ()).throw(c.Refused('backup fail'))
  with self.assertRaises(c.Refused):r.apply()
  self.assertFalse(r.saved)
 def test_apply_failure_and_rollback_failure_both_preserved(self):
  r=self.release();r.lease=lambda:{};r.preflight=lambda:{};v={};r.backup=lambda *a:v;r.tree=type('T',(),{'replace':lambda *a:(_ for _ in ()).throw(ValueError('apply'))})();r.restore_before_exposure=lambda v:(_ for _ in ()).throw(OSError('rollback'))
  with self.assertRaises(ValueError):r.apply()
  self.assertEqual(v['failure'],'ValueError');self.assertEqual(v['rollback_failure'],'OSError');self.assertEqual(v['phase'],'failed_retained_reconciliation_required')
 def test_failure_after_first_entry_exposure_never_restores_old_parser(self):
  r=self.release();r.plan['files']=[{'path':p,'before_sha256':c.sha(b'old'),'sha256':c.sha(b'new')}for p in c.ENTRIES]+[{'path':self.path,'before_sha256':c.sha(b'old'),'sha256':c.sha(b'new')}];r.files={'runtime/'+p:b'new'for p in c.ENTRIES+(self.path,)};r.lease=lambda:{};r.preflight=lambda:{};v={};r.backup=lambda *a:v;r.schema_ready=lambda v:None;r.verify_native=lambda *a:None
  def native(action=None):return {'protected':{}} 
  r.native=native;r.preflight=lambda:{'protected':{}};opened=[];restored=[];r.restore_before_exposure=lambda v:restored.append(True)
  def replace(path,raw,*args):
   if path in c.ENTRIES and raw==b'new':
    opened.append(path)
    if len(opened)==2:raise ValueError('after first exposure')
  r.tree=type('T',(),{'replace':lambda _,*args:replace(*args)})()
  with self.assertRaises(ValueError):r.apply()
  self.assertEqual(len(opened),2);self.assertFalse(restored);self.assertEqual(v['phase'],'failed_retained_reconciliation_required')
 def test_mid_fallback_failure_restores_coherent_new_ui(self):
  r=self.release();second='code/new.php';self.tree.replace(second,b'old',self.meta,{None});r.plan={'fallback':[self.path,second],'files':[{'path':p,'sha256':c.sha(b'old'),**self.meta}for p in [self.path,second]]};r.files={'fallback/'+p:b'fallback'for p in r.plan['fallback']};value={'phase':'applied'};r.verify=lambda:value;r.tree=self.tree;original=self.tree.replace;failed=[False]
  def replace(path,raw,*args):
   if path==second and raw==b'fallback'and not failed[0]:failed[0]=True;raise ValueError('mid fallback')
   return original(path,raw,*args)
  with patch.object(c,'STATE',self.root),patch.object(self.tree,'replace',side_effect=replace):
   with self.assertRaises(ValueError):r.rollback_ui()
  self.assertEqual((self.root/self.path).read_bytes(),b'old');self.assertEqual((self.root/second).read_bytes(),b'old');self.assertEqual(value['phase'],'applied')
 def test_concurrent_ui_edit_between_verify_and_backup_is_retained(self):
  r=self.release();r.plan={'fallback':[self.path],'files':[{'path':self.path,'sha256':c.sha(b'old'),**self.meta}]};r.verify=lambda:{'phase':'applied'};r.tree=self.tree;(self.root/self.path).write_bytes(b'human edit')
  with patch.object(c,'STATE',self.root):
   with self.assertRaises(c.Refused):r.rollback_ui()
  self.assertEqual((self.root/self.path).read_bytes(),b'human edit');self.assertFalse((self.root/'ui-before-0').exists())
 def test_backup_stream_deadline_covers_reads(self):
  source=Path(c.__file__).read_text();self.assertIn('streams.select(min(remaining,1))',source);self.assertIn("need(remaining>0,'Private backup stream deadline exceeded",source);self.assertNotIn('block=process.stdout.read',source)
 def test_shared_backup_lock_and_receipt_identity_contract(self):
  self.assertEqual(len(c.SHARED_LOCKS),13);self.assertEqual(c.SHARED_LOCKS[-1],Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-menu-r13/lock'))
  source=Path(c.__file__).read_text();self.assertIn("'run_id':value['lease']['run_id']",source);self.assertIn("'workflow_commit':value['lease']['workflow_commit']",source);self.assertIn("time.time()+300<lease['expires']",source)
 def test_cancelled_workflow_hold_fences_not_started_apply(self):
  r=self.release();r.inbox=self.tree.fd;(self.root/'reconciliation-hold.json').write_text('{}')
  with self.assertRaises(c.Refused):r.lease()
 def test_database_native_backup_avoids_disabled_php_execution(self):
  import subprocess
  script=c.backup_command('a'*64);self.assertEqual(subprocess.run(['sh','-n'],input=script,capture_output=True).returncode,0)
  self.assertIn(b'exec mariadb-dump',script);self.assertIn(b'export MYSQL_PWD="$p"',script);self.assertNotIn(b'--password',script)
  php=Path(c.__file__).with_name('database_backup.php').read_text();self.assertNotIn('proc_open(',php);self.assertNotIn('disable_functions',php)
  with self.assertRaises(c.Refused):c.backup_command('shell;injection')
 def backup_case(self,style='MARIADB',wrong_db=False,wrong_user=False,missing=False):
  import subprocess
  binary=self.root/'bin';binary.mkdir(exist_ok=True)
  (binary/'sha256sum').write_text('#!/usr/bin/env python3\nimport hashlib,sys\nprint(hashlib.sha256(sys.stdin.buffer.read()).hexdigest()+"  -")\n');os.chmod(binary/'sha256sum',0o755)
  (binary/'mariadb-dump').write_text('#!/usr/bin/env python3\nimport os,sys,json\nprint(json.dumps({"args":sys.argv[1:],"password_match":os.environ.get("MYSQL_PWD")==os.environ.get("FIXTURE_EXPECTED")}))\n');os.chmod(binary/'mariadb-dump',0o755)
  password='synthetic;$(touch '+str(self.root/'unexpected')+');"literal'
  env={'PATH':str(binary)+':'+os.environ['PATH'],'FIXTURE_EXPECTED':password,style+'_DATABASE':'wrong'if wrong_db else'dolibarr_db',style+'_USER':'wrong'if wrong_user else'fixture',style+'_PASSWORD':''if missing else password}
  result=subprocess.run(['sh'],input=c.backup_command(c.sha(b'fixture')),capture_output=True,env=env)
  self.assertNotIn(password.encode(),result.stdout+result.stderr);self.assertFalse((self.root/'unexpected').exists())
  if wrong_db or wrong_user or missing:self.assertNotEqual(result.returncode,0);self.assertFalse(result.stdout)
  else:
   self.assertEqual(result.returncode,0);reply=json.loads(result.stdout);self.assertTrue(reply['password_match']);self.assertIn('--single-transaction',reply['args']);self.assertFalse(any('--password'in a for a in reply['args']))
 def test_db_backup_mariadb_environment(self):self.backup_case()
 def test_db_backup_mysql_environment_fallback(self):self.backup_case(style='MYSQL')
 def test_db_backup_wrong_database_rejected(self):self.backup_case(wrong_db=True)
 def test_db_backup_wrong_user_rejected(self):self.backup_case(wrong_user=True)
 def test_db_backup_missing_password_rejected(self):self.backup_case(missing=True)
 def test_unsealed_package_refused(self):
  with self.assertRaises(c.Refused):c.unpack({},b'bad')
 def test_fixed_fallback_and_root_private_lock_contract(self):
  source=Path(c.__file__).read_text();self.assertIn("set(plan['fallback'])==set(FALLBACK)",source);self.assertIn("STATE.with_suffix('.lock')",source);self.assertNotIn("lock=os.open(INBOX",source)

if __name__=='__main__':unittest.main()
