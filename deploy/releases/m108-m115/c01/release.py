#!/usr/bin/env python3
"""M115-C01: five-file code-only update; no schema, flags, rights or business writes."""
import copy,fcntl,hashlib,importlib.util,json,os,stat,subprocess,sys,tarfile,time
from pathlib import Path
BASE=Path('/root/m108_m115_release.py');BASE_SHA='e679446ba3b74bde881239ce8dc8c6c5945a41fc92b854572244edfa4e0ff2f9'
STATE=Path('/root/chinaplanetos-m115-c01-20261025');ARTIFACT='m115-c01-boards.tar.gz';ARTIFACT_SHA='14ad88c42619484f1e9eb6b168240084c4c67f661263b8cad0fea17bde2d401a';MANIFEST_SHA='f007d23b307ae842aef46d1fbcd4861653016a7ceeca3dc0656eff1a632a4fd4';r=None

def need(v,s):
    if not v:raise RuntimeError(s)
def sha(b):return hashlib.sha256(b).hexdigest()
def init():
    global r
    need(sha(BASE.read_bytes())==BASE_SHA,'Existing release helper changed');spec=importlib.util.spec_from_file_location('m108',BASE);r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r);r.load_base()
def payload():
    p=r.m.guarded(r.m.INBOX/ARTIFACT);need(sha(p.read_bytes())==ARTIFACT_SHA,'Artifact pin mismatch');data={}
    with tarfile.open(p,'r:gz') as t:
        need(len(t.getmembers())==6,'Wrong payload count')
        for x in t.getmembers():
            need(x.isfile() and x.size<2000000 and not x.name.startswith('/') and '..' not in Path(x.name).parts and x.name not in data,'Unsafe entry');data[x.name]=t.extractfile(x).read()
    raw=data.pop('manifest.json');need(sha(raw)==MANIFEST_SHA,'Manifest pin mismatch');m=json.loads(raw);need(len(m['files'])==5 and set(data)=={x['path'] for x in m['files']},'Wrong file allowlist')
    for x in m['files']:need(x['path'].startswith('custom/chinaplanetoperations/') and sha(data[x['path']])==x['sha256'],'File pin mismatch')
    return m,data

def source_check(m,after=False):
    main,_=r.payload();pins={x['path']:x for x in m['files']}
    for x in main['files']:
        if x['path'] in pins:
            need(x['sha256']==pins[x['path']]['before_sha256'],'Wrong prior release identity')
            if after:x['sha256']=pins[x['path']]['sha256']
    r.check_files(main,True)
def snapshot():
    s=r.snapshot();s['new_data']=r.m.php(r.m.CONNECT+r'''
$out=[];$c->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');foreach(['cpo_project_task','cpo_customer_workspace_meta','cpo_customer_workspace_pref','cpo_board_deleted','cpo_board_completion','cpo_customer_workspace_appearance'] as $n){$r=$c->query('SELECT * FROM `'.$p.$n.'` ORDER BY rowid');if(!$r)exit(20);$h=hash_init('sha256');$count=0;while($v=$r->fetch_assoc()){hash_update($h,json_encode($v,JSON_THROW_ON_ERROR)."\n");$count++;}$out[$n]=['rows'=>$count,'sha256'=>hash_final($h)];}$c->rollback();echo json_encode($out,JSON_THROW_ON_ERROR);
''');return s

def rollback(m):
    meta=json.loads((STATE/'metadata.json').read_text())
    for x in m['files']:
        p=r.m.guarded(r.m.ROOT/x['path'].removeprefix('custom/'));need(sha(p.read_bytes()) in [x['before_sha256'],x['sha256']],'Rollback refuses human source drift')
    for x in reversed(m['files']):
        b=(STATE/'code'/x['path']).read_bytes();need(sha(b)==x['before_sha256'],'Backup drift');r.atomic(r.m.ROOT/x['path'].removeprefix('custom/'),b,**meta[x['path']])
    source_check(m);return {'result':'M115_C01_CODE_ROLLED_BACK','database_restored':False,'all_business_writes_retained':True}
