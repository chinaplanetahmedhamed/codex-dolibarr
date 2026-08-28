#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
if [[ -d /private/tmp ]]; then test_root=$(mktemp -d /private/tmp/m062-db-recovery-test.XXXXXX); else test_root=$(mktemp -d /tmp/m062-db-recovery-test.XXXXXX); fi
trap 'rm -rf "$test_root"' EXIT
cp "$repo_root/deploy/tools/recover_production_m062_database.sh" "$test_root/recover-production-m062-database.test"
export M062_DB_RECOVERY_LIBRARY_MODE=1
# shellcheck source=/dev/null
source "$test_root/recover-production-m062-database.test"
unset M062_DB_RECOVERY_LIBRARY_MODE

fail() { echo "M062_PRODUCTION_DB_RECOVERY_FAILURES=FAIL REASON=$1" >&2; exit 1; }
events="$test_root/events"
: >"$events"

mock_running=1
mock_fingerprint=$(printf 'a%.0s' {1..64})
docker() {
  case "$1" in
    inspect)
      if [[ "$3" == '{{.State.Running}}' ]]; then [[ $mock_running -eq 1 ]] && echo true || echo false
      else [[ $mock_running -eq 1 ]] && echo 'true|vision-os|dolibarr' || echo 'false|vision-os|dolibarr'; fi
      ;;
    stop) printf 'erp-stop\n' >>"$events"; mock_running=0 ;;
    start) printf 'erp-start\n' >>"$events"; mock_running=1 ;;
    *) return 1 ;;
  esac
}
drop_database() { printf 'db-drop:%s\n' "$1" >>"$events"; }
create_database() { printf 'db-create:%s\n' "$1" >>"$events"; }
import_database() { printf 'db-import:%s\n' "$1" >>"$events"; }
database_fingerprint() { printf '%s\n' "$mock_fingerprint"; }

SAFETY_BACKUP="$test_root/safety.sql"
printf 'validated safety dump\n' >"$SAFETY_BACKUP"
SAFETY_BACKUP_SHA=$(sha256 "$SAFETY_BACKUP")
SAFETY_FINGERPRINT=$mock_fingerprint
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
ERP_STOPPED=0
restore_safety_backup || fail VERIFIED_SAFETY_RESTORE_REJECTED
expected_order=$(printf 'erp-stop\ndb-drop:dolibarr_db\ndb-create:dolibarr_db\ndb-import:dolibarr_db')
[[ $(cat "$events") == "$expected_order" && $ERP_STOPPED -eq 1 ]] || fail RESTORE_NOT_QUIESCED

: >"$events"
mock_running=1
ERP_STOPPED=0
SAFETY_FINGERPRINT=$(printf 'b%.0s' {1..64})
if restore_safety_backup; then fail FINGERPRINT_MISMATCH_ACCEPTED; fi
grep -Fxq 'erp-stop' "$events" || fail MISMATCH_RESTORE_NOT_QUIESCED
grep -Fxq 'db-import:dolibarr_db' "$events" || fail MISMATCH_NOT_INJECTED_AFTER_IMPORT

owner_group_of() { echo root:root; }
mode_of() { [[ -d "$1" ]] && echo 700 || echo 600; }
flock() { return 0; }
archive="$test_root/state.archive"
state="$test_root/m062.state"
printf 'STATE_STATUS=APPLY_FAILED_DB_RECOVERY_REQUIRED\n' >"$archive"
archive_sha=$(sha256 "$archive")
restore_archived_state "$archive" "$state" "$archive_sha" || fail STATE_RESTORE_REJECTED
[[ -f "$state" && ! -e "$archive" && $(sha256 "$state") == "$archive_sha" ]] || fail STATE_RESTORE_NOT_EXACT

lock_root="$test_root/lock-root"
mkdir "$lock_root"
ln -s "$test_root/symlink-target" "$lock_root/m062.lock"
if prepare_lock "$lock_root" "$lock_root/m062.lock"; then fail LOCK_SYMLINK_ACCEPTED; fi
rm "$lock_root/m062.lock"
prepare_lock "$lock_root" "$lock_root/m062.lock" || fail SAFE_LOCK_REJECTED
[[ -f "$lock_root/m062.lock" && ! -L "$lock_root/m062.lock" ]] || fail SAFE_LOCK_NOT_CREATED
recovery_parent="$test_root/recovery-parent"
mkdir "$recovery_parent"
ln -s "$test_root/symlink-target" "$recovery_parent/database-recovery"
if prepare_private_root "$recovery_parent/database-recovery"; then fail RECOVERY_ROOT_SYMLINK_ACCEPTED; fi

