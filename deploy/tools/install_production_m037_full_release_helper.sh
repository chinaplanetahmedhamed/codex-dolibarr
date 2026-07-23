#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_production_m037_full_release.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-production-m037-full-release.sudoers"
readonly HELPER_SHA='13f7e2462d68449a53f593f841f8afd4141432450907e984ab7475fa0161d65f'
readonly PREVIOUS_HELPER_SHA='0bdec24f6e48feb86adbf4d12fe570209ce62fd02445a085b9d69be1e7ed6539'
readonly SUDOERS_SHA='0a3e9189a5d180dd7f3d8bf159825e597dcf862f3fca41115b64b0dccd23049e'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m037-full-release-deploy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m037-full-release'

die() { echo "M037_PRODUCTION_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m037-production-helper-install.XXXXXX)
trap 'if [[ -n "${private_root:-}" && "$private_root" == /root/m037-production-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi' EXIT
private_helper="${private_root}/helper"
private_sudoers="${private_root}/sudoers"

for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do
	[[ -f "$source" && ! -L "$source" ]] || die 'SOURCE_INVALID'
	[[ $(stat -c '%U:%G' "$source") == 'codex-deploy:codex-deploy' ]] || die 'SOURCE_OWNER_INVALID'
	[[ $(stat -c '%a' "$source") == '644' ]] || die 'SOURCE_MODE_INVALID'
done

install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
install -o root -g root -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'HELPER_HASH_MISMATCH'
[[ $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SUDOERS_HASH_MISMATCH'
bash -n "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$private_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
	[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" ]] || die 'UNEXPECTED_EXISTING_HELPER'
	[[ $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_METADATA_INVALID'
	existing_helper_sha=$(sha256 "$TARGET_HELPER")
	[[ "$existing_helper_sha" == "$HELPER_SHA" || "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]] || die 'UNEXPECTED_EXISTING_HELPER_HASH'
	if [[ "$existing_helper_sha" == "$PREVIOUS_HELPER_SHA" ]]; then
		tmp_helper="${TARGET_HELPER}.tmp.$$"
		install -o root -g root -m 0755 "$private_helper" "$tmp_helper"
		bash -n "$tmp_helper" || die 'INSTALLED_HELPER_SYNTAX_INVALID'
		[[ $(sha256 "$tmp_helper") == "$HELPER_SHA" ]] || die 'INSTALLED_HELPER_HASH_INVALID'
		mv -f "$tmp_helper" "$TARGET_HELPER"
	fi
else
	tmp_helper="${TARGET_HELPER}.tmp.$$"
	install -o root -g root -m 0755 "$private_helper" "$tmp_helper"
	bash -n "$tmp_helper" || die 'INSTALLED_HELPER_SYNTAX_INVALID'
	[[ $(sha256 "$tmp_helper") == "$HELPER_SHA" ]] || die 'INSTALLED_HELPER_HASH_INVALID'
	mv -f "$tmp_helper" "$TARGET_HELPER"
fi

if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
	[[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" ]] || die 'UNEXPECTED_EXISTING_SUDOERS'
	[[ $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_METADATA_INVALID'
	[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'UNEXPECTED_EXISTING_SUDOERS_HASH'
else
	tmp_sudoers="${TARGET_SUDOERS}.tmp.$$"
	install -o root -g root -m 0440 "$private_sudoers" "$tmp_sudoers"
	visudo -cf "$tmp_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
	mv -f "$tmp_sudoers" "$TARGET_SUDOERS"
fi

visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PARSE_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'

echo "M037_PRODUCTION_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO PRODUCTION_ACCESSED=NO"
