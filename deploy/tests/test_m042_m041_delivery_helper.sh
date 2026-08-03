#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
HELPER="$ROOT/deploy/tools/chinaplanetos_m041_delivery.sh"
DISPATCH="$ROOT/deploy/tools/chinaplanetos_m041_ssh_dispatch.sh"
INSTALLER="$ROOT/deploy/tools/install_m041_delivery_helper.sh"
SUDOERS="$ROOT/deploy/config/codex-deploy-m041-delivery.sudoers"
STAGING_PUB="$ROOT/deploy/config/m041-staging-deploy.pub"
PRODUCTION_PUB="$ROOT/deploy/config/m041-production-deploy.pub"
APPROVAL_PUB="$ROOT/deploy/config/m041-production-approval-public.pem"
WORKFLOW="$ROOT/.github/workflows/deploy-m041.yml"
ROLLBACK_WORKFLOW="$ROOT/.github/workflows/rollback-m041.yml"
PROVIDER_TEST="$ROOT/deploy/tests/test_m041_translation_provider.php"
RUNTIME_MANIFEST="$ROOT/deploy/releases/m041-runtime-files.txt"
SUPPORT_MANIFEST="$ROOT/deploy/releases/m041-support-files.txt"
RELEASE_COMMIT='47ceae0d56848c7badbfbcca4abc9e7af3badbc0'
ARTIFACT_SHA='6ee49c1fb298aaa248d56ce771d37d018001b1fc56c0813234e836d8facc7beb'

fail() { echo "M042_DELIVERY_TEST=FAIL REASON=$1" >&2; exit 1; }
require_literal() { grep -Fq -- "$2" "$1" || fail "MISSING_$(basename "$1")_$2"; }

for file in "$HELPER" "$DISPATCH" "$INSTALLER" "$SUDOERS" "$STAGING_PUB" "$PRODUCTION_PUB" "$APPROVAL_PUB" "$WORKFLOW" "$ROLLBACK_WORKFLOW" "$PROVIDER_TEST" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST"; do
	[[ -f "$file" && ! -L "$file" ]] || fail 'REQUIRED_FILE_INVALID'
done
bash -n "$HELPER"; bash -n "$DISPATCH"; bash -n "$INSTALLER"
ssh-keygen -lf "$STAGING_PUB" >/dev/null; ssh-keygen -lf "$PRODUCTION_PUB" >/dev/null
openssl pkey -pubin -in "$APPROVAL_PUB" -noout >/dev/null 2>&1

require_literal "$HELPER" "readonly RUNTIME_COMMIT='$RELEASE_COMMIT'"
require_literal "$HELPER" "readonly ARTIFACT_SHA='$ARTIFACT_SHA'"
require_literal "$HELPER" "readonly PROVIDER_ENDPOINT='https://api.deepseek.com/chat/completions'"
require_literal "$HELPER" "readonly PROVIDER_MODEL='deepseek-v4-flash'"
require_literal "$HELPER" 'die() { echo "M041_${ENVIRONMENT^^}_FAIL=$1" >&2; return 1; }'
require_literal "$HELPER" 'TARGET_VOLUME_IDENTITY_MISMATCH'
require_literal "$HELPER" 'TARGET_DB_SERVICE_MISMATCH'
require_literal "$HELPER" 'STAGING_ERP_PORT_MISMATCH'
require_literal "$HELPER" 'STAGING_SMTP_PORT_MISMATCH'
require_literal "$HELPER" 'STAGING_MAILPIT_PORT_MISMATCH'
require_literal "$HELPER" 'require_staging_verified'
require_literal "$HELPER" 'PRODUCTION_APPROVAL_SIGNATURE_INVALID'
require_literal "$HELPER" 'PRODUCTION_APPROVAL_EXPIRED_OR_TOO_LONG'
require_literal "$HELPER" 'openssl pkeyutl -verify'
require_literal "$HELPER" 'restore_code_backup && restore_database_backup && verify_code_backup_restored && restore_saved_provider_prestate'
require_literal "$HELPER" 'M041_AUTO_ROLLBACK=PASS'
require_literal "$HELPER" 'restore_database_backup || die'
require_literal "$HELPER" 'ROLLBACK_DATABASE_PRESTATE_VERIFY_FAILED'
require_literal "$HELPER" 'M041_ROLLBACK_REAPPLY=PASS'
require_literal "$HELPER" 'restore_code_snapshot "$reapply"'
require_literal "$HELPER" 'restore_database_file "$reapply_db"'
require_literal "$HELPER" 'restore_provider_snapshot "$provider_reapply_status"'
require_literal "$HELPER" 'M041_PROVIDER_AUTO_ROLLBACK=PASS'
require_literal "$HELPER" 'persist_provider_prestate'
require_literal "$HELPER" 'PROVIDER_ACCEPTANCE_FAILED'
require_literal "$HELPER" 'recreate_target_service '\''PRESENT'\'''
require_literal "$HELPER" 'candidate_checks'
require_literal "$HELPER" 'exec 9>"$LOCK_FILE"; flock -n 9'
require_literal "$HELPER" 'APPLY=NOOP_ALREADY_APPLIED'
require_literal "$HELPER" 'ORIGINAL_BACKUPS_PRESERVED=YES'
require_literal "$HELPER" 'EXISTING_APPLY_STATE_RUNTIME_DRIFT'
require_literal "$HELPER" 'KEY=REDACTED'
require_literal "$HELPER" 'ARTIFACT_PATH_UNSAFE'
require_literal "$HELPER" 'ARTIFACT_SYMLINK_FORBIDDEN'
require_literal "$HELPER" 'ARTIFACT_TYPE_FORBIDDEN'
require_literal "$HELPER" 'ARTIFACT_ALLOWLIST_MISMATCH'
require_literal "$PROVIDER_TEST" 'OUTPUT_REDACTED=YES'
if grep -Eq 'sk-[A-Za-z0-9_-]{20,}' "$HELPER" "$DISPATCH" "$INSTALLER" "$WORKFLOW" "$PROVIDER_TEST"; then fail 'POSSIBLE_EMBEDDED_API_KEY'; fi

