#!/usr/bin/python3 -I
"""Fixed M085 staging code release. Generated copies embed reviewed commit bytes.
No migrations, container restarts, configuration, document or business-data writes.
"""
import base64
import fcntl
import hashlib
import json
import os
import secrets
from pathlib import Path
import shutil
import stat
import subprocess
import sys
import tempfile
import time
import urllib.request

# Replaced by the build script, never supplied by a privileged caller.
PAYLOAD = None
CONTAINER = 'erp-staging-dolibarr-1'
VOLUME = 'erp-staging_erp-staging_custom'
ROOT = Path('/var/lib/docker/volumes') / VOLUME / '_data' / 'chinaplanetoperations'
STATE = Path('/var/lib/chinaplanetos-m085-staging')
HELPER = Path('/usr/local/sbin/chinaplanetos-m085-staging')
SUDOERS = Path('/etc/sudoers.d/codex-deploy-m085-staging')
ACTIONS = ('preflight', 'apply', 'verify', 'rollback', 'status')
DOCKER = '/usr/bin/docker'


def fail(code):
    raise RuntimeError(code)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def parent_fd(path, create=False):
    """Walk without following symlinks; pin each directory for all privileged IO."""
    path=path.absolute()
    fd=os.open('/',os.O_RDONLY|os.O_DIRECTORY)
    walked=Path('/')
    try:
        for part in path.parent.parts[1:]:
            walked=walked/part
            try:
                child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
            except FileNotFoundError:
                if not create or walked != ROOT/'langs/fr_FR': raise
                os.mkdir(part,0o755,dir_fd=fd)
                child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
                os.fchown(child,0,0)
            except OSError:
                fail('UNSAFE_PARENT')
            os.close(fd);fd=child
        return fd
    except BaseException:
        os.close(fd);raise


def regular(path):
    try: fd=parent_fd(path)
    except FileNotFoundError: return
    try:
        try: st=os.stat(path.name,dir_fd=fd,follow_symlinks=False)
        except FileNotFoundError: return
        if not stat.S_ISREG(st.st_mode) or st.st_nlink != 1: fail('UNSAFE_FILE')
    finally: os.close(fd)


def read_with_stat(path):
    directory=parent_fd(path)
    try:
        fd=os.open(path.name,os.O_RDONLY|os.O_NOFOLLOW,dir_fd=directory)
        with os.fdopen(fd,'rb') as stream:
            st=os.fstat(stream.fileno())
            if not stat.S_ISREG(st.st_mode) or st.st_nlink!=1: fail('UNSAFE_FILE')
            return stream.read(),st
    finally: os.close(directory)


def read(path): return read_with_stat(path)[0]


def remove(path):
    directory=parent_fd(path)
    try:
        try:
            st=os.stat(path.name,dir_fd=directory,follow_symlinks=False)
            if not stat.S_ISREG(st.st_mode) or st.st_nlink!=1:fail('UNSAFE_FILE')
            os.unlink(path.name,dir_fd=directory);os.fsync(directory)
        except FileNotFoundError: pass
    finally: os.close(directory)


def atomic(path, data, uid=0, gid=0, mode=0o644):
    directory=parent_fd(path,create=True)
    tmp='.m085-'+secrets.token_hex(16)
    try:
        fd=os.open(tmp,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600,dir_fd=directory)
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data);stream.flush()
            os.fchown(stream.fileno(), uid, gid);os.fchmod(stream.fileno(), mode)
            os.fsync(stream.fileno())
        os.replace(tmp,path.name,src_dir_fd=directory,dst_dir_fd=directory)
        os.fsync(directory)
    finally:
        try: os.unlink(tmp,dir_fd=directory)
        except FileNotFoundError: pass
        os.close(directory)


