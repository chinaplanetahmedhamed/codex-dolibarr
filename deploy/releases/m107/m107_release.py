#!/usr/bin/env python3
"""M107 feature-only production release. No Docker/service/core/access changes.
Install a checksum-pinned root-owned copy and invoke through the authorized
existing administrative session. Never add sudo grants for this helper.
"""
import fcntl,hashlib,json,os,socket,stat,subprocess,sys,tarfile,time
from pathlib import Path

ROOT=Path('/var/lib/docker/volumes/vision-os_dolibarr_custom/_data')
STATE=Path('/root/chinaplanetos-m107-workspaces-20261024')
INBOX=Path('/home/codex-deploy/staging-inbox')
IMAGE='sha256:db5c663a52c24e6cf66a19b18d4632dec212e280751780178d9b5162e3afafee'
# Filled after the exact Git-pinned artifact has been assembled.
ARTIFACT_SHA='bb3ba56d04192d502c1a540127fd5d95d8db654673b868ea1925b680f211a65b'
ARTIFACT='m107-workspaces.tar.gz'

def require(v,msg):
    if not v:raise RuntimeError(msg)
def sha(b):return hashlib.sha256(b).hexdigest()
def run(argv,data=None,timeout=60):
    p=subprocess.run(argv,input=data,capture_output=True,timeout=timeout)
    require(p.returncode==0,'Bounded release command failed (private output withheld)')
    return p.stdout
def php(body):
    out=run(['docker','exec','-i','--user','www-data','vision_erp','php','-d','display_errors=0'],b'<?php\n'+body.encode())
    try:return json.loads(out)
    except Exception:raise RuntimeError('Native release receipt invalid (private output withheld)')
def guarded(p):
    require(p.resolve()==p and (not p.exists() or stat.S_ISREG(p.lstat().st_mode)),'Unexpected source symlink/type')
    require(not p.is_symlink(),'Source symlink refused')
    return p
def topology():
    require(os.geteuid()==0 and socket.gethostname()=='srv1011043','Wrong administrative target')
    fmt='{"running":{{json .State.Running}},"image":{{json .Image}},"project":{{json (index .Config.Labels "com.docker.compose.project")}},"mounts":{{json .Mounts}}}'
    erp=json.loads(run(['docker','inspect','--format',fmt,'vision_erp']));db=json.loads(run(['docker','inspect','--format',fmt,'vision_db']))
    require(erp['running'] and db['running'] and erp['image']==IMAGE and db['project']=='vision-os' and erp['project'] in ('vision-os','',None),'Runtime ownership/image drift')
    for dest,name,path in [('/var/www/html/custom','vision-os_dolibarr_custom',str(ROOT)),('/var/www/documents','vision-os_dolibarr_docs',None)]:
        mounts=[x for x in erp['mounts'] if x['Destination']==dest]
        require(len(mounts)==1 and mounts[0].get('Name')==name and mounts[0]['Type']=='volume','Unexpected persistent volume')
        if path:require(mounts[0]['Source']==path,'Unexpected source mount')
    require(ROOT.resolve()==ROOT and ROOT.is_dir(),'Invalid custom root')
    dbmount=[x for x in db['mounts'] if x['Destination']=='/var/lib/mysql']
    require(len(dbmount)==1 and dbmount[0].get('Name')=='vision-os_mariadb_data' and dbmount[0]['Type']=='volume','Unexpected database volume')
    require(os.statvfs(STATE.parent).f_bavail*os.statvfs(STATE.parent).f_frsize>6_000_000_000,'Insufficient backup space')

