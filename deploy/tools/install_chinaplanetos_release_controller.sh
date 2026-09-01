#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/m072-controller-install'
readonly SOURCE_CONTROLLER="$SOURCE_ROOT/chinaplanetos_release_controller.py"
readonly SOURCE_LIBRARY="$SOURCE_ROOT/chinaplanetos_release.py"
readonly SOURCE_SUDOERS="$SOURCE_ROOT/codex-deploy-chinaplanetos-release.sudoers"
readonly TARGET_LINK='/usr/local/sbin/chinaplanetos-release-controller'
readonly LIBEXEC_ROOT='/usr/local/libexec/chinaplanetos-release'
readonly GENERATIONS_ROOT="$LIBEXEC_ROOT/generations"
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-chinaplanetos-release'
readonly STATE_ROOT='/var/lib/chinaplanetos-release'
readonly LOCK_FILE="$STATE_ROOT/controller.lock"
readonly CONTROLLER_SHA='9a16e73649857595a1d162a3879158f347bffabef5db6160b83a0584ed8480a0'
readonly LIBRARY_SHA='c9aefe0ed8019ef17f670dfee99a3af8f5fdad4fe38cf4e188fd172e78f7382b'
readonly SUDOERS_SHA='a6305054b7917df1a4487209a827b774bf51362892c37faed9389ff94898622a'
readonly GENERATION_ID="${CONTROLLER_SHA:0:16}-${LIBRARY_SHA:0:16}"
readonly GENERATION_DIR="$GENERATIONS_ROOT/$GENERATION_ID"

fail() { printf 'M072_CONTROLLER_INSTALL=FAIL REASON=%s\n' "$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
mode_of() { stat -c '%a' "$1"; }
owner_group_of() { stat -c '%U:%G' "$1"; }
fsync_path() { python3 - "$1" <<'PY'
import os, pathlib, sys
path = pathlib.Path(sys.argv[1])
fd = os.open(path, os.O_RDONLY | (getattr(os, 'O_DIRECTORY', 0) if path.is_dir() else 0))
try: os.fsync(fd)
finally: os.close(fd)
PY
}

[[ $(id -u) -eq 0 ]] || fail ROOT_REQUIRED
[[ $(basename "$0") == 'install_chinaplanetos_release_controller.sh' ]] || fail INSTALLER_PATH_INVALID
[[ -d "$SOURCE_ROOT" && ! -L "$SOURCE_ROOT" && $(owner_group_of "$SOURCE_ROOT") == 'codex-deploy:codex-deploy' && $(mode_of "$SOURCE_ROOT") == 700 ]] || fail SOURCE_ROOT_INVALID
for source in "$SOURCE_CONTROLLER" "$SOURCE_LIBRARY" "$SOURCE_SUDOERS"; do
  [[ -f "$source" && ! -L "$source" && $(owner_group_of "$source") == 'codex-deploy:codex-deploy' && $(mode_of "$source") == 600 ]] || fail SOURCE_FILE_INVALID
done
[[ $(sha256 "$SOURCE_CONTROLLER") == "$CONTROLLER_SHA" ]] || fail CONTROLLER_CHECKSUM_MISMATCH
[[ $(sha256 "$SOURCE_LIBRARY") == "$LIBRARY_SHA" ]] || fail LIBRARY_CHECKSUM_MISMATCH
[[ $(sha256 "$SOURCE_SUDOERS") == "$SUDOERS_SHA" ]] || fail SUDOERS_CHECKSUM_MISMATCH
python3 - "$SOURCE_CONTROLLER" "$SOURCE_LIBRARY" <<'PY' || fail PYTHON_SYNTAX_INVALID
import pathlib, sys
for raw in sys.argv[1:]:
    path = pathlib.Path(raw)
    compile(path.read_text(encoding='utf-8'), str(path), 'exec')
PY
visudo -cf "$SOURCE_SUDOERS" >/dev/null || fail SUDOERS_SYNTAX_INVALID

if [[ -e "$STATE_ROOT" || -L "$STATE_ROOT" ]]; then
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(owner_group_of "$STATE_ROOT") == root:root && $(mode_of "$STATE_ROOT") == 700 ]] || fail STATE_ROOT_INVALID
else
  install -d -o root -g root -m 0700 "$STATE_ROOT"
  fsync_path "$STATE_ROOT"
  fsync_path "${STATE_ROOT%/*}"
fi
if [[ -e "$LOCK_FILE" || -L "$LOCK_FILE" ]]; then
  [[ -f "$LOCK_FILE" && ! -L "$LOCK_FILE" && $(owner_group_of "$LOCK_FILE") == root:root && $(mode_of "$LOCK_FILE") == 600 ]] || fail CONTROLLER_LOCK_INVALID
else
  install -o root -g root -m 0600 /dev/null "$LOCK_FILE"
  fsync_path "$LOCK_FILE"
  fsync_path "$STATE_ROOT"
