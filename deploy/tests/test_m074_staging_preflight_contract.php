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

m074PreflightAssert(strpos($helper, 'RELEASE = "M074-R07"') !== false, 'RELEASE_PIN');
m074PreflightAssert(strpos($helper, 'SOURCE_COMMIT = "fc6f0fe1bfba303ab45ed011d634ec7712b6ccda"') !== false, 'SOURCE_COMMIT_PIN');
m074PreflightAssert(strpos($helper, 'ARTIFACT_SHA = "3c022220ef7d6d2540d54eedd5d6bac2b2c5c0892d7e5024c5989f25329fbedc"') !== false, 'ARTIFACT_PIN');
m074PreflightAssert(hash('sha256', $helper) === 'cc653f486f5d637c93b04d8d4c8585017ca4157304c42de18b350c2d3d2cd361', 'HELPER_HASH');
m074PreflightAssert(hash('sha256', $sudoers) === '3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99', 'SUDOERS_HASH');
m074PreflightAssert(strpos($installer, "HELPER_SHA='cc653f486f5d637c93b04d8d4c8585017ca4157304c42de18b350c2d3d2cd361'") !== false, 'INSTALLER_HELPER_PIN');
m074PreflightAssert(strpos($installer, "SUDOERS_SHA='3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99'") !== false, 'INSTALLER_SUDOERS_PIN');
m074PreflightAssert(strpos($installer, "PREVIOUS_HELPER_SHA='a879de5b41c68d140c79b71333fa4337bda5451bb4acba0ddad9368aaef57f89'") !== false&&strpos($installer, "PREVIOUS_SUDOERS_SHA='3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99'") !== false, 'INSTALLER_PREDECESSOR_PINS');
m074PreflightAssert(hash('sha256', $installer) === 'beb5e27a309aeaea4edf45cd4f7e260062d94c7dbd4123b9aeee41ca2a8ce958', 'INSTALLER_HASH');
m074PreflightAssert(strpos($helper, 'TARGET_CONTAINER = "erp-staging-dolibarr-1"') !== false, 'CANONICAL_ERP_TARGET');
m074PreflightAssert(strpos($helper, 'TARGET_VOLUME = "erp-staging_erp-staging_custom"') !== false, 'CANONICAL_VOLUME_TARGET');
m074PreflightAssert(strpos($helper, 'TARGET_HTTP = "http://127.0.0.1:18088"') !== false, 'CANONICAL_HTTP_TARGET');
m074PreflightAssert(strpos($helper, 'command_compare') !== false && strpos($helper, 'command_preflight') !== false && strpos($helper, 'command_status') !== false, 'READ_ONLY_COMMANDS_MISSING');
m074PreflightAssert(strpos($helper, 'command_apply') !== false && strpos($helper, 'command_rollback') !== false && strpos($helper, 'command_verify') !== false, 'STAGED_MUTATING_COMMANDS_MISSING');
m074PreflightAssert(strpos($helper, 'require_plan') !== false && strpos($helper, 'DATABASE_IDENTITY_CHANGED_AFTER_RESTORE_CHECK') !== false && strpos($helper, 'RENAME_EXCHANGE') !== false, 'APPLY_GATES_MISSING');
m074PreflightAssert(strpos($helper, 'EXPECTED_BASELINE_SHA = "UNBOUND_DIAGNOSTIC_ONLY"') !== false && strpos($helper, 'fail("BASELINE_NOT_PINNED")') !== false && strpos($helper, 'fail("BASELINE_MISMATCH")') !== false, 'BASELINE_GATE_MISSING');
m074PreflightAssert(strpos($helper, 'DEPLOY_RUNTIME_FILES = 378') !== false && strpos($helper, 'TREE_SHA=') !== false, 'COMPATIBILITY_DIAGNOSTIC_MISSING');
m074PreflightAssert(strpos($helper, 'fail("PREFLIGHT_REQUIRED_AFTER_STATE")') !== false && strpos($helper, 'minimum_created_at') !== false, 'STALE_PLAN_GATE_MISSING');
m074PreflightAssert(strpos($helper, 'DATABASE_CHANGED=NO') !== false && strpos($helper, 'PRODUCTION_ACCESSED=NO') !== false, 'SAFETY_MARKERS_MISSING');
m074PreflightAssert(strpos($helper, '"env", "-u", "M017_DOL_DOCUMENT_ROOT", "-u", "M017_CREDENTIAL_FILE", "-u", "M017_API_BASE"') !== false, 'M017_INTEGRATION_ENV_NOT_CLEARED');
m074PreflightAssert(strpos($helper, 'test_m070_composite_source.php') === false && strpos($helper, 'test_m017_mobile_api_route.php') === false, 'NON_SELF_CONTAINED_CANDIDATE_TEST_EXPOSED');
m074PreflightAssert(strpos($helper, 'TARGET_SHA=') !== false && strpos($helper, 'TARGET_MODE=') !== false && strpos($helper, 'TARGET_UID=') !== false, 'TARGET_BASELINE_COMPARE_MISSING');
m074PreflightAssert(strpos($helper, 'erp-production') === false && strpos($helper, 'chinaplanet-web-production') === false, 'PRODUCTION_IDENTIFIER_EXPOSED');
m074PreflightAssert(substr_count($sudoers, "\n") === 6, 'SUDOERS_COMMAND_COUNT');
m074PreflightAssert(strpos($sudoers, ' apply') !== false && strpos($sudoers, ' rollback') !== false && strpos($sudoers, ' verify') !== false, 'SUDOERS_FIXED_MUTATION_COMMANDS_MISSING');
m074PreflightAssert(strpos($installer, "COMMANDS=compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE=YES") !== false, 'INSTALLER_COMMAND_SURFACE_MISSING');
m074PreflightAssert(strpos($installer, 'GLOBAL_SUDOERS_PRECHECK_FAILED') !== false && strpos($installer, "previous_state='old'") !== false && strpos($installer, 'previous-helper') !== false && strpos($installer, 'published=1') !== false, 'INSTALLER_TRANSACTION_GUARD_MISSING');
m074PreflightAssert(strpos($installer, '#!/bin/bash') === 0 && strpos($installer, "/usr/bin/python3 -I -c 'import ast,pathlib,sys; ast.parse") !== false && strpos($installer, 'bash -n "$private_helper"') === false, 'INSTALLER_ISOLATED_PYTHON_VALIDATION_MISSING');
m074PreflightAssert(strpos($installer, 'AUTOMATIC_RESTORE_FAILED RECOVERY_REQUIRED=YES') !== false && strpos($installer, 'M074_INSTALL_FAULT_TEST') !== false && strpos($installer, 'recovery_retained=1') !== false, 'INSTALLER_FAIL_CLOSED_RESTORE_MISSING');
m074PreflightAssert(strpos($helper, '__ARTIFACT_SHA__') === false && strpos($helper, '__SOURCE_COMMIT__') === false && strpos($installer, '__HELPER_SHA__') === false && strpos($installer, '__SUDOERS_SHA__') === false, 'UNRESOLVED_RELEASE_PLACEHOLDER');

echo "M074_STAGING_PREFLIGHT_CONTRACT=PASS\n";
