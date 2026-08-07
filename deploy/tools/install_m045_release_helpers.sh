#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

readonly INBOX='/home/codex-deploy/staging-inbox'
[[ $(id -u) -eq 0 ]] || { echo 'M045_RELEASE_HELPERS=FAIL REASON=ROOT_REQUIRED' >&2; exit 1; }
[[ $# -eq 0 ]] || { echo 'M045_RELEASE_HELPERS=FAIL REASON=NO_ARGUMENTS_ALLOWED' >&2; exit 1; }
bash "$INBOX/install_backup_erp_m045_helper.sh"
bash "$INBOX/install_production_m045_helper.sh"
echo 'M045_RELEASE_HELPERS=PASS STAGING=PINNED PRODUCTION=PINNED DEPLOY_EXECUTED=NO'
