<?php
/**
 * M017 additive local schema migration for the customer mobile API.
 *
 * Usage inside a local Dolibarr runtime/container:
 *   php deploy/migrations/m017_customer_mobile_api_schema.php check
 *   php deploy/migrations/m017_customer_mobile_api_schema.php apply
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M017_SCHEMA=FAIL REASON=CLI_ONLY\n");
	exit(1);
}
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) {
	fwrite(STDERR, "M017_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n");
	exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M017_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) {
	fwrite(STDERR, "M017_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';

global $db;

function m017SchemaFail($reason)
{
	fwrite(STDERR, 'M017_SCHEMA=FAIL REASON='.$reason."\n");
	exit(1);
}

function m017SchemaQuery($db, $sql, $reason)
{
	$result = $db->query($sql);
	if (!$result) m017SchemaFail($reason);
	return $result;
}

function m017SchemaTableExists($db, $table)
{
	$result = m017SchemaQuery($db, "SHOW TABLES LIKE '".$db->escape($table)."'", 'TABLE_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

function m017SchemaColumnExists($db, $table, $column)
{
	$result = m017SchemaQuery($db, "SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($column)."'", 'COLUMN_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

function m017SchemaVerify($db)
{
	$required = array(
		'cpcc_mobile_token_family' => array('family_public_id', 'entity', 'fk_user', 'fk_soc', 'device_id_hash', 'current_generation', 'inactivity_expires_at', 'absolute_expires_at', 'revoked_at'),
		'cpcc_mobile_access_token' => array('fk_family', 'token_hash', 'generation', 'expires_at', 'revoked_at'),
		'cpcc_mobile_refresh_token' => array('fk_family', 'token_hash', 'generation', 'expires_at', 'used_at', 'revoked_at'),
		'cpcc_mobile_public_id' => array('public_id', 'resource_type', 'entity', 'fk_soc', 'object_id'),
		'cpcc_mobile_rate_limit' => array('route_key', 'bucket_hash', 'window_start', 'attempt_count', 'blocked_until'),
	);
	foreach ($required as $suffix => $columns) {
		$table = MAIN_DB_PREFIX.$suffix;
		if (!m017SchemaTableExists($db, $table)) return false;
		foreach ($columns as $column) if (!m017SchemaColumnExists($db, $table, $column)) return false;
	}
	return true;
}

if ($argv[1] === 'check') {
	if (!m017SchemaVerify($db)) {
		fwrite(STDERR, "M017_SCHEMA_CHECK=ABSENT\n");
		exit(2);
	}
	echo "M017_SCHEMA_CHECK=PASS\n";
	exit(0);
}

$repoRoot = dirname(__DIR__, 2);
$sqlFiles = array(
	'llx_cpcc_mobile_token_family.sql',
	'llx_cpcc_mobile_access_token.sql',
	'llx_cpcc_mobile_refresh_token.sql',
	'llx_cpcc_mobile_public_id.sql',
	'llx_cpcc_mobile_rate_limit.sql',
);
foreach ($sqlFiles as $sqlFile) {
	$path = $repoRoot.'/custom/chinaplanetcustomercenter/sql/'.$sqlFile;
	if (!is_file($path)) m017SchemaFail('SQL_SOURCE_MISSING');
	$sql = trim((string) file_get_contents($path));
	$sql = preg_replace('/\bllx_/', MAIN_DB_PREFIX, $sql);
	if ($sql === '' || preg_match('/^\s*(DROP|TRUNCATE|DELETE|UPDATE|ALTER)\b/im', $sql)) m017SchemaFail('SQL_SOURCE_FORBIDDEN');
	m017SchemaQuery($db, $sql, 'CREATE_TABLE_FAILED');
}

if (!m017SchemaVerify($db)) m017SchemaFail('POST_APPLY_VERIFY_FAILED');
echo "M017_SCHEMA_APPLY=PASS\n";
