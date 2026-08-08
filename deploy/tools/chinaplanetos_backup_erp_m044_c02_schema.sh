#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M044-C02'
readonly RUNTIME_COMMIT='46f77f67571e58cf0ee5890ff6cafe5159e5a91c'
readonly SHORT_COMMIT='46f77f6'
readonly ARTIFACT_SHA='9ed7644b58a91e1ed534617a823a26c19fb1a6d4b756486e7ca0338aa93852a2'
readonly SUPPORT_MANIFEST_SHA='aa8171caaad428301306ffdad046c6deb5443cfee10ce39525b3877f6f613e8f'
readonly MIGRATION_SHA='bc75838ff8d8ba807da59e6e5514e88f1770c050430780fb88c7ae2b5d9606c9'
readonly SUPPORT_TEST_SHA='3cb5384923c956a79ff5edc568424e4754c4d6ed3d1e1dbc2144d7ddcd575ae1'
readonly IMPORTER_SHA='f2ba5156aecdf7ec9a065bf7fd7b9a21173f97ddea9365538deade9fb4b168d3'
readonly SERVICE_SHA='60912061f2b613b648fcebe7d0bee5112b506aeee3720dbb02132276efa3a333'
readonly IMPORT_PAGE_SHA='35261cc40213fab3988cb32a85eae170a1239799eaef8b0b573ab1da1245584c'
readonly SUPPORT_MANIFEST='deploy/releases/m044-c02-support-files.txt'
readonly MIGRATION='deploy/migrations/m041_shipping_note_translation_schema.php'
readonly SUPPORT_TEST='deploy/tests/test_m044_c02_schema_correction.php'

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m044-c02-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly IMPORTER_REL='chinaplanetshippingprices/class/ShippingPriceImporter.class.php'
readonly SERVICE_REL='chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
readonly IMPORT_PAGE_REL='chinaplanetshippingprices/admin/import.php'
readonly DB_BACKUP_ROOT='/root/backup-erp-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m044-c02-staging'
readonly STATE_FILE="${STATE_ROOT}/m044-c02.state"
readonly LOCK_FILE="${STATE_ROOT}/m044-c02.lock"
readonly MIN_FREE_KB=1048576

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
DB_BACKUP=''
DB_BACKUP_SHA=''
SCHEMA_PRESTATE_B64=''
SCHEMA_PRESTATE_SHA=''
SCHEMA_PRESTATE_KIND=''
MUTATED=0

