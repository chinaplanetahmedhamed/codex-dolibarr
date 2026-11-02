#!/usr/bin/python3 -I
"""Pinned R11 financial UI release; rollback pauses writes and retains compatible code and all money."""
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

SOURCE='a6d1893b46db0cd9349533fdd15461d93ad32f9a'
ARTIFACT='d648078b62e491082bdf7869f1a7a0a283306e44ac29d38fe27be5587983b1d9'
MANIFEST='c5b2f2b27ab6235febdb0b8d294f7b3b546e24e20212864865d0b02ed660d369'
PREVIOUS='e36452a4dc36d55657f742f86314c26acb8c47006d7d045b7b52148d520a2527'
STANDALONE='471df8d4985121ed6b512f2ed4361c459dd080c30e4b71ffaf40aaf3fb6ce7de'
REPLACER='388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757'
WORKER_SHA='0f19c6aecb92cf64e9b34bf62ae3c6071d26618288deb41331ce28c5c4b69b04'
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-finance-r11')
MODULE='custom/chinaplanetofficeexpenses/'
ACTIONS=('preflight','apply','verify','rollback','status')
GATE='finance-bridge.json'
PAUSED=b'{"version":1,"enabled":false}\n'
ACTIVE=b'{"version":1,"enabled":true}\n'
RUNTIME=('index.php', 'users.php', 'employee.css', 'employee.js', 'staff.css', 'core/modules/modChinaPlanetOfficeExpenses.class.php', 'langs/ar_SA/chinaplanetofficeexpenses.lang', 'langs/en_US/chinaplanetofficeexpenses.lang', 'langs/zh_CN/chinaplanetofficeexpenses.lang', 'assets/Cairo-OFL.txt', 'assets/Cairo.ttf', 'assets/Font-Awesome-LICENSE.txt', 'assets/fa-solid-900.woff2', 'assets/fontawesome-solid.css', 'assets/fontawesome.css', 'class/OfficeExpenseDraftService.php', 'class/OfficeExpenseEmployeeController.php', 'class/OfficeExpenseEmployeePage.php', 'class/OfficeExpenseMoney.php', 'class/OfficeExpenseModuleAccess.php', 'class/OfficeExpenseNativeMenuSyncService.php', 'class/OfficeExpenseNativeConnection.php', 'class/OfficeExpenseNativeDraftStore.php', 'class/OfficeExpenseNativeReceiptStore.php', 'class/OfficeExpenseNativeSchemaMigration.php', 'class/OfficeExpenseNativeStorageFactory.php', 'class/OfficeExpensePilotConfiguration.php', 'class/OfficeExpenseReceiptHttp.php', 'class/OfficeExpenseReceiptPanel.php', 'class/OfficeExpenseReceiptService.php', 'class/OfficeExpenseStaffAccess.php', 'class/OfficeExpenseNativeAccessStore.php', 'class/OfficeExpenseStaffRuntime.php', 'class/OfficeExpenseStaffController.php', 'class/OfficeExpenseStaffPage.php', 'schema/001_drafts.sql', 'schema/002_draft_audit.sql', 'schema/003_schema_state.sql', 'schema/manifest.json', 'schema/receipts/001_receipts.sql', 'schema/receipts/002_receipt_audit.sql', 'schema/receipts/manifest.json', 'schema/access/001_membership.sql', 'schema/access/002_access_audit.sql', 'schema/access/manifest.json', 'workspace.php', 'workspace.css', 'workspace.js', 'class/OfficeExpenseNativeTreasury.php', 'class/OfficeExpenseWorkspaceLogo.php', 'class/OfficeExpenseWorkspaceDocument.php', 'class/OfficeExpenseWorkspaceStore.php', 'class/OfficeExpenseWorkspaceService.php', 'class/OfficeExpenseWorkspaceController.php', 'class/OfficeExpenseWorkspacePage.php', 'class/OfficeExpenseWorkspaceRuntime.php', 'schema/workspace/001_documents.sql', 'schema/workspace/002_audit.sql', 'schema/workspace/manifest.json', 'finance.php', 'finance-bridge.json', 'class/OfficeExpenseFinanceGate.php', 'class/OfficeExpenseFinanceSettings.php', 'class/OfficeExpenseFinancialLock.php', 'class/OfficeExpenseFinancialPlan.php', 'class/OfficeExpenseNativeFinance.php', 'class/OfficeExpenseFinancialService.php', 'class/OfficeExpenseWorkspaceEvidence.php', 'class/OfficeExpenseFinancialPage.php')

def sha(raw):return hashlib.sha256(raw).hexdigest()

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
    release={'schema':1,'mission':'M119','source_commit':SOURCE,'financial_posting':False,'package_kind':'standalone-code-only','staff_activation':False,'installer_included':False,'workspace_activation':False,'financial_activation':False}
    if c.decode_json(files['support/release.json'])!=release:raise RuntimeError('Code release declaration differs.')
    return files


