#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_production_m056_shipment_status_email.sh"
installer="$root/deploy/tools/install_production_m056_helper.sh"
sudoers="$root/deploy/config/codex-deploy-production-m056.sudoers"

bash -n "$helper"; bash -n "$installer"
grep -Fq "readonly RELEASE='M056-P01'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='606b54c852230b0674b0745084e107487cf3161d'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='19943d703ddfc0866aa79ffb003a58e82780b824162d9ac08e872c46bad54592'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper"
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper"
grep -Fq "readonly STATE_ROOT='/var/lib/chinaplanetos-m056-production'" "$helper"
grep -Fq 'FILES=8 DATABASE_CHANGED=NO' "$helper"
grep -Fq "readonly HELPER_SHA='6798f0d893b6a6b2683832f96962cb19cfc5565825c1bc2e058b7efeab531a16'" "$installer"
grep -Fq "readonly SUDOERS_SHA='5a9b0f404d6a8f4e359413850d37a4bf8ff5cadab247186d0fb5a11cf5974b1e'" "$installer"
[[ $(sha256sum "$helper" | awk '{print $1}') == '6798f0d893b6a6b2683832f96962cb19cfc5565825c1bc2e058b7efeab531a16' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == '5a9b0f404d6a8f4e359413850d37a4bf8ff5cadab247186d0fb5a11cf5974b1e' ]]

if grep -Eq 'erp-staging|backup-erp|codex_staging_|11025|18025|18088' "$helper" "$installer"; then echo 'M056_PRODUCTION_HELPER_TEST=FAIL REASON=STAGING_IDENTIFIER' >&2; exit 1; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then echo 'M056_PRODUCTION_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2; exit 1; fi
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper"
grep -Fq "readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'" "$helper"
grep -Fq "'63b3defa8acf1018855d28a6e96545e0701c85f2f53c23cb1134128ef0c9fae7'" "$helper"
grep -Fq "'ec5f1ba5743a860eb5c93fbf142bf1e19bfae63a5755e83eaf6c838a29444744'" "$helper"

[[ $(wc -l < "$root/deploy/releases/m056-runtime-files.txt" | tr -d ' ') -eq 8 ]]
[[ $(wc -l < "$root/deploy/releases/m056-support-files.txt" | tr -d ' ') -eq 4 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m056-shipment-status-email $command" "$sudoers"; done

grep -Fq 'require_disk_space' "$helper"
grep -Fq 'sync -f "$CODE_BACKUP"' "$helper"
grep -Fq "save_state; MUTATED=0" "$helper"
grep -Fq 'ROLLBACK_MUTATED=1; restore_prior' "$helper"
grep -Fq "restore_reapply && live_checks" "$helper"
grep -Fq "prior_checks || die 'ROLLBACK_VERIFY_FAILED'" "$helper"
grep -Fq "[[ \"\$STATE_STATUS\" == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'" "$helper"
grep -Fq 'sync -f "$temporary_helper"' "$installer"
grep -Fq 'sync -f "$temporary_sudoers"' "$installer"

echo 'M056_PRODUCTION_HELPER_TEST=PASS TARGET=ERP_PRODUCTION_ONLY COMMANDS=6 FILES=8 DATABASE_CHANGED=NO'
