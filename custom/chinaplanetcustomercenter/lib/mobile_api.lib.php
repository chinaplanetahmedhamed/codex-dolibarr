<?php
/**
 * Security, token, opaque-ID and protocol helpers for M017.
 *
 * This library never trusts request-selected entity/customer values and never
 * passes extension tokens to Dolibarr's master API-key authentication.
 */

if (!defined('CPCC_MOBILE_ACCESS_TTL')) define('CPCC_MOBILE_ACCESS_TTL', 900);
if (!defined('CPCC_MOBILE_REFRESH_ABSOLUTE_TTL')) define('CPCC_MOBILE_REFRESH_ABSOLUTE_TTL', 2592000);
if (!defined('CPCC_MOBILE_REFRESH_INACTIVITY_TTL')) define('CPCC_MOBILE_REFRESH_INACTIVITY_TTL', 604800);
if (!defined('CPCC_MOBILE_MAX_BODY_BYTES')) define('CPCC_MOBILE_MAX_BODY_BYTES', 65536);
if (!defined('CPCC_MOBILE_DEFAULT_LIMIT')) define('CPCC_MOBILE_DEFAULT_LIMIT', 25);
if (!defined('CPCC_MOBILE_MAX_LIMIT')) define('CPCC_MOBILE_MAX_LIMIT', 100);

function cpcc_mobile_request_id()
{
	static $requestId = null;
	if ($requestId === null) $requestId = 'req_'.cpcc_mobile_base64url_encode(random_bytes(18));
	return $requestId;
}

function cpcc_mobile_label($ar, $en)
{
	return array('ar' => (string) $ar, 'en' => (string) $en);
}

function cpcc_mobile_error_label($code)
{
	$labels = array(
		'bad_request' => cpcc_mobile_label('الطلب غير صالح.', 'The request is invalid.'),
		'invalid_credentials' => cpcc_mobile_label('بيانات الدخول غير صحيحة.', 'The credentials are invalid.'),
		'session_expired' => cpcc_mobile_label('انتهت الجلسة. سجل الدخول مرة أخرى.', 'The session has expired. Sign in again.'),
		'forbidden' => cpcc_mobile_label('غير مسموح بتنفيذ هذا الطلب.', 'This request is not allowed.'),
		'not_found' => cpcc_mobile_label('العنصر غير موجود.', 'The resource was not found.'),
		'conflict' => cpcc_mobile_label('تعذر إكمال الطلب بسبب تعارض.', 'The request conflicts with current state.'),
		'validation' => cpcc_mobile_label('تحقق من البيانات المدخلة.', 'Check the submitted values.'),
		'rate_limited' => cpcc_mobile_label('محاولات كثيرة. حاول لاحقًا.', 'Too many attempts. Try again later.'),
		'server_error' => cpcc_mobile_label('الخدمة غير متاحة مؤقتًا.', 'The service is temporarily unavailable.'),
	);
	return isset($labels[$code]) ? $labels[$code] : $labels['server_error'];
}

function cpcc_mobile_error_envelope($code, $fieldCodes = array(), $retryAfter = 0, $requestId = '')
{
	$error = array('code' => (string) $code, 'message' => cpcc_mobile_error_label($code));
	if (!empty($fieldCodes)) $error['field_codes'] = array_values(array_unique(array_map('strval', $fieldCodes)));
	if ((int) $retryAfter > 0) $error['retry_after'] = min(900, max(1, (int) $retryAfter));
	return array('error' => $error, 'request_id' => $requestId !== '' ? $requestId : cpcc_mobile_request_id());
}

function cpcc_mobile_throw($httpStatus, $code, $fieldCodes = array(), $retryAfter = 0)
{
	$details = array(
		'mobile_code' => (string) $code,
		'mobile_message' => cpcc_mobile_error_label($code),
		'request_id' => cpcc_mobile_request_id(),
	);
	if (!empty($fieldCodes)) $details['field_codes'] = array_values(array_unique(array_map('strval', $fieldCodes)));
	if ((int) $retryAfter > 0) $details['retry_after'] = min(900, max(1, (int) $retryAfter));
	throw new \Luracast\Restler\RestException((int) $httpStatus, (string) $code, $details);
}

function cpcc_mobile_base64url_encode($value)
{
	return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

function cpcc_mobile_base64url_decode($value)
{
	$value = (string) $value;
	if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) return false;
	$padding = strlen($value) % 4;
	if ($padding) $value .= str_repeat('=', 4 - $padding);
	return base64_decode(strtr($value, '-_', '+/'), true);
}

function cpcc_mobile_constant($name, $fallback = '')
{
	if (function_exists('getDolGlobalString')) return (string) getDolGlobalString($name, $fallback);
	return $fallback;
}

