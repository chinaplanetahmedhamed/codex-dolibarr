import os,socket,subprocess,json,hashlib,stat,pwd,datetime,re
from pathlib import Path
if os.geteuid()!=0 or socket.gethostname()!='srv1011043':raise RuntimeError('Host identity mismatch')
TARGETS={'backup':{'container':'erp-staging-dolibarr-1','id':'ed2c8761e65a0f9a7747eec302823e4e4dec9e2c769fd01db7b2fd6872bd3fed','image':'sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9','project':'erp-staging','volume':'erp-staging_erp-staging_custom','proxy':'6.conf','host':'backup-erp.visiontrading.biz'},'live':{'container':'vision_erp','id':'06275ef163d77db472886ec5a09cbafde062a007dbced035c78b9c71e62eb004','image':'sha256:a714a9cbf63460f99390d6f59cc2b334103634cff7d909a117e0b1fc4474746f','project':'<no value>','volume':'vision-os_dolibarr_custom','proxy':'2.conf','host':'erp.visiontrading.biz'}}
FILES=['chinaplanetoperations/class/InternalTaskBoardLifecycle.class.php', 'chinaplanetoperations/class/InternalTaskBoardsService.class.php', 'chinaplanetoperations/class/InternalTaskProjectTaskService.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceService.class.php', 'chinaplanetoperations/class/SupplierProjectWorkspaceService.class.php', 'chinaplanetoperations/class/SupplierWorkspaceService.class.php', 'chinaplanetoperations/css/supplier_links.css', 'chinaplanetoperations/js/boards.js', 'chinaplanetoperations/js/supplier_links.js', 'chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang', 'chinaplanetoperations/langs/en_US/chinaplanetoperations.lang', 'chinaplanetoperations/tasks/boards.php', 'chinaplanetoperations/tasks/boards_api.php', 'chinaplanetoperations/tasks/card.php', 'chinaplanetoperations/tasks/supplier_links_api.php']
PROTECTED=['chinaplanetoperations/class/InternalTaskAuthorizationService.class.php', 'chinaplanetoperations/class/InternalTaskBoardPolicy.class.php', 'chinaplanetoperations/class/InternalTaskContextService.class.php', 'chinaplanetoperations/class/InternalTaskService.class.php', 'chinaplanetoperations/class/InternalTaskDetailsService.class.php', 'chinaplanetoperations/class/CustomerAccountService.class.php', 'chinaplanetoperations/class/SupplierAccountService.class.php', 'chinaplanetoperations/css/boards.css']
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
   if not path.exists() and rel in FILES:
    if path.is_symlink() or not path.parent.is_dir() or not path.parent.resolve().is_relative_to(expected):raise RuntimeError('Unsafe new source path')
    f[rel]={'absent':True};continue
   if not path.is_file() or path.is_symlink() or path.stat().st_nlink!=1 or not path.resolve().is_relative_to(expected):raise RuntimeError('Unsafe source path '+rel)
   a=path.stat();data=path.read_bytes();b=path.stat()
   if (a.st_ino,a.st_size,a.st_mtime_ns)!=(b.st_ino,b.st_size,b.st_mtime_ns):raise RuntimeError('Unstable source')
   f[rel]={'sha256':sha(data),'bytes':len(data),'mode':stat.S_IMODE(a.st_mode),'uid':a.st_uid,'gid':a.st_gid}
  php="echo json_encode(['version'=>(function(){require '/var/www/html/filefunc.inc.php';return DOL_VERSION;})(),'config_sha256'=>hash_file('sha256','/var/www/html/conf/conf.php'),'pdo_mysql'=>extension_loaded('pdo_mysql'),'pdo_sqlite'=>extension_loaded('pdo_sqlite')]);"
  # Version is read from the deployed public version definition, not environment values.
  php="$s=file_get_contents('/var/www/html/filefunc.inc.php');preg_match(\"/define\\('DOL_VERSION',\\s*'([^']+)'/\",$s,$m);echo json_encode(['version'=>$m[1]??'unknown','config_sha256'=>hash_file('sha256','/var/www/html/conf/conf.php'),'pdo_mysql'=>extension_loaded('pdo_mysql'),'pdo_sqlite'=>extension_loaded('pdo_sqlite')]);"
  meta=json.loads(subprocess.check_output(['docker','exec',t['container'],'php','-r',php],timeout=20))
  out[name]={'runtime':d,'custom_root':expected,'files':f,'proxy_sha256':sha(proxy.read_bytes()),'meta':meta}
 return out
