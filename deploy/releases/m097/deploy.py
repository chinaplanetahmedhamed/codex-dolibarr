#!/usr/bin/python3 -I
"""Fixed-scope M097 deployment; execute locally as root on the VPS.

The uploaded payload and manifest are untrusted until their reviewed hashes,
file allowlist, target state, and container identities all pass verification.
"""
import base64
import fcntl
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import stat
import subprocess
import sys
import tarfile
import time

RELEASE = 'M097'
INBOX = Path('/home/codex-deploy/staging-inbox')
MANIFEST_SHA = '72c3aa518bdf0ab43d8c70b5fb1840289c9ddfdb14cd83547c57683fc621bdbe'
PAYLOAD_SHA = '00495176043bc143fec54bdb5d651a29b393e2581efa50989556e7a31acbebb2'
TARGETS = {
    'staging': ('erp-staging-dolibarr-1', 'erp-staging-mariadb-1', 'erp-staging_erp-staging_custom', 'erp-staging'),
    'production': ('vision_erp', 'vision_db', 'vision-os_dolibarr_custom', 'vision-os'),
}
ALLOWED_OWNERS = (0,33)
ALLOWED_GROUPS = (0,33)
METADATA_OVERRIDES = {'chinaplanetoperations/workspace.php': (1002,1002,0o644)}
STATE_BASE = Path('/root')

def check(ok, reason):
    if not ok: raise RuntimeError(reason)

def sha(data): return hashlib.sha256(data).hexdigest()

