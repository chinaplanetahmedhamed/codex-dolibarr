#!/usr/bin/python3 -I
"""Pinned original design acceptance R08 using the frozen R02 atomic replacement/rollback engine."""
import fcntl
import hashlib
import json
import os
from pathlib import Path
import stat
import sys
import types

SOURCE='a08a0177848091bd4b1aedf6e0d683632ac437bf'
ARTIFACT='42432dc04b4a51a79c096454a16e2d88b001678177a731cd05fdf6a2c10c8bd5'
MANIFEST='7d087b1e5fbfaeef7363349e6d4f5df9be494abde7c03be3195b68a7656dd5bd'
PREVIOUS='6155d463de43cd429a0be573d08bc95307ce643d78f2c8da9d6ba1c0d8f69f16'
WORKSPACE='709c2221968b17219c87e260b55ca1cd5601e9f7e70031515774ff759fc1d1c6'
REPLACER='388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757'
STANDALONE='471df8d4985121ed6b512f2ed4361c459dd080c30e4b71ffaf40aaf3fb6ce7de'
STATE=Path('/var/lib/chinaplanetos-backup-erp-deploy/m119-design-r08')
CHANGED=('class/OfficeExpenseWorkspaceController.php','class/OfficeExpenseWorkspacePage.php','workspace.css')
ACTIONS=('preflight','apply','verify','rollback','status')


def pinned(raw,expected,name):
    if hashlib.sha256(raw).hexdigest()!=expected:raise RuntimeError('Reviewed '+name+' checksum differs.')
    m=types.ModuleType(name);m.__file__=str(STATE/(name+'.py'));exec(compile(raw,m.__file__,'exec'),m.__dict__);return m


def reader():
    path=STATE.parent/'m119-sidebar-r03/ui_r02_helper.py'
    for parent in path.parents:
        s=parent.lstat()
        if not stat.S_ISDIR(s.st_mode)or s.st_uid!=0 or s.st_mode&0o022 or parent.resolve()!=parent:raise RuntimeError('Unsafe frozen parent.')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        s=os.fstat(fd);raw=os.read(fd,65537)
        if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600 or not 0<s.st_size<=65536:raise RuntimeError('Unsafe frozen reader.')
    finally:os.close(fd)
    r=pinned(raw,REPLACER,'m119_frozen_reader');_,c,_,_=r.setup(r.load()[0]);return raw,c


def setup(raw):
    prior=pinned(raw,PREVIOUS,'m119_frozen_workspace')
    raw_replacer,c_reader=reader()
    # The R06 verifier retains its own pins and rollback; native R04 verification stays unchanged.
    previous_raw=c_reader.secure_read(prior.STATE/'previous_helper.py',0,0o600,65536)
    c_prior,baseline=prior.setup(previous_raw)
    patch=pinned(raw_replacer,REPLACER,'m119_receipt_replacer')
    patch.STATE=STATE;patch.CHANGED=CHANGED;patch.SOURCE=SOURCE;patch.ARTIFACT=ARTIFACT;patch.MANIFEST=MANIFEST
    standalone_raw=c_reader.secure_read(STATE.parent/'m119-ui-r02/standalone_helper.py',0,0o600,65536)
    engine=pinned(standalone_raw,STANDALONE,'m119_receipt_engine');legacy_raw=engine.frozen()
    engine.SOURCE=SOURCE;engine.ARTIFACT=ARTIFACT;engine.MANIFEST=MANIFEST;engine.STATE=STATE;engine.RUNTIME=baseline.old.RUNTIME
    c=engine.configured(legacy_raw);c.PHASES=tuple(c.PHASES)+('restored_baseline','failed_restore_unconfirmed')
    r06=pinned(previous_raw,'76ce5fe08cb3081dc2eeb4ecec00e7b37dec5c668524855e5df541c31263f386','m119_r06')
    r05_raw=c_reader.secure_read(r06.STATE/'previous_helper.py',0,0o600,65536)
    r05=pinned(r05_raw,'945d9b4cedddfa22c442603f293bdde01de839898743ce7dc372731bf72e435e','m119_r05')
    workspace_raw=c_reader.secure_read(r05.STATE/'previous_helper.py',0,0o600,65536)
    validator=pinned(workspace_raw,WORKSPACE,'m119_design_package_validator')
    validator.SOURCE=SOURCE;validator.ARTIFACT=ARTIFACT;validator.MANIFEST=MANIFEST
    files=validator.validate_package(c,c.secure_read(c.INBOX/('manifest-'+ARTIFACT+'.json'),1002,0o600,65536),c.secure_read(c.INBOX/('artifact-'+ARTIFACT+'.tar.gz'),1002,0o600,4*1024*1024))
    update=patch.Update.__new__(patch.Update)
    update.old=baseline.new;update.original=c_prior;update.new=engine;update.c=c;update.before=baseline.after;update.after=files;update.record=c.Deployment(files)
    update.baseline=types.SimpleNamespace(verify=baseline.verify,worker=baseline.baseline.worker,health=baseline.baseline.health,
        base=types.SimpleNamespace(context=baseline.record.context,read_state=baseline.record.read_state))
    return c,update


def cli(arguments):
    if len(arguments)!=1 or arguments[0]not in ACTIONS:raise RuntimeError('Exactly one fixed action required.')
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Administrative staging host differs.')
    os.umask(0o077);_,r=reader();c,update=setup(r.secure_read(STATE/'previous_helper.py',0,0o600,65536));action=arguments[0]
    c.validate_request(c.decode_json(c.secure_read(c.INBOX/'request.json',1002,0o600,8192)),action)
    locks=[]
    try:
        for name in ('m119-standalone-r01','m119-ui-r02','m119-sidebar-r03','m119-workspace-r04','m119-receipt-r05','m119-receipt-r06','m119-design-r07','m119-design-r08'):
            fd=os.open(STATE.parent/name/'lock',os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600);locks.append(fd);s=os.fstat(fd)
            if not stat.S_ISREG(s.st_mode)or s.st_uid!=0 or s.st_nlink!=1 or stat.S_IMODE(s.st_mode)!=0o600:raise RuntimeError('Unsafe release lock.')
            fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='status':
            value=update.record.read_state(False);return {'release':'m119-design-r08','phase':value['phase']if value else'not_applied','financial_posting':False}
        getattr(update,action)();return {'release':'m119-design-r08','action':action,'result':'verified','financial_posting':False,'schema_changed':False,'roles_changed':False}
    finally:
        for fd in reversed(locks):os.close(fd)


if __name__=='__main__':
    try:print(json.dumps(cli(sys.argv[1:]),sort_keys=True))
    except BaseException as error:
        print(json.dumps({'release':'m119-design-r08','result':'STOP','reason':str(error)if type(error)is RuntimeError else'Private receipt update stopped; inspect retained state.'}));raise SystemExit(1)