CONNECT=r'''
error_reporting(0);mysqli_report(MYSQLI_REPORT_OFF);require '/var/www/html/conf/conf.php';
$c=new mysqli($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,$dolibarr_main_db_name,(int)($dolibarr_main_db_port?:3306));
if($c->connect_errno)exit(2);$p=$dolibarr_main_db_prefix;if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))exit(3);
'''
def snapshot():
    return php(CONNECT+r'''
require '/var/www/html/version.inc.php';if(DOL_VERSION!=='24.0.1')exit(4);
$out=['version'=>DOL_VERSION,'protected'=>[],'flags'=>[],'workspace'=>[]];$c->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
foreach(['societe','projet','cpo_board','cpo_board_list','cpo_board_card','cpo_board_member','cpo_card_grant','cpo_task','cpo_task_context','cpo_task_comment','cpo_task_asset','user','user_rights','usergroup_rights'] as $n){
$cols=$n==='user'?'rowid,entity,login,admin,statut,fk_soc,pass_crypted':'*';$r=$c->query('SELECT '.$cols.' FROM `'.$p.$n.'` ORDER BY rowid');if(!$r)exit(5);$ctx=hash_init('sha256');$count=0;while($v=$r->fetch_assoc()){hash_update($ctx,json_encode($v,JSON_THROW_ON_ERROR)."\n");$count++;}$out['protected'][$n]=['rows'=>$count,'sha256'=>hash_final($ctx)];}
$r=$c->query("SELECT name,value,entity FROM `".$p."const` WHERE name IN ('CPO_CUSTOMER_WORKSPACES_ENABLED','CPO_BOARDS_ENABLED','CPO_SHIPMENT_CARDS_ENABLED','MAIN_MODULE_CHINAPLANETOPERATIONS') ORDER BY name,entity");if(!$r)exit(6);while($v=$r->fetch_assoc())$out['flags'][]=$v;
foreach(['cpo_project_board','societe_extrafields'] as $n){$r=$c->query('SHOW COLUMNS FROM `'.$p.$n.'`');$cols=[];if($r)while($v=$r->fetch_assoc())$cols[]=$v['Field'];$out['workspace'][$n]=$cols;}
$out['database']=$dolibarr_main_db_name;$c->rollback();echo json_encode($out,JSON_THROW_ON_ERROR);
''')
def load_artifact():
    file=guarded(INBOX/ARTIFACT);require(sha(file.read_bytes())==ARTIFACT_SHA,'Artifact checksum mismatch')
    content={}
    with tarfile.open(file,'r:gz') as t:
        entries=t.getmembers();require(len(entries)==11,'Unexpected artifact entry count')
        for m in entries:
            require(m.isfile() and m.size<2_000_000 and not m.name.startswith('/') and '..' not in Path(m.name).parts,'Unsafe artifact')
            require(m.name not in content,'Duplicate artifact entry');content[m.name]=t.extractfile(m).read()
    manifest=json.loads(content.pop('manifest.json'));require(len(manifest['files'])==10 and len(content)==10,'Wrong release manifest')
    for x in manifest['files']:
        require(x['path'].startswith('custom/chinaplanetoperations/') and x['path'] in content,'Unexpected module path')
        require(sha(content[x['path']])==x['sha256'],'Payload pin mismatch')
    return manifest,content
def files_match(manifest,after=False):
    out={}
    for x in manifest['files']:
        p=guarded(ROOT/x['path'].removeprefix('custom/'));actual=sha(p.read_bytes()) if p.exists() else None
        expected=x['sha256'] if after else x['before_sha256'];require(actual==expected,'Live source drift: '+x['path']);out[x['path']]=actual
    for name,expected in manifest['dependencies'].items():require(sha(guarded(ROOT/name).read_bytes())==expected,'Dependency drift: '+name)
    return out
def setflag(value):
    return php("$_SERVER['DOCUMENT_ROOT']='/var/www/html';$_SERVER['SERVER_NAME']='erp.visiontrading.biz';$_SERVER['HTTP_HOST']='erp.visiontrading.biz';require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';if(dolibarr_set_const($db,'CPO_CUSTOMER_WORKSPACES_ENABLED','"+str(value)+"','chaine',0,'',1)<=0)exit(7);echo json_encode(['flag'=>"+str(value)+"]);")
def atomic(p,data,uid=33,gid=33,mode=0o644):
    guarded(p);q=p.with_name(p.name+'.m107-tmp');require(not q.exists(),'Unexpected temporary file');q.write_bytes(data);os.chown(q,uid,gid);os.chmod(q,mode);os.replace(q,p)
def compatible_rollback(manifest):
    setflag(0)
    for x in manifest['files']:
        if x['before_sha256'] is None:continue # Additive classes/table remain inert.
        old=STATE/'code'/x['path'];require(sha(old.read_bytes())==x['before_sha256'],'Rollback checksum mismatch')
        meta=json.loads((STATE/'file-metadata.json').read_text())[x['path']]
        atomic(ROOT/x['path'].removeprefix('custom/'),old.read_bytes(),**meta)
    return {'result':'COMPATIBLE_CODE_FLAG_ROLLBACK','schema_and_all_business_writes_retained':True}
def receipt(out):
    out['time_utc']=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime());out['environment']='production';out['production_core_updated']=False
    (STATE/(out['result']+'.json')).write_text(json.dumps(out,indent=2))
    # Sanitized release evidence only. Never copy DB backups/config/business values.
    dest=INBOX/'m107-release-receipt.json';dest.write_text(json.dumps(out,indent=2));os.chmod(dest,0o644);print(json.dumps(out))
