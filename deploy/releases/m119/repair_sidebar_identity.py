#!/usr/bin/python3 -I
"""Install only the fixed M119 UI updater behind the existing five-command helper."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import types

OLD='388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757'
NEW='b51706fff210aba0022502aff7b74b698ed6a6f11b5e0286bd9b5f1a3aaa1674'
READER='2e3a02083f15986706f38e6eab2d6a4d143dad7d7b943681b04cfcd6d6d5e64e'
TARGET=Path('/usr/local/sbin/chinaplanetos-backup-erp-deploy')
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-sidebar-r03')
INBOX=Path('/home/codex-deploy/staging-inbox/m119-sidebar-r03')


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
    old=reader.read(TARGET,0,0o755,65536);new=reader.read(INBOX/'sidebar_update_helper.py',1002,0o600,65536)
    if hashlib.sha256(old).hexdigest()!='7fb963f8db83af868471afcfa7d7c5b97677904b963bbf0365404aed2bfc0b63' or hashlib.sha256(new).hexdigest()!=NEW:raise RuntimeError('Exact installed/fixed helper differs.')
    locks=[]
    try:
        for name in ('m119-standalone-r01','m119-ui-r02','m119-sidebar-r03'):
            fd=os.open(STATE.parent/name/'lock',os.O_RDWR|os.O_NOFOLLOW);locks.append(fd);s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe repair lock.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        raw=reader.read(STATE/'state.json',0,0o600,16384)
        if hashlib.sha256(raw).hexdigest()!='07ceee7362757bb9a7f3d6dd45e2401ccdb8f0f67b666ab32e88199e28b6f454':raise RuntimeError('Exact observed mislabelled state differs; no adoption.')
        m=types.ModuleType('m119_r03_repair');m.__file__=str(TARGET);exec(compile(new,str(TARGET),'exec'),m.__dict__)
        c,u=m.setup(reader.read(STATE/'ui_r02_helper.py',0,0o600,65536));value=c.decode_json(raw)
        prior=u.baseline.base.read_state()
        if value['phase']!='active' or value['commit']!=prior['commit'] or value['artifact']!=prior['artifact'] or value['baseline_id']!=prior['id']:raise RuntimeError('Observed predecessor identity differs.')
        u.closure();u.assert_backup();tree=u.tree(value)
        try:u.new.verify_tree(c,tree,u.after)
        finally:tree.close()
        u.baseline.base.context();u.baseline.worker('verify');u.baseline.health()
        # Preserve the complete original state and helper. Do not delete/rewrite old audit events.
        reader.write(STATE/'state-before-global-fix.json',raw);reader.write(STATE/'helper-before-global-fix.py',old)
        corrected=dict(value,commit=m.SOURCE,artifact=m.ARTIFACT,state_identity_correction_sha256=hashlib.sha256(raw).hexdigest())
        u.record.record(corrected,'active')
        temp=TARGET.with_name('.m119-sidebar-global-fix.tmp');reader.write(temp,new,0o755)
        if hashlib.sha256(reader.read(TARGET,0,0o755,65536)).hexdigest()!=hashlib.sha256(old).hexdigest():raise RuntimeError('Helper changed before replacement.')
        os.replace(temp,TARGET);c.sync_directory(TARGET.parent)
        if hashlib.sha256(reader.read(TARGET,0,0o755,65536)).hexdigest()!=NEW:raise RuntimeError('Fixed helper readback differs.')
        u.verify()
        result={'release':'m119-sidebar-r03','result':'RECORDED_IDENTITY_CORRECTED_AND_VERIFIED','helper_sha256':NEW,'original_state_sha256':hashlib.sha256(raw).hexdigest(),'application_reapplied':False,'schema_changed':False,'roles_changed':False,'sudoers_changed':False}
        reader.write(STATE/'identity-correction-acceptance.json',(json.dumps(result,sort_keys=True)+'\n').encode());print(json.dumps(result,sort_keys=True))
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:main()
    except BaseException as error:
        print(json.dumps({'release':'m119-sidebar-r03','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private reconciliation stopped; inspect retained state.'}));raise SystemExit(1)
