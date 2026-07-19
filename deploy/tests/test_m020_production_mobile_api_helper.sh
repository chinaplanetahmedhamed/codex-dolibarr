#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_production_m020_mobile_api.sh"
CONFIG_TOOL="$ROOT/deploy/tools/m020_mobile_api_config.php"
SUDOERS="$ROOT/deploy/config/codex-deploy-production-m020-mobile-api.sudoers"

bash -n "$HELPER"
php -l "$CONFIG_TOOL" >/dev/null

required=(
	"readonly RUNTIME_COMMIT='c46783002ef1902108d938a745b8f9701436dcdb'"
	"readonly PROD_CONTAINER='vision_erp'"
	"readonly PROD_DB_CONTAINER='vision_db'"
	"readonly PROD_VOLUME='vision-os_dolibarr_custom'"
	"ARTIFACT_ALLOWLIST_MISMATCH"
	"create_code_backup"
	"create_database_backup"
	"M020_AUTO_ROLLBACK=PASS"
	"SCHEMA_RETAINED=1"
	"CONFIG_PRESTATE"
	"ROUTE_HTTP=401"
)
for needle in "${required[@]}"; do grep -Fq "$needle" "$HELPER" || { echo "FAIL missing helper safeguard: $needle" >&2; exit 1; }; done

grep -Eq "readonly ARTIFACT_SHA='[0-9a-f]{64}'" "$HELPER"
! grep -Fq '__M020_ARTIFACT_SHA__' "$HELPER"
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$SUDOERS") -eq 6 ]]
for command in compare preflight apply verify rollback status; do grep -Fqx "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m020-mobile-api-deploy $command" "$SUDOERS"; done

grep -Fq "CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_SECRET" "$CONFIG_TOOL"
grep -Fq 'random_bytes(48)' "$CONFIG_TOOL"
grep -Fq 'M020_SECRET_ENTITY = 0' "$CONFIG_TOOL"
! grep -Eq 'echo .*\$secret|print.*\$secret|var_dump|print_r' "$CONFIG_TOOL"

for forbidden in 'backup-erp' 'erp-staging' 'docker compose' 'docker volume rm' 'conf.php' 'MAIN_SECURITY_CSRF_WITH_TOKEN' 'DOLAPIKEY'; do
	if grep -Fq "$forbidden" "$HELPER"; then echo "FAIL forbidden helper content: $forbidden" >&2; exit 1; fi
done
! grep -Eq '[*?]|/bin/(ba)?sh|ALL$' "$SUDOERS"
echo 'M020 production Mobile API helper static checks passed'
