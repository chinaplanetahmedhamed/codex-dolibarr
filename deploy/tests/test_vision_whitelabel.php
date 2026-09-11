<?php
$root = dirname(__DIR__, 2);

function brandAssert($condition, $message)
{
	if (!$condition) {
		fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
		exit(1);
	}
	print 'PASS '.$message.PHP_EOL;
}

$hook = file_get_contents($root.'/custom/visiontradingui/core/hooks/actions_visiontradingui.class.php');
$hookEntrypoint = file_get_contents($root.'/custom/visiontradingui/class/actions_visiontradingui.class.php');
$setup = file_get_contents($root.'/custom/visiontradingui/admin/setup.php');
$branding = file_get_contents($root.'/custom/visiontradingui/js/branding.js');
$brandingCss = file_get_contents($root.'/custom/visiontradingui/css/branding.css');
$module = file_get_contents($root.'/custom/visiontradingui/core/modules/modVisionTradingUI.class.php');
$apply = file_get_contents($root.'/deploy/tools/apply_vision_whitelabel.php');

brandAssert(strpos($hook, 'VISIONTRADINGUI_ENABLE_WHITE_LABEL') !== false, 'global hook supports white-label mode');
brandAssert(strpos($hookEntrypoint, "core/hooks/actions_visiontradingui.class.php") !== false, 'hook is exposed through the standard runtime path');
brandAssert(strpos($hook, 'vision-system-brand') !== false, 'brand name reaches the browser safely through metadata');
brandAssert(strpos($module, "'mainloginpage'") !== false, 'white-label hook is registered for unauthenticated login pages');
brandAssert(strpos($hook, 'vision-system-logo') !== false && strpos($hook, 'branding.css') !== false, 'logo and immediate branding styles reach the browser');
brandAssert(is_file($root.'/custom/visiontradingui/img/vision-system-logo.svg'), 'full Vision-China Planet OS login logo exists');
brandAssert(is_file($root.'/custom/visiontradingui/img/vision-system-mark.svg'), 'compact Vision-China Planet OS navigation mark exists');
brandAssert(strpos($brandingCss, '#img_logo') !== false && strpos($brandingCss, '.menulogocontainer img.mycompany') !== false, 'logo CSS is scoped to login and top navigation only');
brandAssert(strpos($setup, "'MAIN_APPLICATION_TITLE'") !== false, 'setup uses the native application-title setting');
brandAssert(strpos($setup, "'MAIN_SHOW_VERSION'") !== false, 'setup hides the engine version from normal UI');
brandAssert(strpos($branding, "['title', 'aria-label', 'alt', 'placeholder']") !== false, 'visible and accessibility labels are branded');
brandAssert(strpos($branding, "setAttribute('name'") === false && strpos($branding, "querySelectorAll('a") === false, 'branding never rewrites technical form names or navigation links');
brandAssert(strpos($branding, "getElementById('img_logo')") !== false, 'login logo is replaced after page load as a runtime fallback');
brandAssert(strpos($branding, ".menulogocontainer img.mycompany") !== false, 'internal navigation logo is replaced after page load as a runtime fallback');
brandAssert(strpos($apply, "'Vision-China Planet OS'") !== false && strpos($apply, 'dolibarr_set_const') !== false, 'local apply tool is idempotent and configuration based');
brandAssert(strpos($apply, "activateModule('modVisionTradingUI'") !== false, 'local apply tool refreshes the standard hook registration');
brandAssert(strpos($apply, "'MAIN_FAVICON_URL'") !== false && strpos($apply, "'MAIN_MANIFEST_APPLI_LOGO_URL'") !== false, 'native favicon and application manifest use the Vision mark');

$visibleFiles = array(
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php',
	'custom/chinaplanetcustomercenter/admin_dashboard.php',
	'custom/chinaplanetcustomercenter/board.php',
	'custom/chinaplanetcustomercenter/order.php',
	'custom/chinaplanetcustomercenter/orders.php',
	'custom/chinaplanetcustomercenter/projects.php',
	'custom/chinaplanetcustomercenter/funding.php',
	'custom/chinaplanetcustomercenter/admin/operations.php',
	'custom/cpkanban/board.php',
);
foreach ($visibleFiles as $file) {
	brandAssert(stripos(file_get_contents($root.'/'.$file), 'Dolibarr') === false, $file.' has no legacy visible brand text');
}

print 'Vision white-label tests passed'.PHP_EOL;
