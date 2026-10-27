#!/usr/bin/env python3
"""Pinned M116 UI-only release. No DB mutation, security changes or DB restore path."""
import fcntl,hashlib,importlib.util,io,json,os,stat,subprocess,sys,tarfile,tempfile,time
from pathlib import Path
STATE=Path('/root/chinaplanetos-m116-ui-20261027')
ARTIFACT=Path('/home/codex-deploy/staging-inbox/m116-ui-f6fefab.tar.gz')
ARTIFACT_SHA='9f04bd1ee392913dcb899d7c1148d42771a2afbe75ab111eef5f8ca454a00737'
MANIFEST_SHA='595ebb029fe0fb7b97bad19562a5d5a127eb34d00dc30579bebf030705989f1c'
COMMIT='f6fefab780d14dd863ca8c2a3857dfa421e87fbb'
FILES=['class/CustomerAccountExport.class.php','class/CustomerAccountView.class.php','class/CustomerAccountSummary.class.php','css/customer_account.css','js/boards.js','js/customer_account.js','js/customer_account_presentations.js','tasks/account.php','tasks/account_summary.php']
r=None

def need(ok,message):
 if not ok:raise RuntimeError(message)
def sha(b):return hashlib.sha256(b).hexdigest()
def save(name,obj):
 p=STATE/name;need(not p.is_symlink(),'State symlink');raw=json.dumps(obj,indent=2).encode();q=p.with_suffix(p.suffix+'.tmp');need(not q.exists(),'Unexpected state temporary file')
 with q.open('xb') as f:os.chmod(q,0o600);f.write(raw);f.flush();os.fsync(f.fileno())
 os.replace(q,p)
def init():
 global r
 p=Path('/root/m116_module_release_v2.py');need(sha(p.read_bytes())=='19793f4c88e64bd8b2197f937dc103205a61e05c6dc947f979b59ec5cb5d9158','Support helper drift')
 s=importlib.util.spec_from_file_location('m116',p);r=importlib.util.module_from_spec(s);s.loader.exec_module(r);r.init()
def payload():
 need(not ARTIFACT.is_symlink(),'Artifact symlink');raw_archive=ARTIFACT.read_bytes();need(sha(raw_archive)==ARTIFACT_SHA,'Artifact drift');data={}
 with tarfile.open(fileobj=io.BytesIO(raw_archive),mode='r:gz') as t:
  need(len(t.getmembers())==10,'Archive count')
  for x in t.getmembers():
   need(x.isfile() and x.size<2_000_000 and not x.name.startswith('/') and '..' not in Path(x.name).parts and x.name not in data,'Unsafe archive')
   data[x.name]=t.extractfile(x).read()
 raw=data.pop('manifest.json');need(sha(raw)==MANIFEST_SHA,'Manifest drift');m=json.loads(raw)
 need(m['commit']==COMMIT and not m['schema_changes'] and not m['production_fixtures'] and not m['permissions_changed'],'Scope drift')
 expected={'custom/chinaplanetoperations/'+f for f in FILES};need(set(data)==expected and {x['path'] for x in m['files']}==expected,'File allowlist')
 for x in m['files']:need(sha(data[x['path']])==x['sha256'],'Payload file drift')
 return m,data

def target(x):return r.m.guarded(r.m.ROOT/x['path'].removeprefix('custom/'))
def code_guard(manifest,after=False,mixed=False):
 for x in manifest['files']:
  p=target(x);h=sha(p.read_bytes()) if p.exists() else None
  need(h in (x['before_sha256'],x['sha256']) if mixed else h==x['sha256' if after else 'before_sha256'],'Source drift: '+x['path'])
def preserved(manifest,after=False):
 old,_=r.payload()
 for f in old['files']:
  if f['path']=='custom/chinaplanetoperations/js/boards.js':f['sha256']='becec0596fdde5a94f2190f8130a0987960f28f7805b57ca38129c368d712c65'
 if after:
  replacements={x['path']:x['sha256'] for x in manifest['files']}
  for f in old['files']:
   if f['path'] in replacements:f['sha256']=replacements[f['path']]
 return r.files_check(old,True)