die() { echo "M044_C02_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
value_sha256() { printf '%s' "$1" | sha256sum | awk '{print $1}'; }

restore_database() {
  [[ "$DB_BACKUP" == "$DB_BACKUP_ROOT/"*.sql && -f "$DB_BACKUP" && ! -L "$DB_BACKUP" ]] || return 1
  [[ $(sha256 "$DB_BACKUP") == "$DB_BACKUP_SHA" ]] || return 1
  docker exec -i "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
    user=${MARIADB_USER:-${MYSQL_USER:-root}}
    pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]
    if command -v mariadb >/dev/null 2>&1; then client=mariadb; else client=mysql; fi
    MYSQL_PWD="$pass" exec "$client" -u "$user" "$db"
  ' <"$DB_BACKUP"
}

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    if restore_database; then echo 'M044_C02_STAGING_AUTO_ROLLBACK=PASS' >&2; else echo 'M044_C02_STAGING_AUTO_ROLLBACK=FAIL' >&2; fi
  fi
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m044-c02-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m044-c02-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m044-c02-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m044-c02-schema' ]] || die 'HELPER_PATH_NOT_ALLOWED'
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
  [[ -d "$DB_BACKUP_ROOT" && ! -L "$DB_BACKUP_ROOT" && $(stat -c '%U:%G %a' "$DB_BACKUP_ROOT") == 'root:root 700' ]] || die 'DB_BACKUP_ROOT_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
  [[ $(df -Pk "$DB_BACKUP_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'BACKUP_DISK_LOW'
  docker exec "$DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
}

check_prerequisites() {
  local importer="$TARGET_ROOT/$IMPORTER_REL" service="$TARGET_ROOT/$SERVICE_REL" page="$TARGET_ROOT/$IMPORT_PAGE_REL"
  [[ -f "$importer" && ! -L "$importer" && $(sha256 "$importer") == "$IMPORTER_SHA" ]] || die 'IMPORTER_PREREQUISITE_DRIFT'
  [[ -f "$service" && ! -L "$service" && $(sha256 "$service") == "$SERVICE_SHA" ]] || die 'SERVICE_PREREQUISITE_DRIFT'
  [[ -f "$page" && ! -L "$page" && $(sha256 "$page") == "$IMPORT_PAGE_SHA" ]] || die 'IMPORT_PAGE_PREREQUISITE_DRIFT'
  grep -Fq 'source_notes_raw' "$importer" || die 'IMPORTER_SCHEMA_CONTRACT_ABSENT'
  grep -Fq "require_once __DIR__.'/ShippingNoteTranslationService.class.php';" "$importer" || die 'IMPORTER_DEPENDENCY_DECLARATION_ABSENT'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m044-c02-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 7 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m044-c02-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  mapfile -t support_files <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
  [[ ${#support_files[@]} -eq 2 && "${support_files[0]}" == "$MIGRATION" && "${support_files[1]}" == "$SUPPORT_TEST" ]] || die 'SUPPORT_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "$MIGRATION" "$SUPPORT_TEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$MIGRATION") == "$MIGRATION_SHA" ]] || die 'MIGRATION_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_TEST") == "$SUPPORT_TEST_SHA" ]] || die 'SUPPORT_TEST_HASH_MISMATCH'
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m044-c02-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE/deploy/migrations" "$CANDIDATE_REMOTE/deploy/tests"
  docker cp "$CANDIDATE_ROOT/$MIGRATION" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$MIGRATION"
  docker cp "$CANDIDATE_ROOT/$SUPPORT_TEST" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$SUPPORT_TEST"
  docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$MIGRATION" >/dev/null || die 'CANDIDATE_MIGRATION_LINT_FAILED'
  docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$SUPPORT_TEST" >/dev/null || die 'CANDIDATE_TEST_LINT_FAILED'
}

schema_state() {
  local output rc=0
  set +e
  output=$(docker exec -e M041_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check 2>&1)
  rc=$?
  set -e
  if [[ $rc -eq 0 && "$output" == 'M041_SCHEMA_CHECK=PASS' ]]; then echo 'PRESENT'; return; fi
  if [[ $rc -eq 2 && "$output" == 'M041_SCHEMA_CHECK=ABSENT' ]]; then echo 'ABSENT_OR_PARTIAL'; return; fi
  die 'SCHEMA_CHECK_DRIFT_OR_FAILURE'
}

capture_schema_prestate() {
  local output
  output=$(docker exec -e M041_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" snapshot) || die 'SCHEMA_SNAPSHOT_FAILED'
  [[ "$output" =~ ^M041_SCHEMA_SNAPSHOT=([A-Za-z0-9+/=]+)$ ]] || die 'SCHEMA_SNAPSHOT_INVALID'
  SCHEMA_PRESTATE_B64="${BASH_REMATCH[1]}"
  SCHEMA_PRESTATE_SHA=$(value_sha256 "$SCHEMA_PRESTATE_B64")
  SCHEMA_PRESTATE_KIND=$(schema_state)
}

create_database_backup() {
  DB_BACKUP="${DB_BACKUP_ROOT}/m044-c02-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
    user=${MARIADB_USER:-${MYSQL_USER:-root}}
    pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]
    if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
    MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
  ' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
  [[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'
  chmod 0600 "$DB_BACKUP"
  DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

live_checks() {
  local state body status
  state=$(schema_state)
  [[ "$state" == 'PRESENT' ]] || return 1
  check_prerequisites
  [[ -f "$DB_BACKUP" && ! -L "$DB_BACKUP" && $(sha256 "$DB_BACKUP") == "$DB_BACKUP_SHA" ]] || return 1
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
  body=$(mktemp)
  status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/custom/chinaplanetshippingprices/admin/import.php") || { rm -f "$body"; return 1; }
  if [[ "$status" =~ ^(302|401|403)$ ]]; then rm -f "$body"; return 0; fi
  if [[ "$status" == '200' ]] && grep -Fq '<form id="login"' "$body" && grep -Fq 'name="actionlogin"' "$body"; then rm -f "$body"; return 0; fi
  rm -f "$body"
  return 1
}

save_state() {
  cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=DEPLOYED
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_SCHEMA_PRESTATE_B64=$SCHEMA_PRESTATE_B64
STATE_SCHEMA_PRESTATE_SHA=$SCHEMA_PRESTATE_SHA
STATE_SCHEMA_PRESTATE_KIND=$SCHEMA_PRESTATE_KIND
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  DB_BACKUP="$STATE_DB_BACKUP"; DB_BACKUP_SHA="$STATE_DB_BACKUP_SHA"
  SCHEMA_PRESTATE_B64="$STATE_SCHEMA_PRESTATE_B64"; SCHEMA_PRESTATE_SHA="$STATE_SCHEMA_PRESTATE_SHA"; SCHEMA_PRESTATE_KIND="$STATE_SCHEMA_PRESTATE_KIND"
  [[ $(value_sha256 "$SCHEMA_PRESTATE_B64") == "$SCHEMA_PRESTATE_SHA" ]] || die 'SCHEMA_PRESTATE_HASH_MISMATCH'
}

command_compare() {
  check_environment; check_prerequisites; validate_artifact; candidate_checks
  echo "M044_C02_STAGING_SCHEMA_STATE=$(schema_state)"
  echo "M044_C02_STAGING_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_preflight() {
  check_environment; check_prerequisites; validate_artifact; candidate_checks; capture_schema_prestate
  echo "M044_C02_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA_PRESTATE=$SCHEMA_PRESTATE_KIND PRESTATE_SHA=$SCHEMA_PRESTATE_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; check_prerequisites; validate_artifact; candidate_checks; capture_schema_prestate
  create_database_backup
  if [[ "$SCHEMA_PRESTATE_KIND" == 'PRESENT' ]]; then
    live_checks || die 'NOOP_LIVE_CHECK_FAILED'
    save_state
    echo "M044_C02_STAGING_APPLY=NOOP_ALREADY_APPLIED RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA PRODUCTION_ACCESSED=NO"
    return
  fi
  MUTATED=1
  docker exec -e M041_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" apply >/dev/null || die 'SCHEMA_APPLY_FAILED'
  live_checks || die 'LIVE_CHECK_FAILED'
  MUTATED=0
  save_state
  echo "M044_C02_STAGING_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA SCHEMA_PRESTATE=$SCHEMA_PRESTATE_KIND PRESTATE_SHA=$SCHEMA_PRESTATE_SHA PRODUCTION_ACCESSED=NO"
}

command_verify() {
  check_environment; check_prerequisites; validate_artifact; candidate_checks; load_state
  live_checks || die 'LIVE_VERIFY_FAILED'
  echo "M044_C02_STAGING_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA PRODUCTION_ACCESSED=NO"
}

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; check_prerequisites; validate_artifact; candidate_checks; load_state
  local reapply="${DB_BACKUP_ROOT}/m044-c02-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql" schema_result=''
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
    MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
  ' >"$reapply" || die 'REAPPLY_DATABASE_BACKUP_FAILED'
  [[ -s "$reapply" ]] || die 'REAPPLY_DATABASE_BACKUP_EMPTY'; chmod 0600 "$reapply"
  if docker exec -e M041_DOL_DOCUMENT_ROOT=/var/www/html -e M041_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" rollback >/dev/null 2>&1; then
    schema_result='RESTORED_ADDITIVE_PRESTATE'
  else
    restore_database || die 'DATABASE_RESTORE_FAILED'
    schema_result='RESTORED_DATABASE_BACKUP'
  fi
  docker exec -e M041_DOL_DOCUMENT_ROOT=/var/www/html -e M041_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" verify-prestate >/dev/null || die 'ROLLBACK_PRESTATE_VERIFY_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"
  echo "M044_C02_STAGING_ROLLBACK=PASS SCHEMA=$schema_result REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") PRODUCTION_ACCESSED=NO"
}

command_status() {
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
    grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE_SHA|SCHEMA_PRESTATE_KIND|DEPLOYED_AT)=' "$STATE_FILE"
  else
    echo 'M044_C02_STAGING_STATUS=NOT_DEPLOYED'
  fi
}

require_root "$@"
case "$1" in
  compare) command_compare ;;
  preflight) command_preflight ;;
  apply) command_apply ;;
  verify) command_verify ;;
  rollback) command_rollback ;;
  status) command_status ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