def command(argv, timeout=90, data=None):
    result = subprocess.run(argv, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            timeout=timeout, env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'})
    check(result.returncode == 0, 'COMMAND_FAILED')
    return result.stdout

def load_payload():
    manifest_bytes = (INBOX / 'm097-manifest.json').read_bytes()
    payload_bytes = (INBOX / 'm097-payload.tar.gz').read_bytes()
    check(sha(manifest_bytes) == MANIFEST_SHA and sha(payload_bytes) == PAYLOAD_SHA, 'ARTIFACT_HASH_MISMATCH')
    manifest = json.loads(manifest_bytes)
    check(manifest.get('release') == RELEASE and manifest.get('payload_sha256') == PAYLOAD_SHA, 'MANIFEST_MISMATCH')
    names = set(manifest['files'])
    check(len(names) == 46 and all(not name.startswith('/') and '..' not in Path(name).parts for name in names), 'INVALID_ALLOWLIST')
    check(set(manifest['before']) == set(TARGETS), 'BASELINE_ENV_MISMATCH')
    check(all(set(manifest['before'][env]) == names for env in TARGETS), 'BASELINE_SCOPE_MISMATCH')
    payload = {}
    with tarfile.open(fileobj=io.BytesIO(payload_bytes), mode='r:gz') as archive:
        check(len(archive.getnames()) == len(names) and set(archive.getnames()) == names, 'PAYLOAD_SCOPE_MISMATCH')
        for member in archive:
            check(member.isfile() and not member.issym() and member.size < 5_000_000, 'UNSAFE_PAYLOAD_MEMBER')
            data = archive.extractfile(member).read()
            check(sha(data) == manifest['files'][member.name], 'PAYLOAD_FILE_HASH_MISMATCH')
            payload[member.name] = data
    return manifest, payload

def target(env):
    web, db, volume, project = TARGETS[env]
    METADATA_OVERRIDES['chinaplanetoperations/workspace.php'] = (33,33,0o644) if env=='staging' else (1002,1002,0o644)
    info = json.loads(command(['docker','inspect',web]))[0]
    check(info['State']['Running'] and info['State'].get('Health',{}).get('Status','healthy') == 'healthy', 'ERP_NOT_HEALTHY')
    mounts = [m for m in info['Mounts'] if m['Destination'] == '/var/www/html/custom']
    root = Path('/var/lib/docker/volumes') / volume / '_data'
    check(len(mounts) == 1 and mounts[0]['Type'] == 'volume' and mounts[0]['Name'] == volume and mounts[0]['Source'] == str(root) and mounts[0]['RW'], 'CUSTOM_MOUNT_MISMATCH')
    if project:
        check(info['Config']['Labels'].get('com.docker.compose.project') == project, 'COMPOSE_IDENTITY_MISMATCH')
    dbinfo = json.loads(command(['docker','inspect',db]))[0]
    check(dbinfo['State']['Running'] and dbinfo['Config']['Labels'].get('com.docker.compose.project') == project, 'DB_IDENTITY_OR_STATE_MISMATCH')
    if env=='staging':
        ports=info['NetworkSettings']['Ports'].get('80/tcp')
        check(ports == [{'HostIp':'127.0.0.1','HostPort':'18088'}], 'STAGING_PORT_MISMATCH')
        check(not dbinfo['NetworkSettings']['Ports'].get('3306/tcp'), 'STAGING_DB_EXPOSED')
    check(root.is_dir() and not root.is_symlink() and root.resolve() == root, 'ROOT_MISMATCH')
    check(os.statvfs(root).f_bavail * os.statvfs(root).f_frsize > 500_000_000, 'LOW_DISK_SPACE')
    return web, db, root

def file_info(root, name):
    path = root / name
    parent = root
    for part in path.relative_to(root).parts[:-1]:
        parent = parent / part
        check(parent.is_dir() and not parent.is_symlink(), 'UNSAFE_PARENT')
    if not path.exists():
        check(not path.is_symlink(), 'DANGLING_SYMLINK')
        return None
    st = path.lstat()
    check(stat.S_ISREG(st.st_mode) and st.st_nlink == 1 and st.st_size < 5_000_000, 'UNSAFE_TARGET_FILE')
    metadata = (st.st_uid,st.st_gid,stat.S_IMODE(st.st_mode))
    check(metadata == METADATA_OVERRIDES[name] if name in METADATA_OVERRIDES else st.st_uid in ALLOWED_OWNERS and st.st_gid in ALLOWED_GROUPS and stat.S_IMODE(st.st_mode) == 0o644, 'FILE_METADATA_DRIFT:' + name)
    data = path.read_bytes()
    return {'data':base64.b64encode(data).decode(), 'sha256':sha(data), 'uid':st.st_uid,
            'gid':st.st_gid, 'mode':stat.S_IMODE(st.st_mode)}

def compare(root, expected):
    for name, digest in expected.items():
        info = file_info(root, name)
        check((info['sha256'] if info else None) == digest, 'FILE_DRIFT:' + name)

def atomic(root, name, data, metadata=None):
    path = root / name
    check(path.parent.is_dir() and not path.parent.is_symlink(), 'PARENT_MISSING')
    temp = path.parent / ('.m097-' + str(os.getpid()) + '-' + path.name)
    check(not temp.exists() and not temp.is_symlink(), 'TEMP_COLLISION')
    fd = os.open(temp, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, 0o600)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data); stream.flush(); os.fsync(stream.fileno())
            os.fchown(stream.fileno(), metadata['uid'] if metadata else 33, metadata['gid'] if metadata else 33)
            os.fchmod(stream.fileno(), metadata['mode'] if metadata else 0o644)
        os.replace(temp,path)
    finally:
        if temp.exists(): temp.unlink()

def restore(root, before):
    for name, item in before.items():
        if item is None:
            path = root/name
            if path.exists():
                check(path.is_file() and not path.is_symlink(), 'UNSAFE_ROLLBACK_FILE')
                path.unlink()
        else:
            data=base64.b64decode(item['data'], validate=True)
            check(sha(data)==item['sha256'], 'BACKUP_FILE_HASH_MISMATCH')
            atomic(root,name,data,item)

def php(web, code):
    return command(['docker','exec','-i','--user','www-data',web,'php','-d','display_errors=0','-r',code], timeout=90)

BOOT = "define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);define('NOCSRFCHECK',1);$_SERVER['DOCUMENT_ROOT']='/var/www/html';require '/var/www/html/master.inc.php';"

