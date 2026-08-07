#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M045-P01'
readonly RUNTIME_COMMIT='28ce3ee50886dc70e680717dcfc6f36161b6a8d1'
readonly SHORT_COMMIT='28ce3ee'
readonly ARTIFACT_SHA='c413922cebb16645d1dfdd59da8d027cea7d49e838639b4302ac4d872e2ee794'
readonly RUNTIME_MANIFEST_SHA='21c948877b57b9c3851a374db61df68ea39886174bb05170862c0a958da52608'
readonly SUPPORT_MANIFEST_SHA='5831f1e4107e25dc6e3dce9f16c3ec871999565dcf6add84e6810e414d6549e7'
readonly RUNTIME_MANIFEST='deploy/releases/m045-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m045-support-files.txt'
readonly MIGRATION='deploy/migrations/m045_public_request_quotation_schema.php'
readonly SOURCE_TEST='deploy/tests/test_m045_public_request_quotation_source.php'
readonly PRODUCT_SYNC='deploy/tools/m045_c01_sync_cms_product.php'
readonly PRODUCT_FIXTURE='deploy/fixtures/m045_c01_wp_9688b.json'

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m045c01-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='vision_erp'
readonly DB_CONTAINER='vision_db'
readonly TARGET_VOLUME='vision-os_dolibarr_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly TARGET_HTTP='https://erp.visiontrading.biz'
readonly BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
readonly DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m045-production'
readonly STATE_FILE="${STATE_ROOT}/m045.state"
readonly LOCK_FILE="${STATE_ROOT}/m045.lock"

RUNTIME_FILES=()
SUPPORT_FILES=()
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''
SCHEMA_PREEXISTED='0'
MUTATED=0