import base64,sys,fcntl,tarfile,shutil,urllib.request,ssl
ARTIFACT_SHA='dec04e32ff9597a8d0a3d7c1e16e0212c52816386f1fa21dab8a7c45c8875ca5'
INVENTORY_SHA='6c2b79a2b1ed92bf48d0be8fe3b03e5d5065bc3b5654fe0213401c91dc55204e'
ROOT=Path('/root/m142-release-20261123');PUBLIC=Path('/var/lib/codex-m142-release')
def pinned(path,digest):
 p=Path(path);s=p.lstat()
 if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or s.st_mode&0o022:raise RuntimeError('Unsafe release input')
 data=p.read_bytes()
 if sha(data)!=digest:raise RuntimeError('Input checksum changed')
 return data
artifact=json.loads(pinned('/root/m142-artifact.json',ARTIFACT_SHA));baseline=json.loads(pinned(PUBLIC/'inventory.json',INVENTORY_SHA))['targets']
if artifact['release']!='M142_20261123' or artifact['commit']!='d9f704f60df4aad2a4af7df0483a5f4e40f9985e' or set(artifact['files'])!=set(FILES):raise RuntimeError('Release identity mismatch')
source={r:base64.b64decode(v['base64'],validate=True) for r,v in artifact['files'].items()}
if any(sha(source[r])!=artifact['files'][r]['sha256'] for r in FILES):raise RuntimeError('Source checksum changed')
fixture=base64.b64decode(artifact['test'],validate=True)
if sha(fixture)!=artifact['test_sha256']:raise RuntimeError('Test checksum changed')

def runphp(name,program):
 r=subprocess.run(['docker','exec','-i',TARGETS[name]['container'],'php'],input=program,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=90)
 if r.returncode:
  ROOT.mkdir(mode=0o700,exist_ok=True);path=ROOT/(name+'-php-error.txt');path.write_bytes(r.stderr+r.stdout);path.chmod(0o600)
  raise RuntimeError(name+' PHP validation failed; private diagnostic retained')
 return r.stdout

def normalized(d):
 d=json.loads(json.dumps(d));d['mounts']=sorted(d['mounts'],key=lambda x:json.dumps(x,sort_keys=True));d['ports']={k:sorted(v,key=lambda x:json.dumps(x,sort_keys=True)) if isinstance(v,list) else v for k,v in d['ports'].items()};return d

def identity(name,new=False):
 current=collect()[name];old=baseline[name]
 if normalized(current['runtime'])!=normalized(old['runtime']) or current['meta']!=old['meta'] or current['proxy_sha256']!=old['proxy_sha256']:raise RuntimeError('Runtime/config/proxy drift')
 for rel in PROTECTED:
  if current['files'][rel]!=old['files'][rel]:raise RuntimeError('Protected source drift: '+rel)
 for rel in FILES:
  value=current['files'][rel];before=old['files'][rel];wanted=artifact['files'][rel]['sha256'] if new else artifact['files'][rel]['predecessor']
  if wanted is None:
   if value!={'absent':True}:raise RuntimeError('New path unexpectedly occupied: '+rel)
  elif value.get('sha256')!=wanted:raise RuntimeError('Source predecessor/candidate mismatch: '+rel)
  if not before.get('absent') and any(value.get(k)!=before[k] for k in ['uid','gid','mode']):raise RuntimeError('Source permissions changed')
 return current

