<?php
/** M068-C01 entity-scoped portal invitation HMAC lifecycle; never prints secret material. */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M068_C01_CONFIG=FAIL REASON=CLI_ONLY\n");
	exit(1);
}
$commands = array('check', 'apply', 'verify', 'rollback');
if ($argc !== 2 || !in_array($argv[1], $commands, true)) {
	fwrite(STDERR, "M068_C01_CONFIG=FAIL REASON=COMMAND_NOT_ALLOWED\n");
	exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$testMode = getenv('M068_C01_CONFIG_TEST_MODE') === '1';
if ($testMode) {
	$testRoot = (string) getenv('M068_C01_CONFIG_TEST_ROOT');
	if (!preg_match('#^/(?:private/)?tmp/(?:m068-c01-config-test\.[A-Za-z0-9]+|m068-c01-hmac-helper-test\.[A-Za-z0-9]+/config)$#D', $testRoot)
		|| !is_dir($testRoot) || is_link($testRoot)
		|| basename(__FILE__) !== 'm068_c01_portal_hmac_config.test.php'
		|| !is_file($testRoot.'/bootstrap.php') || is_link($testRoot.'/bootstrap.php')) {
		fwrite(STDERR, "M068_C01_CONFIG=FAIL REASON=TEST_MODE_INVALID\n");
		exit(1);
	}
	require $testRoot.'/bootstrap.php';
} else {
	$documentRoot = rtrim((string) (getenv('M068_C01_DOL_DOCUMENT_ROOT') ?: '/var/www/html'), '/');
	if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) {
		fwrite(STDERR, "M068_C01_CONFIG=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
		exit(1);
	}
	$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
	$_SERVER['SERVER_NAME'] = 'localhost';
	$_SERVER['SERVER_PORT'] = '80';
	require $documentRoot.'/master.inc.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
}

global $db, $conf;
const M068_C01_SECRET_NAME = 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY';
const M068_C01_SECRET_NOTE = 'M068-C01 owned server-side portal invitation HMAC secret';
const M068_C01_SECRET_LENGTH = 64;

function m068C01Fail($reason, $exitCode = 1)
{
	global $db;
	$transactionCleanupFailed = false;
	if (!empty($GLOBALS['m068C01TransactionActive']) && is_object($db)) {
		$transactionCleanupFailed = !$db->rollback();
		$GLOBALS['m068C01TransactionActive'] = false;
	}
	$lockReleaseFailed = !empty($GLOBALS['m068C01LockName']) && is_object($db) && !m068C01ReleaseLock($db);
	if ($transactionCleanupFailed && $lockReleaseFailed) $reason = 'TRANSACTION_AND_LOCK_CLEANUP_FAILED';
	elseif ($transactionCleanupFailed) $reason = 'TRANSACTION_ROLLBACK_CLEANUP_FAILED';
	elseif ($lockReleaseFailed) $reason = 'CONFIG_LOCK_RELEASE_FAILED';
	fwrite(STDERR, 'M068_C01_CONFIG=FAIL REASON='.$reason."\n");
	exit($exitCode);
}

function m068C01AcquireLock($db, $entity)
{
	$lockName = 'm068-c01:portal-hmac:entity:'.((int) $entity);
	$result = $db->query("SELECT GET_LOCK('".$db->escape($lockName)."',0) AS lock_acquired");
	$row = $result ? $db->fetch_object($result) : false;
	if (!$row || (int) $row->lock_acquired !== 1) m068C01Fail('CONFIG_LOCKED');
	$GLOBALS['m068C01LockName'] = $lockName;
}

function m068C01ReleaseLock($db)
{
	$lockName = isset($GLOBALS['m068C01LockName']) ? (string) $GLOBALS['m068C01LockName'] : '';
	if ($lockName === '') return true;
	$result = $db->query("SELECT RELEASE_LOCK('".$db->escape($lockName)."') AS lock_released");
	$row = $result ? $db->fetch_object($result) : false;
	if (!$row || (int) $row->lock_released !== 1) return false;
	$GLOBALS['m068C01LockName'] = '';
	return true;
}

function m068C01Begin($db)
{
	if (!$db->begin()) m068C01Fail('TRANSACTION_BEGIN_FAILED');
	$GLOBALS['m068C01TransactionActive'] = true;
}

function m068C01Rollback($db)
{
	if (!$db->rollback()) m068C01Fail('TRANSACTION_ROLLBACK_FAILED');
	$GLOBALS['m068C01TransactionActive'] = false;
}

function m068C01Commit($db)
{
	if (!$db->commit()) m068C01Fail('TRANSACTION_COMMIT_FAILED');
	$GLOBALS['m068C01TransactionActive'] = false;
	if (!m068C01ReleaseLock($db)) m068C01Fail('CONFIG_LOCK_RELEASE_FAILED');
}

function m068C01Rows($db, $lock = false)
{
	$sql = 'SELECT rowid,entity,value,type,visible,note FROM '.MAIN_DB_PREFIX.'const'
		." WHERE name='".$db->escape(M068_C01_SECRET_NAME)."' ORDER BY entity DESC,rowid".($lock ? ' FOR UPDATE' : '');
	$result = $db->query($sql);
	if (!$result) m068C01Fail('CONFIG_LOOKUP_FAILED');
	$rows = array();
	while ($row = $db->fetch_object($result)) $rows[] = $row;
	return $rows;
}

function m068C01Inspect($db, $entity, $lock = false)
{
	// Environment configuration would override the Dolibarr constant. Presence
	// alone is a conflict; its value is never inspected, copied, or printed.
	if ((string) getenv(M068_C01_SECRET_NAME) !== '') m068C01Fail('ENV_CONFIGURATION_CONFLICT');
	$rows = m068C01Rows($db, $lock);
	if (count($rows) === 0) return 'ABSENT';
	if (count($rows) !== 1) m068C01Fail('CONFIGURATION_AMBIGUOUS');
	$row = $rows[0];
	if ((int) $row->entity === 0) m068C01Fail('GLOBAL_ENTITY_CONFIGURATION');
	if ((int) $row->entity !== (int) $entity) m068C01Fail('FOREIGN_ENTITY_CONFIGURATION');
	if ((string) $row->type !== 'chaine' || (int) $row->visible !== 0 || (string) $row->note !== M068_C01_SECRET_NOTE) {
		m068C01Fail('UNOWNED_OR_INVALID_CONFIGURATION');
	}
	$stored = (string) $row->value;
	if (strncmp($stored, 'dolcrypt:', 9) !== 0) m068C01Fail('SECRET_NOT_ENCRYPTED_AT_REST');
	if (!function_exists('dolDecrypt')) m068C01Fail('SECRET_DECRYPTION_UNAVAILABLE');
	$plain = dolDecrypt($stored);
	unset($stored);
	if (!is_string($plain) || strlen($plain) !== M068_C01_SECRET_LENGTH) {
		unset($plain);
		m068C01Fail('SECRET_LENGTH_INVALID');
	}
	unset($plain);
	return 'READY_OWNED_DB';
}

$entity = isset($conf->entity) ? (int) $conf->entity : 0;
if ($entity <= 0) m068C01Fail('ENTITY_INVALID');

if ($argv[1] === 'check') {
	$state = m068C01Inspect($db, $entity);
	if ($state === 'ABSENT') {
		echo 'M068_C01_CONFIG=ABSENT ENTITY='.$entity." SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO\n";
		exit(2);
	}
	echo 'M068_C01_CONFIG=READY ENTITY='.$entity." SOURCE=OWNED_DB PRESENCE=YES LENGTH_OK=YES SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO\n";
	exit(0);
}

if ($argv[1] === 'verify') {
	if (m068C01Inspect($db, $entity) !== 'READY_OWNED_DB') m068C01Fail('OWNED_DB_SECRET_REQUIRED');
	echo 'M068_C01_CONFIG_VERIFY=PASS ENTITY='.$entity." PRESENCE=YES LENGTH_OK=YES SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO\n";
	exit(0);
}

if ($argv[1] === 'apply') {
	m068C01AcquireLock($db, $entity);
	m068C01Begin($db);
	if (m068C01Inspect($db, $entity, true) !== 'ABSENT') {
		m068C01Rollback($db);
		m068C01Fail('ABSENT_PRESTATE_REQUIRED');
	}
	$secret = base64_encode(random_bytes(48));
	if (strlen($secret) !== M068_C01_SECRET_LENGTH) {
		m068C01Rollback($db);
		unset($secret);
		m068C01Fail('SECRET_GENERATION_FAILED');
	}
	if (dolibarr_set_const($db, M068_C01_SECRET_NAME, $secret, 'chaine', 0, M068_C01_SECRET_NOTE, $entity) <= 0) {
		m068C01Rollback($db);
		unset($secret);
		m068C01Fail('SECRET_CREATE_FAILED');
	}
	if ($testMode && getenv('M068_C01_CONFIG_TEST_FAULT') === 'AFTER_SECRET') {
		m068C01Rollback($db);
		unset($secret);
		m068C01Fail('INJECTED_AFTER_SECRET');
	}
	if (m068C01Inspect($db, $entity, true) !== 'READY_OWNED_DB') {
		m068C01Rollback($db);
		unset($secret);
		m068C01Fail('POST_CREATE_VERIFY_FAILED');
	}
	m068C01Commit($db);
	unset($secret);
	if ($testMode && getenv('M068_C01_CONFIG_TEST_FAULT') === 'AFTER_COMMIT') m068C01Fail('INJECTED_AFTER_COMMIT');
	echo 'M068_C01_CONFIG_APPLY=PASS STATE=CREATED ENTITY='.$entity." PRESENCE=YES LENGTH_OK=YES SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO\n";
	exit(0);
}

m068C01AcquireLock($db, $entity);
m068C01Begin($db);
$state = m068C01Inspect($db, $entity, true);
if ($state === 'ABSENT') {
	m068C01Rollback($db);
	if (!m068C01ReleaseLock($db)) m068C01Fail('CONFIG_LOCK_RELEASE_FAILED');
	echo 'M068_C01_CONFIG_ROLLBACK=PASS STATE=UNCHANGED ENTITY='.$entity." SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO\n";
	exit(0);
}
if ($state !== 'READY_OWNED_DB') m068C01Fail('OWNED_DB_SECRET_REQUIRED');
if (dolibarr_del_const($db, M068_C01_SECRET_NAME, $entity) <= 0) {
	m068C01Rollback($db);
	m068C01Fail('ROLLBACK_DELETE_FAILED');
}
if (m068C01Inspect($db, $entity, true) !== 'ABSENT') {
	m068C01Rollback($db);
	m068C01Fail('ROLLBACK_VERIFY_FAILED');
}
m068C01Commit($db);
echo 'M068_C01_CONFIG_ROLLBACK=PASS STATE=REMOVED ENTITY='.$entity." PRESENCE=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO\n";
