#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$repo_root/deploy/tools/chinaplanetos_backup_erp_m062_ports.sh"
installer="$repo_root/deploy/tools/install_backup_erp_m062_ports_helper.sh"
sudoers="$repo_root/deploy/config/codex-deploy-backup-erp-m062-ports.sudoers"
builder="$repo_root/deploy/tools/build_m062_artifact.py"
report="$repo_root/docs/REPORTS/M062_COMPLETION_REPORT.md"
artifact=${M062_ARTIFACT:-/private/tmp/m062-port-normalization-525eca2.tar.gz}

fail() { echo "M062_STAGING_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

bash -n "$helper" || fail HELPER_SYNTAX
bash -n "$installer" || fail INSTALLER_SYNTAX
[[ $(sha256 "$helper") == b6577abf57aeecba67476026b08ad0052931d7b7380fd421b19841768f241174 ]] || fail HELPER_HASH
[[ $(sha256 "$sudoers") == 958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231 ]] || fail SUDOERS_HASH
[[ $(sha256 "$installer") == 56cc432fb924dd014987a5a6eef47ae861c8e63b9fe3d5f4a31e208644f30ab9 ]] || fail INSTALLER_HASH
grep -Fq "readonly HELPER_SHA='b6577abf57aeecba67476026b08ad0052931d7b7380fd421b19841768f241174'" "$installer" || fail INSTALLER_HELPER_PIN
grep -Fq "readonly PREVIOUS_HELPER_SHA='32f62d30e783ae43b0353a9ce66fe10a9ec639788faf4fd6e791d2bde4e666fd'" "$installer" || fail INSTALLER_PREVIOUS_HELPER_PIN
grep -Fq "readonly SUDOERS_SHA='958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231'" "$installer" || fail INSTALLER_SUDOERS_PIN
grep -Fq "die 'EXISTING_HELPER_REQUIRED'" "$installer" || fail INSTALLER_REQUIRES_EXISTING_HELPER
grep -Fq "die 'EXISTING_SUDOERS_REQUIRED'" "$installer" || fail INSTALLER_REQUIRES_EXISTING_SUDOERS
if grep -Eq 'SOURCE_SUDOERS|sudoers_stage|mv .*TARGET_SUDOERS|install .*TARGET_SUDOERS|rm -f .*TARGET_SUDOERS' "$installer"; then fail INSTALLER_SUDOERS_MUTATION; fi
grep -Fq 'SUDOERS_CHANGED=NO' "$installer" || fail INSTALLER_SUDOERS_UNCHANGED_EVIDENCE
grep -Fq "readonly UPGRADE_BACKUP_ROOT='/root/m062-ports-helper-upgrade-backups'" "$installer" || fail INSTALLER_BACKUP_ROOT
grep -Fq "elif [[ \"\$existing_helper_sha\" == \"\$PREVIOUS_HELPER_SHA\" ]]; then upgrading_helper=1" "$installer" || fail INSTALLER_EXACT_UPGRADE_GATE
grep -Fq 'mv -f "$restore_stage" "$TARGET_HELPER"' "$installer" || fail INSTALLER_AUTO_RESTORE
grep -Fq 'mv "$backup_stage" "$PREVIOUS_HELPER_BACKUP"' "$installer" || fail INSTALLER_ATOMIC_BACKUP
grep -Fq "readonly ARTIFACT_SHA='3ec2073c80f8ea99ce01a8c1e975a9c6d0917c0a63b17253d672b43c37a7ac28'" "$helper" || fail ARTIFACT_PIN
grep -Fq "readonly RUNTIME_COMMIT='525eca241a323d4ae073077a940de75d9d3b64e7'" "$helper" || fail COMMIT_PIN
grep -Fq "readonly BASELINE_REFERENCE='95402e7+verified-live-sql-13939d2'" "$helper" || fail BASELINE_REFERENCE
grep -Fq "readonly SUPPORT_DEPENDENCY_SHA='1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54'" "$helper" || fail DEPENDENCY_PIN
grep -Fq "STATE_ROOT='/var/lib/chinaplanetos-m062-ports-staging-r01'" "$helper" || fail STATE_ISOLATION
grep -Fq 'Approved M062 rollback is non-destructive' "$helper" || fail ROLLBACK_POLICY
grep -Fq 'SCHEMA_RETAINED=YES DATA_RETAINED=YES DATABASE_MUTATED_BY_COMMAND=NO' "$helper" || fail ROLLBACK_OUTPUT
grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper" || fail ERP_TARGET
grep -Fq "DB_CONTAINER='erp-staging-mariadb-1'" "$helper" || fail DB_TARGET
grep -Fq "TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper" || fail CUSTOM_VOLUME
grep -Fq "DB_VOLUME='erp-staging_erp-staging_mariadb_data'" "$helper" || fail DB_VOLUME
if grep -Eq 'vision_erp|vision_db|vision-os_|codex_staging|/opt/codex-dolibarr-staging|https://erp\.visiontrading\.biz' "$helper" "$installer" "$sudoers"; then fail FORBIDDEN_TARGET; fi
if grep -Eq '/root/backup-erp-(code|db|release)-backups|/var/lib/chinaplanetos-m062-ports' "$installer"; then fail INSTALLER_SCOPE_EXPANDED; fi
grep -Fq '56cc432fb924dd014987a5a6eef47ae861c8e63b9fe3d5f4a31e208644f30ab9' "$report" || fail INSTALLER_HASH_NOT_RECORDED

[[ $(wc -l <"$sudoers" | tr -d ' ') -eq 6 ]] || fail SUDOERS_COUNT
for command in compare preflight apply verify rollback status; do
  [[ $(grep -Fxc "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m062-ports $command" "$sudoers") -eq 1 ]] || fail "SUDOERS_${command^^}"
done

[[ -f "$artifact" && ! -L "$artifact" ]] || fail ARTIFACT_MISSING
[[ $(sha256 "$artifact") == 3ec2073c80f8ea99ce01a8c1e975a9c6d0917c0a63b17253d672b43c37a7ac28 ]] || fail ARTIFACT_HASH
[[ $(tar -tzf "$artifact" | wc -l | tr -d ' ') -eq 15 ]] || fail ARTIFACT_COUNT
[[ $(tar -tzf "$artifact" | grep -Fxc 'custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php') -eq 1 ]] || fail DEPENDENCY_MEMBER
[[ -z $(tar -tzf "$artifact" | awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\.DS_Store/ || /PaxHeader/ || /\/\._/ {print; exit}') ]] || fail ARTIFACT_PATH
[[ -z $(tar -tvzf "$artifact" | awk 'substr($1,1,1)!="-" {print; exit}') ]] || fail ARTIFACT_TYPE

if [[ -d /private/tmp ]]; then test_root=$(mktemp -d /private/tmp/m062-ports-helper-test.XXXXXX); else test_root=$(mktemp -d /tmp/m062-ports-helper-test.XXXXXX); fi
trap 'rm -rf "$test_root"' EXIT
for dir in inbox target code-backups db-backups state state/requests state/container-root artifacts; do mkdir -p "$test_root/$dir"; done
python3 "$builder" --output "$test_root/rebuilt.tar.gz" >/dev/null || fail ARTIFACT_REBUILD
cmp -s "$artifact" "$test_root/rebuilt.tar.gz" || fail ARTIFACT_NOT_REPRODUCIBLE
cp "$artifact" "$test_root/inbox/m062-port-normalization-525eca2.tar.gz"
chmod 0644 "$test_root/inbox/m062-port-normalization-525eca2.tar.gz"
export M062_DEPLOY_HELPER_TEST_MODE=1 M062_DEPLOY_HELPER_TEST_ROOT="$test_root" M062_DEPLOY_HELPER_LIBRARY_MODE=1
helper_copy="$test_root/chinaplanetos-backup-erp-m062-ports.test"
cp "$helper" "$helper_copy"
# shellcheck source=/dev/null
source "$helper_copy"
check_environment
prepare_inbox_candidate
[[ ${#RUNTIME_FILES[@]} -eq 7 && ${#SUPPORT_FILES[@]} -eq 5 ]] || fail CANDIDATE_COUNTS
dependency_target="$TARGET_ROOT/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php"
mkdir -p "$(dirname "$dependency_target")"
cp "$CANDIDATE_ROOT/$SUPPORT_DEPENDENCY" "$dependency_target"; chmod 0644 "$dependency_target"
runtime_dependency_ready || fail DEPENDENCY_READY
printf '\ncorrupt\n' >>"$dependency_target"
if runtime_dependency_ready; then fail DEPENDENCY_DRIFT_ACCEPTED; fi
cleanup_paths

echo 'M062_STAGING_HELPER_CONTRACT=PASS TARGET=BACKUP_ERP_ONLY ARTIFACT_FILES=15 RUNTIME_FILES=7 SUPPORT_FILES=5 DEPENDENCY=PINNED ROLLBACK=NON_DESTRUCTIVE COMMANDS=6 PRODUCTION_ACCESSED=NO'
