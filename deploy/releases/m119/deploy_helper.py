#!/usr/bin/python3 -I
"""Fixed M119 backup-erp deployment handler. Exactly one action; no container lifecycle.

Install root-owned behind the existing five-action helper, never under the web root.
The privileged launcher must use isolated system Python (-I), never caller import paths.
Runtime/image and library pins are administrative constants, never request parameters.
An uncertain apply is disabled and retained, and cannot be retried. Rollback never restores a DB.
"""
import fcntl
import ctypes
import gzip
import hashlib
import http.client
import io
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tarfile
import time
import uuid

COMMIT = '8b14b9e9c609ed296cb18368f576bbf86fffadbb'
ARTIFACT_SHA = 'faa869ec8d05b6e631887bb1dbe0c403deb9d97909cea84ede492ee172bca698'
MANIFEST_SHA = '19aa7af9ebc3f6f0dadb6708dec5b182f5dc55a884a42157abc79bb60ef329a7'
FINAL_IMAGE = 'sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9'
BACKUP_LIBRARY_SHA = 'c0d13dda18df364b3a3020ae06fedf1683944a6718db10d723975fcf67ce5ef0'
INBOX = Path('/home/codex-deploy/staging-inbox')
STATE = Path('/var/lib/chinaplanetos-backup-erp-deploy/m119')
BACKUP_LIBRARY = STATE / 'prepare_runtime.py'
CUSTOM = Path('/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data')
TARGET = CUSTOM / 'chinaplanetoperations/office_expenses'
PRIVATE = Path('/var/lib/docker/volumes/erp-staging_m119_office_expenses_private/_data')
APP = 'erp-staging-dolibarr-1'
DB = 'erp-staging-mariadb-1'
MAIL = 'erp-staging-mailpit-1'
DB_ID = 'b00e094f86f9fbe8cb62e47e54146ef46baaf7c69eb25091dd176b60bf04f79d'
MAIL_ID = '92ab1e651f4b6892d67f0fb83a6f75a185f29ca8c74883bf8904fe71a6a7d295'
BASE_SCHEMA = 'a88a40ae71abc63fa836036488b51d3edef72645e349f396a1ed3a4d4aeef88b'
RECEIPT_SCHEMA = '408e34a1e939062951c91cf1d08cbc488877fb027f26bda96318541cabc8e78e'
MODULE = 'custom/chinaplanetoperations/office_expenses/'
RUNTIME = (
    'index.php', 'employee.css', 'employee.js', 'assets/Cairo-OFL.txt', 'assets/Cairo.ttf',
    'assets/Font-Awesome-LICENSE.txt', 'assets/fa-solid-900.woff2',
    'assets/fontawesome-solid.css', 'assets/fontawesome.css',
    'class/OfficeExpenseDraftService.php', 'class/OfficeExpenseEmployeeController.php',
    'class/OfficeExpenseEmployeePage.php', 'class/OfficeExpenseMoney.php',
    'class/OfficeExpenseNativeConnection.php', 'class/OfficeExpenseNativeDraftStore.php',
    'class/OfficeExpenseNativeReceiptStore.php', 'class/OfficeExpenseNativeSchemaMigration.php',
    'class/OfficeExpenseNativeStorageFactory.php', 'class/OfficeExpensePilotConfiguration.php',
    'class/OfficeExpenseReceiptHttp.php', 'class/OfficeExpenseReceiptPanel.php',
    'class/OfficeExpenseReceiptService.php', 'schema/001_drafts.sql',
    'schema/002_draft_audit.sql', 'schema/003_schema_state.sql', 'schema/manifest.json',
    'schema/receipts/001_receipts.sql', 'schema/receipts/002_receipt_audit.sql',
    'schema/receipts/manifest.json',
)
ALLOWLIST = frozenset(MODULE + p for p in RUNTIME) | {'support/native_operations.php', 'support/release.json'}
TABLES = ('cpo_oe_schema', 'cpo_oe_draft', 'cpo_oe_draft_audit', 'cpo_oe_receipt', 'cpo_oe_receipt_audit')
ACTIONS = ('preflight', 'apply', 'verify', 'rollback', 'status')
PHASES = ('backing_up', 'installing', 'files_staged', 'migrating', 'activating', 'active',
          'failed_disabled_retained', 'failed_disable_unconfirmed', 'rolling_back', 'rolled_back_disabled_retained')
