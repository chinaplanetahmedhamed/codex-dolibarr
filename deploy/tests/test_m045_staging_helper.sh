#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m045.sh"
installer="$root/deploy/tools/install_backup_erp_m045_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m045.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='8d919fd9f2164bca6cfd859e89df338e985c486a'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='2503f666d86c9422207295e4c21fa000bd725cd7060eb79ec86881652f6846cd'" "$helper"
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
grep -Fq "readonly HELPER_SHA='71be7469cddefb9d9b715e36653c4464df64a985a48168f2e4de6c4a4efd01db'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='dfd4ff779a186a4ce57871aa5b3ede820628e4580954c8191a739ee859c34695'" "$installer"
grep -Fq "readonly SUDOERS_SHA='244522b944918fb96616bb38ed9ccaab0f883749ffc5728a432e3beb4935671b'" "$installer"
echo 'M045_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6'
