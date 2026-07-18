#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
WORKFLOW="$ROOT/.github/workflows/deploy-backup-erp.yml"

test -f "$WORKFLOW"
grep -Fq 'workflow_dispatch:' "$WORKFLOW"
grep -Fq 'environment: backup-erp' "$WORKFLOW"
grep -Fq 'cancel-in-progress: false' "$WORKFLOW"
grep -Fq 'RELEASE_COMMIT: 2c032eb59a4547b72429e07cf730f6e9963cc98e' "$WORKFLOW"
grep -Fq 'CONFIRMATION: ${{ inputs.confirmation }}' "$WORKFLOW"
! grep -Fq "test '\${{ inputs.confirmation }}'" "$WORKFLOW"
grep -Fq 'DEPLOY_USER: codex-deploy' "$WORKFLOW"
grep -Fq 'BACKUP_ERP_SSH_KEY' "$WORKFLOW"
grep -Fq 'BACKUP_ERP_KNOWN_HOSTS' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER preflight' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER apply' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER verify' "$WORKFLOW"
grep -Fq 'sudo -n $DEPLOY_HELPER rollback' "$WORKFLOW"
grep -Fq 'test_m003_portal_hardening.php' "$WORKFLOW"
grep -Fq 'test_m004_shipping_status_sequence.php' "$WORKFLOW"
! grep -Eq '^[[:space:]]+(push|pull_request|schedule):' "$WORKFLOW"
! grep -Eqi 'erp\.visiontrading\.biz|vision_erp|vision_db|vision-os_|/root/vision-os|docker[[:space:]]' "$WORKFLOW"

echo 'backup-erp GitHub Actions workflow static safety test passed'
