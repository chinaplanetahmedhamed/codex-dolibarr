#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m060_deploy.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m060-deploy.sudoers"
readonly HELPER_SHA='ccc74ff5d722e870ef4b8349af887a891191253f7c6f55b2b06549e45b57b697'
readonly PREVIOUS_HELPER_SHA='ABSENT'
readonly SUDOERS_SHA='b6cd35caf90cc3717bd2e8cc9bd6b16b9d33595a830dce6ff956ccdfe189e4fc'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m060-deploy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m060-deploy'

private_root=''; helper_stage=''; sudoers_stage=''; previous_helper_backup=''; installed_helper=0; installed_sudoers=0; existing_helper=0; existing_sudoers=0; helper_upgrade=0; helper_replaced=0; INSTALL_COMPLETE=0
die() { echo "M060_DEPLOY_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
cleanup() {
  local rc=$? cleanup_rc restore_stage=''; cleanup_rc=$rc; trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    [[ $installed_sudoers -eq 0 ]] || rm -f "$TARGET_SUDOERS"
    if [[ $helper_replaced -eq 1 ]]; then
      restore_stage="${TARGET_HELPER}.restore.$$"
      if [[ -f "$previous_helper_backup" && ! -L "$previous_helper_backup" ]] && install -o root -g root -m 0755 "$previous_helper_backup" "$restore_stage" && [[ $(sha256 "$restore_stage") == "$PREVIOUS_HELPER_SHA" ]] && mv -f "$restore_stage" "$TARGET_HELPER"; then
        :
      else
        rm -f "$restore_stage"
        cleanup_rc=1
      fi
    else
      [[ $installed_helper -eq 0 ]] || rm -f "$TARGET_HELPER"
    fi
    [[ -z "$sudoers_stage" || ! -e "$sudoers_stage" ]] || rm -f "$sudoers_stage"
    [[ -z "$helper_stage" || ! -e "$helper_stage" ]] || rm -f "$helper_stage"
  fi
  if [[ -n "$private_root" && "$private_root" == /root/m060-deploy-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  [[ $rc -eq 0 ]] || cleanup_rc=$rc
  exit "$cleanup_rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m060-deploy-helper-install.XXXXXX)
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
  if [[ "$existing_helper_sha" == "$HELPER_SHA" ]]; then
    existing_helper=1
  elif [[ "$PREVIOUS_HELPER_SHA" != ABSENT && "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]]; then
    helper_upgrade=1
    previous_helper_backup="${private_root}/previous-helper"
    install -o root -g root -m 0600 "$TARGET_HELPER" "$previous_helper_backup"
    [[ $(sha256 "$previous_helper_backup") == "$PREVIOUS_HELPER_SHA" ]] || die 'PREVIOUS_HELPER_BACKUP_INVALID'
  else
    die 'EXISTING_HELPER_UNEXPECTED'
  fi
fi
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_UNEXPECTED'; existing_sudoers=1; fi
for root in /root/chinaplanetos-production-code-backups /root/chinaplanetos-production-db-backups /root/chinaplanetos-production-release-artifacts /var/lib/chinaplanetos-m060-deploy-production-r08 /var/lib/chinaplanetos-m060-deploy-production-r08/requests; do
  if [[ -e "$root" || -L "$root" ]]; then [[ -d "$root" && ! -L "$root" && $(stat -c '%U:%G %a' "$root") == 'root:root 700' ]] || die 'PRIVATE_ROOT_INVALID'; else install -d -o root -g root -m 0700 "$root"; fi
done

if [[ $existing_helper -eq 0 ]]; then helper_stage="${TARGET_HELPER}.stage.$$"; install -o root -g root -m 0755 "$private_helper" "$helper_stage"; [[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_MISMATCH'; fi
if [[ $existing_sudoers -eq 0 ]]; then sudoers_stage="${TARGET_SUDOERS}.stage.$$"; install -o root -g root -m 0440 "$private_sudoers" "$sudoers_stage"; [[ $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || die 'STAGED_SUDOERS_HASH_MISMATCH'; visudo -cf "$sudoers_stage" >/dev/null || die 'STAGED_SUDOERS_PARSE_FAILED'; fi

if [[ $existing_helper -eq 0 ]]; then
  mv "$helper_stage" "$TARGET_HELPER"; helper_stage=''
  if [[ $helper_upgrade -eq 1 ]]; then helper_replaced=1; else installed_helper=1; fi
fi
if [[ $existing_sudoers -eq 0 ]]; then mv "$sudoers_stage" "$TARGET_SUDOERS"; sudoers_stage=''; installed_sudoers=1; fi
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
INSTALL_COMPLETE=1
echo "M060_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=compare,preflight,apply,verify,rollback,status DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
