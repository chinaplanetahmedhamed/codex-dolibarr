#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m062_ports.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m062-ports.sudoers"
readonly HELPER_SHA='5502669adf467e87468d733d3bef6ce184d28bf0349ec2ca44cc2bf81f215da1'
readonly SUDOERS_SHA='958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m062-ports'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m062-ports'

private_root=''; helper_stage=''; sudoers_stage=''; installed_helper=0; installed_sudoers=0; existing_helper=0; existing_sudoers=0; INSTALL_COMPLETE=0
die() { echo "M062_DEPLOY_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
cleanup() {
  local rc=$? cleanup_rc restore_stage=''; cleanup_rc=$rc; trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    [[ $installed_sudoers -eq 0 ]] || rm -f "$TARGET_SUDOERS"
    [[ $installed_helper -eq 0 ]] || rm -f "$TARGET_HELPER"
    [[ -z "$sudoers_stage" || ! -e "$sudoers_stage" ]] || rm -f "$sudoers_stage"
    [[ -z "$helper_stage" || ! -e "$helper_stage" ]] || rm -f "$helper_stage"
  fi
  if [[ -n "$private_root" && "$private_root" == /root/m062-ports-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  [[ $rc -eq 0 ]] || cleanup_rc=$rc
  exit "$cleanup_rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m062-ports-helper-install.XXXXXX)
private_helper="${private_root}/helper"; private_sudoers="${private_root}/sudoers"
for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do [[ -f "$source" && ! -L "$source" && $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_INVALID'; done
install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
install -o root -g root -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" && $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SOURCE_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$private_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_UNEXPECTED'
  existing_helper_sha=$(sha256 "$TARGET_HELPER")
  [[ "$existing_helper_sha" == "$HELPER_SHA" ]] || die 'EXISTING_HELPER_UNEXPECTED'
  existing_helper=1
fi
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_UNEXPECTED'; existing_sudoers=1; fi
if [[ $existing_helper -eq 0 ]]; then helper_stage="${TARGET_HELPER}.stage.$$"; install -o root -g root -m 0755 "$private_helper" "$helper_stage"; [[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_MISMATCH'; fi
if [[ $existing_sudoers -eq 0 ]]; then sudoers_stage="${TARGET_SUDOERS}.stage.$$"; install -o root -g root -m 0440 "$private_sudoers" "$sudoers_stage"; [[ $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || die 'STAGED_SUDOERS_HASH_MISMATCH'; visudo -cf "$sudoers_stage" >/dev/null || die 'STAGED_SUDOERS_PARSE_FAILED'; fi

if [[ $existing_helper -eq 0 ]]; then
  mv "$helper_stage" "$TARGET_HELPER"; helper_stage=''; installed_helper=1
fi
if [[ $existing_sudoers -eq 0 ]]; then mv "$sudoers_stage" "$TARGET_SUDOERS"; sudoers_stage=''; installed_sudoers=1; fi
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
INSTALL_COMPLETE=1
echo "M062_DEPLOY_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=compare,preflight,apply,verify,rollback,status DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
