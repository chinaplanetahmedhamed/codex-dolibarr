#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_production_m007.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-production-m007.sudoers"
bash -n "$HELPER"
grep -Fq "readonly PROD_CONTAINER='vision_erp'" "$HELPER"
grep -Fq "readonly PROD_VOLUME='vision-os_dolibarr_custom'" "$HELPER"
grep -Fq "readonly STAGING_CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "readonly STAGING_VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "readonly COMMIT='d903a14'" "$HELPER"
grep -Fq "'custom/chinaplanetshippingprices/admin/import.php'" "$HELPER"
grep -Fq 'M007_AUTO_ROLLBACK=PASS' "$HELPER"
grep -Fq 'ARTIFACT_ALLOWLIST_MISMATCH' "$HELPER"
grep -Fq 'STAGING_RELEASE_MISMATCH' "$HELPER"
[[ $(grep -c "'custom/chinaplanetcustomercenter/" "$HELPER") -eq 16 ]]
[[ $(grep -c "'custom/chinaplanetlogistics/" "$HELPER") -eq 14 ]]
[[ $(grep -c "'custom/chinaplanetshippingprices/admin/import.php'" "$HELPER") -eq 2 ]]
! grep -Eq 'conf\.php|vision_db|docker (system )?prune|volume rm|NOCSRFCHECK|MAIN_SECURITY_CSRF|rm -rf.*PROD_ROOT' "$HELPER"
[[ $(grep -c 'NOPASSWD' "$HELPER" || true) -eq 0 ]]
[[ $(wc -l <"$SUDOERS") -eq 6 ]]
grep -Fqx 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m007-deploy compare' "$SUDOERS"
grep -Fqx 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m007-deploy preflight' "$SUDOERS"
grep -Fqx 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m007-deploy apply' "$SUDOERS"
grep -Fqx 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m007-deploy verify' "$SUDOERS"
grep -Fqx 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m007-deploy rollback' "$SUDOERS"
grep -Fqx 'codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m007-deploy status' "$SUDOERS"
! grep -Eq '[*?]|/bin/(ba)?sh|ALL$' "$SUDOERS"
echo 'M007 production helper static safety test passed'
