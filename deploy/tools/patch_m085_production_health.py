#!/usr/bin/python3 -I
"""Root-only exact predecessor correction. No app, sudoers or permission changes."""
import ast
import fcntl
import hashlib
import os
from pathlib import Path
import stat
import tempfile

TARGET=Path('/usr/local/sbin/chinaplanetos-m085-production')
STATE=Path('/var/lib/chinaplanetos-m085-production')
BEFORE='2ee4df2182edb27c650239aa18b73e076af40fbc1f965a720ef0e21fa5e4a059'
AFTER='c34e2562d154df2ba44592e182609e74efb0984ac8506589a6a55f8df867da58'
OLD_HEALTH='def health():\n    # Fixed localhost within the already-validated production container. No redirect.\n    code = r\'\'\'$ctx=stream_context_create(array(\'http\'=>array(\'timeout\'=>15,\'follow_location\'=>0)));\n$body=@file_get_contents(\'http://127.0.0.1/\',false,$ctx);\n$status=isset($http_response_header[0])?$http_response_header[0]:\'\';\nif(!preg_match(\'~^HTTP/\\S+ 200 ~\',$status)||$body===false||strpos($body,\'Dolibarr\')===false||strpos($body,\'type="password"\')===false)exit(15);\necho \'M085_LOGIN_OK\';\'\'\'\n    if php(code).strip()!=b\'M085_LOGIN_OK\':fail(\'LOGIN_HEALTH_FAILED\')'
NEW_HEALTH='def health():\n    # Fixed loopback HTTP within the validated container. Socket IO works with\n    # allow_url_fopen disabled; no redirects, external hosts or PHP setting changes.\n    code = r\'\'\'$socket=@fsockopen(\'127.0.0.1\',80,$errno,$error,10);\nif(!$socket)exit(15);\nstream_set_timeout($socket,15);\n$request="GET / HTTP/1.0\\r\\nHost: erp.visiontrading.biz\\r\\nX-Forwarded-Proto: https\\r\\nConnection: close\\r\\n\\r\\n";\nif(fwrite($socket,$request)!==strlen($request)){fclose($socket);exit(15);}\n$response=stream_get_contents($socket,1000000);$meta=stream_get_meta_data($socket);fclose($socket);\n$split=$response===false?false:strpos($response,"\\r\\n\\r\\n");\nif($response===false||!empty($meta[\'timed_out\'])||$split===false||!preg_match(\'~^HTTP/\\S+ 200 ~\',$response))exit(15);\n$body=substr($response,$split+4);\nif(strpos($body,\'Dolibarr\')===false||strpos($body,\'type="password"\')===false)exit(15);\necho \'M085_LOGIN_OK\';\'\'\'\n    if php(code).strip()!=b\'M085_LOGIN_OK\':fail(\'LOGIN_HEALTH_FAILED\')'
OLD_COMMENT='# Production HTTP health is checked inside the fixed container, not through\n    # a guessed host port. Both root and the web service are trusted volume owners.'
NEW_COMMENT='# Production identity is checked against the fixed container and volume.\n    # Both root and the web service are trusted volume owners.'

def digest(data):return hashlib.sha256(data).hexdigest()

def transform(data):
    if digest(data)!=BEFORE:raise RuntimeError('PREDECESSOR_MISMATCH')
    text=data.decode()
    for marker in (OLD_HEALTH,OLD_COMMENT):
        if text.count(marker)!=1:raise RuntimeError('PATCH_MATCH_COUNT')
    result=text.replace(OLD_HEALTH,NEW_HEALTH,1).replace(OLD_COMMENT,NEW_COMMENT,1).encode()
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
            print('M085_HEALTH_PATCH=ALREADY_INSTALLED APPLY_EXECUTED=NO');return
        new=transform(old)
        # Validate the replacement against the fixed production container before
        # changing even the helper; none of these calls applies application code.
        candidate={'__name__':'m085_health_candidate','__file__':str(TARGET)}
        exec(compile(new,str(TARGET),'exec'),candidate)
        candidate['environment']()
        candidate['health']()
        backup=STATE/'helper-before-health-fix.py'
        if os.path.lexists(backup):
            secure_regular(backup,mode=0o600)
            if digest(backup.read_bytes())!=BEFORE:raise RuntimeError('BACKUP_MISMATCH')
        else:
            fd=os.open(backup,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
            with os.fdopen(fd,'wb') as stream:stream.write(old);stream.flush();os.fsync(stream.fileno())
        fd,tmp=tempfile.mkstemp(prefix='.m085-health-',dir=TARGET.parent)
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
        print('M085_HEALTH_PATCH=PASS HELPER_SHA256='+AFTER+' APPLY_EXECUTED=NO APPLICATION_CHANGED=NO SUDOERS_CHANGED=NO PHP_CONFIGURATION_CHANGED=NO')

if __name__=='__main__':main()
