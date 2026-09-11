#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="$SOURCE_ROOT/chinaplanetos_production_m076_workspace.sh"
readonly SOURCE_SUDOERS="$SOURCE_ROOT/codex-deploy-production-m076-workspace.sudoers"
readonly HELPER_SHA='b3f9a6eb45c4f128c7896861a170abd882276f7f5068e1d7a8d83b1e63290f4d'
readonly SUDOERS_SHA='311468a7e1ff85dff9324f35e5961d478c8b6d6bf79abfae1d301400ad2d8e03'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m076-workspace'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m076-workspace'

helper_stage=''; sudoers_stage=''; installed_helper=0; installed_sudoers=0; complete=0
die(){ echo "M076_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256(){ sha256sum "$1" | awk '{print $1}'; }
cleanup(){
  local rc=$?; trap - EXIT
  if [[ $complete -eq 0 ]]; then
    [[ $installed_sudoers -eq 0 ]] || rm -f -- "$TARGET_SUDOERS"
    [[ $installed_helper -eq 0 ]] || rm -f -- "$TARGET_HELPER"
    [[ -z "$helper_stage" || ! -e "$helper_stage" ]] || rm -f -- "$helper_stage"
    [[ -z "$sudoers_stage" || ! -e "$sudoers_stage" ]] || rm -f -- "$sudoers_stage"
  fi
  exit "$rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die ROOT_REQUIRED
[[ $# -eq 0 ]] || die NO_ARGUMENTS_ALLOWED
for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do [[ -f "$source" && ! -L "$source" && $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die SOURCE_INVALID; done
[[ $(sha256 "$SOURCE_HELPER") == "$HELPER_SHA" ]] || die HELPER_HASH_MISMATCH
[[ $(sha256 "$SOURCE_SUDOERS") == "$SUDOERS_SHA" ]] || die SUDOERS_HASH_MISMATCH
bash -n "$SOURCE_HELPER" || die HELPER_SYNTAX_INVALID
visudo -cf "$SOURCE_SUDOERS" >/dev/null || die SUDOERS_PARSE_FAILED
visudo -cf /etc/sudoers >/dev/null || die GLOBAL_SUDOERS_PRECHECK_FAILED

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die EXISTING_HELPER_UNEXPECTED
else
  helper_stage="$TARGET_HELPER.stage.$$"; install -o root -g root -m 0755 "$SOURCE_HELPER" "$helper_stage"; [[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || die STAGED_HELPER_HASH_MISMATCH; mv "$helper_stage" "$TARGET_HELPER"; helper_stage=''; installed_helper=1
fi
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die EXISTING_SUDOERS_UNEXPECTED
else
  sudoers_stage="$TARGET_SUDOERS.stage.$$"; install -o root -g root -m 0440 "$SOURCE_SUDOERS" "$sudoers_stage"; [[ $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || die STAGED_SUDOERS_HASH_MISMATCH; visudo -cf "$sudoers_stage" >/dev/null || die STAGED_SUDOERS_PARSE_FAILED; mv "$sudoers_stage" "$TARGET_SUDOERS"; sudoers_stage=''; installed_sudoers=1
fi

visudo -cf /etc/sudoers >/dev/null || die GLOBAL_SUDOERS_POSTCHECK_FAILED
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die POST_INSTALL_HELPER_INVALID
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die POST_INSTALL_SUDOERS_INVALID
complete=1
echo "M076_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=compare,preflight,apply,verify,rollback,status DEPLOY_EXECUTED=NO PRODUCTION_FILES_CHANGED=NO PRODUCTION_DATABASE_CHANGED=NO"
