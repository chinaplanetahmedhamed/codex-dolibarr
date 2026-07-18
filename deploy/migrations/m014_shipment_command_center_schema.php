<?php
/**
 * M014 additive schema migration for the M011 shipment command center.
 *
 * Usage inside a Dolibarr container:
 *   php deploy/migrations/m014_shipment_command_center_schema.php check
 *   php deploy/migrations/m014_shipment_command_center_schema.php apply
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "M014_SCHEMA=FAIL REASON=CLI_ONLY\n");
	exit(1);
}

if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) {
	fwrite(STDERR, "M014_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n");
	exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M014_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') {
	$documentRoot = '/var/www/html';
}
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) {
	fwrite(STDERR, "M014_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n");
	exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';

global $db;

function m014QueryOrFail($db, $sql, $reason)
{
	$result = $db->query($sql);
	if (!$result) {
		fwrite(STDERR, 'M014_SCHEMA=FAIL REASON='.$reason."\n");
		exit(1);
	}
	return $result;
}

function m014TableExists($db, $table)
{
	$result = m014QueryOrFail($db, "SHOW TABLES LIKE '".$db->escape($table)."'", 'TABLE_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

function m014ColumnExists($db, $table, $column)
{
	$result = m014QueryOrFail($db, "SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($column)."'", 'COLUMN_CHECK_FAILED');
	return $db->num_rows($result) === 1;
}

function m014VerifySchema($db)
{
	$shipmentTable = MAIN_DB_PREFIX.'chinaplanetlogistics';
	$eventTable = MAIN_DB_PREFIX.'cpl_shipment_event';
	$requirementTable = MAIN_DB_PREFIX.'cpl_document_requirement';

	if (!m014TableExists($db, $shipmentTable)) {
		return false;
	}
	if (!m014ColumnExists($db, $shipmentTable, 'date_atd')) {
		return false;
	}
	if (!m014TableExists($db, $eventTable) || !m014TableExists($db, $requirementTable)) {
		return false;
	}

	$requiredColumns = array(
		$eventTable => array('entity', 'fk_shipment', 'fk_soc', 'event_type', 'milestone_code', 'event_at', 'customer_visible'),
		$requirementTable => array('entity', 'fk_shipment', 'fk_soc', 'document_code', 'requirement_status', 'customer_visible'),
	);
	foreach ($requiredColumns as $table => $columns) {
		foreach ($columns as $column) {
			if (!m014ColumnExists($db, $table, $column)) {
				return false;
			}
		}
	}

	return true;
}

$command = $argv[1];
if ($command === 'check') {
	if (!m014VerifySchema($db)) {
		fwrite(STDERR, "M014_SCHEMA_CHECK=ABSENT\n");
		exit(2);
	}
	echo "M014_SCHEMA_CHECK=PASS\n";
	exit(0);
}

$shipmentTable = MAIN_DB_PREFIX.'chinaplanetlogistics';
$eventTable = MAIN_DB_PREFIX.'cpl_shipment_event';
$requirementTable = MAIN_DB_PREFIX.'cpl_document_requirement';

if (!m014TableExists($db, $shipmentTable)) {
	fwrite(STDERR, "M014_SCHEMA=FAIL REASON=SHIPMENT_TABLE_ABSENT\n");
	exit(1);
}

if (!m014ColumnExists($db, $shipmentTable, 'date_atd')) {
	m014QueryOrFail($db, "ALTER TABLE `".$shipmentTable."` ADD COLUMN `date_atd` date NULL AFTER `date_etd`", 'ADD_DATE_ATD_FAILED');
}

m014QueryOrFail($db, "CREATE TABLE IF NOT EXISTS `".$eventTable."` (
	`rowid` bigint AUTO_INCREMENT PRIMARY KEY,
	`entity` integer NOT NULL DEFAULT 1,
	`fk_shipment` integer NOT NULL,
	`fk_soc` integer NOT NULL,
	`event_type` varchar(32) NOT NULL,
	`milestone_code` varchar(32) NOT NULL,
	`status_from` integer NULL,
	`status_to` integer NULL,
	`event_at` datetime NOT NULL,
	`planned_at` datetime NULL,
	`actual_at` datetime NULL,
	`date_value_old` datetime NULL,
	`date_value_new` datetime NULL,
	`source_type` varchar(32) NOT NULL DEFAULT 'manual_team',
	`source_label_public` varchar(128) NOT NULL DEFAULT 'China Planet Team',
	`note_public` text NULL,
	`reason_public` text NULL,
	`customer_visible` tinyint NOT NULL DEFAULT 1,
	`fk_user_creat` integer NOT NULL,
	`date_creation` datetime NOT NULL,
	`tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX `idx_cpl_event_owner` (`entity`, `fk_soc`, `fk_shipment`),
	INDEX `idx_cpl_event_timeline` (`entity`, `fk_shipment`, `customer_visible`, `actual_at`, `event_at`),
	INDEX `idx_cpl_event_type` (`entity`, `fk_shipment`, `event_type`)
) ENGINE=innodb", 'CREATE_EVENT_TABLE_FAILED');

m014QueryOrFail($db, "CREATE TABLE IF NOT EXISTS `".$requirementTable."` (
	`rowid` bigint AUTO_INCREMENT PRIMARY KEY,
	`entity` integer NOT NULL DEFAULT 1,
	`fk_shipment` integer NOT NULL,
	`fk_soc` integer NOT NULL,
	`document_code` varchar(64) NOT NULL,
	`label_public` varchar(255) NOT NULL,
	`requirement_status` varchar(32) NOT NULL DEFAULT 'required',
	`requested_from` varchar(32) NOT NULL DEFAULT 'customer',
	`due_at` datetime NULL,
	`action_required` tinyint NOT NULL DEFAULT 0,
	`source_type` varchar(32) NULL,
	`source_id` bigint NULL,
	`note_public` text NULL,
	`customer_visible` tinyint NOT NULL DEFAULT 1,
	`fk_user_creat` integer NOT NULL,
	`fk_user_modif` integer NULL,
	`date_creation` datetime NOT NULL,
	`tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE INDEX `uk_cpl_requirement_code` (`entity`, `fk_shipment`, `document_code`),
	INDEX `idx_cpl_requirement_owner` (`entity`, `fk_soc`, `fk_shipment`),
	INDEX `idx_cpl_requirement_action` (`entity`, `fk_shipment`, `customer_visible`, `action_required`, `due_at`),
	INDEX `idx_cpl_requirement_source` (`entity`, `source_type`, `source_id`)
) ENGINE=innodb", 'CREATE_REQUIREMENT_TABLE_FAILED');

if (!m014VerifySchema($db)) {
	fwrite(STDERR, "M014_SCHEMA=FAIL REASON=POST_APPLY_VERIFY_FAILED\n");
	exit(1);
}

echo "M014_SCHEMA_APPLY=PASS\n";

