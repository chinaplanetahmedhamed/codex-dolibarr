#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M058-M060-R03'
readonly RUNTIME_COMMIT='579f17e319dc7ac0255e8ea2bb6594eec7aa01d1'
readonly BASELINE_COMMIT='606b54c852230b0674b0745084e107487cf3161d'
readonly SHORT_COMMIT='579f17e'
readonly ARTIFACT_SHA='a71764050597dfd7b42ffd8b9689505b5e37e754f42186e54fc5895b9b55990c'
readonly RUNTIME_MANIFEST_SHA='827a221538c3f947cc1b4f81290f99ebd98b3c163b0c03bc66bfb4359676b05f'
readonly SUPPORT_MANIFEST_SHA='0064bd27cefa7edb4d5c51d1dc60edbb1727f0d16c4be0aacdcaeba59807dce5'
readonly BASELINE_MANIFEST_SHA='5e6be92fda17e7554c0d0d403d97abd51ec3d3a56bc49d2b0428b0cdd672e1f3'
readonly RUNTIME_MANIFEST='deploy/releases/m060-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m060-support-files.txt'
readonly BASELINE_MANIFEST='deploy/releases/m060-m056-baseline.sha256'
readonly MIGRATION='deploy/migrations/m060_internal_tasks_schema.php'
readonly ARTIFACT_NAME="m060-internal-tasks-deploy-${SHORT_COMMIT}.tar.gz"
readonly DEPLOY_CONTEXT='m060-staging-579f17e-root-only'
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly DB_VOLUME='erp-staging_erp-staging_mariadb_data'
readonly DB_NAME='dolibarr_db'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly MIN_FREE_KB=2097152

readonly -a STATIC_TESTS=(
  'deploy/tests/test_m058_asset_validation.php'
  'deploy/tests/test_m058_failure_contract.php'
  'deploy/tests/test_m058_source_contract.php'
  'deploy/tests/test_m058_ui_contract.php'
  'deploy/tests/test_m059_source_contract.php'
  'deploy/tests/test_m059_ui_contract.php'
  'deploy/tests/test_m060_source_contract.php'
  'deploy/tests/test_m060_ui_contract.php'
)

TEST_MODE=0
if [[ ${M060_DEPLOY_HELPER_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M060_DEPLOY_HELPER_TEST_ROOT:-}
  [[ $(basename "${BASH_SOURCE[0]}") == 'chinaplanetos-backup-erp-m060-deploy.test' ]] || { echo 'M060_DEPLOY_FAIL=TEST_HELPER_NAME_INVALID' >&2; exit 1; }
  case "$test_root" in /tmp/m060-deploy-helper-test.*|/private/tmp/m060-deploy-helper-test.*) ;; *) echo 'M060_DEPLOY_FAIL=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" ]] || { echo 'M060_DEPLOY_FAIL=TEST_ROOT_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  INBOX="$test_root/inbox"; TARGET_ROOT="$test_root/target"; BACKUP_ROOT="$test_root/code-backups"; DB_BACKUP_ROOT="$test_root/db-backups"; STATE_ROOT="$test_root/state"; ARTIFACT_ROOT="$test_root/artifacts"
else
  INBOX='/home/codex-deploy/staging-inbox'
  TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
  BACKUP_ROOT='/root/backup-erp-code-backups'
  DB_BACKUP_ROOT='/root/backup-erp-db-backups'
  STATE_ROOT='/var/lib/chinaplanetos-m060-deploy-staging'
  ARTIFACT_ROOT='/root/backup-erp-release-artifacts'
fi
readonly TEST_MODE INBOX TARGET_ROOT BACKUP_ROOT DB_BACKUP_ROOT STATE_ROOT ARTIFACT_ROOT
readonly ARTIFACT="${INBOX}/${ARTIFACT_NAME}"
readonly RETAINED_ARTIFACT="${ARTIFACT_ROOT}/${ARTIFACT_NAME}.${ARTIFACT_SHA}"
readonly STATE_FILE="${STATE_ROOT}/m060.state"
readonly LOCK_FILE="${STATE_ROOT}/m060.lock"
readonly REQUEST_ROOT="${STATE_ROOT}/requests"
readonly AUDIT_FILE="${STATE_ROOT}/audit.log"

RUNTIME_FILES=(); SUPPORT_FILES=()
CANDIDATE_ROOT=''; CANDIDATE_REMOTE=''; ARTIFACT_COPY=''; ARTIFACT_SOURCE="$ARTIFACT"
CODE_BACKUP=''; CODE_BACKUP_SHA=''; PRESTATE_FILE=''; PRESTATE_SHA=''
DB_BACKUP=''; DB_BACKUP_SHA=''; DB_VALIDATION_NAME=''; SCHEMA_PRESTATE=''; DRIFT_COUNT=0
REQUEST_ID=''; REQUEST_FILE=''; REQUEST_ACTION=''; REQUEST_STARTED=0; CODE_MUTATED=0; SCHEMA_MUTATED=0; ROLLBACK_MUTATED=0
REAPPLY_CODE=''; REAPPLY_CODE_SHA=''; CLEANUP_ACTIVE=0
STALE_CANDIDATES=''

