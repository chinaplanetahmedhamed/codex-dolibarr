#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M043-R01'
readonly RUNTIME_COMMIT='c65ca0588470078a2629fc8952688b347b55a449'
readonly SHORT_COMMIT='c65ca05'
readonly ARTIFACT_SHA='a89a5020b9daa6e17e2cfcbe7b28f55b7add182965a5e4d10815ae6867ae4eab'
readonly RUNTIME_MANIFEST_SHA='41d85bbd83e1890b10f3cc068ff528f88cc55d50a3b18b74539b1b645f8133a8'
readonly SUPPORT_MANIFEST_SHA='043f47e31be6e64afbede6afc35b83e3707a7cc1cc60d796545d02ad4e932757'
readonly RUNTIME_MANIFEST='deploy/releases/m043-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m043-support-files.txt'
readonly MIGRATION='deploy/migrations/m043_public_website_gateway.php'

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m043-${SHORT_COMMIT}.tar.gz"
readonly API_KEY_FILE="${INBOX}/m043-api.key"
readonly RATE_TOKEN_FILE="${INBOX}/m043-rate-token.key"
readonly REQUEST_HMAC_FILE="${INBOX}/m043-request-hmac.key"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_PROJECT='erp-staging'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly DB_BACKUP_ROOT='/root/backup-erp-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m043-staging'
readonly STATE_FILE="${STATE_ROOT}/m043.state"
readonly LOCK_FILE="${STATE_ROOT}/m043.lock"

readonly -a STATIC_TESTS=(
  'deploy/tests/test_m043_gateway_migration.php'
  'deploy/tests/test_m043_public_rate_token.php'
  'deploy/tests/test_m043_public_website_intake_source.php'
)
readonly RUNTIME_TEST='deploy/tests/test_m043_public_website_intake_runtime.php'

RUNTIME_FILES=()
SUPPORT_FILES=()
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
SECRET_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''
SCHEMA_PREEXISTED='0'
REMOVE_SECRETS=0

