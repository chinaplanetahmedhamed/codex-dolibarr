#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_upload_limit_m008.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-upload-limit-m008.sudoers"
bash -n "$HELPER"
grep -Fq "readonly COMMIT='7bd5a4e'" "$HELPER"
grep -Fq "readonly EXPECTED_LIMITS='200M|210M|1024M|900|900'" "$HELPER"
grep -Fq "readonly STAGING_CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "readonly PROD_CONTAINER='vision_erp'" "$HELPER"
grep -Fq "readonly PROXY_CONTAINER='management-nginx-proxy-manager-1'" "$HELPER"
grep -Fq 'M008_AUTO_ROLLBACK=PASS' "$HELPER"
grep -Fq 'ARTIFACT_ALLOWLIST_MISMATCH' "$HELPER"
grep -Fq 'EFFECTIVE_LIMITS_MISMATCH' "$HELPER"
grep -Fq "grep -Fqx 'php_value upload_max_filesize 200M'" "$HELPER"
! grep -Fq 'php "$tmp/$TEST_FILE"' "$HELPER"
[[ $(wc -l <"$SUDOERS") -eq 7 ]]
! grep -Eq '[*?]|/bin/(ba)?sh|ALL$' "$SUDOERS"
! grep -Eq 'vision_db|conf\.php|docker (system )?prune|volume rm|compose|proxy_host|rm -rf' "$HELPER"
echo 'M008 upload limit helper static safety test passed'
