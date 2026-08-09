#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m044_c04_auto_translation.sh"
installer="$root/deploy/tools/install_backup_erp_m044_c04_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m044-c04.sudoers"
workflow="$root/.github/workflows/deploy-m044-c04-backup-erp.yml"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RUNTIME_COMMIT='99124ee517bb022b7220f4bafb90c34ca1e49069'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='516853c0fda282ef5b7f8e8cbe263cca285b196516b00f3ce768aac254435e61'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq "readonly STATE_ROOT='/var/lib/chinaplanetos-m044-c04-staging'" "$helper"
grep -Fq 'FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO' "$helper"
grep -Fq "readonly HELPER_SHA='a2f44d230f5e25c35344c766eed9ca40ec885b6511feb8780dc10448cd89dded'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='cd10e665fbaababcc3a2c4b3b1ffc6f7f3acbde7e1feae23a6c368b4272ee2c3'" "$installer"
grep -Fq "readonly SUDOERS_SHA='8cdb77ae3b5633463b9a2bd2eee16febb010ff20b0d146695b26516baa5d570e'" "$installer"
[[ $(sha256sum "$helper" | awk '{print $1}') == 'a2f44d230f5e25c35344c766eed9ca40ec885b6511feb8780dc10448cd89dded' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == '8cdb77ae3b5633463b9a2bd2eee16febb010ff20b0d146695b26516baa5d570e' ]]

if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)|environment:[[:space:]]+production' "$helper" "$installer" "$workflow"; then
  echo 'M044_C04_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2
  exit 1
fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then
  echo 'M044_C04_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2
  exit 1
fi

[[ $(wc -l < "$root/deploy/releases/m044-c04-runtime-files.txt" | tr -d ' ') -eq 6 ]]
[[ $(wc -l < "$root/deploy/releases/m044-c04-support-files.txt" | tr -d ' ') -eq 3 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m044-c04-auto-translation $command" "$sudoers"
done
grep -Fq 'DEPLOY_M044_C04_TO_BACKUP_ERP' "$workflow"
grep -Fq 'environment: backup-erp' "$workflow"

echo 'M044_C04_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO'
