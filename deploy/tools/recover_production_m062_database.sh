#!/usr/bin/env bash
set -Eeuo pipefail

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M062-PROD-R01'
readonly FAILED_RUNTIME_COMMIT='d6c2953bda1ab6f7c74c99385782542fd853299a'
readonly FAILED_ARTIFACT_SHA='e64dae7c857bc77071368393ea4d5cb50ecc63474798e344a2c35c2554924696'
readonly FAILED_HELPER_SHA='9bb10f73b8ccb1928c15bf2d0b23af17cd9aa1bda7f5578cec70f8b951f56b9f'
readonly SOURCE_BACKUP='/root/chinaplanetos-production-db-backups/m062-before-d6c2953-m062-20260828T070839Z-4f2d6bf8b151.sql'
readonly SOURCE_BACKUP_SHA='c9e94e44f375100c586264241269cbf1521894a2a2d4df5aba0b3dc79602f578'
readonly ERP_CONTAINER='vision_erp'
readonly DB_CONTAINER='vision_db'
readonly DB_VOLUME='vision-os_mariadb_data'
readonly DB_NAME='dolibarr_db'
readonly STATE_ROOT='/var/lib/chinaplanetos-m062-ports-production-r01'
readonly STATE_FILE="${STATE_ROOT}/m062.state"
readonly RECOVERY_ROOT="${STATE_ROOT}/database-recovery"
readonly DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
readonly HELPER='/usr/local/sbin/chinaplanetos-production-m062-ports'
readonly TARGET_HTTP='https://erp.visiontrading.biz/'
readonly LOCK_FILE="${STATE_ROOT}/m062.lock"
readonly MIN_FREE_KB=2097152

SOURCE_VALIDATION_DB=''
SAFETY_VALIDATION_DB=''
SAFETY_BACKUP=''
SAFETY_BACKUP_SHA=''
SAFETY_FINGERPRINT=''
SOURCE_FINGERPRINT=''
STATE_ARCHIVE=''
STATE_FILE_SHA=''
RECORD_FILE=''
STATE_ARCHIVED=0
RECORD_PUBLISHED=0
ERP_STOPPED=0
ACTIVE_MUTATED=0
RECOVERY_COMPLETE=0

die() { echo "M062_DB_RECOVERY_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
mode_of() { stat -c '%a' "$1"; }
owner_group_of() { stat -c '%U:%G' "$1"; }
fsync_path() {
  python3 - "$1" <<'PY'
import os, pathlib, sys
p = pathlib.Path(sys.argv[1])
flags = os.O_RDONLY | (getattr(os, 'O_DIRECTORY', 0) if p.is_dir() else 0)
fd = os.open(str(p), flags)
try:
    os.fsync(fd)
finally:
    os.close(fd)
PY
}

state_value() {
  local key=$1 value count
  count=$(grep -c "^${key}=" "$STATE_FILE" || true)
  [[ "$count" == 1 ]] || return 1
  value=$(sed -n "s/^${key}=//p" "$STATE_FILE")
  [[ "$value" =~ ^[A-Za-z0-9_./:+-]*$ ]] || return 1
  printf '%s' "$value"
}

db_root_sql() {
  local sql=$1
  docker exec -e M062_RECOVERY_SQL="$sql" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
    [ -n "$pass" ]
    MYSQL_PWD="$pass" exec mariadb --batch --skip-column-names -u root -e "$M062_RECOVERY_SQL"
  '
}

create_database() {
  local db=$1 charset=$2 collation=$3
  [[ "$db" =~ ^m062_recovery_(source|safety)_[0-9]+_[a-f0-9]{8}$ || "$db" == "$DB_NAME" ]] || return 1
  [[ "$charset" =~ ^[A-Za-z0-9_]+$ && "$collation" =~ ^[A-Za-z0-9_]+$ ]] || return 1
  docker exec -e M062_RECOVERY_DB="$db" -e M062_RECOVERY_CHARSET="$charset" -e M062_RECOVERY_COLLATION="$collation" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
    [ -n "$pass" ]
    case "$M062_RECOVERY_DB$M062_RECOVERY_CHARSET$M062_RECOVERY_COLLATION" in *[!A-Za-z0-9_]*) exit 1;; esac
    MYSQL_PWD="$pass" exec mariadb -u root -e "CREATE DATABASE \`$M062_RECOVERY_DB\` CHARACTER SET $M062_RECOVERY_CHARSET COLLATE $M062_RECOVERY_COLLATION"
  ' >/dev/null
}

