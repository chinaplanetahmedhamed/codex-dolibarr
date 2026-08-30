#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
test_root=$(mktemp -d /private/tmp/m068-preflight-installer-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/inbox" "$test_root/system/usr/local/sbin" "$test_root/system/etc/sudoers.d" "$test_root/bin"
cp "$root/deploy/tools/install_backup_erp_m068_preflight_helper.sh" "$test_root/install-backup-erp-m068-preflight-helper.test"
cp "$root/deploy/tools/chinaplanetos_backup_erp_m068_preflight.sh" "$test_root/inbox/chinaplanetos_backup_erp_m068_preflight.sh"
cp "$root/deploy/config/codex-deploy-backup-erp-m068-preflight.sudoers" "$test_root/inbox/codex-deploy-backup-erp-m068-preflight.sudoers"
chmod 0700 "$test_root/install-backup-erp-m068-preflight-helper.test"
installer="$test_root/install-backup-erp-m068-preflight-helper.test"
target_helper="$test_root/system/usr/local/sbin/chinaplanetos-backup-erp-m068-preflight"
target_sudoers="$test_root/system/etc/sudoers.d/codex-deploy-backup-erp-m068-preflight"
export M068_PREFLIGHT_INSTALLER_TEST_MODE=1 M068_PREFLIGHT_INSTALLER_TEST_ROOT="$test_root"
sha256() { shasum -a 256 "$1" | awk '{print $1}'; }

"$installer" | grep -Fq 'M068_PREFLIGHT_INSTALL=PASS'
cmp "$root/deploy/tools/chinaplanetos_backup_erp_m068_preflight.sh" "$target_helper"
cmp "$root/deploy/config/codex-deploy-backup-erp-m068-preflight.sudoers" "$target_sudoers"
before_helper=$(sha256 "$target_helper")
before_sudoers=$(sha256 "$target_sudoers")
[[ $(stat -f '%Lp' "$target_helper") == 755 && $(stat -f '%Lp' "$target_sudoers") == 440 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=INITIAL_METADATA' >&2; exit 1; }
"$installer" >/dev/null
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=IDEMPOTENCY' >&2; exit 1; }
[[ $(stat -f '%Lp' "$target_helper") == 755 && $(stat -f '%Lp' "$target_sudoers") == 440 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=IDEMPOTENT_METADATA' >&2; exit 1; }

if M068_PREFLIGHT_INSTALLER_TEST_FAULT=AFTER_HELPER "$installer" >/dev/null 2>&1; then echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=FAULT_AFTER_HELPER_ACCEPTED' >&2; exit 1; fi
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=ROLLBACK_AFTER_HELPER' >&2; exit 1; }
[[ $(stat -f '%Lp' "$target_helper") == 755 && $(stat -f '%Lp' "$target_sudoers") == 440 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=ROLLBACK_AFTER_HELPER_METADATA' >&2; exit 1; }
if M068_PREFLIGHT_INSTALLER_TEST_FAULT=AFTER_SUDOERS "$installer" >/dev/null 2>&1; then echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=FAULT_AFTER_SUDOERS_ACCEPTED' >&2; exit 1; fi
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=ROLLBACK_AFTER_SUDOERS' >&2; exit 1; }
[[ $(stat -f '%Lp' "$target_helper") == 755 && $(stat -f '%Lp' "$target_sudoers") == 440 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=ROLLBACK_AFTER_SUDOERS_METADATA' >&2; exit 1; }

recovery_log="$test_root/recovery.log"
if M068_PREFLIGHT_INSTALLER_TEST_FAULT=AFTER_SUDOERS M068_PREFLIGHT_INSTALLER_TEST_RECOVERY_FAULT=SUDOERS "$installer" >/dev/null 2>"$recovery_log"; then echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=RESTORE_FAILURE_ACCEPTED' >&2; exit 1; fi
grep -Fq 'RECOVERY_REQUIRED=YES' "$recovery_log" || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=RECOVERY_REQUIRED_NOT_REPORTED' >&2; exit 1; }
recovery_root=$(sed -n 's/.*RECOVERY_ROOT=//p' "$recovery_log" | tail -1)
case "$recovery_root" in "$test_root"/private.*) ;; *) echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=RECOVERY_ROOT_INVALID' >&2; exit 1;; esac
[[ -d "$recovery_root" && -f "$recovery_root/old-sudoers" ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=RECOVERY_BACKUP_NOT_RETAINED' >&2; exit 1; }
[[ $(sha256 "$recovery_root/old-sudoers") == "$before_sudoers" && $(stat -f '%Lp' "$recovery_root/old-sudoers") == 440 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=RECOVERY_BACKUP_INVALID' >&2; exit 1; }
rm -rf "$recovery_root"

git -C "$root" show 'fdbb5ca443b942f37da2b545ec3ba2b4cb492ed3:deploy/tools/chinaplanetos_backup_erp_m068_preflight.sh' >"$target_helper"
chmod 0644 "$target_sudoers"
git -C "$root" show 'fdbb5ca443b942f37da2b545ec3ba2b4cb492ed3:deploy/config/codex-deploy-backup-erp-m068-preflight.sudoers' >"$target_sudoers"
chmod 0755 "$target_helper"
chmod 0440 "$target_sudoers"
[[ $(sha256 "$target_helper") == e340e79ce56e88ad27f066788a85f67aa55a1cc2eef2f247390d1eb83ffa3256 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=PREDECESSOR_HELPER_FIXTURE' >&2; exit 1; }
[[ $(sha256 "$target_sudoers") == d939e20162ec13fa7a125bbd9ec40128a05615444140ef920aa0fcae8e27e018 ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=PREDECESSOR_SUDOERS_FIXTURE' >&2; exit 1; }
"$installer" >/dev/null
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=PINNED_PREDECESSOR_UPGRADE' >&2; exit 1; }

printf '\ncorrupt\n' >>"$test_root/inbox/chinaplanetos_backup_erp_m068_preflight.sh"
if "$installer" >/dev/null 2>&1; then echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=CORRUPT_SOURCE_ACCEPTED' >&2; exit 1; fi
[[ $(sha256 "$target_helper") == "$before_helper" && $(sha256 "$target_sudoers") == "$before_sudoers" ]] || { echo 'M068_PREFLIGHT_INSTALLER=FAIL REASON=SOURCE_FAILURE_MUTATION' >&2; exit 1; }

echo 'M068_PREFLIGHT_INSTALLER=PASS TRANSACTION_ROLLBACK=PASS RECOVERY_BACKUP_RETAINED=PASS IDEMPOTENT=PASS PINNED_PREDECESSOR_UPGRADE=PASS CHECKSUM_PINNED=PASS HELPER_EXECUTED=NO SERVER_ACCESSED=NO PRODUCTION_ACCESSED=NO'
