import importlib.util
from pathlib import Path
import types
import unittest
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
def load(name,path):
 spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
builder=load('security_builder',ROOT/'tools/build_security_20260920_release.py')
for environment in ('staging','production'):
 code,payload=builder.build(environment)
 engine=types.ModuleType(environment);exec(compile(code,'generated-'+environment,'exec'),engine.__dict__)
 base=load('base_'+environment,ROOT/'tests/test_m085_stage_release.py');base.m=engine
 def setup(self,base=base,engine=engine):
  base.ReleaseTest.setUp(self);engine.PAYLOAD['release']='SEC20260920'
  if hasattr(engine,'staging_candidate'):
   p=patch.object(engine,'staging_candidate');p.start();self.addCleanup(p.stop)
 def identity(self,engine=engine,environment=environment):
  import json
  project='vision-os' if environment=='production' else 'erp-staging'
  raw=(json.dumps({'Running':True,'Health':{'Status':'unhealthy'}})+'|[]|{}|'+project).encode()
  with patch.object(engine,'command',return_value=raw):
   with self.assertRaisesRegex(RuntimeError,'CONTAINER_NOT_HEALTHY'):engine.inspect(engine.CONTAINER)
   self.assertEqual(engine.inspect(engine.CONTAINER,False),([],{}))
 globals()[environment.title()+'ReleaseTest']=type(environment.title()+'ReleaseTest',(base.ReleaseTest,),{'setUp':setup,'test_recovery_identity_allows_unhealthy_running_container':identity})
class ScopeTest(unittest.TestCase):
 def test_exact_two_file_scope(self):
  for environment in ('staging','production'):
   code,payload=builder.build(environment);engine=types.ModuleType('scope');exec(compile(code,'scope','exec'),engine.__dict__)
   self.assertEqual(set(payload['files']),{'ajax/move.php','class/cpkanbancard.class.php'})
   self.assertEqual(engine.ROOT.name,'cpkanban')
   self.assertEqual(str(engine.HELPER),'/usr/local/sbin/chinaplanetos-security-20260920-'+environment)
   self.assertIn("DOL_VERSION !== '23.0.3'",code.decode())
   if environment=='production':
    self.assertEqual(engine.CONTAINER,'vision_erp')
    import base64
    def read(path):return base64.b64decode(payload['files'][str(path).split('/cpkanban/')[1]]['data'])
    with patch.object(engine,'read',side_effect=read):engine.staging_candidate()
    with patch.object(engine,'read',return_value=b'drift'):
     with self.assertRaisesRegex(RuntimeError,'STAGING_CANDIDATE_MISMATCH'):engine.staging_candidate()
   else:self.assertEqual(engine.CONTAINER,'erp-staging-dolibarr-1')
if __name__=='__main__':unittest.main()