die() { echo "M045_PRODUCTION_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

restore_code() {
  [[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
  tar -xzpf "$CODE_BACKUP" -C "$TARGET_ROOT" || return 1
  local rel target
  while IFS= read -r rel; do
    [[ -z "$rel" ]] && continue
    [[ "$rel" != /* && "$rel" != *'..'* ]] || return 1
    target="$TARGET_ROOT/$rel"
    [[ "$target" == "$TARGET_ROOT/"* ]] || return 1
    rm -f "$target"
  done <"$ABSENT_LIST"
}

restore_database() {
  [[ -f "$DB_BACKUP" && $(sha256 "$DB_BACKUP") == "$DB_BACKUP_SHA" ]] || return 1
  docker exec -i "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; MYSQL_PWD="$pass" exec mariadb -u "$user" "$db"
  ' <"$DB_BACKUP"
}

cleanup() {
  local rc=$? code_ok=0 db_ok=0
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    restore_code && code_ok=1
    restore_database && db_ok=1
    if [[ $code_ok -eq 1 && $db_ok -eq 1 ]]; then
      echo 'M045_PRODUCTION_AUTO_ROLLBACK=PASS' >&2
    else
      echo 'M045_PRODUCTION_AUTO_ROLLBACK=FAIL' >&2
    fi
  fi
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m045-production-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m045-production-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m045-production-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-production-m045-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

check_environment() {
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ "$TARGET_CONTAINER" == 'vision_erp' && "$DB_CONTAINER" == 'vision_db' ]] || die 'PRODUCTION_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'ERP_NOT_RUNNING'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'DB_NOT_RUNNING'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  for root in "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"; do
    [[ -d "$root" && ! -L "$root" && $(stat -c '%U:%G %a' "$root") == 'root:root 700' ]] || die 'PROTECTED_ROOT_INVALID'
  done
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge 1048576 ]] || die 'TARGET_DISK_LOW'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m045-production-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual file
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 33 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m045-production-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
  mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
  [[ ${#RUNTIME_FILES[@]} -eq 9 && ${#SUPPORT_FILES[@]} -eq 4 ]] || die 'MANIFEST_COUNT_INVALID'
  [[ -z $(printf '%s\n' "${RUNTIME_FILES[@]}" | awk '$0 !~ /^custom\/chinaplanetoperations\// {print; exit}') ]] || die 'RUNTIME_SCOPE_INVALID'
  [[ -z $(printf '%s\n' "${SUPPORT_FILES[@]}" | awk '$0 !~ /^deploy\/(migrations|tests|tools|fixtures)\// {print; exit}') ]] || die 'SUPPORT_SCOPE_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die 'ARTIFACT_FILE_INVALID'; done
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m045-production-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
  docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
  local file
  for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
    if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die 'CANDIDATE_LINT_FAILED'; fi
  done
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SOURCE_TEST" >/dev/null || die 'CANDIDATE_SOURCE_TEST_FAILED'
}

schema_prestate() {
  local output rc
  set +e
  output=$(docker exec -e M045_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check 2>&1)
  rc=$?
  set -e
  if [[ $rc -eq 0 && "$output" == 'M045_SCHEMA_CHECK=PASS' ]]; then SCHEMA_PREEXISTED='1'; return; fi
  if [[ $rc -eq 2 && "$output" == 'M045_SCHEMA_CHECK=ABSENT' ]]; then SCHEMA_PREEXISTED='0'; return; fi
  die 'SCHEMA_PRESTATE_DRIFT'
}

compare_files() {
  local file rel target release_hash target_hash state
  for file in "${RUNTIME_FILES[@]}"; do
    rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
    if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M045_PRODUCTION_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
    [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
    target_hash=$(sha256 "$target"); state='UPDATE_REQUIRED'; [[ "$target_hash" == "$release_hash" ]] && state='CURRENT'
    echo "M045_PRODUCTION_COMPARE FILE=$file STATE=$state TARGET_SHA=$target_hash RELEASE_SHA=$release_hash"
  done
}

create_code_backup() {
  local stamp rel target
  local -a existing=()
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m045-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  ABSENT_LIST="${BACKUP_ROOT}/m045-absent-before-${SHORT_COMMIT}-${stamp}.txt"
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
  DB_BACKUP="${DB_BACKUP_ROOT}/m045-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
  docker exec "$DB_CONTAINER" sh -eu -c '
    db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}; user=${MARIADB_USER:-${MYSQL_USER:-root}}; pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
    [ -n "$db" ] && [ -n "$pass" ]; if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
    MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
  ' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
  [[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'
  chmod 0600 "$DB_BACKUP"; DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

apply_schema() { docker exec -e M045_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" apply >/dev/null || die 'SCHEMA_APPLY_FAILED'; }

apply_code() {
  local rel src target tmp
  for rel in "${RUNTIME_FILES[@]}"; do
    src="$CANDIDATE_ROOT/$rel"; target="$TARGET_ROOT/${rel#custom/}"; tmp="${target}.m045.$$"
    install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"
    install -o www-data -g www-data -m 0644 "$src" "$tmp"
    mv -f "$tmp" "$target"
  done
}

sync_acceptance_product() {
  docker exec -e M045_DOL_DOCUMENT_ROOT=/var/www/html -e M045_C01_PRODUCT_JSON="$CANDIDATE_REMOTE/$PRODUCT_FIXTURE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$PRODUCT_SYNC" apply >/dev/null || die 'PRODUCT_SYNC_FAILED'
}

live_checks() {
  local rel status request_body
  for rel in "${RUNTIME_FILES[@]}"; do
    [[ $(sha256 "$TARGET_ROOT/${rel#custom/}") == $(sha256 "$CANDIDATE_ROOT/$rel") ]] || return 1
    if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; fi
  done
  docker exec -e M045_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION" check >/dev/null || return 1
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SOURCE_TEST" >/dev/null || return 1
  docker exec -e M045_DOL_DOCUMENT_ROOT=/var/www/html -e M045_C01_PRODUCT_JSON="$CANDIDATE_REMOTE/$PRODUCT_FIXTURE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$PRODUCT_SYNC" check >/dev/null || return 1
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
  request_body=$(mktemp)
  status=$(curl -sS -o "$request_body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/custom/chinaplanetoperations/operations/public_requests.php") || { rm -f "$request_body"; return 1; }
  if [[ "$status" =~ ^(302|401|403)$ ]]; then rm -f "$request_body"; return 0; fi
  if [[ "$status" == '200' ]] && grep -Fq '<form id="login"' "$request_body" && grep -Fq 'name="actionlogin"' "$request_body"; then rm -f "$request_body"; return 0; fi
  rm -f "$request_body"; return 1
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
  CODE_BACKUP="$STATE_CODE_BACKUP"; CODE_BACKUP_SHA="$STATE_CODE_BACKUP_SHA"
  ABSENT_LIST="$STATE_ABSENT_LIST"; ABSENT_LIST_SHA="$STATE_ABSENT_LIST_SHA"
  DB_BACKUP="$STATE_DB_BACKUP"; DB_BACKUP_SHA="$STATE_DB_BACKUP_SHA"
  SCHEMA_PREEXISTED="$STATE_SCHEMA_PREEXISTED"
}

command_compare() { check_environment; validate_artifact; candidate_checks; schema_prestate; compare_files; echo "M045_PRODUCTION_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; schema_prestate; echo "M045_PRODUCTION_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA_PREEXISTED=$SCHEMA_PREEXISTED"; }

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; schema_prestate; create_code_backup; create_database_backup
  MUTATED=1
  apply_schema; apply_code; sync_acceptance_product
  live_checks || die 'LIVE_CHECK_FAILED'
  save_state; MUTATED=0
  echo "M045_PRODUCTION_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA"
}

command_verify() { check_environment; validate_artifact; candidate_checks; load_state; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M045_PRODUCTION_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; load_state
  local reapply="${BACKUP_ROOT}/m045-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czpf "$reapply" -C "$TARGET_ROOT" "${RUNTIME_FILES[@]#custom/}"; chmod 0600 "$reapply"
  local code_ok=0 db_ok=0
  restore_code && code_ok=1
  restore_database && db_ok=1
  [[ $code_ok -eq 1 ]] || die 'CODE_RESTORE_FAILED'
  [[ $db_ok -eq 1 ]] || die 'DATABASE_RESTORE_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"
  echo "M045_PRODUCTION_ROLLBACK=PASS REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") DATABASE_RESTORED=YES"
}

command_status() {
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
    grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PREEXISTED|DEPLOYED_AT)=' "$STATE_FILE"
  else
    echo 'M045_PRODUCTION_STATUS=NOT_DEPLOYED'
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
