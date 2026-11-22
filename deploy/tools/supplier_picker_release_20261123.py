import os,socket,subprocess,json,hashlib,stat,pwd,datetime,re
from pathlib import Path
if os.geteuid()!=0 or socket.gethostname()!='srv1011043':raise RuntimeError('Host identity mismatch')
TARGETS={'backup':{'container':'erp-staging-dolibarr-1','id':'ed2c8761e65a0f9a7747eec302823e4e4dec9e2c769fd01db7b2fd6872bd3fed','image':'sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9','project':'erp-staging','volume':'erp-staging_erp-staging_custom','proxy':'6.conf','host':'backup-erp.visiontrading.biz'},'live':{'container':'vision_erp','id':'06275ef163d77db472886ec5a09cbafde062a007dbced035c78b9c71e62eb004','image':'sha256:a714a9cbf63460f99390d6f59cc2b334103634cff7d909a117e0b1fc4474746f','project':'<no value>','volume':'vision-os_dolibarr_custom','proxy':'2.conf','host':'erp.visiontrading.biz'}}
FILES=['chinaplanetoperations/class/SupplierWorkspaceService.class.php', 'chinaplanetoperations/css/boards.css', 'chinaplanetoperations/js/boards.js', 'chinaplanetoperations/tasks/boards.php', 'chinaplanetoperations/tasks/boards_api.php', 'chinaplanetoperations/langs/en_US/chinaplanetoperations.lang', 'chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang']
PROTECTED=['chinaplanetoperations/class/InternalTaskBoardsService.class.php', 'chinaplanetoperations/class/InternalTaskAuthorizationService.class.php', 'chinaplanetoperations/class/InternalTaskBoardPolicy.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceService.class.php', 'chinaplanetoperations/class/CustomerAccountService.class.php']
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
ARTIFACT_SHA='3067cd179fa6ee762272a894dea933f5d412468d1d7f26a284466ee74726b49d'
INVENTORY_SHA='08320b306023540a67140ebe9e5dab90e2925d64f97ab3eee523623e7ad429f5'
ROOT=Path('/root/supplier-picker-20261123')
PUBLIC=Path('/var/lib/codex-supplier-picker-20261123')
def pinned(path,digest):
 p=Path(path);s=p.lstat()
 if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or s.st_mode&0o022:raise RuntimeError('Unsafe release input')
 b=p.read_bytes()
 if sha(b)!=digest:raise RuntimeError('Release input checksum mismatch')
 return b
