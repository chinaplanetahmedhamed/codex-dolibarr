#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_backup_erp_m006.sh"

bash -n "$HELPER"
grep -Fq "readonly COMMIT='d903a14'" "$HELPER"
grep -Fq "readonly CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "readonly VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "readonly RUNTIME_FILE='custom/chinaplanetshippingprices/admin/import.php'" "$HELPER"
grep -Fq "readonly TEST_FILE='deploy/tests/test_shipping_prices_upload_csrf.php'" "$HELPER"
grep -Fq "readonly RUNTIME_SHA='47bd4b26a818110052262edef364597cf72191e1cc399f79fd99324ebbded745'" "$HELPER"
grep -Fq "readonly TEST_SHA='2ee7d9c4e8f6a30d1385d5299684d5560651ebbe3b710895087ed3903291a900'" "$HELPER"
grep -Fq 'M006_AUTO_ROLLBACK=PASS' "$HELPER"
grep -Fq 'ARTIFACT_ALLOWLIST_MISMATCH' "$HELPER"
[[ $(grep -c "readonly RUNTIME_FILE=" "$HELPER") -eq 1 ]]
! grep -Eq 'erp\.visiontrading\.biz|vision_erp|vision_db|docker (system )?prune|volume rm|staging-reset|NOCSRFCHECK|MAIN_SECURITY_CSRF' "$HELPER"
[[ $(grep -c 'NOPASSWD' "$HELPER" || true) -eq 0 ]]

echo 'M006 staging helper static safety test passed'
