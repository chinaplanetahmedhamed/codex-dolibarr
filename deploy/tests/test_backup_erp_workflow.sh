#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
WORKFLOW="$ROOT/.github/workflows/deploy-backup-erp.yml"

test -f "$WORKFLOW"
grep -Fq 'workflow_dispatch:' "$WORKFLOW"
grep -Fq 'environment: backup-erp' "$WORKFLOW"
grep -Fq 'cancel-in-progress: false' "$WORKFLOW"
grep -Fq 'DELIVERY_COMMIT: 37b8e26d1488511462941652f857c5a79d4091ff' "$WORKFLOW"
grep -Fq 'RUNTIME_COMMIT: 334c46c4316c8e75c1535e75f772a44e24cb80a7' "$WORKFLOW"
grep -Fq 'ARTIFACT_SHA: 8dd4e625190d2c33de98e9e902bc6f34589b4b8d731f4833462a9a8b2a30224e' "$WORKFLOW"
grep -Fq 'CONFIRMATION: ${{ inputs.confirmation }}' "$WORKFLOW"
! grep -Fq "test '\${{ inputs.confirmation }}'" "$WORKFLOW"
grep -Fq 'DEPLOY_USER: codex-deploy' "$WORKFLOW"
grep -Fq 'DEPLOY_SSH_KEY' "$WORKFLOW"
grep -Fq 'DEPLOY_KNOWN_HOSTS' "$WORKFLOW"
grep -Fq 'M043_API_KEY' "$WORKFLOW"
grep -Fq 'M043_RATE_TOKEN_KEY' "$WORKFLOW"
grep -Fq 'M043_REQUEST_HMAC_KEY' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER preflight' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER apply' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER verify' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER rollback' "$WORKFLOW"
grep -Fq 'test_m043_gateway_migration.php' "$WORKFLOW"
grep -Fq 'test_m043_public_rate_token.php' "$WORKFLOW"
grep -Fq 'test_m043_public_website_intake_source.php' "$WORKFLOW"
grep -Fq 'test_m043_staging_helper.sh' "$WORKFLOW"
! grep -Eq '^[[:space:]]+(push|pull_request|schedule):' "$WORKFLOW"
! grep -Eqi 'erp\.visiontrading\.biz|vision_erp|vision_db|vision-os_|/root/vision-os|docker[[:space:]]|environment:[[:space:]]+production' "$WORKFLOW"

echo 'backup-erp M043 GitHub Actions workflow static safety test passed'
