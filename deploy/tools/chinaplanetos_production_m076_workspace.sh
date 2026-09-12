#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M076-R07'
readonly RUNTIME_COMMIT='526976f21c609bc30482624719afb092905da810'
readonly ARTIFACT_NAME='m076-r07-workspace-526976f.tar.gz'
readonly ARTIFACT_SHA='45c526d8c8139567993df0d5702f6cd2233aae4369f8713d601834bd3969dca4'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="$INBOX/$ARTIFACT_NAME"
readonly TARGET_CONTAINER='vision_erp'
readonly TARGET_VOLUME='vision-os_dolibarr_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly TARGET_HTTP='https://erp.visiontrading.biz'
readonly BACKUP_ROOT='/root/chinaplanetos-production-m076-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m076-production-r07'
readonly STATE_FILE="$STATE_ROOT/m076.state"
readonly LOCK_FILE="$STATE_ROOT/m076.lock"
readonly RUNTIME_MANIFEST='deploy/releases/m076-runtime-files.txt'
readonly BASELINE_MANIFEST='deploy/releases/m076-production-baseline.sha256'
readonly PREFLIGHT_TOOL='deploy/tools/m076_production_preflight.php'

readonly -a PHP_RUNTIME_FILES=(
  'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
  'custom/chinaplanetoperations/class/OperationsService.class.php'
  'custom/chinaplanetoperations/operations/edit.php'
  'custom/chinaplanetoperations/operations/index.php'
  'custom/chinaplanetoperations/operations/view.php'
  'custom/chinaplanetoperations/workspace.php'
)
readonly -a RUNTIME_FILES=(
  'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
  'custom/chinaplanetoperations/class/OperationsService.class.php'
  'custom/chinaplanetoperations/css/operations.css'
  'custom/chinaplanetoperations/operations/edit.php'
  'custom/chinaplanetoperations/operations/index.php'
  'custom/chinaplanetoperations/operations/view.php'
  'custom/chinaplanetoperations/workspace.php'
)
readonly -a SUPPORT_FILES=(
  'deploy/releases/m076-runtime-files.txt'
  'deploy/releases/m076-production-baseline.sha256'
  'deploy/tests/test_m076_workspace_phase1.php'
  'deploy/tests/test_m076_operations_service_security.php'
  'deploy/tools/m076_production_preflight.php'
  'docs/MISSIONS/M076_CHINAPLANET_WORKSPACE_PHASE1.md'
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}")

CANDIDATE_ROOT=''; CANDIDATE_ARTIFACT=''; CODE_BACKUP=''; CODE_BACKUP_SHA=''; ABSENT_LIST=''; ABSENT_LIST_SHA=''
die(){ echo "M076_PRODUCTION_FAIL=$1" >&2; return 1; }
sha256(){ sha256sum "$1" | awk '{print $1}'; }

