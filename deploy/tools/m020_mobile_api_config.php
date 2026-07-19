<?php
/** M020 production-only secret lifecycle and token-family revocation helper. */
if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M020_CONFIG=FAIL REASON=CLI_ONLY\n");
	exit(1);
}
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply', 'remove', 'revoke', 'debug-check'), true)) {
	fwrite(STDERR, "M020_CONFIG=FAIL REASON=COMMAND_NOT_ALLOWED\n");
	exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M020_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) {
	fwrite(STDERR, "M020_CONFIG=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

const M020_SECRET_NAME = 'CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_SECRET';
const M020_SECRET_ENTITY = 0;

global $db;

function m020ConfigFail($reason, $exitCode = 1)
{
	fwrite(STDERR, 'M020_CONFIG=FAIL REASON='.$reason."\n");
	exit($exitCode);
}

function m020ConfigRowExists($db)
{
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'const WHERE name='.$db->encrypt(M020_SECRET_NAME).' AND entity='.M020_SECRET_ENTITY;
	$result = $db->query($sql);
	if (!$result) m020ConfigFail('SECRET_LOOKUP_FAILED');
	return $db->num_rows($result) === 1;
}

function m020ConfigValue($db)
{
	return (string) dolibarr_get_const($db, M020_SECRET_NAME, M020_SECRET_ENTITY);
}

function m020TableExists($db, $table)
{
	$result = $db->query("SHOW TABLES LIKE '".$db->escape($table)."'");
	if (!$result) m020ConfigFail('TABLE_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

if ($argv[1] === 'debug-check') {
	if (function_exists('getDolGlobalInt') && getDolGlobalInt('MAIN_API_DEBUG') > 0) m020ConfigFail('MAIN_API_DEBUG_ENABLED');
	echo "M020_DEBUG_CHECK=PASS\n";
	exit(0);
}

if ($argv[1] === 'check') {
	if (!m020ConfigRowExists($db)) {
		echo "M020_CONFIG=ABSENT\n";
		exit(2);
	}
	if (strlen(m020ConfigValue($db)) < 32) m020ConfigFail('SECRET_INVALID');
	echo "M020_CONFIG=PRESENT\n";
	exit(0);
}

if ($argv[1] === 'apply') {
	if (m020ConfigRowExists($db)) {
		if (strlen(m020ConfigValue($db)) < 32) m020ConfigFail('SECRET_INVALID');
		echo "M020_CONFIG_APPLY=PASS STATE=UNCHANGED\n";
		exit(0);
	}
	$secret = base64_encode(random_bytes(48));
	if (strlen($secret) < 32 || dolibarr_set_const($db, M020_SECRET_NAME, $secret, 'chaine', 0, 'M020 encrypted customer mobile token secret', M020_SECRET_ENTITY) <= 0) m020ConfigFail('SECRET_CREATE_FAILED');
	unset($secret);
	if (!m020ConfigRowExists($db) || strlen(m020ConfigValue($db)) < 32) m020ConfigFail('SECRET_VERIFY_FAILED');
	echo "M020_CONFIG_APPLY=PASS STATE=CREATED\n";
	exit(0);
}

if ($argv[1] === 'remove') {
	if (m020ConfigRowExists($db) && dolibarr_del_const($db, M020_SECRET_NAME, M020_SECRET_ENTITY) <= 0) m020ConfigFail('SECRET_REMOVE_FAILED');
	if (m020ConfigRowExists($db)) m020ConfigFail('SECRET_REMOVE_VERIFY_FAILED');
	echo "M020_CONFIG_REMOVE=PASS\n";
	exit(0);
}

$tables = array(
	MAIN_DB_PREFIX.'cpcc_mobile_token_family',
	MAIN_DB_PREFIX.'cpcc_mobile_access_token',
	MAIN_DB_PREFIX.'cpcc_mobile_refresh_token',
);
foreach ($tables as $table) if (!m020TableExists($db, $table)) m020ConfigFail('TOKEN_TABLE_ABSENT');
$now = gmdate('Y-m-d H:i:s');
$db->begin();
$queries = array(
	"UPDATE ".$tables[0]." SET revoked_at=COALESCE(revoked_at,'".$now."'),revoke_reason='deployment_rollback' WHERE revoked_at IS NULL",
	"UPDATE ".$tables[1]." SET revoked_at=COALESCE(revoked_at,'".$now."') WHERE revoked_at IS NULL",
	"UPDATE ".$tables[2]." SET revoked_at=COALESCE(revoked_at,'".$now."') WHERE revoked_at IS NULL",
);
foreach ($queries as $query) {
	if (!$db->query($query)) {
		$db->rollback();
		m020ConfigFail('TOKEN_REVOKE_FAILED');
	}
}
$db->commit();
echo "M020_TOKEN_REVOKE=PASS\n";

