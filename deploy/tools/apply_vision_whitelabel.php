<?php
/**
 * Enable the upgrade-safe Vision System white-label layer on the local instance.
 * This intentionally changes configuration only; it never edits ERP core files.
 */
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';

require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/visiontradingui/core/modules/modVisionTradingUI.class.php';

global $db, $conf;

$module = new modVisionTradingUI($db);
if (isModEnabled('visiontradingui')) {
	$result = $module->remove('local-white-label-refresh');
	if ($result <= 0) throw new RuntimeException($module->error ?: 'Unable to refresh Vision Trading UI registration');
}
$activation = activateModule('modVisionTradingUI', 0, 1);
if (!empty($activation['errors'])) {
	throw new RuntimeException('Unable to activate Vision Trading UI module: '.implode(', ', $activation['errors']));
}

$settings = array(
	'VISIONTRADINGUI_ENABLE_WHITE_LABEL' => array('1', 'chaine'),
	'VISIONTRADINGUI_BRAND_NAME' => array('Vision System', 'chaine'),
	'MAIN_APPLICATION_TITLE' => array('Vision System', 'chaine'),
	'MAIN_SHOW_VERSION' => array('0', 'chaine'),
	'MAIN_SHOW_LOGO' => array('1', 'chaine'),
	'MAIN_FAVICON_URL' => array('/custom/visiontradingui/img/vision-system-mark.svg', 'chaine'),
	'MAIN_MANIFEST_APPLI_LOGO_URL' => array('/custom/visiontradingui/img/vision-system-mark.svg', 'chaine'),
	'MAIN_MANIFEST_APPLI_LOGO_URL_SIZE' => array('512', 'chaine'),
);
foreach ($settings as $name=>$setting) {
	if (dolibarr_set_const($db, $name, $setting[0], $setting[1], 0, '', $conf->entity) < 0) {
		throw new RuntimeException('Unable to save branding setting '.$name.': '.$db->lasterror());
	}
}

$checkNames = array('MAIN_MODULE_VISIONTRADINGUI', 'VISIONTRADINGUI_ENABLE_WHITE_LABEL', 'VISIONTRADINGUI_BRAND_NAME', 'MAIN_APPLICATION_TITLE', 'MAIN_SHOW_VERSION', 'MAIN_SHOW_LOGO', 'MAIN_FAVICON_URL', 'MAIN_MANIFEST_APPLI_LOGO_URL', 'MAIN_MANIFEST_APPLI_LOGO_URL_SIZE');
$checkSql = "SELECT name,value FROM ".MAIN_DB_PREFIX."const WHERE entity=".(int) $conf->entity." AND name IN ('".implode("','", $checkNames)."') ORDER BY name";
$checkRes = $db->query($checkSql);
$verified = array();
if ($checkRes) while ($checkObj = $db->fetch_object($checkRes)) $verified[$checkObj->name] = $checkObj->value;

print "Vision System white-label branding enabled\n";
foreach ($verified as $name=>$value) print $name.'='.$value."\n";
