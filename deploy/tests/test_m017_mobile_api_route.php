<?php
$root = dirname(__DIR__, 2);
$api = json_decode(file_get_contents($root.'/docs/API/mobile-v1.openapi.json'), true);
$source = file_get_contents($root.'/custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php');
$failures = array();
function m017_route_assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
preg_match_all('/@url\s+(GET|POST)\s+\/mobile\/v1([^\s*]*)/', (string) $source, $matches, PREG_SET_ORDER);
$actual = array();
foreach ($matches as $match) $actual[] = $match[1].' '.($match[2] === '' ? '/' : $match[2]);
$expected = array();
foreach ($api['paths'] as $path => $methods) foreach ($methods as $method => $definition) if (in_array($method, array('get','post'), true)) $expected[] = strtoupper($method).' '.$path;
sort($actual); sort($expected);
m017_route_assert($actual === $expected, 'Restler annotations exactly match frozen OpenAPI methods and paths');
m017_route_assert(strpos((string) $source, '@class DolibarrApiAccess') === false, 'Extension Bearer routes do not use Dolibarr master API-key authentication');
m017_route_assert(strpos((string) $source, "@url POST /mobile/v1/auth/logout/revoke") !== false && strpos((string) $source, 'http_response_code(204)') !== false, 'Logout follows frozen 204 contract');
m017_route_assert(strpos((string) $source, "Defaults::\$composeClass = 'CpccMobileApiCompose'") !== false, 'Errors use the extension envelope composer');

function m017_http_call($method, $url, $body = null, $bearer = '', $extraHeaders = array(), $rawBody = null)
{
	$handle = curl_init($url); $headers = array('Accept: application/json');
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true); curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method); curl_setopt($handle, CURLOPT_HEADER, true); curl_setopt($handle, CURLOPT_TIMEOUT, 10);
	if ($body !== null) { $headers[] = 'Content-Type: application/json'; $payload = json_encode($body); curl_setopt($handle, CURLOPT_POSTFIELDS, $payload); }
	if ($rawBody !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, (string) $rawBody);
	if ($bearer !== '') $headers[] = 'Authorization: Bearer '.$bearer;
	foreach ((array) $extraHeaders as $header) $headers[] = (string) $header;
	$started = microtime(true); curl_setopt($handle, CURLOPT_HTTPHEADER, $headers); $raw = curl_exec($handle); $duration = microtime(true) - $started; $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE); $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE); $error = curl_error($handle); curl_close($handle);
	if ($raw === false) return array('status'=>0,'headers'=>'','body'=>'','json'=>null,'error'=>$error,'duration'=>$duration);
	$headerText = substr($raw, 0, $headerSize); $responseBody = substr($raw, $headerSize); return array('status'=>$status,'headers'=>$headerText,'body'=>$responseBody,'json'=>json_decode($responseBody, true),'error'=>'','duration'=>$duration);
}

function m017_http_concurrent_refresh($url, $refreshToken, $deviceId)
{
	$multi = curl_multi_init(); $handles = array(); $payload = json_encode(array('refresh_token'=>$refreshToken,'device_id'=>$deviceId));
	for ($i = 0; $i < 2; $i++) {
		$handle = curl_init($url); curl_setopt($handle, CURLOPT_RETURNTRANSFER, true); curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'POST'); curl_setopt($handle, CURLOPT_HEADER, true); curl_setopt($handle, CURLOPT_TIMEOUT, 10); curl_setopt($handle, CURLOPT_POSTFIELDS, $payload); curl_setopt($handle, CURLOPT_HTTPHEADER, array('Accept: application/json','Content-Type: application/json'));
		$handles[] = $handle; curl_multi_add_handle($multi, $handle);
	}
	do { $status = curl_multi_exec($multi, $running); if ($running) curl_multi_select($multi, 0.25); } while ($running && $status === CURLM_OK);
	$responses = array(); foreach ($handles as $handle) { $raw = curl_multi_getcontent($handle); $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE); $body = substr($raw, $headerSize); $responses[] = array('status'=>(int) curl_getinfo($handle, CURLINFO_HTTP_CODE),'json'=>json_decode($body, true)); curl_multi_remove_handle($multi, $handle); curl_close($handle); }
	curl_multi_close($multi); return $responses;
}

