#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m062_ports.sh"
readonly HELPER_SHA='6cb72966c1a6e88dcd0f7071727f524a7259eae2f0b337ffabadc24cc86435f4'
readonly PREVIOUS_HELPER_SHA='03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b'
readonly SUDOERS_SHA='958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m062-ports'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m062-ports'
readonly UPGRADE_BACKUP_ROOT='/root/m062-ports-helper-upgrade-backups'
readonly PREVIOUS_HELPER_BACKUP="${UPGRADE_BACKUP_ROOT}/chinaplanetos-backup-erp-m062-ports.${PREVIOUS_HELPER_SHA}.bak"

private_root=''; helper_stage=''; backup_stage=''; restore_stage=''; installed_helper=0; existing_helper=0; upgrading_helper=0; INSTALL_COMPLETE=0
die() { echo "M062_DEPLOY_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
cleanup() {
  local rc=$? cleanup_rc; cleanup_rc=$rc; trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    if [[ $installed_helper -eq 1 ]]; then
      if [[ $upgrading_helper -eq 1 && -f "$PREVIOUS_HELPER_BACKUP" && ! -L "$PREVIOUS_HELPER_BACKUP" && $(sha256 "$PREVIOUS_HELPER_BACKUP") == "$PREVIOUS_HELPER_SHA" ]]; then
        restore_stage="${TARGET_HELPER}.restore.$$"
        if ! install -o root -g root -m 0755 "$PREVIOUS_HELPER_BACKUP" "$restore_stage" || [[ $(sha256 "$restore_stage") != "$PREVIOUS_HELPER_SHA" ]] || ! mv -f "$restore_stage" "$TARGET_HELPER"; then echo 'M062_DEPLOY_HELPER_INSTALL=FAIL REASON=AUTOMATIC_RESTORE_FAILED' >&2; cleanup_rc=1; fi
      else
        rm -f "$TARGET_HELPER"
      fi
    fi
    [[ -z "$restore_stage" || ! -e "$restore_stage" ]] || rm -f "$restore_stage"
    [[ -z "$backup_stage" || ! -e "$backup_stage" ]] || rm -f "$backup_stage"
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
private_helper="${private_root}/helper"
[[ -f "$SOURCE_HELPER" && ! -L "$SOURCE_HELPER" && $(stat -c '%U:%G %a' "$SOURCE_HELPER") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_INVALID'
install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'SOURCE_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'

[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_REQUIRED'
existing_helper_sha=$(sha256 "$TARGET_HELPER")
if [[ "$existing_helper_sha" == "$HELPER_SHA" ]]; then existing_helper=1; elif [[ "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]]; then upgrading_helper=1; else die 'EXISTING_HELPER_UNEXPECTED'; fi
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_REQUIRED'
if [[ $upgrading_helper -eq 1 ]]; then
  if [[ -e "$UPGRADE_BACKUP_ROOT" || -L "$UPGRADE_BACKUP_ROOT" ]]; then [[ -d "$UPGRADE_BACKUP_ROOT" && ! -L "$UPGRADE_BACKUP_ROOT" && $(stat -c '%U:%G %a' "$UPGRADE_BACKUP_ROOT") == 'root:root 700' ]] || die 'UPGRADE_BACKUP_ROOT_INVALID'; else install -d -o root -g root -m 0700 "$UPGRADE_BACKUP_ROOT"; fi
  if [[ -e "$PREVIOUS_HELPER_BACKUP" || -L "$PREVIOUS_HELPER_BACKUP" ]]; then [[ -f "$PREVIOUS_HELPER_BACKUP" && ! -L "$PREVIOUS_HELPER_BACKUP" && $(stat -c '%U:%G %a' "$PREVIOUS_HELPER_BACKUP") == 'root:root 600' && $(sha256 "$PREVIOUS_HELPER_BACKUP") == "$PREVIOUS_HELPER_SHA" ]] || die 'PREVIOUS_HELPER_BACKUP_INVALID'; else backup_stage="${PREVIOUS_HELPER_BACKUP}.stage.$$"; install -o root -g root -m 0600 "$TARGET_HELPER" "$backup_stage"; [[ $(sha256 "$backup_stage") == "$PREVIOUS_HELPER_SHA" ]] || die 'PREVIOUS_HELPER_BACKUP_HASH_MISMATCH'; mv "$backup_stage" "$PREVIOUS_HELPER_BACKUP"; backup_stage=''; fi
fi
if [[ $existing_helper -eq 0 ]]; then helper_stage="${TARGET_HELPER}.stage.$$"; install -o root -g root -m 0755 "$private_helper" "$helper_stage"; [[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_MISMATCH'; fi
if [[ $existing_helper -eq 0 ]]; then
  mv "$helper_stage" "$TARGET_HELPER"; helper_stage=''; installed_helper=1
fi
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
INSTALL_COMPLETE=1
echo "M062_DEPLOY_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA PREVIOUS_HELPER_SHA=$PREVIOUS_HELPER_SHA UPGRADED_PRIOR_HELPER=$upgrading_helper PREVIOUS_HELPER_BACKUP=${PREVIOUS_HELPER_BACKUP} SUDOERS_SHA=$SUDOERS_SHA SUDOERS_CHANGED=NO COMMANDS=compare,preflight,apply,verify,rollback,status DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
