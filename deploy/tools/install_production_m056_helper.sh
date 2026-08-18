#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m056_shipment_status_email.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m056.sudoers"
readonly HELPER_SHA='6798f0d893b6a6b2683832f96962cb19cfc5565825c1bc2e058b7efeab531a16'
readonly SUDOERS_SHA='5a9b0f404d6a8f4e359413850d37a4bf8ff5cadab247186d0fb5a11cf5974b1e'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m056-shipment-status-email'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m056'

die() { echo "M056_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m056-production-helper-install.XXXXXX)
trap 'if [[ -n "${private_root:-}" && "$private_root" == /root/m056-production-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi' EXIT
private_helper="${private_root}/helper"; private_sudoers="${private_root}/sudoers"

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
  [[ "$existing_helper_sha" == "$HELPER_SHA" ]] || die 'EXISTING_HELPER_HASH_UNEXPECTED'
fi
if [[ ! -e "$TARGET_HELPER" || $(sha256 "$TARGET_HELPER") != "$HELPER_SHA" ]]; then
  temporary_helper="${TARGET_HELPER}.tmp.$$"; install -o root -g root -m 0755 "$private_helper" "$temporary_helper"; [[ $(sha256 "$temporary_helper") == "$HELPER_SHA" ]] || die 'INSTALLED_HELPER_HASH_INVALID'; sync -f "$temporary_helper"; mv -f "$temporary_helper" "$TARGET_HELPER"; sync -f "$(dirname "$TARGET_HELPER")"
fi

if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_INVALID'
  [[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_HASH_UNEXPECTED'
else
  temporary_sudoers="${TARGET_SUDOERS}.tmp.$$"; install -o root -g root -m 0440 "$private_sudoers" "$temporary_sudoers"; visudo -cf "$temporary_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'; sync -f "$temporary_sudoers"; mv -f "$temporary_sudoers" "$TARGET_SUDOERS"; sync -f "$(dirname "$TARGET_SUDOERS")"
fi

install -d -o root -g root -m 0700 /root/chinaplanetos-production-code-backups /var/lib/chinaplanetos-m056-production
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'
echo "M056_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO"
