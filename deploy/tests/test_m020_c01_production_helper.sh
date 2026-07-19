#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_production_m020_c01_shipment_gallery.sh"
CONFIG_TOOL="$ROOT/deploy/tools/m020_c01_shipment_gallery_config.php"
SUDOERS="$ROOT/deploy/config/codex-deploy-production-m020-c01-shipment-gallery.sudoers"

bash -n "$HELPER"
php -l "$CONFIG_TOOL" >/dev/null

required=(
	"readonly PROD_CONTAINER='vision_erp'"
	"readonly PROD_DB_CONTAINER='vision_db'"
	"readonly PROD_VOLUME='vision-os_dolibarr_custom'"
	'ARTIFACT_ALLOWLIST_MISMATCH'
	'create_code_backup'
	'create_database_backup'
	'M020_C01_AUTO_ROLLBACK=PASS'
	'SCHEMA_RETAINED=1'
	'PUBLICATIONS_REVOKED_SINCE'
	'ROUTE_HTTP=401'
)
for needle in "${required[@]}"; do grep -Fq "$needle" "$HELPER" || { echo "FAIL missing helper safeguard: $needle" >&2; exit 1; }; done

grep -Eq "readonly RUNTIME_COMMIT='[0-9a-f]{40}'" "$HELPER"
grep -Eq "readonly SHORT_COMMIT='[0-9a-f]{7}'" "$HELPER"
grep -Eq "readonly ARTIFACT_SHA='[0-9a-f]{64}'" "$HELPER"
! grep -Fq '__M020_C01_' "$HELPER"
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$SUDOERS") -eq 6 ]]
for command in compare preflight apply verify rollback status; do grep -Fqx "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m020-c01-shipment-gallery-deploy $command" "$SUDOERS"; done

grep -Fq "array('check', 'deactivate-since')" "$CONFIG_TOOL"
grep -Fq 'information_schema.tables' "$CONFIG_TOOL"
grep -Fq 'customer_visible=0,status=0' "$CONFIG_TOOL"
! grep -Eq 'echo .*storage_key|print.*storage_key|var_dump|print_r' "$CONFIG_TOOL"

for forbidden in 'backup-erp' 'erp-staging' 'docker compose' 'docker volume rm' 'conf.php' 'MAIN_SECURITY_CSRF_WITH_TOKEN' 'DOLAPIKEY'; do
	if grep -Fq "$forbidden" "$HELPER"; then echo "FAIL forbidden helper content: $forbidden" >&2; exit 1; fi
done
! grep -Eq '[*?]|/bin/(ba)?sh|ALL$' "$SUDOERS"
echo 'M020-C01 production Shipment Gallery helper static checks passed'
