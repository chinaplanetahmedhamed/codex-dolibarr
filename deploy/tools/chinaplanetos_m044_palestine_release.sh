#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M044'
readonly RUNTIME_COMMIT='bea5c9798f2001473a8458a54b1e31821843875b'
readonly SHORT_COMMIT='bea5c97'
readonly ARTIFACT_SHA='ce65bc0ce331ead157d4a09ea814bdaef7faf6f1277a1d7a1f54cfb1ab7b5cbd'
readonly RUNTIME_MANIFEST_SHA='e82694f92592b20a6e3c43401576c73c47b4caec1ef6e5d310aae0af99cc6833'
readonly SUPPORT_MANIFEST_SHA='52f5ccb4b87818fd889ba8f9e14e14540acfe2bee2b8bd090553e84c45735a04'
readonly RUNTIME_MANIFEST='deploy/releases/m044-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m044-support-files.txt'
readonly GATEWAY_MIGRATION='deploy/migrations/m043_public_website_gateway.php'
readonly COUNTRY_MIGRATION='deploy/migrations/m044_palestine_shipping_country.php'
readonly RUNTIME_TEST='deploy/tests/test_m043_public_website_intake_runtime.php'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m044-${SHORT_COMMIT}.tar.gz"

readonly -a STATIC_TESTS=(
  'deploy/tests/test_m043_gateway_migration.php'
  'deploy/tests/test_m043_public_rate_token.php'
  'deploy/tests/test_m043_public_website_intake_source.php'
  'deploy/tests/test_m044_palestine_shipping_country.php'
)

ENVIRONMENT=''
ACTION=''
TARGET_CONTAINER=''
DB_CONTAINER=''
TARGET_VOLUME=''
TARGET_ROOT=''
TARGET_HTTP=''
BACKUP_ROOT=''
DB_BACKUP_ROOT=''
STATE_ROOT=''
STATE_FILE=''
LOCK_FILE=''
API_KEY_FILE=''
RATE_TOKEN_FILE=''
REQUEST_HMAC_FILE=''
RUNTIME_FILES=()
SUPPORT_FILES=()
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
SECRET_REMOTE=''
ARTIFACT_COPY=''
COUNTRY_SNAPSHOT_REMOTE=''
COUNTRY_SNAPSHOT=''
COUNTRY_SNAPSHOT_SHA=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''
SCHEMA_PREEXISTED='0'
REMOVE_SECRETS=0
MUTATED=0

die() { echo "M044_${ENVIRONMENT^^}_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    if restore_code && restore_database; then
      echo "M044_${ENVIRONMENT^^}_AUTO_ROLLBACK=PASS" >&2
    else
      echo "M044_${ENVIRONMENT^^}_AUTO_ROLLBACK=FAIL" >&2
    fi
  fi
  if [[ -n "$SECRET_REMOTE" && "$SECRET_REMOTE" == /tmp/m044-secrets-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$SECRET_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$SECRET_REMOTE" && "$SECRET_REMOTE" == /tmp/m043-secrets-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$SECRET_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$COUNTRY_SNAPSHOT_REMOTE" && "$COUNTRY_SNAPSHOT_REMOTE" == /tmp/m044-country-snapshot-*.json ]]; then docker exec "$TARGET_CONTAINER" rm -f "$COUNTRY_SNAPSHOT_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m044-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m044-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m044-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  if [[ $REMOVE_SECRETS -eq 1 ]]; then rm -f "$API_KEY_FILE" "$RATE_TOKEN_FILE" "$REQUEST_HMAC_FILE"; fi
  exit "$rc"
}
trap cleanup EXIT