def main():
    action=sys.argv[1];require(action in ('preflight','apply','verify','rollback'),'Unsupported release action');topology();STATE.mkdir(mode=0o700,exist_ok=True)
    with (STATE/'release.lock').open('w') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB);manifest,payload=load_artifact()
        confhash=run(['docker','exec','vision_erp','sha256sum','/var/www/html/conf/conf.php']).decode().split()[0]
        if action=='preflight':
            files_match(manifest);s=snapshot();flags={v['name']:v['value'] for v in s['flags'] if v['entity']=='1'}
            require(flags.get('CPO_BOARDS_ENABLED')=='1' and flags.get('MAIN_MODULE_CHINAPLANETOPERATIONS')=='1','Existing module prerequisites not active')
            require(flags.get('CPO_CUSTOMER_WORKSPACES_ENABLED','0')=='0' and not s['workspace']['cpo_project_board'] and 'cpo_board_workspace' not in s['workspace']['societe_extrafields'],'Unexpected pre-existing workspace schema/state')
            (STATE/'preflight.json').write_text(json.dumps(s));receipt({'result':'M107_PREFLIGHT_PASS','commit':manifest['commit'],'artifact_sha256':ARTIFACT_SHA,'version':s['version'],'files':10,'protected':s['protected']});return
        if action=='rollback':receipt(compatible_rollback(manifest));return
        if action=='verify':
            files_match(manifest,True);s=snapshot();require(any(v['name']=='CPO_CUSTOMER_WORKSPACES_ENABLED' and v['value']=='1' and v['entity']=='1' for v in s['flags']),'Feature flag not active')
            require({'entity','fk_project','fk_board'}<=set(s['workspace']['cpo_project_board']) and 'cpo_board_workspace' in s['workspace']['societe_extrafields'],'Incomplete schema')
            receipt({'result':'M107_RUNTIME_VERIFY_PASS','commit':manifest['commit'],'artifact_sha256':ARTIFACT_SHA,'version':s['version'],'files':10,'protected':s['protected']});return
        require((STATE/'preflight.json').exists() and not (STATE/'apply-started.json').exists(),'Fresh preflight required or apply already attempted; inspect state')
        files_match(manifest);before=snapshot();metadata={}
        for x in manifest['files']:
            p=ROOT/x['path'].removeprefix('custom/')
            if p.exists():
                m=p.stat();metadata[x['path']]={'uid':m.st_uid,'gid':m.st_gid,'mode':stat.S_IMODE(m.st_mode)};out=STATE/'code'/x['path'];out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(p.read_bytes())
        (STATE/'file-metadata.json').write_text(json.dumps(metadata));db=before['database'];require(db.replace('_','').isalnum(),'Unsafe database name')
        dump=STATE/'before-full-database.sql'
        require(not dump.exists(),'Backup already exists; inspect state')
        command='set -eu; export MYSQL_PWD="${MYSQL_ROOT_PASSWORD:-${MARIADB_ROOT_PASSWORD:-}}"; if command -v mariadb-dump >/dev/null; then exec mariadb-dump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; else exec mysqldump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; fi'
        with dump.open('xb') as out:
            p=subprocess.run(['docker','exec','vision_db','sh','-c',command,'m107',db],stdout=out,stderr=subprocess.PIPE,timeout=180);require(p.returncode==0 and dump.stat().st_size>1000,'Database backup failed')
        os.chmod(dump,0o600);backupsha=sha(dump.read_bytes());(STATE/'apply-started.json').write_text(json.dumps({'commit':manifest['commit'],'backup_sha256':backupsha,'conf_sha256':confhash,'before':before}))
        try:
            files_match(manifest)
            for x in manifest['files']:atomic(ROOT/x['path'].removeprefix('custom/'),payload[x['path']],**metadata.get(x['path'],{}))
            for x in manifest['files']:
                if x['path'].endswith('.php'):run(['docker','exec','vision_erp','php','-l','/var/www/html/'+x['path']])
            php("$_SERVER['DOCUMENT_ROOT']='/var/www/html';$_SERVER['SERVER_NAME']='erp.visiontrading.biz';$_SERVER['HTTP_HOST']='erp.visiontrading.biz';require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskWorkspaceMigration.class.php';$m=new InternalTaskWorkspaceMigration($db);$m->apply();$m->apply();echo json_encode(['migration'=>'PASS']);")
            setflag(1);files_match(manifest,True);after=snapshot();require(before['protected']==after['protected'],'Protected records changed during release; rollback code, retain writes')
            require(confhash==run(['docker','exec','vision_erp','sha256sum','/var/www/html/conf/conf.php']).decode().split()[0],'Configuration changed')
            receipt({'result':'M107_FEATURE_APPLIED','commit':manifest['commit'],'artifact_sha256':ARTIFACT_SHA,'full_db_backup_sha256':backupsha,'backup_path':str(STATE),'files':10,'version':after['version'],'protected_records_preserved':True,'accounts_rights_unchanged':True,'additive_schema':True,'business_writes_retained':True,'authenticated_acceptance':'PENDING'})
        except Exception:
            result=compatible_rollback(manifest);receipt(result);raise

if __name__=='__main__':
    try:main()
    except Exception as e:print('M107_STOP '+str(e),file=sys.stderr);sys.exit(1)
