#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M068-R01'
readonly RUNTIME_COMMIT='32e82ffbd82248f2a84f468172195267fb6d37a7'
readonly SHORT_COMMIT='32e82ff'
readonly BASELINE_RELEASE='M062-R04'
readonly BASELINE_RUNTIME_COMMIT='f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b'
readonly BASELINE_SCOPE_COMMIT='3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67'
readonly BASELINE_ARTIFACT_SHA='a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c'
readonly ARTIFACT_SHA='369ba510238edc1d67bf8f2d64b14a51c680070b717806fc4029527e2ff19e46'
readonly RUNTIME_MANIFEST_SHA='5b3b8ef2e1cc714860df71b089d4dda0e73cc5eb58b11c41add936144b0fb15a'
readonly SUPPORT_MANIFEST_SHA='f8c02594f11b3f0b377d42c39bb636fd27339a5230e0f434d27d4428cf389ad9'
readonly BASELINE_MANIFEST_SHA='dbcd63d986dc3862419e806c6b900d8e5fcc3cddf7909739ea8df16ae888d3cf'
readonly DEPENDENCY_MANIFEST_SHA='926277d8cf5f89cdf1724b46ab83ed8949691e918681c5e3797fd7c28dac50b7'
readonly RUNTIME_MANIFEST='deploy/releases/m068-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m068-support-files.txt'
readonly BASELINE_MANIFEST='deploy/releases/m068-m062-r04-baseline.sha256'
readonly DEPENDENCY_MANIFEST='deploy/releases/m068-m062-r04-dependencies.sha256'
readonly MIGRATION='deploy/migrations/m068_website_lead_quotation_portal_lifecycle.php'
readonly CONFIG_TEST='deploy/tests/test_m068_staging_configuration.php'
readonly ARTIFACT_NAME="m068-website-lifecycle-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly DOCS_VOLUME='erp-staging_erp-staging_docs'
readonly DB_VOLUME='erp-staging_erp-staging_mariadb_data'
readonly DB_NAME='dolibarr_db'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BASELINE_STATE_FILE='/var/lib/chinaplanetos-m062-terminal-trend-staging-r04/m062-r04.state'
readonly EXPECTED_MEMBERS=35
readonly EXPECTED_RUNTIME_FILES=26
readonly EXPECTED_SUPPORT_FILES=5
readonly EXPECTED_DEPENDENCIES=5
readonly MAX_ARTIFACT_BYTES=2097152
readonly MIN_FREE_KB=1048576

TEST_MODE=0
if [[ ${M068_PREFLIGHT_HELPER_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M068_PREFLIGHT_HELPER_TEST_ROOT:-}
  case "$test_root" in /tmp/m068-preflight-helper-test.*|/private/tmp/m068-preflight-helper-test.*) ;; *) echo 'M068_PREFLIGHT_FAIL=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" && $(basename "$0") == 'chinaplanetos-backup-erp-m068-preflight.test' ]] || { echo 'M068_PREFLIGHT_FAIL=TEST_MODE_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  INBOX="$test_root/inbox"
  TARGET_ROOT="$test_root/target"
  STATE_FILE="$test_root/m062-state/m062-r04.state"
else
  INBOX='/home/codex-deploy/staging-inbox'
  TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
  STATE_FILE="$BASELINE_STATE_FILE"
fi
readonly TEST_MODE INBOX TARGET_ROOT STATE_FILE
readonly ARTIFACT="${INBOX}/${ARTIFACT_NAME}"

CANDIDATE_ROOT=''
ARTIFACT_COPY=''
CANDIDATE_REMOTE=''
DRIFT_COUNT=0
DEPENDENCY_DRIFT_COUNT=0
SCHEMA_STATUS='UNKNOWN'
CONFIG_STATUS='UNKNOWN'

