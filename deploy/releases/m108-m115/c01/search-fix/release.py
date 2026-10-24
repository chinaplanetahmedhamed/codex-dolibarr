#!/usr/bin/env python3
"""One-file follow-up to the accepted C01 release. No DB/config/access writes."""
import fcntl,hashlib,importlib.util,json,os,stat,sys,time
from pathlib import Path
BASE=Path('/root/m115_c01_release.py');BASE_SHA='da092dc98f8c9e97c06db2faa5323121c724ed93598bc80b7723a4917b971020'
STATE=Path('/root/chinaplanetos-m115-c01-searchfix-20261025')
SOURCE='custom/chinaplanetoperations/js/boards.js';INBOX_FILE='m115-c01-searchfix-boards.js'
BEFORE='f0e12e40e35d44803a576372b3b8c9a90f945071269a1950b81f7c8b51bb3692';AFTER='a107d075c347a2976c215063ab35ace6ebc85e57b80f54ea008d32182ec02a07'
COMMIT='f9e4abadd78f5e1a2db1b5b76b313925f1cc0d4f';c=None
def need(v,m):
 if not v:raise RuntimeError(m)
def sha(b):return hashlib.sha256(b).hexdigest()
def init():
 global c
 need(sha(BASE.read_bytes())==BASE_SHA,'Prior helper drift');s=importlib.util.spec_from_file_location('c01',BASE);c=importlib.util.module_from_spec(s);s.loader.exec_module(c);c.init()
def check(after=False):
 main,_=c.r.payload();prior,_=c.payload();pins={x['path']:x['sha256'] for x in prior['files']}
 need(pins[SOURCE]==BEFORE,'Prior release drift')
 for x in main['files']:
  if x['path'] in pins:x['sha256']=pins[x['path']]
  if after and x['path']==SOURCE:x['sha256']=AFTER
 c.r.check_files(main,True)
def target():return c.r.m.guarded(c.r.m.ROOT/SOURCE.removeprefix('custom/'))
def data():
 p=c.r.m.guarded(c.r.m.INBOX/INBOX_FILE);b=p.read_bytes();need(len(b)<2_000_000 and sha(b)==AFTER,'Payload drift');return b
def rollback():
 p=target();need(sha(p.read_bytes()) in [BEFORE,AFTER],'Human source drift; rollback refused');b=(STATE/'before.js').read_bytes();need(sha(b)==BEFORE,'Backup drift');m=json.loads((STATE/'metadata.json').read_text());c.r.atomic(p,b,**m);check();return {'result':'M115_C01_SEARCHFIX_ROLLED_BACK','database_restored':False,'business_writes_retained':True}
def receipt(o):
 o.update(commit=COMMIT,target='srv1011043/vision_erp',time_utc=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),files=1,schema_changes=False,config_or_access_changes=False,production_core_upgrade=False)
 raw=json.dumps(o,indent=2);(STATE/(o['result']+'.json')).write_text(raw);p=c.r.m.INBOX/'m115-c01-searchfix-receipt.json';p.write_text(raw);os.chmod(p,0o644);print(raw)
def main():
 action=sys.argv[1];need(action in ['preflight','apply','verify','rollback'],'Invalid action');init();blob=data();STATE.mkdir(mode=0o700,exist_ok=True)
 with (STATE/'release.lock').open('w') as lock:
  fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  if action=='rollback':receipt(rollback());return
  check(action=='verify');c.r.verify_schema();c.r.health()
  if action=='verify':receipt({'result':'M115_C01_SEARCHFIX_VERIFY_PASS','all_runtime_files_verified':36,'read_only':True});return
  need(not (STATE/'apply-started.json').exists(),'Apply attempted; reconcile outcome; replay refused')
  if action=='preflight':(STATE/'preflight.json').write_text(json.dumps(c.snapshot()));receipt({'result':'M115_C01_SEARCHFIX_PREFLIGHT_PASS'});return
  need((STATE/'preflight.json').exists(),'Preflight required');p=target();st=p.stat();(STATE/'before.js').write_bytes(p.read_bytes());os.chmod(STATE/'before.js',0o600);meta={'uid':st.st_uid,'gid':st.st_gid,'mode':stat.S_IMODE(st.st_mode)};(STATE/'metadata.json').write_text(json.dumps(meta));before=c.snapshot();(STATE/'apply-started.json').write_text(json.dumps({'before':before,'before_sha256':BEFORE,'after_sha256':AFTER}))
  try:
   check();c.r.atomic(p,blob,**meta);check(True);need(c.snapshot()==before,'Concurrent data/config change; preserve writes and restore code');c.r.health();c.r.m.topology();receipt({'result':'M115_C01_SEARCHFIX_APPLIED','backup_path':str(STATE),'before_sha256':BEFORE,'after_sha256':AFTER,'records_and_permissions_preserved':True,'customer_disables_performed':0})
  except Exception:receipt(rollback());raise
if __name__=='__main__':
 try:main()
 except Exception as e:print('M115_C01_SEARCHFIX_STOP '+str(e),file=sys.stderr);sys.exit(1)
