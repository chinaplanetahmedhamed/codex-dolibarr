#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m026_shipment_collaboration.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m026-shipment-collaboration.sudoers"
readonly HELPER_SHA='434920d01f590c379c6d5d4818aac599ce25568c73308cf671e5740f18bee014'
readonly SUDOERS_SHA='59b8f1576b5276e5bdb0678b963d9092497e006dbb7ea5e4a49cbf5ab0b7fd9e'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m026-shipment-collaboration-deploy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m026-shipment-collaboration'

die() { echo "M026_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do
	[[ -f "$source" && ! -L "$source" ]] || die 'SOURCE_INVALID'
	[[ $(stat -c '%U:%G' "$source") == 'codex-deploy:codex-deploy' ]] || die 'SOURCE_OWNER_INVALID'
	[[ $(stat -c '%a' "$source") == '644' ]] || die 'SOURCE_MODE_INVALID'
done
[[ $(sha256 "$SOURCE_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_HASH_MISMATCH'
[[ $(sha256 "$SOURCE_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_HASH_MISMATCH'
bash -n "$SOURCE_HELPER" || die 'HELPER_SYNTAX_INVALID'

current_helper_sha='ABSENT'
if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
	[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" ]] || die 'UNEXPECTED_EXISTING_HELPER'
	[[ $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_METADATA_INVALID'
	current_helper_sha=$(sha256 "$TARGET_HELPER")
	[[ "$current_helper_sha" == "$HELPER_SHA" ]] || die 'UNEXPECTED_EXISTING_HELPER_HASH'
fi

tmp_sudoers="${TARGET_SUDOERS}.tmp.$$"
install -o root -g root -m 0440 "$SOURCE_SUDOERS" "$tmp_sudoers"
visudo -cf "$tmp_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
	[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'UNEXPECTED_EXISTING_SUDOERS'
	rm -f "$tmp_sudoers"
else
	mv -f "$tmp_sudoers" "$TARGET_SUDOERS"
fi
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'

install_helper=0
if [[ ! -e "$TARGET_HELPER" ]]; then
	install_helper=1
elif [[ $(sha256 "$TARGET_HELPER") != "$HELPER_SHA" ]]; then
	install_helper=1
fi
if [[ $install_helper -eq 1 ]]; then
	tmp_helper="${TARGET_HELPER}.tmp.$$"
	install -o root -g root -m 0755 "$SOURCE_HELPER" "$tmp_helper"
	bash -n "$tmp_helper" || die 'INSTALLED_HELPER_SYNTAX_INVALID'
	[[ $(sha256 "$tmp_helper") == "$HELPER_SHA" ]] || die 'INSTALLED_HELPER_HASH_INVALID'
	mv -f "$tmp_helper" "$TARGET_HELPER"
fi

[[ $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'HELPER_METADATA_INVALID'
[[ $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'SUDOERS_METADATA_INVALID'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'

echo "M026_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO PRODUCTION_ACCESSED=NO"
