<?php
$root = dirname(__DIR__, 2);
$pages = array(
	'index.php',
	'admin/dashboard.php',
	'admin/import.php',
	'admin/batch.php',
	'admin/rules.php',
	'admin/ports.php',
	'admin/adjust.php',
	'admin/disabled.php',
	'admin/setup.php',
);

foreach ($pages as $page) {
	$content = file_get_contents($root.'/custom/chinaplanetshippingprices/'.$page);
	if (strpos($content, 'admin_shell.lib.php') === false || strpos($content, 'cpcc_admin_shell_open') === false || strpos($content, 'cpcc_admin_shell_close') === false) {
		fwrite(STDERR, 'FAIL dashboard shell missing from '.$page.PHP_EOL);
		exit(1);
	}
	print 'PASS '.$page.' uses Vision dashboard shell'.PHP_EOL;
}

$import = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/import.php');
$setup = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/setup.php');
if (strpos($import, 'chinaplanetsp-import-page') === false || strpos($setup, 'chinaplanetsp-settings-page') === false) {
	fwrite(STDERR, 'FAIL dedicated import/settings dashboard classes missing'.PHP_EOL);
	exit(1);
}
print 'Shipping prices dashboard shell tests passed'.PHP_EOL;
