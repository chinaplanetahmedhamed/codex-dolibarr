#!/usr/bin/python3 -I
"""Install only the fixed M119 UI updater behind the existing five-command helper."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import types

OLD='471df8d4985121ed6b512f2ed4361c459dd080c30e4b71ffaf40aaf3fb6ce7de'
NEW='388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757'
READER='2e3a02083f15986706f38e6eab2d6a4d143dad7d7b943681b04cfcd6d6d5e64e'
TARGET=Path('/usr/local/sbin/chinaplanetos-backup-erp-deploy')
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-ui-r02')
INBOX=Path('/home/codex-deploy/staging-inbox/m119-ui-r02')


def main():
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077)
    # Bootstrap the already reviewed stable bounded reader by checksum, no mutable execution path.
    path=INBOX/'install_standalone_helper.py'
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid not in (0,1002)or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe reader parent.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=1002 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600 or not 0<s.st_size<=65536:raise RuntimeError('Unsafe reader file.')
        raw=os.read(fd,65537)
    finally:os.close(fd)
    if hashlib.sha256(raw).hexdigest()!=READER:raise RuntimeError('Bounded reader checksum differs.')
    reader=types.ModuleType('m119_reviewed_reader');reader.__file__=str(path);exec(compile(raw,str(path),'exec'),reader.__dict__)
    old=reader.read(TARGET,0,0o755,65536);new=reader.read(INBOX/'ui_update_helper.py',1002,0o600,65536)
    if hashlib.sha256(old).hexdigest()!=OLD or hashlib.sha256(new).hexdigest()!=NEW:raise RuntimeError('Exact UI updater pins differ.')
    lock=os.open(STATE.parent/'m119-standalone-r01/lock',os.O_RDWR|os.O_NOFOLLOW)
    try:
        s=os.fstat(lock)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe original lock.')
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        parent=STATE.parent;s=parent.lstat()
        if s.st_uid!=0 or not stat.S_ISDIR(s.st_mode)or stat.S_IMODE(s.st_mode)!=0o700 or parent.resolve()!=parent:raise RuntimeError('State parent differs.')
        if STATE.exists()or STATE.is_symlink():raise RuntimeError('UI installer has prior state; no replay.')
        STATE.mkdir(mode=0o700);reader.write(STATE/'standalone_helper.py',old)
        m=types.ModuleType('m119_ui_install');m.__file__=str(TARGET);exec(compile(new,str(TARGET),'exec'),m.__dict__)
        old_m,original,new_m,c=m.setup(old)
        m.Update(old_m,original,new_m,c,m.package(old_m,original),m.package(new_m,c)).preflight()
        temp=TARGET.with_name('.m119-ui-r02-helper.tmp');reader.write(temp,new,0o755)
        if hashlib.sha256(reader.read(TARGET,0,0o755,65536)).hexdigest()!=OLD:raise RuntimeError('Helper changed before replacement.')
        os.replace(temp,TARGET);c.sync_directory(TARGET.parent)
        if hashlib.sha256(reader.read(TARGET,0,0o755,65536)).hexdigest()!=NEW:raise RuntimeError('Updater readback differs.')
        result={'release':'m119-ui-r02','result':'HELPER_UPDATED_PREFLIGHT_PASS','helper_sha256':NEW,'application_applied':False,'schema_changed':False,'roles_changed':False,'sudoers_changed':False}
        reader.write(STATE/'installer-acceptance.json',(json.dumps(result,sort_keys=True)+'\n').encode());print(json.dumps(result,sort_keys=True))
    finally:os.close(lock)


if __name__=='__main__':
    try:main()
    except BaseException as error:
        print(json.dumps({'release':'m119-ui-r02','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private installer stopped; inspect retained state.'}));raise SystemExit(1)
