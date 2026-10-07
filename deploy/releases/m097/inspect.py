import subprocess,json,os
from pathlib import Path
assert os.geteuid()==0
code="define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);define('NOCSRFCHECK',1);$_SERVER['DOCUMENT_ROOT']='/var/www/html';require '/var/www/html/master.inc.php';require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';$r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=1 AND statut=1 AND entity IN (0,1) AND fk_soc IS NULL ORDER BY rowid LIMIT 1');$a=$r?$db->fetch_object($r):null;$r=$db->query('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'cpo_task');$n=$r?$db->fetch_object($r):null;echo json_encode(['version'=>DOL_VERSION,'entity'=>$conf->entity,'agenda'=>isModEnabled('agenda'),'operations'=>isModEnabled('chinaplanetoperations'),'flag'=>dolibarr_get_const($db,'CPO_BOARDS_ENABLED',1),'hooks'=>dolibarr_get_const($db,'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS',1),'admin'=>$a?(int)$a->rowid:null,'tasks'=>$n?(int)$n->n:null,'details'=>(bool)$db->query('SELECT payload,version FROM '.MAIN_DB_PREFIX.'cpo_task_details LIMIT 0')]);"
for env,web in [('staging','erp-staging-dolibarr-1'),('production','vision_erp')]:
 r=subprocess.run(['docker','exec','-i','--user','www-data',web,'php','-d','display_errors=0','-r',code],capture_output=True)
 assert r.returncode==0
 v=json.loads(r.stdout);v['environment']=env
 Path('/home/codex-deploy/staging-inbox/m097-'+env+'-runtime.json').write_text(json.dumps(v))
 print(json.dumps(v))
