<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php';

$module = new modChinaPlanetCustomerCenter($db);
$removeResult = $module->remove('local-upgrade');
if ($removeResult <= 0) {
    throw new RuntimeException($module->error ?: 'Unable to clear old Customer Center menu registration');
}
$result = $module->init('local-upgrade');
if ($result <= 0) {
    throw new RuntimeException($module->error ?: 'Unable to reload ChinaPlanet Customer Center module');
}
print "ChinaPlanet Customer Center module reloaded\n";
