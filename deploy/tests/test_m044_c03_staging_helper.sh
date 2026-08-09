#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m044_c03_note_review.sh"
installer="$root/deploy/tools/install_backup_erp_m044_c03_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m044-c03.sudoers"
workflow="$root/.github/workflows/deploy-m044-c03-backup-erp.yml"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='045be07b340f7af8caffa2fa2077cacb53dc0724'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='adb96df5b75e682739cad60d84e1c6f760b2092280cf5c368cd0f7e78ef7abe2'" "$helper"
grep -Fq "readonly BATCH_SHA='151cfe7b2d24efcb476a77fed51f34b2ab27493dcd944d8b55a14cbe329f383f'" "$helper"
grep -Fq "readonly REVIEW_SERVICE_SHA='32854848a2874423ed9401102890129ff749cd7c775f52c55275ee3bc75bde84'" "$helper"
grep -Fq "readonly CSS_SHA='24473c66ee3d441fa145d40a942bddd8ca1fe8c21d76daf47317cd1f322a5372'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq 'CODE_BACKUP=' "$helper"
grep -Fq 'PRESTATE=' "$helper"
grep -Fq 'DATABASE_CHANGED=NO' "$helper"
grep -Fq 'PRODUCTION_ACCESSED=NO' "$helper"
if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper" "$installer" "$workflow"; then
  echo 'M044_C03_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2
  exit 1
fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then
  echo 'M044_C03_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2
  exit 1
fi
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m044-c03-note-review $command" "$sudoers"
done
grep -Fq "readonly HELPER_SHA='7592090e27f438f36a1ef84d2705a3ec50a2cf70a2be0732a5b5140c4b18c0d7'" "$installer"
grep -Fq "readonly SUDOERS_SHA='9a7ffd57580142239544cb504434463db3f68cc7c9e07d3cc02883c0ba1fb502'" "$installer"
grep -Fq 'DEPLOY_M044_C03_TO_BACKUP_ERP' "$workflow"
grep -Fq 'environment: backup-erp' "$workflow"
if grep -Fq 'environment: production' "$workflow"; then
  echo 'M044_C03_HELPER_TEST=FAIL REASON=PRODUCTION_JOB' >&2
  exit 1
fi
echo 'M044_C03_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=3 DATABASE_CHANGED=NO'