def readiness(web, enabled=False):
    code = BOOT + r"""require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if(DOL_VERSION!=='24.0.1'||(int)$conf->entity!==1||!isModEnabled('agenda')||!isModEnabled('chinaplanetoperations'))exit(11);
foreach(['SELECT entity,fk_task,payload,version FROM '.MAIN_DB_PREFIX.'cpo_task_details LIMIT 0','SELECT entity,rowid,status,date_archived,date_deadline,fk_user_assign,fk_stage,version FROM '.MAIN_DB_PREFIX.'cpo_task LIMIT 0'] as $q)if(!$db->query($q))exit(12);
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';$a=new User($db);if($a->fetch(1)<=0||!$a->admin||!$a->statut||$a->socid)exit(13);$a->loadRights();
echo 'READY';"""
    check(php(web,code).strip()==b'READY','RUNTIME_PREREQUISITE_FAILED')

def config(web, old=None):
    code=BOOT + "require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';"
    if old is None:
        code+="echo json_encode(['flag'=>(string)dolibarr_get_const($db,'CPO_BOARDS_ENABLED',1),'hooks'=>(string)dolibarr_get_const($db,'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS',1)]);"
        return json.loads(php(web,code))
    for key,name in [('flag','CPO_BOARDS_ENABLED'),('hooks','MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS')]:
        value=base64.b64encode(old[key].encode()).decode()
        code+=f"if(dolibarr_set_const($db,'{name}',base64_decode('{value}'),'chaine',0,'',1)<=0)exit(30);"
    code+="echo 'CONFIG_RESTORED';"
    check(php(web,code).strip()==b'CONFIG_RESTORED','CONFIG_RESTORE_FAILED')

def migrate(web):
    code=BOOT+r"""require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsMigration.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsLegacyMigration.class.php';
(new InternalTaskBoardsMigration($db))->apply();
function digest97($table){global $db;$r=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.$table.' ORDER BY 1');if(!$r)throw new RuntimeException('DIGEST_QUERY');$h=hash_init('sha256');while($o=$db->fetch_object($r))hash_update($h,json_encode((array)$o));return hash_final($h);}
$db->begin();try{
 $lock=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity=1 FOR UPDATE');if(!$lock)throw new RuntimeException('TASK_LOCK');
 $protected=['cpo_task','cpo_task_details','cpo_task_asset','user_rights','usergroup_rights','cpo_task_team_manager'];$before=[];foreach($protected as $t)$before[$t]=digest97($t);
 $conf->global->CPO_BOARDS_ENABLED=1;$actor=new User($db);if($actor->fetch(1)<=0||!$actor->admin||!$actor->statut||$actor->socid)throw new RuntimeException('ADMIN_REQUIRED');$actor->loadRights();$result=(new InternalTaskBoardsLegacyMigration($db,$actor,1))->apply();
 foreach($protected as $t)if(digest97($t)!==$before[$t])throw new RuntimeException('PROTECTED_RECORDS_CHANGED');
 $r=$db->query('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX."cpo_task t WHERE t.entity=1 AND t.status<>'deleted' AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX.'cpo_board_card c WHERE c.entity=t.entity AND c.fk_task=t.rowid)');if(!$r||(int)$db->fetch_object($r)->n!==0)throw new RuntimeException('UNPLACED_TASKS');
 $h=array_filter(explode(':',(string)dolibarr_get_const($db,'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS',1)));if(!in_array('agenda',$h,true)){$h[]='agenda';if(dolibarr_set_const($db,'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS',implode(':',$h),'chaine',0,'',1)<=0)throw new RuntimeException('HOOK_FAILED');}
 if(dolibarr_set_const($db,'CPO_BOARDS_ENABLED','1','chaine',0,'',1)<=0)throw new RuntimeException('FLAG_FAILED');
 if(!$db->commit())throw new RuntimeException('COMMIT_FAILED');echo json_encode(['result'=>$result,'protected_unchanged'=>true,'task_digest'=>$before['cpo_task']]);
}catch(Throwable $e){while($db->transaction_opened>0)$db->rollback();exit(31);}"""
    result=json.loads(php(web,code));check(result['protected_unchanged'],'MIGRATION_INTEGRITY_FAILED');return result

