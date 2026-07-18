#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_production_m007.sh"
bash -n "$HELPER"
grep -Fq "readonly PROD_CONTAINER='vision_erp'" "$HELPER"
grep -Fq "readonly PROD_VOLUME='vision-os_dolibarr_custom'" "$HELPER"
grep -Fq "readonly STAGING_CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "readonly STAGING_VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "readonly COMMIT='9a1aa5d'" "$HELPER"
grep -Fq "'custom/chinaplanetshippingprices/admin/import.php'" "$HELPER"
grep -Fq 'M007_AUTO_ROLLBACK=PASS' "$HELPER"
grep -Fq 'ARTIFACT_ALLOWLIST_MISMATCH' "$HELPER"
grep -Fq 'STAGING_RELEASE_MISMATCH' "$HELPER"
[[ $(grep -c "'custom/chinaplanetcustomercenter/" "$HELPER") -eq 16 ]]
[[ $(grep -c "'custom/chinaplanetlogistics/" "$HELPER") -eq 14 ]]
[[ $(grep -c "'custom/chinaplanetshippingprices/admin/import.php'" "$HELPER") -eq 2 ]]
! grep -Eq 'conf\.php|vision_db|docker (system )?prune|volume rm|NOCSRFCHECK|MAIN_SECURITY_CSRF|rm -rf.*PROD_ROOT' "$HELPER"
[[ $(grep -c 'NOPASSWD' "$HELPER" || true) -eq 0 ]]
echo 'M007 production helper static safety test passed'