die() { echo "M060_DEPLOY_FAIL=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
mode_of() { if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Lp' "$1"; else stat -c '%a' "$1"; fi; }
owner_group_of() { if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Su:%Sg' "$1"; else stat -c '%U:%G' "$1"; fi; }
fsync_path() { python3 - "$1" <<'PY'
import os, pathlib, sys
p=pathlib.Path(sys.argv[1]); flags=os.O_RDONLY | (getattr(os,'O_DIRECTORY',0) if p.is_dir() else 0); fd=os.open(str(p),flags)
try: os.fsync(fd)
finally: os.close(fd)
PY
}
durable_move() { local src=$1 dst=$2; fsync_path "$src" && mv -f "$src" "$dst" && fsync_path "$(dirname "$dst")"; }
new_request_id() { REQUEST_ID="m060-$(date -u '+%Y%m%dT%H%M%SZ')-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"; [[ $REQUEST_ID =~ ^m060-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$ ]] || die 'REQUEST_ID_INVALID'; REQUEST_FILE="${REQUEST_ROOT}/${REQUEST_ID}.state"; }
append_audit() { local event=$1; printf '%s|%s|%s|%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "${REQUEST_ID:-none}" "${REQUEST_ACTION:-none}" "$event" >>"$AUDIT_FILE"; chmod 0600 "$AUDIT_FILE"; fsync_path "$AUDIT_FILE"; }
begin_request() { [[ ! -e "$REQUEST_FILE" && ! -L "$REQUEST_FILE" ]] || die 'REQUEST_COLLISION'; (set -o noclobber; printf 'REQUEST_ID=%s\nREQUEST_ACTION=%s\nREQUEST_STATUS=PENDING\n' "$REQUEST_ID" "$REQUEST_ACTION" >"$REQUEST_FILE") 2>/dev/null || die 'REQUEST_COLLISION'; chmod 0600 "$REQUEST_FILE"; fsync_path "$REQUEST_FILE"; fsync_path "$REQUEST_ROOT"; REQUEST_STARTED=1; append_audit 'PENDING'; }
finish_request() { local status=$1 tmp="${REQUEST_FILE}.tmp.$$"; [[ $REQUEST_STARTED -eq 1 ]] || return 1; printf 'REQUEST_ID=%s\nREQUEST_ACTION=%s\nREQUEST_STATUS=%s\nREQUEST_FINISHED_AT=%s\n' "$REQUEST_ID" "$REQUEST_ACTION" "$status" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >"$tmp"; chmod 0600 "$tmp"; durable_move "$tmp" "$REQUEST_FILE" || return 1; append_audit "$status" || return 1; REQUEST_STARTED=0; }

safe_parent() {
  local parent=$1 create=${2:-0} module_root="$TARGET_ROOT/chinaplanetoperations" current part rel missing=0
  [[ "$parent" == "$module_root" || "$parent" == "$module_root/"* ]] || return 1
  [[ -d "$module_root" && ! -L "$module_root" ]] || return 1
  current="$module_root"; rel=${parent#"$module_root"}; rel=${rel#/}
  local old_ifs=$IFS; IFS='/'; for part in $rel; do IFS=$old_ifs; [[ -n "$part" && "$part" != . && "$part" != .. ]] || return 1; current="$current/$part"; if [[ ! -e "$current" && ! -L "$current" ]]; then if [[ "$create" == 1 ]]; then if [[ $TEST_MODE -eq 1 ]]; then install -d -m 0755 "$current"; else install -d -o www-data -g www-data -m 0755 "$current"; fi; elif [[ "$create" == 2 ]]; then missing=1; else return 1; fi; fi; if [[ $missing -eq 0 ]]; then [[ -d "$current" && ! -L "$current" ]] || return 1; else [[ ! -L "$current" ]] || return 1; fi; done; IFS=$old_ifs
  if [[ $missing -eq 0 ]]; then if [[ $TEST_MODE -eq 1 ]]; then [[ $(realpath "$parent") == "$parent" ]]; else [[ $(realpath -e "$parent") == "$parent" ]]; fi; fi
}
safe_target() { local target=$1 create_parent=${2:-0} module_root="$TARGET_ROOT/chinaplanetoperations"; [[ "$target" == "$module_root/"* ]] || return 1; safe_parent "$(dirname "$target")" "$create_parent" || return 1; [[ ! -L "$target" ]]; }
parent_identity() { if [[ $TEST_MODE -eq 1 ]]; then stat -f '%d:%i' "$1"; else stat -c '%d:%i' "$1"; fi; }

drop_validation_database() {
  [[ -z "$DB_VALIDATION_NAME" ]] && return 0
  [[ "$DB_VALIDATION_NAME" =~ ^m060_restore_check_[0-9]+_[a-f0-9]{8}$ ]] || return 1
  [[ $TEST_MODE -eq 1 ]] && { DB_VALIDATION_NAME=''; return 0; }
  docker exec -e M060_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c 'pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]; case "$M060_TEMP_DB" in *[!A-Za-z0-9_]*) exit 1;; esac; MYSQL_PWD="$pass" exec mariadb -u root -e "DROP DATABASE IF EXISTS \`$M060_TEMP_DB\`"' >/dev/null || return 1
  DB_VALIDATION_NAME=''
}

cleanup_paths() {
  if [[ $TEST_MODE -eq 0 && -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /root/m060-deploy-candidate.* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || return 1; fi
  if [[ -n "$CANDIDATE_ROOT" ]]; then case "$CANDIDATE_ROOT" in /root/m060-deploy-candidate.*|/tmp/m060-deploy-helper-test.*/state/candidate.*|/private/tmp/m060-deploy-helper-test.*/state/candidate.*) [[ -d "$CANDIDATE_ROOT" ]] && rm -rf "$CANDIDATE_ROOT" ;; esac; fi
  if [[ -n "$ARTIFACT_COPY" ]]; then case "$ARTIFACT_COPY" in /root/m060-deploy-artifact.*|/tmp/m060-deploy-helper-test.*/state/artifact.*|/private/tmp/m060-deploy-helper-test.*/state/artifact.*) [[ -f "$ARTIFACT_COPY" ]] && rm -f "$ARTIFACT_COPY" ;; esac; fi
}

validate_backup_file() { local file=$1 expected=$2; [[ -f "$file" && ! -L "$file" && $(mode_of "$file") == 600 && $(sha256 "$file") == "$expected" ]]; }
restore_code() {
  validate_backup_file "$CODE_BACKUP" "$CODE_BACKUP_SHA" || return 1; validate_backup_file "$PRESTATE_FILE" "$PRESTATE_SHA" || return 1
  local rel state expected owner group mode target tmp parent parent_before
  while IFS='|' read -r rel state expected owner group mode; do
    [[ "$rel" =~ ^chinaplanetoperations/ && "$rel" != *'..'* ]] || return 1; target="$TARGET_ROOT/$rel"; safe_target "$target" 0 || return 1; parent=$(dirname "$target"); parent_before=$(parent_identity "$parent") || return 1
    case "$state" in
      ABSENT) safe_target "$target" 0 || return 1; [[ $(parent_identity "$parent") == "$parent_before" ]] || return 1; rm -f "$target" || return 1; fsync_path "$(dirname "$target")" || return 1 ;;
      PRESENT) tmp=$(mktemp "$(dirname "$target")/.m060-restore.XXXXXX"); tar -xOf "$CODE_BACKUP" "$rel" >"$tmp" || return 1; [[ $(sha256 "$tmp") == "$expected" ]] || return 1; [[ $TEST_MODE -eq 1 ]] || chown "$owner:$group" "$tmp" || return 1; chmod "$mode" "$tmp" || return 1; safe_target "$target" 0 || return 1; [[ $(parent_identity "$parent") == "$parent_before" ]] || return 1; durable_move "$tmp" "$target" || return 1 ;;
      *) return 1 ;;
    esac
  done <"$PRESTATE_FILE"
}
prestate_matches() {
  validate_backup_file "$PRESTATE_FILE" "$PRESTATE_SHA" || return 1; local rel state expected owner group mode target
  while IFS='|' read -r rel state expected owner group mode; do target="$TARGET_ROOT/$rel"; safe_target "$target" 0 || return 1; case "$state" in ABSENT) [[ ! -e "$target" && ! -L "$target" ]] || return 1 ;; PRESENT) [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$expected" ]] || return 1; [[ $TEST_MODE -eq 1 || $(owner_group_of "$target") == "$owner:$group" ]] || return 1; [[ $(mode_of "$target") == "$mode" ]] || return 1 ;; *) return 1 ;; esac; done <"$PRESTATE_FILE"
}

disable_schema() { [[ $TEST_MODE -eq 1 ]] && return 0; docker exec -e M060_DOL_DOCUMENT_ROOT=/var/www/html -e M060_DEPLOY_CONTEXT="$DEPLOY_CONTEXT" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" disable >/dev/null; }
apply_schema() { [[ $TEST_MODE -eq 1 ]] && return 0; docker exec -e M060_DOL_DOCUMENT_ROOT=/var/www/html -e M060_DEPLOY_CONTEXT="$DEPLOY_CONTEXT" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" apply >/dev/null; }

reapply_release() {
  validate_backup_file "$REAPPLY_CODE" "$REAPPLY_CODE_SHA" || return 1
  local rel target tmp parent parent_before
  for rel in "${RUNTIME_FILES[@]}"; do target="$TARGET_ROOT/${rel#custom/}"; safe_target "$target" 1 || return 1; parent=$(dirname "$target"); parent_before=$(parent_identity "$parent") || return 1; tmp=$(mktemp "$parent/.m060-reapply.XXXXXX"); tar -xOf "$REAPPLY_CODE" "${rel#custom/}" >"$tmp" || return 1; [[ $(sha256 "$tmp") == $(sha256 "$CANDIDATE_ROOT/$rel") ]] || return 1; [[ $TEST_MODE -eq 1 ]] || chown www-data:www-data "$tmp" || return 1; chmod 0644 "$tmp" || return 1; safe_target "$target" 0 || return 1; [[ $(parent_identity "$parent") == "$parent_before" ]] || return 1; durable_move "$tmp" "$target" || return 1; done
}

save_state() {
  local status=$1 tmp="${STATE_FILE}.tmp.$$"
  cat >"$tmp" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=$status
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_RETAINED_ARTIFACT=$RETAINED_ARTIFACT
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_PRESTATE_FILE=$PRESTATE_FILE
STATE_PRESTATE_SHA=$PRESTATE_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_SCHEMA_PRESTATE=$SCHEMA_PRESTATE
STATE_REAPPLY_CODE=$REAPPLY_CODE
STATE_REAPPLY_CODE_SHA=$REAPPLY_CODE_SHA
STATE_REQUEST_ID=$REQUEST_ID
STATE_UPDATED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$tmp"; durable_move "$tmp" "$STATE_FILE"
}
load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(mode_of "$STATE_FILE") == 600 ]] || die 'STATE_INVALID'
  [[ $TEST_MODE -eq 1 || $(owner_group_of "$STATE_FILE") == 'root:root' ]] || die 'STATE_METADATA_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" && "$STATE_RETAINED_ARTIFACT" == "$RETAINED_ARTIFACT" ]] || die 'STATE_MISMATCH'
  case "$STATE_STATUS" in DEPLOYED|ROLLED_BACK_SCHEMA_RETAINED|APPLY_FAILED_NO_MUTATION|ROLLBACK_FAILED_NO_MUTATION|APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED|APPLY_FAILED_RECOVERY_REQUIRED|ROLLBACK_FAILED_REAPPLIED|ROLLBACK_FAILED_RECOVERY_REQUIRED|PENDING_APPLY_PREPARE|PENDING_APPLY|PENDING_ROLLBACK_PREPARE|PENDING_ROLLBACK) ;; *) die 'STATE_STATUS_INVALID' ;; esac
  CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; PRESTATE_FILE=$STATE_PRESTATE_FILE; PRESTATE_SHA=$STATE_PRESTATE_SHA; DB_BACKUP=$STATE_DB_BACKUP; DB_BACKUP_SHA=$STATE_DB_BACKUP_SHA; SCHEMA_PRESTATE=$STATE_SCHEMA_PRESTATE; REAPPLY_CODE=$STATE_REAPPLY_CODE; REAPPLY_CODE_SHA=$STATE_REAPPLY_CODE_SHA
  REQUEST_ID=$STATE_REQUEST_ID
  if [[ -n "$REQUEST_ID" ]]; then
    [[ "$REQUEST_ID" =~ ^m060-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$ ]] || die 'STATE_REQUEST_INVALID'; REQUEST_FILE="${REQUEST_ROOT}/${REQUEST_ID}.state"
    [[ -f "$REQUEST_FILE" && ! -L "$REQUEST_FILE" && $(mode_of "$REQUEST_FILE") == 600 ]] || die 'REQUEST_STATE_MISSING'; [[ $TEST_MODE -eq 1 || $(owner_group_of "$REQUEST_FILE") == root:root ]] || die 'REQUEST_STATE_METADATA_INVALID'
    if grep -Fxq 'REQUEST_STATUS=PENDING' "$REQUEST_FILE"; then REQUEST_STARTED=1; REQUEST_ACTION=$(sed -n 's/^REQUEST_ACTION=//p' "$REQUEST_FILE"); case "$REQUEST_ACTION" in apply|rollback) ;; *) die 'REQUEST_ACTION_INVALID' ;; esac; fi
  elif [[ "$STATE_STATUS" == PENDING_* ]]; then die 'STATE_REQUEST_INVALID'; fi
}

