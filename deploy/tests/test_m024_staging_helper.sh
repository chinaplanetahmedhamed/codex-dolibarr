#!/usr/bin/env bash
set -Eeuo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_backup_erp_m024_shipment_collaboration.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-backup-erp-m024-shipment-collaboration.sudoers"

bash -n "$HELPER"
[[ $(stat -f '%Lp' "$HELPER" 2>/dev/null || stat -c '%a' "$HELPER") =~ ^(644|755)$ ]]
grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$HELPER"
grep -Fq "TARGET_DB_CONTAINER='erp-staging-mariadb-1'" "$HELPER"
grep -Fq "TARGET_VOLUME='erp-staging_erp-staging_custom'" "$HELPER"
grep -Fq "TARGET_HTTP='http://127.0.0.1:18088/'" "$HELPER"
grep -Fq "RUNTIME_COMMIT='66656e31c6bba13fa258e14d1b1a37e2c9c5477a'" "$HELPER"
grep -Fq "ARTIFACT_SHA='efccb3d7411559f55ef033f90183d4bbf17d328f024ac861d6dec68b5cc5e97a'" "$HELPER"
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
grep -Fq "M024_STAGING_DEMO_VERIFY=PASS" "$HELPER"
! grep -Eq '(^|[^A-Za-z])(rm -rf|docker compose down|DROP DATABASE|TRUNCATE TABLE)([^A-Za-z]|$)' "$HELPER"

[[ $(wc -l < "$SUDOERS" | tr -d ' ') -eq 9 ]]
for command in compare preflight apply verify seed-demo verify-demo cleanup-demo rollback status; do
	grep -Fqx "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m024-shipment-collaboration-deploy $command" "$SUDOERS"
done
! grep -Eq '(ALL=\(ALL|NOPASSWD: ALL|\*)' "$SUDOERS"

echo 'M024_STAGING_HELPER_TEST=PASS'
