#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m044_c02_schema.sh"
installer="$root/deploy/tools/install_backup_erp_m044_c02_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m044-c02.sudoers"
workflow="$root/.github/workflows/deploy-m044-c02-backup-erp.yml"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='46f77f67571e58cf0ee5890ff6cafe5159e5a91c'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='9ed7644b58a91e1ed534617a823a26c19fb1a6d4b756486e7ca0338aa93852a2'" "$helper"
grep -Fq "readonly MIGRATION_SHA='bc75838ff8d8ba807da59e6e5514e88f1770c050430780fb88c7ae2b5d9606c9'" "$helper"
grep -Fq "readonly IMPORTER_SHA='f2ba5156aecdf7ec9a065bf7fd7b9a21173f97ddea9365538deade9fb4b168d3'" "$helper"
grep -Fq "readonly SERVICE_SHA='60912061f2b613b648fcebe7d0bee5112b506aeee3720dbb02132276efa3a333'" "$helper"
grep -Fq "readonly IMPORT_PAGE_SHA='35261cc40213fab3988cb32a85eae170a1239799eaef8b0b573ab1da1245584c'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly DB_CONTAINER='erp-staging-mariadb-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq 'M041_SCHEMA_PRESTATE_B64' "$helper"
grep -Fq 'create_database_backup' "$helper"
grep -Fq 'M044_C02_STAGING_AUTO_ROLLBACK=PASS' "$helper"
grep -Fq 'RESTORED_DATABASE_BACKUP' "$helper"
grep -Fq 'PRODUCTION_ACCESSED=NO' "$helper"
if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper"; then
  echo 'M044_C02_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2
  exit 1
fi

[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m044-c02-schema $command" "$sudoers"
done

grep -Fq "readonly HELPER_SHA='e24913cbcd19fa84841aedebf1fc1a8c8630478249034df811bbef535529475f'" "$installer"
grep -Fq "readonly SUDOERS_SHA='e7f86f1c475a9879aa3b746755552ed8ca24521a977f6288b8c51e46adaa909b'" "$installer"
grep -Fq 'DEPLOY_M044_C02_TO_BACKUP_ERP' "$workflow"
grep -Fq 'environment: backup-erp' "$workflow"
grep -Fq "steps.apply.outputs.did_mutate == 'true'" "$workflow"
if grep -Fq 'environment: production' "$workflow"; then
  echo 'M044_C02_HELPER_TEST=FAIL REASON=PRODUCTION_JOB' >&2
  exit 1
fi

echo 'M044_C02_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 SCHEMA_COLUMNS=10 INDEXES=1'
