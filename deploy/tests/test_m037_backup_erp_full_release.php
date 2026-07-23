<?php

$root = dirname(__DIR__, 2);
$helper = file_get_contents($root.'/deploy/tools/chinaplanetos_backup_erp_m037_full_release.sh');
$sudoers = file_get_contents($root.'/deploy/config/codex-deploy-backup-erp-m037-full-release.sudoers');
$installer = file_get_contents($root.'/deploy/tools/install_backup_erp_m037_full_release_helper.sh');
$runtimeManifest = file($root.'/deploy/releases/m037-runtime-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$supportManifest = file($root.'/deploy/releases/m037-support-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

function m037Assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "M037_RELEASE_TEST=FAIL REASON=".$message.PHP_EOL);
        exit(1);
    }
}

m037Assert(is_string($helper) && is_string($sudoers) && is_string($installer), 'release helper, sudoers, or installer is unreadable');
m037Assert(count($runtimeManifest) === 52, 'runtime allowlist count is not 52');
m037Assert(count($supportManifest) === 17, 'support allowlist count is not 17');
$sortedRuntime = $runtimeManifest;
$sortedSupport = $supportManifest;
sort($sortedRuntime, SORT_STRING);
sort($sortedSupport, SORT_STRING);
m037Assert($runtimeManifest === $sortedRuntime && count($runtimeManifest) === count(array_unique($runtimeManifest)), 'runtime allowlist is not sorted and unique');
m037Assert($supportManifest === $sortedSupport && count($supportManifest) === count(array_unique($supportManifest)), 'support allowlist is not sorted and unique');
foreach (array_merge($runtimeManifest, $supportManifest) as $relative) {
    m037Assert(is_file($root.'/'.$relative) && !is_link($root.'/'.$relative), 'allowlisted file missing or symbolic: '.$relative);
}

foreach (array(
    "readonly RUNTIME_COMMIT='824fa129ff3f4445ba2c469728fef8245957f05e'",
    "readonly ARTIFACT_SHA='32037f6a0a6a1b7e8cc579f6eba25d4569e66f6e27e28b0d4b87c31bc375c30e'",
    "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'",
    "readonly TARGET_DB_CONTAINER='erp-staging-mariadb-1'",
    "readonly TARGET_MAILPIT_CONTAINER='erp-staging-mailpit-1'",
    "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'",
    "readonly TARGET_HTTP='http://127.0.0.1:18088'",
    "docker port \"\$TARGET_CONTAINER\" 80/tcp",
    "docker port \"\$TARGET_MAILPIT_CONTAINER\" 1025/tcp",
    "docker port \"\$TARGET_MAILPIT_CONTAINER\" 8025/tcp",
    'ARTIFACT_ALLOWLIST_MISMATCH',
    'ARTIFACT_SYMLINK_FORBIDDEN',
    'ARTIFACT_TYPE_FORBIDDEN',
    'ARTIFACT_COPY=$(mktemp /root/m037-artifact.',
    'capture_schema_prestate',
    'verify_schema_prestate',
    'restore_database_backup',
    'finalize_full_database_restore',
    'authenticated_route_checks',
    "save_state 'APPLY_IN_PROGRESS'",
    "save_state 'APPLY_FAILED_ROLLBACK_FAILED'",
    'M037_STAGING_AUTO_FULL_BACKUP_ROLLBACK=PASS',
    "save_state 'ROLLED_BACK'",
    'ROLLBACK_FAILED_PRESTATE_RECOVERY_RETAINED',
    'ROLLBACK_FAILED_DEPLOYED_STATE_REAPPLIED',
    'automatic_rollback',
    'M037_STAGING_AUTO_ROLLBACK_VERIFY=PASS',
    'create_database_backup',
    'M037_STAGING_APPLY=PASS',
    'M037_STAGING_VERIFY=PASS',
) as $needle) m037Assert(strpos($helper, $needle) !== false, 'helper contract missing '.$needle);
foreach (array(
    'com.docker.compose.project',
    'service_owner=$(docker exec "$TARGET_CONTAINER"',
    "target_owner\" == '0:0' || \"\$target_owner\" == \"\$service_owner",
    'STAGING_INBOX_METADATA_INVALID',
    'STAGING_TARGET_DISK_LOW',
    'STAGING_INBOX_DISK_LOW',
    'verify_code_backup_restored',
    'verify_schema_prestate',
    'ROLLBACK_FAILED_REAPPLY_VERIFY_FAILED',
    'test_customer_portal_scope_integration.php',
    'test_unified_admin_shell.php',
) as $needle) m037Assert(strpos($helper, $needle) !== false, 'staging guard missing '.$needle);
m037Assert(strpos($helper, 'M037_AUTH_COOKIE') === false && strpos($helper, '--cookie') === false, 'authenticated cookie leaves the in-process route test');

foreach (array('vision_erp','vision_db','vision-os_','erp.visiontrading.biz','production-m') as $forbidden) {
    m037Assert(stripos($helper, $forbidden) === false, 'production identifier leaked into staging helper');
}
m037Assert(strpos($helper, "grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE_SHA|DEPLOYED_AT)='") !== false, 'status output is not a strict non-secret allowlist');

$allowedCommands = array('compare','preflight','apply','verify','rollback','status');
foreach ($allowedCommands as $command) {
    $line = 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m037-full-release-deploy '.$command;
    m037Assert(substr_count($sudoers, $line) === 1, 'sudoers missing exact '.$command.' command');
}
m037Assert(count(array_filter(explode("\n", trim($sudoers)))) === count($allowedCommands), 'sudoers contains an unexpected command');
m037Assert(strpos($installer, "readonly HELPER_SHA='8044c08ce3d917fdd0b98b99b755496a8df0ce90acde39af8eeee709f505c908'") !== false, 'installer helper checksum is not pinned');
m037Assert(strpos($installer, "readonly PREVIOUS_HELPER_SHA='b87c646320620a4779e39b99273cbb5bdf147d5f54f3a7434ed3568a157e9e55'") !== false, 'installer previous helper checksum is not pinned');
m037Assert(strpos($installer, "readonly SUDOERS_SHA='815e0040fce5ebe2fa77f840a3811e3733db995c3bf4360c39fa105c4d4deab1'") !== false, 'installer sudoers checksum is not pinned');
m037Assert(hash_file('sha256', $root.'/deploy/tools/chinaplanetos_backup_erp_m037_full_release.sh') === '8044c08ce3d917fdd0b98b99b755496a8df0ce90acde39af8eeee709f505c908', 'pinned helper checksum does not match helper bytes');
m037Assert(hash_file('sha256', $root.'/deploy/config/codex-deploy-backup-erp-m037-full-release.sudoers') === '815e0040fce5ebe2fa77f840a3811e3733db995c3bf4360c39fa105c4d4deab1', 'pinned sudoers checksum does not match sudoers bytes');
m037Assert(strpos($installer, 'private_root=$(mktemp -d /root/m037-helper-install.') !== false, 'installer does not use a root-owned immutable input copy');
m037Assert(strpos($installer, 'install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"') !== false, 'installer helper input is not copied once before validation');
m037Assert(strpos($installer, "PRODUCTION_ACCESSED=NO") !== false, 'installer production boundary is not explicit');

echo "M037_RELEASE_TEST=PASS RUNTIME_FILES=52 SUPPORT_FILES=17 COMMANDS=6 PRODUCTION=FORBIDDEN".PHP_EOL;
