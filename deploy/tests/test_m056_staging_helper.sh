#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m056_shipment_status_email.sh"
installer="$root/deploy/tools/install_backup_erp_m056_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m056.sudoers"

bash -n "$helper"; bash -n "$installer"
grep -Fq "readonly RELEASE='M056'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='606b54c852230b0674b0745084e107487cf3161d'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='19943d703ddfc0866aa79ffb003a58e82780b824162d9ac08e872c46bad54592'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq "readonly STATE_ROOT='/var/lib/chinaplanetos-m056-staging'" "$helper"
grep -Fq 'FILES=8 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO' "$helper"
grep -Fq "readonly HELPER_SHA='e08f248628ad0d1d396211bdc6be9dc23ae510a4946d1b06d9948fe8a7e031db'" "$installer"
grep -Fq "readonly SUDOERS_SHA='fa12971aac242c1feeb428bed644e9ca0a20c485f76a1cfcc2ede0050ce3195a'" "$installer"
[[ $(sha256sum "$helper" | awk '{print $1}') == 'e08f248628ad0d1d396211bdc6be9dc23ae510a4946d1b06d9948fe8a7e031db' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == 'fa12971aac242c1feeb428bed644e9ca0a20c485f76a1cfcc2ede0050ce3195a' ]]

if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper" "$installer"; then echo 'M056_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2; exit 1; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then echo 'M056_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2; exit 1; fi

[[ $(wc -l < "$root/deploy/releases/m056-runtime-files.txt" | tr -d ' ') -eq 8 ]]
[[ $(wc -l < "$root/deploy/releases/m056-support-files.txt" | tr -d ' ') -eq 4 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m056-shipment-status-email $command" "$sudoers"; done

grep -Fq "== '127.0.0.1:11025'" "$helper"
grep -Fq "== '127.0.0.1:18025'" "$helper"
grep -Fq 'require_disk_space' "$helper"
grep -Fq 'sync -f "$CODE_BACKUP"' "$helper"
grep -Fq "save_state; MUTATED=0" "$helper"
grep -Fq 'ROLLBACK_MUTATED=1; restore_prior' "$helper"
grep -Fq "restore_reapply && live_checks" "$helper"
grep -Fq "prior_checks || die 'ROLLBACK_VERIFY_FAILED'" "$helper"
grep -Fq "[[ \"\$STATE_STATUS\" == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'" "$helper"

echo 'M056_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=8 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO'
