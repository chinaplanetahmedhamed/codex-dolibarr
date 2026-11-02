#!/usr/bin/env bash
set -Eeuo pipefail
test "$#" = 1
case "$1" in preflight|apply|verify|rollback|status) ;; *) exit 1 ;; esac
export M119_ACTION="$1"
python3 - <<'PY'
import json,os,pathlib
action=os.environ['M119_ACTION']
sha='d57aa2ef03b0e3640f4b2a9d020f39057a54b081e52f88d1794304d5ebed8e4d'
path=pathlib.Path(os.environ['RUNNER_TEMP'])/'m119'/('request-'+action+'.json')
data={'schema':1,'action':action,'mission_id':'M119','commit':'25866ad685e65c23dbeeca692c8719498047a97c','artifact_sha256':sha,'artifact_name':'artifact-'+sha+'.tar.gz','manifest_name':'manifest-'+sha+'.json'}
with path.open('x') as f:json.dump(data,f)
path.chmod(0o600)
PY
scp -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 "$RUNNER_TEMP/m119/request-$1.json" "codex-deploy@31.97.158.178:/home/codex-deploy/staging-inbox/request-m119-menu-r13-$1.json"
# The remote shell text is a fixed allowlist choice, never user-controlled text or a path.
case "$1" in
  preflight) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-menu-r13-preflight.json && mv -T /home/codex-deploy/staging-inbox/request-m119-menu-r13-preflight.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy preflight' ;;
  apply) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-menu-r13-apply.json && mv -T /home/codex-deploy/staging-inbox/request-m119-menu-r13-apply.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy apply' ;;
  verify) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-menu-r13-verify.json && mv -T /home/codex-deploy/staging-inbox/request-m119-menu-r13-verify.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy verify' ;;
  rollback) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-menu-r13-rollback.json && mv -T /home/codex-deploy/staging-inbox/request-m119-menu-r13-rollback.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy rollback' ;;
  status) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-menu-r13-status.json && mv -T /home/codex-deploy/staging-inbox/request-m119-menu-r13-status.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy status' ;;
esac
ssh -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 codex-deploy@31.97.158.178 "$remote"