def command(args, data=None):
    proc = subprocess.run(args, input=data, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                          timeout=45, env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'})
    if proc.returncode:
        fail('FIXED_COMMAND_FAILED')
    return proc.stdout


def inspect(name, require_healthy=True):
    # Explicit projection: do not load Docker environment/credentials into output.
    template = '{{json .State}}|{{json .Mounts}}|{{json .NetworkSettings.Ports}}|{{index .Config.Labels "com.docker.compose.project"}}'
    parts = command([DOCKER, 'inspect', '--format', template, name]).decode().strip().split('|')
    if len(parts) != 4 or parts[3] != 'erp-staging':
        fail('COMPOSE_IDENTITY_MISMATCH')
    state, mounts, ports = map(json.loads, parts[:3])
    if not state.get('Running') or (require_healthy and state.get('Health', {}).get('Status', 'healthy') != 'healthy'):
        fail('CONTAINER_NOT_HEALTHY')
    return mounts, ports


def php(code):
    return command([DOCKER, 'exec', '-i', '--user', 'www-data', CONTAINER, 'php', '-d', 'display_errors=0', '-r', code])


def environment(require_healthy=True):
    mounts, ports = inspect(CONTAINER,require_healthy)
    matches = [m for m in mounts if m.get('Destination') == '/var/www/html/custom']
    if len(matches) != 1 or matches[0].get('Name') != VOLUME or matches[0].get('Type') != 'volume' or not matches[0].get('RW') or matches[0].get('Source') != str(ROOT.parent):
        fail('CUSTOM_MOUNT_MISMATCH')
    if ports.get('80/tcp') != [{'HostIp':'127.0.0.1','HostPort':'18088'}]:
        fail('ERP_PORT_MISMATCH')
    _, mailports = inspect('erp-staging-mailpit-1',require_healthy)
    if mailports.get('1025/tcp') != [{'HostIp':'127.0.0.1','HostPort':'11025'}] or mailports.get('8025/tcp') != [{'HostIp':'127.0.0.1','HostPort':'18025'}]:
        fail('MAILPIT_PORT_MISMATCH')
    for path in (ROOT.parent, STATE):
        if path.is_symlink() or not path.is_dir() or path.stat().st_uid != 0 or path.stat().st_mode & 0o022:
            fail('ROOT_OWNERSHIP_MISMATCH')
    if shutil.disk_usage(ROOT).free < 100_000_000:
        fail('INSUFFICIENT_SPACE')
    if php('echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;').decode() != '8.2':
        fail('PHP_BASELINE_MISMATCH')


def schema():
    # Fixed SELECT-only schema/config capability check. No values or records are printed.
    code = r'''define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);define('NOCSRFCHECK',1);
$_SERVER['DOCUMENT_ROOT']='/var/www/html';require '/var/www/html/master.inc.php';
$checks=array('cpo_public_request'=>array('schema_version','fk_socpeople','qualification_status','request_customer_visible','date_anonymized'),'cpo_public_request_event'=>array('entity','fk_request','event_type','state_from','state_to','metadata_json','customer_visible','fk_user_creat','date_creation'),'cpo_public_request_line'=>array('verified_packing_source'));
foreach($checks as $table=>$columns)foreach($columns as $column){$r=$db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$table." LIKE '".$column."'");if(!$r||$db->num_rows($r)!==1)exit(12);}
$key=(string)getenv('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY');if(strlen($key)<32)$key=getDolGlobalString('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY');if(strlen($key)<32)exit(13);
if(!is_dir(DOL_DATA_ROOT)||!is_writable(DOL_DATA_ROOT)||!is_file(DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php'))exit(14);
echo 'M085_SCHEMA_READY';'''
    if php(code).strip() != b'M085_SCHEMA_READY':
        fail('SCHEMA_OR_CONFIGURATION_NOT_READY')


def validate_payload():
    if not isinstance(PAYLOAD, dict) or PAYLOAD.get('release') != 'M085-R01':
        fail('UNBOUND_TEMPLATE')
    for name, item in PAYLOAD['files'].items():
        if name.startswith('/') or '..' in Path(name).parts:
            fail('INVALID_PAYLOAD_PATH')
        if digest(base64.b64decode(item['data'], validate=True)) != item['sha256']:
            fail('PAYLOAD_CHECKSUM_MISMATCH')


def current(name):
    path = ROOT / name
    regular(path)
    return digest(read(path)) if path.exists() else None


def compare(deployed=False):
    for name, item in PAYLOAD['files'].items():
        if current(name) != (item['sha256'] if deployed else item['before']):
            fail('BASELINE_MISMATCH:'+name)
    for name, sha in PAYLOAD['prerequisites'].items():
        if current(name) != sha:
            fail('DEPENDENCY_MISMATCH:'+name)


def lint():
    for name, item in PAYLOAD['files'].items():
        if name.endswith('.php'):
            command([DOCKER,'exec','-i',CONTAINER,'php','-l'], base64.b64decode(item['data']))


def health():
    # Local Dolibarr login shell only; authenticated workflow acceptance is separate.
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args,**kwargs): fail('UNEXPECTED_HTTP_REDIRECT')
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())
    with opener.open('http://127.0.0.1:18088/', timeout=15) as response:
        body = response.read(1_000_000)
        if response.status != 200 or b'Dolibarr' not in body or b'type="password"' not in body:
            fail('LOGIN_HEALTH_FAILED')


def preflight(deployed=False):
    environment(); compare(deployed); schema(); lint(); health()


def snapshot():
    result = {}
    for name in PAYLOAD['files']:
        path = ROOT/name
        if path.exists():
            data,st = read_with_stat(path)
            result[name] = {'data':base64.b64encode(data).decode(),'sha256':digest(data),'uid':st.st_uid,'gid':st.st_gid,'mode':stat.S_IMODE(st.st_mode)}
        else:
            result[name] = None
    return result


def restore(saved):
    if set(saved) != set(PAYLOAD['files']):
        fail('BACKUP_SCOPE_MISMATCH')
    # Validate every byte before the first restoration.
    for value in saved.values():
        if value and digest(base64.b64decode(value['data'],validate=True)) != value['sha256']:
            fail('BACKUP_CHECKSUM_MISMATCH')
    for name, value in saved.items():
        path=ROOT/name
        if value is None:
            if path.exists() or path.is_symlink(): remove(path)
        else:
            atomic(path,base64.b64decode(value['data']),value['uid'],value['gid'],value['mode'])


