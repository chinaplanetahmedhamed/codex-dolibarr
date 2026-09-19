"""Synthetic production release fault and environment-isolation checks."""
import importlib.util
import json
from pathlib import Path
import types
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[1]
def load(name,path):
 spec=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
builder=load('production_builder',ROOT/'tools/build_m085_production_release.py');code,payload=builder.build()
engine=types.ModuleType('production_release');exec(compile(code,'generated-production-release','exec'),engine.__dict__)
base=load('base_tests',ROOT/'tests/test_m085_stage_release.py');base.m=engine
class ProductionReleaseTest(base.ReleaseTest):
 def setUp(self):
  super().setUp();engine.PAYLOAD['release']='M085-P01'
  p=patch.object(engine,'staging_candidate');p.start();self.addCleanup(p.stop)
 def test_recovery_identity_allows_unhealthy_running_container(self):
  raw=(json.dumps({'Running':True,'Health':{'Status':'unhealthy'}})+'|[]|{}|vision-os').encode()
  with patch.object(engine,'command',return_value=raw):
   with self.assertRaisesRegex(RuntimeError,'CONTAINER_NOT_HEALTHY'):engine.inspect(engine.CONTAINER)
   self.assertEqual(engine.inspect(engine.CONTAINER,False),([],{}))
class IsolationTest(unittest.TestCase):
 def test_exact_scope(self):
  self.assertEqual(set(payload['files']),set(builder.FILES));self.assertEqual(len(payload['files']),8)
  self.assertEqual(engine.CONTAINER,'vision_erp');self.assertEqual(engine.VOLUME,'vision-os_dolibarr_custom')
  self.assertEqual(str(engine.HELPER),'/usr/local/sbin/chinaplanetos-m085-production')
  self.assertEqual(builder.sha(code),'2ee4df2182edb27c650239aa18b73e076af40fbc1f965a720ef0e21fa5e4a059')
 def test_staging_container_identity_rejected(self):
  raw=(json.dumps({'Running':True})+'|[]|{}|erp-staging').encode()
  with patch.object(engine,'command',return_value=raw):
   with self.assertRaisesRegex(RuntimeError,'COMPOSE_IDENTITY_MISMATCH'):engine.inspect(engine.CONTAINER)
 def test_staging_bytes_gate(self):
  import base64
  paths=[]
  def read(path):
   paths.append(str(path));name=str(path).split('/chinaplanetoperations/')[1];return base64.b64decode(payload['files'][name]['data'])
  with patch.object(engine,'PAYLOAD',payload),patch.object(engine,'read',side_effect=read):engine.staging_candidate()
  self.assertEqual(len(paths),8);self.assertTrue(all('/erp-staging_erp-staging_custom/' in p for p in paths))
  with patch.object(engine,'PAYLOAD',payload),patch.object(engine,'read',return_value=b'drift'):
   with self.assertRaisesRegex(RuntimeError,'STAGING_CANDIDATE_MISMATCH'):engine.staging_candidate()
 def test_health_is_fixed_container_localhost_and_fails_closed(self):
  with patch.object(engine,'php',return_value=b'M085_LOGIN_OK') as php:
   engine.health();self.assertIn("'http://127.0.0.1/'",php.call_args.args[0]);self.assertIn("'follow_location'=>0",php.call_args.args[0])
  with patch.object(engine,'php',return_value=b''):
   with self.assertRaisesRegex(RuntimeError,'LOGIN_HEALTH_FAILED'):engine.health()
if __name__=='__main__':unittest.main()
