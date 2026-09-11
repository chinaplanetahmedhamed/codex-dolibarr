#!/usr/bin/env bash
set -Eeuo pipefail
root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_production_m076_workspace.sh"
installer="$root/deploy/tools/install_production_m076_workspace_helper.sh"
sudoers="$root/deploy/config/codex-deploy-production-m076-workspace.sudoers"
fail(){ echo "M076_PRODUCTION_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }

bash -n "$helper" || fail HELPER_SYNTAX
bash -n "$installer" || fail INSTALLER_SYNTAX
grep -Fq "readonly RUNTIME_COMMIT='374e53bcf8b04e2a4ff8c0a39f065ff3a93a6540'" "$helper" || fail COMMIT_PIN
grep -Fq "readonly ARTIFACT_SHA='bf38c200292bbd7a122bee6e43ddef5f778476c2798956c69840ea662c9c9b27'" "$helper" || fail ARTIFACT_PIN
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper" || fail CONTAINER_PIN
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper" || fail VOLUME_PIN
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper" || fail HTTP_PIN
grep -Fq 'PRODUCTION_DRIFT_HASH' "$helper" || fail HASH_DRIFT_GUARD
grep -Fq 'PRODUCTION_DRIFT_ABSENT' "$helper" || fail ABSENCE_DRIFT_GUARD
grep -Fq 'run_prerequisites' "$helper" || fail PREREQUISITE_GATE
grep -Fq 'M076_PRODUCTION_AUTO_ROLLBACK=PASS' "$helper" || fail AUTO_ROLLBACK
grep -Fq "ACTIVE_INTERNAL_TEAM_MEMBERSHIP_REQUIRED" "$root/deploy/tools/m076_production_preflight.php" || fail REAL_TEAM_GATE
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]] || fail SUDOERS_COMMAND_COUNT
grep -Fq "readonly HELPER_SHA='b18e13aa86d9fa2d4ada273d1c696c5c23c5e9c59afa69bea44108e934306d9f'" "$installer" || fail HELPER_INSTALL_PIN
grep -Fq "readonly SUDOERS_SHA='311468a7e1ff85dff9324f35e5961d478c8b6d6bf79abfae1d301400ad2d8e03'" "$installer" || fail SUDOERS_INSTALL_PIN
grep -Fq 'PRODUCTION_DATABASE_CHANGED=NO' "$installer" || fail DATABASE_BOUNDARY
echo 'M076_PRODUCTION_HELPER_CONTRACT=PASS TARGET=erp.visiontrading.biz RUNTIME_FILES=7 COMMANDS=6 BASELINE=PINNED BACKUP=SCOPED AUTO_ROLLBACK=YES DATABASE_MUTATED=NO'
