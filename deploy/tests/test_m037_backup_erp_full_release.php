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
m037Assert(count($supportManifest) === 14, 'support allowlist count is not 14');
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
    "readonly RUNTIME_COMMIT='8be3edb644c5da4b03a80da589dff466904b8e64'",
    "readonly ARTIFACT_SHA='a60968c489b016e58e4543751d9942498d4dda467b3007ab22ee89ed00bfb80a'",
    "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'",
    "readonly TARGET_DB_CONTAINER='erp-staging-mariadb-1'",
    "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'",
    "readonly TARGET_HTTP='http://127.0.0.1:18088'",
    'ARTIFACT_ALLOWLIST_MISMATCH',
    'ARTIFACT_SYMLINK_FORBIDDEN',
    'capture_schema_prestate',
    'ROLLBACK_SCHEMA_RESTORE_FAILED',
    'automatic_rollback',
    'create_database_backup',
    'M037_STAGING_APPLY=PASS',
    'M037_STAGING_VERIFY=PASS',
) as $needle) m037Assert(strpos($helper, $needle) !== false, 'helper contract missing '.$needle);

foreach (array('vision_erp','vision_db','vision-os_','erp.visiontrading.biz','production-m') as $forbidden) {
    m037Assert(stripos($helper, $forbidden) === false, 'production identifier leaked into staging helper');
}
m037Assert(strpos($helper, "grep -E '^STATE_(RELEASE|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE_SHA|DEPLOYED_AT)='") !== false, 'status output is not a strict non-secret allowlist');

$allowedCommands = array('compare','preflight','apply','verify','rollback','status');
foreach ($allowedCommands as $command) {
    $line = 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m037-full-release-deploy '.$command;
    m037Assert(substr_count($sudoers, $line) === 1, 'sudoers missing exact '.$command.' command');
}
m037Assert(count(array_filter(explode("\n", trim($sudoers)))) === count($allowedCommands), 'sudoers contains an unexpected command');
m037Assert(strpos($installer, "readonly HELPER_SHA='e950920cf2f634a5969bca96e4f301ad14328019983406a281ebadf4daf1a41d'") !== false, 'installer helper checksum is not pinned');
m037Assert(strpos($installer, "readonly SUDOERS_SHA='815e0040fce5ebe2fa77f840a3811e3733db995c3bf4360c39fa105c4d4deab1'") !== false, 'installer sudoers checksum is not pinned');
m037Assert(strpos($installer, "PRODUCTION_ACCESSED=NO") !== false, 'installer production boundary is not explicit');

echo "M037_RELEASE_TEST=PASS RUNTIME_FILES=52 SUPPORT_FILES=14 COMMANDS=6 PRODUCTION=FORBIDDEN".PHP_EOL;
