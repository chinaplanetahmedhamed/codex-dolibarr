import os,socket,subprocess,json,hashlib,stat,pwd,datetime,re
from pathlib import Path
if os.geteuid()!=0 or socket.gethostname()!='srv1011043':raise RuntimeError('Host identity mismatch')
TARGETS={'backup':{'container':'erp-staging-dolibarr-1','id':'ed2c8761e65a0f9a7747eec302823e4e4dec9e2c769fd01db7b2fd6872bd3fed','image':'sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9','project':'erp-staging','volume':'erp-staging_erp-staging_custom','proxy':'6.conf','host':'backup-erp.visiontrading.biz'},'live':{'container':'vision_erp','id':'06275ef163d77db472886ec5a09cbafde062a007dbced035c78b9c71e62eb004','image':'sha256:a714a9cbf63460f99390d6f59cc2b334103634cff7d909a117e0b1fc4474746f','project':'<no value>','volume':'vision-os_dolibarr_custom','proxy':'2.conf','host':'erp.visiontrading.biz'}}
FILES=['chinaplanetoperations/class/InternalTaskBoardsService.class.php','chinaplanetoperations/js/boards.js','chinaplanetoperations/tasks/boards.php','chinaplanetoperations/langs/en_US/chinaplanetoperations.lang','chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang']
PROTECTED=['chinaplanetoperations/tasks/boards_api.php','chinaplanetoperations/class/InternalTaskAuthorizationService.class.php','chinaplanetoperations/class/InternalTaskBoardPolicy.class.php','chinaplanetoperations/class/InternalTaskWorkspaceService.class.php','chinaplanetoperations/class/SupplierWorkspaceService.class.php','chinaplanetoperations/class/CustomerAccountService.class.php']
FMT='{"id":"{{.Id}}","name":"{{.Name}}","running":{{.State.Running}},"image":"{{.Image}}","project":"{{index .Config.Labels "com.docker.compose.project"}}","mounts":{{json .Mounts}},"ports":{{json .NetworkSettings.Ports}}}'
def sha(b):return hashlib.sha256(b).hexdigest()
def collect():
 out={}
 for name,t in TARGETS.items():
  d=json.loads(subprocess.check_output(['docker','inspect','--format',FMT,t['container']],timeout=15))
  if (d['name'],d['id'],d['image'],d['project'],d['running'])!=('/'+t['container'],t['id'],t['image'],t['project'],True):raise RuntimeError(name+' container identity changed')
  roots=[m for m in d['mounts'] if m['Destination']=='/var/www/html/custom'];expected='/var/lib/docker/volumes/'+t['volume']+'/_data'
  if len(roots)!=1 or (roots[0]['Type'],roots[0].get('Name'),roots[0]['Source'],roots[0]['RW'])!=('volume',t['volume'],expected,True):raise RuntimeError(name+' custom mount changed')
  if name=='backup' and d['ports']['80/tcp']!=[{'HostIp':'127.0.0.1','HostPort':'18088'}]:raise RuntimeError('Backup public port mismatch')
  proxy=Path('/root/management/npm_data/nginx/proxy_host')/t['proxy'];p=proxy.read_text()
  if not re.search(r'server_name\s+'+re.escape(t['host'])+r'\s*;',p):raise RuntimeError('Proxy hostname mismatch')
  route=(r'set \$server\s+[\"\']erp-staging-dolibarr-1[\"\'];' if name=='backup' else r'set \$server\s+[\"\']31\.97\.158\.178[\"\'];')
  if not re.search(route,p):raise RuntimeError('Proxy target mismatch')
  f={}
  for rel in FILES+PROTECTED:
   path=Path(expected)/rel
   if not path.is_file() or path.is_symlink() or path.stat().st_nlink!=1 or not path.resolve().is_relative_to(expected):raise RuntimeError('Unsafe source path '+rel)
   a=path.stat();data=path.read_bytes();b=path.stat()
   if (a.st_ino,a.st_size,a.st_mtime_ns)!=(b.st_ino,b.st_size,b.st_mtime_ns):raise RuntimeError('Unstable source')
   f[rel]={'sha256':sha(data),'bytes':len(data),'mode':stat.S_IMODE(a.st_mode),'uid':a.st_uid,'gid':a.st_gid}
  php="echo json_encode(['version'=>(function(){require '/var/www/html/filefunc.inc.php';return DOL_VERSION;})(),'config_sha256'=>hash_file('sha256','/var/www/html/conf/conf.php'),'pdo_mysql'=>extension_loaded('pdo_mysql')]);"
  # Version is read from the deployed public version definition, not environment values.
  php="$s=file_get_contents('/var/www/html/filefunc.inc.php');preg_match(\"/define\\('DOL_VERSION',\\s*'([^']+)'/\",$s,$m);echo json_encode(['version'=>$m[1]??'unknown','config_sha256'=>hash_file('sha256','/var/www/html/conf/conf.php'),'pdo_mysql'=>extension_loaded('pdo_mysql')]);"
  meta=json.loads(subprocess.check_output(['docker','exec',t['container'],'php','-r',php],timeout=20))
  out[name]={'runtime':d,'custom_root':expected,'files':f,'proxy_sha256':sha(proxy.read_bytes()),'meta':meta}
 return out