die() { echo "M068_PREFLIGHT_FAIL=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
mode_of() { if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Lp' "$1"; else stat -c '%a' "$1"; fi; }
owner_group_of() { if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Su:%Sg' "$1"; else stat -c '%U:%G' "$1"; fi; }

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /root/m068-preflight-candidate.* && $TEST_MODE -eq 0 ]]; then
    docker exec "$TARGET_CONTAINER" sh -eu -c 'case "$1" in /root/m068-preflight-candidate.*) [ -d "$1" ] && [ ! -L "$1" ] && [ "$(stat -c "%U:%G %a" "$1")" = "root:root 700" ]; rm -rf -- "$1";; *) exit 1;; esac' sh "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
  fi
  if [[ $TEST_MODE -eq 1 ]]; then
    [[ -z "$CANDIDATE_ROOT" || "$CANDIDATE_ROOT" != "$test_root"/candidate.* || ! -d "$CANDIDATE_ROOT" ]] || rm -rf -- "$CANDIDATE_ROOT"
    [[ -z "$ARTIFACT_COPY" || "$ARTIFACT_COPY" != "$test_root"/artifact.* || ! -f "$ARTIFACT_COPY" ]] || rm -f -- "$ARTIFACT_COPY"
  else
    [[ -z "$CANDIDATE_ROOT" || "$CANDIDATE_ROOT" != /root/m068-preflight-candidate.* || ! -d "$CANDIDATE_ROOT" ]] || rm -rf -- "$CANDIDATE_ROOT"
    [[ -z "$ARTIFACT_COPY" || "$ARTIFACT_COPY" != /root/m068-preflight-artifact.* || ! -f "$ARTIFACT_COPY" ]] || rm -f -- "$ARTIFACT_COPY"
  fi
  exit "$rc"
}
trap cleanup EXIT

require_invocation() {
  [[ $TEST_MODE -eq 1 || $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(basename "$0") == 'chinaplanetos-backup-erp-m068-preflight' ]] || die 'HELPER_PATH_NOT_ALLOWED'
  fi
  case "$1" in compare|preflight|status) ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
}

state_value() {
  local key=$1 count value
  count=$(grep -c "^${key}=" "$STATE_FILE" || true)
  [[ "$count" -eq 1 ]] || die 'BASELINE_STATE_INVALID'
  value=$(awk -F= -v key="$key" '$1==key {sub(/^[^=]*=/, ""); print}' "$STATE_FILE")
  [[ "$value" =~ ^[A-Za-z0-9._:/+-]+$ ]] || die 'BASELINE_STATE_VALUE_INVALID'
  printf '%s' "$value"
}

validate_baseline_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'BASELINE_STATE_ABSENT'
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(owner_group_of "$STATE_FILE") == 'root:root' && $(mode_of "$STATE_FILE") == 600 ]] || die 'BASELINE_STATE_METADATA_INVALID'
  fi
  [[ $(state_value STATE_RELEASE) == "$BASELINE_RELEASE" ]] || die 'BASELINE_RELEASE_MISMATCH'
  [[ $(state_value STATE_STATUS) == 'DEPLOYED' ]] || die 'BASELINE_NOT_DEPLOYED'
  [[ $(state_value STATE_RUNTIME_COMMIT) == "$BASELINE_RUNTIME_COMMIT" ]] || die 'BASELINE_RUNTIME_MISMATCH'
  [[ $(state_value STATE_SCOPE_COMMIT) == "$BASELINE_SCOPE_COMMIT" ]] || die 'BASELINE_SCOPE_MISMATCH'
  [[ $(state_value STATE_ARTIFACT_SHA) == "$BASELINE_ARTIFACT_SHA" ]] || die 'BASELINE_ARTIFACT_MISMATCH'
}

