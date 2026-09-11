#!/usr/bin/env bash
set -Eeuo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_production_m076_workspace.sh"
installer="$root/deploy/tools/install_production_m076_workspace_helper.sh"
sudoers="$root/deploy/config/codex-deploy-production-m076-workspace.sudoers"
fail(){ echo "M076_PRODUCTION_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }

bash -n "$helper" || fail HELPER_SYNTAX
bash -n "$installer" || fail INSTALLER_SYNTAX
grep -Fq "readonly RUNTIME_COMMIT='f37e24ba6ff9e671a02c7fb143d8aa49e095c074'" "$helper" || fail COMMIT_PIN
grep -Fq "readonly ARTIFACT_SHA='6ca22405b3acdba1f664ac7b384d324faf5484f7dfe2374dc0b6c0603e15187a'" "$helper" || fail ARTIFACT_PIN
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper" || fail CONTAINER_PIN
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper" || fail VOLUME_PIN
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper" || fail HTTP_PIN
grep -Fq 'PRODUCTION_DRIFT_HASH' "$helper" || fail HASH_DRIFT_GUARD
grep -Fq 'PRODUCTION_DRIFT_ABSENT' "$helper" || fail ABSENCE_DRIFT_GUARD
grep -Fq 'run_prerequisites' "$helper" || fail PREREQUISITE_GATE
grep -Fq 'M076_PRODUCTION_AUTO_ROLLBACK=PASS' "$helper" || fail AUTO_ROLLBACK
grep -Fq 'install -o root -g root -m 0600 "$ARTIFACT" "$CANDIDATE_ARTIFACT"' "$helper" || fail ROOT_PRIVATE_ARTIFACT_COPY
grep -Fq 'tar -tzf "$CANDIDATE_ARTIFACT"' "$helper" || fail PRIVATE_ARTIFACT_VALIDATION
if grep -Fq 'docker cp' "$helper"; then fail READ_ONLY_COMMAND_CONTAINER_COPY; fi
grep -Fq "DEPLOYED|BACKED_UP|APPLY_FAILED_RECOVERY_REQUIRED" "$helper" || fail EMERGENCY_ROLLBACK_STATES
grep -Fq 'command_rollback(){ check_target_root;' "$helper" || fail ROLLBACK_HTTP_INDEPENDENCE
grep -Fq 'die(){ echo "M076_PRODUCTION_FAIL=$1" >&2; return 1; }' "$helper" || fail ERR_TRAP_COMPATIBLE_DIE
grep -Fq 'ROLLBACK_REQUIRED_BEFORE_RETRY' "$helper" || fail ORIGINAL_BACKUP_PRESERVATION
grep -Fq 'rm -f -- "$target" || return 1' "$helper" || fail RESTORE_REMOVE_CHECK
grep -Fq 'grep -Fxq "$rel" "$ABSENT_LIST"' "$helper" || fail ABSENT_RESTORE_VERIFICATION
grep -Fq 'state_stage="$STATE_FILE.stage.$$"' "$helper" || fail ATOMIC_STATE_STAGE
grep -Fq 'mv -f "$state_stage" "$STATE_FILE"' "$helper" || fail ATOMIC_STATE_REPLACE
grep -Fq 'apply_files; live_checks; save_state DEPLOYED; mutated=0; trap - ERR' "$helper" || fail DEPLOYED_STATE_UNDER_ROLLBACK_TRAP
grep -Fq "ACTIVE_INTERNAL_TEAM_MEMBERSHIP_REQUIRED" "$root/deploy/tools/m076_production_preflight.php" || fail REAL_TEAM_GATE
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]] || fail SUDOERS_COMMAND_COUNT
grep -Fq "readonly HELPER_SHA='18c9413864384ffd8700f13441056de58ff7edc03005146411c4ca2a69f01814'" "$installer" || fail HELPER_INSTALL_PIN
grep -Fq "readonly SUDOERS_SHA='311468a7e1ff85dff9324f35e5961d478c8b6d6bf79abfae1d301400ad2d8e03'" "$installer" || fail SUDOERS_INSTALL_PIN
grep -Fq 'PRODUCTION_DATABASE_CHANGED=NO' "$installer" || fail DATABASE_BOUNDARY
echo 'M076_PRODUCTION_HELPER_CONTRACT=PASS TARGET=erp.visiontrading.biz RUNTIME_FILES=7 COMMANDS=6 BASELINE=PINNED BACKUP=SCOPED AUTO_ROLLBACK=YES DATABASE_MUTATED=NO'
