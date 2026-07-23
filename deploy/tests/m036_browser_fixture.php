<?php
/** Seed or remove a deterministic, local-only M036 browser QA fixture. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M036_BROWSER_FIXTURE=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1); define('NOCSRFCHECK', 1);
$root = getenv('M036_DOL_DOCUMENT_ROOT');
if (!$root) $root = '/var/www/html';
$_SERVER['DOCUMENT_ROOT'] = rtrim($root, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
global $db, $conf;

$action = isset($argv[1]) ? (string) $argv[1] : '';
$token = 'M036-BROWSER-QA';

function m036BrowserQuote($db, $value) { return "'".$db->escape((string) $value)."'"; }
function m036BrowserFail($message) { throw new RuntimeException($message); }
function m036BrowserOperationIds($db, $entity, $token)
{
    $ids = array();
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int)$entity." AND ref LIKE ".m036BrowserQuote($db, $token.'-%'));
    while ($res && ($row = $db->fetch_object($res))) $ids[] = (int) $row->rowid;
    return $ids;
}
function m036BrowserCleanup($db, $entity, $token)
{
    $ids = m036BrowserOperationIds($db, $entity, $token);
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        foreach (array(
            "DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking WHERE entity=".(int)$entity." AND fk_operation IN (".$in.")",
            "DELETE FROM ".MAIN_DB_PREFIX."cpo_activity WHERE entity=".(int)$entity." AND fk_operation IN (".$in.")",
            "DELETE FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".(int)$entity." AND fk_operation IN (".$in.")",
            "DELETE FROM ".MAIN_DB_PREFIX."cpo_message WHERE entity=".(int)$entity." AND fk_operation IN (".$in.")",
            "DELETE FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$entity." AND fk_operation IN (".$in.")",
            "DELETE FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int)$entity." AND rowid IN (".$in.") AND ref LIKE ".m036BrowserQuote($db, $token.'-%'),
        ) as $sql) if (!$db->query($sql)) m036BrowserFail('CLEANUP_'.$db->lasterror());
    }
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int)$entity." AND ref LIKE ".m036BrowserQuote($db, $token.'-%'))) m036BrowserFail('SHIPMENT_CLEANUP_'.$db->lasterror());
}

$started = false;
try {
    if (!in_array($action, array('seed','cleanup','status'), true)) m036BrowserFail('ACTION_REQUIRED_seed_cleanup_status');
    $entity = (int) $conf->entity;
    if ($action === 'status') {
        echo 'M036_BROWSER_FIXTURE=STATUS rows='.count(m036BrowserOperationIds($db, $entity, $token))."\n";
        exit(0);
    }

    $db->begin(); $started = true;
    m036BrowserCleanup($db, $entity, $token);
    if ($action === 'cleanup') {
        $db->commit(); $started = false;
        echo "M036_BROWSER_FIXTURE=CLEAN rows=0\n";
        exit(0);
    }

    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$entity." AND status=1 AND client IN (1,3) ORDER BY rowid LIMIT 2");
    $socids = array(); while ($res && ($row=$db->fetch_object($res))) $socids[]=(int)$row->rowid;
    if (count($socids) < 2) m036BrowserFail('TWO_CUSTOMERS_REQUIRED');
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity IN (0,".$entity.") AND statut=1 ORDER BY admin DESC,rowid LIMIT 1");
    $creator = $res ? $db->fetch_object($res) : null; if (!$creator) m036BrowserFail('INTERNAL_USER_REQUIRED');
    $now = $db->idate(dol_now());

    $fixtures = array(
        array('suffix'=>'ACTIVE-LONG','stage'=>'new','socid'=>$socids[0],'service'=>array('booking_status'=>'under_review'),'booking'=>true,'next'=>'Review unusually long bilingual customer cargo instructions / مراجعة تعليمات الشحنة الطويلة مع العميل'),
        array('suffix'=>'CONFIRMED','stage'=>'published','socid'=>$socids[0],'service'=>array('booking_status'=>'confirmed','shipping_order_no'=>'SO-M036-88991'),'booking'=>true,'next'=>'Link confirmed carrier SO to shipment'),
        array('suffix'=>'INCOMPLETE','stage'=>'published','socid'=>$socids[0],'service'=>array('booking_status'=>'confirmed'),'booking'=>true,'next'=>'Carrier confirmation evidence is still incomplete'),
        array('suffix'=>'CLOSED','stage'=>'completed','socid'=>$socids[0],'service'=>array('booking_status'=>'confirmed','carrier_booking_no'=>'CB-M036-77331'),'booking'=>true,'next'=>'Archived after successful operational closure'),
        array('suffix'=>'CANCELLED','stage'=>'cancelled','socid'=>$socids[1],'service'=>array('booking_status'=>'cancelled'),'booking'=>true,'next'=>'Cancelled by approved customer instruction'),
        array('suffix'=>'MANUAL','stage'=>'future_stage','socid'=>$socids[1],'service'=>array(),'booking'=>false,'next'=>'Legacy manual booking without accepted price evidence'),
        array('suffix'=>'OWNER-MISMATCH','stage'=>'new','socid'=>$socids[0],'service'=>array('booking_status'=>'under_review'),'booking'=>true,'booking_socid'=>$socids[1],'next'=>'Investigate booking ownership integrity exception'),
    );
    $operationIds = array();
    foreach ($fixtures as $index=>$fixture) {
        $ref = $token.'-'.$fixture['suffix'];
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_operation(entity,ref,service_type,title,fk_soc,priority,stage,progress,waiting_on,next_action,customer_visible,status,fk_user_creat,date_creation) VALUES(".$entity.",".m036BrowserQuote($db,$ref).",'shipping_booking',".m036BrowserQuote($db,$ref).",".(int)$fixture['socid'].",'normal',".m036BrowserQuote($db,$fixture['stage']).",0,'internal',".m036BrowserQuote($db,$fixture['next']).",1,1,".(int)$creator->rowid.",".m036BrowserQuote($db,$now).")";
        if (!$db->query($sql)) m036BrowserFail('OPERATION_INSERT_'.$db->lasterror());
        $operationId=(int)$db->last_insert_id(MAIN_DB_PREFIX.'cpo_operation'); $operationIds[$fixture['suffix']]=$operationId;
        if (!$db->query("INSERT INTO ".MAIN_DB_PREFIX."cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(".$entity.",".$operationId.",'service',".m036BrowserQuote($db,json_encode($fixture['service'])).",".(int)$creator->rowid.")")) m036BrowserFail('SERVICE_DATA_INSERT');
        if (!empty($fixture['booking'])) {
            $bookingSocid = isset($fixture['booking_socid']) ? (int)$fixture['booking_socid'] : (int)$fixture['socid'];
            $snapshot = array('route'=>array('pol'=>'Guangzhou Nansha International Terminal','pod'=>'SOKHNA Port, Egypt'),'equipment'=>$index % 2 ? '40HQ' : '20GP','quantity'=>$index % 3 + 1,'total'=>7150.00 + ($index * 211.60),'currency'=>$index === 4 ? 'RMB' : 'USD');
            $hash = hash('sha256',$ref);
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_booking(entity,fk_soc,fk_rate,fk_operation,idempotency_key,request_hash,rate_fingerprint,input_json,snapshot_json,terms_hash,status,fk_user_creat,date_acceptance,date_creation) VALUES(".$entity.",".$bookingSocid.",0,".$operationId.",".m036BrowserQuote($db,'m036_browser_'.substr($hash,0,48)).",".m036BrowserQuote($db,$hash).",".m036BrowserQuote($db,$hash).",'{}',".m036BrowserQuote($db,json_encode($snapshot)).",".m036BrowserQuote($db,$hash).",'received',".(int)$creator->rowid.",".m036BrowserQuote($db,$now).",".m036BrowserQuote($db,$now).")";
            if (!$db->query($sql)) m036BrowserFail('BOOKING_INSERT_'.$db->lasterror());
        }
    }

    foreach (array(
        array('suffix'=>'SHIP-OK','socid'=>$socids[0],'operation'=>'CONFIRMED'),
        array('suffix'=>'SHIP-BAD','socid'=>$socids[1],'operation'=>'ACTIVE-LONG'),
    ) as $shipmentFixture) {
        $ref = $token.'-'.$shipmentFixture['suffix'];
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetlogistics(ref,entity,fk_soc,fk_user_creat,fk_statut,date_creation) VALUES(".m036BrowserQuote($db,$ref).",".$entity.",".(int)$shipmentFixture['socid'].",".(int)$creator->rowid.",0,".m036BrowserQuote($db,$now).")";
        if (!$db->query($sql)) m036BrowserFail('SHIPMENT_INSERT_'.$db->lasterror());
        $shipmentId=(int)$db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetlogistics');
        if (!$db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET fk_shipment=".$shipmentId." WHERE entity=".$entity." AND rowid=".(int)$operationIds[$shipmentFixture['operation']])) m036BrowserFail('SHIPMENT_LINK_UPDATE');
    }

    $db->commit(); $started=false;
    echo "M036_BROWSER_FIXTURE=SEEDED token=".$token." rows=7 customer=".$socids[0]."\n";
} catch (Throwable $e) {
    if ($started) $db->rollback();
    fwrite(STDERR, "M036_BROWSER_FIXTURE=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
