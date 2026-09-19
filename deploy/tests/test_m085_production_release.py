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
  self.assertEqual(builder.sha(code),'c34e2562d154df2ba44592e182609e74efb0984ac8506589a6a55f8df867da58')
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
 def test_health_uses_fixed_container_socket(self):
  with patch.object(engine,'php',return_value=b'M085_LOGIN_OK') as php:
   engine.health();self.assertIn("fsockopen('127.0.0.1',80,",php.call_args.args[0]);self.assertNotIn('file_get_contents',php.call_args.args[0])
  with patch.object(engine,'php',return_value=b''):
   with self.assertRaisesRegex(RuntimeError,'LOGIN_HEALTH_FAILED'):engine.health()
 def test_real_php_health_with_url_fopen_disabled(self):
  import http.server,threading,subprocess
  source={}
  with patch.object(engine,'php',side_effect=lambda code:source.update(code=code) or b'M085_LOGIN_OK'):engine.health()
  for status,body,expected in [(200,b'<title>Dolibarr</title><input type="password">',0),(302,b'Dolibarr type="password"',15),(503,b'Dolibarr type="password"',15),(200,b'Welcome',15),(200,b'x'*1000000+b'Dolibarr type="password"',15)]:
   observed=[]
   class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
     observed.append((self.path,self.headers.get('Host'),self.headers.get('X-Forwarded-Proto')))
     self.send_response(status);self.end_headers()
     try:self.wfile.write(body)
     except (BrokenPipeError,ConnectionResetError):pass
    def log_message(self,*args):pass
   server=http.server.HTTPServer(('127.0.0.1',0),Handler);thread=threading.Thread(target=server.handle_request);thread.start()
   try:
    php=source['code'].replace("fsockopen('127.0.0.1',80,",f"fsockopen('127.0.0.1',{server.server_port},")
    result=subprocess.run(['php','-d','allow_url_fopen=0','-r',php],capture_output=True,timeout=20)
    self.assertEqual(result.returncode,expected,result.stderr)
    self.assertEqual(observed,[('/', 'erp.visiontrading.biz','https')])
    if expected==0:self.assertEqual(result.stdout,b'M085_LOGIN_OK')
   finally:thread.join(timeout=2);server.server_close()
 def test_health_patch_preserves_application_payload(self):
  import ast
  patcher=load('health_patch',ROOT/'tools/patch_m085_production_health.py')
  previous=builder.blob('dae2205','deploy/tools/build_m085_production_release.py').decode()
  previous_module=types.ModuleType('previous');exec(compile(previous,'previous-builder','exec'),previous_module.__dict__)
  before,oldpayload=previous_module.build()
  self.assertEqual(patcher.transform(before),code);self.assertEqual(oldpayload,payload)
  with self.assertRaisesRegex(RuntimeError,'PREDECESSOR_MISMATCH'):patcher.transform(before+b'\n')
if __name__=='__main__':unittest.main()
