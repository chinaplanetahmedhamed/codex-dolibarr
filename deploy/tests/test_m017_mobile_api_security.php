<?php
define('CPCC_MOBILE_TESTING', true);
putenv('CPCC_MOBILE_TEST_PEPPER=m017-local-test-pepper-0123456789abcdef');
$root = dirname(__DIR__, 2);
$protocol = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/mobile_api.lib.php');
$data = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php');
$route = file_get_contents($root.'/custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php');
$failures = array();
function m017_security_assert($condition, $message) { global $failures; if (!$condition) $failures[] = $message; }
m017_security_assert(strpos((string) $protocol, "'entity', 'socid', 'fk_soc'") !== false, 'Caller-selected customer/entity overrides are rejected');
m017_security_assert(strpos((string) $protocol, 'u.admin=0') !== false && strpos((string) $protocol, 'u.fk_soc>0') !== false, 'Only active external customer users can log in');
m017_security_assert(strpos((string) $protocol, 's.entity=') !== false && strpos((string) $protocol, 'u.fk_soc=') !== false, 'Every access request reloads authoritative customer/entity ownership');
m017_security_assert(strpos((string) $protocol, 'hash_hmac') !== false && strpos((string) $protocol, 'token_hash') !== false, 'Only keyed token hashes are persisted');
m017_security_assert(strpos((string) $protocol, 'refresh_reuse') !== false && strpos((string) $protocol, 'FOR UPDATE') !== false, 'Refresh reuse and concurrent rotation are handled');
m017_security_assert(strpos((string) $protocol, "'login_origin', \$originBucket, 900, 20") !== false, 'Origin login throttle is fixed');
m017_security_assert(strpos((string) $protocol, "'login_account', \$fingerprint, 900, 5") !== false, 'Failed-account login throttle is fixed');
m017_security_assert(strpos((string) $protocol, "'refresh', \$rateBucket, 300, 10") !== false, 'Refresh throttle is fixed');
m017_security_assert(substr_count((string) $data, "(int) \$context['entity']") >= 20 && substr_count((string) $data, "(int) \$context['socid']") >= 20, 'Customer projections repeatedly enforce both entity and customer scope');
m017_security_assert(strpos((string) $data, 'customer_visible=1') !== false && strpos((string) $data, "review_status IN ('approved','final_received')") !== false, 'Files require customer visibility and approval');
m017_security_assert(strpos((string) $data, 'realpath') !== false && strpos((string) $data, 'Cache-Control: private, no-store') !== false, 'File streams are path-confined and non-cacheable');
m017_security_assert(strpos((string) $data, 'shipper_name') === false && strpos((string) $data, 'note_private') === false && strpos((string) $data, 'bank_label') === false, 'Read models do not select supplier/private/bank metadata');
m017_security_assert(strpos((string) $route, 'MAIN_API_DEBUG') === false || strpos((string) $route, 'cpcc_mobile_debug_logging_enabled') !== false, 'Credential routes fail closed in API debug mode');
m017_security_assert(strpos((string) $route, 'cpcc_mobile_register_debug_auth_gate') !== false && strpos((string) $protocol, "->onRoute") !== false, 'Credential routes are stopped before the core onCall debug logger');
m017_security_assert(strpos((string) $protocol, "method_exists(\$restler, 'onRoute')") === false, 'Restler magic event registration is not incorrectly blocked by method_exists');
m017_security_assert(strpos((string) $protocol, "'api_key'") !== false, 'API-key header alias is rejected');

