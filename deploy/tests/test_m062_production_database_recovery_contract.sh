#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
recovery="$repo_root/deploy/tools/recover_production_m062_database.sh"
failures="$repo_root/deploy/tests/test_m062_production_database_recovery_failures.sh"

fail() { echo "M062_PRODUCTION_DB_RECOVERY_CONTRACT=FAIL REASON=$1" >&2; exit 1; }

bash -n "$recovery" || fail SYNTAX
bash -n "$failures" || fail FAILURE_TEST_SYNTAX
[[ $(grep -Fxc "readonly SOURCE_BACKUP='/root/chinaplanetos-production-db-backups/m062-before-d6c2953-m062-20260828T070839Z-4f2d6bf8b151.sql'" "$recovery") -eq 1 ]] || fail SOURCE_PATH_PIN
[[ $(grep -Fxc "readonly SOURCE_BACKUP_SHA='c9e94e44f375100c586264241269cbf1521894a2a2d4df5aba0b3dc79602f578'" "$recovery") -eq 1 ]] || fail SOURCE_HASH_PIN
[[ $(grep -Fxc "readonly FAILED_HELPER_SHA='9bb10f73b8ccb1928c15bf2d0b23af17cd9aa1bda7f5578cec70f8b951f56b9f'" "$recovery") -eq 1 ]] || fail HELPER_HASH_PIN
for identity in vision_erp vision_db vision-os_mariadb_data dolibarr_db; do grep -Fq "$identity" "$recovery" || fail "IDENTITY_${identity}"; done
grep -Fq "APPLY_FAILED_DB_RECOVERY_REQUIRED" "$recovery" || fail FAILED_STATE_GATE
grep -Fq "state_value STATE_DB_BACKUP" "$recovery" || fail STATE_BACKUP_GATE
grep -Fq 'readonly LOCK_FILE="${STATE_ROOT}/m062.lock"' "$recovery" || fail SHARED_HELPER_LOCK
grep -Fq 'prepare_private_root "$RECOVERY_ROOT"' "$recovery" || fail RECOVERY_ROOT_GATE
grep -Fq "dump_active_database \"\$SAFETY_BACKUP\"" "$recovery" || fail SAFETY_BACKUP
grep -Fq "SAFETY_VALIDATION_MISMATCH" "$recovery" || fail SAFETY_VALIDATION
grep -Fq "SOURCE_FINGERPRINT" "$recovery" || fail SOURCE_FINGERPRINT
grep -Fq "ACTIVE_FINGERPRINT_MISMATCH" "$recovery" || fail ACTIVE_FINGERPRINT
grep -Fq "verify_prestate" "$recovery" || fail PRESTATE_CHECK
grep -Fq 'verify_prestate "$SOURCE_VALIDATION_DB"' "$recovery" || fail SOURCE_PRESTATE_CHECK
grep -Fq "rowid=407 AND pod='CASABLANCA'" "$recovery" || fail SOURCE_ROW_PRESTATE
grep -Fq "pod='KHALIFA PORT, ABU DHABI'" "$recovery" || fail KHALIFA_PRESTATE
grep -Fq '[[ "$khalifa_rows" == 24 && "$agadir_rows" == 22 ]]' "$recovery" || fail RATE_COUNTS_PRESTATE
grep -Fq "restore_safety_backup" "$recovery" || fail AUTO_RESTORE
grep -Fq '[[ $(database_fingerprint "$DB_NAME") == "$SAFETY_FINGERPRINT" ]]' "$recovery" || fail AUTO_RESTORE_FINGERPRINT
grep -Fq 'restore_archived_state "$STATE_ARCHIVE" "$STATE_FILE" "$STATE_FILE_SHA"' "$recovery" || fail STATE_AUTO_RESTORE
grep -Fq 'mark_record_status "$RECORD_FILE" "$record_status"' "$recovery" || fail PASS_RECORD_ROLLBACK
grep -Fq 'record_status=ROLLBACK_FAILED' "$recovery" || fail FAILED_ROLLBACK_RECORD
grep -Fq 'STATE_ARCHIVED=1' "$recovery" || fail STATE_ARCHIVE_FLAG
grep -Fq 'RECORD_PUBLISHED=1' "$recovery" || fail RECORD_PUBLISHED_FLAG
grep -Fq 'state_archive_observed=1' "$recovery" || fail STATE_ARCHIVE_FILESYSTEM_INFERENCE
grep -Fq 'record_published_observed=1' "$recovery" || fail RECORD_PUBLISHED_FILESYSTEM_INFERENCE
grep -Fq "trap 'exit 143' TERM" "$recovery" || fail SIGNAL_TO_EXIT_TRAP
grep -Fq 'start_erp_and_wait || recovery_rc=1' "$recovery" || fail AUTO_RESTORE_HTTP
grep -Fq 'drop_validation_databases || recovery_rc=1' "$recovery" || fail VALIDATION_CLEANUP_VISIBLE
grep -Fq 'ensure_erp_stopped || erp_stop_verified=NO' "$recovery" || fail MANUAL_FAILURE_ERP_STOP
grep -Fq "STATE_ARCHIVE" "$recovery" || fail STATE_ARCHIVE
grep -Fq "PRODUCTION_ACCESSED=YES" "$recovery" || fail PRODUCTION_EVIDENCE
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz/'" "$recovery" || fail HTTP_TARGET_PIN
grep -Fq "'/root/recover-production-m062-database-r03'" "$recovery" || fail INVOCATION_PATH_PIN
if grep -Eq 'erp-staging|backup-erp|127\.0\.0\.1:18088|/opt/codex-dolibarr-staging' "$recovery"; then fail FORBIDDEN_STAGING_TARGET; fi
if grep -Eq 'rm -rf|docker (compose|rm|volume rm)|systemctl|reboot|shutdown' "$recovery"; then fail EXPANDED_MUTATION; fi

echo 'M062_PRODUCTION_DB_RECOVERY_CONTRACT=PASS TARGET=PRODUCTION_ONLY SOURCE=CHECKSUM_PINNED SAFETY_BACKUP=VALIDATED AUTO_RESTORE=ENABLED STATE=ARCHIVED PRODUCTION_ACCESSED=YES'
