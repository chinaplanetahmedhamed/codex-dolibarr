#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m055_customer_replies.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m055.sudoers"
readonly HELPER_SHA='60eda2090b4bee1e8469a51f1cd57ff877b2b2a819daefb51949aa257c669f04'
readonly SUDOERS_SHA='1ac52f20622ec15f916e5a5f952ffd5a707cdf2e794a642e25e7066fb31defe2'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m055-customer-replies'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m055'

die() { echo "M055_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m055-production-helper-install.XXXXXX)
trap 'if [[ -n "${private_root:-}" && "$private_root" == /root/m055-production-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi' EXIT
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

[[ ! -e "$TARGET_HELPER" && ! -L "$TARGET_HELPER" ]] || die 'TARGET_HELPER_ALREADY_EXISTS'
[[ ! -e "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" ]] || die 'TARGET_SUDOERS_ALREADY_EXISTS'

temporary_helper="${TARGET_HELPER}.tmp.$$"
temporary_sudoers="${TARGET_SUDOERS}.tmp.$$"
install -o root -g root -m 0755 "$private_helper" "$temporary_helper"
install -o root -g root -m 0440 "$private_sudoers" "$temporary_sudoers"
[[ $(sha256 "$temporary_helper") == "$HELPER_SHA" ]] || die 'INSTALLED_HELPER_HASH_INVALID'
visudo -cf "$temporary_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
mv -f "$temporary_helper" "$TARGET_HELPER"
mv -f "$temporary_sudoers" "$TARGET_SUDOERS"

install -d -o root -g root -m 0700 /root/chinaplanetos-production-code-backups /var/lib/chinaplanetos-m055-production
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'
echo "M055_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO"
