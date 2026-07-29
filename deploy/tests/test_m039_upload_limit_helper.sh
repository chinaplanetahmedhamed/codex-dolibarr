#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_m039_upload_limit.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-m039-upload-limit.sudoers"
INSTALLER="$ROOT/deploy/tools/install_chinaplanetos_m039_upload_limit.sh"

bash -n "$HELPER"
bash -n "$INSTALLER"
grep -Fq "readonly COMMIT='851c231cc96f51d2c3fafcb32c0a172e4f9973d5'" "$HELPER"
grep -Fq "readonly ARTIFACT_SHA='076280035b2116bdcccbcac42d76508bd9f03170fde16dff7ecf8b614b9e7809'" "$HELPER"
grep -Fq "readonly EXPECTED_LIMITS='200M|210M|1024M|900|900'" "$HELPER"
grep -Fq "readonly EXACT_UPLOAD_BYTES='209715200'" "$HELPER"
grep -Fq "M039_AUTO_ROLLBACK=PASS" "$HELPER"
grep -Fq "check_before_hashes" "$HELPER"
grep -Fq "require_staging_accepted" "$HELPER"
grep -Fq "PRODUCTION_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'" "$HELPER"
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-m039-upload-limit ' "$SUDOERS") -eq 9 ]]
! grep -Eq '(/bin/(ba)?sh|docker |rm |cp |mv |tar |curl |sudoedit|\*)$' "$SUDOERS"
grep -Fq "readonly HELPER_SHA='4381f20c42abb539f24b8d66a24a464056b13fd867af92849b57c7ee3aef88d5'" "$INSTALLER"
grep -Fq "readonly PREVIOUS_HELPER_SHA='6746e6ab1a9d91a737a15d5198b5ea403765c12f8ff372ff185c94d606abeba3'" "$INSTALLER"
grep -Fq "readonly SUDOERS_SHA='b3cf47e21bc4fa18d8588c7f39290101d91c21c7f04785d5949b08a0673b0e13'" "$INSTALLER"
grep -Fq 'APPLY_EXECUTED=NO' "$INSTALLER"
echo 'M039 upload-limit helper static safety test passed'