fi
exec 9>"$LOCK_FILE"
flock -n 9 || fail CONTROLLER_LOCK_CONTENDED
[[ -f "$LOCK_FILE" && ! -L "$LOCK_FILE" && $(owner_group_of "$LOCK_FILE") == root:root && $(mode_of "$LOCK_FILE") == 600 ]] || fail CONTROLLER_LOCK_INVALID

for directory in "$LIBEXEC_ROOT" "$GENERATIONS_ROOT"; do
  if [[ -e "$directory" || -L "$directory" ]]; then
    [[ -d "$directory" && ! -L "$directory" && $(owner_group_of "$directory") == root:root && $(mode_of "$directory") == 755 ]] || fail INSTALL_ROOT_INVALID
  else
    install -d -o root -g root -m 0755 "$directory"
    fsync_path "$directory"
    fsync_path "${directory%/*}"
  fi
done
generation_stage=''
link_stage=''
sudoers_stage=''
previous_link=''
previous_generation=''
previous_controller_sha=''
previous_library_sha=''
link_switched=0
sudoers_added=0
install_complete=0

validate_generation() {
  local directory=$1
  [[ -d "$directory" && ! -L "$directory" && $(owner_group_of "$directory") == root:root && $(mode_of "$directory") == 555 ]] || return 1
  [[ -f "$directory/controller" && ! -L "$directory/controller" && $(owner_group_of "$directory/controller") == root:root && $(mode_of "$directory/controller") == 555 && $(sha256 "$directory/controller") == "$CONTROLLER_SHA" ]] || return 1
  [[ -f "$directory/chinaplanetos_release.py" && ! -L "$directory/chinaplanetos_release.py" && $(owner_group_of "$directory/chinaplanetos_release.py") == root:root && $(mode_of "$directory/chinaplanetos_release.py") == 444 && $(sha256 "$directory/chinaplanetos_release.py") == "$LIBRARY_SHA" ]] || return 1
}

rollback_install() {
  local rollback_ok=1 restore_stage=''
  if [[ $link_switched -eq 1 ]]; then
    if [[ -n "$previous_link" ]]; then
      restore_stage=$(mktemp /usr/local/sbin/.chinaplanetos-release-controller.rollback.XXXXXX)
      rm -f "$restore_stage"
      ln -s "$previous_link" "$restore_stage" || rollback_ok=0
      [[ $rollback_ok -eq 0 ]] || mv -Tf "$restore_stage" "$TARGET_LINK" || rollback_ok=0
      [[ $rollback_ok -eq 0 || $(readlink "$TARGET_LINK") == "$previous_link" ]] || rollback_ok=0
      [[ $rollback_ok -eq 0 || $(sha256 "$TARGET_LINK") == "$previous_controller_sha" ]] || rollback_ok=0
      [[ $rollback_ok -eq 0 || $(sha256 "$previous_generation/chinaplanetos_release.py") == "$previous_library_sha" ]] || rollback_ok=0
    else
      [[ -L "$TARGET_LINK" && $(readlink "$TARGET_LINK") == "$GENERATION_DIR/controller" ]] || rollback_ok=0
      [[ $rollback_ok -eq 0 ]] || rm -f "$TARGET_LINK" || rollback_ok=0
      [[ ! -e "$TARGET_LINK" && ! -L "$TARGET_LINK" ]] || rollback_ok=0
    fi
    fsync_path /usr/local/sbin || rollback_ok=0
  fi
  if [[ $sudoers_added -eq 1 ]]; then
    [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || rollback_ok=0
    [[ $rollback_ok -eq 0 ]] || rm -f "$TARGET_SUDOERS" || rollback_ok=0
    fsync_path /etc/sudoers.d || rollback_ok=0
    visudo -cf /etc/sudoers >/dev/null || rollback_ok=0
  fi
  [[ $rollback_ok -eq 1 ]]
}

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $install_complete -eq 0 ]]; then rollback_install || rc=1; fi
  if [[ -n "$generation_stage" ]]; then case "$generation_stage" in "$GENERATIONS_ROOT"/.m072-generation.*) rm -rf "$generation_stage" ;; *) rc=1 ;; esac; fi
  if [[ -n "$link_stage" ]]; then case "$link_stage" in /usr/local/sbin/.chinaplanetos-release-controller.*) rm -f "$link_stage" ;; *) rc=1 ;; esac; fi
  if [[ -n "$sudoers_stage" ]]; then case "$sudoers_stage" in /etc/sudoers.d/.codex-deploy-chinaplanetos-release.*) rm -f "$sudoers_stage" ;; *) rc=1 ;; esac; fi
  exit "$rc"
}
trap cleanup EXIT

