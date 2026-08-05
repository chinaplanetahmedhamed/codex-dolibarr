#!/usr/bin/env bash
set -Eeuo pipefail
root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
helper="${root}/deploy/tools/chinaplanetos_backup_erp_m043.sh"
installer="${root}/deploy/tools/install_backup_erp_m043_helper.sh"
sudoers="${root}/deploy/config/codex-deploy-backup-erp-m043.sudoers"

fail() { echo "M043_HELPER_SOURCE=FAIL REASON=$1" >&2; exit 1; }
for file in "$helper" "$installer" "$sudoers"; do [[ -f "$file" && ! -L "$file" ]] || fail 'FILE_INVALID'; done
bash -n "$helper"; bash -n "$installer"

grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper" || fail 'ERP_TARGET_MISSING'
grep -Fq "readonly DB_CONTAINER='erp-staging-mariadb-1'" "$helper" || fail 'DB_TARGET_MISSING'
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper" || fail 'VOLUME_TARGET_MISSING'
grep -Fq "readonly ARTIFACT_SHA='bc6c08becab5d825d61daad9433d8f61a5f90fbc710ea952c70229b84045b379'" "$helper" || fail 'ARTIFACT_NOT_PINNED'
grep -Fq 'validate_secret_files' "$helper" || fail 'SECRET_VALIDATION_MISSING'
grep -Fq 'create_database_backup' "$helper" || fail 'DATABASE_BACKUP_MISSING'
grep -Fq 'M043_STAGING_AUTO_ROLLBACK=PASS' "$helper" || fail 'AUTO_ROLLBACK_MISSING'
grep -Fq 'SCHEMA_RETAINED=DORMANT' "$helper" || fail 'NONDESTRUCTIVE_MANUAL_ROLLBACK_MISSING'

for forbidden in vision_erp vision_db vision-os erp.visiontrading.biz codex_staging_ /opt/codex-dolibarr-staging; do
  ! grep -Fq "$forbidden" "$helper" || fail 'FORBIDDEN_TARGET_PRESENT'
done

expected=''
for command in preflight apply verify rollback status; do
  line="codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m043-deploy ${command}"
  [[ $(grep -Fxc "$line" "$sudoers") -eq 1 ]] || fail "SUDOERS_${command}_INVALID"
  expected+="${line}"$'\n'
done
[[ $(wc -l <"$sudoers") -eq 5 ]] || fail 'SUDOERS_COMMAND_COUNT_INVALID'
grep -Fq "readonly HELPER_SHA='0ae0d43bc29edf52a4466315e9654afaf12cdc808da4050a8da17a3eb6b69867'" "$installer" || fail 'INSTALLER_HELPER_HASH_INVALID'
grep -Fq "readonly PREVIOUS_HELPER_SHA='503491ed45bc3df3bb8ba9ccd38f0a71527af8c1e201c3ae67fc40bcf83dabf9'" "$installer" || fail 'INSTALLER_PREVIOUS_HELPER_HASH_INVALID'
grep -Fq "readonly SUDOERS_SHA='7f14c3cdb269d29de15923ff5adb40cebe6751ad4f43fbc24acc4000863d605a'" "$installer" || fail 'INSTALLER_SUDOERS_HASH_INVALID'
[[ $(sha256sum "$helper" | awk '{print $1}') == '0ae0d43bc29edf52a4466315e9654afaf12cdc808da4050a8da17a3eb6b69867' ]] || fail 'HELPER_HASH_DRIFT'
[[ $(sha256sum "$sudoers" | awk '{print $1}') == '7f14c3cdb269d29de15923ff5adb40cebe6751ad4f43fbc24acc4000863d605a' ]] || fail 'SUDOERS_HASH_DRIFT'
echo 'M043_HELPER_SOURCE=PASS TARGET=CANONICAL_STAGING SUDOERS=EXACT BACKUP=CODE_AND_DB ROLLBACK=AUTO'