import base64,io,tarfile,fcntl,sys,urllib.request,urllib.error,ssl,shutil
ARTIFACT_SHA='229979a962af27eeb7934aa36e1c321014a2a1cc6951f66aa770553ce8629f2a'
INVENTORY_SHA='1df96027c3c98df3b4c3b16fedaa81d50f5a5485e99625b5ca3f0fea3b69abd0'
ROOT=Path('/root/m141-general-board-staff-20261123')
PUBLIC=Path('/var/lib/codex-m141-release')
def pinned(path,digest):
 p=Path(path);s=p.lstat()
 if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or s.st_mode&0o022:raise RuntimeError('Unsafe release input')
 b=p.read_bytes()
 if sha(b)!=digest:raise RuntimeError('Release input checksum mismatch')
 return b
artifact=json.loads(pinned('/root/m141-artifact.json',ARTIFACT_SHA));baseline=json.loads(pinned(PUBLIC/'inventory.json',INVENTORY_SHA))['targets']
if artifact['mission']!='M141' or artifact['commit']!='4ed67298f3d0a6ec6ed8c930c7a1ef1f4dcf9590' or set(artifact['files'])!=set(FILES):raise RuntimeError('Artifact allowlist mismatch')
source={r:base64.b64decode(v['base64'],validate=True) for r,v in artifact['files'].items()}
if any(sha(source[r])!=artifact['files'][r]['sha256'] for r in FILES):raise RuntimeError('Source checksum mismatch')
test=base64.b64decode(artifact['test_base64'],validate=True)
if sha(test)!=artifact['test_sha256']:raise RuntimeError('Test checksum mismatch')
def runphp(name,program):
 r=subprocess.run(['docker','exec','-i',TARGETS[name]['container'],'php'],input=program,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=90)
 if r.returncode:
  # Error details retained root-only; no credentials or private records in output.
  ROOT.mkdir(mode=0o700,exist_ok=True);p=ROOT/(name+'-php-error.txt');p.write_bytes(r.stderr+r.stdout);p.chmod(0o600)
  raise RuntimeError(name+' PHP validation failed (private diagnostic saved)')
 return r.stdout

def synthetic(name):
 code=source['chinaplanetoperations/class/InternalTaskBoardsService.class.php'].replace(b'__DIR__',b"'/var/www/html/custom/chinaplanetoperations/class'")
 fixture=test.replace(b"require_once dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';",b"eval('?>'.base64_decode('"+base64.b64encode(code)+b"'));")
 if fixture==test:raise RuntimeError('Synthetic test injection mismatch')
 boot=b"<?php require '/var/www/html/conf/conf.php'; $m141SyntheticPdo=new PDO('mysql:host='.$dolibarr_main_db_host.';port='.($dolibarr_main_db_port?:3306).';dbname='.$dolibarr_main_db_name,$dolibarr_main_db_user,$dolibarr_main_db_pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $m141TemporaryTables=true; ?>"
 result=runphp(name,boot+fixture).decode()
 if result.strip()!='PASS M141: 49 native temporary-table permission/persistence checks':raise RuntimeError('Synthetic acceptance result changed')
 return 49

def identity(name,new=False):
 d=collect()[name];old=baseline[name]
 def normalized(runtime):
  # Docker emits mount and address bindings in nondeterministic array order.
  # Keep every field/value, comparing the same sets rather than their order.
  x=json.loads(json.dumps(runtime));x['mounts']=sorted(x['mounts'],key=lambda v:json.dumps(v,sort_keys=True))
  x['ports']={k:(sorted(v,key=lambda b:json.dumps(b,sort_keys=True)) if isinstance(v,list) else v) for k,v in x['ports'].items()}
  return x
 if normalized(d['runtime'])!=normalized(old['runtime']) or d['meta']['config_sha256']!=old['meta']['config_sha256'] or d['proxy_sha256']!=old['proxy_sha256']:raise RuntimeError(name+' runtime/config/proxy changed since inventory')
 for rel in PROTECTED:
  if d['files'][rel]!=old['files'][rel]:raise RuntimeError('Protected source drift '+rel)
 for rel in FILES:
  expected=artifact['files'][rel]['sha256'] if new else artifact['files'][rel]['predecessor']
  if d['files'][rel]['sha256']!=expected:raise RuntimeError('Unexpected release predecessor '+name+' '+rel)
  if any(d['files'][rel][k]!=old['files'][rel][k] for k in ['uid','gid','mode']):raise RuntimeError('Source ownership changed')
 return d

