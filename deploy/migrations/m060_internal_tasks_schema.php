<?php

if (PHP_SAPI !== 'cli' || $argc !== 2 || !in_array($argv[1], array('check', 'apply', 'disable'), true)) {
    fwrite(STDERR, "M060_SCHEMA=FAIL REASON=INVALID_COMMAND\n");
    exit(64);
}

if ($argv[1] !== 'check') {
    $effectiveUid = function_exists('posix_geteuid') ? posix_geteuid() : -1;
    if ($effectiveUid !== 0 || getenv('M060_DEPLOY_CONTEXT') !== 'm060-staging-ec13773-root-only') {
        fwrite(STDERR, "M060_SCHEMA=FAIL REASON=DEPLOYMENT_CONTEXT_REQUIRED\n");
        exit(66);
    }
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M060_DOL_DOCUMENT_ROOT');
if ($documentRoot !== '/var/www/html' || !is_file($documentRoot.'/master.inc.php')) {
    fwrite(STDERR, "M060_SCHEMA=FAIL REASON=DOL_DOCUMENT_ROOT_INVALID\n");
    exit(65);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
require_once $documentRoot.'/user/class/user.class.php';

$candidateRoot = dirname(__DIR__, 2);
require_once $candidateRoot.'/custom/chinaplanetoperations/class/InternalTaskMigration.class.php';
require_once $candidateRoot.'/custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php';

function m060Fail($reason, $code = 1)
{
    fwrite(STDERR, 'M060_SCHEMA=FAIL REASON='.$reason."\n");
    exit($code);
}

function m060MigrationFailureReason($exception)
{
    $reasons = array(
        'Internal task base table is missing.' => 'BASE_TASK_TABLE_MISSING',
        'Internal task migration is already running.' => 'MIGRATION_LOCKED',
        'Internal task right definition collision.' => 'RIGHT_COLLISION',
        'Internal task schema readiness check failed.' => 'READINESS_FAILED',
        'Internal task migration step failed.' => 'DDL_FAILED',
        'Unable to inspect internal task schema.' => 'INSPECTION_FAILED',
        'Unable to inspect right definitions.' => 'RIGHT_INSPECTION_FAILED',
        'Unable to release internal task migration lock.' => 'LOCK_RELEASE_FAILED',
        'Internal task timestamp contains null values.' => 'TIMESTAMP_DATA_INVALID',
    );
    $message = $exception instanceof Throwable ? $exception->getMessage() : '';
    if (preg_match('/^Internal task schema readiness check failed: ([A-Z0-9_]+)\.$/', $message, $matches)) {
        return 'READINESS_FAILED_'.$matches[1];
    }
    return isset($reasons[$message]) ? $reasons[$message] : 'MIGRATION_FAILED';
}

function m060Count($db, $sql)
{
    $res = $db->query($sql);
    if (!$res) m060Fail('DATABASE_INSPECTION_FAILED');
    $row = $db->fetch_object($res);
    return $row ? (int) $row->count_rows : 0;
}

function m060ModuleEnabled($db, $entity)
{
    $sql = 'SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX."const WHERE entity IN (0,".(int) $entity.") AND name='MAIN_MODULE_CHINAPLANETOPERATIONS' AND value='1'";
    return m060Count($db, $sql) > 0;
}

function m060MenusReady($db, $entity)
{
    $urls = array(
        '/custom/chinaplanetoperations/tasks/board.php',
        '/custom/chinaplanetoperations/tasks/stages.php',
        '/custom/chinaplanetoperations/tasks/inbox.php',
        '/custom/chinaplanetoperations/tasks/labels.php',
        '/custom/chinaplanetoperations/tasks/team_admin.php',
    );
    foreach ($urls as $url) {
        $sql = 'SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX."menu WHERE entity=".(int) $entity." AND module='chinaplanetoperations' AND menu_handler='all' AND url='".$db->escape($url)."'";
        if (m060Count($db, $sql) !== 1) return false;
    }
    return true;
}

function m060MenusDisabled($db, $entity)
{
    $urls = array(
        '/custom/chinaplanetoperations/tasks/board.php',
        '/custom/chinaplanetoperations/tasks/stages.php',
        '/custom/chinaplanetoperations/tasks/inbox.php',
        '/custom/chinaplanetoperations/tasks/labels.php',
        '/custom/chinaplanetoperations/tasks/team_admin.php',
    );
    foreach ($urls as $url) {
        $sql = 'SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX."menu WHERE entity=".(int) $entity." AND module='chinaplanetoperations' AND menu_handler='all' AND url='".$db->escape($url)."'";
        if (m060Count($db, $sql) !== 0) return false;
    }
    return true;
}

function m060LoadAdministrator($db, $entity)
{
    $res = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE entity='.(int) $entity.' AND statut=1 AND admin=1 AND COALESCE(fk_soc,0)=0 ORDER BY rowid LIMIT 1');
    $row = $res ? $db->fetch_object($res) : null;
    if (!$row) m060Fail('ACTIVE_ADMINISTRATOR_UNAVAILABLE');
    $administrator = new User($db);
    if ($administrator->fetch((int) $row->rowid) <= 0) m060Fail('ADMINISTRATOR_LOAD_FAILED');
    $administrator->getrights();
    return $administrator;
}

global $db, $conf, $user;
$entity = (int) $conf->entity;
if ($entity <= 0 || !m060ModuleEnabled($db, $entity)) m060Fail('MODULE_NOT_ENABLED');
if (!function_exists('mb_check_encoding') || !function_exists('mb_strlen')) m060Fail('MBSTRING_REQUIRED');
if (m060Count($db, 'SELECT COUNT(*) count_rows FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=\''.$db->escape(MAIN_DB_PREFIX.'cpo_task').'\'') !== 1) m060Fail('BASE_TASK_TABLE_MISSING');

$migration = new InternalTaskMigration($db);
foreach (InternalTaskMigration::rightCodes() as $rightId => $rightCode) {
    if ($migration->rightCollision($rightId)) m060Fail('RIGHT_COLLISION_'.$rightId);
}

if ($argv[1] === 'check') {
    if ($migration->isReady(true) && m060MenusReady($db, $entity)) {
        echo "M060_SCHEMA_CHECK=READY\n";
        exit(0);
    }
    $reason = $migration->readinessReason();
    if ($reason === 'READY') $reason = 'MENUS_NOT_READY';
    echo "M060_SCHEMA_CHECK=MIGRATION_REQUIRED REASON=".$reason."\n";
    exit(2);
}

if ($argv[1] === 'disable') {
    $taskUrls = array(
        '/custom/chinaplanetoperations/tasks/board.php',
        '/custom/chinaplanetoperations/tasks/stages.php',
        '/custom/chinaplanetoperations/tasks/inbox.php',
        '/custom/chinaplanetoperations/tasks/labels.php',
        '/custom/chinaplanetoperations/tasks/team_admin.php',
    );
    $quoted = array();
    foreach ($taskUrls as $url) $quoted[] = "'".$db->escape($url)."'";
    $db->begin();
    $delete = 'DELETE FROM '.MAIN_DB_PREFIX."menu WHERE entity=".$entity." AND module='chinaplanetoperations' AND menu_handler='all' AND url IN (".implode(',', $quoted).')';
    if (!$db->query($delete)) {
        $db->rollback();
        m060Fail('TASK_MENU_DISABLE_FAILED');
    }
    if ($db->commit() <= 0) m060Fail('TASK_MENU_DISABLE_COMMIT_FAILED');
    if (!m060MenusDisabled($db, $entity)) m060Fail('TASK_MENU_DISABLE_VERIFY_FAILED');
    echo "M060_SCHEMA_DISABLE=PASS DATA_RETAINED=YES\n";
    exit(0);
}

$user = m060LoadAdministrator($db, $entity);
$GLOBALS['user'] = $user;
try {
    $migration->migrate();
    $module = new modChinaPlanetOperations($db);
    $module->rights = array_values(array_filter($module->rights, function ($right) {
        return (int) $right[0] >= 500516 && (int) $right[0] <= 500524;
    }));
    if ($module->insert_permissions(1, null, 1) !== 0) m060Fail('RIGHT_INSTALL_FAILED');

    $taskUrls = array(
        '/custom/chinaplanetoperations/tasks/board.php',
        '/custom/chinaplanetoperations/tasks/stages.php',
        '/custom/chinaplanetoperations/tasks/inbox.php',
        '/custom/chinaplanetoperations/tasks/labels.php',
        '/custom/chinaplanetoperations/tasks/team_admin.php',
    );
    $quoted = array();
    foreach ($taskUrls as $url) $quoted[] = "'".$db->escape($url)."'";
    $db->begin();
    $delete = 'DELETE FROM '.MAIN_DB_PREFIX."menu WHERE entity=".$entity." AND module='chinaplanetoperations' AND menu_handler='all' AND url IN (".implode(',', $quoted).')';
    if (!$db->query($delete)) {
        $db->rollback();
        m060Fail('TASK_MENU_DELETE_FAILED');
    }
    $db->commit();

    $module = new modChinaPlanetOperations($db);
    $module->menu = array_values(array_filter($module->menu, function ($menu) use ($taskUrls) {
        return in_array($menu['url'], $taskUrls, true);
    }));
    if ($module->insert_menus() !== 0) m060Fail('TASK_MENU_INSTALL_FAILED');
} catch (Throwable $exception) {
    m060Fail(m060MigrationFailureReason($exception));
}

$migration = new InternalTaskMigration($db);
if (!$migration->isReady(true)) m060Fail('READINESS_FAILED_'.$migration->readinessReason());
if (!m060MenusReady($db, $entity)) m060Fail('READINESS_FAILED_MENUS_NOT_READY');
echo "M060_SCHEMA_APPLY=PASS\n";
