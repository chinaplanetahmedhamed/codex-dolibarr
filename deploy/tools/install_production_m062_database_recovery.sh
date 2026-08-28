#!/usr/bin/env bash
set -Eeuo pipefail

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE='/home/codex-deploy/staging-inbox/recover-production-m062-database.sh'
readonly SOURCE_SHA='ecb5b68490431be39879c77eee661bf62845be022cc3dd3a81028d61dc292a1d'
readonly TARGET='/root/recover-production-m062-database'

private_root=''
target_stage=''
INSTALL_COMPLETE=0

die() { echo "M062_PRODUCTION_DB_RECOVERY_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $INSTALL_COMPLETE -eq 0 && -n "$target_stage" && -e "$target_stage" && ! -L "$target_stage" ]]; then rm -f "$target_stage"; fi
  if [[ -n "$private_root" && "$private_root" == /root/m062-production-db-recovery-install.* && -d "$private_root" && ! -L "$private_root" ]]; then rm -rf "$private_root"; fi
  exit "$rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'ARGUMENTS_FORBIDDEN'
[[ -f "$SOURCE" && ! -L "$SOURCE" && $(stat -c '%U:%G %a' "$SOURCE") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_METADATA_INVALID'

private_root=$(mktemp -d /root/m062-production-db-recovery-install.XXXXXX)
private_source="${private_root}/recovery"
install -o root -g root -m 0600 "$SOURCE" "$private_source"
[[ $(sha256 "$private_source") == "$SOURCE_SHA" ]] || die 'SOURCE_HASH_INVALID'
bash -n "$private_source" || die 'SOURCE_SYNTAX_INVALID'

if [[ -e "$TARGET" || -L "$TARGET" ]]; then
  [[ -f "$TARGET" && ! -L "$TARGET" && $(stat -c '%U:%G %a' "$TARGET") == 'root:root 700' && $(sha256 "$TARGET") == "$SOURCE_SHA" ]] || die 'UNEXPECTED_EXISTING_TARGET'
else
  target_stage="${TARGET}.stage.$$"
  install -o root -g root -m 0700 "$private_source" "$target_stage"
  [[ $(sha256 "$target_stage") == "$SOURCE_SHA" ]] || die 'STAGED_HASH_INVALID'
  mv "$target_stage" "$TARGET"
  target_stage=''
fi

[[ -f "$TARGET" && ! -L "$TARGET" && $(stat -c '%U:%G %a' "$TARGET") == 'root:root 700' && $(sha256 "$TARGET") == "$SOURCE_SHA" ]] || die 'POST_INSTALL_INVALID'
INSTALL_COMPLETE=1
echo "M062_PRODUCTION_DB_RECOVERY_INSTALL=PASS RECOVERY_SHA=$SOURCE_SHA TARGET=$TARGET RECOVERY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