def lint(name):
 for rel in FILES:
  if rel.endswith('.php'):
   r=subprocess.run(['docker','exec','-i',TARGETS[name]['container'],'php','-l'],input=source[rel],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=20)
   if r.returncode:raise RuntimeError('PHP lint failed '+rel)

def functional(name):
 probe=b'''<?php
chdir('/var/www/html');$_SERVER['DOCUMENT_ROOT']='/var/www/html';$_SERVER['HTTP_HOST']='HOST';require '/var/www/html/master.inc.php';
$user=new User($db);if($user->fetch(1)<=0||empty($user->admin)||!empty($user->socid))throw new RuntimeException('Native administrator identity');$user->getrights();
require_once '/var/www/html/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';
$s=new InternalTaskBoardsService($db,$user,(int)$conf->entity);$w=$s->workspace(3);if($w['board']['staff_only_members']!==true)throw new RuntimeException('General Board scope');
$expected=[];$r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE entity='.(int)$conf->entity.' AND statut=1 AND employee=1 AND COALESCE(fk_soc,0)=0');while($o=$db->fetch_object($r))$expected[]=(int)$o->rowid;
$got=array_map('intval',array_column($w['people'],'rowid'));sort($expected);sort($got);if($expected!==$got)throw new RuntimeException('Picker employee scope mismatch');
$langs->load('chinaplanetoperations@chinaplanetoperations');if($langs->trans('CPOBoardStaffMember')==='CPOBoardStaffMember')throw new RuntimeException('Language keys missing');
$external=clone $user;$external->socid=999999;if((new InternalTaskAuthorizationService($db,$external,(int)$conf->entity))->canOpenBoard())throw new RuntimeException('External actor allowed');
echo json_encode(['native_board'=>3,'staff_only'=>true,'employee_options'=>count($got),'internal_options'=>count($s->people()),'external_actor_denied'=>true,'version'=>DOL_VERSION,'business_writes'=>false]);
'''.replace(b'HOST',TARGETS[name]['host'].encode())
 data=json.loads(runphp(name,probe))
 if data['version']!='24.0.1':raise RuntimeError('Unexpected Dolibarr version')
 return data

def health(name):
 host=TARGETS[name]['host'];port='18088' if name=='backup' else '8080'
 req=urllib.request.Request('http://127.0.0.1:'+port+'/custom/chinaplanetoperations/tasks/boards.php',headers={'Host':host})
 with urllib.request.urlopen(req,timeout=25) as r:b=r.read()
 if b'cb-config' in b or b'name="password"' not in b or b'Fatal error' in b:raise RuntimeError('Anonymous board privacy/health failed')
 # Match the actual cpo_asset URL emitted by Boards, including its new mtime.
 asset_version=str(int((Path(baseline[name]['custom_root'])/'chinaplanetoperations/js/boards.js').stat().st_mtime))
 req=urllib.request.Request('https://'+host+'/custom/chinaplanetoperations/js/boards.js?v='+asset_version,headers={'Accept-Encoding':'identity','User-Agent':'Mozilla/5.0','Cache-Control':'no-cache'})
 with urllib.request.urlopen(req,timeout=30,context=ssl.create_default_context()) as r:b=r.read()
 if sha(b)!=artifact['files']['chinaplanetoperations/js/boards.js']['sha256']:raise RuntimeError('Served JS bytes differ')
 return {'anonymous_login_required':True,'public_TLS_asset_hash':True}