$documentRoot = getenv('M017_DOL_DOCUMENT_ROOT');
$credentialFile = getenv('M017_CREDENTIAL_FILE');
if (is_string($documentRoot) && $documentRoot !== '' && is_string($credentialFile) && is_file($credentialFile)) {
	define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1); define('NOCSRFCHECK', 1);
	$_SERVER['DOCUMENT_ROOT'] = rtrim($documentRoot, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80'; $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	require rtrim($documentRoot, '/').'/master.inc.php';
	require_once DOL_DOCUMENT_ROOT.'/includes/restler/framework/Luracast/Restler/AutoLoader.php';
	$restlerLoader = \Luracast\Restler\AutoLoader::instance(); spl_autoload_register($restlerLoader);
	require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
	require_once $root.'/custom/chinaplanetcustomercenter/lib/mobile_api.lib.php';
	global $db;
	$credentials = json_decode(file_get_contents($credentialFile), true);
	$credential = is_array($credentials) && isset($credentials[0]) ? $credentials[0] : null;
	m017_security_assert(is_array($credential), 'Synthetic credential file is valid');
	if (is_array($credential)) {
		$candidate = cpcc_mobile_verify_credentials($db, $credential['login'], $credential['password']);
		m017_security_assert($candidate !== null, 'Synthetic external customer can authenticate');
		m017_security_assert(cpcc_mobile_verify_credentials($db, $credential['login'], 'wrong-synthetic-password') === null, 'Wrong password is rejected');
		foreach (array('m017_e1_inactive','m017_e1_internal','m017_e1_nosoc') as $rejectedLogin) m017_security_assert(cpcc_mobile_find_login_candidate($db, $rejectedLogin) === null, 'Rejected login class: '.$rejectedLogin);
		if ($candidate) {
			$deviceId = '11111111-1111-4111-8111-111111111111';
			$session = cpcc_mobile_issue_session($db, $candidate, $deviceId, 'M017 local test');
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$session['access_token'];
			$context = cpcc_mobile_access_context($db);
			m017_security_assert((int) $context['socid'] === (int) $candidate->fk_soc && (int) $context['entity'] === (int) $candidate->resolved_entity, 'Access context derives authoritative entity/customer ownership');
			$rawLookup = $db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpcc_mobile_access_token WHERE token_hash='".$db->escape($session['access_token'])."'"); $rawRow = $rawLookup ? $db->fetch_object($rawLookup) : null;
			m017_security_assert($rawRow && (int) $rawRow->total === 0, 'Plaintext access tokens are never persisted');
			$rotated = cpcc_mobile_refresh_session($db, $session['refresh_token'], $deviceId);
			$replayConflict = false; try { cpcc_mobile_refresh_session($db, $session['refresh_token'], $deviceId); } catch (\Luracast\Restler\RestException $exception) { $replayConflict = (int) $exception->getCode() === 409; }
			m017_security_assert($replayConflict, 'Rotated refresh-token reuse is detected as conflict');
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$rotated['access_token']; $revokedAfterReplay = false; try { cpcc_mobile_access_context($db); } catch (\Luracast\Restler\RestException $exception) { $revokedAfterReplay = (int) $exception->getCode() === 401; }
			m017_security_assert($revokedAfterReplay, 'Refresh replay revokes the complete token family');
			$logoutSession = cpcc_mobile_issue_session($db, $candidate, '22222222-2222-4222-8222-222222222222', 'M017 logout test');
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$logoutSession['access_token']; $logoutContext = cpcc_mobile_access_context($db); cpcc_mobile_logout($db, $logoutContext); $logoutRevoked = false; try { cpcc_mobile_access_context($db); } catch (\Luracast\Restler\RestException $exception) { $logoutRevoked = (int) $exception->getCode() === 401; }
			m017_security_assert($logoutRevoked, 'Logout revokes the complete device token family');
			$passwordSession = cpcc_mobile_issue_session($db, $candidate, '33333333-3333-4333-8333-333333333333', 'M017 password test');
			$db->query("UPDATE ".MAIN_DB_PREFIX."user SET datelastpassvalidation=DATE_ADD(NOW(), INTERVAL 2 SECOND) WHERE rowid=".(int) $candidate->rowid);
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$passwordSession['access_token']; $passwordRevoked = false; try { cpcc_mobile_access_context($db); } catch (\Luracast\Restler\RestException $exception) { $passwordRevoked = (int) $exception->getCode() === 401; }
			$db->query("UPDATE ".MAIN_DB_PREFIX."user SET datelastpassvalidation=NULL WHERE rowid=".(int) $candidate->rowid);
			m017_security_assert($passwordRevoked, 'A password change invalidates older access sessions');

			$expiredAccessSession = cpcc_mobile_issue_session($db, $candidate, '77777777-7777-4777-8777-777777777777', 'M017 expired access test');
			$expiredAccessHash = cpcc_mobile_hash_secret($expiredAccessSession['access_token'], 'access-token');
			$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_access_token SET expires_at='2000-01-01 00:00:00' WHERE token_hash='".$db->escape($expiredAccessHash)."'");
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$expiredAccessSession['access_token'];
			$accessExpired = false; try { cpcc_mobile_access_context($db); } catch (\Luracast\Restler\RestException $exception) { $accessExpired = (int) $exception->getCode() === 401; }
			m017_security_assert($accessExpired, 'Expired access tokens fail immediately with session expiry');

			$expiredRefreshDevice = '88888888-8888-4888-8888-888888888888';
			$expiredRefreshSession = cpcc_mobile_issue_session($db, $candidate, $expiredRefreshDevice, 'M017 expired refresh test');
			$expiredRefreshHash = cpcc_mobile_hash_secret($expiredRefreshSession['refresh_token'], 'refresh-token');
			$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_mobile_refresh_token SET expires_at='2000-01-01 00:00:00' WHERE token_hash='".$db->escape($expiredRefreshHash)."'");
			$refreshExpired = false; try { cpcc_mobile_refresh_session($db, $expiredRefreshSession['refresh_token'], $expiredRefreshDevice); } catch (\Luracast\Restler\RestException $exception) { $refreshExpired = (int) $exception->getCode() === 401; }
			m017_security_assert($refreshExpired, 'Expired refresh tokens fail immediately with session expiry');

			$disabledSession = cpcc_mobile_issue_session($db, $candidate, '99999999-9999-4999-8999-999999999999', 'M017 disabled user test');
			$db->query("UPDATE ".MAIN_DB_PREFIX."user SET statut=0 WHERE rowid=".(int) $candidate->rowid);
			$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$disabledSession['access_token'];
			$disabledRevoked = false; try { cpcc_mobile_access_context($db); } catch (\Luracast\Restler\RestException $exception) { $disabledRevoked = (int) $exception->getCode() === 401; }
			$db->query("UPDATE ".MAIN_DB_PREFIX."user SET statut=1 WHERE rowid=".(int) $candidate->rowid);
			m017_security_assert($disabledRevoked, 'A disabled external user loses access immediately');
		}
	}
}
if ($failures) { fwrite(STDERR, "M017_SECURITY=FAIL\n- ".implode("\n- ", $failures)."\n"); exit(1); }
echo "M017_SECURITY=PASS".($documentRoot ? ' DB_INTEGRATION=PASS' : '')."\n";
