#!/usr/bin/env bash
set -Eeuo pipefail
test "$#" = 1
case "$1" in preflight|apply|verify|rollback|status) ;; *) exit 1 ;; esac
export M119_ACTION="$1"
python3 - <<'PY'
import json,os,pathlib
action=os.environ['M119_ACTION']
sha='3f3254ae3f1fb4bd186b8d37afb7f3b7e31826291a18dad24599dcabf417fd1e'
path=pathlib.Path(os.environ['RUNNER_TEMP'])/'m119'/('request-'+action+'.json')
data={'schema':1,'action':action,'mission_id':'M119','commit':'1b88b8869e700ad54a441124e980105ca6313cfa','artifact_sha256':sha,'artifact_name':'artifact-'+sha+'.tar.gz','manifest_name':'manifest-'+sha+'.json'}
with path.open('x') as f:json.dump(data,f)
path.chmod(0o600)
PY
scp -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 "$RUNNER_TEMP/m119/request-$1.json" "codex-deploy@31.97.158.178:/home/codex-deploy/staging-inbox/request-m119-standalone-$1.json"
# The remote shell text is a fixed allowlist choice, never user-controlled text or a path.
case "$1" in
  preflight) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-standalone-preflight.json && mv -T /home/codex-deploy/staging-inbox/request-m119-standalone-preflight.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy preflight' ;;
  apply) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-standalone-apply.json && mv -T /home/codex-deploy/staging-inbox/request-m119-standalone-apply.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy apply' ;;
  verify) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-standalone-verify.json && mv -T /home/codex-deploy/staging-inbox/request-m119-standalone-verify.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy verify' ;;
  rollback) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-standalone-rollback.json && mv -T /home/codex-deploy/staging-inbox/request-m119-standalone-rollback.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy rollback' ;;
  status) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-standalone-status.json && mv -T /home/codex-deploy/staging-inbox/request-m119-standalone-status.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy status' ;;
esac
ssh -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 codex-deploy@31.97.158.178 "$remote"
