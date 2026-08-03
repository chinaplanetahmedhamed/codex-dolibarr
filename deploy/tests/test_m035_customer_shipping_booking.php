<?php
/** Local DB integration test for M035 customer-safe direct booking. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M035_BOOKING=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1); define('NOCSRFCHECK', 1);
$root = getenv('M035_DOL_DOCUMENT_ROOT');
if (!$root) $root = '/var/www/html';
$_SERVER['DOCUMENT_ROOT'] = rtrim($root, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/user/class/user.class.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/custom/chinaplanetshippingprices/class/ShippingPriceService.class.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/custom/chinaplanetshippingprices/class/CustomerShippingBookingService.class.php';
global $db, $conf;

function m035Fail($message) { throw new RuntimeException($message); }
function m035Count($db, $table) { $res=$db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX.$table); $o=$res?$db->fetch_object($res):null; return $o?(int)$o->total:-1; }

$createdOperation = 0; $createdBooking = 0; $cleanupErrors = array();
try {
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity=".(int)$conf->entity." AND fk_soc IS NOT NULL AND fk_soc>0 AND statut=1 ORDER BY rowid LIMIT 1");
    $u = $res ? $db->fetch_object($res) : null;
    if (!$u) m035Fail('NO_EXTERNAL_USER');
    $customer = new User($db); if ($customer->fetch((int)$u->rowid) <= 0) m035Fail('USER_FETCH_FAILED');
    if (empty($customer->rights->chinaplanetshippingprices)) $customer->rights->chinaplanetshippingprices = new stdClass();
    $customer->rights->chinaplanetshippingprices->read = 1;
    $customer->rights->chinaplanetshippingprices->booking_submit = 1;

    $prices = new ShippingPriceService($db);
    $search = $prices->search(array('incoterm'=>'FOB','cargo_type'=>'normal','documents'=>array()), false, 250);
    if (!empty($search['error'])) m035Fail('RATE_SEARCH_FAILED');
    $rate = !empty($search['results']) ? reset($search['results']) : null;
    if (!$rate) m035Fail('NO_BOOKABLE_RATE');
    $candidate = (object) array(
        'rowid'=>$rate['rate_id'],
        'pol'=>$rate['pol'],
        'destination_country'=>$rate['country_code'],
        'pod'=>$rate['pod'],
        'shipping_line'=>$rate['shipping_line'],
        'container_type'=>$rate['container_type'],
        'service_type'=>$rate['service_type'],
    );
    $filters = array('pol'=>$candidate->pol,'country_code'=>$candidate->destination_country,'pod'=>$candidate->pod,'shipping_line'=>$candidate->shipping_line,'container_type'=>$candidate->container_type,'service_type'=>$candidate->service_type,'incoterm'=>'FOB','cargo_type'=>'normal','documents'=>array());
    $rate = $prices->getCustomerRateById((int)$candidate->rowid, $filters); if (!$rate) m035Fail('SAFE_RATE_REVALIDATION_EMPTY');
    $forbidden = json_encode($rate); foreach (array('cost_price','supplier_cost','base_margin','source_sheet','source_row','batch_ref','rule_id','internal_notes','route_detail','source_notes','source_notes_raw','notes_en','translation_status','translation_provider','translation_model','translation_error','fk_user_translation_review') as $needle) if (strpos($forbidden,$needle)!==false) m035Fail('RATE_LEAK_'.$needle);

    $before = array('propal'=>m035Count($db,'propal'),'commande'=>m035Count($db,'commande'),'facture'=>m035Count($db,'facture'),'paiement'=>m035Count($db,'paiement'));
    $key = 'm035test_'.bin2hex(random_bytes(12));
    $posted = $filters + array(
        'rate_id'=>$candidate->rowid,'rate_fingerprint'=>$rate['fingerprint'],'idempotency_key'=>$key,
        'container_quantity'=>2,'cargo_ready_date'=>date('Y-m-d',strtotime('+3 days')),'cargo_description'=>'M035 synthetic general merchandise',
        'hs_code'=>'TEST-0000','shipper'=>'Synthetic test shipper','consignee'=>'Synthetic test consignee','notify_party'=>'','customer_instruction'=>'M035 automated local test','accept_terms'=>1,
        'entity'=>999,'fk_soc'=>999,'price'=>'0.01','currency'=>'CNY','forwarder'=>'MUST_NOT_PERSIST',
    );
    $bookings = new CustomerShippingBookingService($db); $created = $bookings->submit($customer, $posted);
    if (empty($created['id']) || empty($created['reference'])) m035Fail('CREATE_FAILED');
    $createdBooking=(int)$created['id'];
    $res=$db->query("SELECT fk_operation,fk_soc,snapshot_json FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking WHERE entity=".(int)$conf->entity." AND rowid=".$createdBooking);$row=$res?$db->fetch_object($res):null;if(!$row)m035Fail('BOOKING_ROW_MISSING');$createdOperation=(int)$row->fk_operation;
    if((int)$row->fk_soc!==(int)$customer->socid)m035Fail('SESSION_SOCID_NOT_AUTHORITATIVE');
    $snapshot=json_decode($row->snapshot_json,true);if((int)$snapshot['quantity']!==2||abs((float)$snapshot['total']-(2*(float)$snapshot['unit_price']))>.001)m035Fail('PRICE_ARITHMETIC_FAILED');
    $snapshotJson=json_encode($snapshot);foreach(array('source_notes','source_notes_raw','notes_en','translation_status','translation_provider','translation_model','translation_error','supplier_cost','base_margin','internal_notes')as$needle)if(strpos($snapshotJson,$needle)!==false)m035Fail('SNAPSHOT_LEAK_'.$needle);
    $retry=$bookings->submit($customer,$posted);if(empty($retry['duplicate'])||$retry['reference']!==$created['reference'])m035Fail('IDEMPOTENCY_FAILED');
    $changed=$posted;$changed['cargo_description']='Different request';$conflict=false;try{$bookings->submit($customer,$changed);}catch(Throwable$e){$conflict=true;}if(!$conflict)m035Fail('IDEMPOTENCY_CONFLICT_MISSING');
    $res=$db->query("SELECT data_json FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation." AND section_code='service'");$section=$res?$db->fetch_object($res):null;if(!$section||strpos($section->data_json,'MUST_NOT_PERSIST')!==false||strpos($section->data_json,'forwarder')!==false)m035Fail('UNSAFE_OPERATION_PAYLOAD');
    $after = array('propal'=>m035Count($db,'propal'),'commande'=>m035Count($db,'commande'),'facture'=>m035Count($db,'facture'),'paiement'=>m035Count($db,'paiement')); if($before!==$after)m035Fail('ACCOUNTING_MUTATION');
    echo "M035_BOOKING=PASS booking=".$createdBooking." operation=".$created['reference']." idempotency=PASS ownership=PASS safe_projection=PASS accounting=UNCHANGED\n";
} catch (Throwable $e) {
    fwrite(STDERR,"M035_BOOKING=FAIL REASON=".$e->getMessage()."\n"); $exit=1;
} finally {
    if ($createdOperation>0 && getenv('M035_KEEP_FIXTURE') !== '1') {
        $deletes = array(
            'activity'=>"DELETE FROM ".MAIN_DB_PREFIX."cpo_activity WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
            'task'=>"DELETE FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
            'operation_data'=>"DELETE FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
        );
        if ($createdBooking>0) $deletes['booking']="DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking WHERE entity=".(int)$conf->entity." AND rowid=".$createdBooking." AND fk_operation=".$createdOperation;
        $deletes['operation']="DELETE FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int)$conf->entity." AND rowid=".$createdOperation." AND title LIKE 'Customer FCL booking:%'";
        foreach ($deletes as $name=>$sql) if (!$db->query($sql)) $cleanupErrors[]='DELETE_'.$name;
        $residualChecks = array(
            'activity'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_activity WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
            'task'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
            'operation_data'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
            'booking'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation,
            'operation'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int)$conf->entity." AND rowid=".$createdOperation,
        );
        foreach ($residualChecks as $name=>$sql) {
            $res=$db->query($sql); $row=$res?$db->fetch_object($res):null;
            if (!$row || (int)$row->total !== 0) $cleanupErrors[]='RESIDUAL_'.$name;
        }
    }
}
if (!empty($cleanupErrors)) {
    fwrite(STDERR, "M035_BOOKING=FAIL REASON=CLEANUP_".implode('_', $cleanupErrors)."\n");
    $exit=1;
}
exit(isset($exit)?$exit:0);
