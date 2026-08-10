#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m046_translation_policy.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m046-preflight.sudoers"
readonly HELPER_SHA='a351c5653d8a66e9f7a65db579bf9ce21b5a49d4c082d04dcc48cb0312475926'
readonly PREVIOUS_HELPER_SHA='0324870d9bdcfe8659508cb702f3ddbcc7c8ca27d854504d738e3fb1d29bad58'
readonly SUDOERS_SHA='18a7dfd57efe44ea961ec45af13c589b090eb4c626145b11ea03bc5bb58fe17d'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m046-translation-policy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m046-preflight'

die() { echo "M046_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m046-production-helper-install.XXXXXX)
case "$private_root" in /root/m046-production-helper-install.*) ;; *) die 'PRIVATE_ROOT_INVALID' ;; esac
cleanup() { case "$private_root" in /root/m046-production-helper-install.*) [[ -d "$private_root" ]] && rm -rf "$private_root" ;; esac; }
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

install -d -o root -g root -m 0700 /root/chinaplanetos-production-code-backups /var/lib/chinaplanetos-m046-production /var/lib/chinaplanetos-m046-production/requests
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'
echo "M046_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA PREFLIGHT_ONLY=YES DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
