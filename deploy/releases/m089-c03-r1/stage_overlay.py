#!/usr/bin/python3 -I
"""Restore staging-only UI overlays on top of the tested M089-C03-R1 code."""
import fcntl
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import time

INBOX = Path('/home/codex-deploy/staging-inbox')
PRIMARY = Path('/root/chinaplanetos-m089-c03-r1-deploy.py')
PRIMARY_SHA = 'd7fbf4135c67f566dffd46054e07adafc84674b1befc3d3eda4aeebc55b9ca49'
MANIFEST_SHA = '25661199afb53d29477536f2b72a7e1c8bd26415821230d0d64fbfa60bdcf274'
PAYLOAD_SHA = 'fedf5bbc35d92d010a754ac102a9a77274353644e31286baa5022cc90baa751f'

def check(condition, reason):
    if not condition: raise RuntimeError(reason)

def digest(data): return hashlib.sha256(data).hexdigest()

def main():
    check(os.geteuid()==0 and len(sys.argv)==2 and sys.argv[1] in ('preflight','apply','verify','rollback'), 'EXACT_ROOT_ACTION_REQUIRED')
    check(digest(PRIMARY.read_bytes())==PRIMARY_SHA, 'PRIMARY_HELPER_MISMATCH')
    spec=importlib.util.spec_from_file_location('m089_primary',PRIMARY)
    release=importlib.util.module_from_spec(spec);spec.loader.exec_module(release)
    mb=(INBOX/'m089-c03-r1-stage-overlay-manifest.json').read_bytes()
    pb=(INBOX/'m089-c03-r1-stage-overlay.tar.gz').read_bytes()
    check(digest(mb)==MANIFEST_SHA and digest(pb)==PAYLOAD_SHA, 'OVERLAY_ARTIFACT_MISMATCH')
    manifest=json.loads(mb)
    files=manifest['files']
    check(manifest['release']=='M089-C03-R1-staging-overlay' and manifest['payload_sha256']==PAYLOAD_SHA and len(files)==9, 'OVERLAY_MANIFEST_MISMATCH')
    payload={}
    with tarfile.open(fileobj=io.BytesIO(pb),mode='r:gz') as archive:
        check(set(archive.getnames())==set(files), 'OVERLAY_SCOPE_MISMATCH')
        for member in archive:
            check(member.isfile() and not member.issym() and '..' not in Path(member.name).parts and not member.name.startswith('/'), 'UNSAFE_OVERLAY_MEMBER')
            data=archive.extractfile(member).read()
            check(digest(data)==files[member.name]['after'], 'OVERLAY_FILE_HASH_MISMATCH')
            payload[member.name]=data
    web,_,root=release.target('staging')
    main_state=json.loads(Path('/root/chinaplanetos-m089-c03-r1-staging/state.json').read_text())
    check(main_state['status']=='DEPLOYED', 'PRIMARY_STAGE_NOT_DEPLOYED')
    state_dir=Path('/root/chinaplanetos-m089-c03-r1-stage-overlay')
    state_path=state_dir/'state.json'
    if sys.argv[1]=='preflight':
        release.compare(root,{n:x['before'] for n,x in files.items()})
        for name,data in payload.items():
            if name.endswith('.php'): release.command(['docker','exec','-i',web,'php','-l'],data=data)
        print(json.dumps({'action':'preflight','result':'PASS','environment':'staging','files':len(files)}));return
    state_dir.mkdir(mode=0o700,exist_ok=True)
    check(not state_dir.is_symlink(), 'UNSAFE_STATE')
    with open(state_dir/'lock','a') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        state=json.loads(state_path.read_text()) if state_path.exists() else None
        action=sys.argv[1]
        if action=='apply':
            check(state is None, 'REPLAY_REFUSED')
            release.compare(root,{n:x['before'] for n,x in files.items()})
            before={n:release.file_info(root,n) for n in files}
            release.write_json(state_dir/'before.json',before)
            state={'status':'APPLYING','at':int(time.time()),'backup_sha256':digest((state_dir/'before.json').read_bytes())}
            release.write_json(state_path,state)
            try:
                for name,data in payload.items(): release.atomic(root,name,data,before[name])
                release.compare(root,{n:x['after'] for n,x in files.items()})
                state['status']='DEPLOYED';release.write_json(state_path,state)
            except BaseException:
                release.restore(root,before)
                state['status']='AUTO_ROLLED_BACK';release.write_json(state_path,state)
                raise
        elif action=='verify':
            check(state and state['status']=='DEPLOYED', 'OVERLAY_NOT_DEPLOYED')
            release.compare(root,{n:x['after'] for n,x in files.items()})
        else:
            check(state and state['status']=='DEPLOYED', 'OVERLAY_NOT_DEPLOYED')
            release.compare(root,{n:x['after'] for n,x in files.items()})
            raw=(state_dir/'before.json').read_bytes()
            check(digest(raw)==state['backup_sha256'], 'OVERLAY_BACKUP_HASH_MISMATCH')
            release.restore(root,json.loads(raw))
            release.compare(root,{n:x['before'] for n,x in files.items()})
            state['status']='ROLLED_BACK';release.write_json(state_path,state)
        print(json.dumps({'action':action,'result':'PASS','environment':'staging','status':state['status'],'files':len(files)}))

if __name__=='__main__':
    try: main()
    except BaseException as exc:
        print('M089_STAGE_OVERLAY_FAIL='+str(exc) if isinstance(exc,RuntimeError) else 'M089_STAGE_OVERLAY_FAIL='+type(exc).__name__,file=sys.stderr)
        sys.exit(1)
