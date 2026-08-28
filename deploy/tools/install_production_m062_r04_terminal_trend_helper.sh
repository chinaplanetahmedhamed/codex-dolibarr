#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m062_r04_terminal_trend.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m062-r04-terminal-trend.sudoers"
readonly HELPER_SHA='24648c70da56cb105aa2679269cd8e1f532c0e0f345d08826b56af1a40f21b85'
readonly SUDOERS_SHA='db06230225a8ae124ffcdb6b05c73f043859e1568863579aa9e81510a2d68abd'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m062-r04-terminal-trend'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m062-r04-terminal-trend'
readonly STATE_ROOT='/var/lib/chinaplanetos-m062-terminal-trend-production-r04'

private_root=''; helper_stage=''; sudoers_stage=''; helper_installed=0; sudoers_installed=0; state_root_created=0; INSTALL_COMPLETE=0
die() { echo "M062_R04_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    [[ $state_root_created -eq 0 ]] || rmdir "$STATE_ROOT" 2>/dev/null || true
    [[ $sudoers_installed -eq 0 ]] || rm -f "$TARGET_SUDOERS"
    [[ $helper_installed -eq 0 ]] || rm -f "$TARGET_HELPER"
  fi
  [[ -z "$sudoers_stage" || ! -e "$sudoers_stage" ]] || rm -f "$sudoers_stage"
  [[ -z "$helper_stage" || ! -e "$helper_stage" ]] || rm -f "$helper_stage"
  if [[ -n "$private_root" && "$private_root" == /root/m062-r04-production-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  exit "$rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'
for target in "$TARGET_HELPER" "$TARGET_SUDOERS" "$STATE_ROOT"; do [[ ! -e "$target" && ! -L "$target" ]] || die 'TARGET_ALREADY_EXISTS'; done

private_root=$(mktemp -d /root/m062-r04-production-helper-install.XXXXXX)
private_helper="${private_root}/helper"
private_sudoers="${private_root}/sudoers"
[[ -f "$SOURCE_HELPER" && ! -L "$SOURCE_HELPER" && $(stat -c '%U:%G %a' "$SOURCE_HELPER") == 'codex-deploy:codex-deploy 644' ]] || die 'HELPER_SOURCE_INVALID'
[[ -f "$SOURCE_SUDOERS" && ! -L "$SOURCE_SUDOERS" && $(stat -c '%U:%G %a' "$SOURCE_SUDOERS") == 'codex-deploy:codex-deploy 644' ]] || die 'SUDOERS_SOURCE_INVALID'
install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
install -o root -g root -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'HELPER_SOURCE_HASH_MISMATCH'
[[ $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SUDOERS_SOURCE_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$private_sudoers" >/dev/null || die 'SUDOERS_SOURCE_INVALID'
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'

helper_stage="${TARGET_HELPER}.stage.$$"
sudoers_stage="${TARGET_SUDOERS}.stage.$$"
install -o root -g root -m 0755 "$private_helper" "$helper_stage"
install -o root -g root -m 0440 "$private_sudoers" "$sudoers_stage"
[[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_MISMATCH'
[[ $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || die 'STAGED_SUDOERS_HASH_MISMATCH'
mv -f "$helper_stage" "$TARGET_HELPER"; helper_stage=''; helper_installed=1
mv -f "$sudoers_stage" "$TARGET_SUDOERS"; sudoers_stage=''; sudoers_installed=1
install -d -o root -g root -m 0700 "$STATE_ROOT"; state_root_created=1
visudo -cf "$TARGET_SUDOERS" >/dev/null || die 'TARGET_SUDOERS_INVALID'
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
[[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'

INSTALL_COMPLETE=1
echo "M062_R04_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=compare,preflight,apply,verify,rollback,status DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
