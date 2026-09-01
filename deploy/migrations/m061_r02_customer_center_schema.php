<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "M061_R02_SCHEMA_FAIL=CLI_REQUIRED\n");
    exit(1);
}
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply', 'disable'), true)) {
    fwrite(STDERR, "M061_R02_SCHEMA_FAIL=COMMAND_NOT_ALLOWED\n");
    exit(1);
}

$documentRoot = getenv('M061_R02_DOL_DOCUMENT_ROOT');
if (!is_string($documentRoot) || $documentRoot !== '/var/www/html') {
    fwrite(STDERR, "M061_R02_SCHEMA_FAIL=DOCUMENT_ROOT_INVALID\n");
    exit(1);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
require $documentRoot.'/master.inc.php';

function m061R02Fail($reason)
{
    fwrite(STDERR, 'M061_R02_SCHEMA_FAIL='.$reason."\n");
    exit(1);
}

function m061R02Count($db, $sql)
{
    $result = $db->query($sql);
    if (!$result) m061R02Fail('DATABASE_INSPECTION_FAILED');
    $row = $db->fetch_row($result);
    return $row ? (int) $row[0] : 0;
}

function m061R02TableState($db)
{
    $table = MAIN_DB_PREFIX.'cpcc_customer_document';
    if (m061R02Count($db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."'") === 0) return 'MISSING';
    $required = array('rowid','entity','fk_soc','fk_project','fk_shipment','source_element','source_id','source_ref','relative_path','original_name','document_type','mime_type','file_size','file_sha256','customer_visible','status','fk_user_publish','fk_user_modif','date_publication','date_unpublication','tms');
    foreach ($required as $column) {
        if (m061R02Count($db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."' AND column_name='".$db->escape($column)."'") !== 1) return 'DRIFT';
    }
    if (m061R02Count($db, "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."' AND index_name='uk_cpcc_customer_doc_file' AND non_unique=0") !== 4) return 'DRIFT';
    return 'READY';
}

function m061R02ExtraFieldState($db, $entity)
{
    $columnCount = m061R02Count($db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".$db->escape(MAIN_DB_PREFIX.'paiement_extrafields')."' AND column_name='cpcc_public_note'");
    $definitionCount = m061R02Count($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."extrafields WHERE entity=".(int) $entity." AND elementtype='paiement' AND name='cpcc_public_note'");
    if ($columnCount === 0 && $definitionCount === 0) return 'MISSING';
    if ($columnCount !== 1 || $definitionCount !== 1) return 'DRIFT';
    $valid = m061R02Count($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."extrafields WHERE entity=".(int) $entity." AND elementtype='paiement' AND name='cpcc_public_note' AND type='text'");
    return $valid === 1 ? 'READY' : 'DRIFT';
}

function m061R02RightState($db, $entity)
{
    $all = m061R02Count($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".(int) $entity." AND id=500206");
    if ($all === 0) return 'MISSING';
    $valid = m061R02Count($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".(int) $entity." AND id=500206 AND module='chinaplanetcustomercenter' AND perms='email_write' AND bydefault=0");
    return $all === 1 && $valid === 1 ? 'READY' : 'DRIFT';
}

function m061R02States($db, $entity)
{
    return array(
        'TABLE' => m061R02TableState($db),
        'EXTRAFIELD' => m061R02ExtraFieldState($db, $entity),
        'RIGHT' => m061R02RightState($db, $entity),
    );
}

function m061R02AssertNoDrift($states)
{
    foreach ($states as $name => $state) if ($state === 'DRIFT') m061R02Fail('SCHEMA_DRIFT_'.$name);
}

global $db, $conf;
$entity = (int) $conf->entity;
if ($entity !== 1) m061R02Fail('ENTITY_MISMATCH');
$states = m061R02States($db, $entity);
m061R02AssertNoDrift($states);

if ($argv[1] === 'check') {
    if (count(array_unique($states)) === 1 && reset($states) === 'READY') {
        echo "M061_R02_SCHEMA_CHECK=READY\n";
        exit(0);
    }
    foreach ($states as $name => $state) {
        if ($state !== 'READY') {
            echo 'M061_R02_SCHEMA_CHECK=MIGRATION_REQUIRED REASON='.$name.'_MISSING'."\n";
            exit(2);
        }
    }
}

$context = getenv('M061_R02_CONTEXT');
if (!is_string($context) || $context !== 'm061-r02-staging-487b61e-root-only') m061R02Fail('MUTATION_CONTEXT_INVALID');

if ($argv[1] === 'disable') {
    $db->begin();
    foreach (array('user_rights', 'usergroup_rights') as $table) {
        if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX.$table." WHERE fk_id=500206")) {
            $db->rollback();
            m061R02Fail('RIGHT_ASSIGNMENT_DISABLE_FAILED');
        }
    }
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".$entity." AND id=500206 AND module='chinaplanetcustomercenter' AND perms='email_write'")) {
        $db->rollback();
        m061R02Fail('RIGHT_DISABLE_FAILED');
    }
    if ($db->commit() <= 0) m061R02Fail('RIGHT_DISABLE_COMMIT_FAILED');
    if (m061R02RightState($db, $entity) !== 'MISSING') m061R02Fail('RIGHT_DISABLE_VERIFY_FAILED');
    echo "M061_R02_SCHEMA_DISABLE=PASS SCHEMA_RETAINED=YES DOCUMENT_DATA_RETAINED=YES\n";
    exit(0);
}

if ($states['TABLE'] === 'MISSING') {
    $sqlFile = $documentRoot.'/custom/chinaplanetcustomercenter/sql/llx_cpcc_customer_document.sql';
    $sql = is_file($sqlFile) ? file_get_contents($sqlFile) : false;
    if (!is_string($sql) || $sql === '') m061R02Fail('TABLE_SQL_MISSING');
    $sql = rtrim(str_replace('llx_', MAIN_DB_PREFIX, $sql), ";\r\n\t ");
    if (!$db->query($sql)) m061R02Fail('TABLE_CREATE_FAILED');
}

if ($states['EXTRAFIELD'] === 'MISSING') {
    require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extraFields = new ExtraFields($db);
    $result = $extraFields->addExtraField(
        'cpcc_public_note',
        'Customer-visible payment note / ملاحظة دفع ظاهرة للعميل',
        'text', 500, '', 'paiement', 0, 0, '', '', 1, '', '1',
        'Shown in the Customer Center. Never copy private payment notes into this field automatically.',
        '', $entity, '', '1', 0, 0
    );
    if ($result < 0) m061R02Fail('EXTRAFIELD_CREATE_FAILED');
}

if ($states['RIGHT'] === 'MISSING') {
    require_once $documentRoot.'/custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php';
    $module = new modChinaPlanetCustomerCenter($db);
    $module->rights = array_values(array_filter($module->rights, function ($right) { return (int) $right[0] === 500206; }));
    if (count($module->rights) !== 1 || $module->insert_permissions(1, null, 1) !== 0) m061R02Fail('RIGHT_CREATE_FAILED');
}

$states = m061R02States($db, $entity);
m061R02AssertNoDrift($states);
if (count(array_unique($states)) !== 1 || reset($states) !== 'READY') m061R02Fail('READINESS_VERIFY_FAILED');
echo "M061_R02_SCHEMA_APPLY=PASS TABLE=READY EXTRAFIELD=READY RIGHT=READY\n";