if [[ -e "$GENERATION_DIR" || -L "$GENERATION_DIR" ]]; then
  validate_generation "$GENERATION_DIR" || fail EXISTING_GENERATION_INVALID
else
  generation_stage=$(mktemp -d "$GENERATIONS_ROOT/.m072-generation.XXXXXX")
  install -o root -g root -m 0555 "$SOURCE_CONTROLLER" "$generation_stage/controller"
  install -o root -g root -m 0444 "$SOURCE_LIBRARY" "$generation_stage/chinaplanetos_release.py"
  [[ $(sha256 "$generation_stage/controller") == "$CONTROLLER_SHA" && $(sha256 "$generation_stage/chinaplanetos_release.py") == "$LIBRARY_SHA" ]] || fail STAGED_GENERATION_CHECKSUM_MISMATCH
  fsync_path "$generation_stage/controller"; fsync_path "$generation_stage/chinaplanetos_release.py"
  chmod 0555 "$generation_stage"
  mv "$generation_stage" "$GENERATION_DIR"
  generation_stage=''
  fsync_path "$GENERATIONS_ROOT"
  validate_generation "$GENERATION_DIR" || fail INSTALLED_GENERATION_INVALID
fi

if [[ -e "$TARGET_SUDOERS" || -L "$TARGET_SUDOERS" ]]; then
  [[ -f "$TARGET_SUDOERS" && ! -L "$TARGET_SUDOERS" && $(owner_group_of "$TARGET_SUDOERS") == root:root && $(mode_of "$TARGET_SUDOERS") == 440 && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || fail EXISTING_SUDOERS_INVALID
else
  sudoers_stage=$(mktemp /etc/sudoers.d/.codex-deploy-chinaplanetos-release.XXXXXX)
  install -o root -g root -m 0440 "$SOURCE_SUDOERS" "$sudoers_stage"
  visudo -cf "$sudoers_stage" >/dev/null || fail STAGED_SUDOERS_INVALID
  mv "$sudoers_stage" "$TARGET_SUDOERS"
  sudoers_stage=''
  sudoers_added=1
  fsync_path "$TARGET_SUDOERS"; fsync_path /etc/sudoers.d
fi
visudo -cf /etc/sudoers >/dev/null || fail GLOBAL_SUDOERS_INVALID

if [[ -e "$TARGET_LINK" || -L "$TARGET_LINK" ]]; then
  [[ -L "$TARGET_LINK" ]] || fail EXISTING_CONTROLLER_NOT_MANAGED
  previous_link=$(readlink "$TARGET_LINK")
  case "$previous_link" in "$GENERATIONS_ROOT"/*/controller) ;; *) fail EXISTING_CONTROLLER_LINK_INVALID ;; esac
  previous_generation=$(dirname "$previous_link")
  [[ -d "$previous_generation" && ! -L "$previous_generation" && $(owner_group_of "$previous_generation") == root:root && $(mode_of "$previous_generation") == 555 ]] || fail EXISTING_CONTROLLER_GENERATION_INVALID
  [[ -f "$previous_link" && ! -L "$previous_link" && $(owner_group_of "$previous_link") == root:root && $(mode_of "$previous_link") == 555 ]] || fail EXISTING_CONTROLLER_FILE_INVALID
  [[ -f "$previous_generation/chinaplanetos_release.py" && ! -L "$previous_generation/chinaplanetos_release.py" && $(owner_group_of "$previous_generation/chinaplanetos_release.py") == root:root && $(mode_of "$previous_generation/chinaplanetos_release.py") == 444 ]] || fail EXISTING_LIBRARY_FILE_INVALID
  previous_controller_sha=$(sha256 "$previous_link")
  previous_library_sha=$(sha256 "$previous_generation/chinaplanetos_release.py")
fi
link_stage=$(mktemp /usr/local/sbin/.chinaplanetos-release-controller.XXXXXX)
rm -f "$link_stage"
ln -s "$GENERATION_DIR/controller" "$link_stage"
mv -Tf "$link_stage" "$TARGET_LINK"
link_stage=''
link_switched=1
fsync_path /usr/local/sbin

[[ -L "$TARGET_LINK" && $(readlink "$TARGET_LINK") == "$GENERATION_DIR/controller" ]] || fail CONTROLLER_SWITCH_INVALID
validate_generation "$GENERATION_DIR" || fail ACTIVE_GENERATION_INVALID
[[ -f "$TARGET_SUDOERS" && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" && $(mode_of "$TARGET_SUDOERS") == 440 ]] || fail ACTIVE_SUDOERS_INVALID
install_complete=1
printf 'M072_CONTROLLER_INSTALL=PASS GENERATION=%s RELEASE_PROFILE_INSTALLED=NO TARGET_PROFILE_INSTALLED=NO CONTROLLER_EXECUTED=NO DEPLOY_EXECUTED=NO\n' "$GENERATION_ID"
