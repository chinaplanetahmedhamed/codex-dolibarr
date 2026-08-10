#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd -P)
cd "$root"

helper='deploy/tools/chinaplanetos_production_m046_translation_policy.sh'
installer='deploy/tools/install_production_m046_helper.sh'
sudoers='deploy/config/codex-deploy-production-m046-preflight.sudoers'
workflow='.github/workflows/prepare-m046-production.yml'
runtime_manifest='deploy/releases/m046-c01-runtime-files.txt'
support_manifest='deploy/releases/m046-c01-support-files.txt'

die() { echo "M046_PRODUCTION_RELEASE_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

for file in "$helper" "$installer" "$sudoers" "$workflow" "$runtime_manifest" "$support_manifest"; do
  [[ -f "$file" && ! -L "$file" ]] || die 'REQUIRED_FILE_INVALID'
done

bash -n "$helper" "$installer"
[[ $(sha256 "$helper") == '0324870d9bdcfe8659508cb702f3ddbcc7c8ca27d854504d738e3fb1d29bad58' ]] || die 'HELPER_HASH_INVALID'
[[ $(sha256 "$sudoers") == '18a7dfd57efe44ea961ec45af13c589b090eb4c626145b11ea03bc5bb58fe17d' ]] || die 'SUDOERS_HASH_INVALID'
[[ $(sha256 "$runtime_manifest") == '69d754158a34b54dbf3186a2ba69c7a9a2c958a5f43b3e0605462cc470c7f798' ]] || die 'RUNTIME_MANIFEST_HASH_INVALID'
[[ $(sha256 "$support_manifest") == '6c64b2f9441a1a3fdbb8950f295209f973c34c7e788d05b7303a5c5c7e4ec47b' ]] || die 'SUPPORT_MANIFEST_HASH_INVALID'
[[ $(wc -l <"$runtime_manifest" | tr -d ' ') == 6 ]] || die 'RUNTIME_FILE_COUNT_INVALID'

grep -Fq "readonly RUNTIME_COMMIT='15c19dda5842be992e6926db473878042a0a96d5'" "$helper" || die 'RUNTIME_COMMIT_PIN_INVALID'
grep -Fq "readonly ARTIFACT_SHA='79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596'" "$helper" || die 'ARTIFACT_PIN_INVALID'
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper" || die 'PRODUCTION_CONTAINER_PIN_INVALID'
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper" || die 'PRODUCTION_VOLUME_PIN_INVALID'
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper" || die 'PRODUCTION_HTTP_PIN_INVALID'
if grep -Eq 'backup-erp|erp-staging|18088|11025|18025' "$helper"; then die 'STAGING_IDENTIFIER_PRESENT'; fi
compare_body=$(sed -n '/^command_compare()/,/^}/p' "$helper")
preflight_body=$(sed -n '/^command_preflight()/,/^}/p' "$helper")
if grep -Eq 'candidate_checks|runtime_policy_check|docker cp|docker exec.*(mkdir|rm)' <<<"$compare_body$preflight_body"; then die 'PREFLIGHT_CONTAINER_WRITE_PRESENT'; fi
grep -Fq 'stored_policy_row_check' <<<"$compare_body" || die 'COMPARE_POLICY_ROW_CHECK_MISSING'
grep -Fq 'stored_policy_row_check' <<<"$preflight_body" || die 'PREFLIGHT_POLICY_ROW_CHECK_MISSING'

grep -Fq "readonly HELPER_SHA='0324870d9bdcfe8659508cb702f3ddbcc7c8ca27d854504d738e3fb1d29bad58'" "$installer" || die 'INSTALLER_HELPER_PIN_INVALID'
grep -Fq "readonly SUDOERS_SHA='18a7dfd57efe44ea961ec45af13c589b090eb4c626145b11ea03bc5bb58fe17d'" "$installer" || die 'INSTALLER_SUDOERS_PIN_INVALID'
grep -Fq "readonly TARGET_HELPER='/usr/local/sbin/chinaplanetos-production-m046-translation-policy'" "$installer" || die 'INSTALLER_TARGET_INVALID'
grep -Fq "readonly TARGET_SUDOERS='/etc/sudoers.d/codex-deploy-production-m046-preflight'" "$installer" || die 'INSTALLER_SUDOERS_TARGET_INVALID'

[[ $(wc -l <"$sudoers" | tr -d ' ') == 3 ]] || die 'SUDOERS_LINE_COUNT_INVALID'
for command in compare preflight status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m046-translation-policy $command" "$sudoers" || die 'SUDOERS_COMMAND_MISSING'
done
if grep -Eq ' (apply|verify|rollback)( |$)' "$sudoers"; then die 'MUTATING_SUDO_COMMAND_PRESENT'; fi

grep -Fq 'PREPARE_M046_PRODUCTION_PREFLIGHT' "$workflow" || die 'WORKFLOW_CONFIRMATION_INVALID'
grep -Fq 'environment: erp-production' "$workflow" || die 'WORKFLOW_ENVIRONMENT_INVALID'
for command in compare preflight status; do
  grep -Fq "sudo -n \$DEPLOY_HELPER $command" "$workflow" || die 'WORKFLOW_COMMAND_MISSING'
done
if grep -Eq 'sudo -n \$DEPLOY_HELPER (apply|verify|rollback)( |\")' "$workflow"; then die 'WORKFLOW_MUTATING_COMMAND_PRESENT'; fi
if grep -Eq 'backup-erp|erp-staging|DEPLOY_M046_C01_TO_BACKUP_ERP' "$workflow"; then die 'WORKFLOW_STAGING_IDENTIFIER_PRESENT'; fi

echo 'M046_PRODUCTION_RELEASE_CONTRACT=PASS RUNTIME_COMMIT=15c19dda5842be992e6926db473878042a0a96d5 ARTIFACT_SHA=79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596 FILES=6 SUDO_COMMANDS=3 APPLY_EXPOSED=NO DATABASE_CHANGED=NO'
