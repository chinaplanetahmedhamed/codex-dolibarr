#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m062_ports.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m062-ports.sudoers"
readonly HELPER_SHA='964347ae88550901a10f9f72d56d89804d891ac5c818f0ba7f0172dd29a3687f'
readonly PREVIOUS_HELPER_SHA='a1a769dbdb4f4cdfb0f304a54566a98aad73710ba1a2054d8c026919a8892cc4'
readonly SUDOERS_SHA='4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m062-ports'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m062-ports'
readonly UPGRADE_BACKUP_ROOT='/root/m062-production-helper-upgrade-backups'
readonly PREVIOUS_HELPER_BACKUP="${UPGRADE_BACKUP_ROOT}/chinaplanetos-production-m062-ports.${PREVIOUS_HELPER_SHA}.bak"

private_root=''; helper_stage=''; backup_stage=''; restore_stage=''; installed_helper=0; existing_helper=0; helper_upgrade=0; INSTALL_COMPLETE=0
die() { echo "M062_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
cleanup() {
  local rc=$? cleanup_rc restore_stage=''; cleanup_rc=$rc; trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 ]]; then
    if [[ $installed_helper -eq 1 && $helper_upgrade -eq 1 ]]; then
      restore_stage="${TARGET_HELPER}.restore.$$"
      if [[ -f "$PREVIOUS_HELPER_BACKUP" && ! -L "$PREVIOUS_HELPER_BACKUP" ]] && install -o root -g root -m 0755 "$PREVIOUS_HELPER_BACKUP" "$restore_stage" && [[ $(sha256 "$restore_stage") == "$PREVIOUS_HELPER_SHA" ]] && mv -f "$restore_stage" "$TARGET_HELPER"; then
        :
      else
        rm -f "$restore_stage"
        cleanup_rc=1
      fi
    else
      [[ $installed_helper -eq 0 ]] || rm -f "$TARGET_HELPER"
    fi
    [[ -z "$restore_stage" || ! -e "$restore_stage" ]] || rm -f "$restore_stage"
    [[ -z "$backup_stage" || ! -e "$backup_stage" ]] || rm -f "$backup_stage"
    [[ -z "$helper_stage" || ! -e "$helper_stage" ]] || rm -f "$helper_stage"
  fi
  if [[ -n "$private_root" && "$private_root" == /root/m062-production-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  [[ $rc -eq 0 ]] || cleanup_rc=$rc
  exit "$cleanup_rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m062-production-helper-install.XXXXXX)
private_helper="${private_root}/helper"; private_sudoers="${private_root}/sudoers"
for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do [[ -f "$source" && ! -L "$source" && $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_INVALID'; done
install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
install -o root -g root -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" && $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SOURCE_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$private_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'

[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_REQUIRED'
existing_helper_sha=$(sha256 "$TARGET_HELPER")
if [[ "$existing_helper_sha" == "$HELPER_SHA" ]]; then existing_helper=1; elif [[ "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]]; then helper_upgrade=1; else die 'EXISTING_HELPER_UNEXPECTED'; fi
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_REQUIRED'
for root in /root/chinaplanetos-production-code-backups /root/chinaplanetos-production-db-backups /root/chinaplanetos-production-release-artifacts /var/lib/chinaplanetos-m062-ports-production-r01 /var/lib/chinaplanetos-m062-ports-production-r01/requests; do
  if [[ -e "$root" || -L "$root" ]]; then [[ -d "$root" && ! -L "$root" && $(stat -c '%U:%G %a' "$root") == 'root:root 700' ]] || die 'PRIVATE_ROOT_INVALID'; else install -d -o root -g root -m 0700 "$root"; fi
done

if [[ $helper_upgrade -eq 1 ]]; then
  if [[ -e "$UPGRADE_BACKUP_ROOT" || -L "$UPGRADE_BACKUP_ROOT" ]]; then [[ -d "$UPGRADE_BACKUP_ROOT" && ! -L "$UPGRADE_BACKUP_ROOT" && $(stat -c '%U:%G %a' "$UPGRADE_BACKUP_ROOT") == 'root:root 700' ]] || die 'UPGRADE_BACKUP_ROOT_INVALID'; else install -d -o root -g root -m 0700 "$UPGRADE_BACKUP_ROOT"; fi
  if [[ -e "$PREVIOUS_HELPER_BACKUP" || -L "$PREVIOUS_HELPER_BACKUP" ]]; then
    [[ -f "$PREVIOUS_HELPER_BACKUP" && ! -L "$PREVIOUS_HELPER_BACKUP" && $(stat -c '%U:%G %a' "$PREVIOUS_HELPER_BACKUP") == 'root:root 600' && $(sha256 "$PREVIOUS_HELPER_BACKUP") == "$PREVIOUS_HELPER_SHA" ]] || die 'PREVIOUS_HELPER_BACKUP_INVALID'
  else
    backup_stage="${PREVIOUS_HELPER_BACKUP}.stage.$$"; install -o root -g root -m 0600 "$TARGET_HELPER" "$backup_stage"; [[ $(sha256 "$backup_stage") == "$PREVIOUS_HELPER_SHA" ]] || die 'PREVIOUS_HELPER_BACKUP_HASH_MISMATCH'; mv -f "$backup_stage" "$PREVIOUS_HELPER_BACKUP"; backup_stage=''
  fi
fi
if [[ $existing_helper -eq 0 ]]; then helper_stage="${TARGET_HELPER}.stage.$$"; install -o root -g root -m 0755 "$private_helper" "$helper_stage"; [[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_MISMATCH'; fi

if [[ $existing_helper -eq 0 ]]; then
  mv "$helper_stage" "$TARGET_HELPER"; helper_stage=''
  installed_helper=1
fi
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'POST_INSTALL_HELPER_INVALID'
[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'POST_INSTALL_SUDOERS_INVALID'
INSTALL_COMPLETE=1
echo "M062_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA PREVIOUS_HELPER_SHA=$PREVIOUS_HELPER_SHA UPGRADED_PRIOR_HELPER=$helper_upgrade PREVIOUS_HELPER_BACKUP=$PREVIOUS_HELPER_BACKUP SUDOERS_SHA=$SUDOERS_SHA SUDOERS_CHANGED=NO COMMANDS=compare,preflight,apply,verify,rollback,status DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
