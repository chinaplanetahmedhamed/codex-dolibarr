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

m074PreflightAssert(strpos($helper, 'RELEASE = "M074-R08"') !== false, 'RELEASE_PIN');
m074PreflightAssert(strpos($helper, 'SOURCE_COMMIT = "a2cd4cefac1f3389a535c055d112f537954bb62b"') !== false, 'SOURCE_COMMIT_PIN');
m074PreflightAssert(strpos($helper, 'ARTIFACT_SHA = "2a37f3b13c74bbf98f033a00b2c9df02dea3ffc6108b100f01bbd78ef155c1ee"') !== false, 'ARTIFACT_PIN');
m074PreflightAssert(hash('sha256', $helper) === 'f8f71b4d2b0bbceb060f81690845a56866a89f332e24e7c030c5dce543e40c5f', 'HELPER_HASH');
m074PreflightAssert(hash('sha256', $sudoers) === '3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99', 'SUDOERS_HASH');
m074PreflightAssert(strpos($installer, "HELPER_SHA='f8f71b4d2b0bbceb060f81690845a56866a89f332e24e7c030c5dce543e40c5f'") !== false, 'INSTALLER_HELPER_PIN');
m074PreflightAssert(strpos($installer, "SUDOERS_SHA='3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99'") !== false, 'INSTALLER_SUDOERS_PIN');
m074PreflightAssert(strpos($installer, "PREVIOUS_HELPER_SHA='10192397c88db2d4f8ab0dbc61623a40af62c3bce6ad9a21c21d1790570754f3'") !== false&&strpos($installer, "PREVIOUS_SUDOERS_SHA='3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99'") !== false, 'INSTALLER_PREDECESSOR_PINS');
m074PreflightAssert(hash('sha256', $installer) === '24ca87347e7ec0202371d38a67cf3aa765a96565c40d2c402b25439c2d6c3097', 'INSTALLER_HASH');
m074PreflightAssert(strpos($helper, 'TARGET_CONTAINER = "erp-staging-dolibarr-1"') !== false, 'CANONICAL_ERP_TARGET');
m074PreflightAssert(strpos($helper, 'TARGET_VOLUME = "erp-staging_erp-staging_custom"') !== false, 'CANONICAL_VOLUME_TARGET');
m074PreflightAssert(strpos($helper, 'TARGET_HTTP = "http://127.0.0.1:18088"') !== false, 'CANONICAL_HTTP_TARGET');
m074PreflightAssert(strpos($helper, 'command_compare') !== false && strpos($helper, 'command_preflight') !== false && strpos($helper, 'command_status') !== false, 'READ_ONLY_COMMANDS_MISSING');
m074PreflightAssert(strpos($helper, 'command_apply') !== false && strpos($helper, 'command_rollback') !== false && strpos($helper, 'command_verify') !== false, 'STAGED_MUTATING_COMMANDS_MISSING');
m074PreflightAssert(strpos($helper, 'require_plan') !== false && strpos($helper, 'DATABASE_IDENTITY_CHANGED_AFTER_RESTORE_CHECK') !== false && strpos($helper, 'RENAME_EXCHANGE') !== false, 'APPLY_GATES_MISSING');
m074PreflightAssert(strpos($helper, 'EXPECTED_BASELINE_SHA = "39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094"') !== false && strpos($helper, 'fail("BASELINE_NOT_PINNED")') !== false && strpos($helper, 'fail("BASELINE_MISMATCH")') !== false, 'BASELINE_GATE_MISSING');
m074PreflightAssert(strpos($helper, 'DEPLOY_RUNTIME_FILES = 378') !== false && strpos($helper, 'TREE_SHA=') !== false && strpos($helper, 'EXPECTED_LAYOUT_TREE_SHA') !== false && strpos($helper, 'COMPATIBILITY_OVERLAY_CHANGED') !== false && strpos($helper, 'overlay_unmanaged_matches') !== false, 'COMPATIBILITY_DIAGNOSTIC_MISSING');
m074PreflightAssert(substr_count($helper, '559') >= 3 && strpos($helper, '"members": 559') !== false && strpos($helper, 'len(rows) != 559') !== false && strpos($helper, 'len(members) != 559') !== false, 'RELEASE_MEMBER_COUNT_PIN');
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
