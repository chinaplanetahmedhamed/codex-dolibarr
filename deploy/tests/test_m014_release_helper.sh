#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_release_m014.sh"

[[ -f "$HELPER" ]] || { echo 'FAIL M014 helper absent' >&2; exit 1; }
bash -n "$HELPER"

required=(
	"readonly COMMIT='49eadafd36955446468c3629f2c9d8b6aa3a7a2c'"
	"readonly ARTIFACT_SHA='4d9c05e1681ea9570861ef852f32071413846eaa664e0bbd1d78a3016580f118'"
	"chinaplanetos-backup-erp-deploy"
	"chinaplanetos-production-m007-deploy"
	"ARTIFACT_ALLOWLIST_MISMATCH"
	"create_database_backup"
	"create_code_backup"
	"require_staging_release"
	"STATE=ABSENT"
	"apply_schema"
	"restore_code_backup"
	"SCHEMA_RETAINED=1"
)
for needle in "${required[@]}"; do
	grep -Fq "$needle" "$HELPER" || { echo "FAIL missing helper safeguard: $needle" >&2; exit 1; }
done

for forbidden in 'docker compose down' 'docker volume rm' 'rm -rf "$TARGET_ROOT"' 'MAIN_SECURITY_CSRF_WITH_TOKEN'; do
	if grep -Fq "$forbidden" "$HELPER"; then
		echo "FAIL forbidden helper operation: $forbidden" >&2
		exit 1
	fi
done

echo 'M014 release helper static checks passed'