def lint(name):
 for rel in FILES:
  if rel.endswith('.php'):
   r=subprocess.run(['docker','exec','-i',TARGETS[name]['container'],'php','-l'],input=source[rel],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=20)
   if r.returncode:raise RuntimeError('Candidate PHP syntax failed: '+rel)

def synthetic(name):
 result=runphp(name,fixture).decode().strip()
 if result!='PASS M141: 72 permission/persistence checks\nPASS M142: 46 supplier-link checks':raise RuntimeError('Synthetic service acceptance mismatch')
 return {'staff_checks':72,'supplier_link_checks':46,'database':'in-memory SQLite; no native writes'}

def native(name,new=False):
 code=r'''<?php
chdir('/var/www/html');$_SERVER['DOCUMENT_ROOT']='/var/www/html';$_SERVER['HTTP_HOST']='TARGET_HOST';require '/var/www/html/master.inc.php';
$user=new User($db);if($user->fetch(1)<=0||!$user->admin||$user->socid||$user->statut!=1)throw new RuntimeException('Native actor mismatch');$user->getrights();
require_once '/var/www/html/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';
$p=MAIN_DB_PREFIX;$entity=(int)$conf->entity;if($entity!==1||DOL_VERSION!=='24.0.1')throw new RuntimeException('Native entity/version mismatch');
$sw=new SupplierWorkspaceService($db,$user,$entity);if(!$sw->canBrowse()||!InternalTaskWorkspaceService::enabled())throw new RuntimeException('Required existing workspaces unavailable');
$r=$db->query("SELECT COUNT(*) n FROM {$p}element_element WHERE (sourcetype='cpo_board' AND relationtype='cpo_supplier_project') OR (sourcetype IN ('project','cpo_task') AND relationtype='cpo_supplier')");if(!$r)throw new RuntimeException('Native relationships unavailable');$owned=(int)$db->fetch_object($r)->n;
$boardService=new InternalTaskBoardsService($db,$user,$entity);$general=$boardService->board(3);if(!$general['staff_only_members'])throw new RuntimeException('General Board staff regression');
$out=['version'=>DOL_VERSION,'entity'=>$entity,'owned_supplier_relations'=>$owned,'native_actor_verified'=>true,'general_staff_scope'=>true,'persistent_writes'=>false,'project_panels'=>0,'card_panels'=>0];
if(NEW_SOURCE){
 $svc=new SupplierProjectWorkspaceService($db,$user,$entity);
 $r=$db->query("SELECT DISTINCT pb.fk_project FROM {$p}cpo_project_board pb JOIN {$p}cpo_board b ON b.entity=pb.entity AND b.rowid=pb.fk_board WHERE b.entity=$entity AND b.archived=0 AND ".InternalTaskBoardLifecycle::visibleSql('b.entity','b.rowid').' ORDER BY pb.fk_project LIMIT 5');if(!$r)throw new RuntimeException('Customer project query failed');$projects=[];while($o=$db->fetch_object($r))$projects[]=(int)$o->fk_project;
 foreach($projects as $id){$panel=$svc->projectPanel($id);if($panel['project_id']!==$id)throw new RuntimeException('Native project panel context mismatch');$out['project_panels']++;}
 if(!$projects)throw new RuntimeException('No native project available for acceptance');
 $r=$db->query("SELECT c.fk_task FROM {$p}cpo_board_card c JOIN {$p}cpo_project_board pb ON pb.entity=c.entity AND pb.fk_board=c.fk_board WHERE c.entity=$entity AND c.archived=0 ORDER BY c.fk_task LIMIT 5");if(!$r)throw new RuntimeException('Native card query failed');$cards=[];while($o=$db->fetch_object($r))$cards[]=(int)$o->fk_task;
 foreach($cards as $id){$panel=$svc->forCard($id);if(!$panel['project_id'])throw new RuntimeException('Native card context mismatch');$out['card_panels']++;}
 $external=clone $user;$external->socid=999999;$denied=false;try{(new SupplierProjectWorkspaceService($db,$external,$entity))->projectPanel($projects[0]);}catch(InternalTaskAccessException $e){$denied=true;}if(!$denied)throw new RuntimeException('External supplier projection allowed');$out['external_denied']=true;
 $expected=[];$r=$db->query("SELECT s.rowid FROM {$p}societe s WHERE s.entity=$entity AND s.fournisseur=1");if(!$r)throw new RuntimeException('Supplier independent read failed');while($o=$db->fetch_object($r))$expected[]=(int)$o->rowid;$got=[];$page=0;
 do{$options=$svc->options($projects[0],'',++$page);foreach(array_slice($options,0,50) as $option)$got[]=(int)$option['rowid'];if($page>10000)throw new RuntimeException('Supplier paging overflow');}while(count($options)>50);
 sort($expected);sort($got);if($got!==$expected||count(array_unique($got))!==count($got))throw new RuntimeException('Supplier paging mismatch');$out['all_supplier_ids_match']=true;$out['supplier_count']=count($got);$out['supplier_pages']=$page;
 $langs->load('chinaplanetoperations@chinaplanetoperations');if($langs->trans('CPOSupplierLinksTitle')==='CPOSupplierLinksTitle')throw new RuntimeException('Missing supplier labels');
}
if(!NEW_SOURCE&&$owned!==0)throw new RuntimeException('Unexpected existing M142 relationships; review before release');
echo json_encode($out);
'''.replace('TARGET_HOST',TARGETS[name]['host']).replace('NEW_SOURCE','true' if new else 'false')
 return json.loads(runphp(name,code.encode()))

