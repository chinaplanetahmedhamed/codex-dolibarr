#!/usr/bin/env python3
"""One-time, fixed M119 runtime preparation. Does not replace or start the app."""
import datetime
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import selectors
import shutil
import signal
import stat
import subprocess
import tarfile
import time

APP = 'erp-staging-dolibarr-1'
APP_ID = '01d83d861962c7c95a3fbe837f0975d9db6fc71980612d300d3fe149e868079c'
BASE = 'sha256:56e459f0d2e7be2c9d809015b922624234f2899cc9f36e74fab1c5254913e482'
CANDIDATE = 'sha256:f4bb140e6a0ab98aeff0a3701b8f83d9ad28f3305a17bb604c4c320280a50b13'
CANDIDATE_TAG = 'local/dolibarr:m119-bcmath-3fcaf460'
SOURCE = '8b14b9e9c609ed296cb18368f576bbf86fffadbb'
VOLUME = 'erp-staging_m119_office_expenses_private'
PRIVATE = '/var/lib/chinaplanetos-office-expenses'
BACKUP = Path('/root/backup-erp-code-backups/m119-' + SOURCE[:12])
SNAPSHOT_TAG = 'local/dolibarr:m119-preserved-' + SOURCE[:12]
FINAL_TAG = 'local/dolibarr:m119-pilot-' + SOURCE[:12]
EXTENSION = '/usr/local/lib/php/extensions/no-debug-non-zts-20220829/bcmath.so'
INI = '/usr/local/etc/php/conf.d/docker-php-ext-bcmath.ini'
ENV = {'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'}


def run(args, data=None, timeout=60):
    fixed = ['docker','--host','unix:///var/run/docker.sock']+args[1:] if args[0]=='docker' else args
    r = subprocess.run(fixed, input=data, capture_output=True, timeout=timeout, env=ENV, cwd='/')
    if r.returncode:
        raise RuntimeError('A fixed staging preparation command failed; details withheld.')
    return r.stdout


def write_private(path, data):
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'wb') as f:
        f.write(data)
        f.flush()
        os.fsync(f.fileno())
    return hashlib.sha256(data).hexdigest()


def safe_root_directory(path):
    s = path.lstat()
    if not stat.S_ISDIR(s.st_mode) or s.st_uid != 0 or s.st_mode & 0o022 or path.resolve() != path:
        raise RuntimeError('Private administrative directory provenance differs.')


def archive_file(container, path):
    raw = run(['docker','cp',container+':'+path,'-'])
    if len(raw) > 131072:
        raise RuntimeError('Fixed configuration capture exceeds its bound.')
    with tarfile.open(fileobj=io.BytesIO(raw), mode='r:') as a:
        members = a.getmembers()
        if len(members) != 1 or not members[0].isfile() or members[0].name != Path(path).name or members[0].size > 65536:
            raise RuntimeError('Fixed configuration capture differs.')
        return a.extractfile(members[0]).read()


def inspect(name):
    return json.loads(run(['docker','inspect',name]))[0]


def native_readonly(php):
    return run(['docker','exec','--user','33:33',APP,'php','-r',php]).decode().strip()


def hash_file(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda:f.read(65536),b''):
            h.update(chunk)
    return h.hexdigest()


