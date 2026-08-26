#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
if [[ -d /private/tmp ]]; then
  test_root=$(mktemp -d /private/tmp/m062-ports-helper-test.XXXXXX)
else
  test_root=$(mktemp -d /tmp/m062-ports-helper-test.XXXXXX)
fi
trap 'rm -rf "$test_root"' EXIT
for dir in inbox target code-backups db-backups state state/requests state/container-root artifacts bin; do mkdir -p "$test_root/$dir"; done
helper_source=${M062_DEPLOY_HELPER_SOURCE:-$repo_root/deploy/tools/chinaplanetos_backup_erp_m062_ports.sh}
helper_basename=${M062_DEPLOY_HELPER_TEST_BASENAME:-chinaplanetos-backup-erp-m062-ports.test}
case "$helper_source" in "$repo_root"/deploy/tools/chinaplanetos_*_m062_ports.sh) ;; *) echo 'M062_DEPLOY_FAILURES=FAIL REASON=HELPER_SOURCE_INVALID' >&2; exit 1;; esac
case "$helper_basename" in chinaplanetos-backup-erp-m062-ports.test|chinaplanetos-production-m062-ports.test) ;; *) echo 'M062_DEPLOY_FAILURES=FAIL REASON=HELPER_BASENAME_INVALID' >&2; exit 1;; esac
helper="$test_root/$helper_basename"
cp "$helper_source" "$helper"
chmod 0700 "$helper"

export M062_DEPLOY_HELPER_TEST_MODE=1
export M062_DEPLOY_HELPER_TEST_ROOT="$test_root"
export M062_DEPLOY_HELPER_LIBRARY_MODE=1
# shellcheck source=/dev/null
source "$helper"

events="$test_root/events"
marker="$test_root/concurrent-business-write"
printf 'preserve-me\n' >"$marker"

