#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
workflow="$root/.github/workflows/deploy-m044.yml"

test -f "$workflow"
grep -Fq 'workflow_dispatch:' "$workflow"
grep -Fq 'DELIVERY_COMMIT: 3d94f2d53309c5fe3e4406be432f3e460bbc7eca' "$workflow"
grep -Fq 'RUNTIME_COMMIT: bea5c9798f2001473a8458a54b1e31821843875b' "$workflow"
grep -Fq 'ARTIFACT_SHA: ce65bc0ce331ead157d4a09ea814bdaef7faf6f1277a1d7a1f54cfb1ab7b5cbd' "$workflow"
grep -Fq "test \"\$CONFIRMATION\" = 'DEPLOY_M044_TO_BACKUP_ERP'" "$workflow"
grep -Fq "test \"\$CONFIRMATION\" = 'DEPLOY_M044_TO_ERP_PRODUCTION'" "$workflow"
grep -Fq 'environment: backup-erp' "$workflow"
grep -Fq 'environment: production' "$workflow"
grep -Fq 'sudo -n $DEPLOY_HELPER staging-verify' "$workflow"
grep -Fq 'sudo -n $DEPLOY_HELPER production-preflight' "$workflow"
grep -Fq 'sudo -n $DEPLOY_HELPER production-apply' "$workflow"
grep -Fq 'sudo -n $DEPLOY_HELPER production-verify' "$workflow"
grep -Fq 'sudo -n $DEPLOY_HELPER production-rollback' "$workflow"
grep -Fq 'M043_API_KEY' "$workflow"
grep -Fq 'M043_RATE_TOKEN_KEY' "$workflow"
grep -Fq 'M043_REQUEST_HMAC_KEY' "$workflow"
grep -Fq 'DEPLOY_SSH_KEY' "$workflow"
grep -Fq 'DEPLOY_KNOWN_HOSTS' "$workflow"
grep -Fq "= '32'" "$workflow"
[[ $(grep -F 'fetch-depth: 0' "$workflow" | wc -l | tr -d ' ') -eq 2 ]]
grep -Fq 'cancel-in-progress: false' "$workflow"
! grep -Eq '^[[:space:]]+(push|pull_request|schedule):' "$workflow"

echo 'M044_WORKFLOW_TEST=PASS STAGING_GATE=YES PRODUCTION_GATE=YES'