finalize_terminal_request_from_state() {
  [[ $REQUEST_STARTED -eq 1 ]] || return 0
  local terminal
  case "$STATE_STATUS" in
    DEPLOYED) if [[ "$REQUEST_ACTION" == apply ]]; then terminal='APPLIED_RECOVERED_TERMINAL_AUDIT'; else terminal='ROLLBACK_FAILED_NO_MUTATION_RECOVERED_TERMINAL_AUDIT'; fi ;;
    ROLLED_BACK_SCHEMA_RETAINED) terminal='ROLLED_BACK_SCHEMA_RETAINED_RECOVERED_TERMINAL_AUDIT' ;;
    APPLY_FAILED_NO_MUTATION|APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED|APPLY_FAILED_RECOVERY_REQUIRED|ROLLBACK_FAILED_REAPPLIED|ROLLBACK_FAILED_RECOVERY_REQUIRED) terminal="${STATE_STATUS}_RECOVERED_TERMINAL_AUDIT" ;;
    PENDING_*) return 0 ;;
    *) return 1 ;;
  esac
  finish_request "$terminal"
}

recover_apply() {
  local code_ok=1 schema_ok=1 prior_ok=1
  [[ $CODE_MUTATED -eq 0 ]] || restore_code || code_ok=0
  [[ $SCHEMA_MUTATED -eq 0 ]] || disable_schema || schema_ok=0
  [[ $CODE_MUTATED -eq 0 ]] || prestate_matches || prior_ok=0
  if [[ $code_ok -eq 1 && $schema_ok -eq 1 && $prior_ok -eq 1 ]]; then save_state 'APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED' || return 1; [[ $REQUEST_STARTED -eq 0 ]] || finish_request 'APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED' || return 1; CODE_MUTATED=0; SCHEMA_MUTATED=0; echo 'M060_DEPLOY_AUTO_ROLLBACK=PASS SCHEMA_RETAINED=YES DATA_RETAINED=YES' >&2; return 0; fi
  save_state 'APPLY_FAILED_RECOVERY_REQUIRED' || return 1; [[ $REQUEST_STARTED -eq 0 ]] || finish_request 'APPLY_FAILED_RECOVERY_REQUIRED' || return 1; echo 'M060_DEPLOY_AUTO_ROLLBACK=FAIL RECOVERY_REQUIRED=YES' >&2; return 1
}
recover_rollback() {
  if reapply_release && apply_schema && live_checks; then save_state 'ROLLBACK_FAILED_REAPPLIED' || return 1; [[ $REQUEST_STARTED -eq 0 ]] || finish_request 'ROLLBACK_FAILED_REAPPLIED' || return 1; ROLLBACK_MUTATED=0; echo 'M060_DEPLOY_ROLLBACK_REAPPLY=PASS' >&2; return 0; fi
  save_state 'ROLLBACK_FAILED_RECOVERY_REQUIRED' || return 1; [[ $REQUEST_STARTED -eq 0 ]] || finish_request 'ROLLBACK_FAILED_RECOVERY_REQUIRED' || return 1; echo 'M060_DEPLOY_ROLLBACK_REAPPLY=FAIL RECOVERY_REQUIRED=YES' >&2; return 1
}
finish_pending_no_mutation() { local status; status=$(printf '%s_FAILED_NO_MUTATION' "$REQUEST_ACTION" | tr '[:lower:]' '[:upper:]'); if [[ "$REQUEST_ACTION" == rollback ]]; then save_state DEPLOYED || return 1; else save_state "$status" || return 1; fi; finish_request "$status"; }
reconcile_pending() {
  case "$STATE_STATUS" in
    PENDING_APPLY_PREPARE) REQUEST_ACTION=apply; finish_pending_no_mutation || return 1; echo 'M060_DEPLOY_RECONCILE=PASS RESULT=APPLY_FAILED_NO_MUTATION';;
    PENDING_ROLLBACK_PREPARE) REQUEST_ACTION=rollback; finish_pending_no_mutation || return 1; echo 'M060_DEPLOY_RECONCILE=PASS RESULT=ROLLBACK_FAILED_NO_MUTATION STATE=DEPLOYED';;
    PENDING_APPLY)
      REQUEST_ACTION=apply; prepare_retained_candidate
      if live_checks; then save_state DEPLOYED || return 1; finish_request 'APPLY_RECOVERED_DEPLOYED' || return 1; echo 'M060_DEPLOY_RECONCILE=PASS RESULT=DEPLOYED'; return 0; fi
      CODE_MUTATED=1; SCHEMA_MUTATED=1; recover_apply || return 1; echo 'M060_DEPLOY_RECONCILE=PASS RESULT=AUTO_ROLLED_BACK_SCHEMA_RETAINED'
      ;;
    PENDING_ROLLBACK)
      REQUEST_ACTION=rollback; prepare_retained_candidate
      if prestate_matches; then schema_prestate; if [[ "$SCHEMA_PRESTATE" == MIGRATION_REQUIRED ]]; then save_state ROLLED_BACK_SCHEMA_RETAINED || return 1; finish_request 'ROLLBACK_RECOVERED_COMPLETED' || return 1; echo 'M060_DEPLOY_RECONCILE=PASS RESULT=ROLLED_BACK_SCHEMA_RETAINED'; return 0; fi; fi
      ROLLBACK_MUTATED=1; recover_rollback || return 1; echo 'M060_DEPLOY_RECONCILE=PASS RESULT=ROLLBACK_FAILED_REAPPLIED'
      ;;
    *) return 1 ;;
  esac
}
cleanup() {
  local rc=$? cleanup_rc=0 recovery_attempted=0; [[ $CLEANUP_ACTIVE -eq 0 ]] || exit "$rc"; CLEANUP_ACTIVE=1; trap - EXIT
  if [[ $ROLLBACK_MUTATED -eq 1 ]]; then recovery_attempted=1; recover_rollback || cleanup_rc=1; elif [[ $CODE_MUTATED -eq 1 || $SCHEMA_MUTATED -eq 1 ]]; then recovery_attempted=1; recover_apply || cleanup_rc=1; fi
  if [[ $REQUEST_STARTED -eq 1 ]]; then if [[ $recovery_attempted -eq 1 ]]; then finish_request 'FAILED_TERMINAL_AUDIT_RETRY' || cleanup_rc=1; else finish_pending_no_mutation || cleanup_rc=1; fi; fi
  drop_validation_database || cleanup_rc=1; cleanup_paths || cleanup_rc=1
  [[ $cleanup_rc -eq 0 ]] || rc=1; exit "$rc"
}
trap cleanup EXIT