def save_state(value):
    atomic(STATE/'state.json',json.dumps(value,sort_keys=True).encode(),mode=0o600)


def load_state():
    path=STATE/'state.json'
    return json.loads(read(path)) if path.exists() else {}


def install():
    # Install this exact pinned candidate only; existing releases cannot be replaced.
    if HELPER.exists() or HELPER.is_symlink() or SUDOERS.exists() or SUDOERS.is_symlink() or STATE.exists() or STATE.is_symlink():
        fail('INSTALL_ALREADY_EXISTS')
    source=read(Path(__file__).absolute())
    sudo=''.join('codex-deploy ALL=(root) NOPASSWD: '+str(HELPER)+' '+action+'\n' for action in ACTIONS).encode()
    fd,tmp=tempfile.mkstemp(prefix='m085-sudo-',dir='/etc/sudoers.d')
    try:
        with os.fdopen(fd,'wb') as stream: stream.write(sudo)
        os.chmod(tmp,0o440)
        command(['/usr/sbin/visudo','-cf',tmp])
        STATE.mkdir(mode=0o700)
        atomic(HELPER,source,mode=0o755)
        atomic(SUDOERS,sudo,mode=0o440)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)
    print(json.dumps({'install':'PASS','helper_sha256':digest(source),'actions':ACTIONS,'apply_executed':False,'production_accessed':False}))


def state_directory_valid():
    return not STATE.is_symlink() and STATE.is_dir() and STATE.stat().st_uid == 0 and stat.S_IMODE(STATE.stat().st_mode)==0o700


def main():
    if os.geteuid()!=0 or len(sys.argv)!=2 or sys.argv[1] not in ACTIONS+('install',):
        fail('EXACT_ROOT_ACTION_REQUIRED')
    validate_payload()
    action=sys.argv[1]
    if action=='install': install();return
    if not state_directory_valid():
        fail('STATE_DIRECTORY_INVALID')
    regular(STATE/'release.lock')
    with open(STATE/'release.lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        state=load_state()
        if action=='status':
            print(json.dumps({'release':PAYLOAD['release'],'commit':PAYLOAD['commit'],'state':state.get('status','NOT_APPLIED')}));return
        if action=='preflight': preflight()
        elif action=='verify':
            if state.get('status')!='DEPLOYED':fail('NOT_DEPLOYED')
            preflight(True)
        elif action=='apply':
            if state:fail('REPLAY_REFUSED')
            preflight()
            before=snapshot();data=json.dumps(before,sort_keys=True).encode()
            atomic(STATE/'before.json',data,mode=0o600)
            state={'status':'APPLYING','commit':PAYLOAD['commit'],'backup_sha256':digest(data),'at':int(time.time())}
            save_state(state)
            try:
                compare()
                for name,item in PAYLOAD['files'].items():
                    prior=before[name]
                    atomic(ROOT/name,base64.b64decode(item['data']),prior['uid'] if prior else 33,prior['gid'] if prior else 33,prior['mode'] if prior else 0o644)
                preflight(True)
                state['status']='DEPLOYED';save_state(state)
            except BaseException:
                restore(before);compare();health()
                state['status']='AUTO_ROLLED_BACK';save_state(state)
                raise
        elif action=='rollback':
            if state.get('status') not in ('DEPLOYED','APPLYING'):fail('NOT_DEPLOYED_OR_INTERRUPTED')
            environment(False)
            for name,item in PAYLOAD['files'].items():
                if current(name) not in (item['before'],item['sha256']):fail('ROLLBACK_DRIFT:'+name)
            for name,sha in PAYLOAD['prerequisites'].items():
                if current(name)!=sha:fail('DEPENDENCY_MISMATCH:'+name)
            data=read(STATE/'before.json')
            if digest(data)!=state['backup_sha256']:fail('BACKUP_CHECKSUM_MISMATCH')
            safety=snapshot();atomic(STATE/'before-rollback.json',json.dumps(safety,sort_keys=True).encode(),mode=0o600)
            try:
                restore(json.loads(data));compare();health()
            except BaseException:
                restore(safety);compare(True);health();raise
            state['status']='ROLLED_BACK';save_state(state)
        print(json.dumps({'action':action,'result':'PASS','release':PAYLOAD['release'],'commit':PAYLOAD['commit'],'database_changed':False,'production_accessed':False,'authenticated_acceptance':'PENDING'}))

if __name__=='__main__':
    try: main()
    except BaseException as exc:
        # Never emit subprocess stdout/stderr or configuration contents.
        print('M085_RELEASE_FAIL='+str(exc) if isinstance(exc,RuntimeError) else 'M085_RELEASE_FAIL='+type(exc).__name__,file=sys.stderr)
        sys.exit(1)