drop_database() {
  local db=$1
  [[ "$db" =~ ^m062_recovery_(source|safety)_[0-9]+_[a-f0-9]{8}$ || "$db" == "$DB_NAME" ]] || return 1
  docker exec -e M062_RECOVERY_DB="$db" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
    [ -n "$pass" ]
    case "$M062_RECOVERY_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    MYSQL_PWD="$pass" exec mariadb -u root -e "DROP DATABASE IF EXISTS \`$M062_RECOVERY_DB\`"
  ' >/dev/null
}

import_database() {
  local db=$1 source=$2
  [[ "$db" =~ ^m062_recovery_(source|safety)_[0-9]+_[a-f0-9]{8}$ || "$db" == "$DB_NAME" ]] || return 1
  [[ -f "$source" && ! -L "$source" ]] || return 1
  docker exec -i -e M062_RECOVERY_DB="$db" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
    [ -n "$pass" ]
    case "$M062_RECOVERY_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    MYSQL_PWD="$pass" exec mariadb -u root "$M062_RECOVERY_DB"
  ' <"$source"
}

database_fingerprint() {
  local db=$1
  [[ "$db" =~ ^m062_recovery_(source|safety)_[0-9]+_[a-f0-9]{8}$ || "$db" == "$DB_NAME" ]] || return 1
  docker exec -e M062_RECOVERY_DB="$db" "$DB_CONTAINER" sh -eu -c '
    pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
    [ -n "$pass" ]
    case "$M062_RECOVERY_DB" in *[!A-Za-z0-9_]*) exit 1;; esac
    q(){ MYSQL_PWD="$pass" mariadb --batch --skip-column-names -u root "$M062_RECOVERY_DB" -e "$1"; }
    q "SELECT TABLE_TYPE,TABLE_NAME,COALESCE(ENGINE,\"\"),COALESCE(TABLE_COLLATION,\"\") FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY TABLE_TYPE,TABLE_NAME"
    q "SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,\"<NULL>\"),EXTRA FROM information_schema.columns WHERE table_schema=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION"
    q "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,COALESCE(SUB_PART,0),INDEX_TYPE FROM information_schema.statistics WHERE table_schema=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX"
    q "SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,ACTION_STATEMENT FROM information_schema.triggers WHERE trigger_schema=DATABASE() ORDER BY TRIGGER_NAME"
    q "SELECT ROUTINE_TYPE,ROUTINE_NAME,COALESCE(DATA_TYPE,\"\"),COALESCE(ROUTINE_DEFINITION,\"\") FROM information_schema.routines WHERE routine_schema=DATABASE() ORDER BY ROUTINE_TYPE,ROUTINE_NAME"
    q "SELECT EVENT_NAME,STATUS,EVENT_DEFINITION FROM information_schema.events WHERE event_schema=DATABASE() ORDER BY EVENT_NAME"
    q "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=\"BASE TABLE\" ORDER BY TABLE_NAME" | while IFS= read -r table; do
      case "$table" in *[!A-Za-z0-9_]*) exit 1;; esac
      printf "TABLE=%s ROWS=" "$table"; q "SELECT COUNT(*) FROM \`$table\`"
      q "CHECKSUM TABLE \`$table\`" | awk -F "\t" "{print \"TABLE=$table CHECKSUM=\" \$2}"
    done
  ' | sha256sum | awk '{print $1}'
}

dump_active_database() {
  local destination=$1
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
    user=${MARIADB_USER:-${MYSQL_USER:-root}}
    pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ "$db" = dolibarr_db ] && [ -n "$pass" ]
    if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
    MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables --hex-blob --routines --triggers --events --default-character-set=utf8mb4 -u "$user" "$db"
  ' >"$destination"
  [[ -s "$destination" ]] || return 1
  chmod 0600 "$destination"
  fsync_path "$destination" && fsync_path "$DB_BACKUP_ROOT"
}

verify_prestate() {
  local db=$1 schema_count row_407 row_407_pod row_407_pod_hex khalifa_rows agadir_rows
  [[ "$db" =~ ^m062_recovery_source_[0-9]+_[a-f0-9]{8}$ || "$db" == "$DB_NAME" ]] || return 1
  schema_count=$(db_root_sql "SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db}' AND table_name='llx_chinaplanetsp_port_alias') + (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${db}' AND table_name='llx_chinaplanetsp_rate' AND column_name IN ('pod_raw','fk_destination_port','port_match_method','port_match_score')) + (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='${db}' AND table_name='llx_chinaplanetsp_rate' AND index_name='idx_chinaplanetsp_rate_destination_port')")
  [[ "$schema_count" == 0 ]] || return 1
  row_407=$(db_root_sql "SELECT COUNT(*) FROM \`${db}\`.llx_chinaplanetsp_rate WHERE rowid=407 AND pod='CASABLANCA'")
  row_407_pod=$(db_root_sql "SELECT COALESCE(pod,'<NULL>') FROM \`${db}\`.llx_chinaplanetsp_rate WHERE rowid=407 LIMIT 1")
  row_407_pod_hex=$(printf '%s' "$row_407_pod" | od -An -tx1 | tr -d ' \n')
  khalifa_rows=$(db_root_sql "SELECT COUNT(*) FROM \`${db}\`.llx_chinaplanetsp_rate WHERE pod='KHALIFA PORT, ABU DHABI'")
  agadir_rows=$(db_root_sql "SELECT COUNT(*) FROM \`${db}\`.llx_chinaplanetsp_rate WHERE pod='AGADIR, Morocco'")
  echo "M062_DB_RECOVERY_PRESTATE DB=$db SCHEMA_COUNT=$schema_count ROW_407_CASABLANCA=$row_407 ROW_407_POD_HEX=$row_407_pod_hex KHALIFA_ROWS=$khalifa_rows AGADIR_MOROCCO_ROWS=$agadir_rows" >&2
  [[ "$row_407" == 1 ]] || return 1
  [[ "$khalifa_rows" == 24 && "$agadir_rows" == 22 ]]
}

restore_safety_backup() {
  [[ -n "$SAFETY_BACKUP" && -f "$SAFETY_BACKUP" && ! -L "$SAFETY_BACKUP" && $(sha256 "$SAFETY_BACKUP") == "$SAFETY_BACKUP_SHA" ]] || return 1
  [[ "$SAFETY_FINGERPRINT" =~ ^[a-f0-9]{64}$ ]] || return 1
  ensure_erp_stopped || return 1
  drop_database "$DB_NAME" && create_database "$DB_NAME" "$DB_CHARSET" "$DB_COLLATION" && import_database "$DB_NAME" "$SAFETY_BACKUP" || return 1
  [[ $(database_fingerprint "$DB_NAME") == "$SAFETY_FINGERPRINT" ]]
}

ensure_erp_stopped() {
  local identity
  identity=$(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$ERP_CONTAINER") || return 1
  case "$identity" in
    'true|vision-os|dolibarr') docker stop --time 30 "$ERP_CONTAINER" >/dev/null || return 1; ERP_STOPPED=1 ;;
    'false|vision-os|dolibarr') ERP_STOPPED=1 ;;
    *) return 1 ;;
  esac
  [[ $(docker inspect -f '{{.State.Running}}' "$ERP_CONTAINER") == false ]]
}

restore_archived_state() {
  local archive=$1 state_file=$2 expected_sha=$3
  [[ -f "$archive" && ! -L "$archive" && $(owner_group_of "$archive") == root:root && $(mode_of "$archive") == 600 && $(sha256 "$archive") == "$expected_sha" ]] || return 1
  [[ ! -e "$state_file" && ! -L "$state_file" ]] || return 1
  mv "$archive" "$state_file" || return 1
  chmod 0600 "$state_file" || return 1
  [[ $(sha256 "$state_file") == "$expected_sha" ]] || return 1
  fsync_path "$state_file" && fsync_path "$(dirname "$state_file")"
}

prepare_lock() {
  local root=$1 lock=$2
  [[ -d "$root" && ! -L "$root" && $(owner_group_of "$root") == root:root && $(mode_of "$root") == 700 ]] || return 1
  if [[ -e "$lock" || -L "$lock" ]]; then
    [[ -f "$lock" && ! -L "$lock" && $(owner_group_of "$lock") == root:root && $(mode_of "$lock") == 600 ]] || return 1
  else
    (set -o noclobber; : >"$lock") 2>/dev/null || return 1
    chmod 0600 "$lock" || return 1
    fsync_path "$lock" && fsync_path "$root" || return 1
  fi
  exec 9<>"$lock"
  flock -n 9
}

prepare_private_root() {
  local root=$1 parent
  parent=$(dirname "$root")
  [[ -d "$parent" && ! -L "$parent" && $(owner_group_of "$parent") == root:root && $(mode_of "$parent") == 700 ]] || return 1
  if [[ -e "$root" || -L "$root" ]]; then
    [[ -d "$root" && ! -L "$root" && $(owner_group_of "$root") == root:root && $(mode_of "$root") == 700 ]] || return 1
  else
    install -d -o root -g root -m 0700 "$root" || return 1
    fsync_path "$root" && fsync_path "$parent" || return 1
  fi
}

start_erp_and_wait() {
  docker start "$ERP_CONTAINER" >/dev/null || return 1
  ERP_STOPPED=0
  local attempt
  for attempt in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
    if [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 5 "$TARGET_HTTP" || true) =~ ^(200|302)$ ]]; then return 0; fi
    sleep 2
  done
  return 1
}

drop_validation_databases() {
  local failed=0
  if [[ -n "$SOURCE_VALIDATION_DB" ]]; then
    if drop_database "$SOURCE_VALIDATION_DB"; then SOURCE_VALIDATION_DB=''; else failed=1; fi
  fi
  if [[ -n "$SAFETY_VALIDATION_DB" ]]; then
    if drop_database "$SAFETY_VALIDATION_DB"; then SAFETY_VALIDATION_DB=''; else failed=1; fi
  fi
  [[ $failed -eq 0 ]]
}

mark_record_status() {
  local record=$1 status=$2 tmp
  case "$status" in ROLLED_BACK|ROLLBACK_FAILED) ;; *) return 1 ;; esac
  [[ -f "$record" && ! -L "$record" && $(owner_group_of "$record") == root:root && $(mode_of "$record") == 600 ]] || return 1
  [[ $(grep -Fxc 'RECOVERY_STATUS=PASS' "$record") == 1 ]] || return 1
  tmp=$(mktemp "$(dirname "$record")/.rolled-back.XXXXXX") || return 1
  sed "s/^RECOVERY_STATUS=PASS$/RECOVERY_STATUS=${status}/" "$record" >"$tmp" || return 1
  printf '%s_AT=%s\n' "$status" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >>"$tmp"
  chmod 0600 "$tmp" || return 1
  fsync_path "$tmp" || return 1
  mv "$tmp" "$record" || return 1
  fsync_path "$record" && fsync_path "$(dirname "$record")"
}

cleanup() {
  local rc=$? recovery_rc=0 db_restore_ok=1 state_restore_ok=1 record_status state_archive_observed=0 record_published_observed=0
  trap - EXIT INT TERM HUP
  if [[ $STATE_ARCHIVED -eq 1 || ( -n "$STATE_ARCHIVE" && -f "$STATE_ARCHIVE" && ! -L "$STATE_ARCHIVE" && ! -e "$STATE_FILE" && ! -L "$STATE_FILE" ) ]]; then state_archive_observed=1; fi
  if [[ $RECORD_PUBLISHED -eq 1 || ( -n "$RECORD_FILE" && -f "$RECORD_FILE" && ! -L "$RECORD_FILE" ) ]]; then record_published_observed=1; fi
  if [[ $ACTIVE_MUTATED -eq 1 && $RECOVERY_COMPLETE -eq 0 ]]; then
    if ! restore_safety_backup; then db_restore_ok=0; recovery_rc=1; fi
    if [[ $state_archive_observed -eq 1 ]]; then
      if [[ -n "$STATE_ARCHIVE" && -f "$STATE_ARCHIVE" && ! -L "$STATE_ARCHIVE" && ! -e "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
        if ! restore_archived_state "$STATE_ARCHIVE" "$STATE_FILE" "$STATE_FILE_SHA"; then state_restore_ok=0; recovery_rc=1; fi
      else
        state_restore_ok=0; recovery_rc=1
      fi
    fi
  fi
  if [[ $ERP_STOPPED -eq 1 && $recovery_rc -eq 0 ]]; then start_erp_and_wait || recovery_rc=1; fi
  if [[ $recovery_rc -eq 0 ]]; then drop_validation_databases || recovery_rc=1; fi
  if [[ $ACTIVE_MUTATED -eq 1 && $RECOVERY_COMPLETE -eq 0 && $record_published_observed -eq 1 ]]; then
    if [[ $recovery_rc -eq 0 && $db_restore_ok -eq 1 && $state_restore_ok -eq 1 ]]; then record_status=ROLLED_BACK; else record_status=ROLLBACK_FAILED; fi
    if [[ -n "$RECORD_FILE" && -f "$RECORD_FILE" && ! -L "$RECORD_FILE" ]]; then
      mark_record_status "$RECORD_FILE" "$record_status" || recovery_rc=1
    else
      recovery_rc=1
    fi
  fi
  if [[ $recovery_rc -ne 0 ]]; then
    erp_stop_verified=YES
    ensure_erp_stopped || erp_stop_verified=NO
    echo "M062_DB_RECOVERY_AUTO_RESTORE=FAIL MANUAL_RECOVERY_ATTENTION_REQUIRED=YES ERP_STOP_VERIFIED=$erp_stop_verified SOURCE_VALIDATION_DB=$SOURCE_VALIDATION_DB SAFETY_VALIDATION_DB=$SAFETY_VALIDATION_DB SAFETY_BACKUP=$SAFETY_BACKUP SAFETY_BACKUP_SHA=$SAFETY_BACKUP_SHA" >&2
    exit 1
  fi
  if [[ $ACTIVE_MUTATED -eq 1 && $RECOVERY_COMPLETE -eq 0 ]]; then echo "M062_DB_RECOVERY_AUTO_RESTORE=PASS SAFETY_BACKUP=$SAFETY_BACKUP SAFETY_BACKUP_SHA=$SAFETY_BACKUP_SHA ERP_HTTP=PASS STATE_RESTORED=$state_restore_ok" >&2; fi
  exit "$rc"
}
trap cleanup EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ${M062_DB_RECOVERY_LIBRARY_MODE:-0} == 1 ]]; then
  case "${BASH_SOURCE[0]}" in /tmp/m062-db-recovery-test.*/*|/private/tmp/m062-db-recovery-test.*/*) ;; *) die 'LIBRARY_MODE_FORBIDDEN' ;; esac
  trap - EXIT INT TERM HUP
  return 0 2>/dev/null || exit 0
fi

[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
[[ "$0" == '/root/recover-production-m062-database-r03' && -f "$0" && ! -L "$0" && $(owner_group_of "$0") == root:root && $(mode_of "$0") == 700 ]] || die 'RECOVERY_PATH_NOT_ALLOWED'
[[ $# -eq 0 ]] || die 'ARGUMENTS_FORBIDDEN'

for root in "$STATE_ROOT" "$DB_BACKUP_ROOT"; do
  [[ -d "$root" && ! -L "$root" && $(owner_group_of "$root") == root:root && $(mode_of "$root") == 700 ]] || die 'PRIVATE_ROOT_INVALID'
done
prepare_lock "$STATE_ROOT" "$LOCK_FILE" || die 'LOCK_INVALID_OR_BUSY'
prepare_private_root "$RECOVERY_ROOT" || die 'RECOVERY_ROOT_INVALID'
[[ -f "$SOURCE_BACKUP" && ! -L "$SOURCE_BACKUP" && $(owner_group_of "$SOURCE_BACKUP") == root:root && $(mode_of "$SOURCE_BACKUP") == 600 ]] || die 'SOURCE_BACKUP_METADATA_INVALID'
[[ $(sha256 "$SOURCE_BACKUP") == "$SOURCE_BACKUP_SHA" ]] || die 'SOURCE_BACKUP_HASH_INVALID'
[[ -f "$HELPER" && ! -L "$HELPER" && $(owner_group_of "$HELPER") == root:root && $(mode_of "$HELPER") == 755 && $(sha256 "$HELPER") == "$FAILED_HELPER_SHA" ]] || die 'HELPER_STATE_INVALID'
[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(owner_group_of "$STATE_FILE") == root:root && $(mode_of "$STATE_FILE") == 600 ]] || die 'STATE_METADATA_INVALID'
[[ $(state_value STATE_RELEASE) == "$RELEASE" ]] || die 'STATE_RELEASE_MISMATCH'
[[ $(state_value STATE_STATUS) == 'APPLY_FAILED_DB_RECOVERY_REQUIRED' ]] || die 'STATE_STATUS_MISMATCH'
[[ $(state_value STATE_RUNTIME_COMMIT) == "$FAILED_RUNTIME_COMMIT" ]] || die 'STATE_COMMIT_MISMATCH'
[[ $(state_value STATE_ARTIFACT_SHA) == "$FAILED_ARTIFACT_SHA" ]] || die 'STATE_ARTIFACT_MISMATCH'
[[ $(state_value STATE_DB_BACKUP) == "$SOURCE_BACKUP" && $(state_value STATE_DB_BACKUP_SHA) == "$SOURCE_BACKUP_SHA" ]] || die 'STATE_BACKUP_MISMATCH'
STATE_FILE_SHA=$(sha256 "$STATE_FILE")
[[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$ERP_CONTAINER") == 'true|vision-os|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
[[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER") == 'true|vision-os|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$DB_CONTAINER") == "${DB_VOLUME}|true" ]] || die 'DB_MOUNT_MISMATCH'
[[ $(docker exec "$DB_CONTAINER" sh -eu -c 'printf %s "${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}"') == "$DB_NAME" ]] || die 'DB_NAME_MISMATCH'
db_mount=$(docker volume inspect -f '{{.Mountpoint}}' "$DB_VOLUME")
[[ -d "$db_mount" && ! -L "$db_mount" ]] || die 'DB_VOLUME_PATH_INVALID'
source_kb=$((($(stat -c '%s' "$SOURCE_BACKUP") + 1023) / 1024))
required_backup_kb=$((source_kb * 2 + MIN_FREE_KB / 2))
required_db_kb=$((source_kb * 4 + MIN_FREE_KB))
[[ $(df -Pk "$DB_BACKUP_ROOT" | awk 'NR==2 {print $4}') -ge $required_backup_kb ]] || die 'BACKUP_DISK_LOW'
[[ $(df -Pk "$db_mount" | awk 'NR==2 {print $4}') -ge $required_db_kb ]] || die 'DB_VOLUME_DISK_LOW'

read -r DB_CHARSET DB_COLLATION < <(db_root_sql "SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.schemata WHERE schema_name='${DB_NAME}'")
[[ "$DB_CHARSET" =~ ^[A-Za-z0-9_]+$ && "$DB_COLLATION" =~ ^[A-Za-z0-9_]+$ ]] || die 'DATABASE_DEFAULTS_INVALID'
nonce=$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')
SOURCE_VALIDATION_DB="m062_recovery_source_$$_${nonce}"
nonce=$(od -An -N4 -tx1 /dev/urandom | tr -d ' \n')
SAFETY_VALIDATION_DB="m062_recovery_safety_$$_${nonce}"

create_database "$SOURCE_VALIDATION_DB" "$DB_CHARSET" "$DB_COLLATION" || die 'SOURCE_VALIDATION_CREATE_FAILED'
import_database "$SOURCE_VALIDATION_DB" "$SOURCE_BACKUP" || die 'SOURCE_VALIDATION_IMPORT_FAILED'
SOURCE_FINGERPRINT=$(database_fingerprint "$SOURCE_VALIDATION_DB")
[[ "$SOURCE_FINGERPRINT" =~ ^[a-f0-9]{64}$ ]] || die 'SOURCE_FINGERPRINT_INVALID'
verify_prestate "$SOURCE_VALIDATION_DB" || die 'SOURCE_PRESTATE_INVALID'

recovery_id="m062-recovery-$(date -u '+%Y%m%dT%H%M%SZ')-${nonce}"
SAFETY_BACKUP="${DB_BACKUP_ROOT}/${recovery_id}-partial-state-safety.sql"
[[ ! -e "$SAFETY_BACKUP" && ! -L "$SAFETY_BACKUP" ]] || die 'SAFETY_BACKUP_COLLISION'
ensure_erp_stopped || die 'ERP_STOP_FAILED'
dump_active_database "$SAFETY_BACKUP" || die 'SAFETY_BACKUP_FAILED'
SAFETY_BACKUP_SHA=$(sha256 "$SAFETY_BACKUP")
create_database "$SAFETY_VALIDATION_DB" "$DB_CHARSET" "$DB_COLLATION" || die 'SAFETY_VALIDATION_CREATE_FAILED'
import_database "$SAFETY_VALIDATION_DB" "$SAFETY_BACKUP" || die 'SAFETY_VALIDATION_IMPORT_FAILED'
SAFETY_FINGERPRINT=$(database_fingerprint "$SAFETY_VALIDATION_DB")
[[ "$SAFETY_FINGERPRINT" =~ ^[a-f0-9]{64}$ && $(database_fingerprint "$DB_NAME") == "$SAFETY_FINGERPRINT" ]] || die 'SAFETY_VALIDATION_MISMATCH'

ACTIVE_MUTATED=1
drop_database "$DB_NAME" || die 'ACTIVE_DROP_FAILED'
create_database "$DB_NAME" "$DB_CHARSET" "$DB_COLLATION" || die 'ACTIVE_CREATE_FAILED'
import_database "$DB_NAME" "$SOURCE_BACKUP" || die 'ACTIVE_IMPORT_FAILED'
[[ $(database_fingerprint "$DB_NAME") == "$SOURCE_FINGERPRINT" ]] || die 'ACTIVE_FINGERPRINT_MISMATCH'
verify_prestate "$DB_NAME" || die 'ACTIVE_PRESTATE_INVALID'

start_erp_and_wait || die 'ERP_HTTP_UNHEALTHY'
drop_validation_databases || die 'VALIDATION_DATABASE_CLEANUP_FAILED'

STATE_ARCHIVE="${RECOVERY_ROOT}/${recovery_id}.state.before-recovery"
RECORD_FILE="${RECOVERY_ROOT}/${recovery_id}.record"
[[ ! -e "$STATE_ARCHIVE" && ! -L "$STATE_ARCHIVE" && ! -e "$RECORD_FILE" && ! -L "$RECORD_FILE" ]] || die 'RECOVERY_RECORD_COLLISION'
mv "$STATE_FILE" "$STATE_ARCHIVE"
STATE_ARCHIVED=1
chmod 0600 "$STATE_ARCHIVE"
[[ $(sha256 "$STATE_ARCHIVE") == "$STATE_FILE_SHA" ]] || die 'STATE_ARCHIVE_HASH_MISMATCH'
record_tmp=$(mktemp "${RECOVERY_ROOT}/.record.XXXXXX")
printf 'RECOVERY_ID=%s\nRECOVERY_STATUS=PASS\nRELEASE=%s\nPRIOR_STATUS=APPLY_FAILED_DB_RECOVERY_REQUIRED\nSOURCE_BACKUP=%s\nSOURCE_BACKUP_SHA=%s\nSOURCE_FINGERPRINT=%s\nSAFETY_BACKUP=%s\nSAFETY_BACKUP_SHA=%s\nSAFETY_FINGERPRINT=%s\nSTATE_ARCHIVE=%s\nSTATE_FILE_SHA=%s\nRECOVERED_AT=%s\nPRODUCTION_ACCESSED=YES\n' \
  "$recovery_id" "$RELEASE" "$SOURCE_BACKUP" "$SOURCE_BACKUP_SHA" "$SOURCE_FINGERPRINT" "$SAFETY_BACKUP" "$SAFETY_BACKUP_SHA" "$SAFETY_FINGERPRINT" "$STATE_ARCHIVE" "$STATE_FILE_SHA" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >"$record_tmp"
chmod 0600 "$record_tmp"
mv "$record_tmp" "$RECORD_FILE"
RECORD_PUBLISHED=1
fsync_path "$STATE_ARCHIVE" && fsync_path "$RECORD_FILE" && fsync_path "$RECOVERY_ROOT" && fsync_path "$STATE_ROOT"

RECOVERY_COMPLETE=1
ACTIVE_MUTATED=0
echo "M062_DB_RECOVERY=PASS RECOVERY_ID=$recovery_id SOURCE_BACKUP_SHA=$SOURCE_BACKUP_SHA SOURCE_FINGERPRINT=$SOURCE_FINGERPRINT SAFETY_BACKUP=$SAFETY_BACKUP SAFETY_BACKUP_SHA=$SAFETY_BACKUP_SHA STATE_ARCHIVE=$STATE_ARCHIVE DATABASE_PRESTATE=MIGRATION_REQUIRED ERP_HTTP=PASS PRODUCTION_ACCESSED=YES"