ROOT_UID = 0
SERVICE_UID = 33
SERVICE_GID = 33
SAFE_ROOT = Path('/')
ENV = {'PATH': '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'}


class Refused(RuntimeError):
    pass


def decode_json(raw):
    def pairs(items):
        out = {}
        for key, value in items:
            if key in out:
                raise Refused('Duplicate JSON key.')
            out[key] = value
        return out
    try:
        return json.loads(raw, object_pairs_hook=pairs)
    except (ValueError, UnicodeError, RecursionError) as error:
        raise Refused('Invalid bounded JSON.') from error


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def require_runtime_pin():
    if re.fullmatch(r'sha256:[a-f0-9]{64}', FINAL_IMAGE) is None:
        raise Refused('Final reviewed runtime image is not pinned; deployment remains disabled.')


def validate_request(value, action):
    expected = {'schema': 1, 'action': action, 'mission_id': 'M119', 'commit': COMMIT,
                'artifact_sha256': ARTIFACT_SHA, 'artifact_name': 'artifact-'+ARTIFACT_SHA+'.tar.gz',
                'manifest_name': 'manifest-'+ARTIFACT_SHA+'.json'}
    if type(value) is not dict or type(value.get('schema')) is not int or value != expected or action not in ACTIONS:
        raise Refused('Request differs from the exact approved release/action contract.')


def safe_parents(directory, owners, root=Path('/')):
    directory, root = Path(directory), Path(root)
    if not directory.is_absolute() or not root.is_absolute() or root not in (directory, *directory.parents):
        raise Refused('Noncanonical private directory.')
    for path in (directory, *directory.parents):
        s = path.lstat()
        if not stat.S_ISDIR(s.st_mode) or s.st_uid not in owners or s.st_mode & 0o022 or path.resolve() != path:
            raise Refused('Unsafe private directory provenance.')
        if path == root:
            break


def secure_read(path, owner, mode, limit, root=Path('/')):
    """No symlink component, stable ordinary inode, exact owner/mode and hard bound."""
    path = Path(path)
    safe_parents(path.parent, {ROOT_UID, owner}, root)
    try:fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
    except OSError as error:raise Refused('Private ordinary file is unavailable.') from error
    try:
        before = os.fstat(fd)
        if (not stat.S_ISREG(before.st_mode) or before.st_uid != owner
                or stat.S_IMODE(before.st_mode) != mode or before.st_nlink != 1 or not 0 < before.st_size <= limit):
            raise Refused('Unsafe bounded private file.')
        with os.fdopen(fd, 'rb', closefd=False) as handle:
            raw = handle.read(limit+1)
        after = os.fstat(fd)
        named = path.lstat()
        safe_parents(path.parent, {ROOT_UID, owner}, root)
        def identity(s):
            return tuple(getattr(s,k) for k in ('st_dev','st_ino','st_mode','st_nlink','st_uid','st_gid','st_size','st_mtime_ns','st_ctime_ns'))
        if len(raw) != before.st_size or len(raw) > limit or identity(before) != identity(after) or identity(after) != identity(named):
            raise Refused('Private file changed during read.')
        return raw
    finally:
        os.close(fd)


