#!/usr/bin/python3 -I
"""One-file M091 correction. Read-only production candidate check precedes writes."""
import base64, fcntl, hashlib, json, os, pathlib, stat, sys

INBOX=pathlib.Path('/home/codex-deploy/staging-inbox')
BASE_SHA='fcd594997e2d768307ed4da43e94c64d84e600f425f3ab3e6d82ce83bc48f495'
NEW_SHA='859e45babae5ba010acb6fb3bf94938f0a551ef37bc82a75368f602c3f15406e'
NAME='chinaplanetoperations/class/InternalTaskAgendaService.class.php'
STATE=pathlib.Path('/root/chinaplanetos-m091-production/c01')

def runtime(ns,web,data=None):
    code=ns['BOOT']+"require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php';"
    klass='InternalTaskAgendaService'
    if data is not None:
        source=data.decode().removeprefix('<?php').replace('class InternalTaskAgendaService','class M091AgendaCandidate').replace('__DIR__',"(DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class')")
        code+="eval(base64_decode('"+base64.b64encode(source.encode()).decode()+"'));"
        klass='M091AgendaCandidate'
    code+="$db->query('START TRANSACTION READ ONLY');$actor=new User($db);if($actor->fetch(11)<=0)exit(20);$actor->loadRights();"
    code+="$days=(new "+klass+"($db,$actor,1))->events(strtotime('2026-09-01'),strtotime('2026-10-01'),array('owner'=>'11'));$ids=array();foreach($days as $events)foreach($events as $e){if($e->userownerid!==11)exit(21);$ids[$e->id]=1;}"
    code+="if(count($ids)<1)exit(22);$db->query('ROLLBACK');echo json_encode(array('result'=>'PASS','scheduled_tasks'=>count($ids)));$db->close();"
    result=json.loads(ns['php'](web,code))
    ns['check'](result['result']=='PASS','CALENDAR_RUNTIME_FAILED')
    return result

def main():
    assert os.geteuid()==0 and len(sys.argv)==2 and sys.argv[1] in ('preflight','apply','verify','rollback'),'EXACT_ACTION_REQUIRED'
    action=sys.argv[1]
    b=(INBOX/'m091-deploy.py').read_bytes()
    assert hashlib.sha256(b).hexdigest()==BASE_SHA,'BASE_HELPER_HASH'
    ns={'__name__':'m091_c01_base'};exec(compile(b,'m091-deploy.py','exec'),ns)
    manifest,_=ns['load_payload']();web,db,root=ns['target']('production')
    data=(INBOX/'m091-c01-agenda.php').read_bytes()
    assert hashlib.sha256(data).hexdigest()==NEW_SHA,'CANDIDATE_HASH'
    expected=dict(manifest['files']);expected[NAME]=NEW_SHA
    ns['compare'](root,manifest['prerequisites'])
    ns['readiness'](web,True)
    ns['command'](['docker','exec','-i',web,'php','-l'],data=data)
    state_path=STATE/'state.json'
    if action=='preflight':
        ns['compare'](root,manifest['files']);result=runtime(ns,web,data)
    else:
        STATE.mkdir(mode=0o700,exist_ok=True)
        assert not STATE.is_symlink() and stat.S_IMODE(STATE.stat().st_mode)==0o700,'STATE_UNSAFE'
        with open(STATE/'lock','a') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            state=json.loads(state_path.read_text()) if state_path.exists() else None
            if action=='apply':
                assert state is None,'REPLAY_REFUSED'
                ns['compare'](root,manifest['files']);runtime(ns,web,data)
                before=ns['file_info'](root,NAME)
                ns['write_json'](STATE/'code-backup.json',{NAME:before})
                state={'status':'APPLYING','before_sha256':ns['sha']((STATE/'code-backup.json').read_bytes()),'source':'db746106ef5fbe6a62db61ccac70064390fa3fd7'}
                ns['write_json'](state_path,state)
                try:
                    ns['atomic'](root,NAME,data,before);ns['compare'](root,expected)
                    result=runtime(ns,web);ns['health'](web)
                    state['status']='DEPLOYED';ns['write_json'](state_path,state)
                except BaseException:
                    ns['restore'](root,{NAME:before});ns['compare'](root,manifest['files'])
                    state['status']='AUTO_ROLLED_BACK';ns['write_json'](state_path,state)
                    raise
            elif action=='verify':
                assert state and state['status']=='DEPLOYED','NOT_DEPLOYED'
                ns['compare'](root,expected);result=runtime(ns,web);ns['health'](web)
            else:
                assert state and state['status']=='DEPLOYED','NOT_DEPLOYED'
                ns['compare'](root,expected)
                backup=(STATE/'code-backup.json').read_bytes()
                assert ns['sha'](backup)==state['before_sha256'],'BACKUP_HASH'
                ns['restore'](root,json.loads(backup));ns['compare'](root,manifest['files']);ns['health'](web)
                state['status']='ROLLED_BACK';ns['write_json'](state_path,state)
                result={'result':'PASS','status':'ROLLED_BACK'}
    result.update({'release':'M091-C01','environment':'production','action':action,'code_files':1})
    output=INBOX/('m091-c01-'+action+'.json')
    fd=os.open(output,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'w') as out:
        json.dump(result,out);out.flush();os.fchown(out.fileno(),1002,1002)
    print(json.dumps(result))

if __name__=='__main__':main()
