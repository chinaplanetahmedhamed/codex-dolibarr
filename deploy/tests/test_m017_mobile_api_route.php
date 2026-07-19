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

$apiBase = getenv('M017_API_BASE'); $credentialFile = getenv('M017_CREDENTIAL_FILE');
if (is_string($apiBase) && $apiBase !== '' && is_string($credentialFile) && is_file($credentialFile)) {
	$credentials = json_decode(file_get_contents($credentialFile), true); $deviceA = '44444444-4444-4444-8444-444444444444'; $deviceB = '55555555-5555-4555-8555-555555555555';
	m017_route_assert(is_array($credentials) && count($credentials) >= 2, 'Synthetic HTTP credentials are available');
	if (is_array($credentials) && count($credentials) >= 2) {
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

		preg_match('/^ETag:\s*(.+)\r?$/mi', $projectsA['headers'], $etagMatch); $etag = isset($etagMatch[1]) ? trim($etagMatch[1]) : '';
		$notModified = m017_http_call('GET', rtrim($apiBase, '/').'/projects', null, $accessA, array('If-None-Match: '.$etag));
		$futureProjects = m017_http_call('GET', rtrim($apiBase, '/').'/projects?updated_since=2100-01-01T00%3A00%3A00Z', null, $accessA);
		m017_route_assert($etag !== '' && $notModified['status'] === 304 && $notModified['body'] === '', 'Customer-bound ETag supports If-None-Match without returning data');
		m017_route_assert($futureProjects['status'] === 200 && empty($futureProjects['json']['data']), 'updated_since filters the owned list without leakage');

		$loginB = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[1]['login'],'password'=>$credentials[1]['password'],'device_id'=>$deviceB,'device_name'=>'M017 HTTP B')); $accessB = !empty($loginB['json']['data']['access_token']) ? $loginB['json']['data']['access_token'] : '';
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
	}
}
if ($failures) { fwrite(STDERR, "M017_ROUTE=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_ROUTE=PASS ROUTES=".count($actual).($apiBase ? ' HTTP_INTEGRATION=PASS' : '')."\n";
