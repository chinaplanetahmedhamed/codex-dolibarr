#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m045.sh"
installer="$root/deploy/tools/install_backup_erp_m045_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m045.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='bd553a72eadeaa0437105060ea7cdafc8f11ecdd'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='2b01b0b4bc31c0f1e8ee80110f953ddb3b6b0c16e5e99d5de5e19b0b8e1b073f'" "$helper"
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
grep -Fq "readonly HELPER_SHA='e30e2cd5da00b8a5adf7018b0cd53106ca0be261bfdc7e3078a98bb661c99332'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='a7ff9ef3c0e58451e5a6d2341b43904c2374b816e052b0b377f5e37d087b6bf0'" "$installer"
grep -Fq "readonly SUDOERS_SHA='244522b944918fb96616bb38ed9ccaab0f883749ffc5728a432e3beb4935671b'" "$installer"
echo 'M045_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6'
