#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$repo_root/deploy/tools/chinaplanetos_production_m062_ports.sh"
installer="$repo_root/deploy/tools/install_production_m062_ports_helper.sh"
sudoers="$repo_root/deploy/config/codex-deploy-production-m062-ports.sudoers"
builder="$repo_root/deploy/tools/build_m062_production_artifact.py"
artifact=${M062_PRODUCTION_ARTIFACT:-/private/tmp/m062-production-a84c9ac.tar.gz}

fail() { echo "M062_PRODUCTION_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

bash -n "$helper" || fail HELPER_SYNTAX
bash -n "$installer" || fail INSTALLER_SYNTAX
[[ $(sha256 "$helper") == d10f813110c3047bed4ec7040824491e88ac545fc73227905b9e30034e50df53 ]] || fail HELPER_HASH
[[ $(sha256 "$sudoers") == 4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf ]] || fail SUDOERS_HASH
[[ $(sha256 "$installer") == 59d006e55247a786780446a691e7fe0c86e5b8858b1631294cfad5a2d9e459c1 ]] || fail INSTALLER_HASH
grep -Fq "readonly HELPER_SHA='d10f813110c3047bed4ec7040824491e88ac545fc73227905b9e30034e50df53'" "$installer" || fail INSTALLER_HELPER_PIN
grep -Fq "readonly PREVIOUS_HELPER_SHA='ABSENT'" "$installer" || fail INSTALLER_ABSENT_GATE
grep -Fq "readonly SUDOERS_SHA='4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf'" "$installer" || fail INSTALLER_SUDOERS_PIN
grep -Fq "readonly ARTIFACT_SHA='a96314b98e2d5bdb630376373a076f90ab36370cc93675b4a5ef3d76a09d7f2b'" "$helper" || fail ARTIFACT_PIN
grep -Fq "readonly RUNTIME_COMMIT='a84c9ac0444dc9f15b1730cceb921d99354cfeab'" "$helper" || fail COMMIT_PIN
grep -Fq "TARGET_CONTAINER='vision_erp'" "$helper" || fail ERP_TARGET
grep -Fq "DB_CONTAINER='vision_db'" "$helper" || fail DB_TARGET
grep -Fq "TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper" || fail CUSTOM_VOLUME
grep -Fq "DB_VOLUME='vision-os_mariadb_data'" "$helper" || fail DB_VOLUME
grep -Fq "TARGET_HTTP='https://erp.visiontrading.biz'" "$helper" || fail HTTPS_TARGET
grep -Fq "STATE_ROOT='/var/lib/chinaplanetos-m062-ports-production-r01'" "$helper" || fail STATE_ROOT
grep -Fq 'Approved M062 rollback is non-destructive' "$helper" || fail ROLLBACK_POLICY
if grep -Eq 'erp-staging|backup-erp\.visiontrading\.biz|codex_staging|/opt/codex-dolibarr-staging' "$helper" "$installer" "$sudoers"; then fail STAGING_TARGET_PRESENT; fi

[[ $(wc -l <"$sudoers" | tr -d ' ') -eq 6 ]] || fail SUDOERS_COUNT
for command in compare preflight apply verify rollback status; do
  [[ $(grep -Fxc "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m062-ports $command" "$sudoers") -eq 1 ]] || fail "SUDOERS_${command^^}"
done

[[ -f "$artifact" && ! -L "$artifact" ]] || fail ARTIFACT_MISSING
[[ $(sha256 "$artifact") == a96314b98e2d5bdb630376373a076f90ab36370cc93675b4a5ef3d76a09d7f2b ]] || fail ARTIFACT_HASH
[[ $(tar -tzf "$artifact" | wc -l | tr -d ' ') -eq 19 ]] || fail ARTIFACT_COUNT
[[ -z $(tar -tzf "$artifact" | awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\.DS_Store/ || /PaxHeader/ || /\/\._/ {print; exit}') ]] || fail ARTIFACT_PATH
[[ -z $(tar -tvzf "$artifact" | awk 'substr($1,1,1)!="-" {print; exit}') ]] || fail ARTIFACT_TYPE

test_root=$(mktemp -d /private/tmp/m062-production-helper-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
for dir in inbox target code-backups db-backups state state/requests state/container-root artifacts; do mkdir -p "$test_root/$dir"; done
python3 "$builder" --output "$test_root/rebuilt.tar.gz" >/dev/null || fail ARTIFACT_REBUILD
cmp -s "$artifact" "$test_root/rebuilt.tar.gz" || fail ARTIFACT_NOT_REPRODUCIBLE
cp "$artifact" "$test_root/inbox/m062-production-a84c9ac.tar.gz"
chmod 0644 "$test_root/inbox/m062-production-a84c9ac.tar.gz"
export M062_DEPLOY_HELPER_TEST_MODE=1 M062_DEPLOY_HELPER_TEST_ROOT="$test_root" M062_DEPLOY_HELPER_LIBRARY_MODE=1
helper_copy="$test_root/chinaplanetos-production-m062-ports.test"
cp "$helper" "$helper_copy"
# shellcheck source=/dev/null
source "$helper_copy"
check_environment
prepare_inbox_candidate
[[ ${#RUNTIME_FILES[@]} -eq 8 && ${#SUPPORT_FILES[@]} -eq 8 ]] || fail CANDIDATE_COUNTS
for dependency in "$TRANSLATION_DEPENDENCY" "$PRICE_DEPENDENCY"; do
  target="$TARGET_ROOT/${dependency#custom/}"
  mkdir -p "$(dirname "$target")"
  cp "$CANDIDATE_ROOT/$dependency" "$target"
  chmod 0644 "$target"
done
runtime_dependency_ready || fail DEPENDENCY_READY
printf '\ncorrupt\n' >>"$TARGET_ROOT/${PRICE_DEPENDENCY#custom/}"
if runtime_dependency_ready; then fail DEPENDENCY_DRIFT_ACCEPTED; fi
cleanup_paths

echo 'M062_PRODUCTION_HELPER_CONTRACT=PASS TARGET=PRODUCTION_ONLY ARTIFACT_FILES=19 RUNTIME_FILES=8 SUPPORT_FILES=8 DEPENDENCIES=PINNED ROLLBACK=NON_DESTRUCTIVE COMMANDS=6'