select_environment() {
  [[ $(id -u) -eq 0 ]] || { ENVIRONMENT='unknown'; die 'ROOT_REQUIRED'; }
  [[ $# -eq 1 ]] || { ENVIRONMENT='unknown'; die 'EXACTLY_ONE_COMMAND_REQUIRED'; }
  [[ $(basename "$0") == 'chinaplanetos-m044-palestine-release' ]] || { ENVIRONMENT='unknown'; die 'HELPER_PATH_NOT_ALLOWED'; }
  case "$1" in
    staging-*) ENVIRONMENT='staging'; ACTION=${1#staging-} ;;
    production-*) ENVIRONMENT='production'; ACTION=${1#production-} ;;
    *) ENVIRONMENT='unknown'; die 'COMMAND_NOT_ALLOWED' ;;
  esac
  case "$ACTION" in compare|preflight|apply|verify|rollback|status) ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac

  if [[ "$ENVIRONMENT" == 'staging' ]]; then
    TARGET_CONTAINER='erp-staging-dolibarr-1'; DB_CONTAINER='erp-staging-mariadb-1'
    TARGET_VOLUME='erp-staging_erp-staging_custom'; TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
    TARGET_HTTP='http://127.0.0.1:18088'; BACKUP_ROOT='/root/backup-erp-code-backups'; DB_BACKUP_ROOT='/root/backup-erp-db-backups'
  else
    TARGET_CONTAINER='vision_erp'; DB_CONTAINER='vision_db'
    TARGET_VOLUME='vision-os_dolibarr_custom'; TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
    TARGET_HTTP='https://erp.visiontrading.biz'; BACKUP_ROOT='/root/chinaplanetos-production-code-backups'; DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
  fi
  STATE_ROOT="/var/lib/chinaplanetos-m044-${ENVIRONMENT}"
  STATE_FILE="${STATE_ROOT}/m044.state"; LOCK_FILE="${STATE_ROOT}/m044.lock"
  API_KEY_FILE="${INBOX}/m044-${ENVIRONMENT}-api.key"
  RATE_TOKEN_FILE="${INBOX}/m044-${ENVIRONMENT}-rate-token.key"
  REQUEST_HMAC_FILE="${INBOX}/m044-${ENVIRONMENT}-request-hmac.key"
}

