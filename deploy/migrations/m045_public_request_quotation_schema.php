<?php
/** Additive M045 schema and product-mapping fields. No business record is created. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M045_SCHEMA=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check','apply'), true)) { fwrite(STDERR, "M045_SCHEMA=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M045_DOL_DOCUMENT_ROOT');
if (!$documentRoot) $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M045_SCHEMA=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
global $db, $conf;

$table = MAIN_DB_PREFIX.'cpo_public_request';
$columnExists = function ($column) use ($db, $table) {
    $res = $db->query("SHOW COLUMNS FROM `".$table."` LIKE '".$db->escape($column)."'");
    return $res && $db->num_rows($res) === 1;
};
$indexExists = function ($index) use ($db, $table) {
    $res = $db->query("SHOW INDEX FROM `".$table."` WHERE Key_name='".$db->escape($index)."'");
    return $res && $db->num_rows($res) > 0;
};
$extraFieldExists = function ($name) use ($db, $conf) {
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."extrafields WHERE entity=".(int)$conf->entity." AND elementtype='product' AND name='".$db->escape($name)."'");
    return $res && $db->num_rows($res) === 1;
};
$rightExists = function () use ($db, $conf) {
    $res = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".(int)$conf->entity." AND id=500515 AND module='chinaplanetoperations' AND perms='publicrequest_quote'");
    return $res && $db->num_rows($res) === 1;
};

if ($argv[1] === 'check') {
    $ok = $columnExists('fk_propal') && $indexExists('uk_cpo_public_request_propal') && $extraFieldExists('cp_woo_product_id') && $extraFieldExists('cp_woo_variation_id') && $rightExists();
    echo $ok ? "M045_SCHEMA_CHECK=PASS\n" : "M045_SCHEMA_CHECK=ABSENT\n";
    exit($ok ? 0 : 2);
}

$db->begin();
try {
    if (!$columnExists('fk_propal') && !$db->query("ALTER TABLE `".$table."` ADD COLUMN `fk_propal` bigint NULL AFTER `fk_operation`")) throw new RuntimeException('ADD_FK_PROPAL_FAILED');
    if (!$indexExists('uk_cpo_public_request_propal') && !$db->query("ALTER TABLE `".$table."` ADD UNIQUE INDEX `uk_cpo_public_request_propal` (`entity`,`fk_propal`)")) throw new RuntimeException('ADD_PROPAL_INDEX_FAILED');
    $rightSql = "INSERT INTO ".MAIN_DB_PREFIX."rights_def(id,entity,libelle,module,module_origin,module_position,family,family_position,perms,subperms,type,bydefault,enabled) VALUES(500515,".(int)$conf->entity.",'Prepare draft quotations from public website requests','chinaplanetoperations','',90,'projects',0,'publicrequest_quote','', 'w',0,'1') ON DUPLICATE KEY UPDATE libelle=VALUES(libelle),module=VALUES(module),perms=VALUES(perms),type=VALUES(type),enabled=VALUES(enabled)";
    if (!$db->query($rightSql)) throw new RuntimeException('PERMISSION_DEFINITION_FAILED');
    $extraFields = new ExtraFields($db);
    if ($extraFields->addExtraField('cp_woo_product_id', 'WooCommerce product ID', 'int', 500, '20', 'product', 0, 0, '', '', 1, '', '1', 'Authoritative mapping to the WordPress/WooCommerce product.', '', (int)$conf->entity, '', '1', 0, 0) < 0) throw new RuntimeException('WOO_PRODUCT_FIELD_FAILED');
    if ($extraFields->addExtraField('cp_woo_variation_id', 'WooCommerce variation ID', 'int', 501, '20', 'product', 0, 0, '', '', 1, '', '1', 'Optional authoritative mapping to a WooCommerce variation.', '', (int)$conf->entity, '', '1', 0, 0) < 0) throw new RuntimeException('WOO_VARIATION_FIELD_FAILED');
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "M045_SCHEMA=FAIL REASON=".$e->getMessage()."\n");
    exit(1);
}
echo "M045_SCHEMA_APPLY=PASS GRANTS=EXPLICIT\n";
