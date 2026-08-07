#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m044_c01.sh"
installer="$root/deploy/tools/install_backup_erp_m044_c01_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m044-c01.sudoers"
workflow="$root/.github/workflows/deploy-m044-c01-backup-erp.yml"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='dc6387f41611f2bec24c5a96eb8aacc252f019ce'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='946479da0e9072ff83e2145ac5a82e75e2109459e262e449ef7375a53446a847'" "$helper"
grep -Fq "readonly SERVICE_SHA='60912061f2b613b648fcebe7d0bee5112b506aeee3720dbb02132276efa3a333'" "$helper"
grep -Fq "readonly IMPORTER_SHA='f2ba5156aecdf7ec9a065bf7fd7b9a21173f97ddea9365538deade9fb4b168d3'" "$helper"
grep -Fq "readonly IMPORT_PAGE_SHA='35261cc40213fab3988cb32a85eae170a1239799eaef8b0b573ab1da1245584c'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq 'DATABASE_CHANGED=NO' "$helper"
grep -Fq 'PRODUCTION_ACCESSED=NO' "$helper"
if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper"; then
  echo 'M044_C01_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2
  exit 1
fi
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m044-c01-deploy $command" "$sudoers"
done
grep -Fq "readonly HELPER_SHA='f0d84b4781f32ee863f5899de1ef7ea3ea9b030fe7b952f87af2fc13a4b53f71'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='ead2a700720dfeae28fc45fb8ffc5f764d09211c0fd046fa105a2f5faa6e6e96'" "$installer"
grep -Fq "readonly SUDOERS_SHA='46938fd2e0089c4c33375ffba2b6f8ee56dd4893a48de626b3d6b70c0de51154'" "$installer"
grep -Fq "DEPLOY_M044_C01_TO_BACKUP_ERP" "$workflow"
grep -Fq "environment: backup-erp" "$workflow"
if grep -Fq 'environment: production' "$workflow"; then
  echo 'M044_C01_HELPER_TEST=FAIL REASON=PRODUCTION_JOB' >&2
  exit 1
fi
echo 'M044_C01_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=1'
