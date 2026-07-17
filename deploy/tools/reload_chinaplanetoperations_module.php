<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php';

global $db, $conf;

$module = new modChinaPlanetOperations($db);
if (isModEnabled('chinaplanetoperations')) {
	$removeResult = $module->remove('local-upgrade');
	if ($removeResult <= 0) throw new RuntimeException($module->error ?: 'Unable to clear old China Planet Operations registration');
}

$activation = activateModule('modChinaPlanetOperations', 0, 1);
if (!empty($activation['errors'])) {
	throw new RuntimeException('Unable to activate China Planet Operations module: '.implode(', ', $activation['errors']));
}

$sql = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=".(int) $conf->entity." AND name='MAIN_MODULE_CHINAPLANETOPERATIONS'";
$resql = $db->query($sql);
$object = $resql ? $db->fetch_object($resql) : null;
if (empty($object) || (string) $object->value !== '1') {
	throw new RuntimeException('China Planet Operations activation could not be verified');
}

print "China Planet Operations module installed, activated and reloaded\n";
print "MAIN_MODULE_CHINAPLANETOPERATIONS=1\n";
