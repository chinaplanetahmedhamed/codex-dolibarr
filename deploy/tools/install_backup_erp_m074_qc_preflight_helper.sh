#!/bin/bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly SOURCE_ROOT='/home/codex-deploy/m074-qc-preflight-install'
readonly SOURCE_HELPER="${SOURCE_ROOT}/chinaplanetos_backup_erp_m074_qc_preflight.sh"
readonly SOURCE_SUDOERS="${SOURCE_ROOT}/codex-deploy-backup-erp-m074-qc-preflight.sudoers"
readonly HELPER_SHA='bc168d10991c53996567c5a4048f2003ca81cc40f651cee94df17f1298692753'
readonly SUDOERS_SHA='3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99'
readonly PREVIOUS_HELPER_SHA='cc653f486f5d637c93b04d8d4c8585017ca4157304c42de18b350c2d3d2cd361'
readonly PREVIOUS_SUDOERS_SHA='3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99'
readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight'
readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight'

die() { echo "M074_HELPER_INSTALL=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

private_root=''
temporary_helper=''
temporary_sudoers=''
previous_state='unknown'
published=0
recovery_retained=0

cleanup() {
  local rc=$?
  local restore_ok=1
  local restore_helper=''
  local restore_sudoers=''
  trap - EXIT
  set +e
  if [[ $rc -ne 0 && $published -eq 0 ]]; then
    if [[ $previous_state == old && -n $private_root ]]; then
      restore_helper="${TARGET_HELPER}.restore.$$"
      restore_sudoers="${TARGET_SUDOERS}.restore.$$"
      [[ ! -e $restore_helper && ! -L $restore_helper && ! -e $restore_sudoers && ! -L $restore_sudoers ]] || restore_ok=0
      [[ $restore_ok -eq 0 ]] || install -o root -g root -m 0755 "${private_root}/previous-helper" "$restore_helper" || restore_ok=0
      [[ $restore_ok -eq 0 ]] || install -o root -g root -m 0440 "${private_root}/previous-sudoers" "$restore_sudoers" || restore_ok=0
      [[ $restore_ok -eq 0 ]] || [[ -f $restore_helper && ! -L $restore_helper && $(stat -c '%U:%G %a' "$restore_helper") == 'root:root 755' && $(sha256 "$restore_helper") == "$PREVIOUS_HELPER_SHA" ]] || restore_ok=0
      [[ $restore_ok -eq 0 ]] || [[ -f $restore_sudoers && ! -L $restore_sudoers && $(stat -c '%U:%G %a' "$restore_sudoers") == 'root:root 440' && $(sha256 "$restore_sudoers") == "$PREVIOUS_SUDOERS_SHA" ]] || restore_ok=0
      [[ $restore_ok -eq 0 ]] || visudo -cf "$restore_sudoers" >/dev/null 2>&1 || restore_ok=0
      if [[ $restore_ok -eq 1 ]]; then
        mv -f "$restore_sudoers" "$TARGET_SUDOERS" || restore_ok=0
        [[ $restore_ok -eq 0 ]] || mv -f "$restore_helper" "$TARGET_HELPER" || restore_ok=0
      fi
      [[ $restore_ok -eq 0 ]] || visudo -cf /etc/sudoers >/dev/null 2>&1 || restore_ok=0
      [[ $restore_ok -eq 0 ]] || [[ -f $TARGET_HELPER && ! -L $TARGET_HELPER && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$PREVIOUS_HELPER_SHA" ]] || restore_ok=0
      [[ $restore_ok -eq 0 ]] || [[ -f $TARGET_SUDOERS && ! -L $TARGET_SUDOERS && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$PREVIOUS_SUDOERS_SHA" ]] || restore_ok=0
    elif [[ $previous_state == absent ]]; then
      if [[ -e $TARGET_SUDOERS || -L $TARGET_SUDOERS ]]; then
        [[ -f $TARGET_SUDOERS && ! -L $TARGET_SUDOERS && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || restore_ok=0
        [[ $restore_ok -eq 0 ]] || rm -f "$TARGET_SUDOERS" || restore_ok=0
      fi
      if [[ -e $TARGET_HELPER || -L $TARGET_HELPER ]]; then
        [[ -f $TARGET_HELPER && ! -L $TARGET_HELPER && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || restore_ok=0
        [[ $restore_ok -eq 0 ]] || rm -f "$TARGET_HELPER" || restore_ok=0
      fi
      [[ $restore_ok -eq 0 ]] || [[ ! -e $TARGET_HELPER && ! -L $TARGET_HELPER && ! -e $TARGET_SUDOERS && ! -L $TARGET_SUDOERS ]] || restore_ok=0
    fi
    if [[ $restore_ok -ne 1 ]]; then
      recovery_retained=1
      rc=70
      echo "M074_HELPER_INSTALL=FAIL REASON=AUTOMATIC_RESTORE_FAILED RECOVERY_REQUIRED=YES RECOVERY_ROOT=$private_root" >&2
    fi
  fi
  if [[ -n $restore_helper && $restore_helper == "${TARGET_HELPER}.restore."* && -f $restore_helper && ! -L $restore_helper ]]; then rm -f "$restore_helper"; fi
  if [[ -n $restore_sudoers && $restore_sudoers == "${TARGET_SUDOERS}.restore."* && -f $restore_sudoers && ! -L $restore_sudoers ]]; then rm -f "$restore_sudoers"; fi
  if [[ -n $temporary_helper && $temporary_helper == "${TARGET_HELPER}.tmp."* && -f $temporary_helper && ! -L $temporary_helper ]]; then rm -f "$temporary_helper"; fi
  if [[ -n $temporary_sudoers && $temporary_sudoers == "${TARGET_SUDOERS}.tmp."* && -f $temporary_sudoers && ! -L $temporary_sudoers ]]; then rm -f "$temporary_sudoers"; fi
  if [[ $recovery_retained -eq 0 && -n $private_root && $private_root == /root/m074-helper-install.* && -d $private_root ]]; then rm -rf "$private_root"; fi
  exit "$rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ $# -eq 0 ]] || die 'NO_ARGUMENTS_ALLOWED'
[[ -d $SOURCE_ROOT && ! -L $SOURCE_ROOT && $(stat -c '%U:%G %a' "$SOURCE_ROOT") == 'codex-deploy:codex-deploy 700' ]] || die 'SOURCE_ROOT_INVALID'

private_root=$(mktemp -d /root/m074-helper-install.XXXXXX)
private_helper="${private_root}/helper"
private_sudoers="${private_root}/sudoers"

for source in "$SOURCE_HELPER" "$SOURCE_SUDOERS"; do
  [[ -f $source && ! -L $source ]] || die 'SOURCE_INVALID'
  [[ $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die 'SOURCE_METADATA_INVALID'
  [[ $(stat -c '%h' "$source") == 1 ]] || die 'SOURCE_HARDLINK_REJECTED'
done

install -o root -g root -m 0600 "$SOURCE_HELPER" "$private_helper"
install -o root -g root -m 0600 "$SOURCE_SUDOERS" "$private_sudoers"
[[ $(sha256 "$private_helper") == "$HELPER_SHA" ]] || die 'HELPER_HASH_MISMATCH'
[[ $(sha256 "$private_sudoers") == "$SUDOERS_SHA" ]] || die 'SUDOERS_HASH_MISMATCH'
/usr/bin/python3 -I -c 'import ast,pathlib,sys; ast.parse(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"))' "$private_helper" || die 'HELPER_SYNTAX_INVALID'
visudo -cf "$private_sudoers" >/dev/null || die 'SUDOERS_PARSE_FAILED'
visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_PRECHECK_FAILED'

helper_exists=0; sudoers_exists=0
[[ -e $TARGET_HELPER || -L $TARGET_HELPER ]] && helper_exists=1
[[ -e $TARGET_SUDOERS || -L $TARGET_SUDOERS ]] && sudoers_exists=1
[[ $helper_exists -eq $sudoers_exists ]] || die 'EXISTING_PAIR_INCOMPLETE'

if [[ $helper_exists -eq 0 ]]; then
  previous_state='absent'
else
  [[ -f $TARGET_HELPER && ! -L $TARGET_HELPER && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' ]] || die 'EXISTING_HELPER_INVALID'
  [[ -f $TARGET_SUDOERS && ! -L $TARGET_SUDOERS && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' ]] || die 'EXISTING_SUDOERS_INVALID'
  current_helper_sha=$(sha256 "$TARGET_HELPER")
  current_sudoers_sha=$(sha256 "$TARGET_SUDOERS")
  if [[ $current_helper_sha == "$HELPER_SHA" && $current_sudoers_sha == "$SUDOERS_SHA" ]]; then
    previous_state='new'
  elif [[ $current_helper_sha == "$PREVIOUS_HELPER_SHA" && $current_sudoers_sha == "$PREVIOUS_SUDOERS_SHA" ]]; then
    previous_state='old'
    install -o root -g root -m 0600 "$TARGET_HELPER" "${private_root}/previous-helper"
    install -o root -g root -m 0600 "$TARGET_SUDOERS" "${private_root}/previous-sudoers"
  else
    die 'EXISTING_PAIR_HASH_UNEXPECTED'
  fi
fi

if [[ $previous_state != new ]]; then
  temporary_helper="${TARGET_HELPER}.tmp.$$"
  temporary_sudoers="${TARGET_SUDOERS}.tmp.$$"
  [[ ! -e $temporary_helper && ! -L $temporary_helper && ! -e $temporary_sudoers && ! -L $temporary_sudoers ]] || die 'TEMPORARY_TARGET_COLLISION'
  install -o root -g root -m 0755 "$private_helper" "$temporary_helper"
  install -o root -g root -m 0440 "$private_sudoers" "$temporary_sudoers"
  [[ $(sha256 "$temporary_helper") == "$HELPER_SHA" ]] || die 'STAGED_HELPER_HASH_INVALID'
  [[ $(sha256 "$temporary_sudoers") == "$SUDOERS_SHA" ]] || die 'STAGED_SUDOERS_HASH_INVALID'
  visudo -cf "$temporary_sudoers" >/dev/null || die 'STAGED_SUDOERS_PARSE_FAILED'
  mv -f "$temporary_helper" "$TARGET_HELPER"; temporary_helper=''
  [[ ${M074_INSTALL_FAULT_TEST:-} != 'AFTER_HELPER_PUBLISH' ]] || die 'FAULT_TEST_AFTER_HELPER_PUBLISH'
  mv -f "$temporary_sudoers" "$TARGET_SUDOERS"; temporary_sudoers=''
fi

visudo -cf /etc/sudoers >/dev/null || die 'GLOBAL_SUDOERS_POSTCHECK_FAILED'
[[ -f $TARGET_HELPER && ! -L $TARGET_HELPER && $(stat -c '%U:%G %a' "$TARGET_HELPER") == 'root:root 755' && $(sha256 "$TARGET_HELPER") == "$HELPER_SHA" ]] || die 'HELPER_POSTCHECK_FAILED'
[[ -f $TARGET_SUDOERS && ! -L $TARGET_SUDOERS && $(stat -c '%U:%G %a' "$TARGET_SUDOERS") == 'root:root 440' && $(sha256 "$TARGET_SUDOERS") == "$SUDOERS_SHA" ]] || die 'SUDOERS_POSTCHECK_FAILED'
published=1
echo "M074_HELPER_INSTALL=PASS HELPER_SHA=$HELPER_SHA SUDOERS_SHA=$SUDOERS_SHA COMMANDS=compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE=YES DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
