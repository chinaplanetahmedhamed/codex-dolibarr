#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m045.sh"
installer="$root/deploy/tools/install_backup_erp_m045_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m045.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='d488f3f30e822089c88a73ea9693adea13afc107'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='d048ac349940ce59df40615a0b2f74ad28ce3813cff84032fe927a6d3780309d'" "$helper"
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
grep -Fq "readonly HELPER_SHA='bd95a86607f2381c538dbf86413dcd4a691df094af116b26ba052311da2988c8'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='af67c021d1ad6cd7343057047fa7caad7aeb85a91385db6ce6cc3a3bd545bafd'" "$installer"
grep -Fq "readonly SUDOERS_SHA='244522b944918fb96616bb38ed9ccaab0f883749ffc5728a432e3beb4935671b'" "$installer"
echo 'M045_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6'
