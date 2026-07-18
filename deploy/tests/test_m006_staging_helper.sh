#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_backup_erp_m006.sh"

bash -n "$HELPER"
grep -Fq "readonly COMMIT='9a1aa5d'" "$HELPER"
grep -Fq "readonly CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "readonly VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "readonly RUNTIME_FILE='custom/chinaplanetshippingprices/admin/import.php'" "$HELPER"
grep -Fq "readonly TEST_FILE='deploy/tests/test_shipping_prices_upload_csrf.php'" "$HELPER"
grep -Fq "readonly RUNTIME_SHA='442f80fef69938ca744db87b7b74d26d3846db08eeb7637d283c16adf5524656'" "$HELPER"
grep -Fq "readonly TEST_SHA='cc4193a398d8e1db6e2c88f286c34bf199c4f44f0c628ba51dceded5aa3ab540'" "$HELPER"
grep -Fq 'M006_AUTO_ROLLBACK=PASS' "$HELPER"
grep -Fq 'ARTIFACT_ALLOWLIST_MISMATCH' "$HELPER"
[[ $(grep -c "readonly RUNTIME_FILE=" "$HELPER") -eq 1 ]]
! grep -Eq 'erp\.visiontrading\.biz|vision_erp|vision_db|docker (system )?prune|volume rm|staging-reset|NOCSRFCHECK|MAIN_SECURITY_CSRF' "$HELPER"
[[ $(grep -c 'NOPASSWD' "$HELPER" || true) -eq 0 ]]

echo 'M006 staging helper static safety test passed'
