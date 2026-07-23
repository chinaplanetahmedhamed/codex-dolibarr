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

$createdOperation = 0; $createdBooking = 0;
try {
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity=".(int)$conf->entity." AND fk_soc IS NOT NULL AND fk_soc>0 AND statut=1 ORDER BY rowid LIMIT 1");
    $u = $res ? $db->fetch_object($res) : null;
    if (!$u) m035Fail('NO_EXTERNAL_USER');
    $customer = new User($db); if ($customer->fetch((int)$u->rowid) <= 0) m035Fail('USER_FETCH_FAILED');
    if (empty($customer->rights->chinaplanetshippingprices)) $customer->rights->chinaplanetshippingprices = new stdClass();
    $customer->rights->chinaplanetshippingprices->read = 1;
    $customer->rights->chinaplanetshippingprices->booking_submit = 1;

    $sql = "SELECT r.rowid,r.pol,r.destination_country,r.pod,r.shipping_line,r.container_type,r.service_type FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch AND b.entity=r.entity AND b.status=2 WHERE r.entity=".(int)$conf->entity." AND r.status=1 AND r.is_current=1 AND r.public_visible=1 AND r.container_type IN ('20GP','40GP','40HQ') AND r.valid_from<=CURDATE() AND COALESCE(r.valid_to,DATE_ADD(b.effective_date,INTERVAL 30 DAY))>=CURDATE() ORDER BY r.sale_price LIMIT 1";
    $res = $db->query($sql); $candidate = $res ? $db->fetch_object($res) : null; if (!$candidate) m035Fail('NO_BOOKABLE_RATE');
    $filters = array('pol'=>$candidate->pol,'country_code'=>$candidate->destination_country,'pod'=>$candidate->pod,'shipping_line'=>$candidate->shipping_line,'container_type'=>$candidate->container_type,'service_type'=>$candidate->service_type,'incoterm'=>'FOB','cargo_type'=>'normal','documents'=>array());
    $prices = new ShippingPriceService($db); $rate = $prices->getCustomerRateById((int)$candidate->rowid, $filters); if (!$rate) m035Fail('SAFE_RATE_PROJECTION_EMPTY');
    $forbidden = json_encode($rate); foreach (array('cost_price','supplier_cost','base_margin','source_sheet','source_row','batch_ref','rule_id','internal_notes','route_detail') as $needle) if (strpos($forbidden,$needle)!==false) m035Fail('RATE_LEAK_'.$needle);

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
    $retry=$bookings->submit($customer,$posted);if(empty($retry['duplicate'])||$retry['reference']!==$created['reference'])m035Fail('IDEMPOTENCY_FAILED');
    $changed=$posted;$changed['cargo_description']='Different request';$conflict=false;try{$bookings->submit($customer,$changed);}catch(Throwable$e){$conflict=true;}if(!$conflict)m035Fail('IDEMPOTENCY_CONFLICT_MISSING');
    $res=$db->query("SELECT data_json FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation." AND section_code='service'");$section=$res?$db->fetch_object($res):null;if(!$section||strpos($section->data_json,'MUST_NOT_PERSIST')!==false||strpos($section->data_json,'forwarder')!==false)m035Fail('UNSAFE_OPERATION_PAYLOAD');
    $after = array('propal'=>m035Count($db,'propal'),'commande'=>m035Count($db,'commande'),'facture'=>m035Count($db,'facture'),'paiement'=>m035Count($db,'paiement')); if($before!==$after)m035Fail('ACCOUNTING_MUTATION');
    echo "M035_BOOKING=PASS booking=".$createdBooking." operation=".$created['reference']." idempotency=PASS ownership=PASS safe_projection=PASS accounting=UNCHANGED\n";
} catch (Throwable $e) {
    fwrite(STDERR,"M035_BOOKING=FAIL REASON=".$e->getMessage()."\n"); $exit=1;
} finally {
    if ($createdOperation>0 && getenv('M035_KEEP_FIXTURE') !== '1') {
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_activity WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".(int)$conf->entity." AND fk_operation=".$createdOperation);
        if ($createdBooking>0) $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_booking WHERE entity=".(int)$conf->entity." AND rowid=".$createdBooking." AND fk_operation=".$createdOperation);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".(int)$conf->entity." AND rowid=".$createdOperation." AND title LIKE 'Customer FCL booking:%'");
    }
}
exit(isset($exit)?$exit:0);
