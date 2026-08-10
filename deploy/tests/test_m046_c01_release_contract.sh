#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd -P)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m046_c01_translation_policy.sh"
installer="$root/deploy/tools/install_backup_erp_m046_c01_helper.sh"
builder="$root/deploy/tools/build_m046_c01_artifact.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m046-c01.sudoers"
workflow="$root/.github/workflows/deploy-m046-c01-backup-erp.yml"
runtime_manifest="$root/deploy/releases/m046-c01-runtime-files.txt"
support_manifest="$root/deploy/releases/m046-c01-support-files.txt"

die() { echo "M046_C01_RELEASE_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

bash -n "$helper"; bash -n "$installer"; bash -n "$builder"
[[ $(sha256 "$helper") == '2546a927e27c3b0fcf1d4957c0b8864fd1359a177250fcc980bdd2f0bc50ea1f' ]] || die 'HELPER_HASH_INVALID'
[[ $(sha256 "$installer") == '124eacc1316e11b96a428842bd71183dece8088dc9118c7f172286bb5c9a2704' ]] || die 'INSTALLER_HASH_INVALID'
[[ $(sha256 "$builder") == '7b66b8da8fc6650a6525689462f47eb0b1854dbafb14f2543cca48edc02089b6' ]] || die 'BUILDER_HASH_INVALID'
[[ $(sha256 "$sudoers") == 'a44d291dcc7a3a417c98aff42421b88d51212f97b7ef26406a7adba14a98633b' ]] || die 'SUDOERS_HASH_INVALID'
[[ $(sha256 "$runtime_manifest") == '69d754158a34b54dbf3186a2ba69c7a9a2c958a5f43b3e0605462cc470c7f798' ]] || die 'RUNTIME_MANIFEST_HASH_INVALID'
[[ $(sha256 "$support_manifest") == '6c64b2f9441a1a3fdbb8950f295209f973c34c7e788d05b7303a5c5c7e4ec47b' ]] || die 'SUPPORT_MANIFEST_HASH_INVALID'

[[ $(wc -l <"$runtime_manifest" | tr -d ' ') == 6 ]] || die 'RUNTIME_SCOPE_INVALID'
[[ $(wc -l <"$support_manifest" | tr -d ' ') == 19 ]] || die 'SUPPORT_SCOPE_INVALID'
LC_ALL=C sort -c "$runtime_manifest"; LC_ALL=C sort -c "$support_manifest"
[[ -z $(comm -12 "$runtime_manifest" "$support_manifest") ]] || die 'MANIFEST_OVERLAP'

grep -Fq "readonly RUNTIME_COMMIT='15c19dda5842be992e6926db473878042a0a96d5'" "$helper" || die 'RUNTIME_COMMIT_PIN_ABSENT'
grep -Fq "readonly ARTIFACT_SHA='79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596'" "$helper" || die 'ARTIFACT_PIN_ABSENT'
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper" || die 'TARGET_CONTAINER_PIN_ABSENT'
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper" || die 'TARGET_VOLUME_PIN_ABSENT'
grep -Fq "readonly TARGET_HTTP='http://127.0.0.1:18088'" "$helper" || die 'TARGET_HTTP_PIN_ABSENT'
grep -Fq "REQUEST_ID_REPLAYED" "$helper" || die 'REPLAY_GUARD_ABSENT'
grep -Fq "NOOP_ALREADY_MATCHING" "$helper" || die 'NOOP_CONTRACT_ABSENT'
grep -Fq "ROLLBACK_RESTORE_FAILED_REAPPLIED" "$helper" || die 'ROLLBACK_REAPPLY_CONTRACT_ABSENT'
grep -Fq "STORED_ROW_SHA256" "$helper" || die 'CONFIG_ROW_PROOF_ABSENT'
grep -Fq "DISK_SPACE_INSUFFICIENT" "$helper" || die 'DISK_SPACE_GATE_ABSENT'
grep -Fq 'fsync_path "$CODE_BACKUP"' "$helper" || die 'BACKUP_FSYNC_ABSENT'
grep -Fq 'fsync_path "$source"' "$helper" || die 'TARGET_FSYNC_ABSENT'
grep -Fq '$(owner_group_of "$target") == "$owner:$group"' "$helper" || die 'OWNER_GROUP_RESTORE_CHECK_ABSENT'
grep -Fq 'release_file_current "$index"' "$helper" || die 'COMPARE_METADATA_CHECK_ABSENT'
grep -Fq '${REQUEST_ID}.tar.gz' "$helper" || die 'REQUEST_SCOPED_BACKUP_ABSENT'
grep -Fq 'terminal_status="${action_upper}_FAILED_${restore_result}"' "$helper" || die 'TERMINAL_FAILED_REQUEST_ABSENT'
grep -Fq "'deploy/tests/test_m046_c01_setup_route.php'" "$helper" || die 'SETUP_CANDIDATE_TEST_ABSENT'
grep -Fq 'STATE=UNTRACKED_MATCHING' "$helper" || die 'UNTRACKED_NOOP_VERIFY_ABSENT'
grep -Fq 'install -m 0644 "$CANDIDATE_ROOT/${RUNTIME_FILES[$index]}"' "$helper" || die 'PINNED_CANDIDATE_REAPPLY_ABSENT'
if grep -Fq 'LIVE_PRE_ROLLBACK_INVALID' "$helper"; then die 'EMERGENCY_ROLLBACK_BLOCKED_BY_LIVE_CHECK'; fi
grep -Fq 'M046_C01_TRANSLATION_SERVICE_PATH' "$helper" || die 'CANDIDATE_POLICY_PATH_ABSENT'

grep -Fq "readonly HELPER_SHA='2546a927e27c3b0fcf1d4957c0b8864fd1359a177250fcc980bdd2f0bc50ea1f'" "$installer" || die 'INSTALLER_HELPER_PIN_INVALID'
grep -Fq "readonly PREVIOUS_HELPER_SHA='2b47bbb8e19a8a9869d5410fbe1122d30b089b66c41c0535e96e670b3df686fa'" "$installer" || die 'INSTALLER_PREVIOUS_HELPER_PIN_INVALID'
grep -Fq '[[ "$existing_helper_sha" == "$HELPER_SHA" || "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]]' "$installer" || die 'INSTALLER_UPGRADE_SCOPE_INVALID'
grep -Fq 'normalized="${stamp:0:4}-${stamp:4:2}-${stamp:6:2}T${stamp:9:2}:${stamp:11:2}:${stamp:13:2}Z"' "$helper" || die 'REQUEST_TIME_NORMALIZATION_ABSENT'
grep -Fq 'date -u -d "$normalized"' "$helper" || die 'GNU_DATE_NORMALIZED_INPUT_ABSENT'
if grep -Fq 'date -u -d "$stamp"' "$helper"; then die 'GNU_DATE_RAW_COMPACT_INPUT_PRESENT'; fi
grep -Fq "readonly SUDOERS_SHA='a44d291dcc7a3a417c98aff42421b88d51212f97b7ef26406a7adba14a98633b'" "$installer" || die 'INSTALLER_SUDOERS_PIN_INVALID'
grep -Fq '/var/lib/chinaplanetos-m046-c01-staging/requests' "$installer" || die 'REQUEST_ROOT_INSTALL_ABSENT'
grep -Fq 'OLD_M044_HELPER_PRESERVED=YES' "$installer" || die 'OLD_HELPER_PRESERVATION_ABSENT'

[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") == 6 ]] || die 'SUDOERS_COMMAND_COUNT_INVALID'
grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m046-c01-translation-policy compare' "$sudoers" || die 'COMPARE_SUDOERS_INVALID'
grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m046-c01-translation-policy preflight' "$sudoers" || die 'PREFLIGHT_SUDOERS_INVALID'
grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m046-c01-translation-policy verify' "$sudoers" || die 'VERIFY_SUDOERS_INVALID'
grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m046-c01-translation-policy status' "$sudoers" || die 'STATUS_SUDOERS_INVALID'
[[ $(grep -c ' apply m046-c01-' "$sudoers") == 1 && $(grep -c ' rollback m046-c01-' "$sudoers") == 1 ]] || die 'MUTATION_SUDOERS_INVALID'

grep -Fq 'DEPLOY_M046_C01_TO_BACKUP_ERP' "$workflow" || die 'WORKFLOW_CONFIRMATION_ABSENT'
grep -Fq 'environment: backup-erp' "$workflow" || die 'WORKFLOW_ENVIRONMENT_INVALID'
grep -Fq 'Verify separately installed staging helper' "$workflow" || die 'SEPARATE_HELPER_INSTALL_BOUNDARY_ABSENT'
grep -Fq 'Compare staging files read-only' "$workflow" || die 'COMPARE_READ_ONLY_LABEL_ABSENT'
grep -Fq 'Run restricted staging preflight read-only' "$workflow" || die 'PREFLIGHT_READ_ONLY_LABEL_ABSENT'
grep -Fq 'Reconcile staging after every apply attempt' "$workflow" || die 'APPLY_RECONCILIATION_ABSENT'
grep -Fq "steps.reconcile.outputs.needs_rollback == 'true'" "$workflow" || die 'RECONCILIATION_ROLLBACK_ABSENT'
grep -Fq 'Remove only the correction-owned inbox artifact' "$workflow" || die 'INBOX_CLEANUP_ABSENT'
grep -Fq "ARTIFACT_SHA: 79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596" "$workflow" || die 'WORKFLOW_ARTIFACT_PIN_INVALID'

if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|environment:[[:space:]]+production' "$helper" "$installer" "$workflow"; then die 'PRODUCTION_IDENTIFIER_PRESENT'; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper" "$installer"; then die 'DATABASE_COMMAND_PRESENT'; fi
if grep -Eq 'rm[[:space:]]+-rf[[:space:]]+(/root/backup-erp-code-backups|/var/lib/chinaplanetos-m046-c01-staging|/etc/sudoers|/usr/local/sbin)' "$helper" "$installer"; then die 'FORBIDDEN_CLEANUP_SCOPE'; fi

echo 'M046_C01_RELEASE_CONTRACT=PASS RUNTIME_FILES=6 SUPPORT_FILES=19 SUDOERS_COMMANDS=6 ARTIFACT_MEMBERS=27 HELPER_INSTALL=SEPARATE STAGING=PINNED PRODUCTION=FORBIDDEN DATABASE_CHANGED=NO'
