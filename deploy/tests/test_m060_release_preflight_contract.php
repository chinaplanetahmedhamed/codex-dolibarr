<?php

$root = dirname(__DIR__, 2);
$helper = file_get_contents($root.'/deploy/tools/chinaplanetos_backup_erp_m060_internal_tasks.sh');
$installer = file_get_contents($root.'/deploy/tools/install_backup_erp_m060_helper.sh');
$sudoers = file_get_contents($root.'/deploy/config/codex-deploy-backup-erp-m060.sudoers');
$runtime = file($root.'/deploy/releases/m060-runtime-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$support = file($root.'/deploy/releases/m060-support-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$baseline = file($root.'/deploy/releases/m060-m056-baseline.sha256', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

function m060ReleaseAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M060_RELEASE_PREFLIGHT_CONTRACT=FAIL REASON=".$message."\n");
        exit(1);
    }
}

m060ReleaseAssert(count($runtime) === 38, 'RUNTIME_MANIFEST_COUNT');
m060ReleaseAssert(count($support) === 9, 'SUPPORT_MANIFEST_COUNT');
m060ReleaseAssert(count($baseline) === 38, 'BASELINE_MANIFEST_COUNT');
m060ReleaseAssert(strpos($helper, "RUNTIME_COMMIT='579f17e319dc7ac0255e8ea2bb6594eec7aa01d1'") !== false, 'RUNTIME_COMMIT_PIN');
m060ReleaseAssert(strpos($helper, "BASELINE_COMMIT='606b54c852230b0674b0745084e107487cf3161d'") !== false, 'BASELINE_COMMIT_PIN');
m060ReleaseAssert(strpos($helper, 'command_compare') !== false && strpos($helper, 'command_preflight') !== false && strpos($helper, 'command_status') !== false, 'READ_ONLY_COMMANDS_MISSING');
m060ReleaseAssert(strpos($helper, 'command_apply') === false && strpos($helper, 'command_rollback') === false && strpos($helper, 'command_verify') === false, 'MUTATING_COMMAND_EXPOSED');
m060ReleaseAssert(strpos($helper, 'APPLY_AVAILABLE=NO') !== false && strpos($helper, 'PRODUCTION_ACCESSED=NO') !== false, 'BOUNDARY_MARKERS_MISSING');
m060ReleaseAssert(substr_count($sudoers, "\n") === 3, 'SUDOERS_COMMAND_COUNT');
m060ReleaseAssert(strpos($sudoers, ' apply') === false && strpos($sudoers, ' rollback') === false && strpos($sudoers, ' verify') === false, 'SUDOERS_MUTATION_EXPOSED');
m060ReleaseAssert(strpos($installer, "COMMANDS=compare,preflight,status APPLY_AVAILABLE=NO") !== false, 'INSTALLER_BOUNDARY_MISSING');
m060ReleaseAssert(strpos($helper, '__') === false && strpos($installer, '__') === false, 'UNRESOLVED_RELEASE_PLACEHOLDER');

echo "M060_RELEASE_PREFLIGHT_CONTRACT=PASS\n";
