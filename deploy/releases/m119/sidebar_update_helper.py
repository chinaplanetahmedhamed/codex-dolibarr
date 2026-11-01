#!/usr/bin/python3 -I
"""Pinned R03 sidebar follow-up using the verified R02 replacement/rollback engine."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import sys
import types

SOURCE='26ce07fdef53765121532837dbaf7dcc2f6057cf'
ARTIFACT='87c828b1ef26187384e705fa184ef5bc9567106c0f19d5c8a17b702efabc1830'
MANIFEST='0518d3464781345ee10a8fab960ed5b54ccb5bc1de6468c72d690da83dbbc69b'
PREVIOUS='388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757'
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-sidebar-r03')
CHANGED=('index.php','users.php','staff.css')
ACTIONS=('preflight','apply','verify','rollback','status')


def engine(raw):
    if hashlib.sha256(raw).hexdigest()!=PREVIOUS:raise RuntimeError('Reviewed R02 engine differs.')
    m=types.ModuleType('m119_r03_frozen_r02');m.__file__=str(STATE/'ui_r02_helper.py')
    exec(compile(raw,m.__file__,'exec'),m.__dict__)
    return m


def frozen():
    p=STATE/'ui_r02_helper.py'
    for parent in p.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid!=0 or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe frozen parent.')
    fd=os.open(p,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600 or not 0<s.st_size<=65536:raise RuntimeError('Unsafe frozen engine.')
        raw=os.read(fd,65537);engine(raw);return raw
    finally:os.close(fd)


def configure(raw,raw_standalone):
    baseline=engine(raw)
    old,original,current,c_current=baseline.setup(raw_standalone)
    prior=baseline.Update(old,original,current,c_current,baseline.package(old,original),baseline.package(current,c_current))
    # Separate module instances keep the prior five-file verification/rollback contract unchanged.
    patch=engine(raw);patch.STATE=STATE;patch.CHANGED=CHANGED
    next_source=baseline.module(raw_standalone)
    next_source.SOURCE=SOURCE;next_source.ARTIFACT=ARTIFACT;next_source.MANIFEST=MANIFEST;next_source.STATE=STATE
    c=next_source.configured(old.frozen());c.PHASES=tuple(c.PHASES)+('restored_baseline','failed_restore_unconfirmed')
    u=patch.Update.__new__(patch.Update)
    u.old=current;u.original=c_current;u.new=next_source;u.c=c;u.before=prior.after;u.after=baseline.package(next_source,c);u.record=c.Deployment(u.after)
    u.baseline=types.SimpleNamespace(verify=prior.verify,worker=prior.baseline.worker,health=prior.baseline.health,
        base=types.SimpleNamespace(context=prior.baseline.base.context,read_state=prior.record.read_state))
    return c,u


def setup(raw):
    baseline=engine(raw);raw_standalone,_=baseline.load()
    return configure(raw,raw_standalone)


def cli(arguments):
    if len(arguments)!=1 or arguments[0]not in ACTIONS:raise RuntimeError('Exactly one fixed action required.')
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077);c,update=setup(frozen());action=arguments[0]
    c.validate_request(c.decode_json(c.secure_read(c.INBOX/'request.json',1002,0o600,8192)),action)
    locks=[]
    try:
        for name in ('m119-standalone-r01','m119-ui-r02','m119-sidebar-r03'):
            fd=os.open(STATE.parent/name/'lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600);locks.append(fd);s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe release lock.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='status':
            value=update.record.read_state(False)
            return {'release':'m119-sidebar-r03','phase':value['phase']if value else'not_applied','financial_posting':False}
        getattr(update,action)()
        return {'release':'m119-sidebar-r03','action':action,'result':'verified','financial_posting':False,'schema_changed':False,'roles_changed':False}
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'release':'m119-sidebar-r03','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private sidebar update stopped; inspect retained state.'}));raise SystemExit(1)