pass_record="$test_root/recovery-pass.record"
printf 'RECOVERY_STATUS=PASS\nRECOVERY_ID=test\n' >"$pass_record"
mark_record_status "$pass_record" ROLLED_BACK || fail PASS_RECORD_ROLLBACK_REJECTED
grep -Fxq 'RECOVERY_STATUS=ROLLED_BACK' "$pass_record" || fail FALSE_PASS_RECORD_RETAINED
if grep -Fxq 'RECOVERY_STATUS=PASS' "$pass_record"; then fail FALSE_PASS_RECORD_RETAINED; fi

# A failed automatic database restore must preserve validation evidence and
# leave the application stopped instead of exposing an uncertain database.
restore_safety_backup() { printf 'auto-restore-failed\n' >>"$events"; return 1; }
drop_database() { printf 'validation-drop:%s\n' "$1" >>"$events"; }
start_erp_and_wait() { printf 'erp-health-check\n' >>"$events"; ERP_STOPPED=0; return 0; }
SOURCE_VALIDATION_DB=m062_recovery_source_1_deadbeef
SAFETY_VALIDATION_DB=m062_recovery_safety_1_deadbeef
ACTIVE_MUTATED=1
RECOVERY_COMPLETE=0
ERP_STOPPED=1
STATE_ARCHIVE=''
: >"$events"
set +e
cleanup_output=$(cleanup 2>&1)
cleanup_rc=$?
set -e
[[ $cleanup_rc -ne 0 ]] || fail FAILED_AUTO_RESTORE_ACCEPTED
grep -Fxq 'auto-restore-failed' "$events" || fail FAILED_AUTO_RESTORE_NOT_ATTEMPTED
if grep -Fq 'validation-drop:' "$events"; then fail FAILURE_EVIDENCE_DROPPED; fi
if grep -Fq 'erp-start' "$events"; then fail UNCERTAIN_DATABASE_EXPOSED; fi
[[ "$cleanup_output" == *'MANUAL_RECOVERY_ATTENTION_REQUIRED=YES'* && "$cleanup_output" == *'ERP_STOP_VERIFIED=YES'* && "$cleanup_output" == *"SOURCE_VALIDATION_DB=$SOURCE_VALIDATION_DB"* ]] || fail FAILURE_EVIDENCE_NOT_REPORTED

# Simulate a failure after a PASS record was published. Cleanup must restore
# the database/state, convert the record to ROLLED_BACK, restart and health
# check ERP, and expose validation cleanup failures instead of swallowing them.
restore_safety_backup() { printf 'auto-restore-pass\n' >>"$events"; ERP_STOPPED=1; return 0; }
restore_archived_state() { printf 'state-restored\n' >>"$events"; return 0; }
start_erp_and_wait() { printf 'erp-http-pass\n' >>"$events"; ERP_STOPPED=0; return 0; }
drop_validation_databases() { printf 'validation-cleanup-pass\n' >>"$events"; SOURCE_VALIDATION_DB=''; SAFETY_VALIDATION_DB=''; return 0; }
STATE_ARCHIVE="$test_root/late.state.archive"
printf 'archived state\n' >"$STATE_ARCHIVE"
RECORD_FILE="$test_root/late.record"
printf 'RECOVERY_STATUS=PASS\nRECOVERY_ID=late\n' >"$RECORD_FILE"
STATE_FILE_SHA=$(sha256 "$STATE_ARCHIVE")
ACTIVE_MUTATED=1
RECOVERY_COMPLETE=0
ERP_STOPPED=0
STATE_ARCHIVED=1
RECORD_PUBLISHED=1
: >"$events"
set +e
(false; cleanup) >/dev/null 2>&1
late_rc=$?
set -e
[[ $late_rc -ne 0 ]] || fail LATE_FAILURE_ACCEPTED
grep -Fxq 'RECOVERY_STATUS=ROLLED_BACK' "$RECORD_FILE" || fail LATE_PASS_RECORD_RETAINED
grep -Fxq 'state-restored' "$events" || fail LATE_STATE_NOT_RESTORED
grep -Fxq 'erp-http-pass' "$events" || fail AUTO_RESTORE_HTTP_NOT_CHECKED
grep -Fxq 'validation-cleanup-pass' "$events" || fail VALIDATION_CLEANUP_NOT_CHECKED