function cpcc_mobile_pepper()
{
	$pepper = cpcc_mobile_constant('CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_PEPPER', '');
	if (defined('CPCC_MOBILE_TESTING') && CPCC_MOBILE_TESTING && $pepper === '') {
		$testPepper = getenv('CPCC_MOBILE_TEST_PEPPER');
		if (is_string($testPepper)) $pepper = $testPepper;
	}
	if (strlen($pepper) < 32) cpcc_mobile_throw(503, 'server_error');
	return $pepper;
}

function cpcc_mobile_hash_secret($value, $purpose)
{
	return hash_hmac('sha256', (string) $purpose."\0".(string) $value, cpcc_mobile_pepper());
}

function cpcc_mobile_random_token($prefix)
{
	return (string) $prefix.cpcc_mobile_base64url_encode(random_bytes(32));
}

function cpcc_mobile_now_sql($offsetSeconds = 0)
{
	return gmdate('Y-m-d H:i:s', time() + (int) $offsetSeconds);
}

function cpcc_mobile_iso($value)
{
	if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return null;
	if (is_numeric($value)) $timestamp = (int) $value;
	else $timestamp = strtotime((string) $value.' UTC');
	return $timestamp > 0 ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : null;
}

function cpcc_mobile_decimal($value)
{
	$formatted = number_format((float) $value, 8, '.', '');
	$formatted = rtrim(rtrim($formatted, '0'), '.');
	if ($formatted === '' || $formatted === '-0') $formatted = '0';
	return $formatted;
}

function cpcc_mobile_currency($value, $fallback = 'USD')
{
	$value = strtoupper(trim((string) $value));
	if ($value === 'RMB' || $value === 'RNB') $value = 'CNY';
	if ($value === 'LE') $value = 'EGP';
	return preg_match('/^[A-Z]{3}$/', $value) ? $value : (string) $fallback;
}

function cpcc_mobile_base_currency()
{
	global $conf;
	$currency = isset($conf->currency) ? (string) $conf->currency : '';
	return cpcc_mobile_currency($currency, 'USD');
}

function cpcc_mobile_money($amount, $currency)
{
	return array('amount' => cpcc_mobile_decimal($amount), 'currency' => cpcc_mobile_currency($currency));
}

function cpcc_mobile_origin()
{
	$origin = function_exists('getUserRemoteIP') ? getUserRemoteIP() : (!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
	return substr((string) $origin, 0, 128);
}

function cpcc_mobile_debug_logging_enabled()
{
	if (function_exists('getDolGlobalInt')) return getDolGlobalInt('MAIN_API_DEBUG') > 0;
	return cpcc_mobile_constant('MAIN_API_DEBUG', '') !== '' && cpcc_mobile_constant('MAIN_API_DEBUG', '0') !== '0';
}

function cpcc_mobile_request_headers()
{
	$headers = function_exists('getallheaders') ? getallheaders() : array();
	$result = array();
	foreach ((array) $headers as $key => $value) $result[strtolower((string) $key)] = (string) $value;
	foreach ($_SERVER as $key => $value) {
		if (strpos($key, 'HTTP_') !== 0) continue;
		$name = strtolower(str_replace('_', '-', substr($key, 5)));
		if (!isset($result[$name])) $result[$name] = (string) $value;
	}
	return $result;
}

function cpcc_mobile_reject_overrides($body = array())
{
	$forbidden = array('entity', 'socid', 'fk_soc', 'dolapientity', 'dolapikey', 'api_key', 'token', 'role', 'permission', 'permissions');
	$headers = cpcc_mobile_request_headers();
	foreach (array('dolapientity', 'dolapikey', 'api-key', 'api_key', 'x-dolapikey') as $name) {
		if (isset($headers[$name]) && trim($headers[$name]) !== '') cpcc_mobile_throw(403, 'forbidden');
	}
	foreach ((array) $_GET as $key => $value) {
		if (in_array(strtolower((string) $key), $forbidden, true)) cpcc_mobile_throw(403, 'forbidden');
	}
	foreach ((array) $body as $key => $value) {
		if (in_array(strtolower((string) $key), $forbidden, true)) cpcc_mobile_throw(403, 'forbidden');
	}
}

/**
 * Stop credential-bearing routes before Restler's onCall debug logger runs.
 * The route event is dispatched after the request is decoded but before the
 * API method is selected and before any onCall callback can log its payload.
 */
function cpcc_mobile_register_debug_auth_gate($restler)
{
	// Restler exposes onRoute through __call(), so method_exists() is false even
	// though registering the event is supported.
	if (!cpcc_mobile_debug_logging_enabled() || !is_object($restler)) return;
	$restler->onRoute(function () use ($restler) {
		$path = isset($restler->url) ? trim((string) $restler->url, '/') : '';
		if (preg_match('#(?:^|/)chinaplanetcustomercenter/mobile/v1/auth/(?:login|refresh)$#', $path)) {
			cpcc_mobile_throw(503, 'server_error');
		}
	});
}

function cpcc_mobile_request_json($allowed, $required)
{
	$contentType = !empty($_SERVER['CONTENT_TYPE']) ? strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0])) : '';
	if ($contentType !== 'application/json') cpcc_mobile_throw(422, 'validation', array('content_type'));
	$length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
	if ($length > CPCC_MOBILE_MAX_BODY_BYTES) cpcc_mobile_throw(422, 'validation', array('body'));
	$raw = (string) file_get_contents('php://input');
	if (strlen($raw) > CPCC_MOBILE_MAX_BODY_BYTES) cpcc_mobile_throw(422, 'validation', array('body'));
	$data = json_decode($raw, true);
	if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) cpcc_mobile_throw(422, 'validation', array('body'));
	cpcc_mobile_reject_overrides($data);
	$fieldCodes = array();
	foreach ($data as $key => $value) if (!in_array((string) $key, $allowed, true)) $fieldCodes[] = 'unknown_field';
	foreach ($required as $key) if (!array_key_exists($key, $data)) $fieldCodes[] = (string) $key;
	if (!empty($fieldCodes)) cpcc_mobile_throw(422, 'validation', $fieldCodes);
	return $data;
}