die() { echo "M043_STAGING_FAIL=$1" >&2; return 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup() {
  if [[ -n "$SECRET_REMOTE" && "$SECRET_REMOTE" == /tmp/m043-secrets-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$SECRET_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m043-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m043-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m043-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  if [[ $REMOVE_SECRETS -eq 1 ]]; then rm -f "$API_KEY_FILE" "$RATE_TOKEN_FILE" "$REQUEST_HMAC_FILE"; fi
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m043-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
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
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge 1048576 ]] || die 'TARGET_DISK_LOW'
  install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m043-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing tar_types expected actual file
  listing=$(mktemp); tar_types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$tar_types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$tar_types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  [[ -z $(grep -E '(^|/)(\.env|conf\.php|\.DS_Store|[^/]*\.(pem|key|p12|dump|log|bak|tmp))$' "$listing" || true) ]] || die 'ARTIFACT_SECRET_FILE'
  CANDIDATE_ROOT=$(mktemp -d /root/m043-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
  mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
  [[ ${#RUNTIME_FILES[@]} -eq 13 && ${#SUPPORT_FILES[@]} -eq 5 ]] || die 'MANIFEST_COUNT_INVALID'
  [[ -z $(printf '%s\n' "${RUNTIME_FILES[@]}" | awk '$0 !~ /^custom\/(chinaplanetoperations|chinaplanetshippingprices)\// {print; exit}') ]] || die 'RUNTIME_SCOPE_INVALID'
  [[ -z $(printf '%s\n' "${SUPPORT_FILES[@]}" | awk '$0 !~ /^deploy\/(migrations|tests)\// {print; exit}') ]] || die 'SUPPORT_SCOPE_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die 'ARTIFACT_FILE_INVALID'; done
  rm -f "$listing" "$tar_types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m043-candidate-$$"
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
  set +e; output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check 2>&1); rc=$?; set -e
  if [[ $rc -eq 0 && "$output" == M043_GATEWAY_CHECK=PASS* ]]; then SCHEMA_PREEXISTED='1'; return; fi
  if grep -Eq 'REASON=(TABLE_ABSENT|RIGHT_ABSENT_|GATEWAY_USER_INVALID|CONSTANT_INVALID)' <<<"$output"; then SCHEMA_PREEXISTED='0'; return; fi
  die 'SCHEMA_PRESTATE_DRIFT'
}

create_code_backup() {
  local stamp rel target
  local -a existing=()
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m043-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  ABSENT_LIST="${BACKUP_ROOT}/m043-absent-before-${SHORT_COMMIT}-${stamp}.txt"
  : >"$ABSENT_LIST"
  for rel in "${RUNTIME_FILES[@]}"; do
    target="$TARGET_ROOT/${rel#custom/}"
    if [[ -e "$target" || -L "$target" ]]; then
      [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
      existing+=("${rel#custom/}")
    else
      printf '%s\n' "${rel#custom/}" >>"$ABSENT_LIST"
    fi
  done
  if [[ ${#existing[@]} -gt 0 ]]; then tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
  chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}

create_database_backup() {
  DB_BACKUP="${DB_BACKUP_ROOT}/m043-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
    MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
  ' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
  [[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'
  chmod 0600 "$DB_BACKUP"; DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

apply_code() {
  local rel src target tmp
  for rel in "${RUNTIME_FILES[@]}"; do
    src="$CANDIDATE_ROOT/$rel"; target="$TARGET_ROOT/${rel#custom/}"; tmp="${target}.m043.$$"
    install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"
    install -o www-data -g www-data -m 0644 "$src" "$tmp"
    mv -f "$tmp" "$target"
  done
}

restore_code() {
  [[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
  tar -xzpf "$CODE_BACKUP" -C "$TARGET_ROOT" || return 1
  local rel target
  while IFS= read -r rel; do
    [[ -z "$rel" ]] && continue
    [[ "$rel" != /* && "$rel" != *'..'* ]] || return 1
    target="$TARGET_ROOT/$rel"; [[ "$target" == "$TARGET_ROOT/"* ]] || return 1
    rm -f "$target"
  done <"$ABSENT_LIST"
}

restore_database() {
  [[ -f "$DB_BACKUP" && $(sha256 "$DB_BACKUP") == "$DB_BACKUP_SHA" ]] || return 1
  docker exec -i "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; MYSQL_PWD="$pass" exec mariadb -u "$user" "$db"
  ' <"$DB_BACKUP" || return 1
  if [[ "$SCHEMA_PREEXISTED" == '0' ]]; then docker exec -e M043_SCHEMA_PREEXISTED=0 "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" finalize-restore >/dev/null || return 1; fi
}

configure_gateway() {
  SECRET_REMOTE="/tmp/m043-secrets-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$SECRET_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$SECRET_REMOTE"
  docker cp "$API_KEY_FILE" "$TARGET_CONTAINER:$SECRET_REMOTE/api.key"
  docker cp "$RATE_TOKEN_FILE" "$TARGET_CONTAINER:$SECRET_REMOTE/rate-token.key"
  docker cp "$REQUEST_HMAC_FILE" "$TARGET_CONTAINER:$SECRET_REMOTE/request-hmac.key"
  docker exec "$TARGET_CONTAINER" chmod 0600 "$SECRET_REMOTE/api.key" "$SECRET_REMOTE/rate-token.key" "$SECRET_REMOTE/request-hmac.key"
  docker exec -e M043_SECRET_DIR="$SECRET_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" apply >/dev/null || die 'GATEWAY_CONFIGURE_FAILED'
}

live_checks() {
  local rel
  for rel in "${RUNTIME_FILES[@]}"; do
    [[ $(sha256 "$TARGET_ROOT/${rel#custom/}") == $(sha256 "$CANDIDATE_ROOT/$rel") ]] || return 1
    if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; fi
  done
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check >/dev/null || return 1
  docker exec -e M043_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$RUNTIME_TEST" >/dev/null || return 1
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
STATE_SCHEMA_PREEXISTED=$SCHEMA_PREEXISTED
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  CODE_BACKUP="$STATE_CODE_BACKUP"; CODE_BACKUP_SHA="$STATE_CODE_BACKUP_SHA"; ABSENT_LIST="$STATE_ABSENT_LIST"; ABSENT_LIST_SHA="$STATE_ABSENT_LIST_SHA"; DB_BACKUP="$STATE_DB_BACKUP"; DB_BACKUP_SHA="$STATE_DB_BACKUP_SHA"; SCHEMA_PREEXISTED="$STATE_SCHEMA_PREEXISTED"
}

command_preflight() {
  check_environment; validate_artifact; candidate_checks; schema_prestate
  echo "M043_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA_PREEXISTED=$SCHEMA_PREEXISTED PRODUCTION_ACCESSED=NO"
}

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; validate_secret_files; schema_prestate; create_code_backup; create_database_backup
  REMOVE_SECRETS=1
  local mutated=0
  trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_code && restore_database && echo M043_STAGING_AUTO_ROLLBACK=PASS >&2 || echo M043_STAGING_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
  mutated=1
  apply_code
  configure_gateway
  live_checks || die 'LIVE_CHECK_FAILED'
  mutated=0; trap - ERR
  save_state
  echo "M043_STAGING_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA PRODUCTION_ACCESSED=NO"
}

command_verify() {
  check_environment; validate_artifact; candidate_checks; load_state; live_checks || die 'LIVE_VERIFY_FAILED'
  echo "M043_STAGING_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA PRODUCTION_ACCESSED=NO"
}

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; load_state
  local reapply="${BACKUP_ROOT}/m043-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czpf "$reapply" -C "$TARGET_ROOT" "${RUNTIME_FILES[@]#custom/}"; chmod 0600 "$reapply"
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" disable >/dev/null || die 'GATEWAY_DISABLE_FAILED'
  restore_code || die 'CODE_RESTORE_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"
  echo "M043_STAGING_ROLLBACK=PASS REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") SCHEMA_RETAINED=DORMANT PRODUCTION_ACCESSED=NO"
}

command_status() {
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PREEXISTED|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M043_STAGING_STATUS=NOT_DEPLOYED'; fi
}

require_root "$@"
case "$1" in
  preflight) command_preflight ;;
  apply) command_apply ;;
  verify) command_verify ;;
  rollback) command_rollback ;;
  status) command_status ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
