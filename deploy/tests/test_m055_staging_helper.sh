#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m055_customer_replies.sh"
installer="$root/deploy/tools/install_backup_erp_m055_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m055.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RELEASE='M055'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='991011e8db9a95f0fa98ce5d518d09fca551f4bc'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='983541e2a3f4f0200ba1aeab891efc68226dfe0a34c1f9672b0f4e036a6c3a68'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq "readonly STATE_ROOT='/var/lib/chinaplanetos-m055-staging'" "$helper"
grep -Fq 'FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO' "$helper"
grep -Fq "readonly HELPER_SHA='bfa26e83b5260e83106715961fcd01d68e5e32dffe0554e0a35cc1667413a01c'" "$installer"
grep -Fq "readonly SUDOERS_SHA='cb6c8a05f5102d2f3bc5c2b464d0e916d6846d90bfa3a1c37c96c666683d2a0f'" "$installer"
[[ $(sha256sum "$helper" | awk '{print $1}') == 'bfa26e83b5260e83106715961fcd01d68e5e32dffe0554e0a35cc1667413a01c' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == 'cb6c8a05f5102d2f3bc5c2b464d0e916d6846d90bfa3a1c37c96c666683d2a0f' ]]

if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper" "$installer"; then
  echo 'M055_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2
  exit 1
fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then
  echo 'M055_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2
  exit 1
fi

[[ $(wc -l < "$root/deploy/releases/m055-runtime-files.txt" | tr -d ' ') -eq 6 ]]
[[ $(wc -l < "$root/deploy/releases/m055-support-files.txt" | tr -d ' ') -eq 2 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m055-customer-replies $command" "$sudoers"
done

echo 'M055_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO'