function cpcc_mobile_bearer_token()
{
	cpcc_mobile_reject_overrides();
	$headers = cpcc_mobile_request_headers();
	$authorization = isset($headers['authorization']) ? trim($headers['authorization']) : '';
	if (!preg_match('/^Bearer ([A-Za-z0-9_-]{40,160})$/', $authorization, $matches)) cpcc_mobile_throw(401, 'session_expired');
	return $matches[1];
}

function cpcc_mobile_schema_ready($db)
{
	foreach (array('cpcc_mobile_token_family', 'cpcc_mobile_access_token', 'cpcc_mobile_refresh_token', 'cpcc_mobile_public_id', 'cpcc_mobile_rate_limit') as $suffix) {
		$result = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$suffix)."'");
		if (!$result || $db->num_rows($result) !== 1) return false;
	}
	return true;
}

function cpcc_mobile_query_or_throw($db, $sql)
{
	$result = $db->query($sql);
	if (!$result) cpcc_mobile_throw(500, 'server_error');
	return $result;
}

function cpcc_mobile_rate_hit($db, $routeKey, $bucketHash, $windowSeconds, $limit)
{
	$windowSeconds = max(60, (int) $windowSeconds);
	$windowEpoch = (int) (floor(time() / $windowSeconds) * $windowSeconds);
	$windowStart = gmdate('Y-m-d H:i:s', $windowEpoch);
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_mobile_rate_limit (route_key,bucket_hash,window_start,attempt_count,date_creation) VALUES (";
	$sql .= "'".$db->escape((string) $routeKey)."','".$db->escape((string) $bucketHash)."','".$db->escape($windowStart)."',1,'".cpcc_mobile_now_sql()."')";
	$sql .= " ON DUPLICATE KEY UPDATE attempt_count=attempt_count+1";
	cpcc_mobile_query_or_throw($db, $sql);
	$select = "SELECT attempt_count FROM ".MAIN_DB_PREFIX."cpcc_mobile_rate_limit WHERE route_key='".$db->escape((string) $routeKey)."'";
	$select .= " AND bucket_hash='".$db->escape((string) $bucketHash)."' AND window_start='".$db->escape($windowStart)."'";
	$result = cpcc_mobile_query_or_throw($db, $select);
	$row = $db->fetch_object($result);
	$count = $row ? (int) $row->attempt_count : (int) $limit + 1;
	$retry = max(1, min(900, ($windowEpoch + $windowSeconds) - time()));
	return array('allowed' => $count <= (int) $limit, 'count' => $count, 'retry_after' => $retry);
}

function cpcc_mobile_rate_status($db, $routeKey, $bucketHash, $windowSeconds, $limit)
{
	$windowSeconds = max(60, (int) $windowSeconds); $windowEpoch = (int) (floor(time() / $windowSeconds) * $windowSeconds); $windowStart = gmdate('Y-m-d H:i:s', $windowEpoch);
	$sql = "SELECT attempt_count FROM ".MAIN_DB_PREFIX."cpcc_mobile_rate_limit WHERE route_key='".$db->escape((string) $routeKey)."' AND bucket_hash='".$db->escape((string) $bucketHash)."' AND window_start='".$db->escape($windowStart)."'";
	$result = cpcc_mobile_query_or_throw($db, $sql); $row = $db->fetch_object($result); $count = $row ? (int) $row->attempt_count : 0; $retry = max(1, min(900, ($windowEpoch + $windowSeconds) - time()));
	return array('allowed' => $count < (int) $limit, 'count' => $count, 'retry_after' => $retry);
}

function cpcc_mobile_login_rate_prepare($db, $login)
{
	$origin = cpcc_mobile_origin();
	$originBucket = cpcc_mobile_hash_secret($origin, 'login-origin');
	$originHit = cpcc_mobile_rate_hit($db, 'login_origin', $originBucket, 900, 20);
	if (empty($originHit['allowed'])) cpcc_mobile_throw(429, 'rate_limited', array(), $originHit['retry_after']);
	$fingerprint = cpcc_mobile_hash_secret(mb_strtolower((string) $login, 'UTF-8')."\0".$origin, 'login-account');
	$status = cpcc_mobile_rate_status($db, 'login_account', $fingerprint, 900, 5);
	if (empty($status['allowed'])) cpcc_mobile_throw(429, 'rate_limited', array(), $status['retry_after']);
	return $fingerprint;
}

function cpcc_mobile_login_rate_failure($db, $fingerprint)
{
	$hit = cpcc_mobile_rate_hit($db, 'login_account', (string) $fingerprint, 900, 5);
	return $hit;
}

function cpcc_mobile_validate_login_input($data)
{
	$login = isset($data['login']) ? (string) $data['login'] : '';
	$password = isset($data['password']) ? (string) $data['password'] : '';
	$deviceId = isset($data['device_id']) ? (string) $data['device_id'] : '';
	$deviceName = isset($data['device_name']) ? trim((string) $data['device_name']) : '';
	$invalid = array();
	if ($login === '' || mb_strlen($login, 'UTF-8') > 190) $invalid[] = 'login';
	if ($password === '' || mb_strlen($password, 'UTF-8') > 1024) $invalid[] = 'password';
	if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $deviceId)) $invalid[] = 'device_id';
	if (mb_strlen($deviceName, 'UTF-8') > 80) $invalid[] = 'device_name';
	if (!empty($invalid)) cpcc_mobile_throw(422, 'validation', $invalid);
	return array($login, $password, strtolower($deviceId), $deviceName);
}

