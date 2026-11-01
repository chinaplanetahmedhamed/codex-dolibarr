#!/usr/bin/python3 -I
"""Pinned standalone backup-erp update, retaining the existing five-action privilege surface."""
import fcntl
import gzip
import hashlib
import http.client
import io
import json
import os
from pathlib import Path
import shutil
import stat
import sys
import tarfile
import types
import uuid

SOURCE='1b88b8869e700ad54a441124e980105ca6313cfa'
ARTIFACT='3f3254ae3f1fb4bd186b8d37afb7f3b7e31826291a18dad24599dcabf417fd1e'
MANIFEST='b8a62ae39a372018f5fed3bccdab701de65be6e80ebf4420797368d032d6f7c1'
FROZEN_SHA='845e497f2828df927ce14e5431d180a747c62bae264fbcee244183696cb967d9'
WORKER_SHA='eefc1b7525031f6401f0e8568f85aab09722079562f5b913b607d1ee68ef2898'
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-standalone-r01')
INBOX=Path('/home/codex-deploy/staging-inbox')
MODULE='custom/chinaplanetofficeexpenses/'
RUNTIME=('index.php', 'users.php', 'employee.css', 'employee.js', 'staff.css', 'core/modules/modChinaPlanetOfficeExpenses.class.php', 'langs/ar_SA/chinaplanetofficeexpenses.lang', 'langs/en_US/chinaplanetofficeexpenses.lang', 'langs/zh_CN/chinaplanetofficeexpenses.lang', 'assets/Cairo-OFL.txt', 'assets/Cairo.ttf', 'assets/Font-Awesome-LICENSE.txt', 'assets/fa-solid-900.woff2', 'assets/fontawesome-solid.css', 'assets/fontawesome.css', 'class/OfficeExpenseDraftService.php', 'class/OfficeExpenseEmployeeController.php', 'class/OfficeExpenseEmployeePage.php', 'class/OfficeExpenseMoney.php', 'class/OfficeExpenseModuleAccess.php', 'class/OfficeExpenseNativeMenuSyncService.php', 'class/OfficeExpenseNativeConnection.php', 'class/OfficeExpenseNativeDraftStore.php', 'class/OfficeExpenseNativeReceiptStore.php', 'class/OfficeExpenseNativeSchemaMigration.php', 'class/OfficeExpenseNativeStorageFactory.php', 'class/OfficeExpensePilotConfiguration.php', 'class/OfficeExpenseReceiptHttp.php', 'class/OfficeExpenseReceiptPanel.php', 'class/OfficeExpenseReceiptService.php', 'class/OfficeExpenseStaffAccess.php', 'class/OfficeExpenseNativeAccessStore.php', 'class/OfficeExpenseStaffRuntime.php', 'class/OfficeExpenseStaffController.php', 'class/OfficeExpenseStaffPage.php', 'schema/001_drafts.sql', 'schema/002_draft_audit.sql', 'schema/003_schema_state.sql', 'schema/manifest.json', 'schema/receipts/001_receipts.sql', 'schema/receipts/002_receipt_audit.sql', 'schema/receipts/manifest.json', 'schema/access/001_membership.sql', 'schema/access/002_access_audit.sql', 'schema/access/manifest.json')
ACTIONS=('preflight','apply','verify','rollback','status')
SHIM=b"<?php\n// CPO_OFFICEEXPENSES_COMPATIBILITY_SHIM\ndeclare(strict_types=1);\nrequire dirname(__DIR__,2).'/chinaplanetofficeexpenses/index.php';\n"


def sha(data):return hashlib.sha256(data).hexdigest()


def frozen():
    # Bootstrap has no caller-controlled imports or caller-writable parent component.
    path=STATE/'legacy_helper.py'
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode) or s.st_uid!=0 or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe frozen library parent.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode) or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600 or s.st_size>65536:raise RuntimeError('Unsafe frozen library.')
        raw=os.read(fd,65537)
        if sha(raw)!=FROZEN_SHA:raise RuntimeError('Frozen deployment library checksum differs.')
    finally:os.close(fd)
    return raw


def library(raw):
    if sha(raw)!=FROZEN_SHA:raise RuntimeError('Frozen library pin differs.')
    module=types.ModuleType('m119_fixed_library');module.__file__=str(STATE/'legacy_helper.py')
    exec(compile(raw,module.__file__,'exec'),module.__dict__)
    return module


def configured(raw):
    c=library(raw)
    c.COMMIT=SOURCE;c.ARTIFACT_SHA=ARTIFACT;c.MANIFEST_SHA=MANIFEST;c.STATE=STATE
    c.TARGET=c.CUSTOM/'chinaplanetofficeexpenses';c.MODULE=MODULE;c.RUNTIME=RUNTIME
    c.ALLOWLIST=frozenset(MODULE+p for p in RUNTIME)|{'support/release.json'}
    # Preserve the previously installed, checksum-pinned backup library, not a caller path.
    c.BACKUP_LIBRARY=c.STATE.parent/'m119/prepare_runtime.py'
    return c


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
    release={'schema':1,'mission':'M119','source_commit':SOURCE,'financial_posting':False,'package_kind':'standalone-code-only','staff_activation':False,'installer_included':False}
    if c.decode_json(files['support/release.json'])!=release:raise RuntimeError('Code release declaration differs.')
    return files