# Simulate EXIT in the instruction window after each mv but before its flag.
# Filesystem evidence must still drive exact state and record rollback.
RECORD_FILE="$test_root/atomic-window.record"
printf 'RECOVERY_STATUS=PASS\nRECOVERY_ID=atomic-window\n' >"$RECORD_FILE"
STATE_ARCHIVE="$test_root/atomic-window.state"
printf 'archived state\n' >"$STATE_ARCHIVE"
STATE_FILE_SHA=$(sha256 "$STATE_ARCHIVE")
ACTIVE_MUTATED=1; RECOVERY_COMPLETE=0; ERP_STOPPED=1; STATE_ARCHIVED=0; RECORD_PUBLISHED=0
SOURCE_VALIDATION_DB=''; SAFETY_VALIDATION_DB=''
restore_safety_backup() { return 0; }
restore_archived_state() { printf 'atomic-state-restored\n' >>"$events"; return 0; }
start_erp_and_wait() { ERP_STOPPED=0; return 0; }
drop_validation_databases() { return 0; }
: >"$events"
set +e
(false; cleanup) >/dev/null 2>&1
atomic_rc=$?
set -e
[[ $atomic_rc -ne 0 ]] || fail ATOMIC_WINDOW_FAILURE_ACCEPTED
grep -Fxq 'atomic-state-restored' "$events" || fail ATOMIC_STATE_WINDOW_MISSED
grep -Fxq 'RECOVERY_STATUS=ROLLED_BACK' "$RECORD_FILE" || fail ATOMIC_RECORD_WINDOW_MISSED

# If either database or state rollback fails, a published PASS record must
# become ROLLBACK_FAILED and ERP must remain unavailable for manual recovery.
for failure in database state http validation; do
  : >"$events"
  mock_running=1
  RECORD_FILE="$test_root/${failure}-rollback.record"
  printf 'RECOVERY_STATUS=PASS\nRECOVERY_ID=%s\n' "$failure" >"$RECORD_FILE"
  STATE_ARCHIVE="$test_root/${failure}-rollback.state"
  printf 'archived state\n' >"$STATE_ARCHIVE"
  STATE_FILE_SHA=$(sha256 "$STATE_ARCHIVE")
  ACTIVE_MUTATED=1; RECOVERY_COMPLETE=0; ERP_STOPPED=1; STATE_ARCHIVED=1; RECORD_PUBLISHED=1
  SOURCE_VALIDATION_DB=m062_recovery_source_1_deadbeef; SAFETY_VALIDATION_DB=m062_recovery_safety_1_deadbeef
  restore_safety_backup() { return 0; }
  restore_archived_state() { return 0; }
  start_erp_and_wait() { ERP_STOPPED=0; mock_running=1; return 0; }
  drop_validation_databases() { return 0; }
  case "$failure" in
    database) restore_safety_backup() { return 1; } ;;
    state) restore_archived_state() { return 1; } ;;
    http) start_erp_and_wait() { ERP_STOPPED=0; mock_running=1; return 1; } ;;
    validation) drop_validation_databases() { return 1; } ;;
  esac
  set +e
  (false; cleanup) >/dev/null 2>&1
  failed_rc=$?
  set -e
  [[ $failed_rc -ne 0 ]] || fail "${failure^^}_ROLLBACK_FAILURE_ACCEPTED"
  grep -Fxq 'RECOVERY_STATUS=ROLLBACK_FAILED' "$RECORD_FILE" || fail "${failure^^}_FALSE_PASS_NOT_FAILED"
  grep -Fxq 'erp-stop' "$events" || fail "${failure^^}_FAILURE_ERP_NOT_STOPPED"
done

echo 'M062_PRODUCTION_DB_RECOVERY_FAILURES=PASS QUIESCED_RESTORE=YES FINGERPRINT_MISMATCH=REJECTED STATE_RESTORE=EXACT LOCK_SYMLINK=REJECTED RECOVERY_ROOT_SYMLINK=REJECTED FALSE_PASS=ROLLED_BACK FAILED_ROLLBACK=RECORDED AUTO_RESTORE_HTTP=PASS VALIDATION_CLEANUP=VISIBLE FAILED_EVIDENCE=PRESERVED'
