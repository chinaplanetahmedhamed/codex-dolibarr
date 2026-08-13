#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_production_m055_customer_replies.sh"
installer="$root/deploy/tools/install_production_m055_helper.sh"
sudoers="$root/deploy/config/codex-deploy-production-m055.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RELEASE='M055-P01'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='991011e8db9a95f0fa98ce5d518d09fca551f4bc'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='983541e2a3f4f0200ba1aeab891efc68226dfe0a34c1f9672b0f4e036a6c3a68'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper"
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper"
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper"
grep -Fq "readonly STATE_ROOT='/var/lib/chinaplanetos-m055-production'" "$helper"
grep -Fq 'FILES=6 DATABASE_CHANGED=NO' "$helper"
grep -Fq 'M055_PRODUCTION_AUTO_ROLLBACK=PASS' "$helper"
grep -Fq "readonly HELPER_SHA='60eda2090b4bee1e8469a51f1cd57ff877b2b2a819daefb51949aa257c669f04'" "$installer"
grep -Fq "readonly SUDOERS_SHA='1ac52f20622ec15f916e5a5f952ffd5a707cdf2e794a642e25e7066fb31defe2'" "$installer"
[[ $(sha256sum "$helper" | awk '{print $1}') == '60eda2090b4bee1e8469a51f1cd57ff877b2b2a819daefb51949aa257c669f04' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == '1ac52f20622ec15f916e5a5f952ffd5a707cdf2e794a642e25e7066fb31defe2' ]]

if grep -Eq 'erp-staging|backup-erp|1102[56]|1802[56]|18088|codex_staging_' "$helper" "$installer"; then
  echo 'M055_PRODUCTION_HELPER_TEST=FAIL REASON=STAGING_IDENTIFIER' >&2
  exit 1
fi
if grep -Eq 'vision_db|mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then
  echo 'M055_PRODUCTION_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2
  exit 1
fi

[[ $(wc -l < "$root/deploy/releases/m055-runtime-files.txt" | tr -d ' ') -eq 6 ]]
[[ $(wc -l < "$root/deploy/releases/m055-support-files.txt" | tr -d ' ') -eq 2 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m055-customer-replies $command" "$sudoers"
done

echo 'M055_PRODUCTION_HELPER_TEST=PASS TARGET=ERP_PRODUCTION_ONLY COMMANDS=6 FILES=6 DATABASE_CHANGED=NO'
