#!/usr/bin/env python3
"""Pinned M108-M115 feature-only release. No core/service/access changes.
Rollback restores code/feature flags only; all schema and business writes remain.
"""
import fcntl,hashlib,importlib.util,json,os,stat,subprocess,sys,tarfile,time
from pathlib import Path
BASE=Path('/root/m107_release.py')
BASE_SHA='b0e0eda8bc1cda77c14ebf1e8451ed05ae492519376e83329359109092289662'
STATE=Path('/root/chinaplanetos-m108-m115-20261025')
ARTIFACT='m108-m115-boards.tar.gz'
ARTIFACT_SHA='a2739eca3d3155d4f56fc4d475eb6847e30a3945e7326c13b2b8da06ccb62183'
MANIFEST_SHA='7f54a775d707790605166672a8b6e26dd336fdf3bad1c740fa3641047d58a9fa'
FLAGS=['CPO_PROJECT_TASK_BRIDGE_ENABLED','CPO_WORKSPACE_GALLERY_ENABLED','CPO_BOARD_DELETE_ENABLED','CPO_BOARD_COMPLETION_ENABLED','CPO_BOARDS_SHORTCUT_ENABLED']
TABLES=['cpo_project_task','cpo_customer_workspace_meta','cpo_customer_workspace_pref','cpo_board_deleted','cpo_board_completion','cpo_customer_workspace_appearance']
CORE_PINS={'projet/class/task.class.php': 'efcd0d3a0aea351a5b663cf5cfab1eb6c3a940fa28ab0ee37cc35c1d921fadc9', 'projet/class/project.class.php': 'b77612b38fc7830ad4a9c8439b42e92629faecf06fd106b4effcf74a491896d4'}
m=None

def need(v,msg):
    if not v:raise RuntimeError(msg)
def sha(b):return hashlib.sha256(b).hexdigest()
def load_base():
    global m
    need(sha(BASE.read_bytes())==BASE_SHA,'Baseline helper identity mismatch')
    spec=importlib.util.spec_from_file_location('baseline',BASE);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);m.topology()
    for name,pin in CORE_PINS.items():need(m.run(['docker','exec','vision_erp','sha256sum','/var/www/html/'+name]).decode().split()[0]==pin,'Native core source mismatch: '+name)
def payload():
    p=m.guarded(m.INBOX/ARTIFACT);need(sha(p.read_bytes())==ARTIFACT_SHA,'Artifact pin mismatch');data={}
    with tarfile.open(p,'r:gz') as t:
        need(len(t.getmembers())==37,'Artifact entry count mismatch')
        for x in t.getmembers():
            need(x.isfile() and x.size<2_000_000 and not x.name.startswith('/') and '..' not in Path(x.name).parts and x.name not in data,'Unsafe artifact entry')
            data[x.name]=t.extractfile(x).read()
    raw=data.pop('manifest.json');need(sha(raw)==MANIFEST_SHA,'Manifest pin mismatch');manifest=json.loads(raw)
    need(len(manifest['files'])==36 and set(data)=={x['path'] for x in manifest['files']},'Unexpected source set')
    for x in manifest['files']:
        need(x['path'].startswith('custom/chinaplanetoperations/') and sha(data[x['path']])==x['sha256'],'Payload file mismatch')
    return manifest,data

def check_files(manifest,after=False):
    for x in manifest['files']:
        p=m.guarded(m.ROOT/x['path'].removeprefix('custom/'));actual=sha(p.read_bytes()) if p.exists() else None
        need(actual==x['sha256' if after else 'before_sha256'],'Source drift: '+x['path'])
    for name,pin in manifest['preserved_files'].items():
        p=m.guarded(m.ROOT/name);actual=sha(p.read_bytes()) if p.exists() else None
        need(actual==pin,'Preserved dependency drift: '+name)