def verify_tree(c,tree,files,index=True):
    tree.assert_live();expected=set(RUNTIME)-({'index.php'}if not index else set());actual=set();directories=set()
    for name in RUNTIME:
        directories.update(p.as_posix()for p in Path(name).parents if str(p)!='.')
    def visit(fd,prefix=''):
        for name in os.listdir(fd):
            relative=prefix+name;s=os.stat(name,dir_fd=fd,follow_symlinks=False)
            if s.st_uid!=0 or s.st_mode&0o022:raise RuntimeError('Runtime ownership drift.')
            if stat.S_ISDIR(s.st_mode):
                if relative not in directories:raise RuntimeError('Unexpected directory.')
                child=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
                try:visit(child,relative+'/')
                finally:os.close(child)
            else:
                if relative not in expected or not stat.S_ISREG(s.st_mode)or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o644:raise RuntimeError('Unexpected runtime inode.')
                child=os.open(name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=fd)
                try:
                    if c.inode(os.fstat(child))!=c.inode(s):raise RuntimeError('Runtime inode changed.')
                    with os.fdopen(child,'rb',closefd=False)as handle:data=handle.read(2*1024*1024+1)
                    if data!=files[MODULE+relative]:raise RuntimeError('Runtime checksum drift.')
                finally:os.close(child)
                actual.add(relative)
    visit(tree.fd)
    if actual!=expected:raise RuntimeError('Runtime closure differs.')


