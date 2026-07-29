#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly HELPER_SOURCE='/home/codex-deploy/staging-inbox/chinaplanetos-m039-upload-limit'
readonly SUDOERS_SOURCE='/home/codex-deploy/staging-inbox/codex-deploy-m039-upload-limit.sudoers'
readonly ARTIFACT_SOURCE='/home/codex-deploy/staging-inbox/m039-851c231.tar.gz'
readonly HELPER_TARGET='/usr/local/sbin/chinaplanetos-m039-upload-limit'
readonly SUDOERS_TARGET='/etc/sudoers.d/codex-deploy-m039-upload-limit'
readonly HELPER_SHA='6746e6ab1a9d91a737a15d5198b5ea403765c12f8ff372ff185c94d606abeba3'
readonly SUDOERS_SHA='b3cf47e21bc4fa18d8588c7f39290101d91c21c7f04785d5949b08a0673b0e13'
readonly ARTIFACT_SHA='076280035b2116bdcccbcac42d76508bd9f03170fde16dff7ecf8b614b9e7809'
readonly BACKUP_ROOT='/root/chinaplanetos-m039-upload-limit-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m039-upload-limit'

die() { echo "M039_INSTALL_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'

for source in "$HELPER_SOURCE" "$SUDOERS_SOURCE" "$ARTIFACT_SOURCE"; do
  [[ -f "$source" && ! -L "$source" ]] || die 'SOURCE_INVALID'
  [[ $(stat -c '%U:%G' "$source") == 'codex-deploy:codex-deploy' ]] || die 'SOURCE_OWNER_INVALID'
done

[[ $(sha256 "$HELPER_SOURCE") == "$HELPER_SHA" ]] || die 'HELPER_HASH_MISMATCH'
[[ $(sha256 "$SUDOERS_SOURCE") == "$SUDOERS_SHA" ]] || die 'SUDOERS_HASH_MISMATCH'
[[ $(sha256 "$ARTIFACT_SOURCE") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
bash -n "$HELPER_SOURCE" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$SUDOERS_SOURCE" >/dev/null || die 'SUDOERS_SOURCE_INVALID'

if [[ -e "$HELPER_TARGET" ]]; then
  [[ -f "$HELPER_TARGET" && ! -L "$HELPER_TARGET" && $(sha256 "$HELPER_TARGET") == "$HELPER_SHA" ]] || die 'HELPER_TARGET_CONFLICT'
fi
if [[ -e "$SUDOERS_TARGET" ]]; then
  [[ -f "$SUDOERS_TARGET" && ! -L "$SUDOERS_TARGET" && $(sha256 "$SUDOERS_TARGET") == "$SUDOERS_SHA" ]] || die 'SUDOERS_TARGET_CONFLICT'
fi

install -o root -g root -m 0755 "$HELPER_SOURCE" "$HELPER_TARGET"
install -o root -g root -m 0440 "$SUDOERS_SOURCE" "$SUDOERS_TARGET"
install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
visudo -cf /etc/sudoers >/dev/null || die 'SUDOERS_GLOBAL_INVALID'

[[ $(sha256 "$HELPER_TARGET") == "$HELPER_SHA" && $(stat -c '%U:%G|%a' "$HELPER_TARGET") == 'root:root|755' ]] || die 'HELPER_INSTALL_VERIFY'
[[ $(sha256 "$SUDOERS_TARGET") == "$SUDOERS_SHA" && $(stat -c '%U:%G|%a' "$SUDOERS_TARGET") == 'root:root|440' ]] || die 'SUDOERS_INSTALL_VERIFY'
[[ $(stat -c '%U:%G|%a' "$BACKUP_ROOT") == 'root:root|700' ]] || die 'BACKUP_ROOT_VERIFY'
[[ $(stat -c '%U:%G|%a' "$STATE_ROOT") == 'root:root|700' ]] || die 'STATE_ROOT_VERIFY'

echo "M039_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA ARTIFACT_SHA=$ARTIFACT_SHA APPLY_EXECUTED=NO"
