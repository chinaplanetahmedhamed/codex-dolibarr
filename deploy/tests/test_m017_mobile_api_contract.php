<?php
$root = dirname(__DIR__, 2);
$path = $root.'/docs/API/mobile-v1.openapi.json';
$failures = array();
function m017_contract_assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
$json = file_get_contents($path);
m017_contract_assert($json !== false, 'OpenAPI file is readable');
$api = json_decode((string) $json, true);
m017_contract_assert(is_array($api), 'OpenAPI is valid JSON');
m017_contract_assert(isset($api['openapi']) && $api['openapi'] === '3.1.0', 'OpenAPI version is 3.1.0');
m017_contract_assert(isset($api['paths']) && count($api['paths']) === 21, 'Exactly 21 approved routes exist');
$expected = array(
	'POST /auth/login', 'POST /auth/refresh', 'POST /auth/logout/revoke', 'GET /me', 'GET /dashboard',
	'GET /projects', 'GET /projects/{opaqueId}', 'GET /shipments', 'GET /shipments/{opaqueId}', 'GET /orders',
	'GET /account/balances', 'GET /account/transactions', 'GET /service-invoices', 'GET /service-invoices/{opaqueId}', 'GET /documents',
	'GET /documents/{opaqueId}/content', 'GET /commercial-documents', 'GET /commercial-documents/{opaqueId}',
	'GET /reports', 'GET /reports/{opaqueId}', 'GET /reports/{opaqueId}/documents/{documentOpaqueId}/content',
);
foreach ($expected as $route) {
	list($method, $routePath) = explode(' ', $route, 2);
	m017_contract_assert(isset($api['paths'][$routePath][strtolower($method)]), 'Route exists: '.$route);
}
m017_contract_assert(isset($api['components']['schemas']['ErrorEnvelope']), 'Frozen ErrorEnvelope exists');
m017_contract_assert(isset($api['components']['schemas']['BilingualLabel']), 'Frozen bilingual labels exist');
m017_contract_assert(isset($api['components']['securitySchemes']['bearerAuth']), 'Bearer authentication is documented');
m017_contract_assert(hash('sha256', (string) $json) === '65fd77cab6aa4717dfcbe886fa90a90b8297d7f0e7da07a38be36eb705a2c3ba', 'OpenAPI byte hash matches the approved M061-R02 contract');
if ($failures) { fwrite(STDERR, "M017_CONTRACT=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M061_R02_MOBILE_CONTRACT=PASS PATHS=21 HASH=65fd77cab6aa4717dfcbe886fa90a90b8297d7f0e7da07a38be36eb705a2c3ba\n";