check_environment() {
  [[ -d "$INBOX" && ! -L "$INBOX" && -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'RELEASE_ROOT_INVALID'
  if [[ $TEST_MODE -eq 1 ]]; then validate_baseline_state; return; fi
  [[ $(owner_group_of "$INBOX") == 'codex-deploy:codex-deploy' && $(mode_of "$INBOX") == 700 ]] || die 'INBOX_METADATA_INVALID'
  [[ $(owner_group_of "$TARGET_ROOT") == 'www-data:www-data' && $(mode_of "$TARGET_ROOT") == 755 ]] || die 'TARGET_ROOT_METADATA_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/documents"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${DOCS_VOLUME}|true" ]] || die 'DOCS_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}' "$DOCS_VOLUME" 2>/dev/null) == 'local' ]] || die 'DOCS_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER" 2>/dev/null) == 'true|erp-staging|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$DB_CONTAINER") == "${DB_VOLUME}|true" ]] || die 'DB_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}' "$DB_VOLUME" 2>/dev/null) == 'local' ]] || die 'DB_VOLUME_MISMATCH'
  [[ -z $(docker port "$DB_CONTAINER" 2>/dev/null) ]] || die 'DB_PORT_EXPOSED'
  [[ $(docker exec "$DB_CONTAINER" sh -eu -c 'printf %s "${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}"') == "$DB_NAME" ]] || die 'DB_NAME_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'TARGET_DISK_LOW'
  validate_baseline_state
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(owner_group_of "$ARTIFACT") == 'codex-deploy:codex-deploy' && $(mode_of "$ARTIFACT") == 644 ]] || die 'ARTIFACT_METADATA_INVALID'
    ARTIFACT_COPY=$(mktemp /root/m068-preflight-artifact.XXXXXX)
    expected_uid=$(id -u codex-deploy) || die 'CODEX_DEPLOY_USER_INVALID'
    CANDIDATE_ROOT=$(mktemp -d /root/m068-preflight-candidate.XXXXXX)
  else
    ARTIFACT_COPY=$(mktemp "$test_root/artifact.XXXXXX")
    expected_uid=$(id -u)
    CANDIDATE_ROOT=$(mktemp -d "$test_root/candidate.XXXXXX")
  fi
  python3 - "$ARTIFACT" "$ARTIFACT_COPY" "$MAX_ARTIFACT_BYTES" "$expected_uid" "$TEST_MODE" <<'PY' || die 'ARTIFACT_SECURE_COPY_FAILED'
import os, stat, sys
source, target, maximum, expected_uid, test_mode = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4]), int(sys.argv[5])
source_fd = os.open(source, os.O_RDONLY | getattr(os, 'O_NOFOLLOW', 0))
try:
    info = os.fstat(source_fd)
    if not stat.S_ISREG(info.st_mode) or info.st_uid != expected_uid or stat.S_IMODE(info.st_mode) != 0o644 or info.st_size < 1 or info.st_size > maximum:
        raise SystemExit(1)
    target_fd = os.open(target, os.O_WRONLY | os.O_TRUNC | getattr(os, 'O_NOFOLLOW', 0))
    try:
        total = 0
        while True:
            chunk = os.read(source_fd, min(131072, maximum + 1 - total))
            if not chunk:
                break
            total += len(chunk)
            if total > maximum:
                raise SystemExit(1)
            view = memoryview(chunk)
            while view:
                written = os.write(target_fd, view)
                view = view[written:]
        if total != info.st_size:
            raise SystemExit(1)
        os.fchmod(target_fd, 0o600)
        if not test_mode:
            os.fchown(target_fd, 0, 0)
        os.fsync(target_fd)
    finally:
        os.close(target_fd)
finally:
    os.close(source_fd)
PY
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  python3 - "$ARTIFACT_COPY" "$CANDIDATE_ROOT" "$EXPECTED_MEMBERS" <<'PY' || die 'ARTIFACT_STRUCTURE_INVALID'
import pathlib, sys, tarfile
archive, target, expected = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2]), int(sys.argv[3])
with tarfile.open(archive, mode='r:gz') as tf:
    members = tf.getmembers()
    names = [m.name for m in members]
    if len(members) != expected or len(set(names)) != expected:
        raise SystemExit(1)
    for member in members:
        parts = pathlib.PurePosixPath(member.name).parts
        if not member.isfile() or member.issym() or member.islnk() or member.name.startswith('/') or '..' in parts:
            raise SystemExit(1)
        if member.uid != 0 or member.gid != 0 or member.uname != 'root' or member.gname != 'root' or member.mode != 0o644:
            raise SystemExit(1)
        if any(part.startswith('._') for part in parts) or '.DS_Store' in parts or member.pax_headers:
            raise SystemExit(1)
    tf.extractall(target)
