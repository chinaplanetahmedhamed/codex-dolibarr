#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/m074-qc-preflight-install'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m074_qc_preflight.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m074-qc-preflight.sudoers"
readonly HELPER_SHA='2f1f9f24ed2f0a1bda121190f41d5a6c73bf6c01fab209c877b03bfd4ef16e39'
readonly SUDOERS_SHA='777217564edf5a9b1dd67617e73cdf4e8f7fe3f90c3095ccc1930d1016fdbf38'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight'

die() { echo "M074_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

private_root=''
temporary_helper=''
temporary_sudoers=''
created_helper=0
created_sudoers=0

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $rc -ne 0 ]]; then
    if [[ $created_sudoers -eq 1 && -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]]; then rm -f "$TARGET_SUDOERS"; fi
    if [[ $created_helper -eq 1 && -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]]; then rm -f "$TARGET_HELPER"; fi
  fi
  if [[ -n "$temporary_helper" && "$temporary_helper" == "${TARGET_HELPER}.tmp."* && -f "$temporary_helper" && ! -L "$temporary_helper" ]]; then rm -f "$temporary_helper"; fi
  if [[ -n "$temporary_sudoers" && "$temporary_sudoers" == "${TARGET_SUDOERS}.tmp."* && -f "$temporary_sudoers" && ! -L "$temporary_sudoers" ]]; then rm -f "$temporary_sudoers"; fi
  if [[ -n "$private_root" && "$private_root" == /root/m074-helper-install.* && -d "$private_root" ]]; then rm -rf "$private_root"; fi
  exit "$rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'
[[ -d "$SOURCE_ROOT" && ! -L "$SOURCE_ROOT" && $(stat -c '%U:%G %a' "$SOURCE_ROOT") == 'codex-deploy:codex-deploy 700' ]] || die 'SOURCE_ROOT_INVALID'

private_root=$(mktemp -d /root/m074-helper-install.XXXXXX)
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

# Validate the complete existing and global state before creating either target.
if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" ]]; then
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_INVALID'
  [[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'EXISTING_HELPER_HASH_UNEXPECTED'
fi
if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_INVALID'
  [[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'EXISTING_SUDOERS_HASH_UNEXPECTED'
fi
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'

# Stage every missing target before publishing either one.
if [[ ! -e "$TARGET_HELPER" && ! -L "$TARGET_HELPER" ]]; then
  temporary_helper="${TARGET_HELPER}.tmp.$$"
  install -o root -g root -m 0755 "$private_helper" "$temporary_helper"
  [[ $(sha256 "$temporary_helper") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_INVALID'
fi
if [[ ! -e "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" ]]; then
  temporary_sudoers="${TARGET_SUDOERS}.tmp.$$"
  install -o root -g root -m 0440 "$private_sudoers" "$temporary_sudoers"
  visudo -cf "$temporary_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
fi

# Publish the staged pair. The EXIT trap removes only newly created targets on failure.
if [[ -n "$temporary_helper" ]]; then
  mv -f "$temporary_helper" "$TARGET_HELPER"
  temporary_helper=''
  created_helper=1
fi
if [[ -n "$temporary_sudoers" ]]; then
  mv -f "$temporary_sudoers" "$TARGET_SUDOERS"
  temporary_sudoers=''
  created_sudoers=1
fi

visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POST_HASH_MISMATCH'
[[ $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POST_HASH_MISMATCH'
echo "M074_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=compare,preflight,status APPLY_AVAILABLE=NO DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
