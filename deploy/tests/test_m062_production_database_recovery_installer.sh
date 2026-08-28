#!/usr/bin/env bash
set -Eeuo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
installer="$repo_root/deploy/tools/install_production_m062_database_recovery.sh"
recovery="$repo_root/deploy/tools/recover_production_m062_database.sh"

fail() { echo "M062_PRODUCTION_DB_RECOVERY_INSTALLER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

bash -n "$installer" || fail SYNTAX
recovery_sha=$(sha256 "$recovery")
[[ $(grep -Fxc "readonly SOURCE_SHA='$recovery_sha'" "$installer") == 1 ]] || fail RECOVERY_HASH_PIN
grep -Fq "readonly SOURCE='/home/codex-deploy/staging-inbox/recover-production-m062-database.sh'" "$installer" || fail SOURCE_PATH_PIN
grep -Fq "readonly TARGET='/root/recover-production-m062-database-r03'" "$installer" || fail TARGET_PATH_PIN
grep -Fq "'codex-deploy:codex-deploy 644'" "$installer" || fail SOURCE_METADATA_GATE
grep -Fq "'root:root 700'" "$installer" || fail TARGET_METADATA_GATE
grep -Fq "die 'UNEXPECTED_EXISTING_TARGET'" "$installer" || fail EXISTING_TARGET_GATE
grep -Fq 'RECOVERY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES' "$installer" || fail INSTALL_BOUNDARY
if grep -Eq 'docker|mariadb|mysql|curl|recover-production-m062-database"' "$installer"; then fail EXECUTION_PATH_PRESENT; fi

echo "M062_PRODUCTION_DB_RECOVERY_INSTALLER_CONTRACT=PASS RECOVERY_SHA=$recovery_sha INSTALL_ONLY=YES DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
