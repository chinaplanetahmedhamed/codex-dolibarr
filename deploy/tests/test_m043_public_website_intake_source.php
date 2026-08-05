<?php
/** Source-contract test for M043 public website intake and FCL booking request. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) { $data=file_get_contents($root.'/'.$path); if($data===false) throw new RuntimeException('Unreadable '.$path); return $data; };
$assert = function ($condition, $message) { if(!$condition) throw new RuntimeException($message); };
try {
    $rate = $read('custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php');
    $rateApi = $read('custom/chinaplanetshippingprices/api/class/api_chinaplanetshippingprices.class.php');
    $rateApiEntrypoint = $read('custom/chinaplanetshippingprices/class/api_chinaplanetshippingprices.class.php');
    $intake = $read('custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php');
    $intakeApi = $read('custom/chinaplanetoperations/api/class/api_chinaplanetoperations.class.php');
    $intakeApiEntrypoint = $read('custom/chinaplanetoperations/class/api_chinaplanetoperations.class.php');
    $schema = $read('custom/chinaplanetoperations/sql/llx_cpo_public_request.sql');
    $module = $read('custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php');
    $page = $read('custom/chinaplanetoperations/operations/public_requests.php');

    foreach (array('supplier_cost','cost_price','base_margin','source_sheet','source_row','batch_ref','rule_id','rate_id','fingerprint') as $forbidden) {
        $projectionStart = strpos($rate, 'private function publicProjection');
        $projectionEnd = strpos($rate, 'private function encryptToken');
        $projection = substr($rate, $projectionStart, $projectionEnd - $projectionStart);
        $assert(strpos($projection, $forbidden) === false, 'public rate projection leaks '.$forbidden);
    }
    $assert(strpos($rate, "array('20GP', '40GP', '40HQ')") !== false, 'FCL allowlist absent');
    $assert(strpos($rate, "getDolGlobalString('CHINAPLANET_PUBLIC_RATE_TOKEN_KEY')") !== false, 'server-side rate token constant fallback absent');
    $assert(strpos($rate, 'RESULT_LIMIT = 20') !== false, 'public result cap absent');
    $assert(strpos($rate, "'aes-256-gcm'") !== false && strpos($rate, 'TOKEN_TTL = 600') !== false, 'authenticated short-lived rate token absent');
    $assert(strpos($rateApi, "hasRight('chinaplanetshippingprices', 'website_rate_search')") !== false, 'dedicated rate permission absent');
    $assert(strpos($rateApi, '@url GET website-route-options') !== false, 'public dependent route options endpoint absent');
    $assert(strpos($rateApi, '@url GET website-rate-trend') !== false, 'public reference trend endpoint absent');
    $assert(strpos($rateApiEntrypoint, "api/class/api_chinaplanetshippingprices.class.php") !== false, 'Dolibarr shipping API router entrypoint absent');
    $assert(strpos($rate, 'getPublicRouteOptions') !== false, 'route options do not use the eligible published-rate source');
    $assert(strpos($rate, 'getPublicPriceTrend') !== false, 'trend does not use published shipping-rate history');
    $assert(strpos($intakeApi, "hasRight('chinaplanetoperations', 'website_intake_create')") !== false, 'dedicated intake permission absent');
    $assert(strpos($intakeApi, 'public function websiteRequests()') !== false, 'intake endpoint must not expose a Restler-mapped body parameter');
    $assert(strpos($intakeApi, "file_get_contents('php://input'") !== false, 'intake endpoint does not read its bounded JSON body explicitly');
    $assert(strpos($intakeApiEntrypoint, "api/class/api_chinaplanetoperations.class.php") !== false, 'Dolibarr intake API router entrypoint absent');
    $assert(strpos($intake, "getDolGlobalString('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY')") !== false, 'server-side intake HMAC constant fallback absent');
    $assert(strpos($module, "'api' => 1") !== false, 'operations API module flag absent');
    foreach (array('website_intake_create','publicrequest_read','publicrequest_update','publicrequest_promote') as $right) $assert(strpos($module, $right) !== false, 'missing right '.$right);
    $assert(strpos($schema, 'UNIQUE KEY uk_cpo_public_request_idempotency (entity, integration_source, request_type, idempotency_key)') !== false, 'idempotency uniqueness absent');
    $assert(strpos($schema, 'contact_hmac') !== false && strpos($schema, 'party_json') !== false && strpos($schema, 'request_json') !== false, 'PII separation schema absent');
    $assert(strpos($intake, "customer_visible'=>0") !== false, 'promoted operation is customer visible');
    $assert(strpos($intake, "'shipping_inquiry' : 'shipping_booking'") !== false, 'restricted cargo promotion gate absent');
    $assert(strpos($intake, "addImmutableSection(\$operationId, 'public_rate'") !== false, 'immutable public price evidence absent');
    $assert(strpos($intake, 'idempotency_conflict') !== false && strpos($intake, 'hash_equals') !== false, 'idempotency conflict handling absent');
    $assert(strpos($intake, 'RETENTION_DAYS = 180') !== false && strpos($intake, 'date_anonymized IS NULL') !== false, 'restartable retention policy absent');
    $assert(strpos($page, "cpo_require(\$user, 'publicrequest_read')") !== false, 'staff request page permission absent');
    foreach (array('new Facture(', 'new Commande(', 'new Propal(', 'new Expedition(') as $forbiddenObject) $assert(stripos($intake, $forbiddenObject) === false, 'automatic Dolibarr business object creation found '.$forbiddenObject);
    echo "M043_SOURCE=PASS public_projection=ALLOWLIST idempotency=STRICT promotion=MANUAL retention=180D\n";
} catch(Throwable $e) { fwrite(STDERR,"M043_SOURCE=FAIL REASON=".$e->getMessage()."\n"); exit(1); }
