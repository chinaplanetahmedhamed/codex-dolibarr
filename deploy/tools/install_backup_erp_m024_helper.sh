#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/staging-inbox'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m024_shipment_collaboration.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m024-shipment-collaboration.sudoers"
readonly HELPER_SHA='97a9ec92b151eccf46b624c64b6d6e20e001c0bdfc46dacdb11b541fa8d22530'
readonly SUDOERS_SHA='a5fa71eb1aec148b97e4ed2d59ceeaca30e082d300b93a2f7916fdeef8ca5811'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m024-shipment-collaboration-deploy'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m024-shipment-collaboration'

die() { echo "M024_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
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

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
	[[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'UNEXPECTED_EXISTING_HELPER'
else
	tmp_helper="${TARGET_HELPER}.tmp.$$"
	install -o root -g root -m 0755 "$SOURCE_HELPER" "$tmp_helper"
	bash -n "$tmp_helper" || die 'INSTALLED_HELPER_SYNTAX_INVALID'
	mv -f "$tmp_helper" "$TARGET_HELPER"
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

[[ $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'HELPER_METADATA_INVALID'
[[ $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'SUDOERS_METADATA_INVALID'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'

echo "M024_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA DEPLOY_EXECUTED=NO PRODUCTION_ACCESSED=NO"
