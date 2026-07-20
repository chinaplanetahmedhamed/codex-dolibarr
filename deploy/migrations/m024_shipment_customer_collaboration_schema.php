<?php
/** Additive M024 customer shipment collaboration schema migration. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M024_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) { fwrite(STDERR, "M024_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M024_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M024_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

function m024QueryOrFail($db, $sql, $reason)
{
    $result = $db->query($sql);
    if (!$result) { fwrite(STDERR, 'M024_SCHEMA=FAIL REASON='.$reason."\n"); exit(1); }
    return $result;
}
function m024TableExists($db, $table)
{
    $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $table);
    $result = m024QueryOrFail($db, "SHOW TABLES LIKE '".$db->escape($pattern)."'", 'TABLE_CHECK_FAILED');
    return $db->num_rows($result) === 1;
}
function m024ColumnExists($db, $table, $column)
{
    $result = m024QueryOrFail($db, "SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($column)."'", 'COLUMN_CHECK_FAILED');
    return $db->num_rows($result) === 1;
}
function m024Verify($db)
{
    $documentTable = MAIN_DB_PREFIX.'cpl_shipment_public_document';
    $eventTable = MAIN_DB_PREFIX.'cpl_shipment_customer_event';
    if (!m024TableExists($db, $documentTable) || !m024TableExists($db, $eventTable)) return false;
    foreach (array('source_origin','response_status','response_note','fk_user_response','date_response') as $column) if (!m024ColumnExists($db, $documentTable, $column)) return false;
    foreach (array('entity','fk_shipment','fk_soc','fk_public_document','event_type','status_code','note_public','actor_type','fk_user','customer_visible','event_at') as $column) if (!m024ColumnExists($db, $eventTable, $column)) return false;
    return true;
}

if ($argv[1] === 'check') {
    if (!m024Verify($db)) { fwrite(STDERR, "M024_SCHEMA_CHECK=ABSENT\n"); exit(2); }
    echo "M024_SCHEMA_CHECK=PASS\n"; exit(0);
}

$documentTable = MAIN_DB_PREFIX.'cpl_shipment_public_document';
if (!m024TableExists($db, $documentTable)) { fwrite(STDERR, "M024_SCHEMA=FAIL REASON=PUBLIC_DOCUMENT_TABLE_ABSENT\n"); exit(1); }
$columns = array(
    'source_origin' => "varchar(16) NOT NULL DEFAULT 'staff' AFTER `item_kind`",
    'response_status' => "varchar(32) NOT NULL DEFAULT 'awaiting_confirmation' AFTER `source_origin`",
    'response_note' => "text NULL AFTER `response_status`",
    'fk_user_response' => "integer NULL AFTER `response_note`",
    'date_response' => "datetime NULL AFTER `fk_user_response`",
);
foreach ($columns as $column => $definition) {
    if (!m024ColumnExists($db, $documentTable, $column)) m024QueryOrFail($db, "ALTER TABLE `".$documentTable."` ADD COLUMN `".$column."` ".$definition, 'ADD_'.strtoupper($column).'_FAILED');
}
$eventTable = MAIN_DB_PREFIX.'cpl_shipment_customer_event';
m024QueryOrFail($db, "CREATE TABLE IF NOT EXISTS `".$eventTable."` (
 `rowid` bigint AUTO_INCREMENT PRIMARY KEY,
 `entity` integer NOT NULL DEFAULT 1,
 `fk_shipment` integer NOT NULL,
 `fk_soc` integer NOT NULL,
 `fk_public_document` bigint NULL,
 `event_type` varchar(48) NOT NULL,
 `status_code` varchar(48) NULL,
 `note_public` text NULL,
 `actor_type` varchar(16) NOT NULL,
 `fk_user` integer NOT NULL,
 `customer_visible` tinyint NOT NULL DEFAULT 1,
 `event_at` datetime NOT NULL,
 `date_creation` datetime NOT NULL,
 `tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX `idx_cpl_customer_event_owner` (`entity`,`fk_soc`,`fk_shipment`,`customer_visible`,`event_at`),
 INDEX `idx_cpl_customer_event_document` (`entity`,`fk_public_document`,`event_at`),
 INDEX `idx_cpl_customer_event_type` (`entity`,`fk_shipment`,`event_type`,`status_code`)
) ENGINE=innodb", 'CREATE_EVENT_TABLE_FAILED');

if (!m024Verify($db)) { fwrite(STDERR, "M024_SCHEMA=FAIL REASON=POST_APPLY_VERIFY_FAILED\n"); exit(1); }
echo "M024_SCHEMA_APPLY=PASS\n";
