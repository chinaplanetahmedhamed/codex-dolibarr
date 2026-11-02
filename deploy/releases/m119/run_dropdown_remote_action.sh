#!/usr/bin/env bash
set -Eeuo pipefail
test "$#" = 1
case "$1" in preflight|apply|verify|rollback|status) ;; *) exit 1 ;; esac
export M119_ACTION="$1"
python3 - <<'PY'
import json,os,pathlib
action=os.environ['M119_ACTION']
sha='575052cc666655db1cd56e1b9dcfcd1d52e9b021ff81dc12c74ed96cf5e6b190'
path=pathlib.Path(os.environ['RUNNER_TEMP'])/'m119'/('request-'+action+'.json')
data={'schema':1,'action':action,'mission_id':'M119','commit':'819dac01d1e63ecce106062017e3c7194eb1b766','artifact_sha256':sha,'artifact_name':'artifact-'+sha+'.tar.gz','manifest_name':'manifest-'+sha+'.json'}
with path.open('x') as f:json.dump(data,f)
path.chmod(0o600)
PY
scp -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 "$RUNNER_TEMP/m119/request-$1.json" "codex-deploy@31.97.158.178:/home/codex-deploy/staging-inbox/request-m119-dropdown-r09-$1.json"
# The remote shell text is a fixed allowlist choice, never user-controlled text or a path.
case "$1" in
  preflight) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-preflight.json && mv -T /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-preflight.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy preflight' ;;
  apply) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-apply.json && mv -T /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-apply.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy apply' ;;
  verify) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-verify.json && mv -T /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-verify.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy verify' ;;
  rollback) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-rollback.json && mv -T /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-rollback.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy rollback' ;;
  status) remote='chmod 0600 /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-status.json && mv -T /home/codex-deploy/staging-inbox/request-m119-dropdown-r09-status.json /home/codex-deploy/staging-inbox/request.json && sudo -n /usr/local/sbin/chinaplanetos-backup-erp-deploy status' ;;
esac
ssh -o BatchMode=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=10 codex-deploy@31.97.158.178 "$remote"
