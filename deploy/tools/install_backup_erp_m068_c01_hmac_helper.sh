#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly HELPER_SHA='5b867120ff1b6aaaaa1303f133bc23125f1d200a0bbed9caad2fc14a193b826f'
readonly PREDECESSOR_HELPER_SHA='752df39e6f9f7f679feba22afa08c3a94b4ccdfe00055984d6c327c5ef677e54'
readonly CONFIG_SHA='20d82fec7a4b95c6a843bfcce4b0a27415c7215d7cb807df21a10e7dc714c2bf'
readonly SUDOERS_SHA='dc551abe7abad810f409b22abbe69c727a949893217579a4ba43f2a8d4c2c4f4'
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'

TEST_MODE=0
if [[ ${M068_C01_INSTALLER_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M068_C01_INSTALLER_TEST_ROOT:-}
  case "$test_root" in /tmp/m068-c01-hmac-installer-test.*|/private/tmp/m068-c01-hmac-installer-test.*) ;; *) echo 'M068_C01_INSTALL=FAIL REASON=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" && $(basename "$0") == 'install-backup-erp-m068-c01-hmac-helper.test' ]] || { echo 'M068_C01_INSTALL=FAIL REASON=TEST_MODE_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  SOURCE_ROOT="$test_root/inbox"
  TARGET_HELPER="$test_root/system/usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac"
  TARGET_CONFIG="$test_root/system/usr/local/libexec/chinaplanetos-m068-c01/m068_c01_portal_hmac_config.php"
  TARGET_SUDOERS="$test_root/system/etc/sudoers.d/codex-deploy-backup-erp-m068-c01-hmac"
  PRIVATE_TEMPLATE="$test_root/private.XXXXXX"
else
  SOURCE_ROOT='/home/codex-deploy/staging-inbox'
  TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac'
  TARGET_CONFIG='/usr/local/libexec/chinaplanetos-m068-c01/m068_c01_portal_hmac_config.php'
  TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m068-c01-hmac'
  PRIVATE_TEMPLATE='/root/m068-c01-hmac-helper-install.XXXXXX'
fi
readonly TEST_MODE SOURCE_ROOT TARGET_HELPER TARGET_CONFIG TARGET_SUDOERS PRIVATE_TEMPLATE
readonly SOURCE_HELPER="$SOURCE_ROOT/chinaplanetos_backup_erp_m068_c01_hmac.sh"
readonly SOURCE_CONFIG="$SOURCE_ROOT/m068_c01_portal_hmac_config.php"
readonly SOURCE_SUDOERS="$SOURCE_ROOT/codex-deploy-backup-erp-m068-c01-hmac.sudoers"

private_root=''
helper_stage=''
config_stage=''
sudoers_stage=''
old_helper=''
old_config=''
old_sudoers=''
helper_published=0
config_published=0
sudoers_published=0
INSTALL_COMPLETE=0
RECOVERY_REQUIRED=0
CONFIG_DIR_CREATED=0