def receipt(name,value):
 value.update({'mission':'M141','source_commit':artifact['commit'],'artifact_sha256':ARTIFACT_SHA,'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()})
 p=PUBLIC/(name+'-release.json');temp=p.with_suffix('.tmp')
 with open(temp,'x') as f:json.dump(value,f,indent=2);f.flush();os.fsync(f.fileno())
 os.chown(temp,0,pwd.getpwnam('codex-deploy').pw_gid);os.chmod(temp,0o440);os.replace(temp,p)

def restore(name,backup,changed):
 current=collect()[name];root=Path(current['custom_root'])
 with tarfile.open(backup,'r:gz') as t:
  old={m.name:m for m in t}
  for rel in reversed(changed):
   p=root/rel
   if sha(p.read_bytes())!=artifact['files'][rel]['sha256']:raise RuntimeError('Rollback refused subsequent edit')
   m=old[rel];data=t.extractfile(m).read()
   temp=p.with_name('.m141-rollback-'+p.name);fd=os.open(temp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,m.mode)
   with os.fdopen(fd,'wb') as f:f.write(data);f.flush();os.fsync(f.fileno())
   os.chown(temp,m.uid,m.gid);os.chmod(temp,m.mode);os.replace(temp,p)
 identity(name,False)

name,action=sys.argv[1:]
if name not in TARGETS or action not in ['preflight','apply','verify','rollback']:raise RuntimeError('Fixed target/action required')
ROOT.mkdir(mode=0o700,exist_ok=True)
with open(ROOT/(name+'.lock'),'a') as lock:
 fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
 if action=='preflight':
  identity(name);lint(name);checks=synthetic(name)
  if shutil.disk_usage(baseline[name]['custom_root']).free<10*1024*1024:raise RuntimeError('Insufficient backup space')
  print('M141_PREFLIGHT_PASS',name,'native_checks',checks)
 elif action=='apply':
  identity(name);lint(name);synthetic(name)
  if name=='live':
   state=json.loads((PUBLIC/'backup-release.json').read_text())
   if state['status']!='VERIFIED' or state['artifact_sha256']!=ARTIFACT_SHA:raise RuntimeError('Backup acceptance required first')
  folder=ROOT/(name+'-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'));folder.mkdir(mode=0o700);backup=folder/'source-before.tar.gz';root=Path(baseline[name]['custom_root'])
  with tarfile.open(backup,'x:gz') as t:
   for rel in FILES:t.add(root/rel,arcname=rel,recursive=False)
  backup.chmod(0o600)
  with tarfile.open(backup,'r:gz') as t:
   members=t.getmembers()
   if len(members)!=5 or {m.name for m in members}!=set(FILES):raise RuntimeError('Backup allowlist mismatch')
   for m in members:
    if not m.isfile() or sha(t.extractfile(m).read())!=artifact['files'][m.name]['predecessor']:raise RuntimeError('Recovery backup verification failed')
  changed=[]
  try:
   for rel in FILES:
    p=root/rel;old=baseline[name]['files'][rel]
    if sha(p.read_bytes())!=old['sha256']:raise RuntimeError('Source changed before atomic write')
    temp=p.with_name('.m141-'+p.name);fd=os.open(temp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,old['mode'])
    with os.fdopen(fd,'wb') as f:f.write(source[rel]);f.flush();os.fsync(f.fileno())
    os.chown(temp,old['uid'],old['gid']);os.chmod(temp,old['mode']);os.replace(temp,p);changed.append(rel)
   identity(name,True);result=functional(name);web=health(name)
   receipt(name,{'status':'VERIFIED','files':{r:artifact['files'][r]['sha256'] for r in FILES},'backup':str(backup),'backup_sha256':sha(backup.read_bytes()),'functional':result,'web':web,'temporary_native_tests':49,'database_config_rights_mutations':False,'restart':False})
   print('M141_APPLY_VERIFIED',name,'files',len(changed),'employee_options',result['employee_options'])
  except BaseException:
   restore(name,backup,changed);receipt(name,{'status':'ROLLED_BACK','backup':str(backup)});raise
 elif action=='verify':
  identity(name,True);lint(name);synthetic(name);result=functional(name);web=health(name)
  state=json.loads((PUBLIC/(name+'-release.json')).read_text());backup=Path(state['backup'])
  if sha(backup.read_bytes())!=state['backup_sha256']:raise RuntimeError('Recovery checksum changed')
  print('M141_INDEPENDENT_VERIFY_PASS',name,'native49','staff_only',result['staff_only'],'external_denied',result['external_actor_denied'],'served_hash',web['public_TLS_asset_hash'])
 elif action=='rollback':
  state=json.loads((PUBLIC/(name+'-release.json')).read_text());backup=Path(state['backup'])
  if state['artifact_sha256']!=ARTIFACT_SHA or sha(backup.read_bytes())!=state['backup_sha256']:raise RuntimeError('Rollback artifact mismatch')
  restore(name,backup,FILES);receipt(name,{'status':'ROLLED_BACK','backup':str(backup)});print('M141_ROLLBACK_PASS',name)
