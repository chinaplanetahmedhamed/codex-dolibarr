#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M068-C01-R03'
readonly OWNERSHIP_STATE_RELEASE='M068-C01-R01'
readonly BASELINE_RELEASE='M062-R04'
readonly BASELINE_RUNTIME_COMMIT='f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b'
readonly BASELINE_SCOPE_COMMIT='3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67'
readonly BASELINE_ARTIFACT_SHA='a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c'
readonly M068_PREFLIGHT_HELPER_SHA='649888ffca725bcf8cc1406a0ff383dffe2688754e7e720ecd5f64cfac053c2c'
readonly CONFIG_SHA='20d82fec7a4b95c6a843bfcce4b0a27415c7215d7cb807df21a10e7dc714c2bf'
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly DOCS_VOLUME='erp-staging_erp-staging_docs'
readonly DB_VOLUME='erp-staging_erp-staging_mariadb_data'
readonly DB_NAME='dolibarr_db'
readonly TARGET_HTTP='http://127.0.0.1:18088'

TEST_MODE=0
if [[ ${M068_C01_HELPER_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M068_C01_HELPER_TEST_ROOT:-}
  case "$test_root" in /tmp/m068-c01-hmac-helper-test.*|/private/tmp/m068-c01-hmac-helper-test.*) ;; *) echo 'M068_C01_HELPER=FAIL REASON=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" && $(basename "$0") == 'chinaplanetos-backup-erp-m068-c01-hmac.test' ]] || { echo 'M068_C01_HELPER=FAIL REASON=TEST_MODE_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  CONFIG_TEST_ROOT="$test_root/config"
  CONFIG_TOOL="$CONFIG_TEST_ROOT/m068_c01_portal_hmac_config.test.php"
  STATE_ROOT="$test_root/state"
  BACKUP_ROOT="$test_root/db-backups"
  LOCK_FILE="$test_root/m068-c01.lock"
  BASELINE_STATE_FILE="$test_root/m062-state/m062-r04.state"
  TARGET_ROOT="$test_root/target"
  M068_PREFLIGHT_HELPER="$test_root/system/usr/local/sbin/chinaplanetos-backup-erp-m068-preflight"
else
  CONFIG_TEST_ROOT=''
  CONFIG_TOOL='/usr/local/libexec/chinaplanetos-m068-c01/m068_c01_portal_hmac_config.php'
  STATE_ROOT='/var/lib/chinaplanetos-backup-erp-m068-c01-hmac'
  BACKUP_ROOT='/root/backup-erp-db-backups'
  LOCK_FILE='/run/lock/chinaplanetos-backup-erp-m068-c01-hmac.lock'
  BASELINE_STATE_FILE='/var/lib/chinaplanetos-m062-terminal-trend-staging-r04/m062-r04.state'
  TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
  M068_PREFLIGHT_HELPER='/usr/local/sbin/chinaplanetos-backup-erp-m068-preflight'
fi
readonly TEST_MODE CONFIG_TEST_ROOT CONFIG_TOOL STATE_ROOT BACKUP_ROOT LOCK_FILE BASELINE_STATE_FILE TARGET_ROOT M068_PREFLIGHT_HELPER
readonly STATE_FILE="$STATE_ROOT/m068-c01.state"

TOOL_OUTPUT=''
TOOL_RC=0
DB_BACKUP=''
DB_BACKUP_SHA=''
DB_BACKUP_VALIDATED=0
DB_VALIDATION_NAME=''
CONFIG_MUTATED=0
APPLY_COMPLETE=0
APPLY_REQUEST_ID=''
APPLY_ENTITY=''

