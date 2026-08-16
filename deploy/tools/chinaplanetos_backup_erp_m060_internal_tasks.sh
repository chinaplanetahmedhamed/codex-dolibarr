#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M058-M060-R01'
readonly RUNTIME_COMMIT='579f17e319dc7ac0255e8ea2bb6594eec7aa01d1'
readonly BASELINE_COMMIT='606b54c852230b0674b0745084e107487cf3161d'
readonly SHORT_COMMIT='579f17e'
readonly ARTIFACT_SHA='b8612e76a39da740c5bb9daada75c242c73946016984d01b2d6c2ce6d3927357'
readonly RUNTIME_MANIFEST_SHA='827a221538c3f947cc1b4f81290f99ebd98b3c163b0c03bc66bfb4359676b05f'
readonly SUPPORT_MANIFEST_SHA='0064bd27cefa7edb4d5c51d1dc60edbb1727f0d16c4be0aacdcaeba59807dce5'
readonly BASELINE_MANIFEST_SHA='5e6be92fda17e7554c0d0d403d97abd51ec3d3a56bc49d2b0428b0cdd672e1f3'
readonly RUNTIME_MANIFEST='deploy/releases/m060-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m060-support-files.txt'
readonly BASELINE_MANIFEST='deploy/releases/m060-m056-baseline.sha256'
readonly MIGRATION='deploy/migrations/m060_internal_tasks_schema.php'

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m060-internal-tasks-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly STATE_ROOT='/var/lib/chinaplanetos-m060-staging'
readonly MIN_FREE_KB=1048576

readonly -a STATIC_TESTS=(
  'deploy/tests/test_m058_asset_validation.php'
  'deploy/tests/test_m058_failure_contract.php'
  'deploy/tests/test_m058_source_contract.php'
  'deploy/tests/test_m058_ui_contract.php'
  'deploy/tests/test_m059_source_contract.php'
  'deploy/tests/test_m059_ui_contract.php'
  'deploy/tests/test_m060_source_contract.php'
  'deploy/tests/test_m060_ui_contract.php'
)

RUNTIME_FILES=()
SUPPORT_FILES=()
declare -A BASELINE_HASHES=()
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
SCHEMA_PRESTATE=''
DRIFT_COUNT=0