function cpcc_mobile_find_login_candidate($db, $login)
{
	$sql = "SELECT u.rowid,u.login,u.entity,u.fk_soc,u.admin,u.statut,s.entity AS soc_entity,s.status AS soc_status,s.nom AS company_name";
	$sql .= " FROM ".MAIN_DB_PREFIX."user u INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=u.fk_soc";
	$sql .= " WHERE u.login='".$db->escape((string) $login)."' AND u.statut=1 AND u.admin=0 AND u.fk_soc>0 AND s.status=1 AND s.client IN (1,3)";
	$sql .= " ORDER BY u.rowid ASC LIMIT 2";
	$result = cpcc_mobile_query_or_throw($db, $sql);
	if ($db->num_rows($result) !== 1) return null;
	$row = $db->fetch_object($result);
	if (!$row) return null;
	$row->resolved_entity = (int) $row->entity > 0 ? (int) $row->entity : (int) $row->soc_entity;
	if ((int) $row->resolved_entity <= 0 || (int) $row->soc_entity !== (int) $row->resolved_entity) return null;
	return $row;
}

function cpcc_mobile_verify_credentials($db, $login, $password)
{
	global $dolibarr_main_authentication;
	$candidate = cpcc_mobile_find_login_candidate($db, $login);
	$dummyHash = '$2y$10$wH8QvY7YtL2d7RrQ7I4X6eWd7Y5Uyl7plYzYIcbBpT5F5hHq2gVbe';
	if (!$candidate) {
		password_verify((string) $password, $dummyHash);
		return null;
	}
	$authMode = empty($dolibarr_main_authentication) || $dolibarr_main_authentication === 'openid_connect' ? 'dolibarr' : $dolibarr_main_authentication;
	if ($authMode === 'forceuser') return null;
	$authModes = explode(',', $authMode);
	include_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
	$verifiedLogin = checkLoginPassEntity((string) $login, (string) $password, (int) $candidate->resolved_entity, $authModes, 'api');
	if ($verifiedLogin === '--bad-login-validity--' || $verifiedLogin === '' || $verifiedLogin !== (string) $candidate->login) return null;
	return $candidate;
}

