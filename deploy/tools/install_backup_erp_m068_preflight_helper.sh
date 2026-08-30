#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly HELPER_SHA='649888ffca725bcf8cc1406a0ff383dffe2688754e7e720ecd5f64cfac053c2c'
readonly SUDOERS_SHA='a9970f4e1788b3efb513bd2558170e5765dd3c8067b4c3b1b5cdaf62e520a45f'
readonly PREDECESSOR_HELPER_SHA='11b3e0e9e566be2722bc831e097ab67d04d93a6e4f708b4795494407df71940a'
readonly PREDECESSOR_SUDOERS_SHA='a9970f4e1788b3efb513bd2558170e5765dd3c8067b4c3b1b5cdaf62e520a45f'
TEST_MODE=0
if [[ ${M068_PREFLIGHT_INSTALLER_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M068_PREFLIGHT_INSTALLER_TEST_ROOT:-}
  case "$test_root" in /tmp/m068-preflight-installer-test.*|/private/tmp/m068-preflight-installer-test.*) ;; *) echo 'M068_PREFLIGHT_INSTALL=FAIL REASON=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" && $(basename "$0") == 'install-backup-erp-m068-preflight-helper.test' ]] || { echo 'M068_PREFLIGHT_INSTALL=FAIL REASON=TEST_MODE_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  SOURCE_ROOT="$test_root/inbox"
  TARGET_HELPER="$test_root/system/usr/local/sbin/chinaplanetos-backup-erp-m068-preflight"
  TARGET_SUDOERS="$test_root/system/etc/sudoers.d/codex-deploy-backup-erp-m068-preflight"
  CODE_BACKUP_ROOT="$test_root/system/root/backup-erp-code-backups"
  DB_BACKUP_ROOT="$test_root/system/root/backup-erp-db-backups"
  STATE_ROOT="$test_root/system/var/lib/chinaplanetos-m068-deploy-staging-r03"
  ARTIFACT_ROOT="$test_root/system/root/backup-erp-release-artifacts"
  PRIVATE_TEMPLATE="$test_root/private.XXXXXX"
else
  SOURCE_ROOT='/home/codex-deploy/staging-inbox'
  TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m068-preflight'
  TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m068-preflight'
  CODE_BACKUP_ROOT='/root/backup-erp-code-backups'
  DB_BACKUP_ROOT='/root/backup-erp-db-backups'
  STATE_ROOT='/var/lib/chinaplanetos-m068-deploy-staging-r03'
  ARTIFACT_ROOT='/root/backup-erp-release-artifacts'
  PRIVATE_TEMPLATE='/root/m068-preflight-helper-install.XXXXXX'
fi
readonly TEST_MODE SOURCE_ROOT TARGET_HELPER TARGET_SUDOERS CODE_BACKUP_ROOT DB_BACKUP_ROOT STATE_ROOT ARTIFACT_ROOT PRIVATE_TEMPLATE
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m068_preflight.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m068-preflight.sudoers"

