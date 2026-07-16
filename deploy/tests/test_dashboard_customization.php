<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';

function dashboardAssert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    print 'PASS '.$message.PHP_EOL;
}

$table = MAIN_DB_PREFIX.'cpcc_dashboard_layout';
$tableResult = $db->query("SHOW TABLES LIKE '".$db->escape($table)."'");
dashboardAssert($tableResult && $db->num_rows($tableResult) === 1, 'dashboard layout table exists');

$columns = array();
$columnResult = $db->query('SHOW COLUMNS FROM '.$table);
while ($columnResult && ($column = $db->fetch_object($columnResult))) $columns[] = $column->Field;
foreach (array('entity','fk_user','dashboard_key','widget_key','position','widget_size','visible') as $required) {
    dashboardAssert(in_array($required, $columns, true), 'layout column '.$required.' exists');
}

$indexResult = $db->query('SHOW INDEX FROM '.$table." WHERE Key_name='uk_cpcc_dashboard_widget'");
dashboardAssert($indexResult && $db->num_rows($indexResult) === 4, 'layout is unique per entity, user, dashboard, and widget');

$endpoint = file_get_contents(DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/ajax/dashboard_layout.php');
dashboardAssert(strpos($endpoint, 'empty($user->admin)') !== false, 'layout endpoint is admin-only');
dashboardAssert(strpos($endpoint, 'hash_equals') !== false && strpos($endpoint, 'currentToken()') !== false, 'layout endpoint validates CSRF token');
dashboardAssert(strpos($endpoint, '$user->id') !== false, 'layout persistence is scoped to the current user');

print 'Dashboard customization integration tests passed'.PHP_EOL;