function cpcc_mobile_issue_session($db, $candidate, $deviceId, $deviceName)
{
	$now = cpcc_mobile_now_sql();
	$familyPublic = 'fam_'.cpcc_mobile_base64url_encode(random_bytes(18));
	$deviceHash = cpcc_mobile_hash_secret((string) $deviceId, 'device-id');
	$absolute = cpcc_mobile_now_sql(CPCC_MOBILE_REFRESH_ABSOLUTE_TTL);
	$inactive = cpcc_mobile_now_sql(CPCC_MOBILE_REFRESH_INACTIVITY_TTL);
	$db->begin();
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_mobile_token_family (family_public_id,entity,fk_user,fk_soc,device_id_hash,device_name,current_generation,date_creation,last_refresh_at,inactivity_expires_at,absolute_expires_at) VALUES (";
	$sql .= "'".$db->escape($familyPublic)."',".(int) $candidate->resolved_entity.",".(int) $candidate->rowid.",".(int) $candidate->fk_soc.",'".$db->escape($deviceHash)."',";
	$sql .= $deviceName === '' ? 'NULL' : "'".$db->escape($deviceName)."'";
	$sql .= ",1,'".$now."','".$now."','".$inactive."','".$absolute."')";
	if (!$db->query($sql)) { $db->rollback(); cpcc_mobile_throw(500, 'server_error'); }
	$familyId = (int) $db->last_insert_id(MAIN_DB_PREFIX.'cpcc_mobile_token_family');
	$tokens = cpcc_mobile_insert_token_pair($db, $familyId, 1, $absolute);
	if (!$tokens) { $db->rollback(); cpcc_mobile_throw(500, 'server_error'); }
	$db->commit();
	return array(
		'access_token' => $tokens['access_token'],
		'refresh_token' => $tokens['refresh_token'],
		'token_type' => 'Bearer',
		'expires_in' => CPCC_MOBILE_ACCESS_TTL,
		'refresh_expires_at' => cpcc_mobile_iso($absolute),
	);
}

function cpcc_mobile_insert_token_pair($db, $familyId, $generation, $absoluteExpires)
{
	$access = cpcc_mobile_random_token('cpa_');
	$refresh = cpcc_mobile_random_token('cpr_');
	$now = cpcc_mobile_now_sql();
	$accessExpires = cpcc_mobile_now_sql(CPCC_MOBILE_ACCESS_TTL);
	$refreshExpires = $absoluteExpires;
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_mobile_access_token (fk_family,token_hash,generation,date_creation,expires_at) VALUES (".(int) $familyId.",'".$db->escape(cpcc_mobile_hash_secret($access, 'access-token'))."',".(int) $generation.",'".$now."','".$accessExpires."')";
	if (!$db->query($sql)) return false;
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_mobile_refresh_token (fk_family,token_hash,generation,date_creation,expires_at) VALUES (".(int) $familyId.",'".$db->escape(cpcc_mobile_hash_secret($refresh, 'refresh-token'))."',".(int) $generation.",'".$now."','".$db->escape($refreshExpires)."')";
	if (!$db->query($sql)) return false;
	return array('access_token' => $access, 'refresh_token' => $refresh);
}

function cpcc_mobile_revoke_family($db, $familyId, $reason)
{
	$now = cpcc_mobile_now_sql();
	$reason = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string) $reason)), 0, 32);
	if ($reason === '') $reason = 'revoked';
	$queries = array(
		"UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_token_family SET revoked_at=COALESCE(revoked_at,'".$now."'),revoke_reason='".$db->escape($reason)."' WHERE rowid=".(int) $familyId,
		"UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_access_token SET revoked_at=COALESCE(revoked_at,'".$now."') WHERE fk_family=".(int) $familyId,
		"UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_refresh_token SET revoked_at=COALESCE(revoked_at,'".$now."') WHERE fk_family=".(int) $familyId,
	);
	foreach ($queries as $sql) if (!$db->query($sql)) return false;
	return true;
}

