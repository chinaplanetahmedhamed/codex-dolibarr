#!/usr/bin/python3 -I
"""Root-only exact predecessor correction. No app, sudoers or permission changes."""
import ast
import fcntl
import hashlib
import os
from pathlib import Path
import stat
import tempfile

TARGET=Path('/usr/local/sbin/chinaplanetos-m085-staging')
STATE=Path('/var/lib/chinaplanetos-m085-staging')
BEFORE='97eaf56b1464560369cf8e5cc60eb3adb692085408c24a4a80a2556ddce0b168'
AFTER='f9b2db76010c710d81ee70c90cce52e260518bb230b9079f57e987d0683e4426'
OLD_GUARD='''    for path in (ROOT.parent, STATE):
        if path.is_symlink() or not path.is_dir() or path.stat().st_uid != 0 or path.stat().st_mode & 0o022:
            fail('ROOT_OWNERSHIP_MISMATCH')
'''
NEW_GUARD='''    # Root stat supplied by Ahmed: application volume www-data:www-data 0755;
    # private deployment state root:root 0700. Neither directory is mutated here.
    web_uid=pwd.getpwnam('www-data').pw_uid
    web_gid=grp.getgrnam('www-data').gr_gid
    check_directory(ROOT.parent,web_uid,web_gid,0o755)
    check_directory(STATE,0,0,0o700)
'''
CHECK_FUNCTION='''def check_directory(path,uid,gid,mode):
    info=path.lstat()
    if not stat.S_ISDIR(info.st_mode) or (info.st_uid,info.st_gid,stat.S_IMODE(info.st_mode)) != (uid,gid,mode):
        fail('DIRECTORY_OWNERSHIP_MISMATCH:'+path.name)


'''

def digest(data):return hashlib.sha256(data).hexdigest()

def transform(data):
    if digest(data)!=BEFORE:raise RuntimeError('PREDECESSOR_MISMATCH')
    text=data.decode()
    for marker in ('import os\n',OLD_GUARD,'def environment(require_healthy=True):'):
        if text.count(marker)!=1:raise RuntimeError('PATCH_MATCH_COUNT')
    result=text.replace('import os\n','import os\nimport pwd\nimport grp\n',1).replace(OLD_GUARD,NEW_GUARD,1).replace('def environment(require_healthy=True):',CHECK_FUNCTION+'def environment(require_healthy=True):',1).encode()
    if digest(result)!=AFTER:raise RuntimeError('CANDIDATE_MISMATCH')
    ast.parse(result)
    return result

def secure_regular(path,uid=0,mode=None):
    info=path.lstat()
    if not stat.S_ISREG(info.st_mode) or info.st_nlink!=1 or info.st_uid!=uid or info.st_gid!=0 or (mode is not None and stat.S_IMODE(info.st_mode)!=mode):raise RuntimeError('UNSAFE_FILE')

def main():
    if os.geteuid()!=0:raise RuntimeError('ROOT_REQUIRED')
    for directory,mode in ((Path('/usr/local/sbin'),None),(STATE,0o700)):
        info=directory.lstat()
        if not stat.S_ISDIR(info.st_mode) or info.st_uid!=0 or info.st_gid!=0 or info.st_mode&0o022 or (mode is not None and stat.S_IMODE(info.st_mode)!=mode):raise RuntimeError('UNSAFE_DIRECTORY')
    fd=os.open(STATE/'release.lock',os.O_WRONLY|os.O_CREAT|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        # Never replace a helper after any apply or backup has started.
        if any(os.path.lexists(STATE/name) for name in ('state.json','before.json','before-rollback.json')):raise RuntimeError('RELEASE_ALREADY_STARTED')
        secure_regular(TARGET,mode=0o755)
        old=TARGET.read_bytes()
        if digest(old)==AFTER:
            print('M085_OWNERSHIP_PATCH=ALREADY_INSTALLED APPLY_EXECUTED=NO');return
        new=transform(old)
        backup=STATE/'helper-before-ownership-fix.py'
        if os.path.lexists(backup):
            secure_regular(backup,mode=0o600)
            if digest(backup.read_bytes())!=BEFORE:raise RuntimeError('BACKUP_MISMATCH')
        else:
            fd=os.open(backup,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
            with os.fdopen(fd,'wb') as stream:stream.write(old);stream.flush();os.fsync(stream.fileno())
        fd,tmp=tempfile.mkstemp(prefix='.m085-owner-',dir=TARGET.parent)
        try:
            with os.fdopen(fd,'wb') as stream:
                stream.write(new);stream.flush();os.fchmod(stream.fileno(),0o755);os.fsync(stream.fileno())
            secure_regular(TARGET,mode=0o755)
            if digest(TARGET.read_bytes())!=BEFORE:raise RuntimeError('PREDECESSOR_CHANGED')
            os.replace(tmp,TARGET)
            fd=os.open(TARGET.parent,os.O_DIRECTORY)
            try:os.fsync(fd)
            finally:os.close(fd)
        finally:
            if os.path.exists(tmp):os.unlink(tmp)
        secure_regular(TARGET,mode=0o755)
        if digest(TARGET.read_bytes())!=AFTER:raise RuntimeError('INSTALLED_HASH_MISMATCH')
        print('M085_OWNERSHIP_PATCH=PASS HELPER_SHA256='+AFTER+' APPLY_EXECUTED=NO APPLICATION_CHANGED=NO SUDOERS_CHANGED=NO PRODUCTION_ACCESSED=NO')

if __name__=='__main__':main()
