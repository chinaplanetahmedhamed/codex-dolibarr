#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/m073-helper-install'
readonly SOURCE_HELPER="$SOURCE_ROOT/chinaplanetos_backup_erp_rebuild_m073.py"
readonly SOURCE_SUDOERS="$SOURCE_ROOT/codex-deploy-backup-erp-rebuild-m073.sudoers"
readonly BUNDLE_ROOT='/home/codex-deploy/m073-rebuild-inbox'
readonly SOURCE_BUNDLE="$BUNDLE_ROOT/backup-erp-rebuild-m073-r01.tar.gz"
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-rebuild-m073'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-rebuild-m073'
readonly HELPER_SHA='9fc52d29e58b6e987e9dfcab9283e733717718f98527751d85f87e4dce01907d'
readonly SUDOERS_SHA='1d4e7cf16fafe5a30e64353f60d8578d716a70ee20c1ab3f3dbd0cc1f1495966'
readonly BUNDLE_SHA='c0f42a9e159b8f4d1dc83b223a62205f7d598ef62f0b1b81662c55259709f840'

fail() { printf 'M073_INSTALL=FAIL REASON=%s\n' "$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
mode_of() { stat -c '%a' "$1"; }
owner_group_of() { stat -c '%U:%G' "$1"; }
fsync_path() { python3 - "$1" <<'PY'
import os, pathlib, sys
path = pathlib.Path(sys.argv[1])
descriptor = os.open(path, os.O_RDONLY | (getattr(os, "O_DIRECTORY", 0) if path.is_dir() else 0))
try: os.fsync(descriptor)
finally: os.close(descriptor)
PY
}

[[ $(id -u) -eq 0 ]] || fail ROOT_REQUIRED
[[ $(basename "$0") == 'install_backup_erp_rebuild_m073_helper.sh' ]] || fail INSTALLER_PATH_INVALID
[[ -d "$SOURCE_ROOT" && ! -L "$SOURCE_ROOT" && $(owner_group_of "$SOURCE_ROOT") == 'codex-deploy:codex-deploy' && $(mode_of "$SOURCE_ROOT") == 700 ]] || fail SOURCE_ROOT_INVALID
[[ -d "$BUNDLE_ROOT" && ! -L "$BUNDLE_ROOT" && $(owner_group_of "$BUNDLE_ROOT") == 'codex-deploy:codex-deploy' && $(mode_of "$BUNDLE_ROOT") == 700 ]] || fail BUNDLE_ROOT_INVALID
for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do
  [[ -f "$source" && ! -L "$source" && $(owner_group_of "$source") == 'codex-deploy:codex-deploy' && $(mode_of "$source") == 600 ]] || fail SOURCE_FILE_INVALID
done
[[ -f "$SOURCE_BUNDLE" && ! -L "$SOURCE_BUNDLE" && $(owner_group_of "$SOURCE_BUNDLE") == 'codex-deploy:codex-deploy' && $(mode_of "$SOURCE_BUNDLE") == 600 ]] || fail BUNDLE_FILE_INVALID
[[ $(sha256 "$SOURCE_HELPER") == "$HELPER_SHA" ]] || fail HELPER_CHECKSUM_MISMATCH
[[ $(sha256 "$SOURCE_SUDOERS") == "$SUDOERS_SHA" ]] || fail SUDOERS_CHECKSUM_MISMATCH
[[ $(sha256 "$SOURCE_BUNDLE") == "$BUNDLE_SHA" ]] || fail BUNDLE_CHECKSUM_MISMATCH
PYTHONDONTWRITEBYTECODE=1 python3 - "$SOURCE_HELPER" <<'PY' || fail PYTHON_SYNTAX_INVALID
import pathlib, sys
compile(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"), sys.argv[1], "exec")
PY
visudo -cf "$SOURCE_SUDOERS" >/dev/null || fail SUDOERS_SYNTAX_INVALID

helper_stage=$(mktemp /usr/local/sbin/.chinaplanetos-backup-erp-rebuild-m073.XXXXXX)
sudoers_stage=$(mktemp /etc/sudoers.d/.codex-deploy-backup-erp-rebuild-m073.XXXXXX)
helper_installed=0
sudoers_installed=0
install_complete=0
cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $install_complete -eq 0 ]]; then
    if [[ $sudoers_installed -eq 1 ]]; then rm -f "$TARGET_SUDOERS" || rc=1; fi
    if [[ $helper_installed -eq 1 ]]; then rm -f "$TARGET_HELPER" || rc=1; fi
    fsync_path /etc/sudoers.d || rc=1
    fsync_path /usr/local/sbin || rc=1
  fi
  rm -f "$helper_stage" "$sudoers_stage"
  exit "$rc"
}
trap cleanup EXIT

install -o root -g root -m 0555 "$SOURCE_HELPER" "$helper_stage"
install -o root -g root -m 0440 "$SOURCE_SUDOERS" "$sudoers_stage"
[[ $(sha256 "$helper_stage") == "$HELPER_SHA" ]] || fail STAGED_HELPER_CHECKSUM_MISMATCH
[[ $(sha256 "$sudoers_stage") == "$SUDOERS_SHA" ]] || fail STAGED_SUDOERS_CHECKSUM_MISMATCH
visudo -cf "$sudoers_stage" >/dev/null || fail STAGED_SUDOERS_INVALID

if [[ -e "$TARGET_HELPER" || -L "$TARGET_HELPER" || -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_HELPER" && ! -L "$TARGET_HELPER" && $(owner_group_of "$TARGET_HELPER") == root:root && $(mode_of "$TARGET_HELPER") == 555 && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || fail EXISTING_HELPER_INVALID
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(owner_group_of "$TARGET_SUDOERS") == root:root && $(mode_of "$TARGET_SUDOERS") == 440 && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || fail EXISTING_SUDOERS_INVALID
  printf 'M073_INSTALL=PASS STATE=ALREADY_INSTALLED HELPER_EXECUTED=NO STACK_CREATED=NO\n'
  exit 0
fi

mv "$helper_stage" "$TARGET_HELPER"
helper_stage=''
helper_installed=1
mv "$sudoers_stage" "$TARGET_SUDOERS"
sudoers_stage=''
sudoers_installed=1
fsync_path "$TARGET_HELPER"
fsync_path "$TARGET_SUDOERS"
fsync_path /usr/local/sbin
fsync_path /etc/sudoers.d
visudo -cf /etc/sudoers >/dev/null || fail GLOBAL_SUDOERS_INVALID
[[ $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || fail POST_INSTALL_CHECKSUM_MISMATCH
install_complete=1
printf 'M073_INSTALL=PASS STATE=INSTALLED HELPER_EXECUTED=NO STACK_CREATED=NO\n'
