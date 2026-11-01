#!/usr/bin/python3 -I
"""M119 UI-only follow-up on the recorded standalone inode; same five fixed actions."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import sys
import types

SOURCE='e59efa9a33e5c21b5bbd82e37caf6c1670713480'
ARTIFACT='c1ae3bb30a849834165e4810beedadc69cb3112d486c897da874abf177a96ec3'
MANIFEST='6d6640ef6aa79ff9dfed05d13776f0666b6162d053034465c03990548f3036de'
PREVIOUS_HELPER='471df8d4985121ed6b512f2ed4361c459dd080c30e4b71ffaf40aaf3fb6ce7de'
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-ui-r02')
CHANGED=('users.php','class/OfficeExpenseStaffPage.php','class/OfficeExpenseEmployeePage.php','employee.css','staff.css')
ACTIONS=('preflight','apply','verify','rollback','status')


def digest(raw):return hashlib.sha256(raw).hexdigest()


def module(raw):
    if digest(raw)!=PREVIOUS_HELPER:raise RuntimeError('Frozen standalone helper differs.')
    m=types.ModuleType('m119_ui_frozen');m.__file__=str(STATE/'standalone_helper.py')
    exec(compile(raw,m.__file__,'exec'),m.__dict__)
    return m


def load():
    path=STATE/'standalone_helper.py'
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid!=0 or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe frozen parent.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600 or not 0<s.st_size<=65536:raise RuntimeError('Unsafe frozen file.')
        raw=os.read(fd,65537)
        return raw,module(raw)
    finally:os.close(fd)


def setup(raw):
    old=module(raw);legacy_raw=old.frozen();original=old.configured(legacy_raw)
    updated=module(raw);updated.SOURCE=SOURCE;updated.ARTIFACT=ARTIFACT;updated.MANIFEST=MANIFEST;updated.STATE=STATE
    c=updated.configured(legacy_raw)
    c.PHASES=tuple(c.PHASES)+('restored_baseline','failed_restore_unconfirmed')
    c.BACKUP_LIBRARY=original.BACKUP_LIBRARY
    return old,original,updated,c


def package(m,c):
    return m.validate_package(c,c.secure_read(c.INBOX/('manifest-'+m.ARTIFACT+'.json'),1002,0o600,65536),c.secure_read(c.INBOX/('artifact-'+m.ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))


def backup(path,data):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb')as handle:
        handle.write(data);handle.flush();os.fsync(handle.fileno());os.fchmod(handle.fileno(),0o600)


def replace(c,tree,relative,desired,allowed):
    if relative not in CHANGED:raise RuntimeError('Replacement outside fixed UI set.')
    parts=Path(relative).parts;directory=tree.directory(parts[:-1],False);temporary='.m119-ui-'+c.uuid.uuid4().hex
    try:
        fd=os.open(parts[-1],os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=directory)
        try:
            before=os.fstat(fd);raw=os.read(fd,2*1024*1024+1)
            if not stat.S_ISREG(before.st_mode)or before.st_uid!=0 or before.st_nlink!=1 or stat.S_IMODE(before.st_mode)!=0o644 or raw not in allowed:raise RuntimeError('UI file precondition differs.')
        finally:os.close(fd)
        c.install_file(temporary,desired,dir_fd=directory);tree.assert_live()
        named=os.stat(parts[-1],dir_fd=directory,follow_symlinks=False)
        if (before.st_dev,before.st_ino,before.st_mtime_ns,before.st_ctime_ns)!=(named.st_dev,named.st_ino,named.st_mtime_ns,named.st_ctime_ns):raise RuntimeError('UI destination changed before replacement.')
        os.replace(temporary,parts[-1],src_dir_fd=directory,dst_dir_fd=directory);os.fsync(directory)
    finally:
        try:os.unlink(temporary,dir_fd=directory)
        except FileNotFoundError:pass
        os.close(directory)


class Update:
    def __init__(self,old,original,new,c,before,after):
        self.old=old;self.original=original;self.new=new;self.c=c;self.before=before;self.after=after
        self.baseline=old.Deployment(original,old.library(old.frozen()),before)
        self.record=c.Deployment(after)

    def closure(self):
        changed=tuple(p for p in self.old.RUNTIME if self.before[self.old.MODULE+p]!=self.after[self.old.MODULE+p])
        if set(changed)!=set(CHANGED):raise RuntimeError('Only the five reviewed UI files may change.')

    def preflight(self):
        if self.record.read_state(False)is not None:raise RuntimeError('UI update already has state; no replay.')
        self.closure();self.baseline.verify()
        for relative in CHANGED:
            if relative.endswith('.php'):self.c.docker(['exec','-i','--user','33:33',self.c.APP,'php','-l','/dev/stdin'],self.after[self.old.MODULE+relative])
        return self.baseline.base.read_state()

    def tree(self,value):return self.c.RuntimeTree(value=value)

    def assert_backup(self):
        for index,relative in enumerate(CHANGED):
            if self.c.secure_read(STATE/('before-'+str(index)),0,0o600,2*1024*1024)!=self.before[self.old.MODULE+relative]:raise RuntimeError('Recorded UI backup differs.')

    def restore(self,value):
        self.assert_backup();tree=self.tree(value)
        try:
            # Read every current file before replacing any: refuse third-party drift.
            for relative in CHANGED:
                fd=os.open(relative,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=tree.fd)
                try:
                    s=os.fstat(fd);data=os.read(fd,2*1024*1024+1)
                    if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o644 or data not in (self.before[self.old.MODULE+relative],self.after[self.old.MODULE+relative]):raise RuntimeError('Unknown UI drift; no overwrite.')
                finally:os.close(fd)
            for relative in CHANGED:replace(self.c,tree,relative,self.before[self.old.MODULE+relative],(self.before[self.old.MODULE+relative],self.after[self.old.MODULE+relative]))
            self.old.verify_tree(self.original,tree,self.before)
        finally:tree.close()
        self.baseline.verify()
        self.record.record(value,'restored_baseline')

    def apply(self):
        prior=self.preflight();c=self.c
        value=dict(prior,commit=SOURCE,artifact=ARTIFACT,baseline_commit=prior['commit'],baseline_id=prior['id'],id=c.uuid.uuid4().hex)
        value=self.record.record(value,'backing_up')
        # Root-private exact backups; no schema, account, grant or configuration write.
        for index,relative in enumerate(CHANGED):backup(STATE/('before-'+str(index)),self.before[self.old.MODULE+relative])
        self.assert_backup();value=self.record.record(value,'installing');tree=self.tree(value)
        try:
            self.old.verify_tree(self.original,tree,self.before)
            for relative in CHANGED:replace(c,tree,relative,self.after[self.old.MODULE+relative],(self.before[self.old.MODULE+relative],))
            self.new.verify_tree(c,tree,self.after)
            self.baseline.base.context();self.baseline.worker('verify');self.baseline.health()
            self.record.record(value,'active')
        except BaseException:
            try:self.restore(value)
            except BaseException:self.record.record(value,'failed_restore_unconfirmed');raise
            raise
        finally:tree.close()

    def verify(self):
        value=self.record.read_state()
        if value['phase']!='active':raise RuntimeError('UI update is not active.')
        self.closure();self.assert_backup();tree=self.tree(value)
        try:
            self.new.verify_tree(self.c,tree,self.after);self.baseline.base.context();self.baseline.worker('verify');self.baseline.health()
        finally:tree.close()

    def rollback(self):
        value=self.record.read_state()
        if value['phase']=='restored_baseline':return
        self.baseline.base.context();self.restore(value)


def cli(arguments):
    if len(arguments)!=1 or arguments[0]not in ACTIONS:raise RuntimeError('Exactly one fixed action required.')
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077);raw,_=load();old,original,new,c=setup(raw);action=arguments[0]
    c.validate_request(c.decode_json(c.secure_read(c.INBOX/'request.json',1002,0o600,8192)),action)
    # Both release locks, so original rollback and follow-up cannot race.
    locks=[]
    try:
        for path in (old.STATE/'lock',STATE/'lock'):
            fd=os.open(path,os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600);locks.append(fd);s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe release lock.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='status':
            value=c.Deployment().read_state(False)
            return {'release':'m119-ui-r02','phase':value['phase']if value else'not_applied','financial_posting':False}
        update=Update(old,original,new,c,package(old,original),package(new,c));getattr(update,action)()
        return {'release':'m119-ui-r02','action':action,'result':'verified','financial_posting':False,'schema_changed':False,'roles_changed':False}
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'release':'m119-ui-r02','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private UI update stopped; inspect recorded state.'}));raise SystemExit(1)
