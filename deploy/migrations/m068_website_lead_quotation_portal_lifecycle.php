<?php
/** Additive local/staging migration for M068. It never creates business records or sends mail. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M068_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) { fwrite(STDERR, "M068_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M068_DOL_DOCUMENT_ROOT');
if (!$documentRoot) $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M068_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db, $conf;

$requestTable = MAIN_DB_PREFIX.'cpo_public_request';
$columnExists = function ($column) use ($db, $requestTable) {
    $res = $db->query("SHOW COLUMNS FROM `".$requestTable."` LIKE '".$db->escape($column)."'");
    return $res && $db->num_rows($res) === 1;
};
$indexExists = function ($index) use ($db, $requestTable) {
    $res = $db->query("SHOW INDEX FROM `".$requestTable."` WHERE Key_name='".$db->escape($index)."'");
    return $res && $db->num_rows($res) > 0;
};
$tableExists = function ($name) use ($db) {
    $res = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$name)."'");
    return $res && $db->num_rows($res) === 1;
};
$tableColumnExists = function ($table, $column) use ($db) {
    $res = $db->query("SHOW COLUMNS FROM `".MAIN_DB_PREFIX.$db->escape($table)."` LIKE '".$db->escape($column)."'");
    return $res && $db->num_rows($res) === 1;
};
$tableIndexExists = function ($table, $index) use ($db) {
    $res = $db->query("SHOW INDEX FROM `".MAIN_DB_PREFIX.$db->escape($table)."` WHERE Key_name='".$db->escape($index)."'");
    return $res && $db->num_rows($res) > 0;
};
$rightExists = function ($id, $permission) use ($db, $conf) {
    $sql = "SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".(int)$conf->entity." AND id=".(int)$id." AND module='chinaplanetoperations' AND perms='".$db->escape($permission)."'";
    $res = $db->query($sql);
    return $res && $db->num_rows($res) === 1;
};

$columns = array(
    'schema_version' => "smallint NOT NULL DEFAULT 1 AFTER snapshot_json",
    'fk_socpeople' => "integer NULL AFTER fk_soc",
    'fk_propal' => "bigint NULL AFTER fk_operation",
    'qualification_status' => "varchar(32) NOT NULL DEFAULT 'received' AFTER status",
    'match_status' => "varchar(32) NOT NULL DEFAULT 'unreviewed' AFTER qualification_status",
    'portal_status' => "varchar(32) NOT NULL DEFAULT 'not_invited' AFTER match_status",
    'request_customer_visible' => "tinyint NOT NULL DEFAULT 0 AFTER portal_status",
    'proposal_customer_visible' => "tinyint NOT NULL DEFAULT 0 AFTER request_customer_visible",
    'date_qualified' => "datetime NULL AFTER date_converted",
    'date_customer_visible' => "datetime NULL AFTER date_qualified",
);
$tables = array(
    'cpo_public_request_line' => dirname(__DIR__, 2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request_line.sql',
    'cpo_public_request_event' => dirname(__DIR__, 2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request_event.sql',
    'cpo_public_request_publication' => dirname(__DIR__, 2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request_publication.sql',
    'cpcc_portal_invitation' => dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/sql/llx_cpcc_portal_invitation.sql',
    'cpcc_portal_invitation_attempt' => dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/sql/llx_cpcc_portal_invitation_attempt.sql',
);
$rights = array(
    500525 => array('Safely qualify public website requests', 'publicrequest_qualify'),
    500526 => array('Publish approved public request content to customers', 'publicrequest_publish'),
    500527 => array('Invite qualified customers to Customer Center', 'publicrequest_invite'),
);

if ($argv[1] === 'check') {
    $ok = true;
    foreach (array_keys($columns) as $column) $ok = $ok && $columnExists($column);
    foreach (array_keys($tables) as $table) $ok = $ok && $tableExists($table);
    $ok = $ok && $tableColumnExists('cpo_public_request_line', 'verified_packing_source');
    $ok = $ok && $tableIndexExists('cpcc_portal_invitation_attempt', 'idx_cpcc_invitation_attempt_scope') && !$tableIndexExists('cpcc_portal_invitation_attempt', 'uk_cpcc_invitation_attempt');
    $ok = $ok && $indexExists('idx_cpo_public_request_lifecycle') && $indexExists('idx_cpo_public_request_portal') && $indexExists('uk_cpo_public_request_propal');
    foreach ($rights as $id=>$definition) $ok = $ok && $rightExists($id, $definition[1]);
    echo $ok ? "M068_SCHEMA_CHECK=PASS\n" : "M068_SCHEMA_CHECK=ABSENT\n";
    exit($ok ? 0 : 2);
}

$db->begin();
try {
    foreach ($columns as $column=>$definition) {
        if (!$columnExists($column) && !$db->query("ALTER TABLE `".$requestTable."` ADD COLUMN `".$column."` ".$definition)) throw new RuntimeException('ADD_COLUMN_'.strtoupper($column).'_FAILED');
    }
    if (!$indexExists('idx_cpo_public_request_lifecycle') && !$db->query("ALTER TABLE `".$requestTable."` ADD INDEX `idx_cpo_public_request_lifecycle` (`entity`,`qualification_status`,`date_creation`)")) throw new RuntimeException('ADD_LIFECYCLE_INDEX_FAILED');
    if (!$indexExists('idx_cpo_public_request_portal') && !$db->query("ALTER TABLE `".$requestTable."` ADD INDEX `idx_cpo_public_request_portal` (`entity`,`fk_soc`,`request_customer_visible`,`status`)")) throw new RuntimeException('ADD_PORTAL_INDEX_FAILED');
    if (!$indexExists('uk_cpo_public_request_propal') && !$db->query("ALTER TABLE `".$requestTable."` ADD UNIQUE INDEX `uk_cpo_public_request_propal` (`entity`,`fk_propal`)")) throw new RuntimeException('ADD_PROPAL_INDEX_FAILED');
    foreach ($tables as $name=>$path) {
        if ($tableExists($name)) continue;
        $sql = file_get_contents($path);
        if ($sql === false) throw new RuntimeException('SCHEMA_FILE_MISSING_'.strtoupper($name));
        $sql = preg_replace('/^CREATE TABLE llx_/i', 'CREATE TABLE '.MAIN_DB_PREFIX, trim($sql));
        if (!$db->query($sql)) throw new RuntimeException('CREATE_TABLE_'.strtoupper($name).'_FAILED');
    }
    if (!$tableColumnExists('cpo_public_request_line', 'verified_packing_source') && !$db->query("ALTER TABLE `".MAIN_DB_PREFIX."cpo_public_request_line` ADD COLUMN `verified_packing_source` varchar(32) NULL AFTER `verified_gross_weight_kg`")) throw new RuntimeException('ADD_VERIFIED_PACKING_SOURCE_FAILED');
    if ($tableIndexExists('cpcc_portal_invitation_attempt', 'uk_cpcc_invitation_attempt') && !$db->query("ALTER TABLE `".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt` DROP INDEX `uk_cpcc_invitation_attempt`")) throw new RuntimeException('DROP_FIXED_ATTEMPT_WINDOW_FAILED');
    if (!$tableIndexExists('cpcc_portal_invitation_attempt', 'idx_cpcc_invitation_attempt_scope') && !$db->query("ALTER TABLE `".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt` ADD INDEX `idx_cpcc_invitation_attempt_scope` (`entity`,`token_hash`,`ip_hmac`,`last_attempt_at`)")) throw new RuntimeException('ADD_ROLLING_ATTEMPT_INDEX_FAILED');
    foreach ($rights as $id=>$definition) {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."rights_def(id,entity,libelle,module,module_origin,module_position,family,family_position,perms,subperms,type,bydefault,enabled) VALUES(".(int)$id.",".(int)$conf->entity.",'".$db->escape($definition[0])."','chinaplanetoperations','',90,'projects',0,'".$db->escape($definition[1])."','','w',0,'1') ON DUPLICATE KEY UPDATE libelle=VALUES(libelle),module=VALUES(module),perms=VALUES(perms),type=VALUES(type),enabled=VALUES(enabled)";
        if (!$db->query($sql)) throw new RuntimeException('PERMISSION_'.(int)$id.'_FAILED');
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "M068_SCHEMA=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
echo "M068_SCHEMA_APPLY=PASS GRANTS=EXPLICIT BUSINESS_RECORDS=NONE\n";
