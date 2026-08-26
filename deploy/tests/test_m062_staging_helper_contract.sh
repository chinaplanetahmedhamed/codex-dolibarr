#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$repo_root/deploy/tools/chinaplanetos_backup_erp_m062_ports.sh"
installer="$repo_root/deploy/tools/install_backup_erp_m062_ports_helper.sh"
sudoers="$repo_root/deploy/config/codex-deploy-backup-erp-m062-ports.sudoers"
builder="$repo_root/deploy/tools/build_m062_artifact.py"
report="$repo_root/docs/REPORTS/M062_COMPLETION_REPORT.md"
artifact=${M062_ARTIFACT:-/private/tmp/m062-port-normalization-f697431.tar.gz}

fail() { echo "M062_STAGING_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

bash -n "$helper" || fail HELPER_SYNTAX
bash -n "$installer" || fail INSTALLER_SYNTAX
[[ $(sha256 "$helper") == 34fad8e6e04304b5661206a1b7562e09641431b82deb06f658aa1fae6efc2c87 ]] || fail HELPER_HASH
[[ $(sha256 "$sudoers") == 958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231 ]] || fail SUDOERS_HASH
[[ $(sha256 "$installer") == b3c542155e4557c80989a14b3df2c76fbedbf0efe54b38949eec9e814e2408c8 ]] || fail INSTALLER_HASH
grep -Fq "readonly HELPER_SHA='34fad8e6e04304b5661206a1b7562e09641431b82deb06f658aa1fae6efc2c87'" "$installer" || fail INSTALLER_HELPER_PIN
grep -Fq "readonly SUDOERS_SHA='958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231'" "$installer" || fail INSTALLER_SUDOERS_PIN
[[ $(grep -n 'GLOBAL_SUDOERS_PRECHECK_FAILED' "$installer" | cut -d: -f1) -lt $(grep -n 'mv "$sudoers_stage" "$TARGET_SUDOERS"' "$installer" | cut -d: -f1) ]] || fail INSTALLER_PRECHECK_ORDER
! grep -Fq 'PREVIOUS_HELPER_SHA' "$installer" || fail INSTALLER_UNPINNED_UPGRADE
grep -Fq "readonly ARTIFACT_SHA='6200712277c4af51fb81bb40ea3ead9ad811035547d179128f029ea028b55e0e'" "$helper" || fail ARTIFACT_PIN
grep -Fq "readonly RUNTIME_COMMIT='f697431b4ebf646bc2196e131e98e3bd53568e3e'" "$helper" || fail COMMIT_PIN
grep -Fq "STATE_ROOT='/var/lib/chinaplanetos-m062-ports-staging-r01'" "$helper" || fail STATE_ISOLATION
grep -Fq 'Approved M062 rollback is non-destructive' "$helper" || fail ROLLBACK_POLICY
grep -Fq 'SCHEMA_RETAINED=YES DATA_RETAINED=YES DATABASE_MUTATED_BY_COMMAND=NO' "$helper" || fail ROLLBACK_OUTPUT
grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper" || fail ERP_TARGET
grep -Fq "DB_CONTAINER='erp-staging-mariadb-1'" "$helper" || fail DB_TARGET
grep -Fq "TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper" || fail CUSTOM_VOLUME
grep -Fq "DB_VOLUME='erp-staging_erp-staging_mariadb_data'" "$helper" || fail DB_VOLUME
if grep -Eq 'vision_erp|vision_db|vision-os_|codex_staging|/opt/codex-dolibarr-staging|https://erp\.visiontrading\.biz' "$helper" "$installer" "$sudoers"; then fail FORBIDDEN_TARGET; fi
if grep -Eq '/root/backup-erp-(code|db|release)-backups|/var/lib/chinaplanetos-m062-ports' "$installer"; then fail INSTALLER_SCOPE_EXPANDED; fi
grep -Fq 'b3c542155e4557c80989a14b3df2c76fbedbf0efe54b38949eec9e814e2408c8' "$report" || fail INSTALLER_HASH_NOT_RECORDED

[[ $(wc -l <"$sudoers" | tr -d ' ') -eq 6 ]] || fail SUDOERS_COUNT
for command in compare preflight apply verify rollback status; do
  [[ $(grep -Fxc "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m062-ports $command" "$sudoers") -eq 1 ]] || fail "SUDOERS_${command^^}"
done

[[ -f "$artifact" && ! -L "$artifact" ]] || fail ARTIFACT_MISSING
[[ $(sha256 "$artifact") == 6200712277c4af51fb81bb40ea3ead9ad811035547d179128f029ea028b55e0e ]] || fail ARTIFACT_HASH
[[ $(tar -tzf "$artifact" | wc -l | tr -d ' ') -eq 14 ]] || fail ARTIFACT_COUNT
[[ -z $(tar -tzf "$artifact" | awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\.DS_Store/ || /PaxHeader/ || /\/\._/ {print; exit}') ]] || fail ARTIFACT_PATH
[[ -z $(tar -tvzf "$artifact" | awk 'substr($1,1,1)!="-" {print; exit}') ]] || fail ARTIFACT_TYPE

if [[ -d /private/tmp ]]; then test_root=$(mktemp -d /private/tmp/m062-ports-helper-test.XXXXXX); else test_root=$(mktemp -d /tmp/m062-ports-helper-test.XXXXXX); fi
trap 'rm -rf "$test_root"' EXIT
for dir in inbox target code-backups db-backups state state/requests state/container-root artifacts; do mkdir -p "$test_root/$dir"; done
python3 "$builder" --output "$test_root/rebuilt.tar.gz" >/dev/null || fail ARTIFACT_REBUILD
cmp -s "$artifact" "$test_root/rebuilt.tar.gz" || fail ARTIFACT_NOT_REPRODUCIBLE
cp "$artifact" "$test_root/inbox/m062-port-normalization-f697431.tar.gz"
chmod 0644 "$test_root/inbox/m062-port-normalization-f697431.tar.gz"
export M062_DEPLOY_HELPER_TEST_MODE=1 M062_DEPLOY_HELPER_TEST_ROOT="$test_root" M062_DEPLOY_HELPER_LIBRARY_MODE=1
helper_copy="$test_root/chinaplanetos-backup-erp-m062-ports.test"
cp "$helper" "$helper_copy"
# shellcheck source=/dev/null
source "$helper_copy"
check_environment
prepare_inbox_candidate
[[ ${#RUNTIME_FILES[@]} -eq 7 && ${#SUPPORT_FILES[@]} -eq 4 ]] || fail CANDIDATE_COUNTS
cleanup_paths

echo 'M062_STAGING_HELPER_CONTRACT=PASS TARGET=BACKUP_ERP_ONLY ARTIFACT_FILES=14 RUNTIME_FILES=7 SUPPORT_FILES=4 ROLLBACK=NON_DESTRUCTIVE COMMANDS=6 PRODUCTION_ACCESSED=NO'
