#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$repo_root/deploy/tools/chinaplanetos_production_m062_ports.sh"
installer="$repo_root/deploy/tools/install_production_m062_ports_helper.sh"
sudoers="$repo_root/deploy/config/codex-deploy-production-m062-ports.sudoers"
builder="$repo_root/deploy/tools/build_m062_production_artifact.py"
artifact=${M062_PRODUCTION_ARTIFACT:-/private/tmp/m062-production-8dab3b9.tar.gz}

fail() { echo "M062_PRODUCTION_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

bash -n "$helper" || fail HELPER_SYNTAX
bash -n "$installer" || fail INSTALLER_SYNTAX
[[ $(sha256 "$helper") == a1a769dbdb4f4cdfb0f304a54566a98aad73710ba1a2054d8c026919a8892cc4 ]] || fail HELPER_HASH
[[ $(sha256 "$sudoers") == 4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf ]] || fail SUDOERS_HASH
[[ $(sha256 "$installer") == 94b33fb3b9f76e1a6c986de2a7b772fd95117d35f035ebf5cee6484af9ed1e37 ]] || fail INSTALLER_HASH
grep -Fq "readonly HELPER_SHA='a1a769dbdb4f4cdfb0f304a54566a98aad73710ba1a2054d8c026919a8892cc4'" "$installer" || fail INSTALLER_HELPER_PIN
grep -Fq "readonly PREVIOUS_HELPER_SHA='2006eab431cca65127dcea495d3fbc262ed840fad38129a235ae0364790cb049'" "$installer" || fail INSTALLER_PREVIOUS_HELPER_PIN
grep -Fq "readonly UPGRADE_BACKUP_ROOT='/root/m062-production-helper-upgrade-backups'" "$installer" || fail INSTALLER_BACKUP_ROOT
grep -Fq 'PREVIOUS_HELPER_BACKUP_HASH_MISMATCH' "$installer" || fail INSTALLER_BACKUP_HASH_GATE
grep -Fq "die 'EXISTING_HELPER_REQUIRED'" "$installer" || fail INSTALLER_REQUIRES_INSTALLED_HELPER
grep -Fq "die 'EXISTING_SUDOERS_REQUIRED'" "$installer" || fail INSTALLER_REQUIRES_INSTALLED_SUDOERS
grep -Fq 'install -o root -g root -m 0755 "$PREVIOUS_HELPER_BACKUP" "$restore_stage"' "$installer" || fail INSTALLER_AUTOMATIC_RESTORE
grep -Fq 'SUDOERS_CHANGED=NO' "$installer" || fail INSTALLER_SUDOERS_UNCHANGED_MARKER
if grep -Eq 'mv .*TARGET_SUDOERS|install .*TARGET_SUDOERS|rm -f .*TARGET_SUDOERS' "$installer"; then fail INSTALLER_SUDOERS_MUTATION_PATH; fi
grep -Fq "readonly SUDOERS_SHA='4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf'" "$installer" || fail INSTALLER_SUDOERS_PIN
grep -Fq "readonly ARTIFACT_SHA='add7cbc918c1af489f3b107bf2156eb6b9debb0c43f76a936e782c4cfe21aae7'" "$helper" || fail ARTIFACT_PIN
grep -Fq "readonly RUNTIME_COMMIT='8dab3b90ba335ad99b5d61d12c731021fc92f1d7'" "$helper" || fail COMMIT_PIN
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
[[ $(sha256 "$artifact") == add7cbc918c1af489f3b107bf2156eb6b9debb0c43f76a936e782c4cfe21aae7 ]] || fail ARTIFACT_HASH
[[ $(tar -tzf "$artifact" | wc -l | tr -d ' ') -eq 19 ]] || fail ARTIFACT_COUNT
[[ -z $(tar -tzf "$artifact" | awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\.DS_Store/ || /PaxHeader/ || /\/\._/ {print; exit}') ]] || fail ARTIFACT_PATH
[[ -z $(tar -tvzf "$artifact" | awk 'substr($1,1,1)!="-" {print; exit}') ]] || fail ARTIFACT_TYPE

test_root=$(mktemp -d /private/tmp/m062-production-helper-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
for dir in inbox target code-backups db-backups state state/requests state/container-root artifacts; do mkdir -p "$test_root/$dir"; done
python3 "$builder" --output "$test_root/rebuilt.tar.gz" >/dev/null || fail ARTIFACT_REBUILD
cmp -s "$artifact" "$test_root/rebuilt.tar.gz" || fail ARTIFACT_NOT_REPRODUCIBLE
cp "$artifact" "$test_root/inbox/m062-production-8dab3b9.tar.gz"
chmod 0644 "$test_root/inbox/m062-production-8dab3b9.tar.gz"
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
for rel in "${RUNTIME_FILES[@]}"; do
  baseline=$(awk -v p="$rel" '$2==p {print $1}' "$CANDIDATE_ROOT/$BASELINE_MANIFEST")
  target="$TARGET_ROOT/${rel#custom/}"
  if [[ "$baseline" == ABSENT ]]; then
    rm -f "$target"
  else
    mkdir -p "$(dirname "$target")"
    baseline_commit='95402e7f22665c9efd61d648f1e545094aaf47c9'
    if [[ "$rel" == 'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql' ]]; then
      baseline_commit='2dff2fd76d8d4f6b5690c13828c3fcd58f2499b7'
    fi
    git -C "$repo_root" show "$baseline_commit:$rel" >"$target"
    chmod 0644 "$target"
  fi
done
compare_files >/dev/null
[[ $DRIFT_COUNT -eq 0 && $BASELINE_COUNT -eq 8 && $CURRENT_COUNT -eq 0 && $MIXED_STATE -eq 0 ]] || fail EXACT_BASELINE_REJECTED
mixed_rel='custom/chinaplanetshippingprices/admin/adjust.php'
cp "$CANDIDATE_ROOT/$mixed_rel" "$TARGET_ROOT/${mixed_rel#custom/}"
compare_files >/dev/null
[[ $DRIFT_COUNT -eq 1 && $BASELINE_COUNT -eq 7 && $CURRENT_COUNT -eq 1 && $MIXED_STATE -eq 1 ]] || fail MIXED_PRESTATE_ACCEPTED
printf '\ncorrupt\n' >>"$TARGET_ROOT/${PRICE_DEPENDENCY#custom/}"
if runtime_dependency_ready; then fail DEPENDENCY_DRIFT_ACCEPTED; fi
cleanup_paths

echo 'M062_PRODUCTION_HELPER_CONTRACT=PASS TARGET=PRODUCTION_ONLY ARTIFACT_FILES=19 RUNTIME_FILES=8 SUPPORT_FILES=8 DEPENDENCIES=PINNED ROLLBACK=NON_DESTRUCTIVE COMMANDS=6'