require_invocation() {
  REQUEST_ACTION=${1:-unknown}
  [[ $TEST_MODE -eq 1 || $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $TEST_MODE -eq 1 || $(basename "$0") == 'chinaplanetos-backup-erp-m060-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  case "$1" in compare|preflight|apply|verify|rollback|status) ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
}
detect_stale_candidates() { if [[ $TEST_MODE -eq 1 ]]; then STALE_CANDIDATES=$(find "$STATE_ROOT/container-root" -maxdepth 1 -type d -name 'm060-deploy-candidate.*' -print 2>/dev/null || true); else STALE_CANDIDATES=$(docker exec "$TARGET_CONTAINER" find /root -maxdepth 1 -type d -name 'm060-deploy-candidate.*' -print); fi; }
clear_stale_candidates() { local stale; while IFS= read -r stale; do [[ -z "$stale" ]] && continue; if [[ $TEST_MODE -eq 1 ]]; then [[ "$stale" == "$STATE_ROOT/container-root/m060-deploy-candidate."* && -d "$stale" && ! -L "$stale" ]] || return 1; rm -rf "$stale"; else [[ "$stale" == /root/m060-deploy-candidate.* ]] || return 1; [[ $(docker exec "$TARGET_CONTAINER" stat -c '%U:%G %a' "$stale") == 'root:root 700' ]] || return 1; docker exec "$TARGET_CONTAINER" sh -eu -c 'case "$1" in /root/m060-deploy-candidate.*) [ -d "$1" ] && [ ! -L "$1" ]; rm -rf -- "$1";; *) exit 1;; esac' sh "$stale" || return 1; fi; done <<<"$STALE_CANDIDATES"; STALE_CANDIDATES=''; detect_stale_candidates; [[ -z "$STALE_CANDIDATES" ]]; }
check_environment() {
  local allow_stale=${1:-0}
  if [[ $TEST_MODE -eq 1 ]]; then for root in "$INBOX" "$TARGET_ROOT" "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT" "$ARTIFACT_ROOT" "$REQUEST_ROOT" "$STATE_ROOT/container-root"; do [[ -d "$root" && ! -L "$root" ]] || die 'TEST_ENVIRONMENT_INVALID'; done; detect_stale_candidates; [[ "$allow_stale" == 1 || -z "$STALE_CANDIDATES" ]] || die 'STALE_CANDIDATE_PRESENT'; return; fi
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" && $(owner_group_of "$TARGET_ROOT") == 'www-data:www-data' ]] || die 'TARGET_ROOT_INVALID'
  [[ $(mode_of "$TARGET_ROOT") == 755 ]] || die 'TARGET_ROOT_MODE_UNSAFE'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  detect_stale_candidates; [[ "$allow_stale" == 1 || -z "$STALE_CANDIDATES" ]] || die 'STALE_CANDIDATE_PRESENT'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER" 2>/dev/null) == 'true|erp-staging|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$DB_CONTAINER") == "${DB_VOLUME}|true" ]] || die 'DB_MOUNT_MISMATCH'
  [[ $(docker exec "$DB_CONTAINER" sh -eu -c 'printf %s "${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}"') == "$DB_NAME" ]] || die 'DB_NAME_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' && $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(owner_group_of "$INBOX") == 'codex-deploy:codex-deploy' && $(mode_of "$INBOX") == 700 ]] || die 'INBOX_METADATA_INVALID'
  for root in "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT" "$ARTIFACT_ROOT" "$REQUEST_ROOT"; do [[ -d "$root" && ! -L "$root" && $(owner_group_of "$root") == 'root:root' && $(mode_of "$root") == 700 ]] || die 'PRIVATE_ROOT_INVALID'; done
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'TARGET_DISK_LOW'
}

