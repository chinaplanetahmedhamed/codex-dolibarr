<?php

function m074EffectiveUid()
{
    if (function_exists('posix_geteuid')) return (int) posix_geteuid();
    $status = @file_get_contents('/proc/self/status');
    if (is_string($status) && preg_match('/^Uid:\s+[0-9]+\s+([0-9]+)\s+/m', $status, $matches)) {
        return (int) $matches[1];
    }
    return -1;
}

if (PHP_SAPI !== 'cli' || $argc !== 2 || !in_array($argv[1], array('identity', 'check', 'apply'), true)) {
    fwrite(STDERR, "M074_SCHEMA=FAIL REASON=INVALID_COMMAND\n");
    exit(64);
}

if ($argv[1] === 'apply') {
    $effectiveUid = m074EffectiveUid();
    if ($effectiveUid !== 0 || getenv('M074_DEPLOY_CONTEXT') !== 'm074-r10-backup-erp-root-only') {
        fwrite(STDERR, "M074_SCHEMA=FAIL REASON=DEPLOYMENT_CONTEXT_REQUIRED\n");
        exit(66);
    }
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M074_DOL_DOCUMENT_ROOT');
if ($documentRoot !== '/var/www/html' || !is_file($documentRoot.'/master.inc.php')) {
    fwrite(STDERR, "M074_SCHEMA=FAIL REASON=DOL_DOCUMENT_ROOT_INVALID\n");
    exit(65);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';

$candidateRoot = dirname(__DIR__, 2);
require_once $candidateRoot.'/custom/chinaplanetoperations/class/QcMigration.class.php';
require_once $candidateRoot.'/custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php';

function m074SchemaFail($reason, $code = 1)
{
    $allowed = array(
        'INVALID_COMMAND', 'DEPLOYMENT_CONTEXT_REQUIRED', 'DOL_DOCUMENT_ROOT_INVALID',
        'DATABASE_INSPECTION_FAILED', 'DATABASE_IDENTITY_UNAVAILABLE', 'ENTITY_INVALID',
        'MODULE_NOT_ENABLED', 'ROOT_CONTEXT_UNAVAILABLE', 'TABLE_CONTRACT_INVALID', 'TABLE_MIGRATION_FAILED',
        'RIGHT_INSTALL_FAILED', 'RIGHTS_NOT_READY', 'SCHEMA_NOT_READY',
        'RIGHT_COLLISION_500528', 'RIGHT_COLLISION_500529',
        'RIGHT_COLLISION_500530', 'RIGHT_COLLISION_500531',
    );
    if (!in_array($reason, $allowed, true)) $reason = 'DATABASE_INSPECTION_FAILED';
    fwrite(STDERR, 'M074_SCHEMA=FAIL REASON='.$reason."\n");
    exit($code);
}

function m074SchemaCount($db, $sql)
{
    $res = $db->query($sql);
    if (!$res) m074SchemaFail('DATABASE_INSPECTION_FAILED');
    $row = $db->fetch_object($res);
    return $row ? (int) $row->count_rows : 0;
}

function m074ModuleEnabled($db, $entity)
{
    return m074SchemaCount($db, 'SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX."const WHERE entity IN (0,".(int) $entity.") AND name='MAIN_MODULE_CHINAPLANETOPERATIONS' AND value='1'") > 0;
}

function m074RightsReady($db, $entity)
{
    $migration = new QcMigration($db);
    return $migration->rightsReady($entity);
}

function m074DatabaseIdentity($db)
{
    $res = $db->query("SELECT LOWER(SHA2(CONCAT(DATABASE(),CHAR(31),@@hostname,CHAR(31),@@port),256)) database_identity");
    $row = $res ? $db->fetch_object($res) : null;
    $identity = $row ? strtolower((string) $row->database_identity) : '';
    if (!preg_match('/^[0-9a-f]{64}$/', $identity)) m074SchemaFail('DATABASE_IDENTITY_UNAVAILABLE');
    return $identity;
}

global $db, $conf;
$entity = (int) $conf->entity;
if ($entity <= 0) m074SchemaFail('ENTITY_INVALID');
if ($argv[1] === 'identity') {
    echo 'M074_SCHEMA_DATABASE_IDENTITY='.m074DatabaseIdentity($db)."\n";
    exit(0);
}
if (!m074ModuleEnabled($db, $entity)) m074SchemaFail('MODULE_NOT_ENABLED');
if ($argv[1] === 'check' && m074EffectiveUid() !== 0) m074SchemaFail('ROOT_CONTEXT_UNAVAILABLE');

$migration = new QcMigration($db);
foreach (QcMigration::rightCodes() as $rightId => $rightCode) {
    if ($migration->rightDefinitionCollision($rightId, $rightCode)) m074SchemaFail('RIGHT_COLLISION_'.$rightId);
}

$tableMissing = false;
foreach (QcMigration::tables() as $table) {
    $tableStatus = $migration->tableContractStatus($table);
    if ($tableStatus === 'collision') m074SchemaFail('TABLE_CONTRACT_INVALID');
    if ($tableStatus === 'missing') $tableMissing = true;
}

if ($argv[1] === 'check') {
    if (!$tableMissing && m074RightsReady($db, $entity)) {
        echo "M074_SCHEMA_CHECK=READY RIGHTS_DEFAULT=DISABLED\n";
        exit(0);
    }
    $reason = $tableMissing ? 'TABLES_MISSING' : 'RIGHTS_MISSING';
    echo 'M074_SCHEMA_CHECK=MIGRATION_REQUIRED REASON='.$reason."\n";
    exit(2);
}

try {
    $migration->migrate();
} catch (Throwable $exception) {
    m074SchemaFail('TABLE_MIGRATION_FAILED');
}

try {
    $module = new modChinaPlanetOperations($db);
    $module->rights = array_values(array_filter($module->rights, function ($right) {
        return (int) $right[0] >= 500528 && (int) $right[0] <= 500531;
    }));
    if (count($module->rights) !== 4 || !$migration->installRights($module, $entity)) m074SchemaFail('RIGHT_INSTALL_FAILED');
} catch (Throwable $exception) {
    m074SchemaFail('RIGHT_INSTALL_FAILED');
}

$migration = new QcMigration($db);
if (!$migration->schemaReady()) m074SchemaFail('SCHEMA_NOT_READY');
if (!m074RightsReady($db, $entity)) m074SchemaFail('RIGHTS_NOT_READY');
echo "M074_SCHEMA_APPLY=PASS TABLES=3 RIGHTS=4 RIGHTS_DEFAULT=DISABLED\n";
