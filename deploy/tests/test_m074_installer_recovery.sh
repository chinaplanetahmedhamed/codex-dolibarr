#!/bin/bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
installer_source="$repo_root/deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh"
helper_source="$repo_root/deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh"
sudoers_source="$repo_root/deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers"
test_root=$(mktemp -d "${TMPDIR:-/tmp}/m074-installer-recovery.XXXXXX")
trap 'rm -rf "$test_root"' EXIT

fail() { echo "M074_INSTALLER_RECOVERY_TEST=FAIL REASON=$1" >&2; exit 1; }
sha256() { shasum -a 256 "$1" | awk '{print $1}'; }

mkdir -p "$test_root/source" "$test_root/target" "$test_root/bin"
chmod 0700 "$test_root/source"
cp "$helper_source" "$test_root/source/chinaplanetos_backup_erp_m074_qc_preflight.sh"
cp "$sudoers_source" "$test_root/source/codex-deploy-backup-erp-m074-qc-preflight.sudoers"
chmod 0644 "$test_root/source/"*

printf '#!/bin/bash\necho old-helper\n' > "$test_root/old-helper"
printf 'old-sudoers\n' > "$test_root/old-sudoers"
old_helper_sha=$(sha256 "$test_root/old-helper")
old_sudoers_sha=$(sha256 "$test_root/old-sudoers")

cat > "$test_root/bin/id" <<'EOF'
#!/bin/bash
[[ ${1:-} == -u ]] && { echo 0; exit 0; }
exec /usr/bin/id "$@"
EOF
cat > "$test_root/bin/stat" <<'EOF'
#!/bin/bash
[[ ${1:-} == -c && $# -eq 3 ]] || exit 90
format=$2
path=$3
case "$format" in
  '%h') /usr/bin/stat -f '%l' "$path" ;;
  '%U:%G %a')
    mode=$(/usr/bin/stat -f '%Lp' "$path")
    if [[ $path == "$M074_TEST_ROOT/source" || $path == "$M074_TEST_ROOT/source/"* ]]; then
      echo "codex-deploy:codex-deploy $mode"
    else
      echo "root:root $mode"
    fi
    ;;
  *) exit 91 ;;
esac
EOF
cat > "$test_root/bin/install" <<'EOF'
#!/bin/bash
mode=''
while [[ $# -gt 0 ]]; do
  case "$1" in
    -o|-g) shift 2 ;;
    -m) mode=$2; shift 2 ;;
    *) break ;;
  esac
done
[[ $# -eq 2 && -n $mode ]] || exit 92
source=$1
target=$2
if [[ ${M074_TEST_FAIL_RESTORE_SUDOERS:-0} == 1 && $source == */previous-sudoers && $target == *.restore.* ]]; then
  exit 93
fi
/bin/cp "$source" "$target" && /bin/chmod "$mode" "$target"
EOF
cat > "$test_root/bin/sha256sum" <<'EOF'
#!/bin/bash
/usr/bin/shasum -a 256 "$@"
EOF
cat > "$test_root/bin/visudo" <<'EOF'
#!/bin/bash
exit 0
EOF
chmod 0755 "$test_root/bin/"*

escaped_root=${test_root//\//\\/}
sed \
  -e "s#/home/codex-deploy/m074-qc-preflight-install#$escaped_root/source#g" \
  -e "s#/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight#$escaped_root/target/helper#g" \
  -e "s#/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight#$escaped_root/target/sudoers#g" \
  -e "s#/root/m074-helper-install\.#$escaped_root/recovery.#g" \
  -e "s#PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin#PATH=$escaped_root/bin:/usr/bin:/bin#" \
  -e "s#PREVIOUS_HELPER_SHA='[0-9a-f]*'#PREVIOUS_HELPER_SHA='$old_helper_sha'#" \
  -e "s#PREVIOUS_SUDOERS_SHA='[0-9a-f]*'#PREVIOUS_SUDOERS_SHA='$old_sudoers_sha'#" \
  "$installer_source" > "$test_root/installer"
chmod 0755 "$test_root/installer"

reset_old_pair() {
  rm -f "$test_root/target/helper" "$test_root/target/sudoers"
  cp "$test_root/old-helper" "$test_root/target/helper"
  cp "$test_root/old-sudoers" "$test_root/target/sudoers"
  chmod 0755 "$test_root/target/helper"
  chmod 0440 "$test_root/target/sudoers"
}

reset_old_pair
set +e
output=$(M074_TEST_ROOT="$test_root" M074_INSTALL_FAULT_TEST=AFTER_HELPER_PUBLISH "$test_root/installer" 2>&1)
rc=$?
set -e
[[ $rc -ne 0 ]] || fail 'FAULT_DID_NOT_ABORT'
[[ $output == *'FAULT_TEST_AFTER_HELPER_PUBLISH'* ]] || fail 'FAULT_MARKER_MISSING'
[[ $output != *'AUTOMATIC_RESTORE_FAILED'* ]] || fail 'SUCCESSFUL_RESTORE_REPORTED_FAILED'
[[ $(sha256 "$test_root/target/helper") == "$old_helper_sha" ]] || fail 'HELPER_NOT_RESTORED'
[[ $(sha256 "$test_root/target/sudoers") == "$old_sudoers_sha" ]] || fail 'SUDOERS_NOT_RESTORED'
[[ $(/usr/bin/stat -f '%Lp' "$test_root/target/helper") == 755 ]] || fail 'HELPER_MODE_NOT_RESTORED'
[[ $(/usr/bin/stat -f '%Lp' "$test_root/target/sudoers") == 440 ]] || fail 'SUDOERS_MODE_NOT_RESTORED'
if compgen -G "$test_root/recovery.*" >/dev/null; then fail 'RECOVERY_BYTES_NOT_CLEANED_AFTER_SUCCESS'; fi

reset_old_pair
set +e
output=$(M074_TEST_ROOT="$test_root" M074_INSTALL_FAULT_TEST=AFTER_HELPER_PUBLISH M074_TEST_FAIL_RESTORE_SUDOERS=1 "$test_root/installer" 2>&1)
rc=$?
set -e
[[ $rc -eq 70 ]] || fail 'RESTORE_FAILURE_EXIT_CODE'
[[ $output == *'AUTOMATIC_RESTORE_FAILED RECOVERY_REQUIRED=YES'* ]] || fail 'RESTORE_FAILURE_SIGNAL_MISSING'
recovery_count=$(find "$test_root" -maxdepth 1 -type d -name 'recovery.*' | wc -l | tr -d ' ')
[[ $recovery_count -eq 1 ]] || fail 'RECOVERY_BYTES_NOT_RETAINED'
recovery_root=$(find "$test_root" -maxdepth 1 -type d -name 'recovery.*' -print -quit)
[[ $(sha256 "$recovery_root/previous-helper") == "$old_helper_sha" ]] || fail 'RETAINED_HELPER_INVALID'
[[ $(sha256 "$recovery_root/previous-sudoers") == "$old_sudoers_sha" ]] || fail 'RETAINED_SUDOERS_INVALID'

echo 'M074_INSTALLER_RECOVERY_TEST=PASS PARTIAL_PUBLISH=RESTORED RESTORE_FAILURE=FAIL_CLOSED RECOVERY_BYTES=RETAINED'
