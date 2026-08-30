#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
test_root=$(mktemp -d /private/tmp/m068-c01-hmac-installer-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/inbox" "$test_root/system/usr/local/sbin" "$test_root/system/usr/local/libexec" "$test_root/system/etc/sudoers.d" "$test_root/bin"
real_php=$(command -v php)
cat >"$test_root/bin/php" <<'SH'
#!/usr/bin/env bash
echo 'M068_C01_INSTALLER_TEST=FAIL REASON=HOST_PHP_USED' >&2
exit 97
SH
cat >"$test_root/bin/docker" <<'SH'
#!/usr/bin/env bash
set -eu
case "${1:-}" in
  inspect)
    [[ "${2:-}" == --format && "${3:-}" == '{{.State.Running}}' && "${4:-}" == 'erp-staging-dolibarr-1' ]] || exit 91
    printf 'INSPECT_CONTAINER=%s\n' "$4" >>"$M068_C01_INSTALLER_TEST_DOCKER_LOG"
    [[ ${M068_C01_INSTALLER_TEST_DOCKER_INSPECT:-PASS} == PASS ]] || exit 92
    printf 'true\n'
    ;;
  exec)
    [[ "${2:-}" == -i && "${3:-}" == 'erp-staging-dolibarr-1' && "${4:-}" == php && "${5:-}" == -l && "${6:-}" == /dev/stdin && $# -eq 6 ]] || exit 93
    printf 'EXEC_CONTAINER=%s COMMAND=%s %s %s\n' "$3" "$4" "$5" "$6" >>"$M068_C01_INSTALLER_TEST_DOCKER_LOG"
    case ${M068_C01_INSTALLER_TEST_CONFIG_LINT:-PASS} in
      PASS) exec "$M068_C01_INSTALLER_TEST_REAL_PHP" -l /dev/stdin ;;
      SYNTAX) exit 255 ;;
      RUNTIME) exit 125 ;;
      *) exit 94 ;;
    esac
    ;;
  *) exit 95 ;;
esac
SH
chmod 0700 "$test_root/bin/php" "$test_root/bin/docker"
cp "$root/deploy/tools/install_backup_erp_m068_c01_hmac_helper.sh" "$test_root/install-backup-erp-m068-c01-hmac-helper.test"
cp "$root/deploy/tools/chinaplanetos_backup_erp_m068_c01_hmac.sh" "$test_root/inbox/chinaplanetos_backup_erp_m068_c01_hmac.sh"
cp "$root/deploy/tools/m068_c01_portal_hmac_config.php" "$test_root/inbox/m068_c01_portal_hmac_config.php"
cp "$root/deploy/config/codex-deploy-backup-erp-m068-c01-hmac.sudoers" "$test_root/inbox/codex-deploy-backup-erp-m068-c01-hmac.sudoers"
chmod 0700 "$test_root/install-backup-erp-m068-c01-hmac-helper.test"

installer="$test_root/install-backup-erp-m068-c01-hmac-helper.test"
target_helper="$test_root/system/usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac"
target_config="$test_root/system/usr/local/libexec/chinaplanetos-m068-c01/m068_c01_portal_hmac_config.php"
target_sudoers="$test_root/system/etc/sudoers.d/codex-deploy-backup-erp-m068-c01-hmac"
export M068_C01_INSTALLER_TEST_MODE=1 M068_C01_INSTALLER_TEST_ROOT="$test_root"
export M068_C01_INSTALLER_TEST_REAL_PHP="$real_php" M068_C01_INSTALLER_TEST_DOCKER_LOG="$test_root/docker.log"
sha256() { shasum -a 256 "$1" | awk '{print $1}'; }

