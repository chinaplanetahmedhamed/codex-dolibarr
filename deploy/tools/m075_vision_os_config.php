<?php
/** Production-safe configuration helper for the M075 Vision-China Planet OS release. */
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);

$action = isset($argv[1]) ? $argv[1] : '';
if (!in_array($action, array('snapshot', 'apply', 'verify', 'restore'), true)) {
	fwrite(STDERR, "M075_CONFIG_FAIL=ACTION_NOT_ALLOWED\n");
	exit(1);
}
$documentRoot = getenv('M075_DOL_DOCUMENT_ROOT');
if ($documentRoot !== '/var/www/html' && getenv('M075_CONFIG_TEST_MODE') !== '1') {
	fwrite(STDERR, "M075_CONFIG_FAIL=DOCUMENT_ROOT_NOT_ALLOWED\n");
	exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $documentRoot ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = 'erp.visiontrading.biz';
$_SERVER['SERVER_PORT'] = '443';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

global $db, $conf;
$settings = array(
	'VISIONTRADINGUI_ENABLE_WHITE_LABEL' => '1',
	'VISIONTRADINGUI_BRAND_NAME' => 'Vision-China Planet OS',
	'MAIN_APPLICATION_TITLE' => 'Vision-China Planet OS',
	'MAIN_SHOW_VERSION' => '0',
	'MAIN_SHOW_LOGO' => '1',
	'MAIN_FAVICON_URL' => '/custom/visiontradingui/img/vision-system-mark.svg',
	'MAIN_MANIFEST_APPLI_LOGO_URL' => '/custom/visiontradingui/img/vision-system-mark.svg',
	'MAIN_MANIFEST_APPLI_LOGO_URL_SIZE' => '512',
);
$entity = (int) $conf->entity;
$names = array_keys($settings);

function m075ReadSettings($db, $entity, $names)
{
	$sql = "SELECT name,value,type,visible,note FROM ".MAIN_DB_PREFIX."const WHERE entity=".(int) $entity." AND name IN ('".implode("','", array_map(array($db, 'escape'), $names))."')";
	$res = $db->query($sql);
	if (!$res) throw new RuntimeException($db->lasterror());
	$rows = array();
	while ($obj = $db->fetch_object($res)) {
		$rows[$obj->name] = array('value'=>(string) $obj->value, 'type'=>(string) $obj->type, 'visible'=>(int) $obj->visible, 'note'=>(string) $obj->note);
	}
	return $rows;
}

try {
	if ($action === 'snapshot') {
		print json_encode(array('release'=>'M075-R01', 'entity'=>$entity, 'settings'=>m075ReadSettings($db, $entity, $names)), JSON_UNESCAPED_SLASHES).PHP_EOL;
		exit(0);
	}
	if ($action === 'restore') {
		$payload = json_decode(stream_get_contents(STDIN), true);
		if (!is_array($payload) || ($payload['release'] ?? '') !== 'M075-R01' || (int) ($payload['entity'] ?? 0) !== $entity || !isset($payload['settings']) || !is_array($payload['settings'])) throw new RuntimeException('SNAPSHOT_INVALID');
		foreach ($names as $name) {
			if (isset($payload['settings'][$name])) {
				$row = $payload['settings'][$name];
				if (dolibarr_set_const($db, $name, (string) $row['value'], (string) $row['type'], (int) $row['visible'], (string) $row['note'], $entity) < 0) throw new RuntimeException($db->lasterror());
			} else {
				if (dolibarr_del_const($db, $name, $entity) < 0) throw new RuntimeException($db->lasterror());
			}
		}
		print "M075_CONFIG_RESTORE=PASS\n";
		exit(0);
	}
	if ($action === 'apply') {
		foreach ($settings as $name=>$value) if (dolibarr_set_const($db, $name, $value, 'chaine', 0, '', $entity) < 0) throw new RuntimeException($db->lasterror());
	}
	$current = m075ReadSettings($db, $entity, $names);
	foreach ($settings as $name=>$value) if (!isset($current[$name]) || $current[$name]['value'] !== $value) throw new RuntimeException('SETTING_MISMATCH_'.$name);
	print 'M075_CONFIG_'.strtoupper($action)."=PASS BRAND=Vision-China_Planet_OS\n";
} catch (Throwable $e) {
	fwrite(STDERR, 'M075_CONFIG_FAIL='.preg_replace('/[^A-Za-z0-9_.-]/', '_', $e->getMessage()).PHP_EOL);
	exit(1);
}