require_root(){
  [[ $(id -u) -eq 0 ]] || die ROOT_REQUIRED
  [[ $# -eq 1 ]] || die EXACTLY_ONE_COMMAND_REQUIRED
  [[ $(basename "$0") == 'chinaplanetos-production-m076-workspace' ]] || die HELPER_PATH_NOT_ALLOWED
  case "$1" in compare|preflight|apply|verify|rollback|status) ;; *) die COMMAND_NOT_ALLOWED ;; esac
}
cleanup(){
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /tmp/m076-production-candidate.* && -d "$CANDIDATE_ROOT" && ! -L "$CANDIDATE_ROOT" ]]; then rm -rf -- "$CANDIDATE_ROOT"; fi
  if [[ -n "$CANDIDATE_ARTIFACT" && "$CANDIDATE_ARTIFACT" == /tmp/m076-production-artifact.* && -f "$CANDIDATE_ARTIFACT" && ! -L "$CANDIDATE_ARTIFACT" ]]; then rm -f -- "$CANDIDATE_ARTIFACT"; fi
}
check_target_root(){
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die PRODUCTION_ROOT_INVALID
}
check_environment(){
  check_target_root
  [[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == true ]] || die PRODUCTION_CONTAINER_NOT_RUNNING
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "$TARGET_VOLUME|true" ]] || die PRODUCTION_CUSTOM_MOUNT_MISMATCH
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP/") =~ ^(200|302)$ ]] || die PRODUCTION_HTTP_UNHEALTHY
}
ensure_state_dirs(){
  local path
  for path in "$BACKUP_ROOT" "$STATE_ROOT"; do
    if [[ -e "$path" || -L "$path" ]]; then [[ -d "$path" && ! -L "$path" && $(stat -c '%U:%G %a' "$path") == 'root:root 700' ]] || die PRIVATE_ROOT_INVALID; else install -d -o root -g root -m 0700 "$path"; fi
  done
  [[ $(df -Pk "$BACKUP_ROOT" | awk 'NR==2{print $4}') -ge 524288 ]] || die FREE_SPACE_LOW
}
validate_artifact(){
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die ARTIFACT_ABSENT
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die ARTIFACT_METADATA_INVALID
  CANDIDATE_ARTIFACT=$(mktemp /tmp/m076-production-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$CANDIDATE_ARTIFACT"
  [[ $(sha256 "$CANDIDATE_ARTIFACT") == "$ARTIFACT_SHA" ]] || die ARTIFACT_HASH_MISMATCH
  local listing expected actual file
  listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$CANDIDATE_ARTIFACT" >"$listing" || die ARTIFACT_TAR_INVALID
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ {print; exit}' "$listing") ]] || die ARTIFACT_PATH_UNSAFE
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die ARTIFACT_DUPLICATE_ENTRY
  CANDIDATE_ROOT=$(mktemp -d /tmp/m076-production-candidate.XXXXXX)
  tar -xzf "$CANDIDATE_ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die ARTIFACT_SYMLINK_FORBIDDEN
  printf '%s\n' "${ALL_FILES[@]}" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die ARTIFACT_ALLOWLIST_MISMATCH
  for file in "${ALL_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die ARTIFACT_FILE_INVALID; done
  cmp -s "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" <(printf '%s\n' "${RUNTIME_FILES[@]}") || die RUNTIME_MANIFEST_MISMATCH
  [[ $(wc -l <"$CANDIDATE_ROOT/$BASELINE_MANIFEST") -eq ${#RUNTIME_FILES[@]} ]] || die BASELINE_COUNT_MISMATCH
  rm -f "$listing" "$expected" "$actual"
}
candidate_checks(){
  local file
  for file in "${PHP_RUNTIME_FILES[@]}" 'deploy/tests/test_m076_workspace_phase1.php' 'deploy/tests/test_m076_operations_service_security.php' "$PREFLIGHT_TOOL"; do docker exec -i -u 0:0 "$TARGET_CONTAINER" php -l <"$CANDIDATE_ROOT/$file" >/dev/null || die CANDIDATE_LINT_FAILED; done
  grep -Fq 'M076_WORKSPACE_PHASE1_CONTRACT=PASS' "$CANDIDATE_ROOT/deploy/tests/test_m076_workspace_phase1.php" || die WORKSPACE_CONTRACT_MARKER_MISSING
  grep -Fq 'M076_SERVICE_SECURITY=PASS' "$CANDIDATE_ROOT/deploy/tests/test_m076_operations_service_security.php" || die SECURITY_CONTRACT_MARKER_MISSING
}
expected_baseline(){ awk -v path="$1" '$2==path {print $1}' "$CANDIDATE_ROOT/$BASELINE_MANIFEST"; }
compare_files(){
  local file rel target release_hash target_hash baseline state
  for file in "${RUNTIME_FILES[@]}"; do
    rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file"); baseline=$(expected_baseline "$file")
    [[ -n "$baseline" ]] || die BASELINE_ENTRY_MISSING
    if [[ ! -e "$target" && ! -L "$target" ]]; then
      [[ "$baseline" == ABSENT ]] || die PRODUCTION_DRIFT_ABSENT
      echo "M076_PRODUCTION_COMPARE FILE=$file STATE=CREATE_REQUIRED BASELINE=ABSENT RELEASE_SHA=$release_hash"; continue
    fi
    [[ -f "$target" && ! -L "$target" ]] || die TARGET_INVALID
    target_hash=$(sha256 "$target")
    if [[ "$target_hash" == "$release_hash" ]]; then state=CURRENT; elif [[ "$target_hash" == "$baseline" ]]; then state=UPDATE_REQUIRED; else die PRODUCTION_DRIFT_HASH; fi
    echo "M076_PRODUCTION_COMPARE FILE=$file STATE=$state PRODUCTION_SHA=$target_hash RELEASE_SHA=$release_hash"
  done
}
run_prerequisites(){ docker exec -i -u 0:0 -e M076_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php <"$CANDIDATE_ROOT/$PREFLIGHT_TOOL"; }
create_backups(){
  local stamp file rel target; stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="$BACKUP_ROOT/m076-code-before-${RUNTIME_COMMIT:0:7}-$stamp.tar.gz"
  ABSENT_LIST="$BACKUP_ROOT/m076-absent-before-${RUNTIME_COMMIT:0:7}-$stamp.txt"
  : >"$ABSENT_LIST"; local -a existing=()
  for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; if [[ -f "$target" && ! -L "$target" ]]; then existing+=("$rel"); elif [[ ! -e "$target" && ! -L "$target" ]]; then printf '%s\n' "$rel" >>"$ABSENT_LIST"; else die TARGET_INVALID; fi; done
  if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
  chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}
restore_backups(){
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$ABSENT_LIST" && ! -L "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
  local file rel target expected actual
  for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; [[ ! -L "$target" ]] || return 1; rm -f -- "$target" || return 1; done
  if [[ $(tar -tzf "$CODE_BACKUP" | wc -l) -gt 0 ]]; then tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT" || return 1; fi
  for file in "${RUNTIME_FILES[@]}"; do
    rel=${file#custom/}; target="$TARGET_ROOT/$rel"
    if grep -Fxq "$rel" "$ABSENT_LIST"; then [[ ! -e "$target" && ! -L "$target" ]] || return 1; continue; fi
    [[ -f "$target" && ! -L "$target" ]] || return 1
    expected=$(tar -xOf "$CODE_BACKUP" "$rel" | sha256sum | awk '{print $1}') || return 1
    actual=$(sha256 "$target") || return 1
    [[ "$actual" == "$expected" ]] || return 1
  done
}
apply_files(){
  local file rel src target parent uid gid mode stage
  for file in "${RUNTIME_FILES[@]}"; do
    rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
    [[ -d "$parent" && ! -L "$parent" ]] || die TARGET_PARENT_INVALID
    if [[ -f "$target" && ! -L "$target" ]]; then uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target"); elif [[ ! -e "$target" && ! -L "$target" ]]; then uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode=644; else die TARGET_INVALID; fi
    stage="$target.m076.$$"; install -o "$uid" -g "$gid" -m "$mode" "$src" "$stage"; [[ $(sha256 "$stage") == $(sha256 "$src") ]] || die STAGE_HASH_MISMATCH; mv -f "$stage" "$target"
  done
}
live_checks(){
  local file rel code
  for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" && $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1; done
  for file in "${PHP_RUNTIME_FILES[@]}"; do docker exec -u 0:0 "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; done
  run_prerequisites >/dev/null || return 1
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP/custom/chinaplanetoperations/workspace.php")
  [[ "$code" =~ ^(200|302)$ ]]
}
save_state(){
  local status=$1 state_stage="$STATE_FILE.stage.$$"
  cat >"$state_stage" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=$status
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_UPDATED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$state_stage"
  sync -f "$state_stage"
  mv -f "$state_stage" "$STATE_FILE"
  sync -f "$STATE_ROOT"
}
load_state(){
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die STATE_INVALID
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die STATE_MISMATCH
  case "$STATE_STATUS" in BACKED_UP|DEPLOYED|ROLLED_BACK|APPLY_FAILED_AUTO_ROLLED_BACK|APPLY_FAILED_RECOVERY_REQUIRED) ;; *) die STATE_STATUS_INVALID ;; esac
  CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
  for path in "$CODE_BACKUP" "$ABSENT_LIST"; do [[ "$path" == "$BACKUP_ROOT"/m076-* ]] || die STATE_PATH_INVALID; done
}
command_compare(){ check_environment; validate_artifact; candidate_checks; compare_files; echo "M076_PRODUCTION_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA PRODUCTION_MUTATED=NO"; }
command_preflight(){ check_environment; validate_artifact; candidate_checks; compare_files >/dev/null; run_prerequisites; echo "M076_PRODUCTION_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} BACKUP_REQUIRED=YES PRODUCTION_MUTATED=NO"; }
command_apply(){
  check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die LOCKED; validate_artifact; candidate_checks; compare_files >/dev/null; run_prerequisites >/dev/null
  if [[ -f "$STATE_FILE" ]]; then
    load_state
    case "$STATE_STATUS" in
      DEPLOYED) die ALREADY_DEPLOYED ;;
      BACKED_UP|APPLY_FAILED_RECOVERY_REQUIRED) die ROLLBACK_REQUIRED_BEFORE_RETRY ;;
      ROLLED_BACK|APPLY_FAILED_AUTO_ROLLED_BACK) ;;
    esac
  fi
  create_backups; save_state BACKED_UP; local mutated=0
  trap 'rc=$?; if [[ $mutated -eq 1 ]]; then if restore_backups; then save_state APPLY_FAILED_AUTO_ROLLED_BACK; echo M076_PRODUCTION_AUTO_ROLLBACK=PASS >&2; else save_state APPLY_FAILED_RECOVERY_REQUIRED; echo M076_PRODUCTION_AUTO_ROLLBACK=FAIL_RECOVERY_REQUIRED >&2; fi; fi; exit $rc' ERR
  mutated=1; apply_files; live_checks; save_state DEPLOYED; mutated=0; trap - ERR
  echo "M076_PRODUCTION_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA ABSENT_LIST=$ABSENT_LIST ABSENT_LIST_SHA=$ABSENT_LIST_SHA ROLLBACK=READY"
}
command_verify(){ check_environment; validate_artifact; candidate_checks; live_checks || die LIVE_VERIFY_FAILED; load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die STATE_NOT_DEPLOYED; echo 'M076_PRODUCTION_VERIFY=PASS STATE=DEPLOYED WORKSPACE=READY'; }
command_rollback(){ check_target_root; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die LOCKED; load_state; case "$STATE_STATUS" in DEPLOYED|BACKED_UP|APPLY_FAILED_RECOVERY_REQUIRED) ;; *) die STATE_NOT_ROLLBACKABLE ;; esac; restore_backups || die ROLLBACK_RESTORE_FAILED; save_state ROLLED_BACK; echo "M076_PRODUCTION_ROLLBACK=PASS CODE_RESTORED=$CODE_BACKUP"; }
command_status(){ if [[ -f "$STATE_FILE" ]]; then load_state; grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|UPDATED_AT)=' "$STATE_FILE"; else echo M076_PRODUCTION_STATUS=NOT_DEPLOYED; fi; }

require_root "$@"
trap cleanup EXIT
case "$1" in compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;; esac
