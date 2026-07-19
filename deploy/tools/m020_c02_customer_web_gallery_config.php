<?php
/** Production-safe M020-C02 readiness check. It never lists or changes publications. */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M020_C02_CONFIG=FAIL REASON=CLI_ONLY\n");
	exit(1);
}
if ($argc !== 2 || $argv[1] !== 'check') {
	fwrite(STDERR, "M020_C02_CONFIG=FAIL REASON=COMMAND_NOT_ALLOWED\n");
	exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M020_C02_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) {
	fwrite(STDERR, "M020_C02_CONFIG=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

$table = MAIN_DB_PREFIX.'cpl_shipment_public_document';
$result = $db->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."'");
$row = $result ? $db->fetch_object($result) : null;
if (!$row || (int) $row->n !== 1) {
	fwrite(STDERR, "M020_C02_CONFIG=FAIL REASON=SCHEMA_ABSENT\n");
	exit(2);
}

$secret = function_exists('getDolGlobalString') ? (string) getDolGlobalString('CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_SECRET', '') : '';
if (strlen($secret) < 32) {
	fwrite(STDERR, "M020_C02_CONFIG=FAIL REASON=TOKEN_SECRET_NOT_READY\n");
	exit(3);
}

echo "M020_C02_CONFIG_CHECK=PASS\n";
