#!/usr/bin/python3 -I
"""Update only the existing five-command staging deployer; no sudoers/access change."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess

OLD_SHA='3513fa991710eb2b31ef34f7ac59802769ea74b926e3ddaf05c1a6f0fe8d6d20'
NEW_SHA='845e497f2828df927ce14e5431d180a747c62bae264fbcee244183696cb967d9'
LIB_SHA='c0d13dda18df364b3a3020ae06fedf1683944a6718db10d723975fcf67ce5ef0'
TARGET=Path('/usr/local/sbin/chinaplanetos-backup-erp-deploy')
INBOX=Path('/home/codex-deploy/staging-inbox')
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119')
ARTIFACT='faa869ec8d05b6e631887bb1dbe0c403deb9d97909cea84ede492ee172bca698'


def read(path,owner,mode,size):
    path=Path(path)
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode) or s.st_uid not in (0,owner) or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe administrative path.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    with os.fdopen(fd,'rb') as f:
        s=os.fstat(f.fileno())
        if not stat.S_ISREG(s.st_mode) or s.st_uid!=owner or stat.S_IMODE(s.st_mode)!=mode or s.st_nlink!=1 or not 0<s.st_size<=size:raise RuntimeError('Unsafe bounded installer source.')
        data=f.read(size+1)
        if len(data)!=s.st_size or (path.lstat().st_dev,path.lstat().st_ino)!=(s.st_dev,s.st_ino):raise RuntimeError('Installer source changed.')
    return data


def write(path,data,mode=0o600):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,mode)
    with os.fdopen(fd,'wb') as f:
        f.write(data);f.flush();os.fsync(f.fileno());os.fchmod(f.fileno(),mode)


def main():
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Fixed administrative host identity differs.')
    os.umask(0o077)
    helper=read(INBOX/'m119/deploy-helper-845e497f.py',1002,0o600,65536)
    library=read(INBOX/'m119/prepare-c0d13dda18df364b.py',1002,0o600,65536)
    old=read(TARGET,0,0o755,131072)
    if hashlib.sha256(old).hexdigest()!=OLD_SHA or hashlib.sha256(helper).hexdigest()!=NEW_SHA or hashlib.sha256(library).hexdigest()!=LIB_SHA:raise RuntimeError('Exact old/new helper or library pin differs; no replacement.')
    if not helper.startswith(b'#!/usr/bin/python3 -I\n'):raise RuntimeError('Isolated system Python launcher is required.')
    namespace={'__name__':'m119_installer_reviewed_helper','__file__':str(TARGET)}
    exec(compile(helper,str(TARGET),'exec'),namespace)
    files=namespace['validate_package'](read(INBOX/('manifest-'+ARTIFACT+'.json'),1002,0o600,65536),read(INBOX/('artifact-'+ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))
    namespace['ensure_state_directory']()
    if any((STATE/name).exists() for name in ('state.json','previous-helper.py','prepare_runtime.py','installer-acceptance.json')):raise RuntimeError('M119 helper installation has prior state; inspect instead of replay.')
    env={'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'}
    for action in namespace['ACTIONS']:
        result=subprocess.run(['sudo','-n','-l','-U','codex-deploy','--',str(TARGET),action],capture_output=True,timeout=10,cwd='/',env=env)
        if result.returncode:raise RuntimeError('An existing exact helper command grant is unavailable; no access change.')
    locks=[]
    try:
        for path in (STATE.parent/'m014.lock',STATE/'lock'):
            fd=os.open(path,os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW|os.O_NONBLOCK,0o600)
            locks.append(fd)
            s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode) or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Fixed helper lock provenance differs.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        write(STATE/'previous-helper.py',old)
        write(STATE/'prepare_runtime.py',library)
        # Prove actual Linux no-replace publication on a root-private synthetic fixture.
        proof=STATE/'installer-proof';proof.mkdir(mode=0o700)
        fd=os.open(proof,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
        try:
            os.mkdir('synthetic-source',mode=0o700,dir_fd=fd)
            expected=os.stat('synthetic-source',dir_fd=fd,follow_symlinks=False).st_ino
            namespace['rename_new'](fd,'synthetic-source',fd,'synthetic-target')
            if os.stat('synthetic-target',dir_fd=fd,follow_symlinks=False).st_ino!=expected:raise RuntimeError('Actual Linux publication identity differs.')
            os.mkdir('synthetic-source-two',mode=0o700,dir_fd=fd)
            denied=False
            try:namespace['rename_new'](fd,'synthetic-source-two',fd,'synthetic-target')
            except namespace['Refused']:denied=True
            if not denied or os.stat('synthetic-target',dir_fd=fd,follow_symlinks=False).st_ino!=expected:raise RuntimeError('Actual Linux no-replace protection failed.')
        finally:os.close(fd)
        namespace['Deployment'](files).preflight()
        temporary=TARGET.with_name('.m119-deploy-845e497f.tmp')
        write(temporary,helper,0o755)
        if hashlib.sha256(read(TARGET,0,0o755,131072)).hexdigest()!=OLD_SHA:raise RuntimeError('Existing helper changed before atomic installation.')
        os.replace(temporary,TARGET)
        namespace['sync_directory'](TARGET.parent)
        if hashlib.sha256(read(TARGET,0,0o755,65536)).hexdigest()!=NEW_SHA:raise RuntimeError('Installed helper checksum is unconfirmed.')
        result={'mission':'M119','status':'HELPER_INSTALLED_PREFLIGHT_PASS','helper_sha256':NEW_SHA,'backup_library_sha256':LIB_SHA,'linux_no_replace_proof':'PASS','native_uid33_preflight':'PASS','existing_five_command_grants':'UNCHANGED','sudoers_changed':False,'application_files_installed':False,'schema_installed':False,'financial_posting':False}
        write(STATE/'installer-acceptance.json',(json.dumps(result,sort_keys=True)+'\n').encode())
        print(json.dumps(result),flush=True)
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:main()
    except Exception as e:
        print(json.dumps({'mission':'M119','status':'HELPER_INSTALLATION_STOP','reason':str(e) if isinstance(e,RuntimeError) else 'Installer stopped; inspect its own private state before any retry.'}),flush=True)
        raise SystemExit(1)
