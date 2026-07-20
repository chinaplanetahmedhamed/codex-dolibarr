#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_production_m026_shipment_collaboration.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-production-m026-shipment-collaboration.sudoers"
INSTALLER="$ROOT/deploy/tools/install_production_m026_helper.sh"

bash -n "$HELPER"
bash -n "$INSTALLER"
[[ $(stat -f '%Lp' "$HELPER" 2>/dev/null || stat -c '%a' "$HELPER") =~ ^(644|755)$ ]]
grep -Fq "TARGET_CONTAINER='vision_erp'" "$HELPER"
grep -Fq "TARGET_DB_CONTAINER='vision_db'" "$HELPER"
grep -Fq "TARGET_VOLUME='vision-os_dolibarr_custom'" "$HELPER"
grep -Fq "TARGET_HTTP='https://erp.visiontrading.biz/'" "$HELPER"
grep -Fq "RELEASE='M026'" "$HELPER"
grep -Fq "RUNTIME_COMMIT='7b4a216d0dba2f611b457d804e85f1e07317812d'" "$HELPER"
grep -Fq "ARTIFACT_SHA='8a229bb935126eb918c117940bc17c72227a0bf9022acdb94e2fa74d5135fd51'" "$HELPER"
grep -Fq "ARTIFACT=\"\${INBOX}/m026-production-\${SHORT_COMMIT}.tar.gz\"" "$HELPER"
! grep -Fq 'PLACEHOLDER' "$HELPER"
! grep -Fq "backup-erp" "$HELPER"
! grep -Fq "erp-staging" "$HELPER"
grep -Fq "m020_c01_shipment_public_document_schema.php" "$HELPER"
grep -Fq "m024_shipment_customer_collaboration_schema.php" "$HELPER"
grep -Fq "create_database_backup" "$HELPER"
grep -Fq "M026_PRODUCTION_AUTO_CODE_ROLLBACK" "$HELPER"
grep -Fq "ADDITIVE_SCHEMA_RETAINED=1" "$HELPER"
! grep -Fq "DEMO_CUSTOMER_LOGIN" "$HELPER"
! grep -Fq "m024_seed_backup_erp_demo.php" "$HELPER"
grep -Fq "custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php" "$HELPER"
grep -Fq "custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php" "$HELPER"
grep -Fq "docs/API/mobile-v1.openapi.json" "$HELPER"
! grep -Fq "seed-demo" "$HELPER"
! grep -Eq '(^|[^A-Za-z])(rm -rf|docker compose down|DROP DATABASE|TRUNCATE TABLE)([^A-Za-z]|$)' "$HELPER"

[[ $(wc -l < "$SUDOERS" | tr -d ' ') -eq 6 ]]
for command in compare preflight apply verify rollback status; do
	grep -Fqx "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m026-shipment-collaboration-deploy $command" "$SUDOERS"
done
! grep -Eq '(ALL=\(ALL|NOPASSWD: ALL|\*)' "$SUDOERS"
grep -Fq "HELPER_SHA='434920d01f590c379c6d5d4818aac599ce25568c73308cf671e5740f18bee014'" "$INSTALLER"
grep -Fq "SUDOERS_SHA='59b8f1576b5276e5bdb0678b963d9092497e006dbb7ea5e4a49cbf5ab0b7fd9e'" "$INSTALLER"
! grep -Fq "OBSOLETE_HELPER_SHA" "$INSTALLER"
grep -Fq 'DEPLOY_EXECUTED=NO PRODUCTION_ACCESSED=NO' "$INSTALLER"
grep -Fq "die 'UNEXPECTED_EXISTING_HELPER_HASH'" "$INSTALLER"
helper_guard_line=$(grep -n "UNEXPECTED_EXISTING_HELPER_HASH" "$INSTALLER" | head -1 | cut -d: -f1)
sudoers_write_line=$(grep -n 'tmp_sudoers=' "$INSTALLER" | head -1 | cut -d: -f1)
[[ "$helper_guard_line" -lt "$sudoers_write_line" ]]
! grep -Fq 'vision_erp' "$INSTALLER"
! grep -Fq 'erp.visiontrading.biz' "$INSTALLER"

echo 'M026_PRODUCTION_HELPER_TEST=PASS'