def extra():
    return m.php(m.CONNECT+r'''
$out=['protected'=>[],'settings'=>[],'tables'=>[]];$c->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
foreach(['projet_task','projet_task_extrafields','societe_extrafields','cpo_project_board','cpo_task_details','cpo_activity','cpo_board_request','cpo_board_pref','usergroup','usergroup_user'] as $n){$r=$c->query('SELECT * FROM `'.$p.$n.'` ORDER BY rowid');if(!$r)exit(9);$h=hash_init('sha256');$count=0;while($v=$r->fetch_assoc()){hash_update($h,json_encode($v,JSON_THROW_ON_ERROR)."\n");$count++;}$out['protected'][$n]=['rows'=>$count,'sha256'=>hash_final($h)];}
$r=$c->query("SELECT rowid,name,value,entity FROM `".$p."const` WHERE entity=1 AND name IN ('CPO_PROJECT_TASK_BRIDGE_ENABLED','CPO_WORKSPACE_GALLERY_ENABLED','CPO_BOARD_DELETE_ENABLED','CPO_BOARD_COMPLETION_ENABLED','CPO_BOARDS_SHORTCUT_ENABLED','MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS') ORDER BY name");if(!$r)exit(10);while($v=$r->fetch_assoc())$out['settings'][$v['name']]=$v;
foreach(['cpo_project_task','cpo_customer_workspace_meta','cpo_customer_workspace_pref','cpo_board_deleted','cpo_board_completion','cpo_customer_workspace_appearance'] as $n){$r=$c->query("SHOW TABLE STATUS WHERE Name='".$p.$n."'");if(!$r)exit(11);$v=$r->fetch_assoc();$out['tables'][$n]=$v?['engine'=>$v['Engine']]:null;}$c->rollback();echo json_encode($out,JSON_THROW_ON_ERROR);
''')
def snapshot():
    a=m.snapshot();b=extra();a['protected'].update(b['protected']);a['settings']=b['settings'];a['new_tables']=b['tables'];return a

def flag(value):
    return m.php("$_SERVER['DOCUMENT_ROOT']='/var/www/html';$_SERVER['SERVER_NAME']='erp.visiontrading.biz';$_SERVER['HTTP_HOST']='erp.visiontrading.biz';require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';foreach("+php_array(FLAGS)+" as $n){if(dolibarr_set_const($db,$n,'"+str(value)+"','chaine',0,'',1)<=0)exit(12);}echo json_encode(['flags'=>"+str(value)+"]);")
def php_array(items):return '['+','.join("'"+x+"'" for x in items)+']'
def migrate():
    return m.php("$_SERVER['DOCUMENT_ROOT']='/var/www/html';$_SERVER['SERVER_NAME']='erp.visiontrading.biz';$_SERVER['HTTP_HOST']='erp.visiontrading.biz';require '/var/www/html/master.inc.php';foreach("+php_array(['InternalTaskProjectTaskMigration','InternalTaskWorkspaceGalleryMigration','InternalTaskBoardLifecycleMigration','InternalTaskCompletionMigration','InternalTaskWorkspaceAppearanceMigration','InternalTaskBoardsShortcutMigration'])+" as $n){require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/'.$n.'.class.php';$x=new $n($db,1);$x->apply();$x->apply();}echo json_encode(['migrations_twice'=>'PASS']);")
def verify_schema():
    # apply() is intentionally not invoked by independent verify.
    return m.php(m.CONNECT+r'''
$checks=['cpo_project_task'=>['entity','fk_task','fk_project','fk_native_task'],'cpo_customer_workspace_meta'=>['entity','fk_soc','priority','version'],'cpo_customer_workspace_pref'=>['entity','fk_soc','fk_user','starred','position'],'cpo_board_deleted'=>['entity','fk_board','date_deleted'],'cpo_board_completion'=>['entity','fk_board','completed'],'cpo_customer_workspace_appearance'=>['entity','fk_soc','background','mime_type','image_hash','image_data','version']];foreach($checks as $n=>$cols){$r=$c->query('SELECT '.implode(',',$cols).' FROM `'.$p.$n.'` LIMIT 0');if(!$r)exit(13);}$r=$c->query("SELECT value FROM `".$p."const` WHERE name='MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS' AND entity=1");$v=$r?$r->fetch_assoc():null;if(!$v||strpos($v['value'],'leftblock')===false||strpos($v['value'],'pricesuppliercard')===false||strpos($v['value'],'thirdpartyconsumption')===false)exit(14);echo json_encode(['schema'=>'PASS','existing_sourcing_hooks_preserved'=>true]);
''')
def atomic(p,b,uid=33,gid=33,mode=0o644):
    m.guarded(p);q=p.with_name(p.name+'.m108-tmp');need(not q.exists(),'Existing temp path');q.write_bytes(b);os.chown(q,uid,gid);os.chmod(q,mode);os.replace(q,p)