# Exercise the real file restore/reapply and containment primitives before fault stubbing.
eval "$(declare -f restore_code | sed '1s/restore_code/real_restore_code/')"
eval "$(declare -f reapply_release | sed '1s/reapply_release/real_reapply_release/')"
eval "$(declare -f load_state | sed '1s/load_state/real_load_state/')"
mkdir -p "$TARGET_ROOT/chinaplanetshippingprices/class" "$test_root/candidate/custom/chinaplanetshippingprices/class"
target_file="$TARGET_ROOT/chinaplanetshippingprices/class/test.php"
candidate_file="$test_root/candidate/custom/chinaplanetshippingprices/class/test.php"
printf 'old\n' >"$target_file"; chmod 0644 "$target_file"
CODE_BACKUP="$test_root/code-backups/real-code.tar.gz"
PRESTATE_FILE="$test_root/code-backups/real.prestate"
tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" chinaplanetshippingprices/class/test.php
printf 'chinaplanetshippingprices/class/test.php|PRESENT|%s|none|none|644\n' "$(sha256 "$target_file")" >"$PRESTATE_FILE"
chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"; CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); PRESTATE_SHA=$(sha256 "$PRESTATE_FILE")
printf 'new\n' >"$target_file"
real_restore_code
[[ $(<"$target_file") == old ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=REAL_RESTORE_CONTENT' >&2; exit 1; }

printf 'release\n' >"$candidate_file"; CANDIDATE_ROOT="$test_root/candidate"; RUNTIME_FILES=('custom/chinaplanetshippingprices/class/test.php')
REAPPLY_CODE="$test_root/code-backups/real-reapply.tar.gz"; tar -czpf "$REAPPLY_CODE" -C "$TARGET_ROOT" chinaplanetshippingprices/class/test.php
printf 'release\n' >"$target_file"; tar -czpf "$REAPPLY_CODE" -C "$TARGET_ROOT" chinaplanetshippingprices/class/test.php; chmod 0600 "$REAPPLY_CODE"; REAPPLY_CODE_SHA=$(sha256 "$REAPPLY_CODE"); printf 'old-again\n' >"$target_file"
real_reapply_release
[[ $(<"$target_file") == release ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=REAL_REAPPLY_CONTENT' >&2; exit 1; }

mkdir -p "$test_root/outside" "$TARGET_ROOT/chinaplanetshippingprices_evil"
ln -s "$test_root/outside" "$TARGET_ROOT/chinaplanetshippingprices/link"
if safe_parent "$TARGET_ROOT/chinaplanetshippingprices/link" 0; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=SYMLINK_PARENT_ACCEPTED' >&2; exit 1; fi
if safe_parent "$TARGET_ROOT/chinaplanetshippingprices_evil" 0; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=PREFIX_SIBLING_ACCEPTED' >&2; exit 1; fi
rm "$TARGET_ROOT/chinaplanetshippingprices/link"

# Exercise the real no-clobber request and append-only terminal audit.
REQUEST_ID='m062-20260816T115959Z-abcdef123455'; REQUEST_ACTION=apply; REQUEST_FILE="$test_root/state/requests/real-audit.state"
begin_request; finish_request 'SYNTHETIC_TERMINAL'
grep -Fxq 'REQUEST_STATUS=SYNTHETIC_TERMINAL' "$REQUEST_FILE" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=REAL_TERMINAL_STATE' >&2; exit 1; }
grep -Fq '|m062-20260816T115959Z-abcdef123455|apply|SYNTHETIC_TERMINAL' "$AUDIT_FILE" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=REAL_TERMINAL_AUDIT' >&2; exit 1; }

REQUEST_ID='m062-20260816T120000Z-abcdef123456'; REQUEST_ACTION='apply'; REQUEST_FILE="$test_root/state/requests/test.state"
CODE_BACKUP="$test_root/code-backups/code"; CODE_BACKUP_SHA=x; PRESTATE_FILE="$test_root/code-backups/prestate"; PRESTATE_SHA=x
DB_BACKUP="$test_root/db-backups/db"; DB_BACKUP_SHA=x; SCHEMA_PRESTATE=MIGRATION_REQUIRED; REAPPLY_CODE="$test_root/code-backups/reapply"; REAPPLY_CODE_SHA=x
save_state(){ printf 'state:%s\n' "$1" >>"$events"; }
finish_request(){ printf 'finish:%s\n' "$1" >>"$events"; }
restore_code(){ printf 'restore-code\n' >>"$events"; return 0; }
disable_schema(){ printf 'disable-schema\n' >>"$events"; return 0; }
prestate_matches(){ printf 'prestate\n' >>"$events"; return 0; }
reapply_release(){ printf 'reapply\n' >>"$events"; return 0; }
apply_schema(){ printf 'apply-schema\n' >>"$events"; return 0; }
live_checks(){ printf 'live\n' >>"$events"; return 0; }

: >"$events"; REQUEST_STARTED=1; CODE_MUTATED=1; SCHEMA_MUTATED=1; export M062_TEST_SCHEMA_STATE=READY
recover_apply
expected=$'restore-code\nprestate\nstate:APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED\nfinish:APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED'
[[ $(<"$events") == "$expected" ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=APPLY_RECOVERY_ORDER' >&2; exit 1; }
[[ $(<"$marker") == preserve-me ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=CONCURRENT_MARKER_REWOUND' >&2; exit 1; }

: >"$events"; REQUEST_STARTED=1; CODE_MUTATED=1; SCHEMA_MUTATED=1
restore_code(){ printf 'restore-code-fail\n' >>"$events"; return 1; }
if recover_apply; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=APPLY_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fxq 'state:APPLY_FAILED_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=APPLY_FAILURE_STATE' >&2; exit 1; }

restore_code(){ return 0; }

# A non-transactional migration failure is safe only when the exact schema/data check is READY.
: >"$events"; REQUEST_STARTED=1; CODE_MUTATED=0; SCHEMA_MUTATED=1; export M062_TEST_SCHEMA_STATE=MIGRATION_REQUIRED
if recover_apply; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=PARTIAL_SCHEMA_ACCEPTED' >&2; exit 1; fi
grep -Fxq 'state:APPLY_FAILED_DB_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=DB_RECOVERY_STATE_MISSING' >&2; exit 1; }
grep -Fxq 'finish:APPLY_FAILED_DB_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=DB_RECOVERY_AUDIT_MISSING' >&2; exit 1; }

: >"$events"; REQUEST_STARTED=1; CODE_MUTATED=0; SCHEMA_MUTATED=1; export M062_TEST_SCHEMA_STATE=READY
recover_apply
grep -Fxq 'state:APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=READY_SCHEMA_NOT_RETAINED' >&2; exit 1; }

: >"$events"; REQUEST_STARTED=1; CODE_MUTATED=0; SCHEMA_MUTATED=1; export M062_TEST_SCHEMA_STATE=READY M062_TEST_CURRENT_DATA_STATE=FAIL
if recover_apply; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=LATE_PARTIAL_DATA_ACCEPTED' >&2; exit 1; fi
grep -Fxq 'state:APPLY_FAILED_DB_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=LATE_PARTIAL_DB_RECOVERY_STATE' >&2; exit 1; }
export M062_TEST_CURRENT_DATA_STATE=PASS

: >"$events"; REQUEST_STARTED=1; ROLLBACK_MUTATED=1
recover_rollback
expected=$'reapply\nlive\nstate:ROLLBACK_FAILED_REAPPLIED\nfinish:ROLLBACK_FAILED_REAPPLIED'
[[ $(<"$events") == "$expected" ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_REAPPLY_ORDER' >&2; exit 1; }

: >"$events"; REQUEST_STARTED=1; ROLLBACK_MUTATED=1
reapply_release(){ printf 'reapply-fail\n' >>"$events"; return 1; }
if recover_rollback; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=REAPPLY_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fxq 'state:ROLLBACK_FAILED_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_FAILURE_STATE' >&2; exit 1; }

# A terminal audit failure must fail recovery and retain mutation flags.
: >"$events"; REQUEST_STARTED=1; CODE_MUTATED=1; SCHEMA_MUTATED=1
reapply_release(){ return 0; }; restore_code(){ return 0; }; disable_schema(){ return 0; }; prestate_matches(){ return 0; }
finish_request(){ printf 'finish-fail\n' >>"$events"; return 1; }
if recover_apply; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=TERMINAL_AUDIT_FAILURE_ACCEPTED' >&2; exit 1; fi
[[ $CODE_MUTATED -eq 1 && $SCHEMA_MUTATED -eq 1 ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=MUTATION_FLAGS_CLEARED' >&2; exit 1; }

# Simulate process restart from durable pending-prepare and prove terminalization.
finish_request(){ printf 'finish:%s\n' "$1" >>"$events"; REQUEST_STARTED=0; }
: >"$events"; REQUEST_STARTED=1; STATE_STATUS=PENDING_APPLY_PREPARE; REQUEST_ACTION=apply
reconcile_pending
grep -Fxq 'state:APPLY_FAILED_NO_MUTATION' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=PENDING_PREPARE_STATE' >&2; exit 1; }
grep -Fxq 'finish:APPLY_FAILED_NO_MUTATION' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=PENDING_PREPARE_AUDIT' >&2; exit 1; }

# A crashed rollback may only terminalize as retained when the full DB audit is READY.
: >"$events"; REQUEST_STARTED=1; STATE_STATUS=PENDING_ROLLBACK; REQUEST_ACTION=rollback; export M062_TEST_SCHEMA_STATE=MIGRATION_REQUIRED
prepare_retained_candidate(){ return 0; }
prestate_matches(){ return 0; }
if reconcile_pending; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=PENDING_ROLLBACK_INCOMPLETE_DB_ACCEPTED' >&2; exit 1; fi
grep -Fxq 'state:ROLLBACK_FAILED_DB_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=PENDING_ROLLBACK_DB_RECOVERY_STATE' >&2; exit 1; }
grep -Fxq 'finish:ROLLBACK_FAILED_DB_RECOVERY_REQUIRED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=PENDING_ROLLBACK_DB_RECOVERY_AUDIT' >&2; exit 1; }
export M062_TEST_SCHEMA_STATE=READY

# A stale root-only candidate must be reachable and removable through the restart gate.
mkdir "$test_root/state/container-root/m062-ports-candidate.stale"
check_environment 1
[[ -n "$STALE_CANDIDATES" ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=STALE_NOT_DETECTED' >&2; exit 1; }
clear_stale_candidates
[[ ! -e "$test_root/state/container-root/m062-ports-candidate.stale" ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=STALE_NOT_CLEARED' >&2; exit 1; }

# Simulate a crash after final state save but before terminal request audit.
: >"$events"; REQUEST_STARTED=1; REQUEST_ACTION=apply; STATE_STATUS=DEPLOYED
finalize_terminal_request_from_state
grep -Fxq 'finish:APPLIED_RECOVERED_TERMINAL_AUDIT' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=FINAL_STATE_AUDIT_RECONCILE' >&2; exit 1; }

: >"$events"; REQUEST_STARTED=1; REQUEST_ACTION=rollback; STATE_STATUS=DEPLOYED
finalize_terminal_request_from_state
grep -Fxq 'finish:ROLLBACK_FAILED_NO_MUTATION_RECOVERED_TERMINAL_AUDIT' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_FINAL_STATE_AUDIT_RECONCILE' >&2; exit 1; }

# Rollback no-mutation keeps the deployed release state while terminalizing the failed request.
: >"$events"; REQUEST_STARTED=1; REQUEST_ACTION=rollback; STATE_STATUS=PENDING_ROLLBACK_PREPARE
finish_pending_no_mutation
grep -Fxq 'state:DEPLOYED' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_NO_MUTATION_STATE' >&2; exit 1; }
grep -Fxq 'finish:ROLLBACK_FAILED_NO_MUTATION' "$events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_NO_MUTATION_AUDIT' >&2; exit 1; }

# Status must remain byte-for-byte read-only for pending and final states with pending audit.
for status_case in PENDING_APPLY_SCHEMA PENDING_APPLY_CODE PENDING_ROLLBACK DEPLOYED; do
  case "$status_case" in PENDING_APPLY_SCHEMA) action_case=apply; request_case='m062-20260816T120101Z-abcdef123451' ;; PENDING_APPLY_CODE) action_case=apply; request_case='m062-20260816T120104Z-abcdef123454' ;; PENDING_ROLLBACK) action_case=rollback; request_case='m062-20260816T120102Z-abcdef123452' ;; DEPLOYED) action_case=rollback; request_case='m062-20260816T120103Z-abcdef123453' ;; esac
  request_file_case="$REQUEST_ROOT/${request_case}.state"
  printf 'REQUEST_ID=%s\nREQUEST_ACTION=%s\nREQUEST_STATUS=PENDING\n' "$request_case" "$action_case" >"$request_file_case"; chmod 0600 "$request_file_case"
  printf 'STATE_RELEASE=%s\nSTATE_STATUS=%s\nSTATE_RUNTIME_COMMIT=%s\nSTATE_ARTIFACT_SHA=%s\nSTATE_RETAINED_ARTIFACT=%s\nSTATE_CODE_BACKUP=%s\nSTATE_CODE_BACKUP_SHA=%s\nSTATE_PRESTATE_FILE=%s\nSTATE_PRESTATE_SHA=%s\nSTATE_DB_BACKUP=%s\nSTATE_DB_BACKUP_SHA=%s\nSTATE_SCHEMA_PRESTATE=MIGRATION_REQUIRED\nSTATE_REAPPLY_CODE=%s\nSTATE_REAPPLY_CODE_SHA=%s\nSTATE_REQUEST_ID=%s\nSTATE_UPDATED_AT=2026-08-16T12:00:00Z\n' "$RELEASE" "$status_case" "$RUNTIME_COMMIT" "$ARTIFACT_SHA" "$RETAINED_ARTIFACT" "$CODE_BACKUP" "$CODE_BACKUP_SHA" "$PRESTATE_FILE" "$PRESTATE_SHA" "$DB_BACKUP" "$DB_BACKUP_SHA" "$REAPPLY_CODE" "$REAPPLY_CODE_SHA" "$request_case" >"$STATE_FILE"; chmod 0600 "$STATE_FILE"
  before_status=$(sha256 "$STATE_FILE"); before_request=$(sha256 "$request_file_case"); before_audit=$(sha256 "$AUDIT_FILE")
  command_status >/dev/null
  [[ $(sha256 "$STATE_FILE") == "$before_status" && $(sha256 "$request_file_case") == "$before_request" && $(sha256 "$AUDIT_FILE") == "$before_audit" && $REQUEST_STARTED -eq 0 ]] || { echo "M062_DEPLOY_FAILURES=FAIL REASON=STATUS_MUTATED_${status_case}" >&2; exit 1; }
done

# Exercise the command-level initial apply -> rollback -> retained-schema reapply path.
rm -f "$STATE_FILE"; : >"$events"; TEST_SCHEMA=MIGRATION_REQUIRED; request_sequence=0
check_environment(){ return 0; }
clear_stale_candidates(){ return 0; }
prepare_inbox_candidate(){ printf 'prepare-inbox\n' >>"$events"; }
prepare_retained_candidate(){ printf 'prepare-retained\n' >>"$events"; }
schema_prestate(){ SCHEMA_PRESTATE=$TEST_SCHEMA; printf 'schema-prestate:%s\n' "$SCHEMA_PRESTATE" >>"$events"; }
schema_ready(){ SCHEMA_PRESTATE=$TEST_SCHEMA; printf 'schema-ready:%s\n' "$SCHEMA_PRESTATE" >>"$events"; [[ "$SCHEMA_PRESTATE" == READY ]]; }
data_preflight(){ printf 'data-preflight\n' >>"$events"; }
compare_files(){ DRIFT_COUNT=0; printf 'compare\n' >>"$events"; }
new_request_id(){ request_sequence=$((request_sequence+1)); REQUEST_ID="m062-20260816T13000${request_sequence}Z-abcdef12345${request_sequence}"; REQUEST_FILE="$REQUEST_ROOT/${REQUEST_ID}.state"; }
begin_request(){ REQUEST_STARTED=1; printf 'begin\n' >>"$events"; }
finish_request(){ printf 'finish:%s\n' "$1" >>"$events"; REQUEST_STARTED=0; }
retain_artifact(){ printf 'retain\n' >>"$events"; }
save_state(){ STATE_STATUS=$1; : >"$STATE_FILE"; chmod 0600 "$STATE_FILE"; printf 'state:%s\n' "$1" >>"$events"; }
load_state(){ printf 'load-state:%s\n' "$STATE_STATUS" >>"$events"; }
finalize_terminal_request_from_state(){ return 0; }
create_code_backup(){ CODE_BACKUP="$BACKUP_ROOT/code-$request_sequence"; CODE_BACKUP_SHA=codehash; PRESTATE_FILE="$BACKUP_ROOT/prestate-$request_sequence"; PRESTATE_SHA=prestatehash; printf 'code-backup\n' >>"$events"; }
create_database_backup(){ DB_BACKUP="$DB_BACKUP_ROOT/db-$request_sequence"; DB_BACKUP_SHA=dbhash; printf 'db-backup\n' >>"$events"; }
validate_database_backup(){ printf 'db-restore-check\n' >>"$events"; }
apply_schema(){ printf 'apply-schema\n' >>"$events"; TEST_SCHEMA=READY; }
apply_code(){ printf 'apply-code\n' >>"$events"; }
live_checks(){ printf 'live\n' >>"$events"; return 0; }
create_reapply_backup(){ REAPPLY_CODE="$BACKUP_ROOT/reapply-$request_sequence"; REAPPLY_CODE_SHA=reapplyhash; printf 'reapply-backup\n' >>"$events"; }
disable_schema(){ printf 'retain-schema\n' >>"$events"; }
restore_code(){ printf 'restore-code\n' >>"$events"; }
prestate_matches(){ printf 'prestate\n' >>"$events"; }
curl(){ printf '200'; }
flock(){ return 0; }

command_apply >/dev/null
[[ "$STATE_STATUS" == DEPLOYED ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=INITIAL_COMMAND_APPLY_STATE' >&2; exit 1; }
schema_line=$(grep -n '^apply-schema$' "$events" | tail -1 | cut -d: -f1); code_line=$(grep -n '^apply-code$' "$events" | tail -1 | cut -d: -f1)
[[ -n "$schema_line" && -n "$code_line" && "$schema_line" -lt "$code_line" ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=SCHEMA_NOT_BEFORE_CODE' >&2; exit 1; }

command_rollback >/dev/null
[[ "$STATE_STATUS" == ROLLED_BACK_SCHEMA_RETAINED ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=COMMAND_ROLLBACK_STATE' >&2; exit 1; }
printf 'reapply-marker\n' >>"$events"
command_apply >/dev/null
[[ "$STATE_STATUS" == DEPLOYED ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_REAPPLY_STATE' >&2; exit 1; }
reapply_events=$(sed -n '/^reapply-marker$/,$p' "$events")
grep -Fxq 'schema-prestate:READY' <<<"$reapply_events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_REAPPLY_READY_NOT_ACCEPTED' >&2; exit 1; }
grep -Fxq 'apply-code' <<<"$reapply_events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=ROLLBACK_REAPPLY_CODE_MISSING' >&2; exit 1; }
if grep -Fxq 'apply-schema' <<<"$reapply_events"; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=READY_SCHEMA_REAPPLIED' >&2; exit 1; fi

STATE_STATUS=APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED; printf 'auto-reapply-marker\n' >>"$events"
command_apply >/dev/null
auto_reapply_events=$(sed -n '/^auto-reapply-marker$/,$p' "$events")
[[ "$STATE_STATUS" == DEPLOYED ]] || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=AUTO_ROLLBACK_REAPPLY_STATE' >&2; exit 1; }
grep -Fxq 'schema-prestate:READY' <<<"$auto_reapply_events" || { echo 'M062_DEPLOY_FAILURES=FAIL REASON=AUTO_ROLLBACK_READY_NOT_ACCEPTED' >&2; exit 1; }
if grep -Fxq 'apply-schema' <<<"$auto_reapply_events"; then echo 'M062_DEPLOY_FAILURES=FAIL REASON=AUTO_ROLLBACK_SCHEMA_REAPPLIED' >&2; exit 1; fi

printf 'STATE_RELEASE=%s\nSTATE_STATUS=INVALID\nSTATE_RUNTIME_COMMIT=%s\nSTATE_ARTIFACT_SHA=%s\nSTATE_RETAINED_ARTIFACT=%s\n' "$RELEASE" "$RUNTIME_COMMIT" "$ARTIFACT_SHA" "$RETAINED_ARTIFACT" >"$STATE_FILE"
chmod 0600 "$STATE_FILE"
if (real_load_state >/dev/null 2>&1); then echo 'M062_DEPLOY_FAILURES=FAIL REASON=INVALID_STATE_ACCEPTED' >&2; exit 1; fi

echo 'M062_DEPLOY_FAILURES=PASS REAL_RESTORE=PASS REAL_REAPPLY=PASS REAL_AUDIT=PASS STATUS=READ_ONLY PATH_ISOLATION=PASS STALE_RESTART=REACHABLE APPLY_FAULT=RECOVERED ROLLBACK_FAULT=REAPPLIED TERMINAL_AUDIT=FAIL_CLOSED FINAL_STATE_AUDIT=RECONCILED ROLLBACK_NO_MUTATION=DEPLOYED PENDING_RESTART=RECONCILED CONCURRENT_MARKER=PRESERVED INVALID_STATE=REJECTED'