die() { echo "M060_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m060-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m060-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m060-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m060-internal-tasks' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

check_environment() {
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER" 2>/dev/null) == 'true|erp-staging|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'TARGET_DISK_LOW'
  docker exec "$DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' >/dev/null || die 'DB_DUMP_TOOL_ABSENT'
}

validate_manifest() {
  local manifest=$1 expected_sha=$2 prefix_pattern=$3
  [[ -f "$CANDIDATE_ROOT/$manifest" && ! -L "$CANDIDATE_ROOT/$manifest" ]] || die 'MANIFEST_INVALID'
  [[ $(sha256 "$CANDIDATE_ROOT/$manifest") == "$expected_sha" ]] || die 'MANIFEST_HASH_MISMATCH'
  [[ -z $(awk 'NF==0 || $0 ~ /^\// || $0 ~ /(^|\/)\.\.($|\/)/ || $0 !~ /^[A-Za-z0-9_./-]+$/ {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_ENTRY_INVALID'
  [[ -z $(LC_ALL=C sort "$CANDIDATE_ROOT/$manifest" | uniq -d) ]] || die 'MANIFEST_DUPLICATE_ENTRY'
  [[ -z $(awk -v pattern="$prefix_pattern" '$0 !~ pattern {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_SCOPE_INVALID'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m060-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing expected actual tar_stderr
  listing=$(mktemp); expected=$(mktemp); actual=$(mktemp); tar_stderr=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" 2>"$tar_stderr" || die 'ARTIFACT_TAR_INVALID'
  [[ ! -s "$tar_stderr" ]] || die 'ARTIFACT_TAR_METADATA_UNSUPPORTED'
  [[ $(wc -l <"$listing") -eq 50 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(tar -tvzf "$ARTIFACT_COPY" | awk 'substr($1,1,1)!="-" {print; exit}') ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  CANDIDATE_ROOT=$(mktemp -d /root/m060-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" 2>"$tar_stderr" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ ! -s "$tar_stderr" ]] || die 'ARTIFACT_EXTRACT_METADATA_UNSUPPORTED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ -z $(find "$CANDIDATE_ROOT" ! -type d ! -type f -print -quit) ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  validate_manifest "$RUNTIME_MANIFEST" "$RUNTIME_MANIFEST_SHA" '^custom/chinaplanetoperations/'
  validate_manifest "$SUPPORT_MANIFEST" "$SUPPORT_MANIFEST_SHA" '^deploy/(migrations|tests)/'
  [[ $(sha256 "$CANDIDATE_ROOT/$BASELINE_MANIFEST") == "$BASELINE_MANIFEST_SHA" ]] || die 'BASELINE_MANIFEST_HASH_MISMATCH'
  mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
  mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
  [[ ${#RUNTIME_FILES[@]} -eq 38 && ${#SUPPORT_FILES[@]} -eq 9 ]] || die 'MANIFEST_COUNT_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" "$BASELINE_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  local baseline path
  while read -r baseline path; do
    [[ -n "$baseline" && -n "$path" && ( "$baseline" == 'ABSENT' || "$baseline" =~ ^[0-9a-f]{64}$ ) ]] || die 'BASELINE_ENTRY_INVALID'
    BASELINE_HASHES["$path"]=$baseline
  done <"$CANDIDATE_ROOT/$BASELINE_MANIFEST"
  [[ ${#BASELINE_HASHES[@]} -eq 38 ]] || die 'BASELINE_COUNT_INVALID'
  for path in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$path" && ! -L "$CANDIDATE_ROOT/$path" ]] || die 'ARTIFACT_FILE_INVALID'; done
  for path in "${RUNTIME_FILES[@]}"; do [[ -n ${BASELINE_HASHES[$path]+x} ]] || die 'BASELINE_PATH_MISSING'; done
  rm -f "$listing" "$expected" "$actual" "$tar_stderr"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m060-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
  docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
  local file
  for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
    if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die 'CANDIDATE_LINT_FAILED'; fi
  done
  for file in "${STATIC_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_TEST_FAILED_${file//\//_}"; done
}

schema_prestate() {
  local output rc
  set +e
  output=$(docker exec -e M060_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check 2>&1)
  rc=$?
  set -e
  if [[ $rc -eq 0 && "$output" == 'M060_SCHEMA_CHECK=READY' ]]; then SCHEMA_PRESTATE='READY'; return; fi
  if [[ $rc -eq 2 && "$output" == 'M060_SCHEMA_CHECK=MIGRATION_REQUIRED' ]]; then SCHEMA_PRESTATE='MIGRATION_REQUIRED'; return; fi
  echo "$output" >&2
  die 'SCHEMA_PRESTATE_DRIFT'
}

compare_files() {
  local file target release_hash target_hash baseline state
  DRIFT_COUNT=0
  for file in "${RUNTIME_FILES[@]}"; do
    target="$TARGET_ROOT/${file#custom/}"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file"); baseline=${BASELINE_HASHES[$file]}
    if [[ ! -e "$target" && ! -L "$target" ]]; then
      if [[ "$baseline" == 'ABSENT' ]]; then state='BASELINE_ABSENT'; else state='DRIFT_ABSENT'; ((DRIFT_COUNT+=1)); fi
      echo "M060_STAGING_COMPARE FILE=$file STATE=$state BASELINE_SHA=$baseline RELEASE_SHA=$release_hash"
      continue
    fi
    [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
    target_hash=$(sha256 "$target")
    if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'
    elif [[ "$baseline" != 'ABSENT' && "$target_hash" == "$baseline" ]]; then state='BASELINE_M056'
    else state='DRIFT'; ((DRIFT_COUNT+=1)); fi
    echo "M060_STAGING_COMPARE FILE=$file STATE=$state TARGET_SHA=$target_hash BASELINE_SHA=$baseline RELEASE_SHA=$release_hash"
  done
}

command_compare() {
  check_environment; validate_artifact; candidate_checks; schema_prestate; compare_files
  echo "M060_STAGING_COMPARE=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT BASELINE_COMMIT=$BASELINE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$SCHEMA_PRESTATE DRIFT_COUNT=$DRIFT_COUNT APPLY_EXECUTED=NO PRODUCTION_ACCESSED=NO"
}

command_preflight() {
  check_environment; validate_artifact; candidate_checks; schema_prestate; compare_files
  [[ $DRIFT_COUNT -eq 0 ]] || die 'BASELINE_DRIFT'
  echo "M060_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT BASELINE_COMMIT=$BASELINE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$SCHEMA_PRESTATE APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_status() {
  echo "M060_STAGING_STATUS=PREFLIGHT_ONLY RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA APPLY_AVAILABLE=NO PRODUCTION_ACCESSED=NO"
}

require_root "$@"
case "$1" in
  compare) command_compare ;;
  preflight) command_preflight ;;
  status) command_status ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