artifact=json.loads(pinned('/root/supplier-picker-artifact.json',ARTIFACT_SHA));baseline=json.loads(pinned(PUBLIC/'inventory.json',INVENTORY_SHA))['targets']
if artifact['release']!='SUPPLIER_PICKER_20261123' or artifact['commit']!='91d7337e54a3367dc171342fce71c0059e66aa02' or set(artifact['files'])!=set(FILES):raise RuntimeError('Artifact allowlist mismatch')
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
 code=source['chinaplanetoperations/class/SupplierWorkspaceService.class.php'].replace(b'__DIR__',b"'/var/www/html/custom/chinaplanetoperations/class'")
 needle=b"require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';"
 if test.count(needle)!=1:raise RuntimeError('Fixture injection mismatch')
 if not code.startswith(b'<?php'):raise RuntimeError('Candidate PHP header mismatch')
 code=code[5:]
 fixture=test.replace(needle,b"eval(base64_decode('"+base64.b64encode(code)+b"'));")
 needle=b"new PDO('sqlite::memory:')"
 if fixture.count(needle)!=1:raise RuntimeError('Fixture PDO mismatch')
 fixture=fixture.replace(needle,b"$GLOBALS['supplierPickerPdo']")
 needle=b"public function query($sql){return $this->pdo->query($sql);}"
 if fixture.count(needle)!=1:raise RuntimeError('Fixture temporary-table mismatch')
 fixture=fixture.replace(needle,b"public function query($sql){if(str_starts_with($sql,'CREATE TABLE picker_'))$sql=str_replace('CREATE TABLE picker_','CREATE TEMPORARY TABLE picker_',$sql);return $this->pdo->query($sql);}")
 boot=b"<?php require '/var/www/html/conf/conf.php'; $supplierPickerPdo=new PDO('mysql:host='.$dolibarr_main_db_host.';port='.($dolibarr_main_db_port?:3306).';dbname='.$dolibarr_main_db_name.';charset=utf8mb4',$dolibarr_main_db_user,$dolibarr_main_db_pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); ?>"
 result=runphp(name,boot+fixture).decode()
 if result.strip()!='PASS supplier picker: 14 checks':raise RuntimeError('Synthetic acceptance changed')
 return 14

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
$s=new SupplierWorkspaceService($db,$user,(int)$conf->entity);if(!$s->canEnable())throw new RuntimeException('Native activation unavailable');
$expected=[];$p=MAIN_DB_PREFIX;$r=$db->query('SELECT s.rowid FROM '.$p.'societe s WHERE s.entity='.(int)$conf->entity.' AND s.fournisseur=1 AND NOT EXISTS (SELECT 1 FROM '.$p.'cpo_supplier_workspace sw WHERE sw.entity=s.entity AND sw.fk_supplier=s.rowid)');if(!$r)throw new RuntimeException('Independent query failed');while($o=$db->fetch_object($r))$expected[]=(int)$o->rowid;
$got=[];$pages=0;do{$rows=$s->supplierOptions('',++$pages);foreach(array_slice($rows,0,50) as $row)$got[]=(int)$row['rowid'];if($pages>10000)throw new RuntimeException('Paging did not terminate');}while(count($rows)>50);
if(count(array_unique($got))!==count($got))throw new RuntimeException('Duplicate paged supplier');sort($expected);sort($got);if($expected!==$got)throw new RuntimeException('Not all eligible suppliers reachable');
$external=clone $user;$external->socid=999999;$denied=false;try{(new SupplierWorkspaceService($db,$external,(int)$conf->entity))->supplierOptions('',2);}catch(InternalTaskAccessException $e){$denied=true;}if(!$denied)throw new RuntimeException('External picker access allowed');
$langs->load('chinaplanetoperations@chinaplanetoperations');if($langs->trans('CPOSupplierPickerSearch')==='CPOSupplierPickerSearch')throw new RuntimeException('Missing picker translations');
$w=(new InternalTaskBoardsService($db,$user,(int)$conf->entity))->workspace(3);if(empty($w['board']['staff_only_members']))throw new RuntimeException('General Board staff regression');
echo json_encode(['version'=>DOL_VERSION,'eligible_supplier_count'=>count($got),'pages_verified'=>$pages,'all_eligible_ids_match'=>true,'external_actor_denied'=>true,'general_staff_scope_retained'=>true,'business_writes'=>false]);
'''.replace(b'HOST',TARGETS[name]['host'].encode())
 data=json.loads(runphp(name,probe))
 if data['version']!='24.0.1':raise RuntimeError('Unexpected Dolibarr version')
 return data

def health(name):
 host=TARGETS[name]['host'];port='18088' if name=='backup' else '8080'
 req=urllib.request.Request('http://127.0.0.1:'+port+'/custom/chinaplanetoperations/tasks/boards.php',headers={'Host':host})
 with urllib.request.urlopen(req,timeout=25) as r:b=r.read()
 if b'cb-config' in b or b'name="password"' not in b or b'Fatal error' in b:raise RuntimeError('Anonymous board privacy/health failed')
 # Verify both changed assets at exactly the mtime URL emitted by cpo_asset.
 for rel in ['chinaplanetoperations/js/boards.js','chinaplanetoperations/css/boards.css']:
  version=str(int((Path(baseline[name]['custom_root'])/rel).stat().st_mtime))
  req=urllib.request.Request('https://'+host+'/custom/'+rel+'?v='+version,headers={'Accept-Encoding':'identity','User-Agent':'Mozilla/5.0','Cache-Control':'no-cache'})
  with urllib.request.urlopen(req,timeout=30,context=ssl.create_default_context()) as r:b=r.read()
  if sha(b)!=artifact['files'][rel]['sha256']:raise RuntimeError('Served asset bytes differ '+rel)
 return {'anonymous_login_required':True,'public_TLS_JS_CSS_hashes':True}

def receipt(name,value):
 value.update({'release':'SUPPLIER_PICKER_20261123','source_commit':artifact['commit'],'artifact_sha256':ARTIFACT_SHA,'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()})
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
   temp=p.with_name('.supplier-picker-rollback-'+p.name);fd=os.open(temp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,m.mode)
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
  print('SUPPLIER_PICKER_PREFLIGHT_PASS',name,'native_checks',checks)
 elif action=='apply':
  identity(name);lint(name);synthetic(name)
  if name=='live':
   state=json.loads((PUBLIC/'backup-release.json').read_text())
   if state['status']!='VERIFIED' or state['artifact_sha256']!=ARTIFACT_SHA or not state.get('independent_verification'):raise RuntimeError('Backup acceptance required first')
  folder=ROOT/(name+'-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'));folder.mkdir(mode=0o700);backup=folder/'source-before.tar.gz';root=Path(baseline[name]['custom_root'])
  with tarfile.open(backup,'x:gz') as t:
   for rel in FILES:t.add(root/rel,arcname=rel,recursive=False)
  backup.chmod(0o600)
  with tarfile.open(backup,'r:gz') as t:
   members=t.getmembers()
   if len(members)!=len(FILES) or {m.name for m in members}!=set(FILES):raise RuntimeError('Backup allowlist mismatch')
   for m in members:
    if not m.isfile() or sha(t.extractfile(m).read())!=artifact['files'][m.name]['predecessor']:raise RuntimeError('Recovery backup verification failed')
  changed=[]
  try:
   for rel in FILES:
    p=root/rel;old=baseline[name]['files'][rel]
    if sha(p.read_bytes())!=old['sha256']:raise RuntimeError('Source changed before atomic write')
    temp=p.with_name('.supplier-picker-'+p.name);fd=os.open(temp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,old['mode'])
    with os.fdopen(fd,'wb') as f:f.write(source[rel]);f.flush();os.fsync(f.fileno())
    os.chown(temp,old['uid'],old['gid']);os.chmod(temp,old['mode']);os.replace(temp,p);changed.append(rel)
   identity(name,True);result=functional(name);web=health(name)
   receipt(name,{'status':'VERIFIED','files':{r:artifact['files'][r]['sha256'] for r in FILES},'backup':str(backup),'backup_sha256':sha(backup.read_bytes()),'functional':result,'web':web,'temporary_native_tests':14,'database_config_rights_mutations':False,'restart':False})
   print('SUPPLIER_PICKER_APPLY_VERIFIED',name,'files',len(changed),'suppliers',result['eligible_supplier_count'])
  except BaseException:
   restore(name,backup,changed);receipt(name,{'status':'ROLLED_BACK','backup':str(backup)});raise
 elif action=='verify':
  identity(name,True);lint(name);synthetic(name);result=functional(name);web=health(name)
  state=json.loads((PUBLIC/(name+'-release.json')).read_text());backup=Path(state['backup'])
  if sha(backup.read_bytes())!=state['backup_sha256']:raise RuntimeError('Recovery checksum changed')
  state['independent_verification']=True;receipt(name,state)
  print('SUPPLIER_PICKER_INDEPENDENT_VERIFY_PASS',name,'native14','suppliers',result['eligible_supplier_count'],'pages',result['pages_verified'],'external_denied',result['external_actor_denied'],'served_assets',web['public_TLS_JS_CSS_hashes'])
 elif action=='rollback':
  state=json.loads((PUBLIC/(name+'-release.json')).read_text());backup=Path(state['backup'])
  if state['artifact_sha256']!=ARTIFACT_SHA or sha(backup.read_bytes())!=state['backup_sha256']:raise RuntimeError('Rollback artifact mismatch')
  restore(name,backup,FILES);receipt(name,{'status':'ROLLED_BACK','backup':str(backup)});print('SUPPLIER_PICKER_ROLLBACK_PASS',name)
