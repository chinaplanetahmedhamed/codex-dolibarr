<?php

$root = getenv('VISIONTRADINGUI_ROOT');
if (!$root) $root = dirname(__DIR__, 2).'/custom/visiontradingui';
$required = array(
    'core/modules/modVisionTradingUI.class.php',
    'core/hooks/actions_visiontradingui.class.php',
    'admin/setup.php',
    'lib/components.lib.php',
    'css/tokens.css', 'css/components.css', 'css/dashboard.css', 'js/dashboard.js',
    'langs/ar_SA/visiontradingui.lang', 'langs/en_US/visiontradingui.lang',
);
foreach ($required as $file) {
    if (!is_file($root.'/'.$file)) {
        fwrite(STDERR, 'FAIL missing '.$file."\n");
        exit(1);
    }
}

$descriptor = file_get_contents($root.'/core/modules/modVisionTradingUI.class.php');
$hook = file_get_contents($root.'/core/hooks/actions_visiontradingui.class.php');
$setup = file_get_contents($root.'/admin/setup.php');
$tokens = file_get_contents($root.'/css/tokens.css');
$components = file_get_contents($root.'/css/components.css');
$js = file_get_contents($root.'/js/dashboard.js');

$checks = array(
    'module_id' => strpos($descriptor, '$this->numero = 500300;') !== false,
    'feature_default_off' => strpos($descriptor, "'VISIONTRADINGUI_ENABLE_DASHBOARD', 'chaine', '0'") !== false,
    'standard_setup_route' => strpos($descriptor, "array('setup.php@visiontradingui')") !== false,
    'permission_default_off' => strpos($descriptor, '$this->rights[1][3] = 0;') !== false,
    'official_header_hook' => strpos($hook, 'function addHtmlHeader') !== false,
    'official_main_hook' => strpos($hook, 'function printMainArea') !== false,
    'dolibarr23_hook_contract' => strpos($descriptor, "'hooks' => array('main')") !== false
        && strpos($hook, "function addHtmlHeader") !== false
        && strpos($hook, "function printMainArea") !== false,
    'staging_env_guard' => strpos($hook, "getenv('VISIONTRADING_ENVIRONMENT')") !== false && strpos($hook, "!== 'staging'") !== false,
    'admin_only_setup' => strpos($setup, 'empty($user->admin)') !== false && strpos($setup, 'accessforbidden()') !== false,
    'setup_csrf' => strpos($setup, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false && strpos($setup, 'newToken()') !== false,
    'standard_const_write' => strpos($setup, "dolibarr_set_const(\$db, 'VISIONTRADINGUI_ENABLE_DASHBOARD'") !== false,
    'environment_read_only' => strpos($setup, "getenv('VISIONTRADING_ENVIRONMENT')") !== false && strpos($setup, "dolibarr_set_const(\$db, 'VISIONTRADING_ENVIRONMENT'") === false,
    'namespaced_assets' => strpos($tokens, '--cpui-') !== false && strpos($components, '.cpui-') !== false,
    'themes' => strpos($tokens, '[data-cpui-theme="dark"]') !== false && strpos($tokens, 'prefers-color-scheme:dark') !== false,
    'high_contrast' => strpos($tokens, '[data-cpui-contrast="high"]') !== false && strpos($components, 'forced-colors:active') !== false,
    'reduced_motion' => strpos($components, 'prefers-reduced-motion:reduce') !== false,
    'mobile_390' => strpos($components, '@media (max-width:480px)') !== false,
    'local_assets_only' => !preg_match('#(?:https?:)?//#i', $tokens.$components.$js.$hook) && stripos($tokens.$components, '@import') === false,
    'no_reference_chinese' => !preg_match('/[\x{3400}-\x{9FFF}]/u', $tokens.$components.$js.$hook),
);

require $root.'/lib/components.lib.php';
$sample = cpui_dashboard_shell_open('<unsafe>', 'مرحبا', array('data-role' => 'customer', 'onclick' => 'bad'))
    .cpui_kpi_card('Balance', '100', array('unit' => 'USD', 'badge' => 'Ready', 'status' => 'success'))
    .cpui_empty_state('Empty', 'Nothing here')
    .cpui_partial_state('Partial', 'Source unavailable', 'corr-safe-1')
    .cpui_freshness('Updated', '2026-07-12T12:00:00+08:00')
    .cpui_dashboard_shell_close();
$checks['component_escape'] = strpos($sample, '<unsafe>') === false && strpos($sample, '&lt;unsafe&gt;') !== false;
$checks['attribute_allowlist'] = strpos($sample, 'data-role="customer"') !== false && strpos($sample, 'onclick') === false;
$checks['semantic_badge'] = strpos($sample, 'cpui-badge--success') !== false && strpos($sample, 'aria-hidden="true"') !== false;

foreach ($checks as $name => $ok) {
    print $name.':'.($ok ? 'PASS' : 'FAIL')."\n";
    if (!$ok) exit(1);
}
