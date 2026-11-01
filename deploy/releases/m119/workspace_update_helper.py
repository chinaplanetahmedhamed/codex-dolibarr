#!/usr/bin/python3 -I
"""Fixed M119 workspace release; existing five-action staging boundary only."""
import fcntl
import gzip
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import stat
import sys
import tarfile
import types

SOURCE='6fa6caf5dde3e3762354ae8faaf4947c49bdb52d'
ARTIFACT='a367cea37de019749ece5a1d8a2016accad6d24d45efc169e06cd98d737f4192'
MANIFEST='0e2a55e3a9edc624bf154a754c307a1c12ebfc6af8fd9b4c5cf0d3e18bf8f796'
PREVIOUS='b51706fff210aba0022502aff7b74b698ed6a6f11b5e0286bd9b5f1a3aaa1674'
STANDALONE='471df8d4985121ed6b512f2ed4361c459dd080c30e4b71ffaf40aaf3fb6ce7de'
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-workspace-r04')
MODULE='custom/chinaplanetofficeexpenses/'
ACTIONS=('preflight','apply','verify','rollback','status')
RUNTIME=('index.php', 'users.php', 'employee.css', 'employee.js', 'staff.css', 'core/modules/modChinaPlanetOfficeExpenses.class.php', 'langs/ar_SA/chinaplanetofficeexpenses.lang', 'langs/en_US/chinaplanetofficeexpenses.lang', 'langs/zh_CN/chinaplanetofficeexpenses.lang', 'assets/Cairo-OFL.txt', 'assets/Cairo.ttf', 'assets/Font-Awesome-LICENSE.txt', 'assets/fa-solid-900.woff2', 'assets/fontawesome-solid.css', 'assets/fontawesome.css', 'class/OfficeExpenseDraftService.php', 'class/OfficeExpenseEmployeeController.php', 'class/OfficeExpenseEmployeePage.php', 'class/OfficeExpenseMoney.php', 'class/OfficeExpenseModuleAccess.php', 'class/OfficeExpenseNativeMenuSyncService.php', 'class/OfficeExpenseNativeConnection.php', 'class/OfficeExpenseNativeDraftStore.php', 'class/OfficeExpenseNativeReceiptStore.php', 'class/OfficeExpenseNativeSchemaMigration.php', 'class/OfficeExpenseNativeStorageFactory.php', 'class/OfficeExpensePilotConfiguration.php', 'class/OfficeExpenseReceiptHttp.php', 'class/OfficeExpenseReceiptPanel.php', 'class/OfficeExpenseReceiptService.php', 'class/OfficeExpenseStaffAccess.php', 'class/OfficeExpenseNativeAccessStore.php', 'class/OfficeExpenseStaffRuntime.php', 'class/OfficeExpenseStaffController.php', 'class/OfficeExpenseStaffPage.php', 'schema/001_drafts.sql', 'schema/002_draft_audit.sql', 'schema/003_schema_state.sql', 'schema/manifest.json', 'schema/receipts/001_receipts.sql', 'schema/receipts/002_receipt_audit.sql', 'schema/receipts/manifest.json', 'schema/access/001_membership.sql', 'schema/access/002_access_audit.sql', 'schema/access/manifest.json', 'workspace.php', 'workspace.css', 'workspace.js', 'class/OfficeExpenseNativeTreasury.php', 'class/OfficeExpenseWorkspaceLogo.php', 'class/OfficeExpenseWorkspaceDocument.php', 'class/OfficeExpenseWorkspaceStore.php', 'class/OfficeExpenseWorkspaceService.php', 'class/OfficeExpenseWorkspaceController.php', 'class/OfficeExpenseWorkspacePage.php', 'class/OfficeExpenseWorkspaceRuntime.php', 'schema/workspace/001_documents.sql', 'schema/workspace/002_audit.sql', 'schema/workspace/manifest.json')
WORKER_SHA='2e53c9eddeb7d20895e2bf01d786b48dd4d5707da65bdc6efc6f8b10e6cac689'