function cpcc_mobile_refresh_session($db, $refreshToken, $deviceId)
{
	if (!preg_match('/^cpr_[A-Za-z0-9_-]{40,120}$/', (string) $refreshToken)) cpcc_mobile_throw(401, 'session_expired');
	if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $deviceId)) cpcc_mobile_throw(422, 'validation', array('device_id'));
	$tokenHash = cpcc_mobile_hash_secret((string) $refreshToken, 'refresh-token');
	$rateBucket = cpcc_mobile_hash_secret($tokenHash."\0".cpcc_mobile_origin(), 'refresh-rate');
	$rate = cpcc_mobile_rate_hit($db, 'refresh', $rateBucket, 300, 10);
	if (empty($rate['allowed'])) cpcc_mobile_throw(429, 'rate_limited', array(), $rate['retry_after']);
	$db->begin();
	$sql = "SELECT r.rowid AS refresh_id,r.fk_family,r.generation,r.expires_at,r.used_at,r.revoked_at AS refresh_revoked,f.family_public_id,f.entity,f.fk_user,f.fk_soc,f.device_id_hash,f.current_generation,f.date_creation AS family_created,f.inactivity_expires_at,f.absolute_expires_at,f.revoked_at AS family_revoked";
	$sql .= " FROM ".MAIN_DB_PREFIX."cpcc_mobile_refresh_token r INNER JOIN ".MAIN_DB_PREFIX."cpcc_mobile_token_family f ON f.rowid=r.fk_family";
	$sql .= " WHERE r.token_hash='".$db->escape($tokenHash)."' FOR UPDATE";
	$result = $db->query($sql);
	$row = $result ? $db->fetch_object($result) : null;
	if (!$row) { $db->rollback(); cpcc_mobile_throw(401, 'session_expired'); }
	$deviceHash = cpcc_mobile_hash_secret(strtolower((string) $deviceId), 'device-id');
	if (!hash_equals((string) $row->device_id_hash, $deviceHash)) { cpcc_mobile_revoke_family($db, (int) $row->fk_family, 'device_mismatch'); $db->commit(); cpcc_mobile_throw(401, 'session_expired'); }
	if (!empty($row->used_at) || !empty($row->refresh_revoked)) { cpcc_mobile_revoke_family($db, (int) $row->fk_family, 'refresh_reuse'); $db->commit(); cpcc_mobile_throw(409, 'conflict'); }
	$now = time();
	if (!empty($row->family_revoked) || strtotime((string) $row->expires_at.' UTC') <= $now || strtotime((string) $row->absolute_expires_at.' UTC') <= $now || strtotime((string) $row->inactivity_expires_at.' UTC') <= $now) {
		cpcc_mobile_revoke_family($db, (int) $row->fk_family, 'expired'); $db->commit(); cpcc_mobile_throw(401, 'session_expired');
	}
	$context = cpcc_mobile_reload_customer($db, (int) $row->fk_user, (int) $row->entity, (int) $row->fk_soc, (string) $row->family_created);
	if (!$context) { cpcc_mobile_revoke_family($db, (int) $row->fk_family, 'user_invalid'); $db->commit(); cpcc_mobile_throw(401, 'session_expired'); }
	$generation = (int) $row->generation + 1;
	$nowSql = cpcc_mobile_now_sql();
	$inactive = cpcc_mobile_now_sql(CPCC_MOBILE_REFRESH_INACTIVITY_TTL);
	if (!$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_refresh_token SET used_at='".$nowSql."' WHERE rowid=".(int) $row->refresh_id." AND used_at IS NULL")) { $db->rollback(); cpcc_mobile_throw(500, 'server_error'); }
	if (!$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_token_family SET current_generation=".$generation.",last_refresh_at='".$nowSql."',inactivity_expires_at='".$inactive."' WHERE rowid=".(int) $row->fk_family." AND revoked_at IS NULL")) { $db->rollback(); cpcc_mobile_throw(500, 'server_error'); }
	$tokens = cpcc_mobile_insert_token_pair($db, (int) $row->fk_family, $generation, (string) $row->absolute_expires_at);
	if (!$tokens) { $db->rollback(); cpcc_mobile_throw(500, 'server_error'); }
	$db->commit();
	return array('access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'], 'token_type' => 'Bearer', 'expires_in' => CPCC_MOBILE_ACCESS_TTL, 'refresh_expires_at' => cpcc_mobile_iso($row->absolute_expires_at));
}

function cpcc_mobile_reload_customer($db, $userId, $entity, $socid, $sessionCreatedAt = '')
{
	$sql = "SELECT u.rowid,u.login,u.firstname,u.lastname,u.entity,u.fk_soc,u.admin,u.statut,u.datelastpassvalidation,u.flagdelsessionsbefore,s.entity AS soc_entity,s.status AS soc_status,s.nom AS company_name";
	$sql .= " FROM ".MAIN_DB_PREFIX."user u INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=u.fk_soc";
	$sql .= " WHERE u.rowid=".(int) $userId." AND u.statut=1 AND u.admin=0 AND u.fk_soc=".(int) $socid;
	$sql .= " AND s.rowid=".(int) $socid." AND s.entity=".(int) $entity." AND s.status=1 AND s.client IN (1,3)";
	$result = $db->query($sql);
	$row = $result ? $db->fetch_object($result) : null;
	if (!$row) return null;
	$userEntity = (int) $row->entity > 0 ? (int) $row->entity : (int) $row->soc_entity;
	if ($userEntity !== (int) $entity) return null;
	if ($sessionCreatedAt !== '') {
		$created = strtotime((string) $sessionCreatedAt.' UTC');
		$passwordChanged = !empty($row->datelastpassvalidation) ? strtotime((string) $row->datelastpassvalidation.' UTC') : 0;
		$invalidateBefore = !empty($row->flagdelsessionsbefore) ? strtotime((string) $row->flagdelsessionsbefore.' UTC') : 0;
		if (($passwordChanged > 0 && $passwordChanged > $created) || ($invalidateBefore > 0 && $created < $invalidateBefore)) return null;
	}
	return array(
		'user_id' => (int) $row->rowid,
		'entity' => (int) $entity,
		'socid' => (int) $socid,
		'login' => (string) $row->login,
		'display_name' => trim((string) $row->firstname.' '.(string) $row->lastname) !== '' ? trim((string) $row->firstname.' '.(string) $row->lastname) : (string) $row->login,
		'company_name' => (string) $row->company_name,
	);
}

