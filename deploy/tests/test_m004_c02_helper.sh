#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_backup_erp_m004_c02.sh"

bash -n "$HELPER"
grep -Fq "readonly CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "readonly VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "readonly COMMIT='2c032eb59a4547b72429e07cf730f6e9963cc98e'" "$HELPER"
grep -Fq "readonly -a RUNTIME_FILES" "$HELPER"
grep -Fq "declare -Ar EXPECTED_SHA" "$HELPER"
grep -Fq "'custom/chinaplanetcustomercenter/download.php'" "$HELPER"
grep -Fq "'custom/chinaplanetcustomercenter/lib/portal.lib.php'" "$HELPER"
grep -Fq "'custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php'" "$HELPER"
grep -Fq "'custom/chinaplanetlogistics/public/track.php'" "$HELPER"
grep -Fq "CANDIDATE_M003_TEST_FAILED" "$HELPER"
grep -Fq "M004_C02_AUTO_ROLLBACK=PASS" "$HELPER"
! grep -Eq 'vision_erp|vision_db|vision-os_|/root/vision-os|docker (system )?prune|volume rm|staging-reset' "$HELPER"
[[ $(grep -c "NOPASSWD" "$HELPER" || true) -eq 0 ]]

echo 'M004-C02 helper static safety test passed'
