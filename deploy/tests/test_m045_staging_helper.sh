#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m045.sh"
installer="$root/deploy/tools/install_backup_erp_m045_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m045.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='1e6d58a917402db927b56a9e4b5af33bba7d04d1'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='20637c0e9cc4f3b48827d5a5cb5f27853bfda058e857b043e7c04e194667f1cc'" "$helper"
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
grep -Fq "readonly HELPER_SHA='a7ff9ef3c0e58451e5a6d2341b43904c2374b816e052b0b377f5e37d087b6bf0'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='51c7b1e14ad931b23f7f413f7f468e2db9fe6b347a2c00b689d035cfdb2cdbe2'" "$installer"
grep -Fq "readonly SUDOERS_SHA='244522b944918fb96616bb38ed9ccaab0f883749ffc5728a432e3beb4935671b'" "$installer"
echo 'M045_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6'