expected_sudoers=$(mktemp)
tmp_root=$(mktemp -d)
trap 'rm -f "$expected_sudoers"; if [[ -n "${tmp_root:-}" && "$tmp_root" == /tmp/* && -d "$tmp_root" ]]; then rm -rf "$tmp_root"; fi' EXIT
for spec in 'cp-m041-staging staging' 'cp-m041-production production'; do
	read -r account environment <<<"$spec"
	for command in compare preflight apply verify rollback status configure-provider provider-status provider-test; do
		printf '%s ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-m041-delivery %s-%s\n' "$account" "$environment" "$command" >>"$expected_sudoers"
	done
done
diff -u "$expected_sudoers" "$SUDOERS" >/dev/null || fail 'SUDOERS_COMMAND_SURFACE_MISMATCH'
[[ $(wc -l <"$SUDOERS" | tr -d ' ') == '18' ]] || fail 'SUDOERS_LINE_COUNT'
if grep -Eq 'codex-deploy|NOPASSWD:[[:space:]]+ALL|SETENV|\*|\?' "$SUDOERS"; then fail 'SUDOERS_BROAD_AUTHORITY'; fi

require_literal "$DISPATCH" 'cp-m041-staging'
require_literal "$DISPATCH" 'cp-m041-production'
require_literal "$DISPATCH" 'SSH_ORIGINAL_COMMAND'
require_literal "$DISPATCH" 'upload-artifact)'
require_literal "$DISPATCH" 'upload-provider-key)'
require_literal "$DISPATCH" 'upload-production-approval-message)'
require_literal "$DISPATCH" 'upload-production-approval-signature)'
require_literal "$DISPATCH" 'COMMAND_NOT_ALLOWED'
require_literal "$DISPATCH" 'exec sudo -n "$HELPER" "${original#run }"'
require_literal "$DISPATCH" 'rm -f "$tmp"; fail '\''UPLOAD_TOO_LARGE'\'''
require_literal "$DISPATCH" 'rm -f "$tmp"; fail '\''UPLOAD_HASH_MISMATCH'\'''
if grep -Eq '\beval\b|bash -c|sh -c|SSH_ORIGINAL_COMMAND.*exec|trap .*RETURN' "$DISPATCH"; then fail 'DISPATCH_ARBITRARY_EXECUTION_OR_UNSAFE_CLEANUP'; fi

awk '/recreate_target_service '\''PRESENT'\''/ {recreated=1; next} recreated && /candidate_checks/ {recopied=1; exit} END {exit recopied ? 0 : 1}' "$HELPER" || fail 'PROVIDER_CANDIDATE_NOT_RECOPIED_AFTER_RECREATE'

for spec in \
	"HELPER_SHA $HELPER" \
	"DISPATCH_SHA $DISPATCH" \
	"SUDOERS_SHA $SUDOERS" \
	"STAGING_PUB_SHA $STAGING_PUB" \
	"PRODUCTION_PUB_SHA $PRODUCTION_PUB" \
	"APPROVAL_PUB_SHA $APPROVAL_PUB"
do
	read -r name source <<<"$spec"
	require_literal "$INSTALLER" "readonly $name='$(sha256sum "$source" | awk '{print $1}')'"
done
require_literal "$INSTALLER" "ensure_account 'cp-m041-staging'"
require_literal "$INSTALLER" "ensure_account 'cp-m041-production'"
require_literal "$INSTALLER" 'restrict,command='
require_literal "$INSTALLER" 'SUDO_AUDIT_BROAD_AUTHORITY'
require_literal "$INSTALLER" 'FORCED_COMMAND=YES DEPLOY_EXECUTED=NO PROVIDER_CONFIGURED=NO'

