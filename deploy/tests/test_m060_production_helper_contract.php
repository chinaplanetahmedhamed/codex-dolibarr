<?php

$root = dirname(__DIR__, 2);
$helperPath = $root.'/deploy/tools/chinaplanetos_production_m060_deploy.sh';
$installerPath = $root.'/deploy/tools/install_production_m060_deploy_helper.sh';
$sudoersPath = $root.'/deploy/config/codex-deploy-production-m060-deploy.sudoers';
$helper = file_get_contents($helperPath);
$installer = file_get_contents($installerPath);
$sudoers = file_get_contents($sudoersPath);

function m060ProductionAssert($condition, $reason)
{
    if (!$condition) {
        fwrite(STDERR, 'M060_PRODUCTION_HELPER_CONTRACT=FAIL REASON='.$reason."\n");
        exit(1);
    }
}

m060ProductionAssert(strpos($helper, "TARGET_CONTAINER='vision_erp'") !== false, 'ERP_TARGET');
m060ProductionAssert(strpos($helper, "DB_CONTAINER='vision_db'") !== false, 'DB_TARGET');
m060ProductionAssert(strpos($helper, "TARGET_VOLUME='vision-os_dolibarr_custom'") !== false, 'CUSTOM_VOLUME');
m060ProductionAssert(strpos($helper, "DB_VOLUME='vision-os_mariadb_data'") !== false, 'DB_VOLUME');
m060ProductionAssert(strpos($helper, "DB_NAME='dolibarr_db'") !== false, 'DB_NAME');
m060ProductionAssert(strpos($helper, "TARGET_HTTP='https://erp.visiontrading.biz'") !== false, 'LIVE_URL');
m060ProductionAssert(strpos($helper, "'true|vision-os|dolibarr'") !== false, 'ERP_COMPOSE_IDENTITY');
m060ProductionAssert(strpos($helper, "'true|vision-os|mariadb'") !== false, 'DB_COMPOSE_IDENTITY');
m060ProductionAssert(strpos($helper, "STATE_ROOT='/var/lib/chinaplanetos-m060-deploy-production-r08'") !== false, 'STATE_ISOLATION');
m060ProductionAssert(strpos($helper, "RUNTIME_COMMIT='4cb98b36eedfe932097f66822f48837b423d2337'") !== false, 'RUNTIME_PIN');
m060ProductionAssert(strpos($helper, "ARTIFACT_SHA='e438730431f759c581a0b361fab5c9f370b7ab9acbf00f8b5b95bd46efec1d00'") !== false, 'ARTIFACT_PIN');
m060ProductionAssert(strpos($helper, 'erp-staging') === false && strpos($helper, 'backup-erp') === false, 'STAGING_IDENTIFIER');
m060ProductionAssert(strpos($helper, 'restore_database_immediate') === false, 'FULL_DB_REWIND');
m060ProductionAssert(strpos($helper, 'database_fingerprint') !== false && strpos($helper, 'CHECKSUM TABLE') !== false, 'RESTORE_REHEARSAL');
m060ProductionAssert(strpos($helper, 'recover_apply') !== false && strpos($helper, 'recover_rollback') !== false, 'RECOVERY_PATHS');
m060ProductionAssert(strpos($helper, 'PRODUCTION_ACCESSED=YES') !== false, 'PRODUCTION_AUDIT');
m060ProductionAssert(substr_count($sudoers, "\n") === 6, 'SUDOERS_COMMAND_COUNT');
foreach (array('compare', 'preflight', 'apply', 'verify', 'rollback', 'status') as $command) {
    m060ProductionAssert(substr_count($sudoers, '/usr/local/sbin/chinaplanetos-production-m060-deploy '.$command) === 1, 'SUDOERS_'.$command);
}
preg_match("/readonly HELPER_SHA='([0-9a-f]{64})'/", $installer, $helperMatch);
preg_match("/readonly SUDOERS_SHA='([0-9a-f]{64})'/", $installer, $sudoersMatch);
m060ProductionAssert(($helperMatch[1] ?? '') === hash_file('sha256', $helperPath), 'INSTALLER_HELPER_PIN');
m060ProductionAssert(($sudoersMatch[1] ?? '') === hash_file('sha256', $sudoersPath), 'INSTALLER_SUDOERS_PIN');
m060ProductionAssert(strpos($installer, "TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m060-deploy'") !== false, 'INSTALL_TARGET');
m060ProductionAssert(strpos($installer, 'GLOBAL_SUDOERS_PRECHECK_FAILED') < strpos($installer, 'mv "$sudoers_stage" "$TARGET_SUDOERS"'), 'INSTALL_TRANSACTION');

echo "M060_PRODUCTION_HELPER_CONTRACT=PASS\n";