function cpcc_mobile_access_context($db)
{
	$token = cpcc_mobile_bearer_token();
	if (!preg_match('/^cpa_[A-Za-z0-9_-]{40,120}$/', $token)) cpcc_mobile_throw(401, 'session_expired');
	$hash = cpcc_mobile_hash_secret($token, 'access-token');
	$sql = "SELECT a.rowid AS access_id,a.fk_family,a.expires_at,a.revoked_at AS access_revoked,f.entity,f.fk_user,f.fk_soc,f.date_creation AS family_created,f.inactivity_expires_at,f.absolute_expires_at,f.revoked_at AS family_revoked";
	$sql .= " FROM ".MAIN_DB_PREFIX."cpcc_mobile_access_token a INNER JOIN ".MAIN_DB_PREFIX."cpcc_mobile_token_family f ON f.rowid=a.fk_family";
	$sql .= " WHERE a.token_hash='".$db->escape($hash)."' LIMIT 1";
	$result = $db->query($sql);
	$row = $result ? $db->fetch_object($result) : null;
	$now = time();
	if (!$row || !empty($row->access_revoked) || !empty($row->family_revoked) || strtotime((string) $row->expires_at.' UTC') <= $now || strtotime((string) $row->absolute_expires_at.' UTC') <= $now || strtotime((string) $row->inactivity_expires_at.' UTC') <= $now) cpcc_mobile_throw(401, 'session_expired');
	$context = cpcc_mobile_reload_customer($db, (int) $row->fk_user, (int) $row->entity, (int) $row->fk_soc, (string) $row->family_created);
	if (!$context) cpcc_mobile_throw(401, 'session_expired');
	$context['family_id'] = (int) $row->fk_family;
	$context['access_id'] = (int) $row->access_id;
	$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_access_token SET last_seen_at='".cpcc_mobile_now_sql()."' WHERE rowid=".(int) $row->access_id);
	return $context;
}

function cpcc_mobile_logout($db, $context)
{
	$db->begin();
	if (!cpcc_mobile_revoke_family($db, (int) $context['family_id'], 'logout')) { $db->rollback(); cpcc_mobile_throw(500, 'server_error'); }
	$db->commit();
}

function cpcc_mobile_public_prefix($resource)
{
	$prefixes = array('user' => 'usr', 'project' => 'prj', 'shipment' => 'shp', 'order' => 'ord', 'invoice' => 'inv', 'transaction' => 'txn', 'document' => 'doc', 'commercial_document' => 'cmd', 'report' => 'rpt');
	return isset($prefixes[$resource]) ? $prefixes[$resource] : 'obj';
}

function cpcc_mobile_public_id($db, $resource, $context, $objectId)
{
	$resource = (string) $resource;
	$entity = (int) $context['entity'];
	$socid = (int) $context['socid'];
	$objectId = (int) $objectId;
	if ($objectId <= 0) return null;
	$sql = "SELECT public_id FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE resource_type='".$db->escape($resource)."' AND entity=".$entity." AND fk_soc=".$socid." AND object_id=".$objectId." LIMIT 1";
	$result = cpcc_mobile_query_or_throw($db, $sql);
	$row = $db->fetch_object($result);
	if ($row) return (string) $row->public_id;
	for ($attempt = 0; $attempt < 3; $attempt++) {
		$publicId = cpcc_mobile_public_prefix($resource).'_'.cpcc_mobile_base64url_encode(random_bytes(18));
		$insert = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."cpcc_mobile_public_id (public_id,resource_type,entity,fk_soc,object_id,date_creation) VALUES ('".$db->escape($publicId)."','".$db->escape($resource)."',".$entity.",".$socid.",".$objectId.",'".cpcc_mobile_now_sql()."')";
		if (!$db->query($insert)) cpcc_mobile_throw(500, 'server_error');
		$result = cpcc_mobile_query_or_throw($db, $sql);
		$row = $db->fetch_object($result);
		if ($row) return (string) $row->public_id;
	}
	cpcc_mobile_throw(500, 'server_error');
}