def schema():
 s=r.schema();r.verify_schema(s);need(s['core']=='24.0.1' and s['flag']=='1' and all(s['extensions'].values()),'Runtime/schema changed');return s

def backup(manifest,s):
 need(not (STATE/'backup.json').exists(),'Existing backup; reconcile first');meta={}
 for x in manifest['files']:
  p=target(x)
  if not p.exists():continue
  st=p.stat();raw=p.read_bytes();need(sha(raw)==x['before_sha256'],'Backup source drift');q=STATE/'code'/x['path'];q.parent.mkdir(parents=True,exist_ok=True)
  with q.open('xb') as f:os.chmod(q,0o600);f.write(raw);f.flush();os.fsync(f.fileno())
  need(sha(q.read_bytes())==x['before_sha256'],'Backup readback failed');meta[x['path']]={'uid':st.st_uid,'gid':st.st_gid,'mode':stat.S_IMODE(st.st_mode)}
 db=s['database'];need(db.replace('_','').isalnum(),'DB identity');dump=STATE/'before-full-database.sql'
 cmd='set -eu; export MYSQL_PWD="${MYSQL_ROOT_PASSWORD:-${MARIADB_ROOT_PASSWORD:-}}"; if command -v mariadb-dump >/dev/null; then exec mariadb-dump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; else exec mysqldump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; fi'
 with dump.open('xb') as f:
  os.chmod(dump,0o600);p=subprocess.run(['docker','exec','vision_db','sh','-c',cmd,'m116-ui',db],stdout=f,stderr=subprocess.PIPE,timeout=180);f.flush();os.fsync(f.fileno())
 raw=dump.read_bytes();need(p.returncode==0 and len(raw)>1000 and b'-- Dump completed' in raw[-4096:] and b'CREATE TABLE' in raw,'DB backup invalid')
 receipt={'path':str(STATE),'database_bytes':len(raw),'database_sha256':sha(raw),'code_files':len(meta)};save('metadata.json',meta);save('backup.json',receipt);return receipt

def verify_backup(manifest):
 b=json.loads((STATE/'backup.json').read_text());need(sha((STATE/'before-full-database.sql').read_bytes())==b['database_sha256'],'DB backup corruption')
 for x in manifest['files']:
  if x['before_sha256'] is not None:need(sha((STATE/'code'/x['path']).read_bytes())==x['before_sha256'],'Code backup corruption')
 return b

def atomic(p,raw,uid,gid,mode):
 r.m.guarded(p);fd,name=tempfile.mkstemp(prefix='.'+p.name+'.m116-ui-',dir=p.parent);q=Path(name);identity=os.fstat(fd)
 try:
  with os.fdopen(fd,'wb') as f:
   f.write(raw);f.flush();os.fsync(f.fileno());os.fchown(f.fileno(),uid,gid);os.fchmod(f.fileno(),mode)
  os.replace(q,p)
 finally:
  try:
   st=q.lstat()
   if (st.st_dev,st.st_ino)==(identity.st_dev,identity.st_ino):q.unlink()
  except FileNotFoundError:pass

def rollback(manifest):
 need((STATE/'apply-started.json').exists(),'No apply marker');code_guard(manifest,mixed=True);verify_backup(manifest);meta=json.loads((STATE/'metadata.json').read_text())
 # Restore every existing caller before quarantining any new dependency.
 priority=['js/boards.js','tasks/account.php','js/customer_account.js','css/customer_account.css','class/CustomerAccountView.class.php','class/CustomerAccountExport.class.php']
 existing=[x for x in manifest['files'] if x['before_sha256'] is not None]
 existing.sort(key=lambda x:priority.index(x['path'].removeprefix('custom/chinaplanetoperations/')) if x['path'].removeprefix('custom/chinaplanetoperations/') in priority else len(priority))
 for x in existing:atomic(target(x),(STATE/'code'/x['path']).read_bytes(),**meta[x['path']])
 for x in manifest['files']:
  p=target(x)
  if x['before_sha256'] is None and p.exists():
   q=STATE/'quarantined-new-files'/x['path'];q.parent.mkdir(parents=True,exist_ok=True);need(not q.exists(),'Quarantine exists');os.replace(p,q)
 code_guard(manifest);preserved(manifest);r.health();save('rollback.json',{'restored':True,'database_restored':False,'schema_and_business_writes_retained':True})

