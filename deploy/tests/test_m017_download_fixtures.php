<?php
/** M017-C03 focused authenticated download and customer-isolation test. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M017_DOWNLOAD_HTTP=FAIL REASON=CLI_ONLY\n"); exit(1); }
$apiBase = rtrim((string) getenv('M017_API_BASE'), '/');
$credentialFile = (string) getenv('M017_CREDENTIAL_FILE');
if ($apiBase === '' || !is_file($credentialFile)) { fwrite(STDERR, "M017_DOWNLOAD_HTTP=FAIL REASON=ENVIRONMENT_REQUIRED\n"); exit(1); }
$credentials = json_decode(file_get_contents($credentialFile), true);
if (!is_array($credentials) || count($credentials) < 2) { fwrite(STDERR, "M017_DOWNLOAD_HTTP=FAIL REASON=CREDENTIALS_REQUIRED\n"); exit(1); }

$failures = array();
function m017DownloadAssert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
function m017DownloadHttp($method, $url, $body = null, $bearer = '', $headers = array())
{
	$handle = curl_init($url); $requestHeaders = array('Accept: application/json');
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true); curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method); curl_setopt($handle, CURLOPT_HEADER, true); curl_setopt($handle, CURLOPT_TIMEOUT, 10);
	if ($body !== null) { $requestHeaders[] = 'Content-Type: application/json'; curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body)); }
	if ($bearer !== '') $requestHeaders[] = 'Authorization: Bearer '.$bearer;
	foreach ($headers as $header) $requestHeaders[] = $header;
	curl_setopt($handle, CURLOPT_HTTPHEADER, $requestHeaders); $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE); $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE); $error = curl_error($handle); curl_close($handle);
	if ($raw === false) return array('status'=>0,'headers'=>'','body'=>'','json'=>null,'error'=>$error);
	$responseHeaders = substr($raw, 0, $headerSize); $responseBody = substr($raw, $headerSize);
	return array('status'=>$status,'headers'=>$responseHeaders,'body'=>$responseBody,'json'=>json_decode($responseBody, true),'error'=>'');
}

$loginA = m017DownloadHttp('POST', $apiBase.'/auth/login', array('login'=>$credentials[0]['login'],'password'=>$credentials[0]['password'],'device_id'=>'c0300000-0000-4000-8000-00000000000a','device_name'=>'M017-C03 Download A'));
$loginB = m017DownloadHttp('POST', $apiBase.'/auth/login', array('login'=>$credentials[1]['login'],'password'=>$credentials[1]['password'],'device_id'=>'c0300000-0000-4000-8000-00000000000b','device_name'=>'M017-C03 Download B'));
$accessA = !empty($loginA['json']['data']['access_token']) ? (string) $loginA['json']['data']['access_token'] : '';
$accessB = !empty($loginB['json']['data']['access_token']) ? (string) $loginB['json']['data']['access_token'] : '';
m017DownloadAssert($loginA['status'] === 200 && $accessA !== '' && $loginB['status'] === 200 && $accessB !== '', 'Both synthetic customers can authenticate');

$listA = m017DownloadHttp('GET', $apiBase.'/documents?limit=25', null, $accessA);
$listB = m017DownloadHttp('GET', $apiBase.'/documents?limit=25', null, $accessB);
m017DownloadAssert($listA['status'] === 200 && $listB['status'] === 200, 'Both owned document lists are reachable');
$byNameA = array(); foreach ((array) ($listA['json']['data'] ?? array()) as $document) $byNameA[(string) $document['name']] = $document;
$byNameB = array(); foreach ((array) ($listB['json']['data'] ?? array()) as $document) $byNameB[(string) $document['name']] = $document;
$fixtures = array(
	'M017 Download Test.pdf' => array('mime'=>'application/pdf','signature'=>"%PDF"),
	'M017 Download Test Image.png' => array('mime'=>'image/png','signature'=>"\x89PNG"),
	'M017 Download Test Data.csv' => array('mime'=>'text/csv','signature'=>'reference,status,amount,currency'),
);

foreach ($fixtures as $name => $expected) {
	$document = $byNameA[$name] ?? null;
	m017DownloadAssert(is_array($document), 'Alpha lists fixture '.$name);
	m017DownloadAssert(!isset($byNameB[$name]), 'Beta list excludes Alpha fixture '.$name);
	if (!is_array($document)) continue;
	m017DownloadAssert((string) $document['mime_type'] === $expected['mime'] && (int) $document['size_bytes'] > 0, 'Fixture metadata is bounded for '.$name);
	$content = m017DownloadHttp('GET', $apiBase.'/documents/'.rawurlencode((string) $document['id']).'/content', null, $accessA);
	m017DownloadAssert($content['status'] === 200, 'Alpha downloads '.$name);
	m017DownloadAssert(strlen($content['body']) === (int) $document['size_bytes'] && strncmp($content['body'], $expected['signature'], strlen($expected['signature'])) === 0, 'Downloaded bytes match metadata and signature for '.$name);
	m017DownloadAssert(stripos($content['headers'], 'Content-Type: '.$expected['mime']) !== false && stripos($content['headers'], 'Content-Disposition: attachment') !== false && stripos($content['headers'], rawurlencode($name)) !== false, 'Download headers preserve MIME and safe filename for '.$name);
	m017DownloadAssert(stripos($content['headers'], 'Cache-Control: private, no-store') !== false && stripos($content['headers'], 'X-Content-Type-Options: nosniff') !== false, 'Download headers prevent caching and sniffing for '.$name);
	$foreign = m017DownloadHttp('GET', $apiBase.'/documents/'.rawurlencode((string) $document['id']).'/content', null, $accessB);
	m017DownloadAssert($foreign['status'] === 404 && ($foreign['json']['error']['code'] ?? '') === 'not_found', 'Beta receives non-disclosing 404 for '.$name);
}

if (isset($byNameA['M017 Download Test.pdf'])) {
	$pdf = $byNameA['M017 Download Test.pdf'];
	$range = m017DownloadHttp('GET', $apiBase.'/documents/'.rawurlencode((string) $pdf['id']).'/content', null, $accessA, array('Range: bytes=0-3'));
	m017DownloadAssert($range['status'] === 206 && $range['body'] === '%PDF' && stripos($range['headers'], 'Content-Range: bytes 0-3/') !== false, 'PDF supports an authorized bounded range request');
}

if ($accessA !== '') m017DownloadHttp('POST', $apiBase.'/auth/logout/revoke', null, $accessA);
if ($accessB !== '') m017DownloadHttp('POST', $apiBase.'/auth/logout/revoke', null, $accessB);
if ($failures) { fwrite(STDERR, "M017_DOWNLOAD_HTTP=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_DOWNLOAD_HTTP=PASS FIXTURES=3 ISOLATION=PASS RANGE=PASS\n";