require_literal "$WORKFLOW" "vars.M041_AUTODEPLOY_ENABLED == 'true'"
require_literal "$WORKFLOW" 'environment: backup-erp'
require_literal "$WORKFLOW" 'environment: production'
require_literal "$WORKFLOW" 'DEPLOY_USER: cp-m041-staging'
require_literal "$WORKFLOW" 'DEPLOY_USER: cp-m041-production'
require_literal "$WORKFLOW" "inputs.confirmation == 'DEPLOY_M041_47CEAE0_TO_PRODUCTION'"
require_literal "$WORKFLOW" "RELEASE_COMMIT: $RELEASE_COMMIT"
require_literal "$WORKFLOW" "RELEASE_ARTIFACT_SHA: $ARTIFACT_SHA"
require_literal "$WORKFLOW" 'secrets.DEEPSEEK_API_KEY'
require_literal "$WORKFLOW" 'secrets.PRODUCTION_APPROVAL_PRIVATE_KEY'
require_literal "$WORKFLOW" 'openssl pkeyutl -sign -rawin'
require_literal "$WORKFLOW" 'upload-production-approval-signature'
require_literal "$WORKFLOW" 'run staging-verify'
require_literal "$WORKFLOW" 'run production-apply'
require_literal "$WORKFLOW" 'run production-rollback'
require_literal "$WORKFLOW" 'needs: staging'
require_literal "$WORKFLOW" "echo 'did_mutate=true' >> \"\$GITHUB_OUTPUT\""
require_literal "$WORKFLOW" "echo 'did_mutate=false' >> \"\$GITHUB_OUTPUT\""
require_literal "$WORKFLOW" "steps.apply.outputs.did_mutate == 'true'"
if grep -Fq "failure() && steps.apply.outcome == 'success'" "$WORKFLOW"; then fail 'NOOP_RERUN_CAN_TRIGGER_FULL_ROLLBACK'; fi
if grep -Eq '\bscp\b|sudo -n|codex-deploy@|DEPLOY_USER: codex-deploy' "$WORKFLOW"; then fail 'WORKFLOW_BYPASSES_FORCED_COMMAND'; fi
require_literal "$ROLLBACK_WORKFLOW" "inputs.confirmation == 'ROLLBACK_M041_47CEAE0_FROM_PRODUCTION'"
require_literal "$ROLLBACK_WORKFLOW" 'environment: production'
require_literal "$ROLLBACK_WORKFLOW" 'DEPLOY_USER: cp-m041-production'
require_literal "$ROLLBACK_WORKFLOW" 'secrets.PRODUCTION_APPROVAL_PRIVATE_KEY'
require_literal "$ROLLBACK_WORKFLOW" 'production-rollback'
require_literal "$ROLLBACK_WORKFLOW" 'upload-production-approval-signature'
if grep -Eq '\bscp\b|sudo -n|codex-deploy@|DEPLOY_USER: codex-deploy' "$ROLLBACK_WORKFLOW"; then fail 'ROLLBACK_WORKFLOW_BYPASSES_FORCED_COMMAND'; fi

# Reproduce the helper's corrected Bash failure semantics: returning failure
# triggers ERR and runs recovery, while the former explicit exit did not.
trap_probe="$tmp_root/trap-probe"
set +e
(
	set -Ee
	mutated=0
	die_probe() { return 1; }
	trap 'rc=$?; trap - ERR; if [[ $mutated -eq 1 ]]; then printf recovered >"$trap_probe"; fi; exit $rc' ERR
	mutated=1
	die_probe
) 2>/dev/null
probe_rc=$?
set -e
[[ $probe_rc -ne 0 ]] || fail 'ERR_TRAP_PROBE_DID_NOT_FAIL'
[[ $(<"$trap_probe") == 'recovered' ]] || fail 'ERR_TRAP_RECOVERY_SEMANTICS'

artifact="$tmp_root/m041-47ceae0.tar.gz"
cd "$ROOT"
git archive --format=tar "$RELEASE_COMMIT" \
	$(cat "$RUNTIME_MANIFEST") \
	$(cat "$SUPPORT_MANIFEST") \
	deploy/releases/m041-runtime-files.txt \
	deploy/releases/m041-support-files.txt | gzip -n >"$artifact"
[[ $(sha256sum "$artifact" | awk '{print $1}') == "$ARTIFACT_SHA" ]] || fail 'ARTIFACT_NOT_REPRODUCIBLE'
[[ $(tar -tzf "$artifact" | wc -l | tr -d ' ') == '38' ]] || fail 'ARTIFACT_ENTRY_COUNT'
[[ -z $(tar -tzf "$artifact" | awk '/^\// || /(^|\/)\.\.($|\/)/ {print; exit}') ]] || fail 'ARTIFACT_PATH_UNSAFE'
[[ -z $(tar -tvzf "$artifact" | awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}') ]] || fail 'ARTIFACT_TYPE_UNSAFE'

echo "M042_DELIVERY_TEST=PASS COMMIT=$RELEASE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SUDO_COMMANDS=18 DEDICATED_USERS=2 PRODUCTION_SIGNATURE=REQUIRED FAILURE_RECOVERY=CHECKED SECRET_OUTPUT=REDACTED"