def receipt(result,**fields):
 out={'result':result,'time_utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),'target':'srv1011043/vision_erp','commit':COMMIT,'artifact_sha256':ARTIFACT_SHA,'schema_changes':False,'permissions_or_credentials_changed':False,**fields}
 save(result+'.json',out);p=r.m.INBOX/'m116-ui-receipt.json';fd,name=tempfile.mkstemp(prefix='.m116-ui-receipt-',dir=p.parent)
 try:
  with os.fdopen(fd,'w') as f:f.write(json.dumps(out,indent=2));f.flush();os.fsync(f.fileno());os.fchmod(f.fileno(),0o644)
  os.replace(name,p) # Rename replaces a destination symlink without following it.
 finally:
  try:os.unlink(name)
  except FileNotFoundError:pass
 print(json.dumps(out))

def main(action):
 need(action in ['preflight','apply','verify','rollback'],'Unknown action');init();manifest,data=payload();STATE.mkdir(mode=0o700,exist_ok=True);need(not STATE.is_symlink(),'State symlink')
 with (r.STATE/'release.lock').open('a') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  if action=='rollback':rollback(manifest);receipt('M116_UI_CODE_ROLLBACK',database_restored=False);return
  after=action=='verify';code_guard(manifest,after);n=preserved(manifest,after);s=schema();r.health()
  if action=='verify':
   need((STATE/'applied.json').exists(),'No apply success marker');need(s==json.loads((STATE/'schema-before.json').read_text()),'Schema/rights drift');b=verify_backup(manifest);receipt('M116_UI_VERIFIED',runtime_files=9,preserved_source_files=n,backup=b,read_only=True);return
  need(not (STATE/'apply-started.json').exists(),'Prior apply; reconcile outcome')
  for x in manifest['files']:
   if x['path'].endswith('.php'):
    p=subprocess.run(['docker','exec','-i','vision_erp','php','-l'],input=data[x['path']],capture_output=True,timeout=25);need(p.returncode==0,'Live PHP lint failed: '+x['path'])
  if action=='preflight':save('preflight.json',s);receipt('M116_UI_PREFLIGHT_PASS',runtime_files=9,preserved_source_files=n);return
  need((STATE/'preflight.json').exists() and s==json.loads((STATE/'preflight.json').read_text()),'Preflight missing/changed');b=backup(manifest,s);code_guard(manifest);preserved(manifest);need(schema()==s,'Post-backup schema drift');save('schema-before.json',s)
  save('apply-started.json',{'commit':COMMIT,'time':time.time(),'backup':b})
  meta=json.loads((STATE/'metadata.json').read_text());ref=target(next(x for x in manifest['files'] if x['path'].endswith('/tasks/account.php'))).stat();default={'uid':ref.st_uid,'gid':ref.st_gid,'mode':stat.S_IMODE(ref.st_mode)}
  order=['class/CustomerAccountSummary.class.php','js/customer_account_presentations.js','tasks/account_summary.php','class/CustomerAccountExport.class.php','class/CustomerAccountView.class.php','css/customer_account.css','js/customer_account.js','tasks/account.php','js/boards.js']
  try:
   for name in order:
    x=next(x for x in manifest['files'] if x['path']=='custom/chinaplanetoperations/'+name);atomic(target(x),data[x['path']],**meta.get(x['path'],default))
   code_guard(manifest,True);preserved(manifest,True);need(schema()==s,'Schema/rights changed');r.m.topology();r.health();verify_backup(manifest);save('applied.json',{'commit':COMMIT,'time':time.time()});receipt('M116_UI_APPLIED',runtime_files=9,preserved_source_files=n,backup=b,authenticated_acceptance='PENDING')
  except Exception:
   rollback(manifest);raise

if __name__=='__main__':
 try:main(sys.argv[1] if len(sys.argv)==2 else '')
 except Exception as e:print('M116_UI_STOP '+str(e),file=sys.stderr);sys.exit(1)
