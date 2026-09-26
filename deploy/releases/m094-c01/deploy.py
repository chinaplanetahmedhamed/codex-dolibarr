#!/usr/bin/python3 -I
"""Fixed-scope M094-C01 deployment; execute locally as root on the VPS.

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

RELEASE = 'M094-C01'
INBOX = Path('/home/codex-deploy/staging-inbox')
MANIFEST_SHA = '13c9eed4ad8d3948ab74a3fe2d117e984ee26d33f823f7ed75c7ff626a262674'
PAYLOAD_SHA = '31572e5f574f1e49907d1fa40bff9ab40d489763e9d84f48148627fc375df72a'
TARGETS = {
    'production': ('vision_erp', 'vision_db', 'vision-os_dolibarr_custom', 'vision-os'),
}
ALLOWED_OWNERS = (0,33)
ALLOWED_GROUPS = (0,33)
METADATA_OVERRIDES = {'chinaplanetoperations/workspace.php': (1002,1002,0o644)}
STATE = Path('/root/chinaplanetos-m094-c01-production')

def check(ok, reason):
    if not ok: raise RuntimeError(reason)

def sha(data): return hashlib.sha256(data).hexdigest()

def command(argv, timeout=90, data=None):
    result = subprocess.run(argv, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            timeout=timeout, env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'})
    check(result.returncode == 0, 'COMMAND_FAILED')
    return result.stdout

def load_payload():
    manifest_bytes = (INBOX / 'm094-c01-manifest.json').read_bytes()
    payload_bytes = (INBOX / 'm094-c01-payload.tar.gz').read_bytes()
    check(sha(manifest_bytes) == MANIFEST_SHA and sha(payload_bytes) == PAYLOAD_SHA, 'ARTIFACT_HASH_MISMATCH')
    manifest = json.loads(manifest_bytes)
    check(manifest.get('release') == RELEASE and manifest.get('payload_sha256') == PAYLOAD_SHA, 'MANIFEST_MISMATCH')
    names = set(manifest['files'])
    check(len(names) == 5 and all(not name.startswith('/') and '..' not in Path(name).parts for name in names), 'INVALID_ALLOWLIST')
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
    info = json.loads(command(['docker','inspect',web]))[0]
    check(info['State']['Running'] and info['State'].get('Health',{}).get('Status','healthy') == 'healthy', 'ERP_NOT_HEALTHY')
    mounts = [m for m in info['Mounts'] if m['Destination'] == '/var/www/html/custom']
    root = Path('/var/lib/docker/volumes') / volume / '_data'
    check(len(mounts) == 1 and mounts[0]['Type'] == 'volume' and mounts[0]['Name'] == volume and mounts[0]['Source'] == str(root) and mounts[0]['RW'], 'CUSTOM_MOUNT_MISMATCH')
    if project:
        check(info['Config']['Labels'].get('com.docker.compose.project') == project, 'COMPOSE_IDENTITY_MISMATCH')
    dbinfo = json.loads(command(['docker','inspect',db]))[0]
    check(dbinfo['State']['Running'], 'DB_NOT_RUNNING')
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
    temp = path.parent / ('.m094-c01-' + str(os.getpid()) + '-' + path.name)
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

def readiness(web, enabled=True):
    code = BOOT + r'''require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
if(DOL_VERSION!=='23.0.3'||(int)$conf->entity!==1||!isModEnabled('agenda')||!isModEnabled('chinaplanetoperations'))exit(11);
foreach(array('SELECT entity,fk_task,payload,version FROM '.MAIN_DB_PREFIX.'cpo_task_details LIMIT 0',
'SELECT entity,rowid,status,date_archived,date_deadline,fk_user_assign FROM '.MAIN_DB_PREFIX.'cpo_task LIMIT 0',
"SELECT JSON_EXTRACT('{\"start_date\":\"2026-09-27\"}', '$.start_date')") as $q){if(!$db->query($q))exit(12);}
$h=explode(':',(string)dolibarr_get_const($db,'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS',1));
if(!in_array('login',$h,true))exit(13);'''
    if enabled: code += "if(!in_array('agenda',$h,true))exit(14);require_once '/var/www/html/custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php';"
    code += r"""
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php';
$actor=new User($db);if($actor->fetch(11)<=0||$actor->statut!=1||(int)$actor->entity!==1)exit(15);$actor->loadRights();
$service=new InternalTaskService($db,$actor,1);$task=array('rowid'=>0,'entity'=>1,'status'=>'todo','fk_user_assign'=>11,'fk_user_creat'=>11,'fk_usergroup'=>4,'fk_operation'=>0,'date_archived'=>null);
if(!$task||!$service->authorization()->canReadTask($task))exit(16);
$events=(new InternalTaskAgendaService($db,$actor,1))->events(strtotime('2026-09-01'),strtotime('2026-10-01'));
if(!is_array($events))exit(17);
echo 'READY';"""
    check(php(web,code).strip() == b'READY', 'RUNTIME_PREREQUISITE_FAILED')

def controls(web):
    code = BOOT + r'''require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskAssetService.class.php';
if(InternalTaskAssetService::MAX_FILE_BYTES!==209715200)exit(20);
$p=tempnam(sys_get_temp_dir(),'m094-c01-');
try {
 $z=new ZipArchive();if($z->open($p,ZipArchive::OVERWRITE)!==true)exit(21);
 $z->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
 $z->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
 $rel='<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
 $z->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rel.'</Relationships>');
 $z->addFromString('docProps/core.xml','<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"/>');$z->close();
 $s=new InternalTaskAssetService($db,$user,1);$m=new ReflectionMethod($s,'validateAndNormalize');$m->setAccessible(true);$result=$m->invoke($s,$p,'metadata.xlsx');
 if($result['type']!=='document'||$result['sha256']!==hash_file('sha256',$p))exit(22);
 $z=new ZipArchive();$z->open($p);$z->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.str_replace('Target="docProps/core.xml"','Target="https://example.invalid/core.xml" TargetMode="External"',$rel).'</Relationships>');$z->close();
 $denied=false;try{$m->invoke($s,$p,'blocked.xlsx');}catch(RuntimeException $e){$denied=$e->getMessage()==='External Office relationships are not allowed.';}if(!$denied)exit(23);
 if(InternalTaskFileValidator::errorTranslationKey(new RuntimeException('External Office relationships are not allowed.'))!=='InternalTaskOfficeExternalLinks')exit(24);
 if(InternalTaskFileValidator::errorTranslationKey(new RuntimeException('secret'))!=='InternalTaskValidationError')exit(25);
 echo 'CONTROLS_READY';
} finally {if(is_file($p))unlink($p);}'''
    check(php(web,code).strip()==b'CONTROLS_READY','CONTROLS_CHECK_FAILED')

def health(web):
    code = r'''$s=fsockopen('127.0.0.1',80,$e,$m,10);if(!$s)exit(1);stream_set_timeout($s,10);
fwrite($s,"GET /index.php HTTP/1.0\r\nHost: erp.visiontrading.biz\r\nConnection: close\r\n\r\n");
$r=stream_get_contents($s,200000);fclose($s);
if(!preg_match('/^HTTP\/1\.[01] 200 /',$r)||stripos($r,'login')===false)exit(2);echo 'HEALTH_OK';'''
    check(php(web,code).strip() == b'HEALTH_OK', 'HTTP_HEALTH_FAILED')

def upload_config(web, root):
    """Check actual Apache SAPI and inherited asset.php limits, then remove probe."""
    directory=root/'chinaplanetoperations/tasks/m094-c01-upload-probe'
    check(not directory.exists() and not directory.is_symlink(),'PROBE_COLLISION')
    directory.mkdir(mode=0o755);directory.chmod(0o755)
    path=directory/'asset.php'
    probe=b"<?php if(($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}echo json_encode(array('sapi'=>PHP_SAPI,'upload'=>ini_get('upload_max_filesize'),'post'=>ini_get('post_max_size')));"
    try:
        path.write_bytes(probe);path.chmod(0o644)
        code=r'''$s=fsockopen('127.0.0.1',80,$e,$m,10);if(!$s)exit(1);stream_set_timeout($s,10);fwrite($s,"GET /custom/chinaplanetoperations/tasks/m094-c01-upload-probe/asset.php HTTP/1.0\r\nHost: erp.visiontrading.biz\r\nConnection: close\r\n\r\n");$r=stream_get_contents($s,100000);fclose($s);if(!preg_match('/^HTTP\/1\.[01] 200 /',$r))exit(2);$parts=explode("\r\n\r\n",$r,2);echo $parts[1]??'';'''
        result=json.loads(php(web,code))
        check(result=={'sapi':'apache2handler','upload':'200M','post':'201M'},'APACHE_UPLOAD_LIMIT_MISMATCH')
    finally:
        path.unlink(missing_ok=True);directory.rmdir()


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
    temp=path.with_suffix('.tmp')
    with open(temp,'x') as out:
        json.dump(value,out,sort_keys=True);out.flush();os.fsync(out.fileno())
    os.chmod(temp,0o600);os.replace(temp,path)

def report(value):
    print(json.dumps(value))
    dest=INBOX/('m094-c01-'+value['action']+'-result.json')
    check(not dest.is_symlink(),'UNSAFE_RESULT_PATH')
    if dest.exists():dest.unlink()
    fd=os.open(dest,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o644)
    with os.fdopen(fd,'w') as stream:json.dump(value,stream,sort_keys=True)

def main():
    check(os.geteuid()==0 and len(sys.argv)==3 and sys.argv[1] in TARGETS and sys.argv[2] in ('preflight','apply','verify','rollback'), 'EXACT_ROOT_ACTION_REQUIRED')
    env, action=sys.argv[1:]
    manifest,payload=load_payload()
    web,db,root=target(env)
    state_dir=STATE
    compare(root,manifest['prerequisites'])
    if action=='preflight':
        compare(root,manifest['before'][env])
        for name,data in payload.items():
            if name.endswith('.php'): command(['docker','exec','-i',web,'php','-l'],data=data)
        readiness(web); health(web)
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
            state={'status':'APPLYING','release':RELEASE,'environment':env,'at':int(time.time()),
                   'code_backup_sha256':sha((state_dir/'code-backup.json').read_bytes()),
                   'database_backup_sha256':sha((state_dir/'database-before.sql.gz').read_bytes())}
            write_json(state_path,state)
            try:
                for name in sorted(payload,key=lambda n:before[n] is not None): atomic(root,name,payload[name],before[name])
                compare(root,manifest['files']); readiness(web,True);controls(web);upload_config(web,root);health(web)
                state['status']='DEPLOYED';write_json(state_path,state)
            except BaseException:
                try:
                    restore(root,before);compare(root,manifest['before'][env]);health(web)
                    state['status']='AUTO_ROLLED_BACK';write_json(state_path,state)
                except BaseException:
                    state['status']='RECOVERY_REQUIRED';write_json(state_path,state)
                raise
        elif action=='verify':
            check(state and state['status']=='DEPLOYED', 'NOT_DEPLOYED')
            compare(root,manifest['files']);readiness(web,True);controls(web);upload_config(web,root);health(web)
        else:
            check(state and state['status']=='DEPLOYED', 'NOT_DEPLOYED')
            compare(root,manifest['files'])
            raw=(state_dir/'code-backup.json').read_bytes()
            check(sha(raw)==state['code_backup_sha256'], 'BACKUP_HASH_MISMATCH')
            restore(root,json.loads(raw))
            compare(root,manifest['before'][env]);health(web);state['status']='ROLLED_BACK';write_json(state_path,state)
        report({'environment':env,'action':action,'result':'PASS','status':state['status'],
                          'code_files':len(payload),'code_backup':state['code_backup_sha256'],'database_backup':state['database_backup_sha256'],
                          'authenticated_acceptance':'PENDING'})

if __name__=='__main__':
    try: main()
    except BaseException as exc:
        print('M094-C01_RELEASE_FAIL='+str(exc) if isinstance(exc,RuntimeError) else 'M094-C01_RELEASE_FAIL='+type(exc).__name__,file=sys.stderr)
        sys.exit(1)