def controls(web):
    code=BOOT+r"""require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsMigration.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
if((string)dolibarr_get_const($db,'CPO_BOARDS_ENABLED',1)!=='1')exit(32);(new InternalTaskBoardsMigration($db))->apply();
$a=new User($db);$a->fetch(1);$a->loadRights();$svc=new InternalTaskBoardsService($db,$a,1);$g=$svc->gallery();$seen=0;foreach($g['boards'] as $b){$w=$svc->workspace($b['rowid']);$seen+=count($w['cards'])+count($w['archived_cards']);}$svc->myWork();
$tested=0;$negative=0;$r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=0 AND statut=1 AND entity=1 AND fk_soc IS NULL');
$ids=[];while($o=$db->fetch_object($r))$ids[]=(int)$o->rowid;
foreach($ids as $id){$u=new User($db);$u->fetch($id);$u->loadRights();$native=new InternalTaskAuthorizationService($db,$u,1);if(!$native->canOpenBoard())continue;$s=new InternalTaskBoardsService($db,$u,1);$mine=$s->gallery();$allowed=array_column($mine['boards'],'rowid');foreach($mine['boards'] as $b){$w=$s->workspace($b['rowid']);foreach(array_merge($w['cards'],$w['archived_cards']) as $c)if(!(new InternalTaskService($db,$u,1))->requireReadableTask($c['fk_task']))exit(33);}$s->myWork();foreach($g['boards'] as $b)if(!in_array($b['rowid'],$allowed)){try{$s->workspace($b['rowid']);exit(34);}catch(InternalTaskAccessException $e){$negative++;break;}}$tested++;}
echo json_encode(['boards'=>count($g['boards']),'placed_cards'=>$seen,'staff_contexts'=>$tested,'unauthorized_board_denials'=>$negative,'native_rights_unchanged'=>true]);"""
    return json.loads(php(web,code))

def health(web):
    code = r'''$s=fsockopen('127.0.0.1',80,$e,$m,10);if(!$s)exit(1);stream_set_timeout($s,10);
fwrite($s,"GET /index.php HTTP/1.0\r\nHost: erp.visiontrading.biz\r\nConnection: close\r\n\r\n");
$r=stream_get_contents($s,200000);fclose($s);
if(!preg_match('/^HTTP\/1\.[01] 200 /',$r)||stripos($r,'login')===false)exit(2);echo 'HEALTH_OK';'''
    check(php(web,code).strip() == b'HEALTH_OK', 'HTTP_HEALTH_FAILED')

