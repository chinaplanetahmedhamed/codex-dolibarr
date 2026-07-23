#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m037_full_release.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m037-full-release.sudoers"
readonly HELPER_SHA='ff662d508c6aa283ff3e5320ff8643efe89f55db1f7530c5e7364e6969c84ebd'
readonly PREVIOUS_HELPER_SHA='6ac80b97f86aeb7dc6b2da1300b8ced788421bbaad670b513aeb42f832061ce4'
readonly SUDOERS_SHA='815e0040fce5ebe2fa77f840a3811e3733db995c3bf4360c39fa105c4d4deab1'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m037-full-release-deploy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m037-full-release'

die() { echo "M037_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

private_root=$(mktemp -d /root/m037-helper-install.XXXXXX)
trap 'if [[ -n "${private_root:-}" && "$private_root" == /root/m037-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi' EXIT
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

echo "M037_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO PRODUCTION_ACCESSED=NO"