die() { echo "M068_C01_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

validate_sudoers() {
  local path=$1
  if [[ $TEST_MODE -eq 1 ]]; then
    [[ $(wc -l <"$path" | tr -d ' ') -eq 5 ]] || return 1
    local command
    for command in preflight apply verify rollback status; do
      [[ $(grep -Fxc "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac $command" "$path") -eq 1 ]] || return 1
    done
  else
    visudo -cf "$path" >/dev/null
  fi
}

validate_config_syntax() {
  local path=$1 running lint_rc
  running=$(docker inspect --format '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) || return 2
  [[ "$running" == true ]] || return 2
  if docker exec -i "$TARGET_CONTAINER" php -l /dev/stdin <"$path" >/dev/null 2>&1; then return 0; else lint_rc=$?; fi
  [[ $lint_rc -eq 255 ]] && return 1
  return 2
}

restore_or_remove() {
  local published=$1 old=$2 target=$3 fault=$4
  [[ "$published" -eq 1 ]] || return 0
  if [[ -n "$old" ]]; then
    if [[ $TEST_MODE -eq 1 && ${M068_C01_INSTALLER_TEST_RECOVERY_FAULT:-} == "$fault" ]] || ! mv -f "$old" "$target"; then return 1; fi
  elif ! rm -f "$target"; then return 1; fi
}

cleanup() {
  local rc=$? cleanup_rc=$?
  trap - EXIT
  cleanup_rc=$rc
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    restore_or_remove "$sudoers_published" "$old_sudoers" "$TARGET_SUDOERS" SUDOERS || { RECOVERY_REQUIRED=1; cleanup_rc=1; }
    restore_or_remove "$config_published" "$old_config" "$TARGET_CONFIG" CONFIG || { RECOVERY_REQUIRED=1; cleanup_rc=1; }
    restore_or_remove "$helper_published" "$old_helper" "$TARGET_HELPER" HELPER || { RECOVERY_REQUIRED=1; cleanup_rc=1; }
    if [[ $CONFIG_DIR_CREATED -eq 1 && -d $(dirname "$TARGET_CONFIG") ]]; then rmdir "$(dirname "$TARGET_CONFIG")" 2>/dev/null || { RECOVERY_REQUIRED=1; cleanup_rc=1; }; fi
  fi
  for staged in "$helper_stage" "$config_stage" "$sudoers_stage"; do [[ -z "$staged" || ! -e "$staged" ]] || rm -f "$staged"; done
  if [[ $RECOVERY_REQUIRED -eq 0 ]]; then
    for old in "$old_helper" "$old_config" "$old_sudoers"; do [[ -z "$old" || ! -e "$old" ]] || rm -f "$old"; done
  else
    echo "M068_C01_INSTALL=FAIL REASON=AUTOMATIC_RESTORE_FAILED RECOVERY_REQUIRED=YES RECOVERY_ROOT=$private_root" >&2
  fi
  if [[ -n "$private_root" && $RECOVERY_REQUIRED -eq 0 ]]; then
    if [[ $TEST_MODE -eq 1 && "$private_root" == "$test_root"/private.* && -d "$private_root" ]]; then rm -rf "$private_root";
    elif [[ $TEST_MODE -eq 0 && "$private_root" == /root/m068-c01-hmac-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  fi
  [[ $rc -eq 0 ]] || cleanup_rc=$rc
  exit "$cleanup_rc"
}
trap cleanup EXIT

[[ $TEST_MODE -eq 1 || $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'
[[ -d "$SOURCE_ROOT" && ! -L "$SOURCE_ROOT" ]] || die 'SOURCE_ROOT_INVALID'
config_dir=$(dirname "$TARGET_CONFIG")
if [[ $TEST_MODE -eq 1 ]]; then
  mkdir -p "$(dirname "$TARGET_HELPER")" "$(dirname "$TARGET_SUDOERS")" "$(dirname "$config_dir")"
else
  for target_root in "$(dirname "$TARGET_HELPER")" "$(dirname "$config_dir")" "$(dirname "$TARGET_SUDOERS")"; do
    [[ -d "$target_root" && ! -L "$target_root" ]] || die 'TARGET_ROOT_INVALID'
  done
fi
if [[ -e "$config_dir" || -L "$config_dir" ]]; then
  [[ -d "$config_dir" && ! -L "$config_dir" ]] || die 'CONFIG_DIRECTORY_INVALID'
  [[ $TEST_MODE -eq 1 || $(stat -c '%U:%G %a' "$config_dir") == 'root:root 755' ]] || die 'CONFIG_DIRECTORY_METADATA_INVALID'
else
  if [[ $TEST_MODE -eq 1 ]]; then install -d -m 0755 "$config_dir"; else install -d -o root -g root -m 0755 "$config_dir"; fi
  CONFIG_DIR_CREATED=1
fi

private_root=$(mktemp -d "$PRIVATE_TEMPLATE")
private_helper="$private_root/helper"
private_config="$private_root/config.php"
private_sudoers="$private_root/sudoers"
[[ -f "$SOURCE_HELPER" && ! -L "$SOURCE_HELPER" && -f "$SOURCE_CONFIG" && ! -L "$SOURCE_CONFIG" && -f "$SOURCE_SUDOERS" && ! -L "$SOURCE_SUDOERS" ]] || die 'SOURCE_INVALID'
if [[ $TEST_MODE -eq 0 ]]; then
  [[ $(stat -c '%U:%G %a' "$SOURCE_HELPER") == 'codex-deploy:codex-deploy 644' ]] || die 'HELPER_SOURCE_METADATA_INVALID'
  [[ $(stat -c '%U:%G %a' "$SOURCE_CONFIG") == 'codex-deploy:codex-deploy 644' ]] || die 'CONFIG_SOURCE_METADATA_INVALID'
  [[ $(stat -c '%U:%G %a' "$SOURCE_SUDOERS") == 'codex-deploy:codex-deploy 644' ]] || die 'SUDOERS_SOURCE_METADATA_INVALID'
  visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'
fi

install -m 0600 "$SOURCE_HELPER" "$private_helper"
install -m 0600 "$SOURCE_CONFIG" "$private_config"
install -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'HELPER_SOURCE_HASH_MISMATCH'
[[ $(sha256 "$private_config") == "$CONFIG_SHA" ]] || die 'CONFIG_SOURCE_HASH_MISMATCH'
[[ $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SUDOERS_SOURCE_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
config_lint_rc=0
validate_config_syntax "$private_config" || config_lint_rc=$?
[[ $config_lint_rc -ne 2 ]] || die 'CONFIG_RUNTIME_INVALID'
[[ $config_lint_rc -eq 0 ]] || die 'CONFIG_SYNTAX_INVALID'
validate_sudoers "$private_sudoers" || die 'SUDOERS_SYNTAX_INVALID'

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
  existing_helper_sha=$(sha256 "$TARGET_HELPER")
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && ( "$existing_helper_sha" == "$HELPER_SHA" || "$existing_helper_sha" == "$PREDECESSOR_HELPER_SHA" ) ]] || die 'EXISTING_HELPER_UNEXPECTED'
  [[ $TEST_MODE -eq 1 || $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_METADATA_INVALID'
fi
if [[ -e "$TARGET_CONFIG" || -L "$TARGET_CONFIG" ]]; then
  [[ -f "$TARGET_CONFIG" && ! -L "$TARGET_CONFIG" && $(sha256 "$TARGET_CONFIG") == "$CONFIG_SHA" ]] || die 'EXISTING_CONFIG_UNEXPECTED'
  [[ $TEST_MODE -eq 1 || $(stat -c '%U:%G %a' "$TARGET_CONFIG") == 'root:root 644' ]] || die 'EXISTING_CONFIG_METADATA_INVALID'
fi
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_UNEXPECTED'
  [[ $TEST_MODE -eq 1 || $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_METADATA_INVALID'
fi

helper_stage="${TARGET_HELPER}.stage.$$"
config_stage="${TARGET_CONFIG}.stage.$$"
sudoers_stage="${TARGET_SUDOERS}.stage.$$"
if [[ $TEST_MODE -eq 0 ]]; then
  install -o root -g root -m 0755 "$private_helper" "$helper_stage"
  install -o root -g root -m 0644 "$private_config" "$config_stage"
  install -o root -g root -m 0440 "$private_sudoers" "$sudoers_stage"
else
  install -m 0755 "$private_helper" "$helper_stage"
  install -m 0644 "$private_config" "$config_stage"
  install -m 0440 "$private_sudoers" "$sudoers_stage"
fi
[[ $(sha256 "$helper_stage") == "$HELPER_SHA" && $(sha256 "$config_stage") == "$CONFIG_SHA" && $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || die 'STAGED_HASH_MISMATCH'
validate_sudoers "$sudoers_stage" || die 'STAGED_SUDOERS_INVALID'

if [[ -f "$TARGET_HELPER" ]]; then old_helper="$private_root/old-helper"; install -m 0755 "$TARGET_HELPER" "$old_helper"; fi
mv -f "$helper_stage" "$TARGET_HELPER"; helper_stage=''; helper_published=1
[[ $TEST_MODE -ne 1 || ${M068_C01_INSTALLER_TEST_FAULT:-} != AFTER_HELPER ]] || die 'INJECTED_AFTER_HELPER'
if [[ -f "$TARGET_CONFIG" ]]; then old_config="$private_root/old-config"; install -m 0644 "$TARGET_CONFIG" "$old_config"; fi
mv -f "$config_stage" "$TARGET_CONFIG"; config_stage=''; config_published=1
[[ $TEST_MODE -ne 1 || ${M068_C01_INSTALLER_TEST_FAULT:-} != AFTER_CONFIG ]] || die 'INJECTED_AFTER_CONFIG'
if [[ -f "$TARGET_SUDOERS" ]]; then old_sudoers="$private_root/old-sudoers"; install -m 0440 "$TARGET_SUDOERS" "$old_sudoers"; fi
mv -f "$sudoers_stage" "$TARGET_SUDOERS"; sudoers_stage=''; sudoers_published=1
[[ $TEST_MODE -ne 1 || ${M068_C01_INSTALLER_TEST_FAULT:-} != AFTER_SUDOERS ]] || die 'INJECTED_AFTER_SUDOERS'

if [[ $TEST_MODE -eq 0 ]]; then visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'; fi
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_CONFIG" && ! -L "$TARGET_CONFIG" && $(sha256 "$TARGET_CONFIG") == "$CONFIG_SHA" ]] || die 'POST_INSTALL_CONFIG_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
if [[ $TEST_MODE -eq 0 ]]; then
  [[ $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'POST_INSTALL_HELPER_METADATA_INVALID'
  [[ $(stat -c '%U:%G %a' "$TARGET_CONFIG") == 'root:root 644' ]] || die 'POST_INSTALL_CONFIG_METADATA_INVALID'
  [[ $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'POST_INSTALL_SUDOERS_METADATA_INVALID'
fi

INSTALL_COMPLETE=1
echo "M068_C01_INSTALL=PASS HELPER_SHA=$HELPER_SHA CONFIG_SHA=$CONFIG_SHA SUDOERS_SHA=$SUDOERS_SHA CONFIG_LINT=CONTAINER_PHP TARGET_CONTAINER=$TARGET_CONTAINER COMMANDS=preflight,apply,verify,rollback,status HELPER_EXECUTED=NO DATABASE_CHANGED=NO BUSINESS_RECORDS_CHANGED=NO SECRET_CREATED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