def sha(data):return hashlib.sha256(data).hexdigest()

def validate_package(c,manifest_raw,artifact):
    if len(manifest_raw)>65536 or len(artifact)>4*1024*1024 or sha(manifest_raw)!=MANIFEST or sha(artifact)!=ARTIFACT:raise RuntimeError('Pinned package digest differs.')
    manifest=c.decode_json(manifest_raw)
    expected={'schema':1,'mission':'M119','commit':SOURCE,'artifact_name':'artifact-'+ARTIFACT+'.tar.gz','artifact_sha256':ARTIFACT}
    if type(manifest)is not dict or set(manifest)!=set(expected)|{'files'} or any(manifest[k]!=v for k,v in expected.items()) or type(manifest['schema'])is not int or type(manifest['files'])is not list:raise RuntimeError('Standalone manifest differs.')
    entries={}
    for item in manifest['files']:
        if type(item)is not dict or set(item)!={'path','kind','sha256','size','mode'} or item['path']not in c.ALLOWLIST or item['path']in entries or item['mode']!='0644' or type(item['size'])is not int or not 0<item['size']<=2*1024*1024 or item['kind']!=('runtime' if item['path'].startswith(MODULE)else'support'):raise RuntimeError('Standalone entry contract differs.')
        entries[item['path']]=item
    if set(entries)!=c.ALLOWLIST or sum(e['size'] for e in entries.values())>4*1024*1024:raise RuntimeError('Incomplete dependency closure.')
    with gzip.GzipFile(fileobj=io.BytesIO(artifact))as gz:unpacked=gz.read(5*1024*1024+1)
    if len(unpacked)>5*1024*1024:raise RuntimeError('Expanded package exceeds bound.')
    files={}
    with tarfile.open(fileobj=io.BytesIO(unpacked),mode='r:')as archive:
        for member in archive:
            if member.name not in entries or member.name in files or member.type!=tarfile.REGTYPE or member.linkname or member.pax_headers or member.issparse() or member.mode!=0o644 or member.uid!=0 or member.gid!=0 or member.size!=entries[member.name]['size']:raise RuntimeError('Archive provenance differs.')
            data=archive.extractfile(member).read(member.size+1)
            if len(data)!=member.size or sha(data)!=entries[member.name]['sha256']:raise RuntimeError('Member checksum differs.')
            files[member.name]=data
        if any(unpacked[archive.offset:]):raise RuntimeError('Archive trailer differs.')
    if set(files)!=c.ALLOWLIST:raise RuntimeError('Archive closure differs.')
    release={'schema':1,'mission':'M119','source_commit':SOURCE,'financial_posting':False,'package_kind':'standalone-code-only','staff_activation':False,'installer_included':False,'workspace_activation':False}
    if c.decode_json(files['support/release.json'])!=release:raise RuntimeError('Code release declaration differs.')
    return files


def pinned(raw,expected,name):
    if sha(raw)!=expected:raise RuntimeError('Reviewed '+name+' checksum differs.')
    m=types.ModuleType(name);m.__file__=str(STATE/(name+'.py'));exec(compile(raw,m.__file__,'exec'),m.__dict__);return m