def database_backup(path, expected_hostname):
    """Exact staging socket/database, bounded streaming, no option files or secret output."""
    prefix = ['docker','exec','erp-staging-mariadb-1','sh','-c']
    client = 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mariadb --no-defaults --protocol=socket --socket=/run/mysqld/mysqld.sock --user=root --database=dolibarr_db --batch --skip-column-names --execute="SELECT DATABASE(), @@hostname, VERSION()"'
    row = run(prefix+[client]).decode().strip().split('\t')
    if len(row) != 3 or row[:2] != ['dolibarr_db',expected_hostname] or not row[2].startswith('10.6.27-MariaDB'):
        raise RuntimeError('Pinned staging dump socket/database identity differs.')
    run(['docker','exec','erp-staging-mariadb-1','timeout','--version'])
    command = 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec timeout --signal=TERM --kill-after=5 180 mariadb-dump --no-defaults --protocol=socket --socket=/run/mysqld/mysqld.sock --user=root --single-transaction --quick --skip-lock-tables --routines --events --triggers --hex-blob dolibarr_db'
    partial = path.with_name(path.name+'.incomplete')
    fd = os.open(partial,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    process = None
    count, tail, error_count = 0,b'',0
    try:
        with os.fdopen(fd,'wb') as file:
            process = subprocess.Popen(['docker','--host','unix:///var/run/docker.sock']+prefix[1:]+[command],stdout=subprocess.PIPE,stderr=subprocess.PIPE,env=ENV,cwd='/',start_new_session=True)
            deadline = time.monotonic()+195
            with selectors.DefaultSelector() as selector, gzip.GzipFile(fileobj=file,mode='wb',mtime=0,filename='') as gz:
                selector.register(process.stdout,selectors.EVENT_READ,'stdout')
                selector.register(process.stderr,selectors.EVENT_READ,'stderr')
                while selector.get_map():
                    if time.monotonic() >= deadline:
                        raise RuntimeError('Private staging dump deadline exceeded.')
                    for key,_ in selector.select(timeout=min(1,max(0,deadline-time.monotonic()))):
                        chunk = os.read(key.fileobj.fileno(),65536)
                        if not chunk:
                            selector.unregister(key.fileobj)
                            continue
                        if key.data == 'stderr':
                            error_count += len(chunk)
                            if error_count > 1048576:
                                raise RuntimeError('Private dump error stream exceeded its bound.')
                        else:
                            count += len(chunk)
                            if count > 2*1024**3:
                                raise RuntimeError('Private staging dump exceeds reserved capacity.')
                            tail = (tail+chunk)[-8192:]
                            gz.write(chunk)
            if process.wait(timeout=5) != 0 or count < 1024 or b'Dump completed' not in tail:
                raise RuntimeError('Private consistent staging database backup did not complete.')
            file.flush()
            os.fsync(file.fileno())
        if path.exists() or path.is_symlink():
            raise RuntimeError('Existing backup destination must never be overwritten.')
        os.rename(partial,path)
        return count, hash_file(path)
    finally:
        if process is not None:
            if process.poll() is None:
                os.killpg(process.pid,signal.SIGTERM)
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    os.killpg(process.pid,signal.SIGKILL)
                    process.wait(timeout=5)
            process.stdout.close()
            process.stderr.close()


def main():
    if os.geteuid() != 0 or os.uname().nodename != 'srv1011043' or 'verified_probe' not in globals():
        raise RuntimeError('Fixed host and checksum-verified preflight are required.')
    if verified_probe['main']() not in (None, 0):
        raise RuntimeError('Read-only staging context has not passed.')
    before = inspect(APP)
    if before['Id'] != APP_ID or before['Image'] != BASE or not before['State']['Running']:
        raise RuntimeError('Running staging application identity changed.')
    if json.loads(run(['docker','image','inspect',CANDIDATE_TAG]))[0]['Id'] != CANDIDATE:
        raise RuntimeError('Reviewed dependency image tag changed.')
    database = inspect('erp-staging-mariadb-1')
    if database['Id'] != 'b00e094f86f9fbe8cb62e47e54146ef46baaf7c69eb25091dd176b60bf04f79d' or not database['State']['Running'] or database['Image'] != 'sha256:daacc2f260f8ec999daa5e03a017a23a7e6fa3fb982aaf26e8b72f24daf03bc9':
        raise RuntimeError('Fixed staging database container changed.')
    expected_mounts = {'/var/www/html/custom','/var/www/documents'}
    if {m['Destination'] for m in before['Mounts']} != expected_mounts:
        raise RuntimeError('Unreviewed application mount detected.')
    if set(before['NetworkSettings']['Networks']) != {'erp-staging_erp-staging_internal','proxy_net'}:
        raise RuntimeError('Application network membership changed.')
    if BACKUP.exists() or BACKUP.is_symlink():
        raise RuntimeError('Preparation already has state; inspect it instead of replaying.')
    if run(['docker','volume','ls','--filter','name=^'+VOLUME+'$','--format','{{.Name}}']).strip():
        raise RuntimeError('Private volume already exists; no adoption or overwrite.')
    for tag in (SNAPSHOT_TAG, FINAL_TAG):
        if run(['docker','image','ls','--filter','reference='+tag,'--format','{{.ID}}']).strip():
            raise RuntimeError('Preparation image already exists; no tag overwrite.')
    root = BACKUP.parent
    if not root.exists():
        root.mkdir(mode=0o700)
    safe_root_directory(root)
    if shutil.disk_usage(root).free < 6 * 1024**3:
        raise RuntimeError('Insufficient private backup/build space.')
    php = "ob_start();require '/var/www/html/master.inc.php';$r=$db->query(\"SELECT DATABASE() AS d,@@hostname AS h,(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND ENGINE<>'InnoDB') AS n\");if(!$r)exit(2);$o=$db->fetch_object($r);ob_end_clean();echo json_encode($o);"
    identity = json.loads(native_readonly(php))
    if int(identity['n']) != 0 or identity['d'] != 'dolibarr_db' or identity['h'] != database['Config']['Hostname']:
        raise RuntimeError('A consistent InnoDB-only staging backup cannot be established.')
    config_hash = native_readonly("echo hash_file('sha256','/var/www/html/conf/conf.php');")
    if len(config_hash) != 64:
        raise RuntimeError('Native configuration fingerprint is unavailable.')
    BACKUP.mkdir(mode=0o700)
    safe_root_directory(BACKUP)
    phase = {'schema':1,'mission':'M119','source':SOURCE,'phase':'backup_started','application_id':APP_ID,'base_image':BASE,'candidate':CANDIDATE,'volume':VOLUME,'native_configuration_sha256':config_hash,'time':datetime.datetime.now(datetime.timezone.utc).isoformat()}
    write_private(BACKUP/'preparation.json', (json.dumps(phase,sort_keys=True,indent=2)+'\n').encode())
    write_private(BACKUP/'application-inspect.json', json.dumps(before,sort_keys=True).encode())
    original = archive_file('management-portainer-1','/data/compose/2/docker-compose.yml')
    compose_hash = write_private(BACKUP/'portainer-stack2-original.yml', original)
    # Only this dedicated staging DB. Credentials remain in its existing process environment.
    dump_path = BACKUP/'database-before.sql.gz'
    count, database_hash = database_backup(dump_path,database['Config']['Hostname'])
    snapshot = run(['docker','commit','--pause=true',APP,SNAPSHOT_TAG],timeout=120).decode().strip()
    if not snapshot.startswith('sha256:'):
        raise RuntimeError('Private current-rootfs snapshot did not return an immutable image.')
    # Preserve the current rootfs/config; copy ONLY the reviewed exact-ABI dependency files.
    dockerfile = ('FROM '+CANDIDATE_TAG+' AS dependency\nFROM '+SNAPSHOT_TAG+'\nCOPY --from=dependency '+EXTENSION+' '+EXTENSION+'\nCOPY --from=dependency '+INI+' '+INI+'\n').encode()
    build_root = BACKUP/'build'
    build_root.mkdir(mode=0o700)
    write_private(build_root/'Dockerfile',dockerfile)
    run(['docker','build','--pull=false','--network=none','-t',FINAL_TAG,str(build_root)],timeout=180)
    final = json.loads(run(['docker','image','inspect',FINAL_TAG]))[0]
    snapshot_info = json.loads(run(['docker','image','inspect',snapshot]))[0]
    if final['Config'] != snapshot_info['Config'] or final['RootFS']['Layers'][:len(snapshot_info['RootFS']['Layers'])] != snapshot_info['RootFS']['Layers']:
        raise RuntimeError('Preserved image configuration/layers differ beyond the dependency.')
    check = "require '/var/www/html/version.inc.php';if(DOL_VERSION!=='24.0.1'||PHP_VERSION!=='8.2.32'||!extension_loaded('bcmath')||!function_exists('imagejpeg')||!extension_loaded('mysqli')||bcadd('0.1','0.2',2)!=='0.30'||hash_file('sha256','/var/www/html/conf/conf.php')!=='"+config_hash+"')exit(2);echo 'M119_PRESERVED_RUNTIME_PASS';"
    result = run(['docker','run','--rm','--network','none','--read-only','--user','33:33','--entrypoint','php',final['Id'],'-r',check])
    if result != b'M119_PRESERVED_RUNTIME_PASS':
        raise RuntimeError('Preserved runtime capability/configuration acceptance failed.')
    run(['docker','volume','create','--driver','local','--label','cpo.mission=M119',VOLUME])
    volume = json.loads(run(['docker','volume','inspect',VOLUME]))[0]
    volume_root = Path('/var/lib/docker/volumes')/VOLUME/'_data'
    if volume['Driver'] != 'local' or volume.get('Options') or volume['Mountpoint'] != str(volume_root) or volume.get('Labels',{}).get('cpo.mission') != 'M119':
        raise RuntimeError('New private volume provenance differs.')
    safe_root_directory(volume_root)
    if list(volume_root.iterdir()):
        raise RuntimeError('New private volume is unexpectedly nonempty.')
    os.chown(volume_root,0,33)
    os.chmod(volume_root,0o750)
    receipts = volume_root/'receipts'
    receipts.mkdir(mode=0o700)
    os.chown(receipts,33,33)
    marker = receipts/'.cpo-office-expenses-private'
    write_private(marker,b'cpo-office-expenses-private-v1')
    os.chown(marker,33,33)
    pilot = {'version':1,'enabled':True,'environment':'backup-erp','entity':1,'user_id':1,'base_currency':'USD','offices':{'egypt':['EGP'],'china':['CNY']},'precision':{'EGP':2,'CNY':2,'USD':2},'private_root':PRIVATE+'/receipts'}
    write_private(volume_root/'pilot.json',(json.dumps(pilot)+'\n').encode())
    os.chown(volume_root/'pilot.json',0,33)
    os.chmod(volume_root/'pilot.json',0o640)
    after = inspect(APP)
    if after['Id'] != before['Id'] or after['Image'] != BASE or not after['State']['Running'] or after['Mounts'] != before['Mounts'] or after['Config'] != before['Config']:
        raise RuntimeError('Application changed during isolated runtime preparation.')
    result = {**phase,'phase':'runtime_prepared_not_activated','snapshot_image':snapshot,'snapshot_tag':SNAPSHOT_TAG,'final_image':final['Id'],'final_tag':FINAL_TAG,'portainer_original_sha256':compose_hash,'database_backup_sha256':database_hash,'database_uncompressed_bytes':count,'database_backup_bytes':dump_path.stat().st_size,'volume_mountpoint':str(volume_root),'application_replaced':False,'schema_installed':False}
    write_private(BACKUP/'runtime-ready.json',(json.dumps(result,sort_keys=True,indent=2)+'\n').encode())
    print(json.dumps({k:result[k] for k in ('phase','final_image','final_tag','snapshot_image','snapshot_tag','volume','application_replaced','schema_installed','database_backup_sha256','database_backup_bytes','portainer_original_sha256')}),flush=True)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(json.dumps({'status':'M119_RUNTIME_PREPARATION_STOP','error_class':type(error).__name__,'reason':str(error) if isinstance(error,RuntimeError) else 'Private preparation failed; inspect its own preserved state before any retry.'}),flush=True)
        raise SystemExit(1)