def health(name):
 host=TARGETS[name]['host'];port='18088' if name=='backup' else '8080'
 req=urllib.request.Request('http://127.0.0.1:'+port+'/custom/chinaplanetoperations/tasks/boards.php',headers={'Host':host})
 with urllib.request.urlopen(req,timeout=20) as r:body=r.read()
 if b'cb-config' in body or b'name="password"' not in body or b'Fatal error' in body:raise RuntimeError('Anonymous privacy/health failure')
 assets=[r for r in FILES if r.endswith(('.js','.css'))]
 for rel in assets:
  version=str(int((Path(baseline[name]['custom_root'])/rel).stat().st_mtime))
  req=urllib.request.Request('https://'+host+'/custom/'+rel+'?v='+version,headers={'Accept-Encoding':'identity','Cache-Control':'no-cache','User-Agent':'Mozilla/5.0'})
  with urllib.request.urlopen(req,timeout=25,context=ssl.create_default_context()) as r:body=r.read()
  if sha(body)!=artifact['files'][rel]['sha256']:raise RuntimeError('HTTPS asset mismatch '+rel)
 return {'anonymous_login_required':True,'TLS_asset_hashes_verified':len(assets)}

def receipt(name,data):
 data.update({'release':'M142_20261123','source_commit':artifact['commit'],'artifact_sha256':ARTIFACT_SHA,'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()})
 target=PUBLIC/(name+'-release.json');tmp=target.with_suffix('.tmp')
 with open(tmp,'x') as f:json.dump(data,f,indent=2);f.flush();os.fsync(f.fileno())
 os.chown(tmp,0,pwd.getpwnam('codex-deploy').pw_gid);os.chmod(tmp,0o440);os.replace(tmp,target)

def backup_files(name):
 folder=ROOT/(name+'-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'));folder.mkdir(mode=0o700)
 archive=folder/'source-before.tar.gz';root=Path(baseline[name]['custom_root']);existing=[r for r in FILES if not baseline[name]['files'][r].get('absent')]
 with tarfile.open(archive,'x:gz') as t:
  for rel in existing:t.add(root/rel,arcname=rel,recursive=False)
 archive.chmod(0o600)
 with tarfile.open(archive,'r:gz') as t:
  members=t.getmembers()
  if {m.name for m in members}!=set(existing) or len(members)!=len(existing):raise RuntimeError('Recovery archive allowlist mismatch')
  for m in members:
   if not m.isfile() or sha(t.extractfile(m).read())!=artifact['files'][m.name]['predecessor']:raise RuntimeError('Recovery archive checksum mismatch')
 return archive

def write_file(path,data,metadata):
 tmp=path.with_name('.m142-'+path.name);fd=os.open(tmp,os.O_WRONLY|os.O_CREAT|os.O_EXCL,metadata['mode'])
 with os.fdopen(fd,'wb') as f:f.write(data);f.flush();os.fsync(f.fileno())
 os.chown(tmp,metadata['uid'],metadata['gid']);os.chmod(tmp,metadata['mode']);os.replace(tmp,path)

def owned_relations(name):
 code=b"<?php require '/var/www/html/conf/conf.php'; if(!preg_match('/^[A-Za-z0-9_]+$/D',$dolibarr_main_db_prefix))throw new RuntimeException('Invalid native prefix');$p=new PDO('mysql:host='.$dolibarr_main_db_host.';port='.($dolibarr_main_db_port?:3306).';dbname='.$dolibarr_main_db_name.';charset=utf8mb4',$dolibarr_main_db_user,$dolibarr_main_db_pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);echo $p->query(\"SELECT COUNT(*) FROM \".$dolibarr_main_db_prefix.\"element_element WHERE (sourcetype='cpo_board' AND relationtype='cpo_supplier_project') OR (sourcetype IN ('project','cpo_task') AND relationtype='cpo_supplier')\")->fetchColumn();"
 return int(runphp(name,code))

def restore(name,archive,changed):
 # Never restore old visibility code once users create supplier relationships. Preserve their data and fix forward.
 if owned_relations(name)!=0:raise RuntimeError('Rollback held: supplier relationships now exist; preserve current ACL code and fix forward')
 root=Path(baseline[name]['custom_root'])
 with tarfile.open(archive,'r:gz') as t:
  for rel in reversed(changed):
   path=root/rel
   if not path.is_file() or path.is_symlink() or sha(path.read_bytes())!=artifact['files'][rel]['sha256']:raise RuntimeError('Rollback refused later source edit')
   if baseline[name]['files'][rel].get('absent'):path.unlink()
   else:
    m=t.getmember(rel);data=t.extractfile(m).read()
    if sha(data)!=artifact['files'][rel]['predecessor']:raise RuntimeError('Rollback recovery bytes changed')
    write_file(path,data,baseline[name]['files'][rel])
 identity(name,False)

name,action=sys.argv[1:]
if name not in TARGETS or action not in ['preflight','apply','verify','rollback']:raise RuntimeError('Exact target/action required')
ROOT.mkdir(mode=0o700,exist_ok=True)
with open(ROOT/(name+'.lock'),'a') as lock:
 fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
 if action=='preflight':
  identity(name);lint(name);state=native(name)
  if shutil.disk_usage(baseline[name]['custom_root']).free<20*1024*1024:raise RuntimeError('Insufficient recovery space')
  print('M142_PREFLIGHT_PASS',name,'files',len(FILES),'native_actor',state['native_actor_verified'])
 elif action=='apply':
  identity(name);lint(name);native(name)
  if name=='live':
   state=json.loads((PUBLIC/'backup-release.json').read_text())
   if state.get('status')!='VERIFIED' or not state.get('independent_verification') or state['artifact_sha256']!=ARTIFACT_SHA:raise RuntimeError('Independent backup acceptance required before live')
  archive=backup_files(name);changed=[];root=Path(baseline[name]['custom_root'])
  try:
   order=['class/InternalTaskWorkspaceService.class.php','class/SupplierProjectWorkspaceService.class.php','class/InternalTaskBoardsService.class.php','class/InternalTaskBoardLifecycle.class.php','class/InternalTaskProjectTaskService.class.php','class/SupplierWorkspaceService.class.php','langs/ar_SA/chinaplanetoperations.lang','langs/en_US/chinaplanetoperations.lang','css/supplier_links.css','js/supplier_links.js','tasks/supplier_links_api.php','tasks/card.php','tasks/boards.php','tasks/boards_api.php','js/boards.js']
   order=['chinaplanetoperations/'+r for r in order]
   if set(order)!=set(FILES) or len(order)!=len(FILES):raise RuntimeError('Dependency write-order mismatch')
   for rel in order:
    path=root/rel;before=baseline[name]['files'][rel];pred=artifact['files'][rel]['predecessor']
    if (pred is None and (path.exists() or path.is_symlink())) or (pred is not None and sha(path.read_bytes())!=pred):raise RuntimeError('Source changed before atomic write')
    meta=before if not before.get('absent') else {'uid':baseline[name]['files']['chinaplanetoperations/class/InternalTaskBoardsService.class.php']['uid'],'gid':baseline[name]['files']['chinaplanetoperations/class/InternalTaskBoardsService.class.php']['gid'],'mode':0o644}
    write_file(path,source[rel],meta);changed.append(rel)
   identity(name,True);tests=synthetic(name);functional=native(name,True);web=health(name)
   receipt(name,{'status':'VERIFIED','files':{r:artifact['files'][r]['sha256'] for r in FILES},'backup':str(archive),'backup_sha256':sha(archive.read_bytes()),'synthetic':tests,'functional':functional,'web':web,'database_config_rights_mutations':False,'restart':False})
   print('M142_APPLY_VERIFIED',name,'files',len(changed),'synthetic118','projects',functional['project_panels'],'cards',functional['card_panels'])
  except BaseException:
   try:restore(name,archive,changed);receipt(name,{'status':'ROLLED_BACK','backup':str(archive)})
   except BaseException as failure:receipt(name,{'status':'ROLLBACK_HELD','backup':str(archive),'reason':str(failure)})
   raise
 elif action=='verify':
  try:
   identity(name,True);lint(name);tests=synthetic(name);functional=native(name,True);web=health(name)
   state=json.loads((PUBLIC/(name+'-release.json')).read_text());archive=Path(state['backup'])
   if state['artifact_sha256']!=ARTIFACT_SHA or sha(archive.read_bytes())!=state['backup_sha256']:raise RuntimeError('Recovery identity/checksum changed')
   state.update({'independent_verification':True,'synthetic':tests,'functional':functional,'web':web});receipt(name,state)
   print('M142_INDEPENDENT_VERIFY_PASS',name,'synthetic118','suppliers',functional['supplier_count'],'pages',functional['supplier_pages'],'external_denied',functional['external_denied'])
  except BaseException:
   state=json.loads((PUBLIC/(name+'-release.json')).read_text());archive=Path(state['backup'])
   try:restore(name,archive,FILES);receipt(name,{'status':'ROLLED_BACK','backup':str(archive)})
   except BaseException as failure:receipt(name,{'status':'ROLLBACK_HELD','backup':str(archive),'reason':str(failure)})
   raise
 else:
  state=json.loads((PUBLIC/(name+'-release.json')).read_text());archive=Path(state['backup'])
  if state['artifact_sha256']!=ARTIFACT_SHA or sha(archive.read_bytes())!=state['backup_sha256']:raise RuntimeError('Recovery identity/checksum changed')
  restore(name,archive,FILES);receipt(name,{'status':'ROLLED_BACK','backup':str(archive)});print('M142_ROLLBACK_PASS',name)
