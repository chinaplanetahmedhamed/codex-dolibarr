<?php
$root = dirname(__DIR__, 2);
$helperPath = $root.'/deploy/tools/chinaplanetos_production_m075_vision_ui.sh';
$installerPath = $root.'/deploy/tools/install_production_m075_vision_ui_helper.sh';
$sudoersPath = $root.'/deploy/config/codex-deploy-production-m075-vision-ui.sudoers';
$configPath = $root.'/deploy/tools/m075_vision_os_config.php';
$helper = file_get_contents($helperPath);
$installer = file_get_contents($installerPath);
$sudoers = file_get_contents($sudoersPath);
$config = file_get_contents($configPath);

function contractAssert($condition, $message)
{
	if (!$condition) {
		fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
		exit(1);
	}
	print 'PASS '.$message.PHP_EOL;
}

contractAssert(hash_file('sha256', $helperPath) === '1fd9041ee91e88231318dba8d33edc05cab9292eb1bd8ebae5518a62b1370941', 'helper matches its immutable pin');
contractAssert(hash_file('sha256', $sudoersPath) === '1f3c02d9f8b5923b89c90f5451b5ced2f1ae3eba8ba0cfe8f89bdd2f0349ef92', 'sudoers matches its immutable pin');
contractAssert(strpos($installer, "HELPER_SHA='1fd9041ee91e88231318dba8d33edc05cab9292eb1bd8ebae5518a62b1370941'") !== false, 'installer pins the exact helper');
contractAssert(strpos($installer, "PREVIOUS_HELPER_SHA='4ae414d560ea4baa6ad20e06300e86f72e337ee8d82fc01f9bce7891bdee1964'") !== false, 'installer accepts only the installed R01 predecessor');
contractAssert(strpos($installer, "SUDOERS_SHA='1f3c02d9f8b5923b89c90f5451b5ced2f1ae3eba8ba0cfe8f89bdd2f0349ef92'") !== false, 'installer pins the exact sudoers file');
contractAssert(strpos($helper, "ARTIFACT_SHA='9a37ae2a1a03c45b9d561d29dae2e8ab28994eb709932fe969b04fe74ed90da1'") !== false, 'helper pins the exact release artifact');
contractAssert(strpos($helper, "TARGET_CONTAINER='vision_erp'") !== false && strpos($helper, "TARGET_VOLUME='vision-os_dolibarr_custom'") !== false, 'helper is restricted to the production ERP container and custom volume');
contractAssert(strpos($helper, "TARGET_HTTP='https://erp.visiontrading.biz/'") !== false, 'helper verifies only the production ERP URL');
contractAssert(strpos($helper, 'create_backups; save_state BACKED_UP') < strpos($helper, 'mutated=1; apply_files'), 'code and configuration backups precede every release mutation');
contractAssert(strpos($helper, 'restore_backups') !== false && strpos($helper, 'APPLY_FAILED_AUTO_ROLLED_BACK') !== false, 'failed apply has automatic recovery');
contractAssert(strpos($helper, 'config_action snapshot') !== false && strpos($helper, 'config_action restore') !== false, 'configuration snapshot and restore are both implemented');
contractAssert(strpos($config, "array('snapshot', 'apply', 'verify', 'restore')") !== false, 'configuration helper exposes only four fixed actions');
contractAssert(strpos($config, "'Vision-China Planet OS'") !== false, 'configuration helper uses the approved exact brand');
contractAssert(strpos($config, 'INSERT INTO') === false && strpos($config, 'UPDATE ') === false, 'configuration helper has no direct business-table writes');

$sudoersLines = array_values(array_filter(array_map('trim', explode("\n", $sudoers))));
contractAssert(count($sudoersLines) === 6, 'sudoers contains exactly six rules');
$commands = array('compare','preflight','apply','verify','rollback','status');
foreach ($commands as $index=>$command) contractAssert($sudoersLines[$index] === 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m075-vision-ui '.$command, 'sudoers command '.$command.' is exact');
contractAssert(strpos($sudoers, '*') === false && strpos($sudoers, 'ALL,') === false, 'sudoers has no wildcard or command list expansion');

print 'M075 production helper contract passed'.PHP_EOL;
