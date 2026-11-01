#!/usr/bin/python3 -I
"""Exact old-to-new M119 helper installation; no sudoers, credentials or service changes."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import types

OLD_SHA='845e497f2828df927ce14e5431d180a747c62bae264fbcee244183696cb967d9'
NEW_SHA='471df8d4985121ed6b512f2ed4361c459dd080c30e4b71ffaf40aaf3fb6ce7de'
WORKER_SHA='eefc1b7525031f6401f0e8568f85aab09722079562f5b913b607d1ee68ef2898'
TARGET=Path('/usr/local/sbin/chinaplanetos-backup-erp-deploy')
INBOX=Path('/home/codex-deploy/staging-inbox/m119-standalone-r01')
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-standalone-r01')


def read(path,owner,mode,limit):
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid not in (0,owner)or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe installer parent.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=owner or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=mode or not 0<s.st_size<=limit:raise RuntimeError('Unsafe installer input.')
        data=os.read(fd,limit+1);after=os.fstat(fd);named=path.lstat()
        def identity(x):return tuple(getattr(x,k)for k in ('st_dev','st_ino','st_size','st_mtime_ns','st_ctime_ns','st_mode','st_uid','st_gid','st_nlink'))
        if len(data)!=s.st_size or identity(s)!=identity(after)or identity(after)!=identity(named):raise RuntimeError('Installer input changed.')
        return data
    finally:os.close(fd)


def write(path,data,mode=0o600):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
    with os.fdopen(fd,'wb')as f:f.write(data);f.flush();os.fsync(f.fileno());os.fchmod(f.fileno(),mode)


def main():
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative host differs.')
    os.umask(0o077);old=read(TARGET,0,0o755,65536);helper=read(INBOX/'standalone_deploy_helper.py',1002,0o600,65536);worker=read(INBOX/'standalone_native_operations.php',1002,0o600,65536)
    if hashlib.sha256(old).hexdigest()!=OLD_SHA or hashlib.sha256(helper).hexdigest()!=NEW_SHA or hashlib.sha256(worker).hexdigest()!=WORKER_SHA:raise RuntimeError('Exact helper update checksums differ.')
    if not helper.startswith(b'#!/usr/bin/python3 -I\n'):raise RuntimeError('Isolated launcher required.')
    for action in ('preflight','apply','verify','rollback','status'):
        r=subprocess.run(['sudo','-n','-l','-U','codex-deploy','--',str(TARGET),action],capture_output=True,timeout=10,env={'PATH':'/usr/sbin:/usr/bin:/sbin:/bin'},cwd='/')
        if r.returncode:raise RuntimeError('Existing exact five-command grant unavailable.')
    parent=STATE.parent;s=parent.lstat()
    if not stat.S_ISDIR(s.st_mode)or s.st_uid!=0 or s.st_mode&0o077 or parent.resolve()!=parent:raise RuntimeError('State root differs.')
    # Hold the historical release lock, preserving both old and new deployment audit.
    lock=os.open(parent/'m119/lock',os.O_RDWR|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(lock)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Historical lock differs.')
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if STATE.exists()or STATE.is_symlink():raise RuntimeError('New installer has prior state; inspect without replay.')
        STATE.mkdir(mode=0o700);write(STATE/'legacy_helper.py',old);write(STATE/'native_operations.php',worker)
        namespace=types.ModuleType('m119_standalone_installer_review');namespace.__file__=str(TARGET);exec(compile(helper,str(TARGET),'exec'),namespace.__dict__)
        c=namespace.configured(old);files=namespace.validate_package(c,c.secure_read(c.INBOX/('manifest-'+namespace.ARTIFACT+'.json'),1002,0o600,65536),c.secure_read(c.INBOX/('artifact-'+namespace.ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))
        namespace.Deployment(c,namespace.library(old),files).preflight()
        temporary=TARGET.with_name('.m119-standalone-r01-helper.tmp');write(temporary,helper,0o755)
        if hashlib.sha256(read(TARGET,0,0o755,65536)).hexdigest()!=OLD_SHA:raise RuntimeError('Installed helper changed before replacement.')
        os.replace(temporary,TARGET);c.sync_directory(TARGET.parent)
        if hashlib.sha256(read(TARGET,0,0o755,65536)).hexdigest()!=NEW_SHA:raise RuntimeError('New helper readback failed.')
        result={'mission':'M119','release':'standalone-r01','result':'HELPER_UPDATED_PREFLIGHT_PASS','helper_sha256':NEW_SHA,'native_worker_sha256':WORKER_SHA,'sudoers_changed':False,'application_applied':False,'schema_changed':False,'financial_posting':False}
        write(STATE/'installer-acceptance.json',(json.dumps(result,sort_keys=True)+'\n').encode());print(json.dumps(result,sort_keys=True))
    finally:os.close(lock)


if __name__=='__main__':
    try:main()
    except BaseException as error:
        print(json.dumps({'mission':'M119','result':'HELPER_UPDATE_STOP','reason':str(error)if type(error)is RuntimeError else'Private installer stopped; inspect retained state.'}));raise SystemExit(1)
