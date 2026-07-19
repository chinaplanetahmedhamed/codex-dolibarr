#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_production_m020_c02_customer_web_gallery.sh"
CONFIG_TOOL="$ROOT/deploy/tools/m020_c02_customer_web_gallery_config.php"
SUDOERS="$ROOT/deploy/config/codex-deploy-production-m020-c02-customer-web-gallery.sudoers"

bash -n "$HELPER"
php -l "$CONFIG_TOOL" >/dev/null

required=(
	"readonly PROD_CONTAINER='vision_erp'"
	"readonly PROD_VOLUME='vision-os_dolibarr_custom'"
	'ARTIFACT_ALLOWLIST_MISMATCH'
	'create_code_backup'
	'M020_C02_AUTO_ROLLBACK=PASS'
	'DATABASE_UNCHANGED=1'
	'PUBLICATIONS_UNCHANGED=1'
	'TOKEN_SECRET_NOT_READY'
)
for needle in "${required[@]}"; do grep -Fq "$needle" "$HELPER" "$CONFIG_TOOL" || { echo "FAIL missing helper safeguard: $needle" >&2; exit 1; }; done

grep -Eq "readonly RUNTIME_COMMIT='[0-9a-f]{40}'" "$HELPER"
grep -Eq "readonly SHORT_COMMIT='[0-9a-f]{7}'" "$HELPER"
grep -Eq "readonly ARTIFACT_SHA='[0-9a-f]{64}'" "$HELPER"
! grep -Fq '__M020_C02_' "$HELPER"
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$SUDOERS") -eq 6 ]]
for command in compare preflight apply verify rollback status; do grep -Fqx "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m020-c02-customer-web-gallery-deploy $command" "$SUDOERS"; done

grep -Fq "\$argv[1] !== 'check'" "$CONFIG_TOOL"
grep -Fq 'information_schema.tables' "$CONFIG_TOOL"
grep -Fq 'CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_SECRET' "$CONFIG_TOOL"
! grep -Eq 'echo .*secret|print.*secret|var_dump|print_r' "$CONFIG_TOOL"

for forbidden in 'backup-erp' 'erp-staging' 'docker compose' 'docker volume rm' 'conf.php' 'DOLAPIKEY'; do
	if grep -Fq "$forbidden" "$HELPER"; then echo "FAIL forbidden helper content: $forbidden" >&2; exit 1; fi
done
! grep -Eq '[*?]|/bin/(ba)?sh|ALL$' "$SUDOERS"
echo 'M020-C02 production customer web gallery helper static checks passed'
