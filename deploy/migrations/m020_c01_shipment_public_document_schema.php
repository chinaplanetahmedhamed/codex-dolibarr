<?php
/** Additive M020-C01 Shipment Gallery publication registry migration. */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M020_C01_SCHEMA=FAIL REASON=CLI_ONLY\n");
	exit(1);
}
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) {
	fwrite(STDERR, "M020_C01_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n");
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
	fwrite(STDERR, "M020_C01_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

function m020C01QueryOrFail($db, $sql, $reason)
{
	$result = $db->query($sql);
	if (!$result) {
		fwrite(STDERR, 'M020_C01_SCHEMA=FAIL REASON='.$reason."\n");
		exit(1);
	}
	return $result;
}

function m020C01TableExists($db, $table)
{
	$result = m020C01QueryOrFail($db, "SHOW TABLES LIKE '".$db->escape($table)."'", 'TABLE_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

function m020C01ColumnExists($db, $table, $column)
{
	$result = m020C01QueryOrFail($db, "SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($column)."'", 'COLUMN_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

function m020C01Verify($db)
{
	$shipmentTable = MAIN_DB_PREFIX.'chinaplanetlogistics';
	$galleryTable = MAIN_DB_PREFIX.'cpl_shipment_public_document';
	if (!m020C01TableExists($db, $shipmentTable) || !m020C01TableExists($db, $galleryTable)) return false;
	$columns = array('entity','fk_shipment','fk_soc','storage_key','original_name','mime_type','file_size','file_sha256','item_kind','customer_visible','status','fk_user_publish','date_publication','tms');
	foreach ($columns as $column) if (!m020C01ColumnExists($db, $galleryTable, $column)) return false;
	return true;
}

if ($argv[1] === 'check') {
	if (!m020C01Verify($db)) {
		fwrite(STDERR, "M020_C01_SCHEMA_CHECK=ABSENT\n");
		exit(2);
	}
	echo "M020_C01_SCHEMA_CHECK=PASS\n";
	exit(0);
}

if (!m020C01TableExists($db, MAIN_DB_PREFIX.'chinaplanetlogistics')) {
	fwrite(STDERR, "M020_C01_SCHEMA=FAIL REASON=SHIPMENT_TABLE_ABSENT\n");
	exit(1);
}

$galleryTable = MAIN_DB_PREFIX.'cpl_shipment_public_document';
m020C01QueryOrFail($db, "CREATE TABLE IF NOT EXISTS `".$galleryTable."` (
	`rowid` bigint AUTO_INCREMENT PRIMARY KEY,
	`entity` integer NOT NULL DEFAULT 1,
	`fk_shipment` integer NOT NULL,
	`fk_soc` integer NOT NULL,
	`storage_key` varchar(512) NOT NULL,
	`original_name` varchar(255) NOT NULL,
	`mime_type` varchar(150) NOT NULL,
	`file_size` bigint NOT NULL DEFAULT 0,
	`file_sha256` char(64) NOT NULL,
	`item_kind` varchar(16) NOT NULL DEFAULT 'document',
	`customer_visible` tinyint NOT NULL DEFAULT 1,
	`status` tinyint NOT NULL DEFAULT 1,
	`fk_user_publish` integer NOT NULL,
	`fk_user_modif` integer NULL,
	`date_publication` datetime NOT NULL,
	`date_creation` datetime NOT NULL,
	`tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE INDEX `uk_cpl_gallery_file` (`entity`, `fk_shipment`, `storage_key`),
	INDEX `idx_cpl_gallery_owner` (`entity`, `fk_soc`, `fk_shipment`, `customer_visible`, `status`),
	INDEX `idx_cpl_gallery_updated` (`entity`, `fk_soc`, `tms`, `rowid`)
) ENGINE=innodb", 'CREATE_GALLERY_TABLE_FAILED');

if (!m020C01Verify($db)) {
	fwrite(STDERR, "M020_C01_SCHEMA=FAIL REASON=POST_APPLY_VERIFY_FAILED\n");
	exit(1);
}
echo "M020_C01_SCHEMA_APPLY=PASS\n";
