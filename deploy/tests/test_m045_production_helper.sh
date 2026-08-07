#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_production_m045.sh"
installer="$root/deploy/tools/install_production_m045_helper.sh"
sudoers="$root/deploy/config/codex-deploy-production-m045.sudoers"

bash -n "$helper"
bash -n "$installer"
grep -Fq "readonly RELEASE='M045-P01'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='bd553a72eadeaa0437105060ea7cdafc8f11ecdd'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='2b01b0b4bc31c0f1e8ee80110f953ddb3b6b0c16e5e99d5de5e19b0b8e1b073f'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper"
grep -Fq "readonly DB_CONTAINER='vision_db'" "$helper"
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper"
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper"
[[ $(grep -Fc 'restore_database && db_ok=1' "$helper") -eq 2 ]]
grep -Fq '[[ $code_ok -eq 1 && $db_ok -eq 1 ]]' "$helper"
grep -Fq 'M045_PRODUCTION_AUTO_ROLLBACK=PASS' "$helper"
grep -Fq "'<form id=\"login\"'" "$helper"
grep -Fq "'name=\"actionlogin\"'" "$helper"
if grep -Eq 'erp-staging|backup-erp|18088|11025|18025' "$helper"; then
  echo 'M045_PRODUCTION_HELPER_TEST=FAIL REASON=STAGING_IDENTIFIER' >&2
  exit 1
fi
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m045-deploy $command" "$sudoers"
done
grep -Fq "readonly HELPER_SHA='0a4c6c9beddd1fa15b9dfb1f49c19c80c656a7e31124639283599f430cb840d6'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='1483ccb33685fc7e5394eead6221bfc760be4614fba393a42394f23a2e5fa299'" "$installer"
grep -Fq "readonly SUDOERS_SHA='47a75fdfd7d8dd458c0ca98e172ad750daa5b41cf7f05c571330b2b2b805d576'" "$installer"
echo 'M045_PRODUCTION_HELPER_TEST=PASS TARGET=ERP_PRODUCTION_ONLY COMMANDS=6'