runtime_log="$test_root/runtime-failure.log"
if M068_C01_INSTALLER_TEST_DOCKER_INSPECT=FAIL "$installer" >"$runtime_log" 2>&1; then echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RUNTIME_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fq 'M068_C01_INSTALL=FAIL REASON=CONFIG_RUNTIME_INVALID' "$runtime_log"
[[ ! -e "$target_helper" && ! -e "$target_config" && ! -e "$target_sudoers" ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RUNTIME_FAILURE_MUTATION' >&2; exit 1; }

exec_runtime_log="$test_root/exec-runtime-failure.log"
if M068_C01_INSTALLER_TEST_CONFIG_LINT=RUNTIME "$installer" >"$exec_runtime_log" 2>&1; then echo 'M068_C01_INSTALLER_TEST=FAIL REASON=EXEC_RUNTIME_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fq 'M068_C01_INSTALL=FAIL REASON=CONFIG_RUNTIME_INVALID' "$exec_runtime_log"
[[ ! -e "$target_helper" && ! -e "$target_config" && ! -e "$target_sudoers" ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=EXEC_RUNTIME_FAILURE_MUTATION' >&2; exit 1; }

lint_log="$test_root/lint-failure.log"
if M068_C01_INSTALLER_TEST_CONFIG_LINT=SYNTAX "$installer" >"$lint_log" 2>&1; then echo 'M068_C01_INSTALLER_TEST=FAIL REASON=LINT_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fq 'M068_C01_INSTALL=FAIL REASON=CONFIG_SYNTAX_INVALID' "$lint_log"
[[ ! -e "$target_helper" && ! -e "$target_config" && ! -e "$target_sudoers" ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=LINT_FAILURE_MUTATION' >&2; exit 1; }

install_output=$($installer)
grep -Fq 'M068_C01_INSTALL=PASS' <<<"$install_output"
grep -Fq 'HELPER_EXECUTED=NO' <<<"$install_output"
grep -Fq 'CONFIG_LINT=CONTAINER_PHP' <<<"$install_output"
grep -Fq 'TARGET_CONTAINER=erp-staging-dolibarr-1' <<<"$install_output"
grep -Fq 'INSPECT_CONTAINER=erp-staging-dolibarr-1' "$M068_C01_INSTALLER_TEST_DOCKER_LOG"
grep -Fq 'EXEC_CONTAINER=erp-staging-dolibarr-1 COMMAND=php -l /dev/stdin' "$M068_C01_INSTALLER_TEST_DOCKER_LOG"
cmp "$root/deploy/tools/chinaplanetos_backup_erp_m068_c01_hmac.sh" "$target_helper"
cmp "$root/deploy/tools/m068_c01_portal_hmac_config.php" "$target_config"
cmp "$root/deploy/config/codex-deploy-backup-erp-m068-c01-hmac.sudoers" "$target_sudoers"
[[ $(stat -f '%Lp' "$target_helper") == 755 && $(stat -f '%Lp' "$target_config") == 644 && $(stat -f '%Lp' "$target_sudoers") == 440 ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=INITIAL_METADATA' >&2; exit 1; }

before_helper=$(sha256 "$target_helper")
before_config=$(sha256 "$target_config")
before_sudoers=$(sha256 "$target_sudoers")
$installer >/dev/null
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_config") == "$before_config" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=IDEMPOTENCY' >&2; exit 1; }

for fault in AFTER_HELPER AFTER_CONFIG AFTER_SUDOERS; do
  if M068_C01_INSTALLER_TEST_FAULT="$fault" "$installer" >/dev/null 2>&1; then echo "M068_C01_INSTALLER_TEST=FAIL REASON=${fault}_ACCEPTED" >&2; exit 1; fi
  [[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_config") == "$before_config" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo "M068_C01_INSTALLER_TEST=FAIL REASON=${fault}_ROLLBACK" >&2; exit 1; }
  [[ $(stat -f '%Lp' "$target_helper") == 755 && $(stat -f '%Lp' "$target_config") == 644 && $(stat -f '%Lp' "$target_sudoers") == 440 ]] || { echo "M068_C01_INSTALLER_TEST=FAIL REASON=${fault}_METADATA" >&2; exit 1; }
done

cp "$test_root/inbox/chinaplanetos_backup_erp_m068_c01_hmac.sh" "$test_root/good-helper"
printf '\ncorrupt\n' >>"$test_root/inbox/chinaplanetos_backup_erp_m068_c01_hmac.sh"
if "$installer" >/dev/null 2>&1; then echo 'M068_C01_INSTALLER_TEST=FAIL REASON=CORRUPT_SOURCE_ACCEPTED' >&2; exit 1; fi
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_config") == "$before_config" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=SOURCE_FAILURE_MUTATION' >&2; exit 1; }
mv "$test_root/good-helper" "$test_root/inbox/chinaplanetos_backup_erp_m068_c01_hmac.sh"

recovery_log="$test_root/recovery.log"
if M068_C01_INSTALLER_TEST_FAULT=AFTER_SUDOERS M068_C01_INSTALLER_TEST_RECOVERY_FAULT=SUDOERS "$installer" >/dev/null 2>"$recovery_log"; then echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RESTORE_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fq 'RECOVERY_REQUIRED=YES' "$recovery_log" || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RECOVERY_REQUIRED_NOT_REPORTED' >&2; exit 1; }
recovery_root=$(sed -n 's/.*RECOVERY_ROOT=//p' "$recovery_log" | tail -1)
case "$recovery_root" in "$test_root"/private.*) ;; *) echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RECOVERY_ROOT_INVALID' >&2; exit 1;; esac
[[ -d "$recovery_root" && -f "$recovery_root/old-sudoers" ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RECOVERY_BACKUP_NOT_RETAINED' >&2; exit 1; }
[[ $(sha256 "$recovery_root/old-sudoers") == "$before_sudoers" && $(stat -f '%Lp' "$recovery_root/old-sudoers") == 440 ]] || { echo 'M068_C01_INSTALLER_TEST=FAIL REASON=RECOVERY_BACKUP_INVALID' >&2; exit 1; }

echo 'M068_C01_INSTALLER_TEST=PASS FILES=3 CHECKSUM_PINNED=YES TRANSACTION_ROLLBACK=PASS CONFIG_DIRECTORY=BOUNDED RECOVERY_BACKUP_RETAINED=PASS IDEMPOTENT=PASS CONFIG_LINT=CONTAINER_PHP HOST_PHP_USED=NO INSPECT_RUNTIME_FAILURE=FAIL_CLOSED EXEC_RUNTIME_FAILURE=FAIL_CLOSED LINT_FAILURE=FAIL_CLOSED HELPER_EXECUTED=NO SERVER_ACCESSED=NO PRODUCTION_ACCESSED=NO'
