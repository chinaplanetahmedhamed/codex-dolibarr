<?php
/** Transactional local DB integration test for M036 Shipping Orders projections. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M036_RUNTIME=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1); define('NOCSRFCHECK', 1);
$root = getenv('M036_DOL_DOCUMENT_ROOT');
if (!$root) $root = '/var/www/html';
$_SERVER['DOCUMENT_ROOT'] = rtrim($root, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/custom/chinaplanetoperations/class/OperationsService.class.php';
global $db, $conf;

function m036Fail($message) { throw new RuntimeException($message); }
function m036Quote($db, $value) { return "'".$db->escape((string) $value)."'"; }
function m036Delta($after, $before, $key) { return (int)$after[$key] - (int)$before[$key]; }

$started = false;
try {
    $entity = (int) $conf->entity;
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$entity." AND status=1 AND client IN (1,3) ORDER BY rowid LIMIT 2");
    $socids = array(); while ($res && ($row=$db->fetch_object($res))) $socids[]=(int)$row->rowid;
    if (count($socids) < 2) m036Fail('TWO_CUSTOMERS_REQUIRED');
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity IN (0,".$entity.") AND statut=1 ORDER BY admin DESC,rowid LIMIT 1");
    $creator = $res ? $db->fetch_object($res) : null; if (!$creator) m036Fail('INTERNAL_USER_REQUIRED');
    $service = new OperationsService($db);
    $before = $service->shippingOrderStats();
    $customerBefore = $service->shippingOrderStats($socids[0], true);
    $token = 'M036-'.strtoupper(bin2hex(random_bytes(4)));
    $now = $db->idate(dol_now());
    $db->begin(); $started = true;

    $fixtures = array(
        array('suffix'=>'ACTIVE','stage'=>'new','socid'=>$socids[0],'service'=>array('booking_status'=>'under_review'),'booking'=>true),
        array('suffix'=>'CONFIRMED','stage'=>'published','socid'=>$socids[0],'service'=>array('booking_status'=>'confirmed','shipping_order_no'=>'SO-'.$token),'booking'=>true),
        array('suffix'=>'INCOMPLETE','stage'=>'published','socid'=>$socids[0],'service'=>array('booking_status'=>'confirmed'),'booking'=>true),
        array('suffix'=>'CLOSED','stage'=>'completed','socid'=>$socids[0],'service'=>array('booking_status'=>'confirmed','carrier_booking_no'=>'CB-'.$token),'booking'=>true),
        array('suffix'=>'CANCELLED','stage'=>'cancelled','socid'=>$socids[1],'service'=>array('booking_status'=>'cancelled'),'booking'=>true),
        array('suffix'=>'FUTURE','stage'=>'future_stage','socid'=>$socids[1],'service'=>array(),'booking'=>false),
        array('suffix'=>'MISMATCH','stage'=>'new','socid'=>$socids[0],'service'=>array('booking_status'=>'under_review'),'booking'=>true,'booking_socid'=>$socids[1]),
    );
    $operationIds = array();
    foreach ($fixtures as $fixture) {
        $ref = $token.'-'.$fixture['suffix'];
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_operation(entity,ref,service_type,title,fk_soc,priority,stage,progress,waiting_on,next_action,customer_visible,status,fk_user_creat,date_creation) VALUES(".$entity.",".m036Quote($db,$ref).",'shipping_booking',".m036Quote($db,$ref).",".(int)$fixture['socid'].",'normal',".m036Quote($db,$fixture['stage']).",0,'internal','M036 next action',1,1,".(int)$creator->rowid.",".m036Quote($db,$now).")";
        if (!$db->query($sql)) m036Fail('OPERATION_INSERT_'.$db->lasterror());
        $operationId=(int)$db->last_insert_id(MAIN_DB_PREFIX.'cpo_operation'); $operationIds[$fixture['suffix']]=$operationId;
        if (!$db->query("INSERT INTO ".MAIN_DB_PREFIX."cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(".$entity.",".$operationId.",'service',".m036Quote($db,json_encode($fixture['service'])).",".(int)$creator->rowid.")")) m036Fail('SERVICE_DATA_INSERT');
        if (!empty($fixture['booking'])) {
            $bookingSocid = isset($fixture['booking_socid']) ? (int)$fixture['booking_socid'] : (int)$fixture['socid'];
            $snapshot = array('route'=>array('pol'=>'TEST-POL','pod'=>'TEST-POD'),'equipment'=>'40HQ','quantity'=>2,'total'=>8000.00,'currency'=>'USD');
            $hash = hash('sha256',$ref);
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_booking(entity,fk_soc,fk_rate,fk_operation,idempotency_key,request_hash,rate_fingerprint,input_json,snapshot_json,terms_hash,status,fk_user_creat,date_acceptance,date_creation) VALUES(".$entity.",".$bookingSocid.",0,".$operationId.",".m036Quote($db,'key_'.substr($hash,0,60)).",".m036Quote($db,$hash).",".m036Quote($db,$hash).",'{}',".m036Quote($db,json_encode($snapshot)).",".m036Quote($db,$hash).",'received',".(int)$creator->rowid.",".m036Quote($db,$now).",".m036Quote($db,$now).")";
            if (!$db->query($sql)) m036Fail('BOOKING_INSERT_'.$db->lasterror());
        }
    }

    $shipmentRef = $token.'-SHIP';
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetlogistics(ref,entity,fk_soc,fk_user_creat,fk_statut,date_creation) VALUES(".m036Quote($db,$shipmentRef).",".$entity.",".$socids[1].",".(int)$creator->rowid.",0,".m036Quote($db,$now).")";
    if (!$db->query($sql)) m036Fail('SHIPMENT_INSERT_'.$db->lasterror());
    $mismatchShipment=(int)$db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetlogistics');
    if (!$db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET fk_shipment=".$mismatchShipment." WHERE entity=".$entity." AND rowid=".$operationIds['ACTIVE'])) m036Fail('SHIPMENT_LINK_UPDATE');

    $foreignEntity = $entity + 9000;
    $foreignRef = $token.'-FOREIGN';
    if (!$db->query("INSERT INTO ".MAIN_DB_PREFIX."cpo_operation(entity,ref,service_type,title,fk_soc,priority,stage,progress,waiting_on,next_action,customer_visible,status,fk_user_creat,date_creation) VALUES(".$foreignEntity.",".m036Quote($db,$foreignRef).",'shipping_booking',".m036Quote($db,$foreignRef).",".$socids[0].",'normal','new',0,'internal','foreign',1,1,".(int)$creator->rowid.",".m036Quote($db,$now).")")) m036Fail('FOREIGN_INSERT');

    $after = $service->shippingOrderStats();
    $expected = array('total_current'=>7,'active'=>4,'confirmed'=>1,'closed'=>1,'cancelled'=>1);
    foreach ($expected as $key=>$value) if (m036Delta($after,$before,$key)!==$value) m036Fail('BUCKET_DELTA_'.$key.'_'.m036Delta($after,$before,$key));
    if ($after['total_current'] !== $after['active']+$after['confirmed']+$after['closed']+$after['cancelled']) m036Fail('TOTAL_RECONCILIATION');
    $queryStats = $service->shippingOrderStats(0, false, $token);
    foreach ($expected as $key=>$value) if ((int)$queryStats[$key] !== $value) m036Fail('QUERY_STATS_'.$key.'_'.(int)$queryStats[$key]);
    $customerQueryStats = $service->shippingOrderStats($socids[0], true, $token);
    if ((int)$customerQueryStats['total_current'] !== 4 || (int)$customerQueryStats['active'] !== 2 || (int)$customerQueryStats['confirmed'] !== 1 || (int)$customerQueryStats['closed'] !== 1) m036Fail('CUSTOMER_QUERY_STATS');
    $exactQueryStats = $service->shippingOrderStats(0, false, $token.'-CONFIRMED');
    if ((int)$exactQueryStats['total_current'] !== 1 || (int)$exactQueryStats['confirmed'] !== 1) m036Fail('EXACT_QUERY_STATS');

    $allRows = $service->searchShippingOrders(array('q'=>$token,'limit'=>50));
    if (count($allRows)!==7) m036Fail('CURRENT_ENTITY_OR_DUPLICATE_ROWS_'.count($allRows));
    $byRef = array(); foreach ($allRows as $row) $byRef[$row['reference']]=$row;
    if ($byRef[$token.'-CONFIRMED']['lifecycle_bucket']!=='confirmed') m036Fail('CONFIRMED_PREDICATE');
    if ($byRef[$token.'-INCOMPLETE']['lifecycle_bucket']!=='active' || empty($byRef[$token.'-INCOMPLETE']['confirmation_incomplete'])) m036Fail('INCOMPLETE_CONFIRMATION_NOT_ACTIVE');
    if ($byRef[$token.'-FUTURE']['lifecycle_bucket']!=='active') m036Fail('UNKNOWN_STAGE_NOT_ACTIVE');
    if ($byRef[$token.'-FUTURE']['accepted_total']!==null || $byRef[$token.'-FUTURE']['source']!=='manual_legacy') m036Fail('MANUAL_PRICE_FABRICATED');
    if (empty($byRef[$token.'-MISMATCH']['integrity_exception']) || $byRef[$token.'-MISMATCH']['accepted_total']!==null) m036Fail('OWNERSHIP_MISMATCH_NOT_HIDDEN');
    if (empty($byRef[$token.'-ACTIVE']['shipment_integrity_exception']) || $byRef[$token.'-ACTIVE']['shipment_reference']!=='') m036Fail('SHIPMENT_MISMATCH_NOT_HIDDEN');
    foreach ($allRows as $row) foreach (array('snapshot_json','service_json','input_json','idempotency_key','rate_fingerprint','terms_hash','forwarder','note_private') as $forbidden) if (array_key_exists($forbidden,$row)) m036Fail('PROJECTION_LEAK_'.$forbidden);

    foreach (array('active'=>4,'confirmed'=>1,'closed'=>1,'cancelled'=>1) as $bucket=>$count) {
        $bucketRows=$service->searchShippingOrders(array('q'=>$token,'bucket'=>$bucket,'limit'=>50));
        if (count($bucketRows)!==$count) m036Fail('FILTER_'.$bucket.'_'.count($bucketRows));
    }
    $customerRows = $service->searchShippingOrders(array('q'=>$token,'socid'=>$socids[0],'customer_projection'=>true,'limit'=>50));
    if (count($customerRows)!==4) m036Fail('CUSTOMER_PROJECTION_'.count($customerRows));
    foreach ($customerRows as $row) if ($row['reference']===$token.'-MISMATCH') m036Fail('MISMATCH_IN_CUSTOMER_CENTER');
    $customerAfter = $service->shippingOrderStats($socids[0], true);
    if (m036Delta($customerAfter,$customerBefore,'total_current')!==4) m036Fail('CUSTOMER_COUNT_SCOPE');

    $db->rollback(); $started=false;
    $post = $service->searchShippingOrders(array('q'=>$token,'limit'=>50));
    if (count($post)!==0) m036Fail('ROLLBACK_LEFT_FIXTURES');
    echo "M036_RUNTIME=PASS total_delta=7 active=4 confirmed=1 closed=1 cancelled=1 ownership=ISOLATED foreign_entity=EXCLUDED rollback=CLEAN\n";
} catch (Throwable $e) {
    if ($started) $db->rollback();
    fwrite(STDERR, "M036_RUNTIME=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