class Deployment:
    def __init__(self,c,legacy,files=None):self.c=c;self.legacy=legacy;self.base=c.Deployment(files);self.files=files
    def worker(self,mode,snapshot=None):
        c=self.c;raw=c.secure_read(STATE/'native_operations.php',0,0o600,65536)
        if sha(raw)!=WORKER_SHA:raise RuntimeError('Private native worker checksum differs.')
        args=['exec','-i','--user','33:33',c.APP,'php','/dev/stdin',mode]
        if mode=='disable':args.append(json.dumps(snapshot,sort_keys=True))
        value=c.decode_json(c.docker(args,raw,timeout=180))
        expected={'inspect':'inspect_verified','prepare':'prepared_verified','activate':'activated_verified','accept':'verified','verify':'verified','disable':'disabled_verified'}
        if type(value)is not dict or value.get('status')!=expected[mode] or value.get('financial_posting')is not False:raise RuntimeError('Native '+mode+' did not verify.')
        return value
    def legacy_baseline(self):
        old=self.legacy
        files=old.validate_package(old.secure_read(INBOX/('manifest-'+old.ARTIFACT_SHA+'.json'),1002,0o600,65536),old.secure_read(INBOX/('artifact-'+old.ARTIFACT_SHA+'.tar.gz'),1002,0o600,4*1024*1024))
        deployment=old.Deployment(files);state=deployment.read_state()
        if state['phase']!='active':raise RuntimeError('Historical pilot baseline is not active.')
        tree=old.RuntimeTree(value=state)
        try:tree.verify(files,True)
        finally:tree.close()
        return files[old.MODULE+'index.php']
    def health(self):
        for route in ('/custom/chinaplanetofficeexpenses/index.php','/custom/chinaplanetofficeexpenses/users.php','/custom/chinaplanetoperations/office_expenses/index.php'):
            connection=http.client.HTTPConnection('127.0.0.1',18088,timeout=15)
            try:
                connection.request('GET',route,headers={'Host':'backup-erp.visiontrading.biz'});response=connection.getresponse();body=response.read(512*1024+1)
                if response.status not in (200,401)or len(body)>512*1024 or b'actionlogin'not in body or b'name="password"'not in body:raise RuntimeError('Native login wall differs.')
            finally:connection.close()
    def preflight(self):
        c=self.c
        if self.base.read_state(False)is not None or c.TARGET.exists()or c.TARGET.is_symlink():raise RuntimeError('Standalone target/state already exists; no adoption or replay.')
        self.base.backup_library();self.base.context();self.legacy_baseline()
        if STATE.stat().st_dev!=c.TARGET.parent.stat().st_dev or shutil.disk_usage(STATE).free<3*1024**3:raise RuntimeError('Publication/backup capacity differs.')
        return self.worker('inspect')['snapshot']
    def legacy_replace(self,restore=False):
        c=self.c;old=self.legacy
        before=c.secure_read(STATE/'legacy-index-before.php',0,0o644,65536)
        original=old.RuntimeTree(value=old.Deployment().read_state())
        try:
            original.assert_live();current=os.stat('index.php',dir_fd=original.fd,follow_symlinks=False)
            if not stat.S_ISREG(current.st_mode)or current.st_uid!=0 or current.st_nlink!=1 or stat.S_IMODE(current.st_mode)!=0o644:raise RuntimeError('Legacy entry provenance drift.')
            fd=os.open('index.php',os.O_RDONLY|os.O_NOFOLLOW,dir_fd=original.fd)
            try:data=os.read(fd,65537)
            finally:os.close(fd)
            expected=SHIM if restore else before;desired=before if restore else SHIM
            if restore and data==before:return
            if data!=expected:raise RuntimeError('Legacy entry changed; no overwrite.')
            temporary='.standalone-index-'+uuid.uuid4().hex
            c.install_file(temporary,desired,dir_fd=original.fd)
            original.assert_live();os.replace(temporary,'index.php',src_dir_fd=original.fd,dst_dir_fd=original.fd);os.fsync(original.fd)
        finally:original.close()
    def disable(self,value):
        self.worker('disable',value['native_before'])
        if (STATE/'legacy-index-before.php').exists():self.legacy_replace(True)
        self.base.disable(value)
    def apply(self):
        c=self.c;snapshot=self.preflight();value={'schema':1,'mission':'M119','commit':SOURCE,'artifact':ARTIFACT,'id':uuid.uuid4().hex,'native_before':snapshot}
        value=self.base.record(value,'backing_up')
        try:
            value['database_backup_sha256']=self.base.backup(self.base.context());c.install_file(STATE/'legacy-index-before.php',self.legacy_baseline())
            value=self.base.record(value,'installing');tree=c.RuntimeTree(create=True);self.base.tree=tree;s=os.fstat(tree.fd)
            value.update(target_device=s.st_dev,target_inode=s.st_ino,parent_device=tree.parent_identity[0],parent_inode=tree.parent_identity[1]);value=self.base.record(value,'installing')
            for relative in RUNTIME:
                if relative!='index.php':tree.install(relative,self.files[MODULE+relative])
            for name in (MODULE+p for p in RUNTIME if p.endswith('.php')):c.docker(['exec','-i','--user','33:33',c.APP,'php','-l','/dev/stdin'],self.files[name])
            verify_tree(c,tree,self.files,False);value=self.base.record(value,'files_staged');tree.publish();self.base.context();value=self.base.record(value,'migrating');self.worker('prepare')
            value=self.base.record(value,'activating');self.worker('activate');tree.install('index.php',self.files[MODULE+'index.php']);self.legacy_replace();verify_tree(c,tree,self.files)
            accepted=self.worker('accept');self.worker('verify');self.health();self.base.context();value['native_branch_checks']=accepted['native_branch_checks'];self.base.record(value,'active')
        except BaseException:
            phase='failed_disable_unconfirmed'
            try:self.disable(value);value['disabled']=True;phase='failed_disabled_retained'
            finally:self.base.record(value,phase)
            raise
        finally:self.base.close_tree()
    def verify(self):
        if self.base.read_state()['phase']!='active':raise RuntimeError('Only active release can verify.')
        try:
            self.base.context();self.base.assert_recorded_target();verify_tree(self.c,self.base.tree,self.files);self.worker('verify');self.health()
            old=self.legacy;tree=old.RuntimeTree(value=old.Deployment().read_state())
            try:
                fd=os.open('index.php',os.O_RDONLY|os.O_NOFOLLOW,dir_fd=tree.fd)
                try:
                    if os.read(fd,65537)!=SHIM:raise RuntimeError('Legacy shim differs.')
                finally:os.close(fd)
            finally:tree.close()
        finally:self.base.close_tree()
    def rollback(self):
        value=self.base.read_state()
        if value['phase']=='rolled_back_disabled_retained':raise RuntimeError('Rollback already complete.')
        value=self.base.record(value,'rolling_back')
        try:self.base.context();self.disable(value);value['disabled']=True;self.base.record(value,'rolled_back_disabled_retained')
        finally:self.base.close_tree()


def cli(arguments):
    if len(arguments)!=1 or arguments[0]not in ACTIONS:raise RuntimeError('Exactly one fixed action is required.')
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077);raw=frozen();c=configured(raw);legacy=library(raw);action=arguments[0]
    request=c.decode_json(c.secure_read(INBOX/'request.json',1002,0o600,8192));c.validate_request(request,action)
    fd=os.open(STATE/'lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe deployment lock.')
        fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        files=None
        if action in ('preflight','apply','verify'):files=validate_package(c,c.secure_read(INBOX/('manifest-'+ARTIFACT+'.json'),1002,0o600,65536),c.secure_read(INBOX/('artifact-'+ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))
        deployment=Deployment(c,legacy,files)
        if action=='status':
            value=deployment.base.read_state(False);return {'mission':'M119','release':'standalone-r01','phase':value['phase']if value else'not_applied','financial_posting':False}
        getattr(deployment,action)();return {'mission':'M119','release':'standalone-r01','action':action,'result':'verified','financial_posting':False,'staff_login_acceptance':'separate_required_gate'}
    finally:os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'mission':'M119','release':'standalone-r01','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private release step failed; inspect recorded state.'}));raise SystemExit(1)
