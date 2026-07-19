<?php
/**
 * M017 local synthetic fixture seeder.
 *
 * Usage:
 *   M017_DOL_DOCUMENT_ROOT=/path/to/htdocs php deploy/tests/m017_seed_synthetic.php inspect
 *   M017_DOL_DOCUMENT_ROOT=/path/to/htdocs php deploy/tests/m017_seed_synthetic.php apply
 *   M017_DOL_DOCUMENT_ROOT=/path/to/htdocs php deploy/tests/m017_seed_synthetic.php cleanup
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M017_SEED=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('inspect','apply','cleanup'), true)) { fwrite(STDERR, "M017_SEED=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M017_DOL_DOCUMENT_ROOT');
if (!is_string($documentRoot) || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M017_SEED=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot; $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db, $conf;
$fixture = require __DIR__.'/fixtures/m017_synthetic_fixture.php';
$marker = (string) $fixture['marker'];

function m017SeedFail($reason) { global $db; if (is_object($db)) $db->rollback(); fwrite(STDERR, 'M017_SEED=FAIL REASON='.$reason."\n"); exit(1); }
function m017SeedQuery($sql, $reason) { global $db; $result = $db->query($sql); if (!$result) m017SeedFail($reason); return $result; }
function m017SeedEsc($value) { global $db; return "'".$db->escape((string) $value)."'"; }
function m017SeedId($table) { global $db; return (int) $db->last_insert_id(MAIN_DB_PREFIX.$table); }
function m017SeedTable($suffix) { global $db; $result = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$suffix)."'"); return $result && $db->num_rows($result) === 1; }
function m017SeedCount($table, $condition) { global $db; $result = $db->query('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.$table.' WHERE '.$condition); $row = $result ? $db->fetch_object($result) : null; return $row ? (int) $row->total : 0; }
function m017SeedDelete($table, $condition) { if (m017SeedTable($table)) m017SeedQuery('DELETE FROM '.MAIN_DB_PREFIX.$table.' WHERE '.$condition, 'CLEANUP_'.$table); }

function m017SeedRemoveTree($path)
{
	if (!is_dir($path)) return;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $item) { if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname()); }
	@rmdir($path);
}

function m017SeedCleanup($marker)
{
	global $db, $conf;
	$like = m017SeedEsc($marker.'%');
	$socids = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE ref_ext LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $socids[] = (int) $row->rowid;
	$userids = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE ref_ext LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $userids[] = (int) $row->rowid;
	$operations = array(); $result = $db->query('SELECT rowid,entity,ref FROM '.MAIN_DB_PREFIX.'cpo_operation WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $operations[] = array((int) $row->rowid,(int) $row->entity,(string) $row->ref);
	$cards = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpkanban_card WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $cards[] = (int) $row->rowid;
	$documents = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpcc_export_document WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $documents[] = (int) $row->rowid;
	$shipments = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $shipments[] = (int) $row->rowid;
	$projects = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $projects[] = (int) $row->rowid;
	$orders = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'commande WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $orders[] = (int) $row->rowid;
	$invoices = array(); $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'facture WHERE ref LIKE '.$like);
	while ($result && ($row = $db->fetch_object($result))) $invoices[] = (int) $row->rowid;

	if ($socids) {
		$list = implode(',', $socids);
		m017SeedDelete('cpcc_mobile_access_token', 'fk_family IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'cpcc_mobile_token_family WHERE fk_soc IN ('.$list.'))');
		m017SeedDelete('cpcc_mobile_refresh_token', 'fk_family IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'cpcc_mobile_token_family WHERE fk_soc IN ('.$list.'))');
		m017SeedDelete('cpcc_mobile_token_family', 'fk_soc IN ('.$list.')');
		m017SeedDelete('cpcc_mobile_public_id', 'fk_soc IN ('.$list.')');
		m017SeedDelete('cpcc_financial_entry', 'fk_soc IN ('.$list.') AND reference LIKE '.$like);
	}
	if ($operations) {
		$list = implode(',', array_map(function ($item) { return $item[0]; }, $operations));
		m017SeedDelete('cpo_document', 'fk_operation IN ('.$list.')');
		m017SeedDelete('cpo_operation_data', 'fk_operation IN ('.$list.')');
	}
	if ($cards) { $list = implode(',', $cards); m017SeedDelete('cpkanban_asset', 'fk_card IN ('.$list.')'); m017SeedDelete('cpkanban_log', 'fk_card IN ('.$list.')'); }
	if ($shipments) { $list = implode(',', $shipments); m017SeedDelete('cpl_document_requirement', 'fk_shipment IN ('.$list.')'); m017SeedDelete('cpl_shipment_event', 'fk_shipment IN ('.$list.')'); }
	if ($documents) m017SeedDelete('cpcc_export_document_line', 'fk_document IN ('.implode(',', $documents).')');
	if ($orders) m017SeedDelete('commande', 'rowid IN ('.implode(',', $orders).')');
	if ($invoices) m017SeedDelete('facture', 'rowid IN ('.implode(',', $invoices).')');
	m017SeedDelete('cpcc_export_document', 'ref LIKE '.$like);
	m017SeedDelete('cpo_operation', 'ref LIKE '.$like);
	m017SeedDelete('cpkanban_card', 'ref LIKE '.$like);
	m017SeedDelete('chinaplanetlogistics', 'ref LIKE '.$like);
	m017SeedDelete('projet', 'ref LIKE '.$like);
	m017SeedDelete('cpkanban_column', 'code LIKE '.$like);
	m017SeedDelete('user', 'ref_ext LIKE '.$like);
	m017SeedDelete('societe', 'ref_ext LIKE '.$like);
	foreach ($cards as $cardId) m017SeedRemoveTree(DOL_DATA_ROOT.'/cpkanban/card-'.$cardId);
	$operationRoot = !empty($conf->chinaplanetoperations->dir_output) ? (string) $conf->chinaplanetoperations->dir_output : DOL_DATA_ROOT.'/chinaplanetoperations';
	foreach ($operations as $operation) m017SeedRemoveTree(rtrim($operationRoot, '/').'/documents/'.$operation[1].'/'.$operation[2]);
}

function m017SeedColumn($entity, $adminId, $marker)
{
	global $db;
	$code = $marker.'_E'.$entity.'_STEP';
	$result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpkanban_column WHERE entity='.(int) $entity.' AND code='.m017SeedEsc($code).' LIMIT 1');
	$row = $result ? $db->fetch_object($result) : null; if ($row) return (int) $row->rowid;
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpkanban_column(entity,code,label,label_en,color,position,cust_visible,auto_progress,active) VALUES('.(int) $entity.','.m017SeedEsc($code).','.m017SeedEsc('مرحلة تجريبية').','.m017SeedEsc('Synthetic stage').",'#168aad',10,1,55,1)", 'CREATE_COLUMN');
	return m017SeedId('cpkanban_column');
}

function m017SeedCustomer($definition, $adminId, $password, $marker)
{
	global $db, $conf;
	$key = (string) $definition['key']; $entity = (int) $definition['entity']; $now = gmdate('Y-m-d H:i:s');
	$ref = $marker.'_E'.$entity.'_'.$key;
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'societe(nom,entity,ref_ext,status,statut,code_client,client,fournisseur,datec,fk_user_creat,default_lang) VALUES('.m017SeedEsc($definition['company']).','.$entity.','.m017SeedEsc($ref).",1,1,".m017SeedEsc('CU'.$entity.$key).",1,0,".m017SeedEsc($now).','.(int) $adminId.",'ar_EG')", 'CREATE_CUSTOMER');
	$socid = m017SeedId('societe');
	$hash = password_hash($password, PASSWORD_BCRYPT);
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'user(entity,ref_ext,admin,employee,datec,fk_user_creat,login,pass_encoding,pass_crypted,firstname,lastname,email,fk_soc,statut,lang) VALUES('.$entity.','.m017SeedEsc($ref).",0,0,".m017SeedEsc($now).','.(int) $adminId.','.m017SeedEsc($definition['login']).",'bcrypt',".m017SeedEsc($hash).','.m017SeedEsc('Synthetic').','.m017SeedEsc('Customer '.$key).','.m017SeedEsc($definition['login'].'@invalid.example').','.$socid.",1,'ar_EG')", 'CREATE_USER');
	$userId = m017SeedId('user'); $columnId = m017SeedColumn($entity, $adminId, $marker);
	$projectIds = array(); $shipmentIds = array();
	for ($i = 1; $i <= (int) $definition['projects']; $i++) {
		$projectRef = $ref.'_PJ'.$i;
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'projet(fk_soc,datec,dateo,datee,ref,entity,title,description,fk_user_creat,public,fk_statut,note_public) VALUES('.$socid.','.m017SeedEsc($now).','.m017SeedEsc(gmdate('Y-m-d')).','.m017SeedEsc(gmdate('Y-m-d', time()+86400*30)).','.m017SeedEsc($projectRef).','.$entity.','.m017SeedEsc('Synthetic project '.$key.' '.$i).','.m017SeedEsc('M017 synthetic scenario only').','.(int) $adminId.",0,1,".m017SeedEsc('Customer-visible synthetic update').')', 'CREATE_PROJECT');
		$projectId = m017SeedId('projet'); $projectIds[] = $projectId;
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpkanban_card(ref,entity,fk_soc,fk_column,title,description,priority,progress,date_deadline,fk_user_creat,cust_visible,fk_project,position,note_public,status,date_creation) VALUES('.m017SeedEsc($projectRef.'_CARD').','.$entity.','.$socid.','.$columnId.','.m017SeedEsc('Synthetic project card').','.m017SeedEsc('Customer-visible synthetic card').",'med',55,".m017SeedEsc(gmdate('Y-m-d', time()+86400*14)).','.(int) $adminId.',1,'.$projectId.",10,".m017SeedEsc('Synthetic public note').',1,'.m017SeedEsc($now).')', 'CREATE_CARD');
		$cardId = m017SeedId('cpkanban_card');
		if ($i === 1) {
			$dir = DOL_DATA_ROOT.'/cpkanban/card-'.$cardId; if (!is_dir($dir) && !mkdir($dir, 0770, true)) m017SeedFail('CREATE_ASSET_DIR');
			$file = 'm017-project-'.$key.'.txt'; $content = "M017 synthetic project document $key\n"; if (file_put_contents($dir.'/'.$file, $content) === false) m017SeedFail('CREATE_ASSET_FILE');
			m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpkanban_asset(entity,fk_soc,fk_card,asset_type,document_type,original_name,stored_name,mime_type,file_size,caption,version_no,review_status,customer_visible,status,fk_user_creat,date_creation) VALUES('.$entity.','.$socid.','.$cardId.",'document','final',".m017SeedEsc($file).','.m017SeedEsc($file).",'text/plain',".strlen($content).','.m017SeedEsc('Synthetic document').",1,'approved',1,1,".(int) $adminId.','.m017SeedEsc($now).')', 'CREATE_ASSET');
		}
	}
	for ($i = 1; $i <= (int) $definition['shipments']; $i++) {
		$shipmentRef = $ref.'_SH'.$i; $projectId = $projectIds[($i - 1) % count($projectIds)];
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'chinaplanetlogistics(ref,entity,fk_soc,fk_project,fk_commande,fk_user_creat,container_no,container_type,nb_containers,booking_no,bl_no,shipping_line,vessel_name,voyage_no,port_loading,port_destination,date_etd,date_atd,date_eta,freetime_days,fk_statut,customer_note,date_creation) VALUES('.m017SeedEsc($shipmentRef).','.$entity.','.$socid.','.$projectId.',0,'.(int) $adminId.','.m017SeedEsc('M017'.$entity.$key.$i.'CONT').",'40HQ',1,".m017SeedEsc('BK-'.$shipmentRef).','.m017SeedEsc('BL-'.$shipmentRef).','.m017SeedEsc('Synthetic Line').','.m017SeedEsc('MV Synthetic').','.m017SeedEsc('VY-'.$i).','.m017SeedEsc('Shenzhen').','.m017SeedEsc('Alexandria').','.m017SeedEsc(gmdate('Y-m-d', time()+86400*2)).','.m017SeedEsc(gmdate('Y-m-d', time()+86400*3)).','.m017SeedEsc(gmdate('Y-m-d', time()+86400*18)).",21,2,".m017SeedEsc('Synthetic customer shipment note').','.m017SeedEsc($now).')', 'CREATE_SHIPMENT');
		$shipmentId = m017SeedId('chinaplanetlogistics'); $shipmentIds[] = $shipmentId;
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpl_shipment_event(entity,fk_shipment,fk_soc,event_type,milestone_code,event_at,actual_at,source_type,source_label_public,note_public,reason_public,customer_visible,fk_user_creat,date_creation) VALUES('.$entity.','.$shipmentId.','.$socid.",'transshipment','transit',".m017SeedEsc(gmdate('Y-m-d H:i:s', time()+86400*8)).','.m017SeedEsc(gmdate('Y-m-d H:i:s', time()+86400*8)).",'carrier','Synthetic Carrier',".m017SeedEsc('Singapore — Synthetic transit update').','.m017SeedEsc('Synthetic schedule').',1,'.(int) $adminId.','.m017SeedEsc($now).')', 'CREATE_SHIPMENT_EVENT');
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpl_document_requirement(entity,fk_shipment,fk_soc,document_code,label_public,requirement_status,requested_from,due_at,action_required,note_public,customer_visible,fk_user_creat,date_creation) VALUES('.$entity.','.$shipmentId.','.$socid.",'PACKING_LIST','Packing list','requested','customer',".m017SeedEsc(gmdate('Y-m-d H:i:s', time()+86400*5)).",1,'Synthetic requirement',1,".(int) $adminId.','.m017SeedEsc($now).')', 'CREATE_REQUIREMENT');
	}
	for ($i = 1; $i <= (int) $definition['orders']; $i++) {
		$orderRef = $ref.'_SO'.$i; $projectId = $projectIds[($i - 1) % count($projectIds)];
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'commande(ref,entity,fk_soc,fk_projet,date_creation,date_commande,fk_user_author,fk_statut,total_ht,total_ttc,multicurrency_code,multicurrency_total_ht,multicurrency_total_ttc) VALUES('.m017SeedEsc($orderRef).','.$entity.','.$socid.','.$projectId.','.m017SeedEsc($now).','.m017SeedEsc(gmdate('Y-m-d')).','.(int) $adminId.",1,1000,1000,'USD',1000,1000)", 'CREATE_ORDER');
	}
	foreach ((array) $definition['currencies'] as $index => $currency) {
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpcc_financial_entry(entity,fk_soc,fk_project,entry_date,entry_type,direction,description,amount,currency_code,reference,source_type,source_id,customer_visible,status,fk_user_creat,date_creation) VALUES('.$entity.','.$socid.','.$projectIds[0].','.m017SeedEsc(gmdate('Y-m-d H:i:s', time()+$index)).",'synthetic','D','Synthetic customer charge',".(100 + $index).','.m017SeedEsc($currency).','.m017SeedEsc($ref.'_TX'.$index).",'m017_synthetic',".($index+1).',1,1,'.(int) $adminId.','.m017SeedEsc($now).')', 'CREATE_FINANCE');
	}
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'facture(ref,entity,type,fk_soc,datec,datef,paye,total_ht,total_ttc,fk_statut,fk_user_author,fk_projet,fk_cond_reglement,date_lim_reglement,multicurrency_code,multicurrency_total_ht,multicurrency_total_ttc) VALUES('.m017SeedEsc($ref.'_INV').','.$entity.',0,'.$socid.','.m017SeedEsc($now).','.m017SeedEsc(gmdate('Y-m-d')).",0,250,250,1,".(int) $adminId.','.$projectIds[0].',1,'.m017SeedEsc(gmdate('Y-m-d', time()+86400*14)).",'USD',250,250)", 'CREATE_INVOICE');
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpcc_export_document(entity,fk_soc,fk_project,fk_shipment,document_type,ref,document_date,currency_code,total_amount,consignee_name,port_loading,port_destination,incoterm,notes_public,customer_visible,status,fk_user_creat,date_creation) VALUES('.$entity.','.$socid.','.$projectIds[0].','.$shipmentIds[0].",'commercial_invoice',".m017SeedEsc($ref.'_CMD').','.m017SeedEsc(gmdate('Y-m-d')).",'USD',1000,".m017SeedEsc($definition['company']).','.m017SeedEsc('Shenzhen').','.m017SeedEsc('Alexandria').",'FOB',".m017SeedEsc('Synthetic commercial document').',1,1,'.(int) $adminId.','.m017SeedEsc($now).')', 'CREATE_COMMERCIAL');
	$commercialId = m017SeedId('cpcc_export_document');
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpcc_export_document_line(fk_document,position,description,quantity,unit,unit_price,total_amount,date_creation) VALUES('.$commercialId.",1,'Synthetic goods',10,'pcs',100,1000,".m017SeedEsc($now).')', 'CREATE_COMMERCIAL_LINE');
	$operationRef = $ref.'_RPT';
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpo_operation(entity,ref,service_type,title,fk_soc,fk_project,fk_shipment,priority,stage,progress,waiting_on,next_action,date_deadline,note_public,customer_visible,status,fk_user_creat,date_creation) VALUES('.$entity.','.m017SeedEsc($operationRef).",'quality_control','Synthetic quality report',".$socid.','.$projectIds[0].','.$shipmentIds[0].",'normal','completed',100,'none','Review report',".m017SeedEsc(gmdate('Y-m-d H:i:s', time()+86400)).','.m017SeedEsc('Synthetic public report note').',1,1,'.(int) $adminId.','.m017SeedEsc($now).')', 'CREATE_OPERATION');
	$operationId = m017SeedId('cpo_operation');
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES('.$entity.','.$operationId.",'service',".m017SeedEsc(json_encode(array('product_name'=>'Synthetic item','inspection_result'=>'Passed','factory_name'=>'MUST_NOT_EXPOSE'))).','.(int) $adminId.')', 'CREATE_OPERATION_DATA');
	$operationRoot = !empty($conf->chinaplanetoperations->dir_output) ? (string) $conf->chinaplanetoperations->dir_output : DOL_DATA_ROOT.'/chinaplanetoperations'; $dir = rtrim($operationRoot, '/').'/documents/'.$entity.'/'.$operationRef;
	if (!is_dir($dir) && !mkdir($dir, 0770, true)) m017SeedFail('CREATE_REPORT_DIR'); $file = 'm017-report-'.$key.'.txt'; $content = "M017 synthetic report document $key\n"; if (file_put_contents($dir.'/'.$file, $content) === false) m017SeedFail('CREATE_REPORT_FILE');
	m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpo_document(entity,fk_operation,document_type,original_name,stored_name,mime_type,file_size,version_no,visibility,review_status,is_current,fk_user_creat,date_review,date_creation) VALUES('.$entity.','.$operationId.",'report',".m017SeedEsc($file).','.m017SeedEsc($file).",'text/plain',".strlen($content).",1,'customer_after_approval','approved',1,".(int) $adminId.','.m017SeedEsc($now).','.m017SeedEsc($now).')', 'CREATE_REPORT_DOCUMENT');
	return array('socid'=>$socid,'user_id'=>$userId,'entity'=>$entity,'login'=>$definition['login']);
}

function m017SeedNegativeUsers($definitions, $adminId, $socid, $marker)
{
	foreach ($definitions as $definition) {
		$entity = (int) $definition['entity']; $now = gmdate('Y-m-d H:i:s'); $ref = $marker.'_NEG_'.$definition['key']; $hash = password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT); $admin = $definition['kind'] === 'internal' ? 1 : 0; $employee = $definition['kind'] === 'internal' ? 1 : 0; $status = $definition['kind'] === 'inactive' ? 0 : 1; $fkSoc = $definition['kind'] === 'nosoc' || $definition['kind'] === 'internal' ? 'NULL' : (int) $socid;
		m017SeedQuery('INSERT INTO '.MAIN_DB_PREFIX.'user(entity,ref_ext,admin,employee,datec,fk_user_creat,login,pass_encoding,pass_crypted,firstname,lastname,email,fk_soc,statut,lang) VALUES('.$entity.','.m017SeedEsc($ref).','.$admin.','.$employee.','.m017SeedEsc($now).','.(int) $adminId.','.m017SeedEsc($definition['login']).",'bcrypt',".m017SeedEsc($hash).",'Synthetic','Negative',".m017SeedEsc($definition['login'].'@invalid.example').','.$fkSoc.','.$status.",'ar_EG')", 'CREATE_NEGATIVE_USER');
	}
}

function m017SeedInspect($marker)
{
	$like = m017SeedEsc($marker.'%');
	$counts = array(
		'customers'=>m017SeedCount('societe', 'ref_ext LIKE '.$like),
		'users'=>m017SeedCount('user', 'ref_ext LIKE '.$like),
		'projects'=>m017SeedCount('projet', 'ref LIKE '.$like),
		'shipments'=>m017SeedCount('chinaplanetlogistics', 'ref LIKE '.$like),
		'orders'=>m017SeedCount('commande', 'ref LIKE '.$like),
		'reports'=>m017SeedCount('cpo_operation', 'ref LIKE '.$like),
	);
	echo 'M017_SEED_INSPECT=PASS COUNTS='.json_encode($counts, JSON_UNESCAPED_SLASHES)."\n";
}

if ($argv[1] === 'inspect') { m017SeedInspect($marker); exit(0); }
if ($argv[1] === 'cleanup') { $db->begin(); m017SeedCleanup($marker); $db->commit(); m017SeedInspect($marker); echo "M017_SEED_CLEANUP=PASS\n"; exit(0); }

foreach (array('cpcc_mobile_token_family','cpcc_mobile_access_token','cpcc_mobile_refresh_token','cpcc_mobile_public_id','cpcc_mobile_rate_limit','cpcc_financial_entry','cpkanban_column','cpkanban_card','cpkanban_asset','chinaplanetlogistics','cpl_shipment_event','cpl_document_requirement','cpo_operation','cpo_operation_data','cpo_document','cpcc_export_document','cpcc_export_document_line') as $table) if (!m017SeedTable($table)) m017SeedFail('REQUIRED_TABLE_MISSING_'.$table);
$adminResult = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1'); $adminRow = $adminResult ? $db->fetch_object($adminResult) : null; if (!$adminRow) m017SeedFail('LOCAL_ADMIN_MISSING'); $adminId = (int) $adminRow->rowid;
$db->begin(); m017SeedCleanup($marker); $credentials = array(); $firstSocid = 0;
foreach ($fixture['customers'] as $definition) { $password = cpccSeedRandomPassword(); $result = m017SeedCustomer($definition, $adminId, $password, $marker); if (!$firstSocid && (int) $result['entity'] === 1) $firstSocid = (int) $result['socid']; $credentials[] = array('login'=>$result['login'],'password'=>$password,'entity'=>$result['entity']); }
m017SeedNegativeUsers($fixture['negative_users'], $adminId, $firstSocid, $marker); $db->commit();
$credentialFile = getenv('M017_CREDENTIAL_FILE'); if (!is_string($credentialFile) || $credentialFile === '') $credentialFile = '/private/tmp/m017-synthetic-credentials.json';
if (file_put_contents($credentialFile, json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX) === false) m017SeedFail('CREDENTIAL_FILE_WRITE'); @chmod($credentialFile, 0600);
m017SeedInspect($marker); echo 'M017_SEED_APPLY=PASS CREDENTIAL_FILE='.$credentialFile."\n";

function cpccSeedRandomPassword() { return 'M017!'.bin2hex(random_bytes(18)).'aA7'; }
