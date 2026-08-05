<?php
/** Local-only DB integration test for M043. Creates and cleans synthetic intake rows. */
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$sourceRoot = dirname(__DIR__, 2);
$documentRoot = getenv('M043_DOL_DOCUMENT_ROOT');
if (!$documentRoot) $documentRoot = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/');
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once $sourceRoot.'/custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php';
require_once $sourceRoot.'/custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php';

function m043RuntimeAssert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$created = array();
$testRateId = 0;
$testBatchId = 0;
try {
    $schema = file_get_contents($sourceRoot.'/custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
    $schema = preg_replace('/^CREATE TABLE llx_/i', 'CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX, $schema);
    m043RuntimeAssert($db->query($schema), 'Could not install local M043 table: '.$db->lasterror());
    $actor = (object) array('id'=>1);
    $hmacKey = str_repeat('h', 32);
    $tokenKey = str_repeat('t', 32);
    $prices = new PublicShippingRateService($db, (int)$conf->entity, $tokenKey);

    $suffix = strtoupper(bin2hex(random_bytes(3)));
    $batchRef = 'M043-'.$suffix;
    $today = date('Y-m-d');
    $now = $db->idate(dol_now());
    $batchSql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_batch(entity,ref,source_name,original_name,file_hash,source_sheet,effective_date,import_mode,replacement_scope,ignore_below,currency_code,total_rows,accepted_rows,ignored_rows,warning_count,status,fk_user_creat,date_creation) VALUES(".(int)$conf->entity.",'".$db->escape($batchRef)."','M043 local test','m043-local-test.xlsx','".hash('sha256',$batchRef)."','M043','".$today."','current','agent',1000,'USD',1,1,0,0,2,1,'".$now."')";
    m043RuntimeAssert($db->query($batchSql), 'Could not create synthetic rate batch: '.$db->lasterror());
    $testBatchId = (int)$db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_batch');
    $pol = 'M043-POL-'.$suffix; $pod = 'M043-POD-'.$suffix;
    $translationColumn = '';
    $translationValue = '';
    $translationResult = $db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate LIKE 'translation_status'");
    if ($translationResult && $db->num_rows($translationResult) === 1) {
        $translationColumn = ',translation_status';
        $translationValue = ",'approved'";
    }
    $rateSql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_rate(entity,fk_batch,source_sheet,source_row,region_label,pol,destination_country,pod,shipping_line,service_type,container_type,cost_price,sale_price,currency_code,valid_from,valid_to".$translationColumn.",public_notes,public_visible,is_current,status,fk_user_creat,date_creation) VALUES(".(int)$conf->entity.",".$testBatchId.",'M043',1,'North Africa','".$db->escape($pol)."','EG','".$db->escape($pod)."','M043 Public Carrier','direct','40HQ',2000,2300,'USD','".date('Y-m-d',strtotime('-1 day'))."','".date('Y-m-d',strtotime('+7 days'))."'".$translationValue.",'Synthetic local-only rate',1,1,1,1,'".$now."')";
    m043RuntimeAssert($db->query($rateSql), 'Could not create synthetic public rate: '.$db->lasterror());
    $testRateId = (int)$db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_rate');
    $routeOptions = $prices->routeOptions(array('country_code'=>'EG'));
    $matchingRoute = false;
    foreach ($routeOptions['routes'] as $route) if ($route['pol'] === $pol && $route['pod'] === $pod) $matchingRoute = true;
    m043RuntimeAssert($matchingRoute, 'Synthetic published lane missing from dependent route options.');
    $trend = $prices->priceTrend(array('country_code'=>'EG','pol'=>$pol,'pod'=>$pod,'container_type'=>'40HQ'));
    m043RuntimeAssert(!empty($trend['points']) && (float)$trend['points'][count($trend['points'])-1]['price'] === 2300.0, 'Synthetic public price trend missing.');
    $public = $prices->search(array('pol'=>$pol, 'pod'=>$pod, 'container_type'=>'40HQ'));
    m043RuntimeAssert(!empty($public['results'][0]['rate_token']), 'Public rate token not returned.');
    $projection = json_encode($public['results'][0]);
    foreach (array('supplier_cost','cost_price','base_margin','rate_id','fingerprint') as $forbidden) m043RuntimeAssert(strpos($projection, $forbidden)===false, 'Public projection leaked '.$forbidden);

    $intake = new PublicWebsiteRequestService($db, (int)$conf->entity, $hmacKey, $prices);
    $key = 'm043-runtime-'.bin2hex(random_bytes(8));
    $quote = array(
        'request_type'=>'quote_request', 'idempotency_key'=>$key, 'locale'=>'ar',
        'contact'=>array('name'=>'M043 Test Customer','phone'=>'+201000000000','email'=>'m043@example.invalid','country_code'=>'EG'),
        'details'=>array('service'=>'sourcing','message'=>'Synthetic local test','product_summary'=>'Test product','quantity'=>10,'target_country'=>'EG'),
    );
    $first = $intake->create($quote, $actor); $created[] = (int)$first['id'];
    $duplicate = $intake->create($quote, $actor);
    m043RuntimeAssert($duplicate['duplicate']===true && $duplicate['ref']===$first['ref'], 'Identical retry was not idempotent.');
    $conflict = $quote; $conflict['details']['quantity'] = 11;
    try { $intake->create($conflict, $actor); throw new RuntimeException('Changed idempotent payload accepted.'); }
    catch (PublicWebsiteRequestException $e) { m043RuntimeAssert($e->getHttpStatus()===409, 'Changed payload did not return conflict.'); }

    $booking = $intake->create(array(
        'request_type'=>'shipping_booking', 'idempotency_key'=>'m043-ship-'.bin2hex(random_bytes(8)), 'locale'=>'ar',
        'contact'=>array('name'=>'M043 Shipping Test','phone'=>'+201000000001','email'=>'m043-shipping@example.invalid','country_code'=>'EG'),
        'rate_token'=>$public['results'][0]['rate_token'],
        'details'=>array('container_quantity'=>2,'cargo_type'=>'normal','dangerous_goods'=>'no','restricted_cargo'=>false,'cargo_summary'=>'Synthetic normal cargo','terms_acknowledged_at'=>gmdate('c')),
    ), $actor);
    $created[] = (int)$booking['id'];
    $stored = $intake->get((int)$booking['id']);
    m043RuntimeAssert((int)$stored['fk_rate']>0 && (float)$stored['snapshot']['total']>0, 'Commercial snapshot was not stored.');
    m043RuntimeAssert($stored['snapshot']['space_confirmation']===false, 'Booking request incorrectly confirms carrier space.');
    echo 'M043_RUNTIME=PASS quote='.$first['ref'].' booking='.$booking['ref'].' rate='.$pol.'-'.$pod.'-40HQ'."\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'M043_RUNTIME=FAIL REASON='.$e->getMessage()."\n");
    $exit = 1;
} finally {
    foreach ($created as $id) $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".(int)$conf->entity." AND rowid=".(int)$id." AND integration_source='chinaplanet-main-website'");
    if ($testRateId) $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".(int)$conf->entity." AND rowid=".$testRateId." AND source_sheet='M043'");
    if ($testBatchId) $db->query("DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE entity=".(int)$conf->entity." AND rowid=".$testBatchId." AND source_name='M043 local test'");
}
exit(isset($exit) ? $exit : 0);
