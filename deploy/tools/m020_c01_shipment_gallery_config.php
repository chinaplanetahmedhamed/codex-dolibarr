<?php
/** Production-safe M020-C01 schema check and rollback revocation tool. */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=CLI_ONLY\n");
	exit(1);
}
if ($argc < 2 || $argc > 3 || !in_array($argv[1], array('check', 'deactivate-since'), true)) {
	fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=COMMAND_NOT_ALLOWED\n");
	exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M020_C01_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) {
	fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

function m020C01ConfigTableReady($db)
{
	$table = MAIN_DB_PREFIX.'cpl_shipment_public_document';
	$result = $db->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."'");
	$row = $result ? $db->fetch_object($result) : null;
	return $row && (int) $row->n === 1;
}

if (!m020C01ConfigTableReady($db)) {
	fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=SCHEMA_ABSENT\n");
	exit(2);
}

if ($argv[1] === 'check') {
	if ($argc !== 2) {
		fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=COMMAND_NOT_ALLOWED\n");
		exit(1);
	}
	echo "M020_C01_CONFIG_CHECK=PASS\n";
	exit(0);
}

if ($argc !== 3 || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $argv[2])) {
	fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=TIMESTAMP_INVALID\n");
	exit(1);
}
$timestamp = gmdate('Y-m-d H:i:s', strtotime($argv[2]));
$sql = "UPDATE ".MAIN_DB_PREFIX."cpl_shipment_public_document SET customer_visible=0,status=0 WHERE date_creation>='".$db->escape($timestamp)."' AND (customer_visible<>0 OR status<>0)";
if (!$db->query($sql)) {
	fwrite(STDERR, "M020_C01_CONFIG=FAIL REASON=DEACTIVATE_FAILED\n");
	exit(1);
}
echo "M020_C01_CONFIG_DEACTIVATE=PASS\n";