def pinned(raw,expected,name):
    if sha(raw)!=expected:raise RuntimeError('Reviewed '+name+' checksum differs.')
    module=types.ModuleType(name);module.__file__=str(STATE/(name+'.py'));exec(compile(raw,module.__file__,'exec'),module.__dict__);return module


def reader():
    # Bootstrap only the already frozen root-owned bounded reader.
    path=STATE.parent/'m119-sidebar-r03/ui_r02_helper.py'
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid!=0 or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe frozen reader parent.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd);raw=os.read(fd,65537)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600 or not 0<s.st_size<=65536:raise RuntimeError('Unsafe frozen reader.')
    finally:os.close(fd)
    module=pinned(raw,REPLACER,'m119_finance_reader');_,c,_,_=module.setup(module.load()[0]);return raw,c


def setup(raw):
    previous=pinned(raw,PREVIOUS,'m119_finance_previous');_,old=reader()
    c_prior,baseline=previous.setup(old.secure_read(previous.STATE/'previous_helper.py',0,0o600,65536))
    engine=pinned(old.secure_read(STATE.parent/'m119-ui-r02/standalone_helper.py',0,0o600,65536),STANDALONE,'m119_finance_engine')
    legacy=engine.frozen();engine.SOURCE=SOURCE;engine.ARTIFACT=ARTIFACT;engine.MANIFEST=MANIFEST;engine.STATE=STATE;engine.RUNTIME=RUNTIME
    c=engine.configured(legacy);c.PHASES=tuple(c.PHASES)+('finance_paused_retained','failed_reconciliation_required')
    files=validate_package(c,c.secure_read(c.INBOX/('manifest-'+ARTIFACT+'.json'),1002,0o600,65536),c.secure_read(c.INBOX/('artifact-'+ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))
    replacer=pinned(reader()[0],REPLACER,'m119_finance_replacer');replacer.CHANGED=RUNTIME
    return c,Update(c,engine,baseline,c_prior,files,replacer)


def private_write(path,data):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb')as handle:handle.write(data);handle.flush();os.fsync(handle.fileno())


class Update:
    def __init__(self,c,engine,baseline,prior,files,replacer):
        self.c=c;self.engine=engine;self.baseline=baseline;self.prior=prior;self.files=files;self.replacer=replacer;self.record=c.Deployment(files)
        if files[MODULE+GATE]!=ACTIVE or not set(baseline.after)<set(files):raise RuntimeError('Finance closure is not an additive compatible release.')
        if any(files[p]!=v for p,v in baseline.after.items()if p.startswith(MODULE+'schema/')):raise RuntimeError('Unexpected schema change.')

    def worker(self,mode):
        c=self.c;raw=c.secure_read(STATE/'finance_native_verify.php',0,0o600,65536)
        if sha(raw)!=WORKER_SHA:raise RuntimeError('Reviewed native financial verifier differs.')
        out=c.decode_json(c.docker(['exec','-i','--user','33:33',c.APP,'php','/dev/stdin',mode],raw,timeout=180))
        if type(out)is not dict or out.get('status')!=mode+'_verified'or out.get('native_mutations')is not False or type(out.get('financial_fingerprint'))is not str or len(out['financial_fingerprint'])!=64:raise RuntimeError('Read-only native financial verification failed.')
        return out

    def paused_files(self):return dict(self.files,**{MODULE+GATE:PAUSED})

    def preflight(self):
        if self.record.read_state(False)is not None:raise RuntimeError('Finance update has prior state; no replay.')
        self.baseline.verify();self.record.backup_library();self.record.context()
        if shutil.disk_usage(STATE).free<3*1024**3:raise RuntimeError('Database backup capacity differs.')
        for relative in RUNTIME:
            if relative.endswith('.php'):self.c.docker(['exec','-i','--user','33:33',self.c.APP,'php','-l','/dev/stdin'],self.files[MODULE+relative])
        return self.worker('inspect')

    def read_current(self,tree,relative):
        parts=Path(relative).parts;directory=tree.directory(parts[:-1],False)
        try:
            try:fd=os.open(parts[-1],os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=directory)
            except FileNotFoundError:return None
            try:
                s=os.fstat(fd);raw=os.read(fd,2*1024*1024+1)
                if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o644 or s.st_size!=len(raw):raise RuntimeError('Unexpected runtime inode.')
                allowed=[self.files[MODULE+relative]]
                if MODULE+relative in self.baseline.after:allowed.append(self.baseline.after[MODULE+relative])
                if relative==GATE:allowed.append(PAUSED)
                if raw not in allowed:raise RuntimeError('Unknown runtime drift; no overwrite.')
                return raw
            finally:os.close(fd)
        finally:os.close(directory)

    def write_gate(self,tree,enabled):
        before=self.read_current(tree,GATE);desired=ACTIVE if enabled else PAUSED
        if before is None:tree.install(GATE,desired)
        elif before!=desired:self.replacer.replace(self.c,tree,GATE,desired,(before,))

    def complete_paused(self,tree):
        # Validate every existing member before repair; unknown third-party bytes are never overwritten.
        current={p:self.read_current(tree,p)for p in RUNTIME}
        self.write_gate(tree,False)
        entrypoints={'index.php','users.php','workspace.php','finance.php'}
        order=[p for p in RUNTIME if p not in entrypoints and p!=GATE]+sorted(entrypoints)
        for relative in order:
            before=current[relative];desired=self.files[MODULE+relative]
            if before is None:tree.install(relative,desired)
            elif before!=desired:self.replacer.replace(self.c,tree,relative,desired,(before,))
        self.engine.verify_tree(self.c,tree,self.paused_files())

    def apply(self):
        inspected=self.preflight();prior=self.baseline.record.read_state();c=self.c
        value=dict(prior,commit=SOURCE,artifact=ARTIFACT,id=c.uuid.uuid4().hex,baseline_id=prior['id'],baseline_commit=prior['commit'],financial_fingerprint=inspected['financial_fingerprint'])
        value=self.record.record(value,'backing_up');value['database_backup_sha256']=self.record.backup(self.record.context())
        for index,path in enumerate(sorted(self.baseline.after)):private_write(STATE/('source-before-'+str(index)),self.baseline.after[path])
        for index,path in enumerate(sorted(self.baseline.after)):
            if c.secure_read(STATE/('source-before-'+str(index)),0,0o600,2*1024*1024)!=self.baseline.after[path]:raise RuntimeError('Source backup readback differs.')
        if self.worker('inspect')['financial_fingerprint']!=value['financial_fingerprint']:raise RuntimeError('Native records changed during backup; reconcile before apply.')
        value=self.record.record(value,'installing');tree=c.RuntimeTree(value=value)
        try:
            self.baseline.new.verify_tree(self.prior,tree,self.baseline.after);self.complete_paused(tree)
            verified=self.worker('paused')
            if verified['financial_fingerprint']!=value['financial_fingerprint']or not verified['settings_disabled']:raise RuntimeError('Native baseline/configuration differs; retain paused finance.')
            self.baseline.baseline.health();self.record.context();self.write_gate(tree,True);self.engine.verify_tree(c,tree,self.files)
            verified=self.worker('verify')
            if verified['financial_fingerprint']!=value['financial_fingerprint']or not verified['settings_disabled']:raise RuntimeError('Native records changed before release acceptance.')
            self.record.record(value,'active')
        except BaseException:
            try:self.write_gate(tree,False);self.complete_paused(tree);self.worker('paused');self.record.record(value,'finance_paused_retained')
            except BaseException:self.record.record(value,'failed_reconciliation_required')
            raise
        finally:tree.close()

    def verify(self):
        value=self.record.read_state()
        if value['phase']!='active':raise RuntimeError('Finance release is not active.')
        self.record.context();tree=self.c.RuntimeTree(value=value)
        try:self.engine.verify_tree(self.c,tree,self.files);self.worker('verify');self.baseline.baseline.health()
        finally:tree.close()

    def rollback(self):
        # Pause first, then drain native work; never restore old code, database or money.
        value=self.record.read_state();self.record.context();tree=self.c.RuntimeTree(value=value)
        try:self.write_gate(tree,False);self.complete_paused(tree);self.worker('paused');self.record.record(value,'finance_paused_retained')
        finally:tree.close()


def cli(arguments):
    if len(arguments)!=1 or arguments[0]not in ACTIONS:raise RuntimeError('Exactly one fixed action required.')
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077);_,reader_c=reader();c,update=setup(reader_c.secure_read(STATE/'previous_helper.py',0,0o600,65536));action=arguments[0]
    c.validate_request(c.decode_json(c.secure_read(c.INBOX/'request.json',1002,0o600,8192)),action);locks=[]
    try:
        for name in ('m119-standalone-r01','m119-ui-r02','m119-sidebar-r03','m119-workspace-r04','m119-receipt-r05','m119-receipt-r06','m119-design-r07','m119-design-r08','m119-dropdown-r09','m119-entry-r10','m119-finance-r11'):
            fd=os.open(STATE.parent/name/'lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600);locks.append(fd);s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe release lock.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='status':
            value=update.record.read_state(False);return {'release':'m119-finance-r11','phase':value['phase']if value else'not_applied','business_activation':'separate_reviewed_configuration'}
        getattr(update,action)();return {'release':'m119-finance-r11','action':action,'result':'verified','native_mutations':False,'schema_changed':False,'roles_changed':False,'business_activation':'separate_reviewed_configuration'}
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'release':'m119-finance-r11','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private financial update stopped; inspect retained state.'}));raise SystemExit(1)
