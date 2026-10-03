import importlib.util,json,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
OUT=Path(__file__).parent
def load(env):
 p=OUT/(env+'-release.py');s=importlib.util.spec_from_file_location(env,p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
class ReleaseGuards(unittest.TestCase):
 def test_payload_checksums_scope(self):
  for env in ('staging','production'):
   m=load(env);m.validate_payload();self.assertEqual(len(m.PAYLOAD['files']),5);self.assertEqual(len(m.PAYLOAD['prerequisites']),0)
 def test_exact_container_identity_and_drift(self):
  for env in ('staging','production'):
   m=load(env);p=[json.dumps({'Running':True}), '[]',json.dumps({'80/tcp':[{'HostIp':h,'HostPort':p} for h,p in m.BINDINGS]}),json.dumps({'com.docker.compose.project':m.PROJECT} if m.PROJECT else None),m.IMAGE_ID,m.IMAGE]
   with patch.object(m,'command',return_value='|'.join(p).encode()):m.inspect(m.CONTAINER)
   p[4]='sha256:unexpected'
   with patch.object(m,'command',return_value='|'.join(p).encode()),self.assertRaises(RuntimeError):m.inspect(m.CONTAINER)
 def test_baseline_drift_refused(self):
  for env in ('staging','production'):
   m=load(env)
   with patch.object(m,'current',return_value='unexpected'),self.assertRaises(RuntimeError):m.compare()
 def test_unauthorized_action_refused(self):
  for env in ('staging','production'):
   m=load(env)
   with patch.object(m.os,'geteuid',return_value=0),patch.object(m.sys,'argv',['release','install']),self.assertRaises(RuntimeError):m.main()
 def test_preflight_has_no_state_write(self):
  for env in ('staging','production'):
   m=load(env)
   with patch.object(m.os,'geteuid',return_value=0),patch.object(m.sys,'argv',['release','preflight']),patch.object(m,'preflight'),patch.object(m,'atomic') as a,patch('builtins.print'):
    m.main();a.assert_not_called()
 def test_postapply_failure_restores_exact_backup(self):
  m=load('staging')
  with tempfile.TemporaryDirectory() as d:
   m.STATE=Path(d)/'private';before={n:None for n in m.PAYLOAD['files']};original_atomic=m.atomic
   def scoped_atomic(path,*args,**kwargs):
    if m.STATE in path.parents:path.write_bytes(args[0])
   with patch.object(m.os,'geteuid',return_value=0),patch.object(m.sys,'argv',['release','apply']),patch.object(m,'preflight',side_effect=[None,None,RuntimeError('postapply')]),patch.object(m,'state_directory_valid',return_value=True),patch.object(m,'regular'),patch.object(m,'compare'),patch.object(m,'health'),patch.object(m,'snapshot',return_value=before),patch.object(m,'atomic',side_effect=scoped_atomic),patch.object(m,'restore') as restore,self.assertRaises(RuntimeError):m.main()
   restore.assert_called_once_with(before);self.assertEqual(json.loads((m.STATE/'state.json').read_text())['status'],'AUTO_ROLLED_BACK')
 def test_database_and_privilege_changes_absent(self):
  for env in ('staging','production'):
   text=(OUT/(env+'-release.py')).read_text()
   for prohibited in ('def install(', 'def schema(', 'sudoers', "'restart'", "'create'", "'conf.php'"):self.assertNotIn(prohibited,text)
if __name__=='__main__':unittest.main()