def backup_db(db, dest):
    shell = 'set -eu; if command -v mariadb-dump >/dev/null; then D=mariadb-dump; else D=mysqldump; fi; P="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"; test -n "$P"; exec "$D" --all-databases --single-transaction --routines --events -uroot -p"$P"'
    proc = subprocess.Popen(['docker','exec',db,'sh','-c',shell], stdout=subprocess.PIPE,
                            stderr=subprocess.DEVNULL, env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'})
    try:
        with gzip.open(dest,'xb',compresslevel=6) as out:
            while True:
                chunk=proc.stdout.read(1024*1024)
                if not chunk: break
                out.write(chunk)
        check(proc.wait(timeout=600)==0 and dest.stat().st_size > 1024, 'DB_BACKUP_FAILED')
        with gzip.open(dest,'rb') as checkstream:
            header = checkstream.read(1024)
            check(b'dump' in header.lower() and (header.startswith(b'--') or header.startswith(b'/*M!')),
                  'DB_BACKUP_INVALID')
    except BaseException:
        proc.kill(); dest.unlink(missing_ok=True); raise
    os.chmod(dest,0o600)

def write_json(path, value):
    encoded=json.dumps(value,sort_keys=True)
    temp=path.with_suffix('.tmp')
    with open(temp,'x') as out:
        out.write(encoded);out.flush();os.fsync(out.fileno())
    os.chmod(temp,0o600);os.replace(temp,path)

def report(value):
    print(json.dumps(value))
    dest=INBOX/('m097-'+value['environment']+'-'+value['action']+'-result.json')
    check(not dest.is_symlink(),'UNSAFE_RESULT_PATH')
    if dest.exists():dest.unlink()
    fd=os.open(dest,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o644)
    with os.fdopen(fd,'w') as stream:json.dump(value,stream,sort_keys=True)

def main():
    check(os.geteuid()==0 and len(sys.argv)==3 and sys.argv[1] in TARGETS and sys.argv[2] in ('preflight','apply','verify','rollback'), 'EXACT_ROOT_ACTION_REQUIRED')
    env, action=sys.argv[1:]
    manifest,payload=load_payload()
    web,db,root=target(env)
    state_dir=STATE_BASE/('chinaplanetos-m097-'+env)
    compare(root,manifest['prerequisites'][env])
    if action=='preflight':
        compare(root,manifest['before'][env])
        for name,data in payload.items():
            if name.endswith('.php'): command(['docker','exec','-i',web,'php','-l'],data=data)
        readiness(web); health(web); check(config(web)['flag'] in ('','0'),'FEATURE_ALREADY_ACTIVE')
        report({'environment':env,'action':action,'result':'PASS','files':len(payload)}); return
    state_dir.mkdir(mode=0o700,exist_ok=True)
    check(not state_dir.is_symlink() and stat.S_IMODE(state_dir.stat().st_mode)==0o700, 'STATE_UNSAFE')
    with open(state_dir/'lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        state_path=state_dir/'state.json'
        state=json.loads(state_path.read_text()) if state_path.exists() else None
        if action=='apply':
            check(state is None, 'REPLAY_REFUSED')
            compare(root,manifest['before'][env])
            readiness(web); health(web)
            for name,data in payload.items():
                if name.endswith('.php'): command(['docker','exec','-i',web,'php','-l'],data=data)
            before={name:file_info(root,name) for name in payload}
            write_json(state_dir/'code-backup.json',before)
            backup_db(db,state_dir/'database-before.sql.gz')
            old_config=config(web)
            state={'status':'APPLYING','release':RELEASE,'environment':env,'at':int(time.time()),
                   'previous_config':old_config,
                   'code_backup_sha256':sha((state_dir/'code-backup.json').read_bytes()),
                   'database_backup_sha256':sha((state_dir/'database-before.sql.gz').read_bytes())}
            write_json(state_path,state)
            try:
                for name in sorted(payload,key=lambda n:before[n] is not None): atomic(root,name,payload[name],before[name])
                compare(root,manifest['files']); readiness(web,True);state['migration']=migrate(web);state['acceptance']=controls(web);health(web)
                state['status']='DEPLOYED';write_json(state_path,state)
            except BaseException:
                try:
                    config(web,old_config);restore(root,before);compare(root,manifest['before'][env]);health(web)
                    state['status']='AUTO_ROLLED_BACK';write_json(state_path,state)
                except BaseException:
                    state['status']='RECOVERY_REQUIRED';write_json(state_path,state)
                raise
        elif action=='verify':
            check(state and state['status']=='DEPLOYED', 'NOT_DEPLOYED')
            compare(root,manifest['files']);readiness(web,True);state['acceptance']=controls(web);health(web)
        else:
            check(state and state['status']=='DEPLOYED', 'NOT_DEPLOYED')
            compare(root,manifest['files'])
            raw=(state_dir/'code-backup.json').read_bytes()
            check(sha(raw)==state['code_backup_sha256'], 'BACKUP_HASH_MISMATCH')
            config(web,state['previous_config']);restore(root,json.loads(raw))
            compare(root,manifest['before'][env]);health(web);state['status']='ROLLED_BACK';write_json(state_path,state)
        report({'environment':env,'action':action,'result':'PASS','status':state['status'],
                          'code_files':len(payload),'code_backup':state['code_backup_sha256'],'database_backup':state['database_backup_sha256'],
                          'native_service_acceptance':state.get('acceptance'),'migration':state.get('migration'),'visual_browser_acceptance':'PENDING'})

if __name__=='__main__':
    try: main()
    except BaseException as exc:
        print('M097_RELEASE_FAIL='+str(exc) if isinstance(exc,RuntimeError) else 'M097_RELEASE_FAIL='+type(exc).__name__,file=sys.stderr)
        sys.exit(1)
