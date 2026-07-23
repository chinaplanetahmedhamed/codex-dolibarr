<?php
/** Additive local/staging-safe M035 accepted-price booking schema migration. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check','apply'), true)) { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M035_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

$table = MAIN_DB_PREFIX.'chinaplanetsp_booking';
$pattern = str_replace(array('\\','_','%'), array('\\\\','\\_','\\%'), $table);
$exists = function () use ($db, $pattern) {
    $res = $db->query("SHOW TABLES LIKE '".$db->escape($pattern)."'");
    return $res && $db->num_rows($res) === 1;
};

if ($argv[1] === 'check') {
    if (!$exists()) { fwrite(STDERR, "M035_SCHEMA_CHECK=ABSENT\n"); exit(2); }
    $res = $db->query("SHOW INDEX FROM `".$table."` WHERE Key_name IN ('uk_chinaplanetsp_booking_key','uk_chinaplanetsp_booking_operation')");
    if (!$res || $db->num_rows($res) < 2) { fwrite(STDERR, "M035_SCHEMA_CHECK=INVALID\n"); exit(2); }
    $res = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".(int)$conf->entity." AND id=500411 AND perms='booking_submit'");
    if (!$res || $db->num_rows($res) !== 1) { fwrite(STDERR, "M035_SCHEMA_CHECK=PERMISSION_ABSENT\n"); exit(2); }
    echo "M035_SCHEMA_CHECK=PASS\n"; exit(0);
}

$sql = "CREATE TABLE IF NOT EXISTS `".$table."` (
 `rowid` bigint AUTO_INCREMENT PRIMARY KEY,
 `entity` integer NOT NULL DEFAULT 1,
 `fk_soc` integer NOT NULL,
 `fk_rate` bigint NOT NULL,
 `fk_operation` bigint NOT NULL,
 `idempotency_key` varchar(64) NOT NULL,
 `request_hash` char(64) NOT NULL,
 `rate_fingerprint` char(64) NOT NULL,
 `input_json` mediumtext NOT NULL,
 `snapshot_json` mediumtext NOT NULL,
 `terms_hash` char(64) NOT NULL,
 `status` varchar(32) NOT NULL DEFAULT 'received',
 `fk_user_creat` integer NOT NULL,
 `date_acceptance` datetime NOT NULL,
 `date_creation` datetime NOT NULL,
 `tms` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY `uk_chinaplanetsp_booking_key` (`entity`,`fk_soc`,`idempotency_key`),
 UNIQUE KEY `uk_chinaplanetsp_booking_operation` (`entity`,`fk_operation`),
 KEY `idx_chinaplanetsp_booking_customer` (`entity`,`fk_soc`,`date_creation`),
 KEY `idx_chinaplanetsp_booking_rate` (`entity`,`fk_rate`)
) ENGINE=innodb";
if (!$db->query($sql)) { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=CREATE_FAILED\n"); exit(1); }
$rightTable = MAIN_DB_PREFIX.'rights_def';
$rightSql = "INSERT INTO `".$rightTable."` (`id`,`entity`,`libelle`,`module`,`module_origin`,`module_position`,`family`,`family_position`,`perms`,`subperms`,`type`,`bydefault`,`enabled`) VALUES (500411,".(int)$conf->entity.",'Submit an authenticated customer shipping booking from a published price','chinaplanetshippingprices','',100099,'products',0,'booking_submit','external','w',1,'1') ON DUPLICATE KEY UPDATE `libelle`=VALUES(`libelle`),`perms`=VALUES(`perms`),`subperms`=VALUES(`subperms`),`bydefault`=VALUES(`bydefault`),`enabled`=VALUES(`enabled`)";
if (!$db->query($rightSql)) { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=PERMISSION_DEFINITION_FAILED\n"); exit(1); }
// Upgrade rule: existing external users who already had customer rate-read receive the separately revocable booking right.
$grantSql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."user_rights(fk_user,fk_id) SELECT ur.fk_user,500411 FROM ".MAIN_DB_PREFIX."user_rights ur INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=ur.fk_user AND u.entity=".(int)$conf->entity." AND u.fk_soc IS NOT NULL AND u.fk_soc>0 AND u.statut=1 WHERE ur.fk_id=500401";
if (!$db->query($grantSql)) { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=PERMISSION_UPGRADE_FAILED\n"); exit(1); }
if (!$exists()) { fwrite(STDERR, "M035_SCHEMA=FAIL REASON=POST_APPLY_VERIFY_FAILED\n"); exit(1); }
echo "M035_SCHEMA_APPLY=PASS\n";