def validate_package(manifest_raw, artifact):
    if len(manifest_raw) > 65536 or len(artifact) > 4*1024*1024 or digest(manifest_raw) != MANIFEST_SHA or digest(artifact) != ARTIFACT_SHA:
        raise Refused('Immutable artifact/manifest checksum differs.')
    manifest = decode_json(manifest_raw)
    if (type(manifest) is not dict or set(manifest) != {'schema','mission','commit','artifact_name','artifact_sha256','files'}
            or type(manifest['schema']) is not int or manifest['schema'] != 1 or manifest['mission'] != 'M119'
            or manifest['commit'] != COMMIT or manifest['artifact_sha256'] != ARTIFACT_SHA
            or manifest['artifact_name'] != 'artifact-'+ARTIFACT_SHA+'.tar.gz' or type(manifest['files']) is not list):
        raise Refused('Immutable manifest contract differs.')
    entries = {}
    for item in manifest['files']:
        if (type(item) is not dict or set(item) != {'path','kind','sha256','size','mode'}
                or item['path'] not in ALLOWLIST or item['path'] in entries or item['mode'] != '0644'
                or type(item['size']) is not int or not 0 < item['size'] <= 2*1024*1024
                or type(item['sha256']) is not str or re.fullmatch('[a-f0-9]{64}', item['sha256']) is None
                or item['kind'] != ('runtime' if item['path'].startswith(MODULE) else 'support')):
            raise Refused('Manifest file allowlist/metadata differs.')
        entries[item['path']] = item
    if set(entries) != ALLOWLIST or sum(e['size'] for e in entries.values()) > 4*1024*1024:
        raise Refused('Release dependency closure differs.')
    try:
        with gzip.GzipFile(fileobj=io.BytesIO(artifact)) as gz:
            unpacked = gz.read(5*1024*1024+1)
        if len(unpacked) > 5*1024*1024:
            raise Refused('Archive decompression bound exceeded.')
        files = {}
        with tarfile.open(fileobj=io.BytesIO(unpacked), mode='r:') as archive:
            for member in archive:
                if (member.name not in entries or member.name in files or member.type != tarfile.REGTYPE
                        or member.linkname or member.pax_headers or member.issparse() or member.mode != 0o644
                        or member.uid != 0 or member.gid != 0 or member.size != entries[member.name]['size']):
                    raise Refused('Archive entry provenance/type/metadata differs.')
                data = archive.extractfile(member).read(member.size+1)
                if len(data) != member.size or digest(data) != entries[member.name]['sha256']:
                    raise Refused('Archive member bytes differ.')
                files[member.name] = data
            if any(unpacked[archive.offset:]):
                raise Refused('Unexpected archive data after terminator.')
        if set(files) != ALLOWLIST:
            raise Refused('Archive dependency closure differs.')
    except (tarfile.TarError, OSError, EOFError) as error:
        raise Refused('Invalid bounded archive.') from error
    if digest(files[MODULE+'schema/manifest.json']) != BASE_SCHEMA or digest(files[MODULE+'schema/receipts/manifest.json']) != RECEIPT_SCHEMA:
        raise Refused('Reviewed additive schema pins differ.')
    if decode_json(files['support/release.json']) != {'schema':1,'mission':'M119','source_commit':COMMIT,'financial_posting':False}:
        raise Refused('Support release declaration differs.')
    return files


def docker(args, data=None, timeout=60):
    result = subprocess.run(['docker','--host','unix:///var/run/docker.sock']+args,
                            input=data, capture_output=True, env=ENV, cwd='/', timeout=timeout)
    if result.returncode or len(result.stdout) > 2*1024*1024 or len(result.stderr) > 65536:
        raise Refused('Fixed local staging command failed; private details withheld.')
    return result.stdout


def inspect(name):
    value = decode_json(docker(['inspect', name]))
    if type(value) is not list or len(value) != 1 or type(value[0]) is not dict:
        raise Refused('Fixed staging metadata is unavailable.')
    return value[0]


def install_file(path, data, dir_fd=None):
    """Create a new file atomically without replacing even an unexpected symlink."""
    path = Path(path)
    owned=dir_fd is None
    directory=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW) if owned else dir_fd
    name=path.name
    if not owned and str(path)!=name:raise Refused('Only a basename is allowed at the held directory boundary.')
    temporary='.m119-'+uuid.uuid4().hex+'.tmp'
    fd = os.open(temporary, os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW, 0o600,dir_fd=directory)
    try:
        with os.fdopen(fd, 'wb') as handle:
            handle.write(data); handle.flush(); os.fsync(handle.fileno()); os.fchmod(handle.fileno(), 0o644)
        try:
            os.link(temporary,name,src_dir_fd=directory,dst_dir_fd=directory,follow_symlinks=False)
        except FileExistsError as error:
            raise Refused('Existing destination must never be overwritten.') from error
    finally:
        os.unlink(temporary,dir_fd=directory)
        if owned:os.close(directory)
    if not owned:os.fsync(directory)