$apiBase = getenv('M017_API_BASE'); $credentialFile = getenv('M017_CREDENTIAL_FILE'); $testDb = null;
if (is_string($apiBase) && $apiBase !== '' && is_string($credentialFile) && is_file($credentialFile)) {
	$credentials = json_decode(file_get_contents($credentialFile), true); $deviceA = '44444444-4444-4444-8444-444444444444'; $deviceB = '55555555-5555-4555-8555-555555555555';
	m017_route_assert(is_array($credentials) && count($credentials) >= 2, 'Synthetic HTTP credentials are available');
	if (is_array($credentials) && count($credentials) >= 2) {
		$documentRoot = getenv('M017_DOL_DOCUMENT_ROOT');
		if (is_string($documentRoot) && is_file(rtrim($documentRoot, '/').'/master.inc.php')) {
			if (!defined('CPCC_MOBILE_TESTING')) define('CPCC_MOBILE_TESTING', true); putenv('CPCC_MOBILE_TEST_PEPPER=m017-local-test-pepper-0123456789abcdef');
			if (!defined('NOLOGIN')) define('NOLOGIN', 1); if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1); if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1); if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', 1); if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
			$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
			require rtrim($documentRoot, '/').'/master.inc.php'; require_once $root.'/custom/chinaplanetcustomercenter/lib/mobile_api.lib.php'; global $db; $testDb = $db;
		}
		$loginA = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[0]['login'],'password'=>$credentials[0]['password'],'device_id'=>$deviceA,'device_name'=>'M017 HTTP A'));
		m017_route_assert($loginA['status'] === 200 && !empty($loginA['json']['data']['access_token']) && !empty($loginA['json']['data']['refresh_token']), 'Synthetic customer A can log in over HTTP');
		$accessA = !empty($loginA['json']['data']['access_token']) ? $loginA['json']['data']['access_token'] : '';
		$meA = m017_http_call('GET', rtrim($apiBase, '/').'/me', null, $accessA);
		m017_route_assert($meA['status'] === 200 && !empty($meA['json']['data']['company_name']) && stripos($meA['headers'], 'Cache-Control: private, no-store') !== false, 'Authenticated identity uses no-store headers');
		$projectsA = m017_http_call('GET', rtrim($apiBase, '/').'/projects', null, $accessA); $foreignProjectId = !empty($projectsA['json']['data'][0]['id']) ? $projectsA['json']['data'][0]['id'] : '';
		m017_route_assert($projectsA['status'] === 200 && count($projectsA['json']['data']) === 2 && $foreignProjectId !== '', 'Customer A receives its two synthetic projects');
		$shipmentsA = m017_http_call('GET', rtrim($apiBase, '/').'/shipments', null, $accessA); $foreignShipmentId = !empty($shipmentsA['json']['data'][0]['id']) ? $shipmentsA['json']['data'][0]['id'] : '';
		$commercialA = m017_http_call('GET', rtrim($apiBase, '/').'/commercial-documents', null, $accessA); $foreignCommercialId = !empty($commercialA['json']['data'][0]['id']) ? $commercialA['json']['data'][0]['id'] : '';
		$reportsA = m017_http_call('GET', rtrim($apiBase, '/').'/reports', null, $accessA); $foreignReportId = !empty($reportsA['json']['data'][0]['id']) ? $reportsA['json']['data'][0]['id'] : '';
		$documentsA = m017_http_call('GET', rtrim($apiBase, '/').'/documents', null, $accessA); $foreignDocumentId = !empty($documentsA['json']['data'][0]['id']) ? $documentsA['json']['data'][0]['id'] : '';
		m017_route_assert($shipmentsA['status'] === 200 && $commercialA['status'] === 200 && $reportsA['status'] === 200 && $documentsA['status'] === 200, 'All customer A detail-bearing resource lists are reachable');
		if ($testDb) {
			$userResult = $testDb->query("SELECT entity,fk_soc,rowid FROM ".MAIN_DB_PREFIX."user WHERE login='".$testDb->escape($credentials[0]['login'])."' LIMIT 1"); $userRow = $userResult ? $testDb->fetch_object($userResult) : null;
			$assetResult = $userRow ? $testDb->query("SELECT a.fk_card,a.stored_name,a.mime_type,a.file_size FROM ".MAIN_DB_PREFIX."cpkanban_asset a WHERE a.entity=".(int) $userRow->entity." AND a.fk_soc=".(int) $userRow->fk_soc." AND a.status=1 AND a.customer_visible=1 AND a.review_status IN ('approved','final_received') ORDER BY a.rowid ASC LIMIT 1") : false; $assetRow = $assetResult ? $testDb->fetch_object($assetResult) : null;
			m017_route_assert($userRow && $assetRow, 'Synthetic owned asset is available for direct hidden-content HTTP tests');
			if ($userRow && $assetRow) {
				$hiddenDefinitions = array(array('pending',1,1),array('approved',0,1),array('approved',1,0)); $hiddenRows = array();
				foreach ($hiddenDefinitions as $index => $hidden) {
					$hiddenSql = "INSERT INTO ".MAIN_DB_PREFIX."cpkanban_asset (entity,fk_soc,fk_card,asset_type,document_type,original_name,stored_name,mime_type,file_size,caption,version_no,review_status,customer_visible,status,fk_user_creat,date_creation) VALUES (".(int) $userRow->entity.",".(int) $userRow->fk_soc.",".(int) $assetRow->fk_card.",'document','final','m017-http-hidden-".$index.".txt','".$testDb->escape($assetRow->stored_name)."','".$testDb->escape($assetRow->mime_type)."',".(int) $assetRow->file_size.",'M017 hidden HTTP',1,'".$testDb->escape($hidden[0])."',".(int) $hidden[1].",".(int) $hidden[2].",".(int) $userRow->rowid.",'".cpcc_mobile_now_sql()."')";
					$created = $testDb->query($hiddenSql); $hiddenId = $created ? (int) $testDb->last_insert_id(MAIN_DB_PREFIX.'cpkanban_asset') : 0; $hiddenRows[] = $hiddenId;
					$hiddenContext = array('entity'=>(int) $userRow->entity,'socid'=>(int) $userRow->fk_soc); $hiddenPublic = $hiddenId > 0 ? cpcc_mobile_public_id($testDb, 'document', $hiddenContext, $hiddenId * 2) : '';
					$hiddenContent = m017_http_call('GET', rtrim($apiBase, '/').'/documents/'.rawurlencode($hiddenPublic).'/content', null, $accessA);
					m017_route_assert($hiddenId > 0 && $hiddenContent['status'] === 404 && $hiddenContent['json']['error']['code'] === 'not_found', 'Direct content returns 404 for hidden document state '.$index);
					if ($hiddenPublic !== '') $testDb->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$testDb->escape($hiddenPublic)."'");
				}
				$hiddenRows = array_filter(array_map('intval', $hiddenRows)); if ($hiddenRows) $testDb->query("DELETE FROM ".MAIN_DB_PREFIX."cpkanban_asset WHERE rowid IN (".implode(',', $hiddenRows).")");
			}
		}
		$dashboardA = m017_http_call('GET', rtrim($apiBase, '/').'/dashboard', null, $accessA);
		$projectDetailA = m017_http_call('GET', rtrim($apiBase, '/').'/projects/'.rawurlencode($foreignProjectId), null, $accessA);
		$shipmentDetailA = m017_http_call('GET', rtrim($apiBase, '/').'/shipments/'.rawurlencode($foreignShipmentId), null, $accessA);
		$ordersA = m017_http_call('GET', rtrim($apiBase, '/').'/orders', null, $accessA);
		$balancesA = m017_http_call('GET', rtrim($apiBase, '/').'/account/balances', null, $accessA);
		$transactionsA = m017_http_call('GET', rtrim($apiBase, '/').'/account/transactions', null, $accessA);
		$invoicesA = m017_http_call('GET', rtrim($apiBase, '/').'/service-invoices', null, $accessA);
		$commercialDetailA = m017_http_call('GET', rtrim($apiBase, '/').'/commercial-documents/'.rawurlencode($foreignCommercialId), null, $accessA);
		$reportDetailA = m017_http_call('GET', rtrim($apiBase, '/').'/reports/'.rawurlencode($foreignReportId), null, $accessA);
		$reportDocumentId = !empty($reportDetailA['json']['data']['documents'][0]['id']) ? $reportDetailA['json']['data']['documents'][0]['id'] : '';
		$reportContentA = m017_http_call('GET', rtrim($apiBase, '/').'/reports/'.rawurlencode($foreignReportId).'/documents/'.rawurlencode($reportDocumentId).'/content', null, $accessA);
		foreach (array('dashboard'=>$dashboardA,'project detail'=>$projectDetailA,'shipment detail'=>$shipmentDetailA,'orders'=>$ordersA,'balances'=>$balancesA,'transactions'=>$transactionsA,'service invoices'=>$invoicesA,'commercial detail'=>$commercialDetailA,'report detail'=>$reportDetailA,'report content'=>$reportContentA) as $routeName => $routeResponse) {
			m017_route_assert($routeResponse['status'] === 200, 'Owned HTTP route is reachable: '.$routeName.' (actual '.$routeResponse['status'].')');
		}

		preg_match('/^ETag:\s*(.+)\r?$/mi', $projectsA['headers'], $etagMatch); $etag = isset($etagMatch[1]) ? trim($etagMatch[1]) : '';
		$notModified = m017_http_call('GET', rtrim($apiBase, '/').'/projects', null, $accessA, array('If-None-Match: '.$etag));
		$futureProjects = m017_http_call('GET', rtrim($apiBase, '/').'/projects?updated_since=2100-01-01T00%3A00%3A00Z', null, $accessA);
		m017_route_assert($etag !== '' && $notModified['status'] === 304 && $notModified['body'] === '', 'Customer-bound ETag supports If-None-Match without returning data');
		m017_route_assert($futureProjects['status'] === 200 && empty($futureProjects['json']['data']), 'updated_since filters the owned list without leakage');
		$pageOne = m017_http_call('GET', rtrim($apiBase, '/').'/projects?limit=1', null, $accessA); $boundCursor = !empty($pageOne['json']['meta']['next_cursor']) ? $pageOne['json']['meta']['next_cursor'] : '';

		$loginB = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[1]['login'],'password'=>$credentials[1]['password'],'device_id'=>$deviceB,'device_name'=>'M017 HTTP B')); $accessB = !empty($loginB['json']['data']['access_token']) ? $loginB['json']['data']['access_token'] : '';
		$deviceC = '77777777-7777-4777-8777-777777777777'; $loginC = count($credentials) >= 3 ? m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[2]['login'],'password'=>$credentials[2]['password'],'device_id'=>$deviceC,'device_name'=>'M017 HTTP C')) : array('status'=>0,'json'=>array()); $accessC = !empty($loginC['json']['data']['access_token']) ? $loginC['json']['data']['access_token'] : '';
		$cursorCustomerB = m017_http_call('GET', rtrim($apiBase, '/').'/projects?limit=1&cursor='.rawurlencode($boundCursor), null, $accessB);
		$cursorEntityC = m017_http_call('GET', rtrim($apiBase, '/').'/projects?limit=1&cursor='.rawurlencode($boundCursor), null, $accessC);
		$cursorResource = m017_http_call('GET', rtrim($apiBase, '/').'/shipments?limit=1&cursor='.rawurlencode($boundCursor), null, $accessA);
		$cursorFilter = m017_http_call('GET', rtrim($apiBase, '/').'/projects?limit=1&updated_since=2026-07-20T00%3A00%3A00Z&cursor='.rawurlencode($boundCursor), null, $accessA);
		$cursorLimit = m017_http_call('GET', rtrim($apiBase, '/').'/projects?limit=2&cursor='.rawurlencode($boundCursor), null, $accessA);
		m017_route_assert($boundCursor !== '' && $loginC['status'] === 200, 'Synthetic cursor and cross-entity customer session are available');
		foreach (array('customer'=>$cursorCustomerB,'entity'=>$cursorEntityC,'resource'=>$cursorResource,'filter'=>$cursorFilter,'limit'=>$cursorLimit) as $binding => $cursorResponse) m017_route_assert($cursorResponse['status'] === 404 && $cursorResponse['json']['error']['code'] === 'not_found', 'Opaque cursor rejects different '.$binding.' binding');
		$foreignRead = m017_http_call('GET', rtrim($apiBase, '/').'/projects/'.rawurlencode($foreignProjectId), null, $accessB);
		m017_route_assert($loginB['status'] === 200 && $foreignRead['status'] === 404 && isset($foreignRead['json']['error']['code']) && $foreignRead['json']['error']['code'] === 'not_found', 'Valid foreign opaque IDs are non-disclosing over HTTP');
		foreach (array('shipments/'.$foreignShipmentId,'commercial-documents/'.$foreignCommercialId,'reports/'.$foreignReportId) as $foreignPath) {
			$foreignResponse = m017_http_call('GET', rtrim($apiBase, '/').'/'.$foreignPath, null, $accessB);
			m017_route_assert($foreignResponse['status'] === 404 && $foreignResponse['json']['error']['code'] === 'not_found', 'Foreign detail is non-disclosing: '.$foreignPath);
		}
		$foreignContent = m017_http_call('GET', rtrim($apiBase, '/').'/documents/'.rawurlencode($foreignDocumentId).'/content', null, $accessB);
		m017_route_assert($foreignContent['status'] === 404 && $foreignContent['json']['error']['code'] === 'not_found', 'Foreign customer document content is non-disclosing');
		$documentsB = m017_http_call('GET', rtrim($apiBase, '/').'/documents', null, $accessB); $documentId = !empty($documentsB['json']['data'][0]['id']) ? $documentsB['json']['data'][0]['id'] : '';
		$contentB = m017_http_call('GET', rtrim($apiBase, '/').'/documents/'.rawurlencode($documentId).'/content', null, $accessB);
		m017_route_assert($documentsB['status'] === 200 && count($documentsB['json']['data']) === 2 && $contentB['status'] === 200 && stripos($contentB['headers'], 'Content-Disposition: attachment') !== false && stripos($contentB['headers'], 'Cache-Control: private, no-store') !== false, 'Approved customer document streams through the authenticated endpoint');
		$rangeB = m017_http_call('GET', rtrim($apiBase, '/').'/documents/'.rawurlencode($documentId).'/content', null, $accessB, array('Range: bytes=0-3'));
		$invalidRangeB = m017_http_call('GET', rtrim($apiBase, '/').'/documents/'.rawurlencode($documentId).'/content', null, $accessB, array('Range: bytes=0-1,3-4'));
		m017_route_assert($rangeB['status'] === 206 && strlen($rangeB['body']) === 4 && stripos($rangeB['headers'], 'Content-Range: bytes 0-3/') !== false, 'Authorized single-range download returns 206 with exact bytes');
		m017_route_assert($invalidRangeB['status'] === 416 && $invalidRangeB['json']['error']['code'] === 'validation' && stripos($invalidRangeB['headers'], 'Content-Range: bytes */') !== false, 'Invalid or multi-range download returns bounded 416');

		foreach (array('1','prj_forged12345678901234567890','shp_forged12345678901234567890') as $badId) {
			$badPath = strpos($badId, 'shp_') === 0 ? 'shipments/' : 'projects/';
			$badResponse = m017_http_call('GET', rtrim($apiBase, '/').'/'.$badPath.rawurlencode($badId), null, $accessA);
			m017_route_assert($badResponse['status'] === 404 && $badResponse['json']['error']['code'] === 'not_found', 'Raw, malformed, or forged opaque ID is non-disclosing: '.$badId);
		}

		$refreshB = m017_http_call('POST', rtrim($apiBase, '/').'/auth/refresh', array('refresh_token'=>$loginB['json']['data']['refresh_token'],'device_id'=>$deviceB));
		m017_route_assert($refreshB['status'] === 200 && !empty($refreshB['json']['data']['access_token']), 'Refresh token rotates over HTTP');
		$deviceConcurrent = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
		$loginConcurrent = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[0]['login'],'password'=>$credentials[0]['password'],'device_id'=>$deviceConcurrent,'device_name'=>'M017 concurrent refresh'));
		$concurrent = m017_http_concurrent_refresh(rtrim($apiBase, '/').'/auth/refresh', $loginConcurrent['json']['data']['refresh_token'], $deviceConcurrent); $concurrentStatuses = array_column($concurrent, 'status'); sort($concurrentStatuses);
		m017_route_assert($loginConcurrent['status'] === 200 && $concurrentStatuses === array(200,409), 'Exactly one of two concurrent refresh requests succeeds and replay conflicts');

		$noContentType = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', null, '', array(), '');
		$oversized = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', null, '', array('Content-Type: application/json'), json_encode(array('login'=>str_repeat('x', 65537))));
		m017_route_assert($noContentType['status'] === 422 && $noContentType['json']['error']['code'] === 'validation', 'Credential requests reject missing JSON content type (actual '.$noContentType['status'].')');
		m017_route_assert($oversized['status'] === 422 && $oversized['json']['error']['code'] === 'validation', 'Credential requests reject bodies larger than 64 KiB (actual '.$oversized['status'].')');

		$scopedAccess = $refreshB['json']['data']['access_token'];
		foreach (array('entity','socid','fk_soc','DOLAPIENTITY','DOLAPIKEY','api_key','token') as $overrideKey) {
			$override = m017_http_call('GET', rtrim($apiBase, '/').'/me?'.rawurlencode($overrideKey).'=1', null, $scopedAccess);
			m017_route_assert($override['status'] === 403 && $override['json']['error']['code'] === 'forbidden', 'Query scope/key override is rejected: '.$overrideKey);
		}
		foreach (array('DOLAPIENTITY: 1','DOLAPIKEY: forbidden','api-key: forbidden','api_key: forbidden','X-DOLAPIKEY: forbidden') as $overrideHeader) {
			$override = m017_http_call('GET', rtrim($apiBase, '/').'/me', null, $scopedAccess, array($overrideHeader));
			m017_route_assert($override['status'] === 403 && $override['json']['error']['code'] === 'forbidden', 'Header scope/key override is rejected: '.$overrideHeader);
		}
		foreach (array('entity','socid','fk_soc') as $overrideKey) {
			$payload = array('refresh_token'=>$refreshB['json']['data']['refresh_token'],'device_id'=>$deviceB,$overrideKey=>1);
			$override = m017_http_call('POST', rtrim($apiBase, '/').'/auth/refresh', $payload);
			m017_route_assert($override['status'] === 403 && $override['json']['error']['code'] === 'forbidden', 'Body customer/entity override is rejected: '.$overrideKey);
		}

		$wrong = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[0]['login'],'password'=>'not-the-password','device_id'=>'66666666-6666-4666-8666-666666666666'));
		$unknown = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>'m017_unknown_timing','password'=>'not-the-password','device_id'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'));
		$timingRatio = min($wrong['duration'], $unknown['duration']) > 0 ? max($wrong['duration'], $unknown['duration']) / min($wrong['duration'], $unknown['duration']) : 999;
		m017_route_assert($wrong['status'] === 401 && $unknown['status'] === 401 && $wrong['json']['error'] === $unknown['json']['error'], 'Known and unknown login failures use the same status and generic error body');
		m017_route_assert(abs($wrong['duration'] - $unknown['duration']) < 0.5 && $timingRatio < 5, 'Known and unknown login failures have no material timing split (known '.round($wrong['duration'], 3).'s, unknown '.round($unknown['duration'], 3).'s)');

		$throttleStatuses = array(); for ($attempt = 0; $attempt < 6; $attempt++) { $limited = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>'m017_throttle_case','password'=>'wrong','device_id'=>'cccccccc-cccc-4ccc-8ccc-cccccccccccc')); $throttleStatuses[] = $limited['status']; }
		m017_route_assert(array_slice($throttleStatuses, 0, 5) === array(401,401,401,401,401) && $throttleStatuses[5] === 429, 'Login throttling is enforced on the sixth account-origin failure');

		$logoutA = m017_http_call('POST', rtrim($apiBase, '/').'/auth/logout/revoke', null, $accessA); $afterLogout = m017_http_call('GET', rtrim($apiBase, '/').'/me', null, $accessA);
		m017_route_assert($logoutA['status'] === 204 && $logoutA['body'] === '' && $afterLogout['status'] === 401, 'Logout returns 204 and invalidates the device family');
		$httpRouteStatuses = array(
			$loginA['status'],$refreshB['status'],$logoutA['status'],$meA['status'],$dashboardA['status'],$projectsA['status'],$projectDetailA['status'],$shipmentsA['status'],$shipmentDetailA['status'],$ordersA['status'],$balancesA['status'],$transactionsA['status'],$invoicesA['status'],$documentsA['status'],$contentB['status'],$commercialA['status'],$commercialDetailA['status'],$reportsA['status'],$reportDetailA['status'],$reportContentA['status'],
		);
		m017_route_assert(count($httpRouteStatuses) === 20 && !array_diff($httpRouteStatuses, array(200,204)), 'All 20 frozen routes are exercised successfully over local HTTP');
	}
}
if ($failures) { fwrite(STDERR, "M017_ROUTE=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_ROUTE=PASS ROUTES=".count($actual).($apiBase ? ' HTTP_ROUTES=20 HTTP_INTEGRATION=PASS' : '')."\n";
