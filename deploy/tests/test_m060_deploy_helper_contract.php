<?php

$root = dirname(__DIR__, 2);
$helperPath = $root.'/deploy/tools/chinaplanetos_backup_erp_m060_deploy.sh';
$installerPath = $root.'/deploy/tools/install_backup_erp_m060_deploy_helper.sh';
$helper = file_get_contents($helperPath);
$installer = file_get_contents($installerPath);
$sudoers = file_get_contents($root.'/deploy/config/codex-deploy-backup-erp-m060-deploy.sudoers');

function m060DeployAssert($condition, $reason)
{
    if (!$condition) {
        fwrite(STDERR, 'M060_DEPLOY_HELPER_CONTRACT=FAIL REASON='.$reason."\n");
        exit(1);
    }
}

m060DeployAssert(strpos($helper, "TARGET_CONTAINER='erp-staging-dolibarr-1'") !== false, 'ERP_TARGET');
m060DeployAssert(strpos($helper, "DB_CONTAINER='erp-staging-mariadb-1'") !== false, 'DB_TARGET');
m060DeployAssert(strpos($helper, "DB_VOLUME='erp-staging_erp-staging_mariadb_data'") !== false && strpos($helper, "DB_NAME='dolibarr'") !== false, 'DB_STORAGE_IDENTITY');
m060DeployAssert(strpos($helper, "TARGET_VOLUME='erp-staging_erp-staging_custom'") !== false, 'VOLUME_TARGET');
m060DeployAssert(strpos($helper, '/opt/codex-dolibarr-staging') === false && strpos($helper, 'vision_erp') === false && strpos($helper, 'vision_db') === false, 'FORBIDDEN_TARGET');
m060DeployAssert(strpos($helper, "owner_group_of \"\$TARGET_ROOT\") == 'www-data:www-data'") !== false, 'TARGET_OWNER_GATE');
m060DeployAssert(strpos($helper, '$(mode_of "$TARGET_ROOT") == 755') !== false, 'TARGET_MODE_GATE');
m060DeployAssert(strpos($helper, 'CODE_MUTATED=0; SCHEMA_MUTATED=0; ROLLBACK_MUTATED=0') !== false, 'SEPARATE_MUTATION_FLAGS');
m060DeployAssert(strpos($helper, 'restore_database_immediate') === false && strpos($helper, 'SCHEMA_RETAINED=YES DATA_RETAINED=YES') !== false, 'NONDESTRUCTIVE_RECOVERY');
m060DeployAssert(strpos($helper, 'recover_apply') !== false && strpos($helper, 'restore_code') !== false && strpos($helper, 'disable_schema') !== false, 'APPLY_RECOVERY');
m060DeployAssert(strpos($helper, 'recover_rollback') !== false && strpos($helper, 'reapply_release && apply_schema && live_checks') !== false, 'ROLLBACK_REAPPLY');
m060DeployAssert(strpos($helper, 'RETAINED_ARTIFACT') !== false && strpos($helper, 'prepare_retained_candidate') !== false, 'SELF_CONTAINED_ROLLBACK');
m060DeployAssert(strpos($helper, 'mktemp -d /root/m060-deploy-candidate.XXXXXX') !== false && strpos($helper, 'chmod 0700') !== false, 'PRIVATE_CONTAINER_CANDIDATE');
m060DeployAssert(strpos($helper, "DEPLOY_CONTEXT='m060-staging-579f17e-root-only'") !== false, 'MUTATOR_CONTEXT');
m060DeployAssert(strpos($helper, 'REQUEST_STATUS=PENDING') !== false && strpos($helper, 'append_audit') !== false, 'DURABLE_AUDIT');
m060DeployAssert(strpos($helper, 'REQUEST_STARTED=0') !== false && strpos($helper, 'PENDING_APPLY_PREPARE') !== false && strpos($helper, 'reconcile_pending') !== false, 'PENDING_RECONCILIATION');
m060DeployAssert(strpos($helper, 'finish_request \'APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED\' || return 1') !== false, 'TERMINAL_AUDIT_FAIL_CLOSED');
m060DeployAssert(strpos($helper, 'finalize_terminal_request_from_state') !== false && strpos($helper, 'APPLIED_RECOVERED_TERMINAL_AUDIT') !== false, 'FINAL_STATE_AUDIT_RECONCILIATION');
m060DeployAssert(strpos($helper, 'if [[ "$REQUEST_ACTION" == rollback ]]; then save_state DEPLOYED') !== false, 'ROLLBACK_NO_MUTATION_STATE');
m060DeployAssert(strpos($helper, 'check_environment 1; clear_stale_candidates') !== false && strpos($helper, "== 'root:root 700'") !== false, 'STALE_RESTART_GATE');
m060DeployAssert(strpos($helper, 'command_status()') !== false && strpos($helper, 'load_state; REQUEST_STARTED=0; grep') !== false, 'STATUS_READ_ONLY');
m060DeployAssert(strpos($helper, 'ROLLBACK_FAILED_NO_MUTATION_RECOVERED_TERMINAL_AUDIT') !== false, 'ROLLBACK_FINAL_AUDIT_ACCURATE');
m060DeployAssert(strpos($helper, '"$target" == "$module_root/"*') !== false && strpos($helper, 'TARGET_PARENT_SWAPPED') !== false, 'EXACT_TARGET_BOUNDARY');
m060DeployAssert(strpos($helper, 'mktemp "$parent/.m060-apply.XXXXXX"') !== false && strpos($helper, 'fsync_path "$(dirname "$dst")"') !== false, 'ATOMIC_FILE_METADATA');
m060DeployAssert(strpos($helper, 'database_fingerprint') !== false && strpos($helper, 'CHECKSUM TABLE') !== false, 'FULL_RESTORE_REHEARSAL');
m060DeployAssert(substr_count($sudoers, "\n") === 6, 'SUDOERS_COMMAND_COUNT');
foreach (array('compare', 'preflight', 'apply', 'verify', 'rollback', 'status') as $command) {
    m060DeployAssert(substr_count($sudoers, '/usr/local/sbin/chinaplanetos-backup-erp-m060-deploy '.$command) === 1, 'SUDOERS_'.$command);
}
m060DeployAssert(strpos($installer, 'GLOBAL_SUDOERS_PRECHECK_FAILED') < strpos($installer, 'mv "$sudoers_stage" "$TARGET_SUDOERS"'), 'INSTALL_PRECHECK_ORDER');
m060DeployAssert(strpos($installer, 'installed_sudoers') !== false && strpos($installer, 'INSTALL_COMPLETE') !== false, 'INSTALL_TRANSACTION');
m060DeployAssert(strpos($installer, "readonly PREVIOUS_HELPER_SHA='fef8f45d32aed25d76a68c1e34dc7ee7174a88c29ae63c5a657777d49e7f6949'") !== false, 'INSTALL_PREVIOUS_HELPER_PIN');
m060DeployAssert(strpos($installer, 'helper_replaced') !== false && strpos($installer, 'PREVIOUS_HELPER_BACKUP_INVALID') !== false, 'INSTALL_UPGRADE_TRANSACTION');
m060DeployAssert(strpos($installer, 'DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO') !== false, 'INSTALL_BOUNDARY');
preg_match("/readonly HELPER_SHA='([0-9a-f]{64})'/", $installer, $helperMatch);
preg_match("/readonly SUDOERS_SHA='([0-9a-f]{64})'/", $installer, $sudoersMatch);
m060DeployAssert(($helperMatch[1] ?? '') === hash_file('sha256', $helperPath), 'INSTALLER_HELPER_PIN');
m060DeployAssert(($sudoersMatch[1] ?? '') === hash('sha256', $sudoers), 'INSTALLER_SUDOERS_PIN');
m060DeployAssert(strpos($helper, '__ARTIFACT_') === false && strpos($installer, '__DEPLOY_') === false, 'UNRESOLVED_PLACEHOLDER');

echo "M060_DEPLOY_HELPER_CONTRACT=PASS\n";
