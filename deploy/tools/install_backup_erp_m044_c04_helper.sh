#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m044_c04_auto_translation.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m044-c04.sudoers"
readonly HELPER_SHA='276354b2bd8d97e1b43f8189070593d7a716df702c6b04e1899056f092f8fe04'
readonly SUDOERS_SHA='8cdb77ae3b5633463b9a2bd2eee16febb010ff20b0d146695b26516baa5d570e'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m044-c04-auto-translation'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m044-c04'

die() { echo "M044_C04_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m044-c04-helper-install.XXXXXX)
trap 'if [[ -n "${private_root:-}" && "$private_root" == /root/m044-c04-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi' EXIT
private_helper="${private_root}/helper"
private_sudoers="${private_root}/sudoers"

for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do
  [[ -f "$source" && ! -L "$source" ]] || die 'SOURCE_INVALID'
  [[ $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_METADATA_INVALID'
done

install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
install -o root -g root -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'HELPER_HASH_MISMATCH'
[[ $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SUDOERS_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$private_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_INVALID'
  [[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'EXISTING_HELPER_HASH_UNEXPECTED'
else
  temporary_helper="${TARGET_HELPER}.tmp.$$"
  install -o root -g root -m 0755 "$private_helper" "$temporary_helper"
  [[ $(sha256 "$temporary_helper") == "$HELPER_SHA" ]] || die 'INSTALLED_HELPER_HASH_INVALID'
  mv -f "$temporary_helper" "$TARGET_HELPER"
fi

if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_INVALID'
  [[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_HASH_UNEXPECTED'
else
  temporary_sudoers="${TARGET_SUDOERS}.tmp.$$"
  install -o root -g root -m 0440 "$private_sudoers" "$temporary_sudoers"
  visudo -cf "$temporary_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
  mv -f "$temporary_sudoers" "$TARGET_SUDOERS"
fi

install -d -o root -g root -m 0700 /root/backup-erp-code-backups /var/lib/chinaplanetos-m044-c04-staging
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'
echo "M044_C04_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