validate_manifest() { local manifest=$1 expected=$2 pattern=$3; [[ -f "$CANDIDATE_ROOT/$manifest" && ! -L "$CANDIDATE_ROOT/$manifest" && $(sha256 "$CANDIDATE_ROOT/$manifest") == "$expected" ]] || die 'MANIFEST_INVALID'; [[ -z $(awk 'NF==0 || /^\// || /(^|\/)\.\.($|\/)/ || $0 !~ /^[A-Za-z0-9_./-]+$/ {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_ENTRY_INVALID'; [[ -z $(LC_ALL=C sort "$CANDIDATE_ROOT/$manifest" | uniq -d) ]] || die 'MANIFEST_DUPLICATE_ENTRY'; [[ -z $(awk -v p="$pattern" '$0 !~ p {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_SCOPE_INVALID'; }
validate_artifact() {
  local expected_owner expected_mode listing expected actual stderr_file
  if [[ "$ARTIFACT_SOURCE" == "$ARTIFACT" ]]; then expected_owner='codex-deploy:codex-deploy'; expected_mode=644; else expected_owner='root:root'; expected_mode=600; fi
  [[ -f "$ARTIFACT_SOURCE" && ! -L "$ARTIFACT_SOURCE" && ( $TEST_MODE -eq 1 || $(owner_group_of "$ARTIFACT_SOURCE") == "$expected_owner" ) && $(mode_of "$ARTIFACT_SOURCE") == "$expected_mode" ]] || die 'ARTIFACT_INVALID'
  if [[ $TEST_MODE -eq 1 ]]; then ARTIFACT_COPY=$(mktemp "$STATE_ROOT/artifact.XXXXXX"); CANDIDATE_ROOT=$(mktemp -d "$STATE_ROOT/candidate.XXXXXX"); else ARTIFACT_COPY=$(mktemp /root/m060-deploy-artifact.XXXXXX); CANDIDATE_ROOT=$(mktemp -d /root/m060-deploy-candidate.XXXXXX); fi
  install -m 0600 "$ARTIFACT_SOURCE" "$ARTIFACT_COPY"; [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  listing=$(mktemp); expected=$(mktemp); actual=$(mktemp); stderr_file=$(mktemp); tar -tzf "$ARTIFACT_COPY" >"$listing" 2>"$stderr_file" || die 'ARTIFACT_TAR_INVALID'
  [[ ! -s "$stderr_file" && $(wc -l <"$listing") -eq 50 && -z $(tar -tvzf "$ARTIFACT_COPY" | awk 'substr($1,1,1)!="-" {print; exit}') && -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_STRUCTURE_INVALID'
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'; [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) && -z $(find "$CANDIDATE_ROOT" ! -type d ! -type f -print -quit) ]] || die 'ARTIFACT_EXTRACT_INVALID'
  validate_manifest "$RUNTIME_MANIFEST" "$RUNTIME_MANIFEST_SHA" '^custom/chinaplanetoperations/'; validate_manifest "$SUPPORT_MANIFEST" "$SUPPORT_MANIFEST_SHA" '^deploy/(migrations|tests)/'; [[ $(sha256 "$CANDIDATE_ROOT/$BASELINE_MANIFEST") == "$BASELINE_MANIFEST_SHA" ]] || die 'BASELINE_MANIFEST_INVALID'
  mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"; mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"; [[ ${#RUNTIME_FILES[@]} -eq 38 && ${#SUPPORT_FILES[@]} -eq 9 ]] || die 'MANIFEST_COUNT_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" "$BASELINE_MANIFEST" | LC_ALL=C sort >"$expected"; find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"; diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  local baseline path baseline_count=0; while read -r baseline path; do [[ "$baseline" == ABSENT || "$baseline" =~ ^[0-9a-f]{64}$ ]] || die 'BASELINE_ENTRY_INVALID'; [[ "$path" =~ ^custom/chinaplanetoperations/ ]] || die 'BASELINE_PATH_INVALID'; baseline_count=$((baseline_count + 1)); done <"$CANDIDATE_ROOT/$BASELINE_MANIFEST"; [[ $baseline_count -eq 38 ]] || die 'BASELINE_COUNT_INVALID'
  rm -f "$listing" "$expected" "$actual" "$stderr_file"
}
retain_artifact() { [[ "$ARTIFACT_SOURCE" == "$ARTIFACT" ]] || return 0; if [[ -e "$RETAINED_ARTIFACT" || -L "$RETAINED_ARTIFACT" ]]; then validate_backup_file "$RETAINED_ARTIFACT" "$ARTIFACT_SHA" || die 'RETAINED_ARTIFACT_INVALID'; return; fi; local temp; temp=$(mktemp "$ARTIFACT_ROOT/.m060-artifact.XXXXXX"); install -m 0600 "$ARTIFACT_COPY" "$temp"; [[ $(sha256 "$temp") == "$ARTIFACT_SHA" ]] || die 'RETAINED_ARTIFACT_HASH_INVALID'; ln "$temp" "$RETAINED_ARTIFACT" 2>/dev/null || { rm -f "$temp"; die 'RETAINED_ARTIFACT_COLLISION'; }; rm -f "$temp"; fsync_path "$RETAINED_ARTIFACT"; fsync_path "$ARTIFACT_ROOT"; }
candidate_checks() {
  local rel; if [[ $TEST_MODE -eq 1 ]]; then for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do [[ "$rel" == *.php ]] && php -l "$CANDIDATE_ROOT/$rel" >/dev/null; done; return; fi
  CANDIDATE_REMOTE=$(docker exec "$TARGET_CONTAINER" mktemp -d /root/m060-deploy-candidate.XXXXXX); [[ "$CANDIDATE_REMOTE" == /root/m060-deploy-candidate.* ]] || die 'CANDIDATE_REMOTE_INVALID'; docker exec "$TARGET_CONTAINER" chmod 0700 "$CANDIDATE_REMOTE"; docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE/"; docker exec "$TARGET_CONTAINER" chmod -R go-rwx "$CANDIDATE_REMOTE"
  for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_LINT_FAILED'; fi; done
  for rel in "${STATIC_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_TEST_FAILED'; done
}
schema_prestate() { [[ $TEST_MODE -eq 1 ]] && { SCHEMA_PRESTATE='MIGRATION_REQUIRED'; return; }; local out rc; set +e; out=$(docker exec -e M060_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check 2>&1); rc=$?; set -e; if [[ $rc -eq 0 && "$out" == 'M060_SCHEMA_CHECK=READY' ]]; then SCHEMA_PRESTATE=READY; elif [[ $rc -eq 2 && "$out" == 'M060_SCHEMA_CHECK=MIGRATION_REQUIRED' ]]; then SCHEMA_PRESTATE=MIGRATION_REQUIRED; else echo "$out" >&2; die 'SCHEMA_PRESTATE_DRIFT'; fi; }
compare_files() { local rel target release_hash target_hash baseline state; DRIFT_COUNT=0; for rel in "${RUNTIME_FILES[@]}"; do target="$TARGET_ROOT/${rel#custom/}"; release_hash=$(sha256 "$CANDIDATE_ROOT/$rel"); baseline=$(awk -v p="$rel" '$2==p {print $1}' "$CANDIDATE_ROOT/$BASELINE_MANIFEST"); [[ -n "$baseline" ]] || die 'BASELINE_PATH_MISSING'; if [[ ! -e "$target" && ! -L "$target" ]]; then if [[ "$baseline" == ABSENT ]]; then state=BASELINE_ABSENT; else state=DRIFT_ABSENT; ((DRIFT_COUNT+=1)); fi; else [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; target_hash=$(sha256 "$target"); if [[ "$target_hash" == "$release_hash" ]]; then state=CURRENT; elif [[ "$baseline" != ABSENT && "$target_hash" == "$baseline" ]]; then state=BASELINE_M056; else state=DRIFT; ((DRIFT_COUNT+=1)); fi; fi; echo "M060_DEPLOY_COMPARE FILE=$rel STATE=$state BASELINE_SHA=$baseline RELEASE_SHA=$release_hash"; done; }

create_code_backup() {
  CODE_BACKUP="${BACKUP_ROOT}/m060-before-${SHORT_COMMIT}-${REQUEST_ID}.tar.gz"; PRESTATE_FILE="${BACKUP_ROOT}/m060-before-${SHORT_COMMIT}-${REQUEST_ID}.prestate"; [[ ! -e "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && ! -e "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" ]] || die 'BACKUP_COLLISION'
  local list rel target metadata owner group; list=$(mktemp "$STATE_ROOT/code-list.XXXXXX"); : >"$PRESTATE_FILE"
  for rel in "${RUNTIME_FILES[@]}"; do rel=${rel#custom/}; target="$TARGET_ROOT/$rel"; safe_target "$target" 2 || die 'TARGET_PATH_UNSAFE'; if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; metadata=$(owner_group_of "$target"); owner=${metadata%%:*}; group=${metadata#*:}; printf '%s|PRESENT|%s|%s|%s|%s\n' "$rel" "$(sha256 "$target")" "$owner" "$group" "$(mode_of "$target")" >>"$PRESTATE_FILE"; printf '%s\n' "$rel" >>"$list"; else printf '%s|ABSENT|none|none|none|none\n' "$rel" >>"$PRESTATE_FILE"; fi; done
  tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" --files-from "$list"; rm -f "$list"; chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"; fsync_path "$CODE_BACKUP"; fsync_path "$PRESTATE_FILE"; fsync_path "$BACKUP_ROOT"; CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); PRESTATE_SHA=$(sha256 "$PRESTATE_FILE")
}
create_database_backup() { DB_BACKUP="${DB_BACKUP_ROOT}/m060-before-${SHORT_COMMIT}-${REQUEST_ID}.sql"; [[ ! -e "$DB_BACKUP" && ! -L "$DB_BACKUP" ]] || die 'DB_BACKUP_COLLISION'; if [[ $TEST_MODE -eq 1 ]]; then printf 'synthetic-db-backup\n' >"$DB_BACKUP"; else docker exec "$DB_CONTAINER" sh -eu -c 'db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}; [ "$db" = dolibarr_db ] && [ -n "$pass" ]; if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi; MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables --hex-blob --routines --triggers --events --default-character-set=utf8mb4 -u "$user" "$db"' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'; fi; [[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'; chmod 0600 "$DB_BACKUP"; fsync_path "$DB_BACKUP"; fsync_path "$DB_BACKUP_ROOT"; DB_BACKUP_SHA=$(sha256 "$DB_BACKUP"); }
database_fingerprint() { local db=$1; docker exec -e M060_FINGERPRINT_DB="$db" "$DB_CONTAINER" sh -eu -c 'pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]; case "$M060_FINGERPRINT_DB" in *[!A-Za-z0-9_]*) exit 1;; esac; q(){ MYSQL_PWD="$pass" mariadb -N -u root "$M060_FINGERPRINT_DB" -e "$1"; }; q "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY TABLE_NAME" | while read -r t; do printf "TABLE=%s\n" "$t"; q "SHOW CREATE TABLE \`$t\`"; q "SELECT COUNT(*) FROM \`$t\`"; q "CHECKSUM TABLE \`$t\`"; done' | sha256sum | awk '{print $1}'; }
validate_database_backup() { validate_backup_file "$DB_BACKUP" "$DB_BACKUP_SHA" || die 'DATABASE_BACKUP_HASH_INVALID'; [[ $TEST_MODE -eq 1 ]] && return; DB_VALIDATION_NAME="m060_restore_check_$$_$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')"; docker exec -e M060_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c 'pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]; case "$M060_TEMP_DB" in *[!A-Za-z0-9_]*) exit 1;; esac; MYSQL_PWD="$pass" mariadb -u root -e "CREATE DATABASE \`$M060_TEMP_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"' >/dev/null || die 'DATABASE_RESTORE_CHECK_CREATE_FAILED'; docker exec -i -e M060_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c 'pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]; MYSQL_PWD="$pass" exec mariadb -u root "$M060_TEMP_DB"' <"$DB_BACKUP" || die 'DATABASE_RESTORE_CHECK_IMPORT_FAILED'; [[ $(database_fingerprint "$DB_NAME") == $(database_fingerprint "$DB_VALIDATION_NAME") ]] || die 'DATABASE_RESTORE_CHECK_MISMATCH'; drop_validation_database || die 'DATABASE_RESTORE_CHECK_DROP_FAILED'; }
apply_code() { local rel target parent tmp parent_before; for rel in "${RUNTIME_FILES[@]}"; do target="$TARGET_ROOT/${rel#custom/}"; safe_target "$target" 1 || die 'TARGET_PARENT_INVALID'; parent=$(dirname "$target"); parent_before=$(parent_identity "$parent") || die 'TARGET_PARENT_IDENTITY_INVALID'; tmp=$(mktemp "$parent/.m060-apply.XXXXXX"); if [[ $TEST_MODE -eq 1 ]]; then install -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp"; else install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp"; fi; [[ $(sha256 "$tmp") == $(sha256 "$CANDIDATE_ROOT/$rel") ]] || die 'TEMP_HASH_INVALID'; safe_target "$target" 0 || die 'TARGET_PARENT_SWAPPED'; [[ $(parent_identity "$parent") == "$parent_before" ]] || die 'TARGET_PARENT_SWAPPED'; durable_move "$tmp" "$target" || die 'CODE_MOVE_FAILED'; done; }
create_reapply_backup() { REAPPLY_CODE="${BACKUP_ROOT}/m060-reapply-${SHORT_COMMIT}-${REQUEST_ID}.tar.gz"; [[ ! -e "$REAPPLY_CODE" && ! -L "$REAPPLY_CODE" ]] || die 'REAPPLY_COLLISION'; local list rel target; list=$(mktemp "$STATE_ROOT/reapply-list.XXXXXX"); for rel in "${RUNTIME_FILES[@]}"; do rel=${rel#custom/}; target="$TARGET_ROOT/$rel"; safe_target "$target" 0 || die 'REAPPLY_PATH_UNSAFE'; [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == $(sha256 "$CANDIDATE_ROOT/custom/$rel") ]] || die 'REAPPLY_SOURCE_INVALID'; printf '%s\n' "$rel" >>"$list"; done; tar -czpf "$REAPPLY_CODE" -C "$TARGET_ROOT" --files-from "$list"; rm -f "$list"; chmod 0600 "$REAPPLY_CODE"; fsync_path "$REAPPLY_CODE"; fsync_path "$BACKUP_ROOT"; REAPPLY_CODE_SHA=$(sha256 "$REAPPLY_CODE"); validate_backup_file "$REAPPLY_CODE" "$REAPPLY_CODE_SHA" || die 'REAPPLY_BACKUP_INVALID'; }
live_checks() { [[ $TEST_MODE -eq 1 ]] && return 0; local rel status body; for rel in "${RUNTIME_FILES[@]}"; do [[ -f "$TARGET_ROOT/${rel#custom/}" && ! -L "$TARGET_ROOT/${rel#custom/}" && $(sha256 "$TARGET_ROOT/${rel#custom/}") == $(sha256 "$CANDIDATE_ROOT/$rel") && $(owner_group_of "$TARGET_ROOT/${rel#custom/}") == 'www-data:www-data' && $(mode_of "$TARGET_ROOT/${rel#custom/}") == 644 ]] || return 1; done; [[ $(docker exec -e M060_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check) == 'M060_SCHEMA_CHECK=READY' ]] || return 1; [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1; body=$(mktemp); status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/custom/chinaplanetoperations/tasks/board.php") || { rm -f "$body"; return 1; }; if [[ "$status" =~ ^(302|401|403)$ ]] || { [[ "$status" == 200 ]] && grep -Fq 'name="actionlogin"' "$body"; }; then rm -f "$body"; return 0; fi; rm -f "$body"; return 1; }

prepare_inbox_candidate() { ARTIFACT_SOURCE="$ARTIFACT"; validate_artifact; candidate_checks; }
prepare_retained_candidate() { ARTIFACT_SOURCE="$RETAINED_ARTIFACT"; validate_artifact; candidate_checks; }
command_compare() { check_environment; prepare_inbox_candidate; schema_prestate; compare_files; echo "M060_DEPLOY_COMPARE=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT BASELINE_COMMIT=$BASELINE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$SCHEMA_PRESTATE DRIFT_COUNT=$DRIFT_COUNT APPLY_EXECUTED=NO PRODUCTION_ACCESSED=NO"; }
command_preflight() { check_environment; prepare_inbox_candidate; schema_prestate; compare_files; [[ $DRIFT_COUNT -eq 0 ]] || die 'BASELINE_DRIFT'; echo "M060_DEPLOY_PREFLIGHT=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$SCHEMA_PRESTATE APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment 1; clear_stale_candidates || die 'STALE_CANDIDATE_CLEANUP_FAILED'
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then load_state; if [[ "$STATE_STATUS" == PENDING_* ]]; then reconcile_pending || die 'PENDING_RECONCILE_FAILED'; return; fi; finalize_terminal_request_from_state || die 'TERMINAL_AUDIT_RECONCILE_FAILED'; [[ "$STATE_STATUS" == 'ROLLED_BACK_SCHEMA_RETAINED' || "$STATE_STATUS" == 'APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED' || "$STATE_STATUS" == 'APPLY_FAILED_NO_MUTATION' ]] || die 'STATE_NOT_APPLYABLE'; fi
  prepare_inbox_candidate; schema_prestate; compare_files; [[ $DRIFT_COUNT -eq 0 && "$SCHEMA_PRESTATE" == MIGRATION_REQUIRED ]] || die 'APPLY_PRESTATE_INVALID'
  new_request_id; begin_request; retain_artifact; save_state 'PENDING_APPLY_PREPARE'; create_code_backup; create_database_backup; validate_database_backup; save_state 'PENDING_APPLY'
  CODE_MUTATED=1; apply_code; SCHEMA_MUTATED=1; apply_schema; live_checks || die 'LIVE_CHECK_FAILED'; save_state 'DEPLOYED'; finish_request 'APPLIED'; CODE_MUTATED=0; SCHEMA_MUTATED=0
  echo "M060_DEPLOY_APPLY=PASS REQUEST_ID=$REQUEST_ID RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA DATABASE_CHANGED=YES PRODUCTION_ACCESSED=NO"
}
command_verify() { check_environment; load_state; if [[ "$STATE_STATUS" == PENDING_* ]]; then REQUEST_STARTED=0; die 'STATE_PENDING_REQUIRES_APPLY_OR_ROLLBACK'; fi; [[ "$STATE_STATUS" == DEPLOYED ]] || die 'STATE_NOT_DEPLOYED'; finalize_terminal_request_from_state || die 'TERMINAL_AUDIT_RECONCILE_FAILED'; prepare_retained_candidate; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M060_DEPLOY_VERIFY=PASS STATE=DEPLOYED RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_SCHEMA=READY DATABASE_MUTATED_BY_COMMAND=NO PRODUCTION_ACCESSED=NO"; }
command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment 1; clear_stale_candidates || die 'STALE_CANDIDATE_CLEANUP_FAILED'; load_state; if [[ "$STATE_STATUS" == PENDING_* ]]; then reconcile_pending || die 'PENDING_RECONCILE_FAILED'; return; fi; finalize_terminal_request_from_state || die 'TERMINAL_AUDIT_RECONCILE_FAILED'; [[ "$STATE_STATUS" == DEPLOYED ]] || die 'STATE_NOT_DEPLOYED'; prepare_retained_candidate; live_checks || die 'ROLLBACK_CURRENT_STATE_INVALID'
  new_request_id; begin_request; save_state 'PENDING_ROLLBACK_PREPARE'; create_reapply_backup; save_state 'PENDING_ROLLBACK'; ROLLBACK_MUTATED=1; disable_schema; restore_code; prestate_matches || die 'ROLLBACK_PRESTATE_INVALID'; [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'; save_state 'ROLLED_BACK_SCHEMA_RETAINED'; finish_request 'ROLLED_BACK_SCHEMA_RETAINED' || die 'ROLLBACK_AUDIT_FAILED'; ROLLBACK_MUTATED=0
  echo "M060_DEPLOY_ROLLBACK=PASS REQUEST_ID=$REQUEST_ID REAPPLY_CODE=$REAPPLY_CODE REAPPLY_CODE_SHA=$REAPPLY_CODE_SHA SCHEMA_RETAINED=YES DATA_RETAINED=YES DATABASE_MUTATED=MENU_DISABLE_ONLY PRODUCTION_ACCESSED=NO"
}
command_status() { if [[ -e "$STATE_FILE" || -L "$STATE_FILE" ]]; then load_state; REQUEST_STARTED=0; grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|RETAINED_ARTIFACT|CODE_BACKUP|CODE_BACKUP_SHA|PRESTATE_FILE|PRESTATE_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE|REAPPLY_CODE|REAPPLY_CODE_SHA|REQUEST_ID|UPDATED_AT)=' "$STATE_FILE"; else echo 'M060_DEPLOY_STATUS=NOT_DEPLOYED'; fi; }

if [[ ${M060_DEPLOY_HELPER_LIBRARY_MODE:-0} == 1 ]]; then [[ $TEST_MODE -eq 1 ]] || die 'LIBRARY_MODE_FORBIDDEN'; trap - EXIT; return 0 2>/dev/null || exit 0; fi
require_invocation "$@"
case "$1" in compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