def sync_directory(path):
    fd = os.open(path, os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try: os.fsync(fd)
    finally: os.close(fd)


def ensure_state_directory():
    parent = STATE.parent
    if not parent.exists():
        safe_parents(parent.parent, {ROOT_UID}, SAFE_ROOT); parent.mkdir(mode=0o700)
    safe_parents(parent, {ROOT_UID}, SAFE_ROOT)
    if not STATE.exists(): STATE.mkdir(mode=0o700)
    safe_parents(STATE, {ROOT_UID}, SAFE_ROOT)
    if stat.S_IMODE(STATE.stat().st_mode) != 0o700:
        raise Refused('Root deployment state is not private.')


def open_directory(path):
    """Walk with openat/NOFOLLOW. Only the two known existing app directories may be app-owned."""
    path=Path(path)
    if not path.is_absolute() or SAFE_ROOT not in (path,*path.parents):raise Refused('Directory anchor differs.')
    fd=os.open(SAFE_ROOT,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        current=SAFE_ROOT
        for part in ('',*path.relative_to(SAFE_ROOT).parts):
            if part:
                nxt=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
                os.close(fd);fd=nxt;current=current/part
            s=os.fstat(fd)
            if current in (CUSTOM,TARGET.parent):
                valid=(s.st_uid==ROOT_UID and not s.st_mode&0o022) or (s.st_uid==SERVICE_UID and s.st_gid==SERVICE_GID and stat.S_IMODE(s.st_mode)==0o755)
            else:valid=s.st_uid==ROOT_UID and not s.st_mode&0o022
            if not stat.S_ISDIR(s.st_mode) or not valid:raise Refused('Directory descriptor provenance differs.')
        return fd
    except BaseException:os.close(fd);raise


def inode(s):return s.st_dev,s.st_ino


def rename_new(source_fd,source,target_fd,target):
    """Atomic no-replace only. No check-then-rename fallback on app-owned parents."""
    libc=ctypes.CDLL(None,use_errno=True)
    if sys.platform.startswith('linux'):name,flag='renameat2',1
    elif sys.platform=='darwin':name,flag='renameatx_np',4
    else:raise Refused('Atomic no-replace publication is unavailable.')
    try:function=getattr(libc,name)
    except AttributeError as error:raise Refused('Atomic no-replace publication is unavailable.') from error
    function.argtypes=[ctypes.c_int,ctypes.c_char_p,ctypes.c_int,ctypes.c_char_p,ctypes.c_uint]
    function.restype=ctypes.c_int
    if function(source_fd,os.fsencode(source),target_fd,os.fsencode(target),flag):
        raise Refused('Atomic publication refused; no existing destination was replaced.')


class RuntimeTree:
    """Root-owned interior held by descriptors even if an app-owned ancestor is renamed."""
    def __init__(self,create=False,value=None):
        self.parent_fd=self.state_fd=self.fd=None;self.published=False
        try:
            self.parent_fd=open_directory(TARGET.parent);self.state_fd=open_directory(STATE)
            self.parent_identity=inode(os.fstat(self.parent_fd))
            if os.fstat(self.parent_fd).st_dev!=os.fstat(self.state_fd).st_dev:
                raise Refused('Private staging and module parent must share a filesystem; no mutation.')
            if create:
                os.mkdir('module-staged',mode=0o700,dir_fd=self.state_fd)
                self.fd=os.open('module-staged',os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=self.state_fd)
            else:
                if not value:raise Refused('Recorded runtime inode is required.')
                expected=(value.get('target_device'),value.get('target_inode'))
                for parent,name,published in ((self.state_fd,'module-staged',False),(self.parent_fd,TARGET.name,True)):
                    try:s=os.stat(name,dir_fd=parent,follow_symlinks=False)
                    except FileNotFoundError:continue
                    if stat.S_ISDIR(s.st_mode) and inode(s)==expected:
                        candidate=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=parent)
                        if inode(os.fstat(candidate))!=expected:os.close(candidate);raise Refused('Runtime entry changed while opening.')
                        self.fd=candidate;self.published=published;break
                if self.fd is None:raise Refused('Recorded runtime inode is not at its recorded location; no foreign mutation.')
                if self.parent_identity!=(value.get('parent_device'),value.get('parent_inode')):
                    raise Refused('Runtime parent differs from the recorded anchor.')
            s=os.fstat(self.fd)
            if s.st_uid!=ROOT_UID or not stat.S_ISDIR(s.st_mode):raise Refused('Runtime interior must remain root-owned.')
            self.identity=inode(s)
        except BaseException:self.close();raise

    def close(self):
        for key in ('fd','parent_fd','state_fd'):
            fd=getattr(self,key,None)
            if fd is not None:os.close(fd);setattr(self,key,None)

    def assert_live(self):
        fresh=open_directory(TARGET.parent)
        try:
            if inode(os.fstat(fresh))!=self.parent_identity:raise Refused('Runtime parent was replaced.')
            directory,name=(fresh,TARGET.name) if self.published else (self.state_fd,'module-staged')
            s=os.stat(name,dir_fd=directory,follow_symlinks=False)
            if not stat.S_ISDIR(s.st_mode) or inode(s)!=self.identity:raise Refused('Runtime entry was replaced; no foreign mutation.')
        finally:os.close(fresh)

    def publish(self):
        self.assert_live()
        rename_new(self.state_fd,'module-staged',self.parent_fd,TARGET.name)
        self.published=True;os.fsync(self.state_fd);os.fsync(self.parent_fd)
        self.assert_live();os.fchmod(self.fd,0o755);os.fsync(self.fd)

    def directory(self,parts,create=False):
        fd=os.dup(self.fd)
        try:
            for part in parts:
                if part in ('','..','.') or '/' in part:raise Refused('Invalid runtime component.')
                if create:
                    try:os.mkdir(part,mode=0o700,dir_fd=fd);created=True
                    except FileExistsError:created=False
                else:created=False
                nxt=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
                os.close(fd);fd=nxt;s=os.fstat(fd)
                if s.st_uid!=ROOT_UID or s.st_mode&0o022:raise Refused('Unsafe runtime interior directory.')
                if created:os.fchmod(fd,0o755)
            return fd
        except BaseException:os.close(fd);raise

    def install(self,relative,data):
        if relative not in RUNTIME:raise Refused('Runtime file is outside the fixed allowlist.')
        parts=Path(relative).parts;fd=self.directory(parts[:-1],True)
        try:install_file(parts[-1],data,dir_fd=fd)
        finally:os.close(fd)

    def verify(self,files,index):
        self.assert_live();actual=set()
        expected=set(RUNTIME)-({'index.php'} if not index else set())
        directories={str(Path(p).parent) for p in RUNTIME}
        directories|={'schema','schema/receipts'}
        def visit(fd,prefix=''):
            for name in os.listdir(fd):
                relative=prefix+name;s=os.stat(name,dir_fd=fd,follow_symlinks=False)
                if s.st_uid!=ROOT_UID or s.st_mode&0o022:raise Refused('Runtime ownership/mode drift.')
                if stat.S_ISDIR(s.st_mode):
                    if relative not in directories:raise Refused('Unexpected runtime directory.')
                    child=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
                    try:visit(child,relative+'/')
                    finally:os.close(child)
                else:
                    if relative not in expected or not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o644:
                        raise Refused('Unexpected runtime file/type/mode.')
                    child=os.open(name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=fd)
                    try:
                        if inode(os.fstat(child))!=inode(s):raise Refused('Runtime file changed while opening.')
                        with os.fdopen(child,'rb',closefd=False) as handle:data=handle.read(2*1024*1024+1)
                        if data!=files[MODULE+relative]:raise Refused('Runtime file hash differs.')
                    finally:os.close(child)
                    actual.add(relative)
        visit(self.fd)
        if actual!=expected:raise Refused('Runtime dependency closure differs.')

    def disable(self):
        # Never resolve a mutable parent pathname for privileged unlink/chmod.
        try:os.unlink('index.php',dir_fd=self.fd)
        except FileNotFoundError:pass
        os.fsync(self.fd);os.fchmod(self.fd,0);os.fsync(self.fd)
        if stat.S_IMODE(os.fstat(self.fd).st_mode)!=0:raise Refused('Runtime disable was not confirmed.')


class Deployment:
    def __init__(self, files=None):
        self.files = files
        self.app_id = None
        self.tree = None

    def read_state(self, required=True):
        path = STATE/'state.json'
        if not path.exists() and not path.is_symlink():
            if required: raise Refused('No recorded M119 deployment exists.')
            return None
        value = decode_json(secure_read(path, ROOT_UID, 0o600, 16384, root=SAFE_ROOT))
        if (type(value) is not dict or value.get('schema') != 1 or value.get('mission') != 'M119'
                or value.get('commit') != COMMIT or value.get('artifact') != ARTIFACT_SHA
                or value.get('phase') not in PHASES or re.fullmatch('[a-f0-9]{32}', value.get('id','')) is None):
            raise Refused('Recorded deployment identity differs; manual reconciliation required.')
        return value

    def record(self, value, phase):
        value = dict(value, phase=phase, updated_at=int(time.time()))
        event = json.dumps({'mission':'M119','id':value['id'],'phase':phase,'time':value['updated_at']},sort_keys=True).encode()+b'\n'
        fd = os.open(STATE/'audit.jsonl',os.O_WRONLY|os.O_CREAT|os.O_APPEND|os.O_NOFOLLOW,0o600)
        try:
            s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode) or s.st_uid!=ROOT_UID or stat.S_IMODE(s.st_mode)!=0o600 or s.st_nlink!=1:
                raise Refused('Unsafe private deployment audit.')
            with os.fdopen(fd,'ab',closefd=False) as handle:
                handle.write(event);handle.flush();os.fsync(handle.fileno())
        finally:os.close(fd)
        temporary=STATE/('.state-'+uuid.uuid4().hex)
        fd=os.open(temporary,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        try:
            with os.fdopen(fd,'wb') as handle:
                handle.write((json.dumps(value,sort_keys=True)+'\n').encode());handle.flush();os.fsync(handle.fileno())
            os.replace(temporary,STATE/'state.json');sync_directory(STATE)
        finally:temporary.unlink(missing_ok=True)
        return value

    def context(self):
        require_runtime_pin()
        app, database, mail = (inspect(n) for n in (APP,DB,MAIL))
        for item, service in ((app,'dolibarr'),(database,'mariadb'),(mail,'mailpit')):
            labels=item['Config'].get('Labels') or {}
            if (not item['State']['Running'] or item['State'].get('Paused')
                    or labels.get('com.docker.compose.project')!='erp-staging' or labels.get('com.docker.compose.service')!=service):
                raise Refused('Fixed running staging resource identity differs.')
        if app['Image']!=FINAL_IMAGE or database['Id']!=DB_ID or mail['Id']!=MAIL_ID:
            raise Refused('Pinned staging image/container differs.')
        if self.app_id is not None and self.app_id!=app['Id']:
            raise Refused('Application container changed during this operation.')
        self.app_id=app['Id']
        def published(item):return {k:v for k,v in item['NetworkSettings']['Ports'].items() if v}
        if (published(app)!={'80/tcp':[{'HostIp':'127.0.0.1','HostPort':'18088'}]} or published(database)
                or published(mail)!={'1025/tcp':[{'HostIp':'127.0.0.1','HostPort':'11025'}],'8025/tcp':[{'HostIp':'127.0.0.1','HostPort':'18025'}]}
                or set(app['NetworkSettings']['Networks'])!={'erp-staging_erp-staging_internal','proxy_net'}):
            raise Refused('Fixed staging network exposure differs.')
        expected={'/var/www/html/custom':('erp-staging_erp-staging_custom',CUSTOM),
                  '/var/www/documents':('erp-staging_erp-staging_docs',Path('/var/lib/docker/volumes/erp-staging_erp-staging_docs/_data')),
                  '/var/lib/chinaplanetos-office-expenses':('erp-staging_m119_office_expenses_private',PRIVATE)}
        mounts={m['Destination']:m for m in app['Mounts']}
        if len(mounts)!=len(app['Mounts']) or set(mounts)!=set(expected):
            raise Refused('Staging mount set differs.')
        for destination,(name,source) in expected.items():
            m=mounts[destination]
            if m.get('Type')!='volume' or m.get('Name')!=name or m.get('Source')!=str(source) or m.get('RW') is not True:
                raise Refused('Fixed staging volume differs.')
        self.check_private()
        fd=open_directory(TARGET.parent);os.close(fd)
        return database

    def check_private(self):
        safe_parents(PRIVATE,{ROOT_UID},SAFE_ROOT)
        s=PRIVATE.stat()
        if stat.S_IMODE(s.st_mode)!=0o750 or s.st_gid!=SERVICE_GID:
            raise Refused('Private volume parent ownership/mode differs.')
        pilot=decode_json(secure_read(PRIVATE/'pilot.json',ROOT_UID,0o640,8192,root=SAFE_ROOT))
        expected={'version':1,'enabled':True,'environment':'backup-erp','entity':1,'user_id':1,'base_currency':'USD',
                  'offices':{'egypt':['EGP'],'china':['CNY']},'precision':{'EGP':2,'CNY':2,'USD':2},
                  'private_root':'/var/lib/chinaplanetos-office-expenses/receipts'}
        if (pilot!=expected or any(type(pilot.get(k)) is not int for k in ('version','entity','user_id'))
                or pilot.get('enabled') is not True or list(pilot['offices'])!=['egypt','china']
                or list(pilot['precision'])!=['EGP','CNY','USD'] or any(type(v) is not int for v in pilot['precision'].values())
                or (PRIVATE/'pilot.json').stat().st_gid!=SERVICE_GID):
            raise Refused('Private approved pilot binding differs.')
        root=PRIVATE/'receipts';s=root.lstat()
        if not stat.S_ISDIR(s.st_mode) or root.resolve()!=root or s.st_uid!=SERVICE_UID or s.st_gid!=SERVICE_GID or stat.S_IMODE(s.st_mode)!=0o700:
            raise Refused('Private receipt root differs.')
        marker=secure_read(root/'.cpo-office-expenses-private',SERVICE_UID,0o600,128,root=SAFE_ROOT)
        if marker!=b'cpo-office-expenses-private-v1':raise Refused('Private receipt marker differs.')

    def native(self, mode):
        args=['exec','-i','--user','33:33',APP,'php','/dev/stdin',mode,BASE_SCHEMA,RECEIPT_SCHEMA]
        value=decode_json(docker(args,self.files['support/native_operations.php'],timeout=180))
        if (type(value) is not dict or value.get('status')!=('native_context_verified' if mode=='inspect' else 'native_storage_verified')
                or value.get('entity')!=1 or value.get('base_currency')!='USD' or value.get('administrator_id')!=1
                or value.get('database_name')!='dolibarr_db' or not value.get('bcmath') or not value.get('jpeg')):
            raise Refused('Pinned native staging operation did not verify.')
        if mode!='inspect' and (value.get('financial_posting_disabled') is not True or value.get('unapproved_identity_denied') is not True
                                or value.get('base_schema')!='installed' or value.get('receipt_schema')!='installed'):
            raise Refused('Native pilot/storage/financial boundary did not verify.')
        return value

    def backup_library(self):
        raw=secure_read(BACKUP_LIBRARY,ROOT_UID,0o600,65536,root=SAFE_ROOT)
        if digest(raw)!=BACKUP_LIBRARY_SHA:raise Refused('Reviewed root backup library checksum differs.')
        return raw

    def backup(self, database):
        raw=self.backup_library()
        namespace={'__name__':'m119_library','__file__':str(BACKUP_LIBRARY)}
        exec(compile(raw,str(BACKUP_LIBRARY),'exec'),namespace)
        path=STATE/'database-before.sql.gz'
        count,checksum=namespace['database_backup'](path,database['Config']['Hostname'])
        s=path.lstat()
        if (not stat.S_ISREG(s.st_mode) or s.st_uid!=ROOT_UID or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600
                or not 1024<=count<=2*1024**3 or re.fullmatch('[a-f0-9]{64}',checksum) is None
                or namespace['hash_file'](path)!=checksum):
            raise Refused('Scoped pre-application database backup is unconfirmed.')
        return checksum

    def lint(self):
        for name in (*[MODULE+p for p in RUNTIME if p.endswith('.php')],'support/native_operations.php'):
            docker(['exec','-i','--user','33:33',APP,'php','-l','/dev/stdin'],self.files[name])

    def check_tree(self, index=True):
        self.assert_recorded_target()
        self.tree.verify(self.files,index)

    def health(self):
        connection=http.client.HTTPConnection('127.0.0.1',18088,timeout=15)
        try:
            connection.request('GET','/custom/chinaplanetoperations/office_expenses/index.php',headers={'Host':'backup-erp.visiontrading.biz'})
            response=connection.getresponse();body=response.read(512*1024+1)
            if (response.status not in (200,401) or len(body)>512*1024 or b'actionlogin' not in body
                    or b'name="username"' not in body or b'name="password"' not in body):
                raise Refused('Local anonymous native login wall did not verify; authenticated acceptance remains separate.')
        finally:connection.close()

    def preflight(self):
        if self.read_state(False) is not None or TARGET.exists() or TARGET.is_symlink():
            raise Refused('Existing deployment/target cannot be adopted or replayed.')
        self.backup_library();self.context()
        if STATE.stat().st_dev!=TARGET.parent.stat().st_dev or shutil.disk_usage(STATE).free<3*1024**3:
            raise Refused('Same-filesystem publication and reserved private backup capacity are required.')
        presence=self.native('inspect').get('owned_table_presence')
        if type(presence) is not dict or set(presence)!=set(TABLES) or any(type(v) is not int or v!=0 for v in presence.values()):
            raise Refused('Existing/partial owned schema requires manual reconciliation; no DDL retry.')

    def activate(self):
        self.assert_recorded_target()
        self.tree.install('index.php',self.files[MODULE+'index.php'])

    def assert_recorded_target(self):
        value=self.read_state()
        if self.tree is None:self.tree=RuntimeTree(value=value)
        s=os.fstat(self.tree.fd)
        if (not stat.S_ISDIR(s.st_mode) or s.st_uid!=ROOT_UID
                or type(value.get('target_device')) is not int or type(value.get('target_inode')) is not int
                or s.st_dev!=value['target_device'] or s.st_ino!=value['target_inode']):
            raise Refused('Current target is not the recorded created module; no adoption or removal.')
        self.tree.assert_live()

    def disable(self,value):
        if self.tree is None:
            if 'target_inode' not in value:return
            self.tree=RuntimeTree(value=value)
        # A held FD remains safe even if the app renamed its ancestor/entry.
        self.tree.disable()

    def close_tree(self):
        if self.tree is not None:self.tree.close();self.tree=None

    def apply(self):
        self.preflight()
        value={'schema':1,'mission':'M119','commit':COMMIT,'artifact':ARTIFACT_SHA,'id':uuid.uuid4().hex}
        value=self.record(value,'backing_up')
        try:
            value['database_backup_sha256']=self.backup(self.context())
            value=self.record(value,'installing')
            self.tree=RuntimeTree(create=True)
            created=os.fstat(self.tree.fd);value['target_device']=created.st_dev;value['target_inode']=created.st_ino
            value['parent_device'],value['parent_inode']=self.tree.parent_identity
            value=self.record(value,'installing')
            for relative in RUNTIME:
                if relative=='index.php':continue
                self.tree.install(relative,self.files[MODULE+relative])
            self.lint();self.check_tree(False)
            value=self.record(value,'files_staged');self.tree.publish()
            self.context();value=self.record(value,'migrating')
            self.native('migrate');self.native('verify');self.check_tree(False);self.context()
            value=self.record(value,'activating');self.activate()
            self.check_tree();self.native('verify');self.context();self.health()
            self.record(value,'active')
        except BaseException:
            phase='failed_disable_unconfirmed'
            try:
                self.disable(value);value['disabled']=True;phase='failed_disabled_retained'
            finally:self.record(value,phase)
            raise
        finally:self.close_tree()

    def verify(self):
        if self.read_state()['phase']!='active':raise Refused('Only a recorded active deployment may be verified.')
        try:self.context();self.check_tree();self.native('verify');self.context();self.health()
        finally:self.close_tree()

    def rollback(self):
        value=self.read_state()
        if value['phase']=='rolled_back_disabled_retained':raise Refused('Recorded rollback is already complete; no replay.')
        value=self.record(value,'rolling_back')
        try:
            if value.get('disabled') is not True:self.disable(value)
            value['disabled']=True;self.record(value,'rolled_back_disabled_retained')
        finally:self.close_tree()


def cli(arguments):
    if len(arguments)!=1 or arguments[0] not in ACTIONS:
        raise Refused('Exactly one of the five fixed deployment actions is required.')
    action=arguments[0]
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise Refused('Fixed administrative host identity is required.')
    os.umask(0o077)
    request=decode_json(secure_read(INBOX/'request.json',1002,0o600,8192))
    validate_request(request,action)
    ensure_state_directory()
    fd=os.open(STATE/'lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode) or s.st_uid!=ROOT_UID or stat.S_IMODE(s.st_mode)!=0o600 or s.st_nlink!=1:
            raise Refused('Unsafe deployment lock.')
        try:fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError as error:raise Refused('Another fixed deployment operation holds the lock.') from error
        files=None
        if action in ('preflight','apply','verify'):
            require_runtime_pin()
            manifest=secure_read(INBOX/request['manifest_name'],1002,0o600,65536)
            artifact=secure_read(INBOX/request['artifact_name'],1002,0o600,4*1024*1024)
            files=validate_package(manifest,artifact)
        deployment=Deployment(files)
        if action=='status':
            value=deployment.read_state(False)
            return {'mission':'M119','phase':value['phase'] if value else 'not_applied','entrypoint_present':(TARGET/'index.php').exists()}
        getattr(deployment,action)()
        return {'mission':'M119','action':action,'result':'verified','financial_posting':False,
                'authenticated_browser_acceptance':'separate_required_gate'}
    finally:os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'mission':'M119','result':'STOP','reason':str(error) if isinstance(error,Refused) else 'Private operation failed; inspect recorded state before further action.'}))
        raise SystemExit(1)