def setup(raw):
    previous=pinned(raw,PREVIOUS,'m119_frozen_r03')
    old,baseline=previous.setup(previous.frozen())
    raw_standalone=old.secure_read(STATE.parent/'m119-ui-r02/standalone_helper.py',0,0o600,65536)
    engine=pinned(raw_standalone,STANDALONE,'m119_workspace_engine')
    # Independent globals: never change the prior release verifier/rollback contract.
    legacy_raw=engine.frozen()
    engine.SOURCE=SOURCE;engine.ARTIFACT=ARTIFACT;engine.MANIFEST=MANIFEST;engine.STATE=STATE;engine.RUNTIME=RUNTIME
    c=engine.configured(legacy_raw);c.PHASES=tuple(c.PHASES)+('workspace_disabled_retained','failed_reconciliation_required')
    files=validate_package(c,c.secure_read(c.INBOX/('manifest-'+ARTIFACT+'.json'),1002,0o600,65536),c.secure_read(c.INBOX/('artifact-'+ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))
    return c,Update(c,engine,baseline,files)


def private_write(path,data):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb')as handle:
        handle.write(data);handle.flush();os.fsync(handle.fileno())


def replace(c,tree,relative,desired,before):
    if relative not in RUNTIME:raise RuntimeError('Replacement outside workspace closure.')
    parts=Path(relative).parts;directory=tree.directory(parts[:-1],False);temporary='.m119-workspace-'+c.uuid.uuid4().hex
    try:
        fd=os.open(parts[-1],os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=directory)
        try:
            s=os.fstat(fd);raw=os.read(fd,2*1024*1024+1)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o644 or raw!=before:raise RuntimeError('Runtime precondition differs.')
        finally:os.close(fd)
        c.install_file(temporary,desired,dir_fd=directory);tree.assert_live();fresh=os.stat(parts[-1],dir_fd=directory,follow_symlinks=False)
        if (s.st_dev,s.st_ino,s.st_mtime_ns,s.st_ctime_ns)!=(fresh.st_dev,fresh.st_ino,fresh.st_mtime_ns,fresh.st_ctime_ns):raise RuntimeError('Runtime changed before replacement.')
        os.replace(temporary,parts[-1],src_dir_fd=directory,dst_dir_fd=directory);os.fsync(directory)
    finally:
        try:os.unlink(temporary,dir_fd=directory)
        except FileNotFoundError:pass
        os.close(directory)


class Update:
    def __init__(self,c,engine,baseline,files):
        self.c=c;self.engine=engine;self.baseline=baseline;self.files=files;self.record=c.Deployment(files)

    def worker(self,mode):
        c=self.c;raw=c.secure_read(STATE/'workspace_native_operations.php',0,0o600,65536)
        if sha(raw)!=WORKER_SHA:raise RuntimeError('Pinned native worker differs.')
        out=c.decode_json(c.docker(['exec','-i','--user','33:33',c.APP,'php','/dev/stdin',mode],raw,timeout=180))
        if type(out)is not dict or out.get('status')!=mode+'_verified'or out.get('financial_posting')is not False or type(out.get('financial_fingerprint'))is not str or len(out['financial_fingerprint'])!=64:raise RuntimeError('Native '+mode+' did not verify.')
        return out

    def preflight(self):
        if self.record.read_state(False)is not None:raise RuntimeError('Workspace update has state; no replay.')
        self.baseline.verify();self.record.backup_library();self.record.context()
        if shutil.disk_usage(STATE).free<3*1024**3:raise RuntimeError('Database backup capacity differs.')
        for relative in RUNTIME:
            if relative.endswith('.php'):self.c.docker(['exec','-i','--user','33:33',self.c.APP,'php','-l','/dev/stdin'],self.files[MODULE+relative])
        return self.worker('inspect')

    def assert_fingerprint(self,value,out):
        if out['financial_fingerprint']!=value['financial_fingerprint']:raise RuntimeError('Native financial baseline changed; reconcile before continuation.')

    def apply(self):
        inspected=self.preflight();c=self.c;prior=self.baseline.record.read_state()
        value=dict(prior,commit=SOURCE,artifact=ARTIFACT,id=c.uuid.uuid4().hex,baseline_id=prior['id'],baseline_commit=prior['commit'],financial_fingerprint=inspected['financial_fingerprint'])
        value=self.record.record(value,'backing_up')
        # Full consistent DB dump and exact existing source backups precede the first write.
        value['database_backup_sha256']=self.record.backup(self.record.context())
        for index,path in enumerate(sorted(self.baseline.after)):
            private_write(STATE/('source-before-'+str(index)),self.baseline.after[path])
        for index,path in enumerate(sorted(self.baseline.after)):
            if c.secure_read(STATE/('source-before-'+str(index)),0,0o600,2*1024*1024)!=self.baseline.after[path]:raise RuntimeError('Private source backup readback differs.')
        self.assert_fingerprint(value,self.worker('inspect'));value=self.record.record(value,'installing');tree=c.RuntimeTree(value=value)
        try:
            # Recheck the complete prior closure after the database backup and before any code write.
            self.baseline.new.verify_tree(self.baseline.c,tree,self.baseline.after)
            # Add dependencies before changing entry points.
            for relative in RUNTIME:
                if MODULE+relative not in self.baseline.after:tree.install(relative,self.files[MODULE+relative])
            for relative in RUNTIME:
                key=MODULE+relative
                if key in self.baseline.after and self.baseline.after[key]!=self.files[key]:replace(c,tree,relative,self.files[key],self.baseline.after[key])
            self.engine.verify_tree(c,tree,self.files);self.record.context();value=self.record.record(value,'migrating')
            self.assert_fingerprint(value,self.worker('prepare'));value=self.record.record(value,'activating')
            self.assert_fingerprint(value,self.worker('activate'));verified=self.worker('verify');self.assert_fingerprint(value,verified)
            self.baseline.baseline.health();self.record.context();value['native_branch_checks']=verified['native_branch_checks'];self.record.record(value,'active')
        except BaseException:
            # Retain compatible parser and all data after schema/grant changes. Never restore an old DB.
            try:
                self.worker('disable');self.record.record(value,'workspace_disabled_retained')
            except BaseException:self.record.record(value,'failed_reconciliation_required')
            raise
        finally:tree.close()

    def verify(self):
        value=self.record.read_state()
        if value['phase']!='active':raise RuntimeError('Workspace is not active.')
        self.record.context();tree=self.c.RuntimeTree(value=value)
        try:self.engine.verify_tree(self.c,tree,self.files);self.worker('verify');self.baseline.baseline.health()
        finally:tree.close()

    def rollback(self):
        value=self.record.read_state()
        self.record.context();tree=self.c.RuntimeTree(value=value)
        try:
            # Exact compatible closure is mandatory before retaining extended grants.
            self.engine.verify_tree(self.c,tree,self.files);self.worker('disable');self.record.record(value,'workspace_disabled_retained')
        finally:tree.close()


def cli(arguments):
    if len(arguments)!=1 or arguments[0]not in ACTIONS:raise RuntimeError('Exactly one fixed action required.')
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077)
    # Bootstrap the existing bounded reader from its fixed root-owned location.
    reader_path=STATE.parent/'m119-sidebar-r03/ui_r02_helper.py'
    for parent in reader_path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid!=0 or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe frozen parent.')
    fd=os.open(reader_path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd);raw=os.read(fd,65537)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe frozen reader.')
    finally:os.close(fd)
    reader=pinned(raw,'388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757','m119_reviewed_r02')
    _,original,_,_=reader.setup(reader.load()[0]);prior=original.secure_read(STATE/'previous_helper.py',0,0o600,65536)
    c,update=setup(prior);action=arguments[0]
    c.validate_request(c.decode_json(c.secure_read(c.INBOX/'request.json',1002,0o600,8192)),action)
    locks=[]
    try:
        for name in ('m119-standalone-r01','m119-ui-r02','m119-sidebar-r03','m119-workspace-r04'):
            fd=os.open(STATE.parent/name/'lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600);locks.append(fd);s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe release lock.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='status':
            value=update.record.read_state(False);return {'release':'m119-workspace-r04','phase':value['phase']if value else'not_applied'}
        getattr(update,action)();return {'release':'m119-workspace-r04','action':action,'result':'verified','financial_posting':False,'staff_password_login':'not_tested'}
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'release':'m119-workspace-r04','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private workspace step stopped; inspect retained state.'}));raise SystemExit(1)