die() { echo "M068_C01_HELPER=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
mode_of() { if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Lp' "$1"; else stat -c '%a' "$1"; fi; }
owner_group_of() { if [[ $TEST_MODE -eq 1 ]]; then printf 'root:root'; else stat -c '%U:%G' "$1"; fi; }
fsync_path() {
  [[ $TEST_MODE -eq 1 ]] && return 0
  python3 - "$1" <<'PY'
import os, pathlib, sys
p = pathlib.Path(sys.argv[1])
flags = os.O_RDONLY | (getattr(os, 'O_DIRECTORY', 0) if p.is_dir() else 0)
fd = os.open(str(p), flags)
try: os.fsync(fd)
finally: os.close(fd)
PY
}

drop_validation_database() {
  [[ -z "$DB_VALIDATION_NAME" ]] && return 0
  [[ "$DB_VALIDATION_NAME" =~ ^m068_c01_restore_[0-9]+_[a-f0-9]{8}$ ]] || return 1
  if [[ $TEST_MODE -eq 1 ]]; then DB_VALIDATION_NAME=''; return 0; fi
  docker exec -e M068_C01_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]
    case "$M068_C01_TEMP_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    MYSQL_PWD="$pass" exec mariadb -u root -e "DROP DATABASE IF EXISTS \`$M068_C01_TEMP_DB\`"
  ' >/dev/null || return 1
  DB_VALIDATION_NAME=''
}

discard_unvalidated_database_backup() {
  [[ -z "$DB_BACKUP" ]] && return 0
  case "$DB_BACKUP" in "$BACKUP_ROOT"/m068-c01-before-*.sql) ;; *) return 1 ;; esac
  if [[ -e "$DB_BACKUP" || -L "$DB_BACKUP" ]]; then
    [[ -f "$DB_BACKUP" && ! -L "$DB_BACKUP" ]] || return 1
    rm -f -- "$DB_BACKUP" || return 1
    fsync_path "$BACKUP_ROOT" || return 1
  fi
  DB_BACKUP=''
  DB_BACKUP_SHA=''
}

cleanup() {
  local rc=$? recovery='NOT_REQUIRED'
  trap - EXIT
  set +e
  drop_validation_database || { recovery='VALIDATION_DATABASE_DROP_FAILED'; rc=1; }
  if [[ -n "$DB_BACKUP" && $DB_BACKUP_VALIDATED -eq 0 ]]; then
    discard_unvalidated_database_backup || { recovery='UNVALIDATED_DATABASE_BACKUP_DELETE_FAILED'; rc=1; }
  fi
  if [[ $CONFIG_MUTATED -eq 1 && $APPLY_COMPLETE -eq 0 ]]; then
    run_tool rollback
    if [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG_ROLLBACK=PASS* ]]; then
      CONFIG_MUTATED=0
      recovery='AUTO_ROLLED_BACK'
      if [[ -n "$APPLY_REQUEST_ID" && -n "$APPLY_ENTITY" && -n "$DB_BACKUP" && -n "$DB_BACKUP_SHA" ]]; then
        write_state 'APPLY_FAILED_ROLLED_BACK' "$APPLY_REQUEST_ID" "$APPLY_ENTITY" "$DB_BACKUP" "$DB_BACKUP_SHA" || rc=1
      fi
    else
      recovery='RECOVERY_REQUIRED'
      if [[ -n "$APPLY_REQUEST_ID" && -n "$APPLY_ENTITY" && -n "$DB_BACKUP" && -n "$DB_BACKUP_SHA" ]]; then
        write_state 'RECOVERY_REQUIRED' "$APPLY_REQUEST_ID" "$APPLY_ENTITY" "$DB_BACKUP" "$DB_BACKUP_SHA" || true
      fi
      rc=1
    fi
  fi
  if [[ "$recovery" == 'RECOVERY_REQUIRED' || "$recovery" == 'VALIDATION_DATABASE_DROP_FAILED' || "$recovery" == 'UNVALIDATED_DATABASE_BACKUP_DELETE_FAILED' ]]; then
    echo "M068_C01_HELPER=FAIL REASON=$recovery SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO" >&2
  fi
  exit "$rc"
}
trap cleanup EXIT

require_invocation() {
  [[ $TEST_MODE -eq 1 || $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(basename "$0") == 'chinaplanetos-backup-erp-m068-c01-hmac' ]] || die 'HELPER_PATH_NOT_ALLOWED'; fi
  case "$1" in preflight|apply|verify|rollback|status) ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
}

state_value_from() {
  local file=$1 key=$2 count value
  count=$(grep -c "^${key}=" "$file" || true)
  [[ "$count" -eq 1 ]] || die 'STATE_INVALID'
  value=$(awk -F= -v key="$key" '$1==key {sub(/^[^=]*=/, ""); print}' "$file")
  [[ "$value" =~ ^[A-Za-z0-9._:/+-]+$ ]] || die 'STATE_VALUE_INVALID'
  printf '%s' "$value"
}

validate_baseline_state() {
  [[ -f "$BASELINE_STATE_FILE" && ! -L "$BASELINE_STATE_FILE" ]] || die 'BASELINE_STATE_ABSENT'
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(owner_group_of "$BASELINE_STATE_FILE") == 'root:root' && $(mode_of "$BASELINE_STATE_FILE") == 600 ]] || die 'BASELINE_STATE_METADATA_INVALID'; fi
  [[ $(state_value_from "$BASELINE_STATE_FILE" STATE_RELEASE) == "$BASELINE_RELEASE" ]] || die 'BASELINE_RELEASE_MISMATCH'
  [[ $(state_value_from "$BASELINE_STATE_FILE" STATE_STATUS) == 'DEPLOYED' ]] || die 'BASELINE_NOT_DEPLOYED'
  [[ $(state_value_from "$BASELINE_STATE_FILE" STATE_RUNTIME_COMMIT) == "$BASELINE_RUNTIME_COMMIT" ]] || die 'BASELINE_RUNTIME_MISMATCH'
  [[ $(state_value_from "$BASELINE_STATE_FILE" STATE_SCOPE_COMMIT) == "$BASELINE_SCOPE_COMMIT" ]] || die 'BASELINE_SCOPE_MISMATCH'
  [[ $(state_value_from "$BASELINE_STATE_FILE" STATE_ARTIFACT_SHA) == "$BASELINE_ARTIFACT_SHA" ]] || die 'BASELINE_ARTIFACT_MISMATCH'
}

validate_optional_private_root() {
  local path=$1
  [[ ! -e "$path" && ! -L "$path" ]] && return 0
  [[ -d "$path" && ! -L "$path" ]] || die 'PRIVATE_ROOT_INVALID'
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(owner_group_of "$path") == 'root:root' && $(mode_of "$path") == 700 ]] || die 'PRIVATE_ROOT_METADATA_INVALID'
  fi
}

check_environment() {
  [[ -f "$CONFIG_TOOL" && ! -L "$CONFIG_TOOL" && $(sha256 "$CONFIG_TOOL") == "$CONFIG_SHA" ]] || die 'CONFIG_TOOL_INVALID'
  validate_baseline_state
  validate_optional_private_root "$STATE_ROOT"
  validate_optional_private_root "$BACKUP_ROOT"
  if [[ $TEST_MODE -eq 1 ]]; then [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'; return; fi
  [[ $(owner_group_of "$CONFIG_TOOL") == 'root:root' && $(mode_of "$CONFIG_TOOL") == 644 ]] || die 'CONFIG_TOOL_METADATA_INVALID'
  [[ -f "$M068_PREFLIGHT_HELPER" && ! -L "$M068_PREFLIGHT_HELPER" && $(sha256 "$M068_PREFLIGHT_HELPER") == "$M068_PREFLIGHT_HELPER_SHA" ]] || die 'M068_PREFLIGHT_HELPER_INVALID'
  [[ $(owner_group_of "$M068_PREFLIGHT_HELPER") == 'root:root' && $(mode_of "$M068_PREFLIGHT_HELPER") == 755 ]] || die 'M068_PREFLIGHT_HELPER_METADATA_INVALID'
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" && $(owner_group_of "$TARGET_ROOT") == 'www-data:www-data' && $(mode_of "$TARGET_ROOT") == 755 ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/documents"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${DOCS_VOLUME}|true" ]] || die 'DOCS_MOUNT_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER" 2>/dev/null) == 'true|erp-staging|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$DB_CONTAINER") == "${DB_VOLUME}|true" ]] || die 'DB_MOUNT_MISMATCH'
  [[ -z $(docker port "$DB_CONTAINER" 2>/dev/null) ]] || die 'DB_PORT_EXPOSED'
  [[ $(docker exec "$DB_CONTAINER" sh -eu -c 'printf %s "${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}"') == "$DB_NAME" ]] || die 'DB_NAME_MISMATCH'
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
    app_pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    root_pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
    [ "$db" = dolibarr_db ] && [ -n "$app_pass" ] && [ -n "$root_pass" ]
    command -v mariadb >/dev/null
    command -v mariadb-dump >/dev/null || command -v mysqldump >/dev/null
  ' >/dev/null || die 'DATABASE_BACKUP_PREREQUISITE_MISSING'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
}

run_tool() {
  local action=$1
  set +e
  if [[ $TEST_MODE -eq 1 ]]; then
    TOOL_OUTPUT=$(M068_C01_CONFIG_TEST_MODE=1 M068_C01_CONFIG_TEST_ROOT="$CONFIG_TEST_ROOT" php "$CONFIG_TOOL" "$action" 2>&1); TOOL_RC=$?
  else
    TOOL_OUTPUT=$(docker exec -i "$TARGET_CONTAINER" php /dev/stdin "$action" <"$CONFIG_TOOL" 2>&1); TOOL_RC=$?
  fi
  set -e
}

ensure_state_roots() {
  if [[ $TEST_MODE -eq 1 ]]; then mkdir -p "$STATE_ROOT" "$BACKUP_ROOT"; chmod 0700 "$STATE_ROOT" "$BACKUP_ROOT";
  else install -d -o root -g root -m 0700 "$STATE_ROOT" "$BACKUP_ROOT"; fi
  validate_optional_private_root "$STATE_ROOT"
  validate_optional_private_root "$BACKUP_ROOT"
}

write_state() {
  local status=$1 request_id=$2 entity=$3 backup_path=$4 backup_sha=$5 tmp updated_at
  ensure_state_roots
  updated_at=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
  tmp="$STATE_ROOT/m068-c01.state.tmp.$$"
  {
    echo "STATE_RELEASE=$OWNERSHIP_STATE_RELEASE"
    echo "STATE_STATUS=$status"
    echo "STATE_REQUEST_ID=$request_id"
    echo "STATE_ENTITY=$entity"
    echo 'STATE_SECRET_PRESTATE=ABSENT'
    echo "STATE_DB_BACKUP=$backup_path"
    echo "STATE_DB_BACKUP_SHA=$backup_sha"
    echo "STATE_UPDATED_AT=$updated_at"
  } >"$tmp"
  chmod 0600 "$tmp"
  if [[ $TEST_MODE -eq 0 ]]; then chown root:root "$tmp"; fi
  fsync_path "$tmp"
  mv -f "$tmp" "$STATE_FILE"
  fsync_path "$STATE_ROOT"
}

validate_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(owner_group_of "$STATE_FILE") == 'root:root' && $(mode_of "$STATE_FILE") == 600 ]] || die 'STATE_METADATA_INVALID'; fi
  [[ $(state_value_from "$STATE_FILE" STATE_RELEASE) == "$OWNERSHIP_STATE_RELEASE" ]] || die 'STATE_RELEASE_MISMATCH'
  [[ $(state_value_from "$STATE_FILE" STATE_SECRET_PRESTATE) == 'ABSENT' ]] || die 'STATE_PRESTATE_INVALID'
  local backup_path backup_sha
  backup_path=$(state_value_from "$STATE_FILE" STATE_DB_BACKUP)
  backup_sha=$(state_value_from "$STATE_FILE" STATE_DB_BACKUP_SHA)
  case "$backup_path" in "$BACKUP_ROOT"/m068-c01-before-*.sql) ;; *) die 'DATABASE_BACKUP_PATH_INVALID' ;; esac
  [[ -f "$backup_path" && ! -L "$backup_path" && -s "$backup_path" && $(sha256 "$backup_path") == "$backup_sha" ]] || die 'DATABASE_BACKUP_INVALID'
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(owner_group_of "$backup_path") == 'root:root' && $(mode_of "$backup_path") == 600 ]] || die 'DATABASE_BACKUP_METADATA_INVALID'; fi
}

create_database_backup() {
  local request_id=$1
  DB_BACKUP="$BACKUP_ROOT/m068-c01-before-${request_id}.sql"
  [[ ! -e "$DB_BACKUP" && ! -L "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_COLLISION'
  if [[ $TEST_MODE -eq 1 ]]; then
    [[ ${M068_C01_HELPER_TEST_FAULT:-} != BACKUP ]] || die 'INJECTED_DATABASE_BACKUP_FAILURE'
    printf '%s\n' '-- M068-C01 synthetic pre-secret full database backup' >"$DB_BACKUP"
  else
    docker exec "$DB_CONTAINER" sh -eu -c '
      db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
      pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
      [ "$db" = dolibarr_db ] && [ -n "$pass" ]
      if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
      MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables --hex-blob --routines --triggers --events --default-character-set=utf8mb4 -u root "$db"
    ' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
  fi
  [[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'
  chmod 0600 "$DB_BACKUP"
  if [[ $TEST_MODE -eq 0 ]]; then chown root:root "$DB_BACKUP"; fi
  fsync_path "$DB_BACKUP"
  fsync_path "$BACKUP_ROOT"
  DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

database_fingerprint() {
  local db=$1
  docker exec -e M068_C01_FINGERPRINT_DB="$db" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]
    case "$M068_C01_FINGERPRINT_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    q(){ MYSQL_PWD="$pass" mariadb -N -u root "$M068_C01_FINGERPRINT_DB" -e "$1"; }
    for item in $(q "SELECT CONCAT(TABLE_NAME,CHAR(58),IF(TABLE_TYPE=CHAR(66,65,83,69,32,84,65,66,76,69),CHAR(66),CHAR(86))) FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY TABLE_NAME"); do
      name=${item%:*}; kind=${item##*:}; case "$name" in ""|*[!A-Za-z0-9_]*) exit 1;; esac
      printf "TABLE=%s TYPE=%s\n" "$name" "$kind"; q "SHOW CREATE TABLE \`$name\`"
      if [ "$kind" = B ]; then q "SELECT COUNT(*) FROM \`$name\`"; q "CHECKSUM TABLE \`$name\`" | awk -F "\t" "{print \"CHECKSUM=\" \$2}"; fi
    done
    for item in $(q "SELECT CONCAT(ROUTINE_NAME,CHAR(58),IF(ROUTINE_TYPE=CHAR(80,82,79,67,69,68,85,82,69),CHAR(80),CHAR(70))) FROM information_schema.routines WHERE routine_schema=DATABASE() ORDER BY ROUTINE_NAME,ROUTINE_TYPE"); do
      name=${item%:*}; kind=${item##*:}; case "$name:$kind" in *[!A-Za-z0-9_:]*) exit 1;; esac
      if [ "$kind" = P ]; then object=PROCEDURE; else object=FUNCTION; fi
      printf "ROUTINE=%s TYPE=%s\n" "$name" "$object"; q "SHOW CREATE $object \`$name\`"
    done
    for name in $(q "SELECT TRIGGER_NAME FROM information_schema.triggers WHERE trigger_schema=DATABASE() ORDER BY TRIGGER_NAME"); do
      case "$name" in ""|*[!A-Za-z0-9_]*) exit 1;; esac; printf "TRIGGER=%s\n" "$name"; q "SHOW CREATE TRIGGER \`$name\`"
    done
    for name in $(q "SELECT EVENT_NAME FROM information_schema.events WHERE event_schema=DATABASE() ORDER BY EVENT_NAME"); do
      case "$name" in ""|*[!A-Za-z0-9_]*) exit 1;; esac; printf "EVENT=%s\n" "$name"; q "SHOW CREATE EVENT \`$name\`"
    done
  ' | sed "s/\`$db\`/\`__DB__\`/g" | sha256sum | awk '{print $1}'
}

validate_database_backup() {
  local restored_key_count
  [[ -f "$DB_BACKUP" && ! -L "$DB_BACKUP" && -s "$DB_BACKUP" && $(sha256 "$DB_BACKUP") == "$DB_BACKUP_SHA" ]] || die 'DATABASE_BACKUP_HASH_INVALID'
  if [[ $TEST_MODE -eq 1 ]]; then
    [[ ${M068_C01_HELPER_TEST_FAULT:-} != RESTORE ]] || die 'INJECTED_DATABASE_RESTORE_CHECK_FAILURE'
    [[ ${M068_C01_HELPER_TEST_FAULT:-} != RESTORED_KEY ]] || die 'DATABASE_BACKUP_CONTAINS_HMAC_KEY'
    return
  fi
  DB_VALIDATION_NAME="m068_c01_restore_$$_$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')"
  docker exec -e M068_C01_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]
    case "$M068_C01_TEMP_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    MYSQL_PWD="$pass" mariadb -u root -e "CREATE DATABASE \`$M068_C01_TEMP_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  ' >/dev/null || die 'DATABASE_RESTORE_CHECK_CREATE_FAILED'
  docker exec -i -e M068_C01_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]
    MYSQL_PWD="$pass" exec mariadb -u root "$M068_C01_TEMP_DB"
  ' <"$DB_BACKUP" || die 'DATABASE_RESTORE_CHECK_IMPORT_FAILED'
  restored_key_count=$(docker exec -e M068_C01_TEMP_DB="$DB_VALIDATION_NAME" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}; [ -n "$pass" ]
    case "$M068_C01_TEMP_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    MYSQL_PWD="$pass" exec mariadb -N -u root "$M068_C01_TEMP_DB" -e "SELECT COUNT(*) FROM llx_const WHERE name=CHAR(67,72,73,78,65,80,76,65,78,69,84,95,80,79,82,84,65,76,95,73,78,86,73,84,65,84,73,79,78,95,72,77,65,67,95,75,69,89)"
  ') || die 'DATABASE_BACKUP_KEY_ABSENCE_CHECK_FAILED'
  [[ "$restored_key_count" == 0 ]] || die 'DATABASE_BACKUP_CONTAINS_HMAC_KEY'
  [[ $(database_fingerprint "$DB_NAME") == $(database_fingerprint "$DB_VALIDATION_NAME") ]] || die 'DATABASE_RESTORE_CHECK_MISMATCH'
  drop_validation_database || die 'DATABASE_RESTORE_CHECK_DROP_FAILED'
}

command_preflight() {
  check_environment; run_tool check
  if [[ $TOOL_RC -eq 2 && "$TOOL_OUTPUT" == M068_C01_CONFIG=ABSENT* ]]; then
    echo "M068_C01_PREFLIGHT=PASS RELEASE=$RELEASE CONFIG=ABSENT ACTION_REQUIRED=YES DATABASE_CHANGED=NO BUSINESS_RECORDS_CHANGED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
  elif [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG=READY* ]]; then
    echo "M068_C01_PREFLIGHT=PASS RELEASE=$RELEASE CONFIG=READY ACTION_REQUIRED=NO DATABASE_CHANGED=NO BUSINESS_RECORDS_CHANGED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
  else die 'CONFIG_PREFLIGHT_FAILED'; fi
}

command_apply() {
  check_environment; ensure_state_roots
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  [[ ! -e "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'PREEXISTING_STATE_REQUIRES_REVIEW'
  run_tool check
  [[ $TOOL_RC -eq 2 && "$TOOL_OUTPUT" == M068_C01_CONFIG=ABSENT* ]] || die 'ABSENT_PRESTATE_REQUIRED'
  APPLY_ENTITY=$(sed -n 's/.* ENTITY=\([0-9][0-9]*\) .*/\1/p' <<<"$TOOL_OUTPUT")
  [[ "$APPLY_ENTITY" =~ ^[1-9][0-9]*$ ]] || die 'ENTITY_PARSE_FAILED'
  APPLY_REQUEST_ID="$(date -u +%Y%m%dT%H%M%SZ)-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
  [[ "$APPLY_REQUEST_ID" =~ ^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$ ]] || die 'REQUEST_ID_INVALID'
  create_database_backup "$APPLY_REQUEST_ID"
  validate_database_backup
  DB_BACKUP_VALIDATED=1
  write_state 'PENDING_APPLY' "$APPLY_REQUEST_ID" "$APPLY_ENTITY" "$DB_BACKUP" "$DB_BACKUP_SHA"
  run_tool check
  [[ $TOOL_RC -eq 2 && "$TOOL_OUTPUT" == M068_C01_CONFIG=ABSENT* ]] || die 'PRE_APPLY_ABSENCE_RECHECK_FAILED'
  run_tool apply
  if [[ $TOOL_RC -ne 0 || "$TOOL_OUTPUT" != M068_C01_CONFIG_APPLY=PASS* ]]; then
    run_tool rollback
    if [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG_ROLLBACK=PASS* ]]; then write_state 'APPLY_FAILED_ROLLED_BACK' "$APPLY_REQUEST_ID" "$APPLY_ENTITY" "$DB_BACKUP" "$DB_BACKUP_SHA"; die 'CONFIG_APPLY_FAILED_ROLLED_BACK'; fi
    write_state 'RECOVERY_REQUIRED' "$APPLY_REQUEST_ID" "$APPLY_ENTITY" "$DB_BACKUP" "$DB_BACKUP_SHA"; die 'CONFIG_APPLY_FAILED_RECOVERY_REQUIRED'
  fi
  CONFIG_MUTATED=1
  [[ ${M068_C01_HELPER_TEST_FAULT:-} != AFTER_CONFIG ]] || die 'INJECTED_AFTER_CONFIG'
  run_tool verify
  [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG_VERIFY=PASS* ]] || die 'CONFIG_VERIFY_FAILED'
  write_state 'DEPLOYED' "$APPLY_REQUEST_ID" "$APPLY_ENTITY" "$DB_BACKUP" "$DB_BACKUP_SHA"
  APPLY_COMPLETE=1; CONFIG_MUTATED=0
  echo "M068_C01_APPLY=PASS RELEASE=$RELEASE STATE=DEPLOYED REQUEST_ID=$APPLY_REQUEST_ID DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA DATABASE_CONFIGURATION_CHANGED=YES DATABASE_BACKUP_CREATED=YES DATABASE_RESTORE_TEST=PASS BUSINESS_RECORDS_CHANGED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
}

command_verify() {
  check_environment; validate_state
  [[ $(state_value_from "$STATE_FILE" STATE_STATUS) == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'
  run_tool verify
  [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG_VERIFY=PASS* ]] || die 'CONFIG_VERIFY_FAILED'
  echo "M068_C01_VERIFY=PASS RELEASE=$RELEASE STATE=DEPLOYED PRESENCE=YES LENGTH_OK=YES DATABASE_CHANGED=NO BUSINESS_RECORDS_CHANGED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
}

command_rollback() {
  check_environment; validate_state
  [[ $(state_value_from "$STATE_FILE" STATE_STATUS) == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  local request_id entity backup_path backup_sha
  request_id=$(state_value_from "$STATE_FILE" STATE_REQUEST_ID)
  entity=$(state_value_from "$STATE_FILE" STATE_ENTITY)
  backup_path=$(state_value_from "$STATE_FILE" STATE_DB_BACKUP)
  backup_sha=$(state_value_from "$STATE_FILE" STATE_DB_BACKUP_SHA)
  run_tool verify
  [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG_VERIFY=PASS* ]] || die 'ROLLBACK_OWNERSHIP_VERIFY_FAILED'
  run_tool rollback
  [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG_ROLLBACK=PASS* ]] || die 'CONFIG_ROLLBACK_FAILED'
  run_tool check
  [[ $TOOL_RC -eq 2 && "$TOOL_OUTPUT" == M068_C01_CONFIG=ABSENT* ]] || die 'CONFIG_ROLLBACK_VERIFY_FAILED'
  write_state 'ROLLED_BACK' "$request_id" "$entity" "$backup_path" "$backup_sha"
  echo "M068_C01_ROLLBACK=PASS RELEASE=$RELEASE STATE=ROLLED_BACK RESTORED_PRESTATE=ABSENT DATABASE_CONFIGURATION_CHANGED=YES FULL_DATABASE_RESTORE_EXECUTED=NO BUSINESS_RECORDS_CHANGED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
}

command_status() {
  check_environment; run_tool check
  local config='ERROR' state='NOT_APPLIED'
  if [[ $TOOL_RC -eq 2 && "$TOOL_OUTPUT" == M068_C01_CONFIG=ABSENT* ]]; then config='ABSENT';
  elif [[ $TOOL_RC -eq 0 && "$TOOL_OUTPUT" == M068_C01_CONFIG=READY* ]]; then config='READY';
  else die 'CONFIG_STATUS_FAILED'; fi
  if [[ -e "$STATE_FILE" || -L "$STATE_FILE" ]]; then validate_state; state=$(state_value_from "$STATE_FILE" STATE_STATUS); fi
  echo "M068_C01_STATUS=PASS RELEASE=$RELEASE CONFIG=$config STATE=$state COMMANDS=preflight,apply,verify,rollback,status DATABASE_CHANGED=NO BUSINESS_RECORDS_CHANGED=NO SECRET_OUTPUT=NO CUSTOMER_DATA_OUTPUT=NO PRODUCTION_ACCESSED=NO"
}

require_invocation "$@"
case "$1" in
  preflight) command_preflight ;;
  apply) command_apply ;;
  verify) command_verify ;;
  rollback) command_rollback ;;
  status) command_status ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
