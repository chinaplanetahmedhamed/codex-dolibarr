<?php

$root = dirname(__DIR__, 2);
$helper = file_get_contents($root.'/deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh');
$installer = file_get_contents($root.'/deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh');
$sudoers = file_get_contents($root.'/deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers');

function m074PreflightAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M074_STAGING_PREFLIGHT_CONTRACT=FAIL REASON=".$message."\n");
        exit(1);
    }
}

m074PreflightAssert(strpos($helper, 'RELEASE = "M074-R06"') !== false, 'RELEASE_PIN');
m074PreflightAssert(strpos($helper, 'SOURCE_COMMIT = "d58fb5602e0d2019eaac47316550ab9574045b46"') !== false, 'SOURCE_COMMIT_PIN');
m074PreflightAssert(strpos($helper, 'ARTIFACT_SHA = "849f8885833a299b8ec8934c4fdd9ef42c968ccf6dc48bf836f1bbbd4612504f"') !== false, 'ARTIFACT_PIN');
m074PreflightAssert(hash('sha256', $helper) === '864e12817fbb997aadfd6cb1fc481c3a790d0a23ebeb29a9ef206f1e2a71d434', 'HELPER_HASH');
m074PreflightAssert(hash('sha256', $sudoers) === '777217564edf5a9b1dd67617e73cdf4e8f7fe3f90c3095ccc1930d1016fdbf38', 'SUDOERS_HASH');
m074PreflightAssert(strpos($installer, "HELPER_SHA='2f1f9f24ed2f0a1bda121190f41d5a6c73bf6c01fab209c877b03bfd4ef16e39'") !== false, 'INSTALLER_HELPER_PIN');
m074PreflightAssert(strpos($installer, "SUDOERS_SHA='777217564edf5a9b1dd67617e73cdf4e8f7fe3f90c3095ccc1930d1016fdbf38'") !== false, 'INSTALLER_SUDOERS_PIN');
m074PreflightAssert(hash('sha256', $installer) === '94bb5a839cb93f89e5d3d541a4aa6ac3c9c1f79e2102f98fc4d165d1a5430c1c', 'INSTALLER_HASH');
m074PreflightAssert(strpos($helper, 'TARGET_CONTAINER = "erp-staging-dolibarr-1"') !== false, 'CANONICAL_ERP_TARGET');
m074PreflightAssert(strpos($helper, 'TARGET_VOLUME = "erp-staging_erp-staging_custom"') !== false, 'CANONICAL_VOLUME_TARGET');
m074PreflightAssert(strpos($helper, 'TARGET_HTTP = "http://127.0.0.1:18088"') !== false, 'CANONICAL_HTTP_TARGET');
m074PreflightAssert(strpos($helper, 'command_compare') !== false && strpos($helper, 'command_preflight') !== false && strpos($helper, 'command_status') !== false, 'READ_ONLY_COMMANDS_MISSING');
m074PreflightAssert(strpos($helper, 'command_apply') !== false && strpos($helper, 'command_rollback') !== false && strpos($helper, 'command_verify') !== false, 'STAGED_MUTATING_COMMANDS_MISSING');
m074PreflightAssert(strpos($helper, 'require_plan') !== false && strpos($helper, 'DATABASE_IDENTITY_CHANGED_AFTER_RESTORE_CHECK') !== false && strpos($helper, 'RENAME_EXCHANGE') !== false, 'APPLY_GATES_MISSING');
m074PreflightAssert(strpos($helper, 'DATABASE_CHANGED=NO') !== false && strpos($helper, 'PRODUCTION_ACCESSED=NO') !== false, 'SAFETY_MARKERS_MISSING');
m074PreflightAssert(strpos($helper, '"env", "-u", "M017_DOL_DOCUMENT_ROOT", "-u", "M017_CREDENTIAL_FILE", "-u", "M017_API_BASE"') !== false, 'M017_INTEGRATION_ENV_NOT_CLEARED');
m074PreflightAssert(strpos($helper, 'test_m070_composite_source.php') === false && strpos($helper, 'test_m017_mobile_api_route.php') === false, 'NON_SELF_CONTAINED_CANDIDATE_TEST_EXPOSED');
m074PreflightAssert(strpos($helper, 'TARGET_SHA=') !== false && strpos($helper, 'TARGET_MODE=') !== false && strpos($helper, 'TARGET_UID=') !== false, 'TARGET_BASELINE_COMPARE_MISSING');
m074PreflightAssert(strpos($helper, 'erp-production') === false && strpos($helper, 'chinaplanet-web-production') === false, 'PRODUCTION_IDENTIFIER_EXPOSED');
m074PreflightAssert(substr_count($sudoers, "\n") === 3, 'SUDOERS_COMMAND_COUNT');
m074PreflightAssert(strpos($sudoers, ' apply') === false && strpos($sudoers, ' rollback') === false && strpos($sudoers, ' verify') === false, 'SUDOERS_MUTATION_EXPOSED');
m074PreflightAssert(strpos($installer, "COMMANDS=compare,preflight,status APPLY_AVAILABLE=NO") !== false, 'ROOT_UPGRADE_REMAINS_UNAPPROVED');
m074PreflightAssert(strpos($installer, 'GLOBAL_SUDOERS_PRECHECK_FAILED') !== false && strpos($installer, 'created_helper=1') !== false && strpos($installer, 'created_sudoers=1') !== false, 'INSTALLER_TRANSACTION_GUARD_MISSING');
m074PreflightAssert(strpos($helper, '__ARTIFACT_SHA__') === false && strpos($helper, '__SOURCE_COMMIT__') === false && strpos($installer, '__HELPER_SHA__') === false && strpos($installer, '__SUDOERS_SHA__') === false, 'UNRESOLVED_RELEASE_PLACEHOLDER');

echo "M074_STAGING_PREFLIGHT_CONTRACT=PASS\n";
