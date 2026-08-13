#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m056_shipment_status_email.sh"
installer="$root/deploy/tools/install_backup_erp_m056_helper.sh"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m056.sudoers"

bash -n "$helper"; bash -n "$installer"
grep -Fq "readonly RELEASE='M056'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='7a1303c7622ff4514cbf7a7cc6fa5ccb8766f476'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='eca33c6cedc6085f3ff2aeebba96bf083a3ee845fab360c85c2874cf62bb4d1e'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq "readonly STATE_ROOT='/var/lib/chinaplanetos-m056-staging'" "$helper"
grep -Fq 'FILES=7 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO' "$helper"
grep -Fq "readonly HELPER_SHA='99b98da755c000eb92e0dc26ebd75df855f2e6f64ce0ad10084b0282366db245'" "$installer"
grep -Fq "readonly SUDOERS_SHA='fa12971aac242c1feeb428bed644e9ca0a20c485f76a1cfcc2ede0050ce3195a'" "$installer"
[[ $(sha256sum "$helper" | awk '{print $1}') == '99b98da755c000eb92e0dc26ebd75df855f2e6f64ce0ad10084b0282366db245' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == 'fa12971aac242c1feeb428bed644e9ca0a20c485f76a1cfcc2ede0050ce3195a' ]]

if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper" "$installer"; then echo 'M056_HELPER_TEST=FAIL REASON=PRODUCTION_IDENTIFIER' >&2; exit 1; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper"; then echo 'M056_HELPER_TEST=FAIL REASON=DATABASE_COMMAND' >&2; exit 1; fi

[[ $(wc -l < "$root/deploy/releases/m056-runtime-files.txt" | tr -d ' ') -eq 7 ]]
[[ $(wc -l < "$root/deploy/releases/m056-support-files.txt" | tr -d ' ') -eq 3 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m056-shipment-status-email $command" "$sudoers"; done

echo 'M056_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=7 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO'
