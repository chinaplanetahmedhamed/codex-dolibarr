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
grep -Fq "readonly RELEASE='M043-C02'" "$helper" || fail 'RELEASE_NOT_PINNED'
grep -Fq "readonly ARTIFACT_SHA='3ab56995901ed5f81c5e301b0a2fcf7ba60948ff78778675a23ab23473b4eb8c'" "$helper" || fail 'ARTIFACT_NOT_PINNED'
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
grep -Fq "readonly HELPER_SHA='e4bf79eb101bcbec97ec71a40ac61b9e9bf4f705899c5910b0ffb6894ae97a68'" "$installer" || fail 'INSTALLER_HELPER_HASH_INVALID'
grep -Fq "readonly PREVIOUS_HELPER_SHA='43603225e4ed7bdcda8720dd78e757fe61c64886c803d845aa8d9c02ff979741'" "$installer" || fail 'INSTALLER_PREVIOUS_HELPER_HASH_INVALID'
grep -Fq "readonly SUDOERS_SHA='7f14c3cdb269d29de15923ff5adb40cebe6751ad4f43fbc24acc4000863d605a'" "$installer" || fail 'INSTALLER_SUDOERS_HASH_INVALID'
[[ $(sha256sum "$helper" | awk '{print $1}') == 'e4bf79eb101bcbec97ec71a40ac61b9e9bf4f705899c5910b0ffb6894ae97a68' ]] || fail 'HELPER_HASH_DRIFT'
[[ $(sha256sum "$sudoers" | awk '{print $1}') == '7f14c3cdb269d29de15923ff5adb40cebe6751ad4f43fbc24acc4000863d605a' ]] || fail 'SUDOERS_HASH_DRIFT'
echo 'M043_HELPER_SOURCE=PASS TARGET=CANONICAL_STAGING SUDOERS=EXACT BACKUP=CODE_AND_DB ROLLBACK=AUTO'
