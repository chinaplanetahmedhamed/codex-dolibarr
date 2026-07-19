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

function m017_http_call($method, $url, $body = null, $bearer = '')
{
	$handle = curl_init($url); $headers = array('Accept: application/json');
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true); curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method); curl_setopt($handle, CURLOPT_HEADER, true); curl_setopt($handle, CURLOPT_TIMEOUT, 10);
	if ($body !== null) { $headers[] = 'Content-Type: application/json'; $payload = json_encode($body); curl_setopt($handle, CURLOPT_POSTFIELDS, $payload); }
	if ($bearer !== '') $headers[] = 'Authorization: Bearer '.$bearer;
	curl_setopt($handle, CURLOPT_HTTPHEADER, $headers); $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE); $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE); $error = curl_error($handle); curl_close($handle);
	if ($raw === false) return array('status'=>0,'headers'=>'','body'=>'','json'=>null,'error'=>$error);
	$headerText = substr($raw, 0, $headerSize); $responseBody = substr($raw, $headerSize); return array('status'=>$status,'headers'=>$headerText,'body'=>$responseBody,'json'=>json_decode($responseBody, true),'error'=>'');
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
		$loginB = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[1]['login'],'password'=>$credentials[1]['password'],'device_id'=>$deviceB,'device_name'=>'M017 HTTP B')); $accessB = !empty($loginB['json']['data']['access_token']) ? $loginB['json']['data']['access_token'] : '';
		$foreignRead = m017_http_call('GET', rtrim($apiBase, '/').'/projects/'.rawurlencode($foreignProjectId), null, $accessB);
		m017_route_assert($loginB['status'] === 200 && $foreignRead['status'] === 404 && isset($foreignRead['json']['error']['code']) && $foreignRead['json']['error']['code'] === 'not_found', 'Valid foreign opaque IDs are non-disclosing over HTTP');
		$documentsB = m017_http_call('GET', rtrim($apiBase, '/').'/documents', null, $accessB); $documentId = !empty($documentsB['json']['data'][0]['id']) ? $documentsB['json']['data'][0]['id'] : '';
		$contentB = m017_http_call('GET', rtrim($apiBase, '/').'/documents/'.rawurlencode($documentId).'/content', null, $accessB);
		m017_route_assert($documentsB['status'] === 200 && count($documentsB['json']['data']) === 2 && $contentB['status'] === 200 && stripos($contentB['headers'], 'Content-Disposition: attachment') !== false && stripos($contentB['headers'], 'Cache-Control: private, no-store') !== false, 'Approved customer document streams through the authenticated endpoint');
		$refreshB = m017_http_call('POST', rtrim($apiBase, '/').'/auth/refresh', array('refresh_token'=>$loginB['json']['data']['refresh_token'],'device_id'=>$deviceB));
		m017_route_assert($refreshB['status'] === 200 && !empty($refreshB['json']['data']['access_token']), 'Refresh token rotates over HTTP');
		$logoutA = m017_http_call('POST', rtrim($apiBase, '/').'/auth/logout/revoke', null, $accessA); $afterLogout = m017_http_call('GET', rtrim($apiBase, '/').'/me', null, $accessA);
		m017_route_assert($logoutA['status'] === 204 && $logoutA['body'] === '' && $afterLogout['status'] === 401, 'Logout returns 204 and invalidates the device family');
		$override = m017_http_call('GET', rtrim($apiBase, '/').'/me?socid=1', null, $refreshB['json']['data']['access_token']);
		m017_route_assert($override['status'] === 403 && $override['json']['error']['code'] === 'forbidden', 'Caller-selected customer scope is rejected over HTTP');
		$wrong = m017_http_call('POST', rtrim($apiBase, '/').'/auth/login', array('login'=>$credentials[0]['login'],'password'=>'not-the-password','device_id'=>'66666666-6666-4666-8666-666666666666'));
		m017_route_assert($wrong['status'] === 401 && $wrong['json']['error']['code'] === 'invalid_credentials', 'Invalid credentials return the stable non-enumerating error');
	}
}
if ($failures) { fwrite(STDERR, "M017_ROUTE=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_ROUTE=PASS ROUTES=".count($actual).($apiBase ? ' HTTP_INTEGRATION=PASS' : '')."\n";