def restore_settings(before):
    # Restore only settings owned by this release. Never change any native rights.
    body=m.CONNECT+"$settings=json_decode(base64_decode('"+__import__('base64').b64encode(json.dumps(before['settings']).encode()).decode()+"'),true);$c->begin_transaction();"
    body+=r"""$old=$settings['MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS']??null;if(!$old)exit(18);$r=$c->query("SELECT value FROM `".$p."const` WHERE entity=1 AND name='MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS' FOR UPDATE");$current=$r?$r->fetch_assoc():null;$hooks=json_decode($old['value'],true);if(is_array($hooks)){$hooks[]='leftblock';$expected=json_encode($hooks);}else{$expected=$old['value'].':leftblock';}if(!$current||!in_array($current['value'],[$old['value'],$expected],true))exit(19);"""
    body+="foreach("+php_array(FLAGS+["MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS"])+" as $n){$old=$settings[$n]??null;if($old){$stmt=$c->prepare('UPDATE `'.$p.'const` SET value=? WHERE entity=1 AND name=?');$stmt->bind_param('ss',$old['value'],$n);if(!$stmt->execute())exit(15);}else{$stmt=$c->prepare('DELETE FROM `'.$p.'const` WHERE entity=1 AND name=?');$stmt->bind_param('s',$n);if(!$stmt->execute())exit(16);}}if(!$c->commit())exit(17);echo json_encode(['settings_restored'=>true]);"
    return m.php(body)
def rollback(manifest):
    state=json.loads((STATE/'apply-started.json').read_text());meta=json.loads((STATE/'metadata.json').read_text())
    # Refuse human/other-writer drift; never overwrite unknown source.
    for x in manifest['files']:
        p=m.guarded(m.ROOT/x['path'].removeprefix('custom/'));a=sha(p.read_bytes()) if p.exists() else None
        need(a in (x['before_sha256'],x['sha256']),'Rollback refuses human source drift: '+x['path'])
    flag(0)
    for x in manifest['files']:
        if x['before_sha256'] is None:continue # additive files remain inert
        b=(STATE/'code'/x['path']).read_bytes();need(sha(b)==x['before_sha256'],'Backup pin mismatch');atomic(m.ROOT/x['path'].removeprefix('custom/'),b,**meta[x['path']])
    restore_settings(state['before']);return {'result':'M108_M115_COMPATIBLE_ROLLBACK','business_and_new_schema_retained':True,'database_restored':False}
def receipt(out):
    out.update(time_utc=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),target='srv1011043/vision_erp',production_core_upgrade=False,access_changes=False)
    raw=json.dumps(out,indent=2);(STATE/(out['result']+'.json')).write_text(raw);p=m.INBOX/'m108-m115-release-receipt.json';p.write_text(raw);os.chmod(p,0o644);print(json.dumps(out))
