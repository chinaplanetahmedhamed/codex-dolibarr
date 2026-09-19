#!/usr/bin/env python3
"""Read-only diagnosis of the fixed M085 production preflight. No secret output."""
import contextlib,hashlib,importlib.machinery,importlib.util,io,json,os,stat,subprocess,sys
sys.dont_write_bytecode=True
from pathlib import Path
path=Path('/usr/local/sbin/chinaplanetos-m085-production')
st=path.lstat()
if os.geteuid()!=0 or not stat.S_ISREG(st.st_mode) or st.st_uid!=0 or st.st_nlink!=1 or hashlib.sha256(path.read_bytes()).hexdigest()!='2ee4df2182edb27c650239aa18b73e076af40fbc1f965a720ef0e21fa5e4a059':raise SystemExit('FIXED_HELPER_IDENTITY_REQUIRED')
loader=importlib.machinery.SourceFileLoader('m085fixed',str(path));spec=importlib.util.spec_from_loader(loader.name,loader);m=importlib.util.module_from_spec(spec)
with contextlib.redirect_stdout(io.StringIO()):loader.exec_module(m)
errors=[]
def command(args,data=None):
 p=subprocess.run(args,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=45,env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'})
 if p.returncode:
  errors.append({'operation':'docker_inspect' if 'inspect' in args else 'php_lint' if '-l' in args else 'php_probe','exit_code':p.returncode,'permission_denied':b'Permission denied' in p.stderr,'fatal_php':b'Fatal error' in p.stderr or b'Fatal error' in p.stdout})
  raise RuntimeError('FIXED_COMMAND_FAILED')
 return p.stdout
m.command=command
m.validate_payload()
for name in ['environment','compare','schema','lint','health','staging_candidate']:
 errors.clear()
 try:
  with contextlib.redirect_stdout(io.StringIO()):getattr(m,name)()
  print(json.dumps({'check':name,'result':'PASS'}))
 except Exception as e:
  print(json.dumps({'check':name,'result':'FAIL','code':str(e) if isinstance(e,RuntimeError) else type(e).__name__,'commands':errors}))
  if name=='environment':raise SystemExit(2)
# Only fixed non-secret HTTP diagnostics, inside the already-validated container.
code=r'''$ctx=stream_context_create(array('http'=>array('timeout'=>15,'follow_location'=>0,'ignore_errors'=>true)));$b=@file_get_contents('http://127.0.0.1/',false,$ctx);$h=isset($http_response_header[0])?$http_response_header[0]:'';preg_match('~^HTTP/\S+ ([0-9]{3})~',$h,$matches);echo json_encode(array('allow_url_fopen'=>(bool)ini_get('allow_url_fopen'),'http_status'=>isset($matches[1])?(int)$matches[1]:null,'bytes'=>$b===false?0:strlen($b),'password_field'=>$b!==false&&strpos($b,'type="password"')!==false,'dolibarr_marker'=>$b!==false&&strpos($b,'Dolibarr')!==false));'''
try:print(json.dumps({'http':json.loads(m.php(code))}))
except Exception:print(json.dumps({'http':'probe_failed'}))
print('M085_DIAG_READ_ONLY=YES APPLICATION_CHANGED=NO')