def receipt(o):
    o.update(target='srv1011043/vision_erp',time_utc=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),schema_changes=False,config_or_access_changes=False,production_core_upgrade=False)
    raw=json.dumps(o,indent=2);(STATE/(o['result']+'.json')).write_text(raw);p=r.m.INBOX/'m115-c01-release-receipt.json';p.write_text(raw);os.chmod(p,0o644);print(json.dumps(o))
def main():
    a=sys.argv[1];need(a in ['preflight','apply','verify','rollback'],'Invalid action');init();m,data=payload();STATE.mkdir(mode=0o700,exist_ok=True)
    with (STATE/'release.lock').open('w') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if a=='rollback':receipt(rollback(m));return
        source_check(m,a=='verify');r.verify_schema();r.health()
        if a=='verify':receipt({'result':'M115_C01_INDEPENDENT_VERIFY_PASS','commit':m['commit'],'artifact_sha256':ARTIFACT_SHA,'files':5,'all_final_runtime_files_verified':36,'read_only':True});return
        before=snapshot()
        if a=='preflight':need(not (STATE/'apply-started.json').exists(),'Apply already attempted; reconcile outcome');(STATE/'preflight.json').write_text(json.dumps(before));receipt({'result':'M115_C01_PREFLIGHT_PASS','commit':m['commit'],'artifact_sha256':ARTIFACT_SHA,'files':5});return
        need((STATE/'preflight.json').exists() and not (STATE/'apply-started.json').exists(),'Fresh preflight required; replay refused');meta={}
        for x in m['files']:
            p=r.m.guarded(r.m.ROOT/x['path'].removeprefix('custom/'));st=p.stat();meta[x['path']]={'uid':st.st_uid,'gid':st.st_gid,'mode':stat.S_IMODE(st.st_mode)};q=STATE/'code'/x['path'];q.parent.mkdir(parents=True,exist_ok=True);q.write_bytes(p.read_bytes())
        (STATE/'metadata.json').write_text(json.dumps(meta));db=before['database'];need(db.replace('_','').isalnum(),'Unsafe database identity');dump=STATE/'before-full-database.sql';need(not dump.exists(),'Prior backup requires reconciliation')
        command='set -eu; export MYSQL_PWD="${MYSQL_ROOT_PASSWORD:-${MARIADB_ROOT_PASSWORD:-}}"; if command -v mariadb-dump >/dev/null; then exec mariadb-dump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; else exec mysqldump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; fi'
        with dump.open('xb') as out:
            p=subprocess.run(['docker','exec','vision_db','sh','-c',command,'m115c01',db],stdout=out,stderr=subprocess.PIPE,timeout=180);need(p.returncode==0 and dump.stat().st_size>1000,'Backup failed')
        need(b'-- Dump completed' in dump.read_bytes()[-4096:],'Incomplete backup');os.chmod(dump,0o600);backup=sha(dump.read_bytes());before=snapshot();(STATE/'apply-started.json').write_text(json.dumps({'before':before,'backup_sha256':backup,'commit':m['commit']}))
        try:
            source_check(m)
            # Backend first; CSS and JS last. No existing workspace is disabled here.
            for x in m['files']:r.atomic(r.m.ROOT/x['path'].removeprefix('custom/'),data[x['path']],**meta[x['path']])
            for x in m['files']:
                if x['path'].endswith('.php'):r.m.run(['docker','exec','vision_erp','php','-l','/var/www/html/'+x['path']])
            source_check(m,True);after=snapshot();need(before==after,'Data/config changed during update; retain writes and restore code');r.health();r.m.topology()
            receipt({'result':'M115_C01_FEATURE_APPLIED','commit':m['commit'],'artifact_sha256':ARTIFACT_SHA,'files':5,'backup_path':str(STATE),'full_database_backup_sha256':backup,'all_native_and_workspace_records_preserved':True,'team_container_disable_not_access_revocation':True,'actual_customer_disables_performed':0,'authenticated_live_acceptance':'PENDING'})
        except Exception:receipt(rollback(m));raise
if __name__=='__main__':
    try:main()
    except Exception as e:print('M115_C01_STOP '+str(e),file=sys.stderr);sys.exit(1)