private_root=''; helper_stage=''; sudoers_stage=''; old_helper=''; old_sudoers=''; helper_published=0; sudoers_published=0; INSTALL_COMPLETE=0; RECOVERY_REQUIRED=0
die() { echo "M068_PREFLIGHT_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
validate_sudoers() {
  local path=$1
  if [[ $TEST_MODE -eq 1 ]]; then
    [[ $(wc -l <"$path" | tr -d ' ') -eq 8 ]] || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight configure-mailpit' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight repair-portal-group' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight compare' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight preflight' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight apply' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight verify' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight rollback' "$path" || return 1
    grep -Fxq 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight status' "$path"
  else
    visudo -cf "$path" >/dev/null
  fi
}
cleanup() {
  local rc=$? cleanup_rc
  cleanup_rc=$rc
  trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    if [[ $sudoers_published -eq 1 ]]; then
      if [[ -n "$old_sudoers" ]]; then
        if [[ $TEST_MODE -eq 1 && ${M068_PREFLIGHT_INSTALLER_TEST_RECOVERY_FAULT:-} == SUDOERS ]] || ! mv -f "$old_sudoers" "$TARGET_SUDOERS"; then RECOVERY_REQUIRED=1; cleanup_rc=1; fi
      elif ! rm -f "$TARGET_SUDOERS"; then RECOVERY_REQUIRED=1; cleanup_rc=1; fi
    fi
    if [[ $helper_published -eq 1 ]]; then
      if [[ -n "$old_helper" ]]; then
        if [[ $TEST_MODE -eq 1 && ${M068_PREFLIGHT_INSTALLER_TEST_RECOVERY_FAULT:-} == HELPER ]] || ! mv -f "$old_helper" "$TARGET_HELPER"; then RECOVERY_REQUIRED=1; cleanup_rc=1; fi
      elif ! rm -f "$TARGET_HELPER"; then RECOVERY_REQUIRED=1; cleanup_rc=1; fi
    fi
  fi
  [[ -z "$helper_stage" || ! -e "$helper_stage" ]] || rm -f "$helper_stage"
  [[ -z "$sudoers_stage" || ! -e "$sudoers_stage" ]] || rm -f "$sudoers_stage"
  if [[ $RECOVERY_REQUIRED -eq 0 ]]; then
    [[ -z "$old_helper" || ! -e "$old_helper" ]] || rm -f "$old_helper"
    [[ -z "$old_sudoers" || ! -e "$old_sudoers" ]] || rm -f "$old_sudoers"
  else
    echo "M068_PREFLIGHT_INSTALL=FAIL REASON=AUTOMATIC_RESTORE_FAILED RECOVERY_REQUIRED=YES RECOVERY_ROOT=$private_root" >&2
  fi
  if [[ -n "$private_root" && $RECOVERY_REQUIRED -eq 0 ]]; then
    if [[ $TEST_MODE -eq 1 && "$private_root" == "$test_root"/private.* && -d "$private_root" ]]; then rm -rf "$private_root";
    elif [[ $TEST_MODE -eq 0 && "$private_root" == /root/m068-preflight-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  fi
  [[ $rc -eq 0 ]] || cleanup_rc=$rc
  exit "$cleanup_rc"
}
trap cleanup EXIT

[[ $TEST_MODE -eq 1 || $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'
[[ -d "$SOURCE_ROOT" && ! -L "$SOURCE_ROOT" ]] || die 'SOURCE_ROOT_INVALID'
if [[ $TEST_MODE -eq 1 ]]; then
  mkdir -p "$(dirname "$TARGET_HELPER")" "$(dirname "$TARGET_SUDOERS")"
else
  [[ -d $(dirname "$TARGET_HELPER") && ! -L $(dirname "$TARGET_HELPER") ]] || die 'HELPER_TARGET_ROOT_INVALID'
  [[ -d $(dirname "$TARGET_SUDOERS") && ! -L $(dirname "$TARGET_SUDOERS") ]] || die 'SUDOERS_TARGET_ROOT_INVALID'
fi
for private_dir in "$CODE_BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT" "$ARTIFACT_ROOT" "$STATE_ROOT/requests"; do
  if [[ $TEST_MODE -eq 1 ]]; then install -d -m 0700 "$private_dir"; else install -d -o root -g root -m 0700 "$private_dir"; fi
done
private_root=$(mktemp -d "$PRIVATE_TEMPLATE")
private_helper="$private_root/helper"
private_sudoers="$private_root/sudoers"
[[ -f "$SOURCE_HELPER" && ! -L "$SOURCE_HELPER" && -f "$SOURCE_SUDOERS" && ! -L "$SOURCE_SUDOERS" ]] || die 'SOURCE_INVALID'
if [[ $TEST_MODE -eq 0 ]]; then
  [[ $(stat -c '%U:%G %a' "$SOURCE_HELPER") == 'codex-deploy:codex-deploy 644' ]] || die 'HELPER_SOURCE_METADATA_INVALID'
  [[ $(stat -c '%U:%G %a' "$SOURCE_SUDOERS") == 'codex-deploy:codex-deploy 644' ]] || die 'SUDOERS_SOURCE_METADATA_INVALID'
  visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'
fi
install -m 0600 "$SOURCE_HELPER" "$private_helper"
install -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'HELPER_SOURCE_HASH_MISMATCH'
[[ $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SUDOERS_SOURCE_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
validate_sudoers "$private_sudoers" || die 'SUDOERS_SYNTAX_INVALID'

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
  existing_helper_sha=$(sha256 "$TARGET_HELPER")
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && ( "$existing_helper_sha" == "$HELPER_SHA" || "$existing_helper_sha" == "$PREDECESSOR_HELPER_SHA" ) ]] || die 'EXISTING_HELPER_UNEXPECTED'
  [[ $TEST_MODE -eq 1 || $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_METADATA_INVALID'
fi
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  existing_sudoers_sha=$(sha256 "$TARGET_SUDOERS")
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && ( "$existing_sudoers_sha" == "$SUDOERS_SHA" || "$existing_sudoers_sha" == "$PREDECESSOR_SUDOERS_SHA" ) ]] || die 'EXISTING_SUDOERS_UNEXPECTED'
  [[ $TEST_MODE -eq 1 || $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_METADATA_INVALID'
fi

helper_stage="${TARGET_HELPER}.stage.$$"
sudoers_stage="${TARGET_SUDOERS}.stage.$$"
if [[ $TEST_MODE -eq 0 ]]; then
  install -o root -g root -m 0755 "$private_helper" "$helper_stage"
  install -o root -g root -m 0440 "$private_sudoers" "$sudoers_stage"
else
  install -m 0755 "$private_helper" "$helper_stage"
  install -m 0440 "$private_sudoers" "$sudoers_stage"
fi
[[ $(sha256 "$helper_stage") == "$HELPER_SHA" && $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || die 'STAGED_HASH_MISMATCH'
validate_sudoers "$sudoers_stage" || die 'STAGED_SUDOERS_INVALID'
if [[ -f "$TARGET_HELPER" ]]; then
  old_helper="${private_root}/old-helper"
  if [[ $TEST_MODE -eq 0 ]]; then install -o root -g root -m 0755 "$TARGET_HELPER" "$old_helper"; else install -m 0755 "$TARGET_HELPER" "$old_helper"; fi
fi
mv -f "$helper_stage" "$TARGET_HELPER"; helper_stage=''; helper_published=1
if [[ $TEST_MODE -eq 1 && ${M068_PREFLIGHT_INSTALLER_TEST_FAULT:-} == AFTER_HELPER ]]; then die 'INJECTED_AFTER_HELPER'; fi
if [[ -f "$TARGET_SUDOERS" ]]; then
  old_sudoers="${private_root}/old-sudoers"
  if [[ $TEST_MODE -eq 0 ]]; then install -o root -g root -m 0440 "$TARGET_SUDOERS" "$old_sudoers"; else install -m 0440 "$TARGET_SUDOERS" "$old_sudoers"; fi
fi
mv -f "$sudoers_stage" "$TARGET_SUDOERS"; sudoers_stage=''; sudoers_published=1
if [[ $TEST_MODE -eq 1 && ${M068_PREFLIGHT_INSTALLER_TEST_FAULT:-} == AFTER_SUDOERS ]]; then die 'INJECTED_AFTER_SUDOERS'; fi
if [[ $TEST_MODE -eq 0 ]]; then visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'; fi
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
if [[ $TEST_MODE -eq 0 ]]; then
  [[ $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'POST_INSTALL_HELPER_METADATA_INVALID'
  [[ $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'POST_INSTALL_SUDOERS_METADATA_INVALID'
fi
INSTALL_COMPLETE=1
echo "M068_PREFLIGHT_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=configure-mailpit,repair-portal-group,compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE=YES HELPER_EXECUTED=NO DATABASE_CHANGED=NO RUNTIME_CHANGED=NO STATE_FILES_CREATED=NO PRODUCTION_ACCESSED=NO"