function cpcc_mobile_resolve_public_id($db, $resource, $context, $publicId)
{
	$prefix = cpcc_mobile_public_prefix($resource);
	if (!preg_match('/^'.preg_quote($prefix, '/').'_[A-Za-z0-9_-]{20,80}$/', (string) $publicId)) return null;
	$sql = "SELECT object_id FROM ".MAIN_DB_PREFIX."cpcc_mobile_public_id WHERE public_id='".$db->escape((string) $publicId)."'";
	$sql .= " AND resource_type='".$db->escape((string) $resource)."' AND entity=".(int) $context['entity']." AND fk_soc=".(int) $context['socid']." LIMIT 1";
	$result = $db->query($sql);
	$row = $result ? $db->fetch_object($result) : null;
	return $row ? (int) $row->object_id : null;
}

function cpcc_mobile_list_limit($limit)
{
	if ($limit === null || $limit === '') return CPCC_MOBILE_DEFAULT_LIMIT;
	if (!is_numeric($limit) || (int) $limit < 1 || (int) $limit > CPCC_MOBILE_MAX_LIMIT) cpcc_mobile_throw(422, 'validation', array('limit'));
	return (int) $limit;
}

function cpcc_mobile_cursor_key()
{
	return hash('sha256', cpcc_mobile_pepper()."\0cursor", true);
}

function cpcc_mobile_cursor_encode($payload)
{
	$plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES);
	$key = cpcc_mobile_cursor_key();
	if (function_exists('sodium_crypto_secretbox')) {
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		return cpcc_mobile_base64url_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $key));
	}
	$iv = random_bytes(12); $tag = '';
	$cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'm017', 16);
	if ($cipher === false) cpcc_mobile_throw(500, 'server_error');
	return cpcc_mobile_base64url_encode($iv.$tag.$cipher);
}

function cpcc_mobile_cursor_decode($cursor, $resource, $context, $filterHash, $limit)
{
	if ($cursor === null || $cursor === '') return null;
	if (strlen((string) $cursor) > 512) cpcc_mobile_throw(404, 'not_found');
	$binary = cpcc_mobile_base64url_decode($cursor);
	if ($binary === false) cpcc_mobile_throw(404, 'not_found');
	$key = cpcc_mobile_cursor_key(); $plaintext = false;
	if (function_exists('sodium_crypto_secretbox_open')) {
		$nonceBytes = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if (strlen($binary) > $nonceBytes) $plaintext = sodium_crypto_secretbox_open(substr($binary, $nonceBytes), substr($binary, 0, $nonceBytes), $key);
	} elseif (strlen($binary) > 28) {
		$plaintext = openssl_decrypt(substr($binary, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($binary, 0, 12), substr($binary, 12, 16), 'm017');
	}
	$data = $plaintext !== false ? json_decode($plaintext, true) : null;
	$valid = is_array($data) && isset($data['v'], $data['resource'], $data['entity'], $data['socid'], $data['filter'], $data['limit'], $data['last']);
	$valid = $valid && (int) $data['v'] === 1 && (string) $data['resource'] === (string) $resource && (int) $data['entity'] === (int) $context['entity'] && (int) $data['socid'] === (int) $context['socid'];
	$valid = $valid && hash_equals((string) $data['filter'], (string) $filterHash) && (int) $data['limit'] === (int) $limit;
	if (!$valid) cpcc_mobile_throw(404, 'not_found');
	return $data['last'];
}

function cpcc_mobile_filter_hash($resource, $filters)
{
	ksort($filters);
	return cpcc_mobile_hash_secret((string) $resource."\0".json_encode($filters), 'list-filter');
}

function cpcc_mobile_page_meta($db, $resource, $context, $filters, $limit, $rows, $sortDateField, $sortIdField)
{
	$hasMore = count($rows) > $limit;
	if ($hasMore) array_pop($rows);
	$next = null;
	if ($hasMore && !empty($rows)) {
		$last = end($rows);
		$next = cpcc_mobile_cursor_encode(array('v' => 1, 'resource' => $resource, 'entity' => (int) $context['entity'], 'socid' => (int) $context['socid'], 'filter' => cpcc_mobile_filter_hash($resource, $filters), 'limit' => $limit, 'last' => array('date' => (string) $last[$sortDateField], 'id' => (int) $last[$sortIdField])));
	}
	return array('rows' => $rows, 'meta' => array('limit' => (int) $limit, 'has_more' => (bool) $hasMore, 'next_cursor' => $next));
}

function cpcc_mobile_etag($resource, $context, $version)
{
	$value = cpcc_mobile_hash_secret($resource."\0".(int) $context['entity']."\0".(int) $context['socid']."\0".(string) $version, 'etag');
	return '"m017-'.substr($value, 0, 32).'"';
}
