#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m046_c01_translation_policy.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m046-c01.sudoers"
readonly HELPER_SHA='2546a927e27c3b0fcf1d4957c0b8864fd1359a177250fcc980bdd2f0bc50ea1f'
readonly PREVIOUS_HELPER_SHA='2b47bbb8e19a8a9869d5410fbe1122d30b089b66c41c0535e96e670b3df686fa'
readonly SUDOERS_SHA='a44d291dcc7a3a417c98aff42421b88d51212f97b7ef26406a7adba14a98633b'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m046-c01-translation-policy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m046-c01'

die() { echo "M046_C01_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m046-c01-helper-install.XXXXXX)
case "$private_root" in /root/m046-c01-helper-install.*) ;; *) die 'PRIVATE_ROOT_INVALID' ;; esac
cleanup() { case "$private_root" in /root/m046-c01-helper-install.*) [[ -d "$private_root" ]] && rm -rf "$private_root" ;; esac; }
trap cleanup EXIT
private_helper="$private_root/helper"
private_sudoers="$private_root/sudoers"

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
  existing_helper_sha=$(sha256 "$TARGET_HELPER")
  [[ "$existing_helper_sha" == "$HELPER_SHA" || "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]] || die 'EXISTING_HELPER_HASH_UNEXPECTED'
  if [[ "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]]; then
    temporary_helper="${TARGET_HELPER}.tmp.$$"
    install -o root -g root -m 0755 "$private_helper" "$temporary_helper"
    [[ $(sha256 "$temporary_helper") == "$HELPER_SHA" ]] || die 'UPGRADED_HELPER_HASH_INVALID'
    mv -f "$temporary_helper" "$TARGET_HELPER"
  fi
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

install -d -o root -g root -m 0700 /root/backup-erp-code-backups /var/lib/chinaplanetos-m046-c01-staging /var/lib/chinaplanetos-m046-c01-staging/requests
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'
echo "M046_C01_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA OLD_M044_HELPER_PRESERVED=YES DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
