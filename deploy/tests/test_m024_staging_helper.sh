#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_backup_erp_m024_shipment_collaboration.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-backup-erp-m024-shipment-collaboration.sudoers"
INSTALLER="$ROOT/deploy/tools/install_backup_erp_m024_helper.sh"

bash -n "$HELPER"
bash -n "$INSTALLER"
[[ $(stat -f '%Lp' "$HELPER" 2>/dev/null || stat -c '%a' "$HELPER") =~ ^(644|755)$ ]]
grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "TARGET_DB_CONTAINER='erp-staging-mariadb-1'" "$HELPER"
grep -Fq "TARGET_VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "TARGET_HTTP='http://127.0.0.1:18088/'" "$HELPER"
grep -Fq "RELEASE='M020-C03_M023_M024_M024-C01_M025'" "$HELPER"
grep -Fq "RUNTIME_COMMIT='7b4a216d0dba2f611b457d804e85f1e07317812d'" "$HELPER"
grep -Fq "ARTIFACT_SHA='dcb020f773e399f83df99f1416358f18f2dc96ee9559699e86c9049ab0974e35'" "$HELPER"
grep -Fq "ARTIFACT=\"\${INBOX}/m024-c01-r2-\${SHORT_COMMIT}.tar.gz\"" "$HELPER"
! grep -Fq 'PLACEHOLDER' "$HELPER"
! grep -Fq "vision_erp" "$HELPER"
! grep -Fq "erp.visiontrading.biz" "$HELPER"
grep -Fq "m020_c01_shipment_public_document_schema.php" "$HELPER"
grep -Fq "m024_shipment_customer_collaboration_schema.php" "$HELPER"
grep -Fq "create_database_backup" "$HELPER"
grep -Fq "M024_STAGING_AUTO_CODE_ROLLBACK" "$HELPER"
grep -Fq "ADDITIVE_SCHEMA_RETAINED=1" "$HELPER"
grep -Fq "DEMO_CUSTOMER_LOGIN='mohamed.azmy'" "$HELPER"
grep -Fq "m024_seed_backup_erp_demo.php" "$HELPER"
grep -Fq "custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php" "$HELPER"
grep -Fq "custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php" "$HELPER"
grep -Fq "docs/API/mobile-v1.openapi.json" "$HELPER"
grep -Fq 'documents:11,events:26' "$HELPER"
grep -Fq "M024_STAGING_DEMO_VERIFY=PASS" "$HELPER"
! grep -Eq '(^|[^A-Za-z])(rm -rf|docker compose down|DROP DATABASE|TRUNCATE TABLE)([^A-Za-z]|$)' "$HELPER"

[[ $(wc -l < "$SUDOERS" | tr -d ' ') -eq 9 ]]
for command in compare preflight apply verify seed-demo verify-demo cleanup-demo rollback status; do
	grep -Fqx "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m024-shipment-collaboration-deploy $command" "$SUDOERS"
done
! grep -Eq '(ALL=\(ALL|NOPASSWD: ALL|\*)' "$SUDOERS"
grep -Fq "HELPER_SHA='e4dc0872c9f452265d7459f93968c5abb4426abbd293d139fdda3785d957e178'" "$INSTALLER"
grep -Fq "OBSOLETE_HELPER_SHA='d4a2c1c23efa8a9ef4b76a2c800d43a1264514f2d20aa49a99125bcc2cf46bc6'" "$INSTALLER"
grep -Fq "SUDOERS_SHA='a5fa71eb1aec148b97e4ed2d59ceeaca30e082d300b93a2f7916fdeef8ca5811'" "$INSTALLER"
grep -Fq 'DEPLOY_EXECUTED=NO PRODUCTION_ACCESSED=NO' "$INSTALLER"
grep -Fq "die 'UNEXPECTED_EXISTING_HELPER_HASH'" "$INSTALLER"
helper_guard_line=$(grep -n "UNEXPECTED_EXISTING_HELPER_HASH" "$INSTALLER" | head -1 | cut -d: -f1)
sudoers_write_line=$(grep -n 'tmp_sudoers=' "$INSTALLER" | head -1 | cut -d: -f1)
[[ "$helper_guard_line" -lt "$sudoers_write_line" ]]
! grep -Fq 'vision_erp' "$INSTALLER"
! grep -Fq 'erp.visiontrading.biz' "$INSTALLER"

echo 'M024_STAGING_HELPER_TEST=PASS'
