<?php
/**
 * Synthetic backup-erp acceptance scenarios for M020-C03/M023/M024/M025.
 * The tool is intentionally unusable unless the staging-only environment gate
 * is present. It never creates credentials and attaches data only to an
 * existing external demo customer.
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M024_DEMO=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('inspect', 'apply', 'cleanup'), true)) { fwrite(STDERR, "M024_DEMO=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }
if (getenv('M024_DEMO_ALLOWED') !== 'backup-erp') { fwrite(STDERR, "M024_DEMO=FAIL REASON=STAGING_GATE_REQUIRED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M024_DOL_DOCUMENT_ROOT');
if (!is_string($documentRoot) || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M024_DEMO=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
global $db, $conf;

const M024_DEMO_PREFIX = 'M024-DEMO-';
const M024_DEMO_PROJECT = 'M024-DEMO-PROJECT';
const M024_DEMO_SOURCE = 'm024_demo';

function m024DemoFail($reason)
{
    global $db;
    if (is_object($db)) $db->rollback();
    fwrite(STDERR, 'M024_DEMO=FAIL REASON='.$reason."\n");
    exit(1);
}
function m024DemoEsc($value) { global $db; return "'".$db->escape((string) $value)."'"; }
function m024DemoQuery($sql, $reason) { global $db; $result = $db->query($sql); if (!$result) m024DemoFail($reason); return $result; }
function m024DemoTable($suffix)
{
    global $db;
    $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), MAIN_DB_PREFIX.$suffix);
    $result = $db->query("SHOW TABLES LIKE '".$db->escape($pattern)."'");
    return $result && $db->num_rows($result) === 1;
}
function m024DemoCount($table, $condition)
{
    global $db;
    $result = $db->query('SELECT COUNT(*) AS total FROM '.MAIN_DB_PREFIX.$table.' WHERE '.$condition);
    $row = $result ? $db->fetch_object($result) : null;
    return $row ? (int) $row->total : 0;
}
function m024DemoLastId($table) { global $db; return (int) $db->last_insert_id(MAIN_DB_PREFIX.$table); }
function m024DemoRemoveTree($path)
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) { if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname()); }
    @rmdir($path);
}
function m024DemoContext()
{
    global $db;
    $login = getenv('M024_DEMO_CUSTOMER_LOGIN');
    if (!is_string($login) || !preg_match('/^[A-Za-z0-9._-]{2,64}$/', $login)) m024DemoFail('CUSTOMER_LOGIN_REQUIRED');
    $result = m024DemoQuery('SELECT rowid,entity,fk_soc,admin,statut FROM '.MAIN_DB_PREFIX.'user WHERE login='.m024DemoEsc($login).' LIMIT 1', 'CUSTOMER_LOOKUP_FAILED');
    $customer = $db->fetch_object($result);
    if (!$customer || (int) $customer->fk_soc <= 0 || (int) $customer->admin !== 0 || (int) $customer->statut !== 1) m024DemoFail('EXTERNAL_CUSTOMER_NOT_READY');
    $result = m024DemoQuery('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE entity='.(int) $customer->entity.' AND admin=1 AND statut=1 ORDER BY rowid LIMIT 1', 'ADMIN_LOOKUP_FAILED');
    $admin = $db->fetch_object($result);
    if (!$admin) m024DemoFail('ADMIN_NOT_READY');
    return array(
        'entity' => (int) $customer->entity,
        'socid' => (int) $customer->fk_soc,
        'customer' => (object) array('id'=>(int)$customer->rowid, 'socid'=>(int)$customer->fk_soc, 'admin'=>0),
        'staff' => (object) array('id'=>(int)$admin->rowid),
    );
}
function m024DemoShipmentIds($context)
{
    global $db;
    $ids = array();
    $result = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref LIKE '.m024DemoEsc(M024_DEMO_PREFIX.'%'));
    while ($result && ($row = $db->fetch_object($result))) $ids[] = (int) $row->rowid;
    return $ids;
}
function m024DemoCleanup($context)
{
    global $db;
    $ids = m024DemoShipmentIds($context);
    if ($ids) {
        $list = implode(',', $ids);
        m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'cpl_shipment_customer_event WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND fk_shipment IN ('.$list.')', 'CLEAN_EVENTS');
        m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'cpl_shipment_public_document WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND fk_shipment IN ('.$list.')', 'CLEAN_PUBLICATIONS');
        foreach ($ids as $id) m024DemoRemoveTree(cpcc_portal_shipment_doc_root($id));
        m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND rowid IN ('.$list.')', 'CLEAN_SHIPMENTS');
    }
    m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'cpcc_financial_entry WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND source_type='.m024DemoEsc(M024_DEMO_SOURCE), 'CLEAN_FINANCE');
    m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'commande WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref LIKE '.m024DemoEsc(M024_DEMO_PREFIX.'%'), 'CLEAN_ORDERS');
    m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'facture WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref LIKE '.m024DemoEsc(M024_DEMO_PREFIX.'%'), 'CLEAN_INVOICES');
    m024DemoQuery('DELETE FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref='.m024DemoEsc(M024_DEMO_PROJECT), 'CLEAN_PROJECT');
}
function m024DemoProject($context)
{
    global $db;
    $now = gmdate('Y-m-d H:i:s');
    m024DemoQuery('INSERT INTO '.MAIN_DB_PREFIX.'projet(fk_soc,datec,dateo,datee,ref,entity,title,description,fk_user_creat,public,fk_statut,note_public) VALUES('.(int)$context['socid'].','.m024DemoEsc($now).','.m024DemoEsc(gmdate('Y-m-d')).','.m024DemoEsc(gmdate('Y-m-d', time()+86400*90)).','.m024DemoEsc(M024_DEMO_PROJECT).','.(int)$context['entity'].','.m024DemoEsc('Shipment collaboration acceptance cases').','.m024DemoEsc('Synthetic backup-erp scenarios only').','.(int)$context['staff']->id.',0,1,'.m024DemoEsc('Demo project for shipment documents, gallery, receipts and balances').')', 'CREATE_PROJECT');
    return m024DemoLastId('projet');
}
function m024DemoShipment($context, $projectId, $code, $status, $offset)
{
    $ref = M024_DEMO_PREFIX.$code;
    $now = gmdate('Y-m-d H:i:s');
    m024DemoQuery('INSERT INTO '.MAIN_DB_PREFIX.'chinaplanetlogistics(ref,entity,fk_soc,fk_project,fk_commande,fk_user_creat,container_no,container_type,nb_containers,booking_no,bl_no,shipping_line,vessel_name,voyage_no,port_loading,port_destination,date_etd,date_atd,date_eta,date_ata,freetime_days,freetime_start,freetime_end,fk_statut,customer_note,date_creation) VALUES('.m024DemoEsc($ref).','.(int)$context['entity'].','.(int)$context['socid'].','.(int)$projectId.',0,'.(int)$context['staff']->id.','.m024DemoEsc('M024'.$offset.'DEMO').',\'40HQ\',1,'.m024DemoEsc('BK-'.$code).','.m024DemoEsc('BL-'.$code).',\'Demo Line\',\'MV Demo\','.m024DemoEsc('VY-'.$offset).',\'Shenzhen\',\'Alexandria\','.m024DemoEsc(gmdate('Y-m-d', time()-86400*(20-$offset))).','.m024DemoEsc(gmdate('Y-m-d', time()-86400*(19-$offset))).','.m024DemoEsc(gmdate('Y-m-d', time()+86400*$offset)).','.($status >= 5 ? m024DemoEsc(gmdate('Y-m-d', time()-86400)) : 'NULL').',14,'.m024DemoEsc(gmdate('Y-m-d', time()+86400*$offset)).','.m024DemoEsc(gmdate('Y-m-d', time()+86400*($offset+14))).','.(int)$status.','.m024DemoEsc('Synthetic '.$code.' acceptance case').','.m024DemoEsc($now).')', 'CREATE_SHIPMENT_'.$code);
    $id = m024DemoLastId('chinaplanetlogistics');
    return (object) array('id'=>$id, 'rowid'=>$id, 'ref'=>$ref, 'entity'=>(int)$context['entity'], 'fk_soc'=>(int)$context['socid'], 'fk_statut'=>(int)$status);
}
function m024DemoWriteFile($shipment, $name, $bytes)
{
    $root = cpcc_portal_shipment_doc_root($shipment->id);
    if (!is_dir($root) && !dol_mkdir($root)) m024DemoFail('CREATE_DOCUMENT_DIRECTORY');
    $path = $root.'/'.$name;
    if (file_put_contents($path, $bytes, LOCK_EX) === false) m024DemoFail('CREATE_FILE');
    @chmod($path, 0640);
    return 'ship-'.$shipment->id.'/'.$name;
}
function m024DemoPublish($context, $shipment, $name, $bytes)
{
    global $db;
    $result = cpcc_portal_gallery_publish($db, $shipment, $context['staff'], m024DemoWriteFile($shipment, $name, $bytes));
    if (empty($result['ok'])) m024DemoFail('PUBLISH_'.$name);
    return $result;
}
function m024DemoItem($context, $shipment, $name)
{
    global $db;
    foreach (cpcc_portal_customer_gallery_items($db, $context['customer'], $shipment) as $item) if ((string)$item->original_name === $name) return $item;
    m024DemoFail('PUBLICATION_NOT_VISIBLE_'.$name);
}
function m024DemoResponse($context, $shipment, $name, $status, $note = '')
{
    global $db;
    $item = m024DemoItem($context, $shipment, $name);
    $result = cpcc_portal_customer_document_response($db, $context['customer'], $shipment, $item->public_token, $status, $note);
    if (empty($result['ok'])) m024DemoFail('DOCUMENT_RESPONSE_'.$status);
}
function m024DemoFinance($context, $projectId)
{
    $now = gmdate('Y-m-d H:i:s');
    $entries = array(
        array('customer_receipt','C','Customer payment received',5000,'USD','PAYMENT-RECEIVED'),
        array('service_charge','D','ChinaPlanet service charge',1200,'USD','SERVICE-CHARGE'),
        array('supplier_payment','D','Supplier payment allocation',18000,'CNY','SUPPLIER-PAYMENT'),
    );
    foreach ($entries as $index=>$entry) {
        m024DemoQuery('INSERT INTO '.MAIN_DB_PREFIX.'cpcc_financial_entry(entity,fk_soc,fk_project,entry_date,entry_type,direction,description,amount,currency_code,reference,source_type,source_id,customer_visible,status,note_private,fk_user_creat,date_creation) VALUES('.(int)$context['entity'].','.(int)$context['socid'].','.(int)$projectId.','.m024DemoEsc(gmdate('Y-m-d H:i:s', time()+$index)).','.m024DemoEsc($entry[0]).','.m024DemoEsc($entry[1]).','.m024DemoEsc($entry[2]).','.(float)$entry[3].','.m024DemoEsc($entry[4]).','.m024DemoEsc(M024_DEMO_PREFIX.$entry[5]).','.m024DemoEsc(M024_DEMO_SOURCE).','.($index+1).',1,1,'.m024DemoEsc('Synthetic backup-erp UI scenario; not an accounting posting').','.(int)$context['staff']->id.','.m024DemoEsc($now).')', 'CREATE_FINANCE_'.$index);
    }
    foreach (array(array('ORDER-DRAFT',0),array('ORDER-VALID',1),array('ORDER-DONE',3)) as $order) {
        m024DemoQuery('INSERT INTO '.MAIN_DB_PREFIX.'commande(ref,entity,fk_soc,fk_projet,date_creation,date_commande,fk_user_author,fk_statut,total_ht,total_ttc,multicurrency_code,multicurrency_total_ht,multicurrency_total_ttc) VALUES('.m024DemoEsc(M024_DEMO_PREFIX.$order[0]).','.(int)$context['entity'].','.(int)$context['socid'].','.(int)$projectId.','.m024DemoEsc($now).','.m024DemoEsc(gmdate('Y-m-d')).','.(int)$context['staff']->id.','.(int)$order[1].',1000,1000,\'USD\',1000,1000)', 'CREATE_'.$order[0]);
    }
    $invoices = array(
        array('INV-DRAFT',0,0,gmdate('Y-m-d', time()+86400*30)),
        array('INV-OVERDUE',1,0,gmdate('Y-m-d', time()-86400*10)),
        array('INV-PAID',2,1,gmdate('Y-m-d', time()-86400*20)),
    );
    foreach ($invoices as $invoice) {
        m024DemoQuery('INSERT INTO '.MAIN_DB_PREFIX.'facture(ref,entity,type,fk_soc,datec,datef,paye,total_ht,total_ttc,fk_statut,fk_user_author,fk_projet,fk_cond_reglement,date_lim_reglement,multicurrency_code,multicurrency_total_ht,multicurrency_total_ttc) VALUES('.m024DemoEsc(M024_DEMO_PREFIX.$invoice[0]).','.(int)$context['entity'].',0,'.(int)$context['socid'].','.m024DemoEsc($now).','.m024DemoEsc(gmdate('Y-m-d')).','.(int)$invoice[2].',750,750,'.(int)$invoice[1].','.(int)$context['staff']->id.','.(int)$projectId.',1,'.m024DemoEsc($invoice[3]).',\'USD\',750,750)', 'CREATE_'.$invoice[0]);
    }
}
function m024DemoInspect($context)
{
    $shipmentCondition = 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref LIKE '.m024DemoEsc(M024_DEMO_PREFIX.'%');
    $counts = array(
        'projects'=>m024DemoCount('projet', 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref='.m024DemoEsc(M024_DEMO_PROJECT)),
        'shipments'=>m024DemoCount('chinaplanetlogistics', $shipmentCondition),
        'documents'=>m024DemoCount('cpl_shipment_public_document', 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND fk_shipment IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE '.$shipmentCondition.')'),
        'events'=>m024DemoCount('cpl_shipment_customer_event', 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND fk_shipment IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE '.$shipmentCondition.')'),
        'orders'=>m024DemoCount('commande', 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref LIKE '.m024DemoEsc(M024_DEMO_PREFIX.'%')),
        'invoices'=>m024DemoCount('facture', 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND ref LIKE '.m024DemoEsc(M024_DEMO_PREFIX.'%')),
        'financial_entries'=>m024DemoCount('cpcc_financial_entry', 'entity='.(int)$context['entity'].' AND fk_soc='.(int)$context['socid'].' AND source_type='.m024DemoEsc(M024_DEMO_SOURCE)),
    );
    echo 'M024_DEMO_INSPECT=PASS COUNTS='.json_encode($counts, JSON_UNESCAPED_SLASHES)."\n";
    return $counts;
}

foreach (array('projet','chinaplanetlogistics','cpl_shipment_public_document','cpl_shipment_customer_event','cpcc_financial_entry','commande','facture') as $table) if (!m024DemoTable($table)) m024DemoFail('REQUIRED_TABLE_MISSING_'.$table);
if (!cpcc_portal_gallery_schema_ready($db) || !cpcc_portal_collaboration_schema_ready($db)) m024DemoFail('SHIPMENT_COLLABORATION_SCHEMA_NOT_READY');
$context = m024DemoContext();
if ($argv[1] === 'inspect') { m024DemoInspect($context); exit(0); }
if ($argv[1] === 'cleanup') { $db->begin(); m024DemoCleanup($context); $db->commit(); m024DemoInspect($context); echo "M024_DEMO_CLEANUP=PASS\n"; exit(0); }

$db->begin();
m024DemoCleanup($context);
$projectId = m024DemoProject($context);
$pending = m024DemoShipment($context, $projectId, 'DOC-PENDING', 2, 7);
$amend = m024DemoShipment($context, $projectId, 'DOC-AMEND', 2, 8);
$confirmed = m024DemoShipment($context, $projectId, 'DOC-CONFIRMED', 3, 9);
$delivery = m024DemoShipment($context, $projectId, 'DELIVERY-AWAIT', 6, 1);
$received = m024DemoShipment($context, $projectId, 'RECEIVED', 7, 0);
$pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
m024DemoPublish($context, $pending, 'Packing-List-Pending.pdf', $pdf.'pending');
m024DemoPublish($context, $pending, 'Container-Loading.png', $png);
m024DemoPublish($context, $amend, 'Bill-of-Lading-Amendment.pdf', $pdf.'amendment');
m024DemoResponse($context, $amend, 'Bill-of-Lading-Amendment.pdf', 'amendment_requested', 'Please correct the consignee name before final issue.');
m024DemoPublish($context, $confirmed, 'Commercial-Invoice-Confirmed.pdf', $pdf.'confirmed');
m024DemoResponse($context, $confirmed, 'Commercial-Invoice-Confirmed.pdf', 'confirmed');
m024DemoPublish($context, $confirmed, 'Container-Seal.png', $png);
m024DemoPublish($context, $delivery, 'Original-Documents-Await-Receipt.pdf', $pdf.'delivery');
foreach (array('preparing','finished','government_uploaded','sent_dhl','in_transit','delivered') as $status) {
    $result = cpcc_portal_staff_original_document_status($db, $context['staff'], $delivery, $status, 'Synthetic original document timeline: '.$status);
    if (empty($result['ok'])) m024DemoFail('ORIGINAL_DOCUMENT_'.$status);
}
m024DemoPublish($context, $received, 'Delivered-Document-Pack.pdf', $pdf.'received');
foreach (array('preparing','finished','government_uploaded','sent_dhl','in_transit','delivered') as $status) {
    $result = cpcc_portal_staff_original_document_status($db, $context['staff'], $received, $status, 'Synthetic received case: '.$status);
    if (empty($result['ok'])) m024DemoFail('RECEIVED_TIMELINE_'.$status);
}
$result = cpcc_portal_customer_container_received($db, $context['customer'], $received, 'Container received in good condition.');
if (empty($result['ok'])) m024DemoFail('CONTAINER_RECEIPT');
$result = cpcc_portal_customer_original_documents_received($db, $context['customer'], $received, 'Original document package received.');
if (empty($result['ok'])) m024DemoFail('ORIGINAL_RECEIPT');
m024DemoFinance($context, $projectId);
$db->commit();
$counts = m024DemoInspect($context);
foreach (array('projects'=>1,'shipments'=>5,'documents'=>7,'orders'=>3,'invoices'=>3,'financial_entries'=>3) as $key=>$expected) if ((int)$counts[$key] !== $expected) m024DemoFail('POST_APPLY_COUNT_'.$key);
echo "M024_DEMO_APPLY=PASS\n";