PY
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$BASELINE_MANIFEST") == "$BASELINE_MANIFEST_SHA" ]] || die 'BASELINE_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$DEPENDENCY_MANIFEST") == "$DEPENDENCY_MANIFEST_SHA" ]] || die 'DEPENDENCY_MANIFEST_HASH_MISMATCH'
  [[ $(wc -l <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST" | tr -d ' ') -eq $EXPECTED_RUNTIME_FILES ]] || die 'RUNTIME_MANIFEST_COUNT_INVALID'
  [[ $(wc -l <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST" | tr -d ' ') -eq $EXPECTED_SUPPORT_FILES ]] || die 'SUPPORT_MANIFEST_COUNT_INVALID'
  [[ $(wc -l <"$CANDIDATE_ROOT/$BASELINE_MANIFEST" | tr -d ' ') -eq $EXPECTED_RUNTIME_FILES ]] || die 'BASELINE_MANIFEST_COUNT_INVALID'
  [[ $(wc -l <"$CANDIDATE_ROOT/$DEPENDENCY_MANIFEST" | tr -d ' ') -eq $EXPECTED_DEPENDENCIES ]] || die 'DEPENDENCY_MANIFEST_COUNT_INVALID'
  local expected actual rel digest state extra
  expected=$(mktemp); actual=$(mktemp)
  cat "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" "$CANDIDATE_ROOT/$SUPPORT_MANIFEST" >"$expected"
  printf '%s\n' "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" "$BASELINE_MANIFEST" "$DEPENDENCY_MANIFEST" >>"$expected"
  LC_ALL=C sort -o "$expected" "$expected"
  (cd "$CANDIDATE_ROOT" && find . -type f -print | sed 's#^\./##' | LC_ALL=C sort) >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  rm -f "$expected" "$actual"
  while IFS= read -r rel; do
    [[ "$rel" =~ ^custom/(chinaplanetcustomercenter|chinaplanetoperations)/[A-Za-z0-9_./-]+$ && -f "$CANDIDATE_ROOT/$rel" ]] || die 'RUNTIME_MANIFEST_SCOPE_INVALID'
  done <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
  while IFS= read -r rel; do
    case "$rel" in
      "$MIGRATION"|deploy/tests/test_m068_lifecycle_source.php|deploy/tests/test_m068_portal_currency_unit.php|deploy/tests/test_m068_portal_invitation_security_source.php|"$CONFIG_TEST") ;;
      *) die 'SUPPORT_MANIFEST_SCOPE_INVALID' ;;
    esac
    [[ -f "$CANDIDATE_ROOT/$rel" ]] || die 'SUPPORT_FILE_ABSENT'
  done <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
  while read -r state rel extra; do
    [[ -z "${extra:-}" && "$rel" =~ ^custom/ && -f "$CANDIDATE_ROOT/$rel" ]] || die 'BASELINE_MANIFEST_SCOPE_INVALID'
    [[ "$state" == ABSENT || "$state" =~ ^[a-f0-9]{64}$ ]] || die 'BASELINE_MANIFEST_VALUE_INVALID'
  done <"$CANDIDATE_ROOT/$BASELINE_MANIFEST"
  while read -r digest rel extra; do
    [[ -z "${extra:-}" && "$digest" =~ ^[a-f0-9]{64}$ && "$rel" =~ ^custom/ ]] || die 'DEPENDENCY_MANIFEST_INVALID'
  done <"$CANDIDATE_ROOT/$DEPENDENCY_MANIFEST"
}

candidate_checks() {
  local rel output rc=0
  if [[ $TEST_MODE -eq 1 ]]; then
    while IFS= read -r rel; do [[ "$rel" != *.php ]] || php -l "$CANDIDATE_ROOT/$rel" >/dev/null || die 'CANDIDATE_PHP_LINT_FAILED'; done < <(cat "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" "$CANDIDATE_ROOT/$SUPPORT_MANIFEST")
    php "$CANDIDATE_ROOT/deploy/tests/test_m068_lifecycle_source.php" | grep -Fq 'M068_LIFECYCLE_SOURCE=PASS' || die 'CANDIDATE_LIFECYCLE_TEST_FAILED'
    php "$CANDIDATE_ROOT/deploy/tests/test_m068_portal_currency_unit.php" | grep -Fq 'M068_PORTAL_CURRENCY=PASS' || die 'CANDIDATE_CURRENCY_TEST_FAILED'
    php "$CANDIDATE_ROOT/deploy/tests/test_m068_portal_invitation_security_source.php" | grep -Fq 'M068_INVITATION_SECURITY_SOURCE=PASS' || die 'CANDIDATE_INVITATION_TEST_FAILED'
    CONFIG_STATUS='TEST_ONLY'
    SCHEMA_STATUS='MIGRATION_REQUIRED'
    return
  fi
  CANDIDATE_REMOTE=$(docker exec "$TARGET_CONTAINER" mktemp -d /root/m068-preflight-candidate.XXXXXX) || die 'CONTAINER_CANDIDATE_CREATE_FAILED'
  [[ "$CANDIDATE_REMOTE" =~ ^/root/m068-preflight-candidate\.[A-Za-z0-9]+$ ]] || die 'CONTAINER_CANDIDATE_PATH_INVALID'
  [[ $(docker exec "$TARGET_CONTAINER" stat -c '%U:%G %a' "$CANDIDATE_REMOTE") == 'root:root 700' ]] || die 'CONTAINER_CANDIDATE_METADATA_INVALID'
  docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE/"
  [[ $(docker exec "$TARGET_CONTAINER" stat -c '%U:%G %a' "$CANDIDATE_REMOTE") == 'root:root 700' ]] || die 'CONTAINER_CANDIDATE_REPLACED'
  while IFS= read -r rel; do [[ "$rel" != *.php ]] || docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_PHP_LINT_FAILED'; done < <(cat "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" "$CANDIDATE_ROOT/$SUPPORT_MANIFEST")
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m068_lifecycle_source.php" | grep -Fq 'M068_LIFECYCLE_SOURCE=PASS' || die 'CANDIDATE_LIFECYCLE_TEST_FAILED'
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m068_portal_currency_unit.php" | grep -Fq 'M068_PORTAL_CURRENCY=PASS' || die 'CANDIDATE_CURRENCY_TEST_FAILED'
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m068_portal_invitation_security_source.php" | grep -Fq 'M068_INVITATION_SECURITY_SOURCE=PASS' || die 'CANDIDATE_INVITATION_TEST_FAILED'
  output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TEST") || die 'CONFIG_PREREQUISITE_FAILED'
  grep -Fq 'M068_STAGING_CONFIGURATION=PASS' <<<"$output" || die 'CONFIG_PREREQUISITE_FAILED'
  CONFIG_STATUS='READY'
  set +e
  output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check 2>&1)
  rc=$?
  set -e
  if [[ $rc -eq 0 ]] && grep -Fq 'M068_SCHEMA_CHECK=PASS' <<<"$output"; then SCHEMA_STATUS='READY';
  elif [[ $rc -eq 2 ]] && grep -Fq 'M068_SCHEMA_CHECK=ABSENT' <<<"$output"; then SCHEMA_STATUS='MIGRATION_REQUIRED';
  else die 'SCHEMA_PREREQUISITE_FAILED'; fi
}

compare_dependencies() {
  local expected rel extra target actual
  DEPENDENCY_DRIFT_COUNT=0
  while read -r expected rel extra; do
    [[ -z "${extra:-}" ]] || die 'DEPENDENCY_MANIFEST_INVALID'
    target="$TARGET_ROOT/${rel#custom/}"
    if [[ ! -f "$target" || -L "$target" ]]; then actual='ABSENT'; DEPENDENCY_DRIFT_COUNT=$((DEPENDENCY_DRIFT_COUNT + 1));
    else actual=$(sha256 "$target"); [[ "$actual" == "$expected" ]] || DEPENDENCY_DRIFT_COUNT=$((DEPENDENCY_DRIFT_COUNT + 1)); fi
    echo "M068_PREFLIGHT_DEPENDENCY FILE=$rel EXPECTED_SHA=$expected TARGET_SHA=$actual"
  done <"$CANDIDATE_ROOT/$DEPENDENCY_MANIFEST"
}

compare_runtime() {
  local baseline rel extra target actual release_sha state
  DRIFT_COUNT=0
  while read -r baseline rel extra; do
    [[ -z "${extra:-}" ]] || die 'BASELINE_MANIFEST_INVALID'
    target="$TARGET_ROOT/${rel#custom/}"
    release_sha=$(sha256 "$CANDIDATE_ROOT/$rel")
    if [[ ! -e "$target" && ! -L "$target" ]]; then actual='ABSENT'; else [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_TYPE_INVALID'; actual=$(sha256 "$target"); fi
    if [[ "$baseline" == ABSENT && "$actual" == ABSENT ]]; then state='BASELINE_ABSENT';
    elif [[ "$baseline" != ABSENT && "$actual" == "$baseline" ]]; then state='BASELINE';
    elif [[ "$actual" == "$release_sha" ]]; then state='UNEXPECTED_RELEASE_PRESENT'; DRIFT_COUNT=$((DRIFT_COUNT + 1));
    else state='UNEXPECTED_DRIFT'; DRIFT_COUNT=$((DRIFT_COUNT + 1)); fi
    echo "M068_PREFLIGHT_COMPARE FILE=$rel STATE=$state TARGET_SHA=$actual BASELINE_SHA=$baseline RELEASE_SHA=$release_sha"
  done <"$CANDIDATE_ROOT/$BASELINE_MANIFEST"
}

command_compare() {
  check_environment; validate_artifact; candidate_checks; compare_dependencies; compare_runtime
  echo "M068_PREFLIGHT_COMPARE=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA RUNTIME_FILES=$EXPECTED_RUNTIME_FILES SUPPORT_FILES=$EXPECTED_SUPPORT_FILES DRIFT=$DRIFT_COUNT DEPENDENCY_DRIFT=$DEPENDENCY_DRIFT_COUNT SCHEMA=$SCHEMA_STATUS CONFIG=$CONFIG_STATUS DATABASE_CHANGED=NO RUNTIME_CHANGED=NO STATE_FILES_CREATED=NO PRODUCTION_ACCESSED=NO"
}
command_preflight() {
  check_environment; validate_artifact; candidate_checks; compare_dependencies; compare_runtime
  [[ $DRIFT_COUNT -eq 0 ]] || die 'BASELINE_DRIFT'
  [[ $DEPENDENCY_DRIFT_COUNT -eq 0 ]] || die 'DEPENDENCY_DRIFT'
  echo "M068_PREFLIGHT=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA RUNTIME_FILES=$EXPECTED_RUNTIME_FILES SUPPORT_FILES=$EXPECTED_SUPPORT_FILES SCHEMA=$SCHEMA_STATUS CONFIG=$CONFIG_STATUS APPLY_AVAILABLE=NO DATABASE_CHANGED=NO RUNTIME_CHANGED=NO STATE_FILES_CREATED=NO PRODUCTION_ACCESSED=NO"
}
command_status() {
  check_environment
  echo "M068_PREFLIGHT_STATUS=PREPARATION_ONLY RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA BASELINE_RELEASE=$BASELINE_RELEASE BASELINE_RUNTIME_COMMIT=$BASELINE_RUNTIME_COMMIT COMMANDS=compare,preflight,status APPLY_AVAILABLE=NO DATABASE_CHANGED=NO RUNTIME_CHANGED=NO STATE_FILES_CREATED=NO PRODUCTION_ACCESSED=NO"
}

require_invocation "$@"
case "$1" in compare) command_compare ;; preflight) command_preflight ;; status) command_status ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
