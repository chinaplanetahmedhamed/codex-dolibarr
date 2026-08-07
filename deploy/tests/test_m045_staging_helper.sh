#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m045.sh"
installer="$root/deploy/tools/install_backup_erp_m045_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m045.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='2bc1de310f401fdea587873736ffaa52e2cad9dc'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='5e5ab632407137473895f6d10353c2955332ce69bc2b374220bc2e5dca76f1e4'" "$helper"
grep -Fq "readonly PRODUCT_SYNC='deploy/tools/m045_c01_sync_cms_product.php'" "$helper"
grep -Fq 'sync_acceptance_product' "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly DB_CONTAINER='erp-staging-mariadb-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq "PRODUCTION_ACCESSED=NO" "$helper"
grep -Fq "'<form id=\"login\"'" "$helper"
grep -Fq "'name=\"actionlogin\"'" "$helper"
if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper"; then
  echo 'M045_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2
  exit 1
fi
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m045-deploy $command" "$sudoers"
done
grep -Fq "readonly HELPER_SHA='187dbbcff1dcd858dabaed74120405e9d4b02a5a61615bc1d88fef93cd284bab'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='bd95a86607f2381c538dbf86413dcd4a691df094af116b26ba052311da2988c8'" "$installer"
grep -Fq "readonly SUDOERS_SHA='244522b944918fb96616bb38ed9ccaab0f883749ffc5728a432e3beb4935671b'" "$installer"
echo 'M045_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6'