check_environment() {
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'ERP_NOT_RUNNING'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'DB_NOT_RUNNING'
  if [[ "$ENVIRONMENT" == 'staging' ]]; then
    [[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER") == 'erp-staging|dolibarr' ]] || die 'STAGING_ERP_IDENTITY_MISMATCH'
    [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'STAGING_PORT_MISMATCH'
  else
    [[ "$TARGET_CONTAINER" == 'vision_erp' && "$DB_CONTAINER" == 'vision_db' && "$TARGET_ROOT" == /var/lib/docker/volumes/vision-os_dolibarr_custom/_data ]] || die 'PRODUCTION_IDENTITY_MISMATCH'
  fi
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge 1048576 ]] || die 'TARGET_DISK_LOW'
  install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m044-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual file
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 32 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m044-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
  mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
  [[ ${#RUNTIME_FILES[@]} -eq 23 && ${#SUPPORT_FILES[@]} -eq 7 ]] || die 'MANIFEST_COUNT_INVALID'
  [[ -z $(printf '%s\n' "${RUNTIME_FILES[@]}" | awk '$0 !~ /^custom\/(chinaplanetoperations|chinaplanetshippingprices)\// && $0 !~ /^custom\/chinaplanetcustomercenter\/(admin_dashboard\.php|css\/admin_dashboard\.css|lib\/admin_shell\.lib\.php)$/ {print; exit}') ]] || die 'RUNTIME_SCOPE_INVALID'
  [[ -z $(printf '%s\n' "${SUPPORT_FILES[@]}" | awk '$0 !~ /^deploy\/(migrations|tests)\// {print; exit}') ]] || die 'SUPPORT_SCOPE_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die 'ARTIFACT_FILE_INVALID'; done
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m044-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
  docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
  local file
  for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
    if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die 'CANDIDATE_LINT_FAILED'; fi
  done
  for file in "${STATIC_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die 'CANDIDATE_STATIC_TEST_FAILED'; done
}

validate_secret_files() {
  local file
  for file in "$API_KEY_FILE" "$RATE_TOKEN_FILE" "$REQUEST_HMAC_FILE"; do
    [[ -f "$file" && ! -L "$file" ]] || die 'SECRET_FILE_ABSENT'
    [[ $(stat -c '%U:%G %a' "$file") == 'codex-deploy:codex-deploy 600' ]] || die 'SECRET_FILE_METADATA_INVALID'
    [[ $(tr -d '\r\n' <"$file" | wc -c) -ge 32 && $(tr -d '\r\n' <"$file" | wc -c) -le 255 ]] || die 'SECRET_LENGTH_INVALID'
    [[ $(wc -l <"$file") -le 1 ]] || die 'SECRET_FORMAT_INVALID'
  done
}

schema_prestate() {
  local output rc=0
  set +e; output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$GATEWAY_MIGRATION" check 2>&1); rc=$?; set -e
  if [[ $rc -eq 0 && "$output" == M043_GATEWAY_CHECK=PASS* ]]; then SCHEMA_PREEXISTED='1'; return; fi
  if grep -Eq 'REASON=API_MODULE_DISABLED' <<<"$output"; then SCHEMA_PREEXISTED='1'; return; fi
  if grep -Eq 'REASON=(TABLE_ABSENT|RIGHT_ABSENT_|GATEWAY_USER_INVALID|CONSTANT_INVALID)' <<<"$output"; then SCHEMA_PREEXISTED='0'; return; fi
  die 'SCHEMA_PRESTATE_DRIFT'
}

country_prestate() {
  local output
  output=$(docker exec -e M044_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$COUNTRY_MIGRATION" check) || die 'COUNTRY_PRESTATE_FAILED'
  [[ "$output" == M044_PALESTINE_CHECK=PASS* ]] || die 'COUNTRY_PRESTATE_INVALID'
  echo "$output"
}

compare_files() {
  local file rel target release_hash target_hash state
  for file in "${RUNTIME_FILES[@]}"; do
    rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
    if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M044_${ENVIRONMENT^^}_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
    [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
    target_hash=$(sha256 "$target"); state='UPDATE_REQUIRED'; [[ "$target_hash" == "$release_hash" ]] && state='CURRENT'
    echo "M044_${ENVIRONMENT^^}_COMPARE FILE=$file STATE=$state TARGET_SHA=$target_hash RELEASE_SHA=$release_hash"
  done
}

create_code_backup() {
  local stamp rel target; local -a existing=()
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m044-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  ABSENT_LIST="${BACKUP_ROOT}/m044-absent-before-${SHORT_COMMIT}-${stamp}.txt"; : >"$ABSENT_LIST"
  for rel in "${RUNTIME_FILES[@]}"; do
    target="$TARGET_ROOT/${rel#custom/}"
    if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; existing+=("${rel#custom/}"); else printf '%s\n' "${rel#custom/}" >>"$ABSENT_LIST"; fi
  done
  if [[ ${#existing[@]} -gt 0 ]]; then tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
  chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"; CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}

create_database_backup() {
  DB_BACKUP="${DB_BACKUP_ROOT}/m044-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
    MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
  ' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
  [[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'; chmod 0600 "$DB_BACKUP"; DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

create_country_snapshot() {
  COUNTRY_SNAPSHOT_REMOTE="/tmp/m044-country-snapshot-$$.json"
  COUNTRY_SNAPSHOT="${STATE_ROOT}/m044-country-snapshot-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').json"
  docker exec -e M044_DOL_DOCUMENT_ROOT=/var/www/html -e M044_SNAPSHOT_PATH="$COUNTRY_SNAPSHOT_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$COUNTRY_MIGRATION" snapshot >/dev/null || die 'COUNTRY_SNAPSHOT_FAILED'
  docker cp "$TARGET_CONTAINER:$COUNTRY_SNAPSHOT_REMOTE" "$COUNTRY_SNAPSHOT" || die 'COUNTRY_SNAPSHOT_COPY_FAILED'
  chmod 0600 "$COUNTRY_SNAPSHOT"; COUNTRY_SNAPSHOT_SHA=$(sha256 "$COUNTRY_SNAPSHOT")
}

apply_code() {
  local rel src target tmp
  for rel in "${RUNTIME_FILES[@]}"; do
    src="$CANDIDATE_ROOT/$rel"; target="$TARGET_ROOT/${rel#custom/}"; tmp="${target}.m044.$$"
    install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"
    install -o www-data -g www-data -m 0644 "$src" "$tmp"; mv -f "$tmp" "$target"
  done
}

configure_gateway() {
  SECRET_REMOTE="/tmp/m043-secrets-$$"; docker exec "$TARGET_CONTAINER" rm -rf "$SECRET_REMOTE"; docker exec "$TARGET_CONTAINER" mkdir -p "$SECRET_REMOTE"
  docker cp "$API_KEY_FILE" "$TARGET_CONTAINER:$SECRET_REMOTE/api.key"
  docker cp "$RATE_TOKEN_FILE" "$TARGET_CONTAINER:$SECRET_REMOTE/rate-token.key"
  docker cp "$REQUEST_HMAC_FILE" "$TARGET_CONTAINER:$SECRET_REMOTE/request-hmac.key"
  docker exec "$TARGET_CONTAINER" chmod 0600 "$SECRET_REMOTE/api.key" "$SECRET_REMOTE/rate-token.key" "$SECRET_REMOTE/request-hmac.key"
  docker exec -e M043_SECRET_DIR="$SECRET_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$GATEWAY_MIGRATION" apply >/dev/null || die 'GATEWAY_CONFIGURE_FAILED'
}

apply_country() {
  docker exec -e M044_DOL_DOCUMENT_ROOT=/var/www/html -e M044_SNAPSHOT_PATH="$COUNTRY_SNAPSHOT_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$COUNTRY_MIGRATION" apply >/dev/null || die 'COUNTRY_APPLY_FAILED'
}

restore_code() {
  [[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
  tar -xzpf "$CODE_BACKUP" -C "$TARGET_ROOT" || return 1
  local rel target
  while IFS= read -r rel; do [[ -z "$rel" ]] && continue; [[ "$rel" != /* && "$rel" != *'..'* ]] || return 1; target="$TARGET_ROOT/$rel"; [[ "$target" == "$TARGET_ROOT/"* ]] || return 1; rm -f "$target"; done <"$ABSENT_LIST"
}

restore_database() {
  [[ -f "$DB_BACKUP" && $(sha256 "$DB_BACKUP") == "$DB_BACKUP_SHA" ]] || return 1
  docker exec -i "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; MYSQL_PWD="$pass" exec mariadb -u "$user" "$db"
  ' <"$DB_BACKUP"
}

live_checks() {
  local rel output
  for rel in "${RUNTIME_FILES[@]}"; do
    [[ $(sha256 "$TARGET_ROOT/${rel#custom/}") == $(sha256 "$CANDIDATE_ROOT/$rel") ]] || return 1
    if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; fi
  done
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$GATEWAY_MIGRATION" check >/dev/null || return 1
  output=$(docker exec -e M044_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$COUNTRY_MIGRATION" check) || return 1
  [[ "$output" == *' IL=0 '* || "$output" == *' IL=0' ]] || return 1
  docker exec -e M043_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$RUNTIME_TEST" >/dev/null || return 1
  if [[ -f "$API_KEY_FILE" ]]; then
    local gateway_key body status
    gateway_key=$(tr -d '\r\n' <"$API_KEY_FILE")
    body=$(curl -sS --max-time 15 -w $'\n%{http_code}' -H "DOLAPIKEY: ${gateway_key}" "${TARGET_HTTP}/api/index.php/chinaplanetshippingprices/website-route-options") || return 1
    status=${body##*$'\n'}; body=${body%$'\n'*}; [[ "$status" == '200' && "$body" == *'"countries"'* ]] || return 1
  fi
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]]
}

save_state() {
  cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=DEPLOYED
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_COUNTRY_SNAPSHOT=$COUNTRY_SNAPSHOT
STATE_COUNTRY_SNAPSHOT_SHA=$COUNTRY_SNAPSHOT_SHA
STATE_SCHEMA_PREEXISTED=$SCHEMA_PREEXISTED
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_INVALID'
  # shellcheck disable=SC1090
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  CODE_BACKUP="$STATE_CODE_BACKUP"; CODE_BACKUP_SHA="$STATE_CODE_BACKUP_SHA"; ABSENT_LIST="$STATE_ABSENT_LIST"; ABSENT_LIST_SHA="$STATE_ABSENT_LIST_SHA"
  DB_BACKUP="$STATE_DB_BACKUP"; DB_BACKUP_SHA="$STATE_DB_BACKUP_SHA"; COUNTRY_SNAPSHOT="$STATE_COUNTRY_SNAPSHOT"; COUNTRY_SNAPSHOT_SHA="$STATE_COUNTRY_SNAPSHOT_SHA"; SCHEMA_PREEXISTED="$STATE_SCHEMA_PREEXISTED"
}

command_compare() { check_environment; validate_artifact; candidate_checks; schema_prestate; country_prestate; compare_files; echo "M044_${ENVIRONMENT^^}_COMPARE=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; schema_prestate; country_prestate; echo "M044_${ENVIRONMENT^^}_PREFLIGHT=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA_PREEXISTED=$SCHEMA_PREEXISTED"; }
command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; validate_secret_files; schema_prestate; country_prestate; create_code_backup; create_database_backup; create_country_snapshot
  REMOVE_SECRETS=1
  MUTATED=1; apply_code; configure_gateway; apply_country; live_checks || die 'LIVE_CHECK_FAILED'; MUTATED=0; save_state
  echo "M044_${ENVIRONMENT^^}_APPLY=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA COUNTRY_SNAPSHOT=$COUNTRY_SNAPSHOT COUNTRY_SNAPSHOT_SHA=$COUNTRY_SNAPSHOT_SHA"
}
command_verify() { check_environment; validate_artifact; candidate_checks; load_state; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M044_${ENVIRONMENT^^}_VERIFY=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; candidate_checks; load_state
  [[ -f "$COUNTRY_SNAPSHOT" && $(sha256 "$COUNTRY_SNAPSHOT") == "$COUNTRY_SNAPSHOT_SHA" ]] || die 'COUNTRY_SNAPSHOT_INVALID'
  COUNTRY_SNAPSHOT_REMOTE="/tmp/m044-country-snapshot-$$.json"; docker cp "$COUNTRY_SNAPSHOT" "$TARGET_CONTAINER:$COUNTRY_SNAPSHOT_REMOTE"
  docker exec -e M044_DOL_DOCUMENT_ROOT=/var/www/html -e M044_SNAPSHOT_PATH="$COUNTRY_SNAPSHOT_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$COUNTRY_MIGRATION" rollback >/dev/null || die 'COUNTRY_ROLLBACK_FAILED'
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$GATEWAY_MIGRATION" disable >/dev/null || die 'GATEWAY_DISABLE_FAILED'
  restore_code || die 'CODE_RESTORE_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"
  echo "M044_${ENVIRONMENT^^}_ROLLBACK=PASS RESTORED=$CODE_BACKUP COUNTRY_ROWS=RESTORED"
}
command_status() { if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|DB_BACKUP|DB_BACKUP_SHA|COUNTRY_SNAPSHOT|COUNTRY_SNAPSHOT_SHA|SCHEMA_PREEXISTED|DEPLOYED_AT)=' "$STATE_FILE"; else echo "M044_${ENVIRONMENT^^}_STATUS=NOT_DEPLOYED"; fi; }

select_environment "$@"
case "$ACTION" in
  compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;;
esac
