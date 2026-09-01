<?php
/** Additive schema preparation for M071. It creates no business record and sends no email. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M071_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply'), true)) { fwrite(STDERR, "M071_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M071_DOL_DOCUMENT_ROOT') ?: '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M071_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

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
    $res = $db->query("SHOW COLUMNS FROM `".MAIN_DB_PREFIX.$table."` LIKE '".$db->escape($column)."'");
    return $res && $db->num_rows($res) === 1;
};
$tableIndexExists = function ($table, $index) use ($db) {
    $res = $db->query("SHOW INDEX FROM `".MAIN_DB_PREFIX.$table."` WHERE Key_name='".$db->escape($index)."'");
    return $res && $db->num_rows($res) > 0;
};
$columns = array(
    'schema_version' => "smallint NOT NULL DEFAULT 1 AFTER snapshot_json",
    'fk_socpeople' => "integer NULL AFTER fk_soc",
    'qualification_status' => "varchar(32) NOT NULL DEFAULT 'received' AFTER status",
    'match_status' => "varchar(32) NOT NULL DEFAULT 'unreviewed' AFTER qualification_status",
    'request_customer_visible' => "tinyint NOT NULL DEFAULT 0 AFTER match_status",
    'proposal_customer_visible' => "tinyint NOT NULL DEFAULT 0 AFTER request_customer_visible",
    'date_qualified' => "datetime NULL AFTER date_converted",
    'date_customer_visible' => "datetime NULL AFTER date_qualified",
);
$tables = array(
    'cpo_public_request_line' => dirname(__DIR__, 2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request_line.sql',
    'cpo_public_request_event' => dirname(__DIR__, 2).'/custom/chinaplanetoperations/sql/llx_cpo_public_request_event.sql',
);
$tableRequirements = array(
    'cpo_public_request_line' => array(
        'columns'=>array('rowid','entity','fk_request','line_key','position','source_product_id','source_variation_id','requested_cartons','submitted_units_per_carton','submitted_cbm_per_carton','submitted_gross_weight_kg','submitted_calculation_ready','submitted_missing_fields','price_snapshot_json','review_status','fk_user_creat','date_creation'),
        'indexes'=>array('PRIMARY','uk_cpo_request_line','idx_cpo_request_line_request'),
    ),
    'cpo_public_request_event' => array(
        'columns'=>array('rowid','entity','fk_request','event_type','state_from','state_to','metadata_json','customer_visible','fk_user_creat','date_creation'),
        'indexes'=>array('PRIMARY','idx_cpo_request_event_request','idx_cpo_request_event_portal'),
    ),
);
$rights = array(
    500525=>array('Safely qualify public website requests','publicrequest_qualify'),
    500526=>array('Publish approved request content to Customer Center','publicrequest_publish'),
);
$rightStatus = function ($id, array $definition) use ($db) {
    $res = $db->query("SELECT module,perms,bydefault FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".(int)$GLOBALS['conf']->entity." AND id=".(int)$id);
    if (!$res) throw new RuntimeException('RIGHT_QUERY_FAILED_'.(int)$id);
    if ($db->num_rows($res) === 0) return 'absent';
    $row = $db->fetch_object($res);
    return (string)$row->module === 'chinaplanetoperations' && (string)$row->perms === $definition[1] && (int)$row->bydefault === 0 ? 'ready' : 'collision';
};
$tableSchemaReady = function ($table) use ($tableRequirements, $tableColumnExists, $tableIndexExists) {
    foreach ($tableRequirements[$table]['columns'] as $column) if (!$tableColumnExists($table, $column)) return false;
    foreach ($tableRequirements[$table]['indexes'] as $index) if (!$tableIndexExists($table, $index)) return false;
    return true;
};

if ($argv[1] === 'check') {
    $ok = true;
    foreach (array_keys($columns) as $column) $ok = $ok && $columnExists($column);
    foreach (array_keys($tables) as $table) $ok = $ok && $tableExists($table) && $tableSchemaReady($table);
    $ok = $ok && $indexExists('idx_cpo_public_request_lifecycle') && $indexExists('idx_cpo_public_request_portal');
    foreach ($rights as $id=>$definition) $ok = $ok && $rightStatus($id, $definition) === 'ready';
    echo $ok ? "M071_SCHEMA_CHECK=PASS\n" : "M071_SCHEMA_CHECK=ABSENT\n";
    exit($ok ? 0 : 2);
}

try {
    foreach ($rights as $id=>$definition) if ($rightStatus($id, $definition) === 'collision') throw new RuntimeException('RIGHT_COLLISION_'.(int)$id);
    foreach ($columns as $column=>$definition) {
        if (!$columnExists($column) && !$db->query("ALTER TABLE `".$requestTable."` ADD COLUMN `".$column."` ".$definition)) throw new RuntimeException('ADD_COLUMN_'.strtoupper($column).'_FAILED');
    }
    if (!$indexExists('idx_cpo_public_request_lifecycle') && !$db->query("ALTER TABLE `".$requestTable."` ADD INDEX `idx_cpo_public_request_lifecycle` (`entity`,`qualification_status`,`date_creation`)")) throw new RuntimeException('ADD_LIFECYCLE_INDEX_FAILED');
    if (!$indexExists('idx_cpo_public_request_portal') && !$db->query("ALTER TABLE `".$requestTable."` ADD INDEX `idx_cpo_public_request_portal` (`entity`,`fk_soc`,`request_customer_visible`,`status`)")) throw new RuntimeException('ADD_PORTAL_INDEX_FAILED');
    foreach ($tables as $name=>$path) {
        if ($tableExists($name)) {
            if (!$tableSchemaReady($name)) throw new RuntimeException('TABLE_SCHEMA_INCOMPATIBLE_'.strtoupper($name));
            continue;
        }
        $sql = file_get_contents($path);
        if ($sql === false) throw new RuntimeException('SCHEMA_FILE_MISSING_'.strtoupper($name));
        $sql = preg_replace('/^CREATE TABLE llx_/i', 'CREATE TABLE '.MAIN_DB_PREFIX, trim($sql));
        if (!$db->query($sql)) throw new RuntimeException('CREATE_TABLE_'.strtoupper($name).'_FAILED');
        if (!$tableSchemaReady($name)) throw new RuntimeException('CREATE_TABLE_VERIFY_'.strtoupper($name).'_FAILED');
    }
    foreach (array_keys($columns) as $column) if (!$columnExists($column)) throw new RuntimeException('VERIFY_COLUMN_'.strtoupper($column).'_FAILED');
    if (!$indexExists('idx_cpo_public_request_lifecycle') || !$indexExists('idx_cpo_public_request_portal')) throw new RuntimeException('VERIFY_REQUEST_INDEX_FAILED');
    foreach ($rights as $id=>$definition) {
        if ($rightStatus($id, $definition) === 'absent') {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."rights_def(id,entity,libelle,module,module_origin,module_position,family,family_position,perms,subperms,type,bydefault,enabled) VALUES(".(int)$id.",".(int)$conf->entity.",'".$db->escape($definition[0])."','chinaplanetoperations','',90,'projects',0,'".$db->escape($definition[1])."','','w',0,'1')";
            if (!$db->query($sql)) throw new RuntimeException('RIGHT_INSERT_FAILED_'.(int)$id);
        }
        if ($rightStatus($id, $definition) !== 'ready') throw new RuntimeException('RIGHT_VERIFY_FAILED_'.(int)$id);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "M071_SCHEMA=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
echo "M071_SCHEMA_APPLY=PASS GRANTS=EXPLICIT BUSINESS_RECORDS=NONE EMAIL=NONE\n";