def health():need(m.run(['curl','--silent','--show-error','--max-time','15','--output','/dev/null','--write-out','%{http_code}','https://erp.visiontrading.biz/']).decode()=='200','Native web health failed')
def main():
    action=sys.argv[1];need(action in ['preflight','apply','verify','rollback'],'Unsupported action');load_base();manifest,data=payload();STATE.mkdir(exist_ok=True,mode=0o700)
    with (STATE/'release.lock').open('w') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='rollback':receipt(rollback(manifest));return
        if action=='verify':
            check_files(manifest,True);verify_schema();s=snapshot();need(all(s['settings'].get(k,{}).get('value')=='1' for k in FLAGS),'Feature flags not active');health();receipt({'result':'M108_M115_INDEPENDENT_VERIFY_PASS','commit':manifest['commit'],'artifact_sha256':ARTIFACT_SHA,'runtime_files':36,'native_core_pins':CORE_PINS,'schema_tables':TABLES,'read_only':True});return
        check_files(manifest);before=snapshot();need(all(any(v['name']==name and v['value']=='1' and v['entity']=='1' for v in before['flags']) for name in ['CPO_BOARDS_ENABLED','CPO_CUSTOMER_WORKSPACES_ENABLED','CPO_SHIPMENT_CARDS_ENABLED','MAIN_MODULE_CHINAPLANETOPERATIONS']),'Current module prerequisites not active');need(all(v is None for v in before['new_tables'].values()),'Existing schema requires reconciliation');need(all(before['settings'].get(k,{}).get('value','0')=='0' for k in FLAGS),'Existing enabled feature requires reconciliation');need(before['version']=='24.0.1','Wrong production core')
        if action=='preflight':
            need(not (STATE/'apply-started.json').exists(),'Apply previously attempted; reconcile before retry');(STATE/'preflight.json').write_text(json.dumps(before));receipt({'result':'M108_M115_PREFLIGHT_PASS','commit':manifest['commit'],'artifact_sha256':ARTIFACT_SHA,'runtime_files':36,'preserved_files':len(manifest['preserved_files']),'core':before['version'],'protected':before['protected']});return
        need((STATE/'preflight.json').exists() and not (STATE/'apply-started.json').exists(),'Fresh preflight required; apply cannot replay');meta={}
        for x in manifest['files']:
            p=m.ROOT/x['path'].removeprefix('custom/')
            if p.exists():
                st=p.stat();meta[x['path']]={'uid':st.st_uid,'gid':st.st_gid,'mode':stat.S_IMODE(st.st_mode)};q=STATE/'code'/x['path'];q.parent.mkdir(parents=True,exist_ok=True);q.write_bytes(p.read_bytes())
        (STATE/'metadata.json').write_text(json.dumps(meta));db=before['database'];need(db.replace('_','').isalnum(),'Unsafe database identity');dump=STATE/'before-full-database.sql';need(not dump.exists(),'Existing backup; reconcile before retry')
        command='set -eu; export MYSQL_PWD="${MYSQL_ROOT_PASSWORD:-${MARIADB_ROOT_PASSWORD:-}}"; if command -v mariadb-dump >/dev/null; then exec mariadb-dump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; else exec mysqldump --user=root --single-transaction --quick --skip-lock-tables --routines --triggers "$1"; fi'
        with dump.open('xb') as out:
            p=subprocess.run(['docker','exec','vision_db','sh','-c',command,'m108',db],stdout=out,stderr=subprocess.PIPE,timeout=180);need(p.returncode==0 and dump.stat().st_size>1000,'Full database backup failed')
        need(b'-- Dump completed' in dump.read_bytes()[-4096:],'Incomplete database dump');os.chmod(dump,0o600);backup=sha(dump.read_bytes());confhash=m.run(['docker','exec','vision_erp','sha256sum','/var/www/html/conf/conf.php']).decode().split()[0]
        # Capture a fresh protected baseline after the potentially long backup.
        before=snapshot();(STATE/'apply-started.json').write_text(json.dumps({'before':before,'backup_sha256':backup,'conf_sha256':confhash,'commit':manifest['commit']}))
        try:
            check_files(manifest)
            for x in manifest['files']:atomic(m.ROOT/x['path'].removeprefix('custom/'),data[x['path']],**meta.get(x['path'],{}))
            for x in manifest['files']:
                if x['path'].endswith('.php'):m.run(['docker','exec','vision_erp','php','-l','/var/www/html/'+x['path']])
            migrate();verify_schema();flag(1);check_files(manifest,True);after=snapshot();need(before['protected']==after['protected'],'Protected records changed; retain writes and restore code');need(confhash==m.run(['docker','exec','vision_erp','sha256sum','/var/www/html/conf/conf.php']).decode().split()[0],'Configuration changed');m.topology();health()
            receipt({'result':'M108_M115_FEATURE_APPLIED','commit':manifest['commit'],'artifact_sha256':ARTIFACT_SHA,'runtime_files':36,'backup_path':str(STATE),'full_database_backup_sha256':backup,'protected_records_preserved':True,'native_projects_tasks_and_rights_preserved':True,'additive_tables':TABLES,'existing_sourcing_hooks_preserved':True,'authenticated_live_acceptance':'PENDING'})
        except Exception:
            receipt(rollback(manifest));raise
if __name__=='__main__':
    try:main()
    except Exception as e:print('M108_M115_STOP '+str(e),file=sys.stderr);sys.exit(1)
