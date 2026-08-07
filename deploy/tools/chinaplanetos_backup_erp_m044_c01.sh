#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M044-C01'
readonly RUNTIME_COMMIT='dc6387f41611f2bec24c5a96eb8aacc252f019ce'
readonly SHORT_COMMIT='dc6387f'
readonly ARTIFACT_SHA='946479da0e9072ff83e2145ac5a82e75e2109459e262e449ef7375a53446a847'
readonly RUNTIME_MANIFEST_SHA='51031466dad513a091cfb7e0b03b09ea57b2648159c1b3148548d18f2ce902fd'
readonly SUPPORT_MANIFEST_SHA='e2a5226abfcde343cea1a2777ee5a2184319850356c071bdc4a6ef5cc57b0822'
readonly SERVICE_SHA='60912061f2b613b648fcebe7d0bee5112b506aeee3720dbb02132276efa3a333'
readonly IMPORTER_SHA='f2ba5156aecdf7ec9a065bf7fd7b9a21173f97ddea9365538deade9fb4b168d3'
readonly IMPORT_PAGE_SHA='35261cc40213fab3988cb32a85eae170a1239799eaef8b0b573ab1da1245584c'

readonly RUNTIME_MANIFEST='deploy/releases/m044-c01-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m044-c01-support-files.txt'
readonly RUNTIME_FILE='custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
readonly SUPPORT_TEST='deploy/tests/test_m044_c01_import_dependency.php'
readonly IMPORTER_REL='chinaplanetshippingprices/class/ShippingPriceImporter.class.php'
readonly IMPORT_PAGE_REL='chinaplanetshippingprices/admin/import.php'
readonly SERVICE_REL='chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m044-c01-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m044-c01-staging'
readonly STATE_FILE="${STATE_ROOT}/m044-c01.state"
readonly LOCK_FILE="${STATE_ROOT}/m044-c01.lock"

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
PREVIOUS_STATE=''
MUTATED=0

die() { echo "M044_C01_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

restore_code() {
  local target="$TARGET_ROOT/$SERVICE_REL"
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  case "$PREVIOUS_STATE" in
    ABSENT)
      [[ "$target" == "$TARGET_ROOT/"* ]] || return 1
      rm -f "$target"
      ;;
    PRESENT)
      tar -xzpf "$CODE_BACKUP" -C "$TARGET_ROOT" "$SERVICE_REL" || return 1
      ;;
    *) return 1 ;;
  esac
}

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    if restore_code; then echo 'M044_C01_STAGING_AUTO_ROLLBACK=PASS' >&2; else echo 'M044_C01_STAGING_AUTO_ROLLBACK=FAIL' >&2; fi
  fi
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m044-c01-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m044-c01-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m044-c01-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m044-c01-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
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
  [[ -d "$BACKUP_ROOT" && ! -L "$BACKUP_ROOT" && $(stat -c '%U:%G %a' "$BACKUP_ROOT") == 'root:root 700' ]] || die 'BACKUP_ROOT_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
}

check_prerequisites() {
  local importer="$TARGET_ROOT/$IMPORTER_REL" page="$TARGET_ROOT/$IMPORT_PAGE_REL"
  [[ -f "$importer" && ! -L "$importer" && $(sha256 "$importer") == "$IMPORTER_SHA" ]] || die 'IMPORTER_PREREQUISITE_DRIFT'
  [[ -f "$page" && ! -L "$page" && $(sha256 "$page") == "$IMPORT_PAGE_SHA" ]] || die 'IMPORT_PAGE_PREREQUISITE_DRIFT'
  grep -Fq "require_once __DIR__.'/ShippingNoteTranslationService.class.php';" "$importer" || die 'IMPORTER_DEPENDENCY_DECLARATION_ABSENT'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m044-c01-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 10 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m044-c01-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  [[ $(cat "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_FILE" ]] || die 'RUNTIME_MANIFEST_SCOPE_INVALID'
  [[ $(cat "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_TEST" ]] || die 'SUPPORT_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "$RUNTIME_FILE" "$SUPPORT_TEST" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_FILE") == "$SERVICE_SHA" ]] || die 'SERVICE_HASH_MISMATCH'
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m044-c01-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/class" "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/admin" "$CANDIDATE_REMOTE/deploy/tests"
  docker cp "$CANDIDATE_ROOT/$RUNTIME_FILE" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$RUNTIME_FILE"
  docker cp "$CANDIDATE_ROOT/$SUPPORT_TEST" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$SUPPORT_TEST"
  docker exec "$TARGET_CONTAINER" cp "/var/www/html/custom/$IMPORTER_REL" "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php"
  docker exec "$TARGET_CONTAINER" cp "/var/www/html/custom/$IMPORT_PAGE_REL" "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/admin/import.php"
  docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$RUNTIME_FILE" >/dev/null || die 'CANDIDATE_SERVICE_LINT_FAILED'
  docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$SUPPORT_TEST" >/dev/null || die 'CANDIDATE_TEST_LINT_FAILED'
  docker exec -e M044_C01_DOL_DOCUMENT_ROOT="$CANDIDATE_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SUPPORT_TEST" >/dev/null || die 'CANDIDATE_DEPENDENCY_TEST_FAILED'
}

compare_file() {
  local target="$TARGET_ROOT/$SERVICE_REL" target_sha
  if [[ ! -e "$target" && ! -L "$target" ]]; then
    echo "M044_C01_STAGING_COMPARE FILE=$RUNTIME_FILE STATE=ABSENT RELEASE_SHA=$SERVICE_SHA"
    return
  fi
  [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_SERVICE_INVALID'
  target_sha=$(sha256 "$target")
  if [[ "$target_sha" == "$SERVICE_SHA" ]]; then
    echo "M044_C01_STAGING_COMPARE FILE=$RUNTIME_FILE STATE=CURRENT TARGET_SHA=$target_sha RELEASE_SHA=$SERVICE_SHA"
  else
    die 'TARGET_SERVICE_UNEXPECTED_DRIFT'
  fi
}

create_backup() {
  local stamp target="$TARGET_ROOT/$SERVICE_REL"
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m044-c01-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  if [[ -e "$target" || -L "$target" ]]; then
    [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_SERVICE_INVALID'
    PREVIOUS_STATE='PRESENT'
    tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" "$SERVICE_REL"
  else
    PREVIOUS_STATE='ABSENT'
    tar -czf "$CODE_BACKUP" --files-from /dev/null
  fi
  chmod 0600 "$CODE_BACKUP"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP")
}

apply_code() {
  local target="$TARGET_ROOT/$SERVICE_REL" tmp="${TARGET_ROOT}/${SERVICE_REL}.m044-c01.$$"
  install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"
  install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$RUNTIME_FILE" "$tmp"
  mv -f "$tmp" "$target"
}

live_checks() {
  local target="$TARGET_ROOT/$SERVICE_REL" body status
  [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$SERVICE_SHA" ]] || return 1
  check_prerequisites
  docker exec "$TARGET_CONTAINER" php -l "/var/www/html/custom/$SERVICE_REL" >/dev/null || return 1
  docker exec -e M044_C01_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SUPPORT_TEST" >/dev/null || return 1
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
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_PREVIOUS_STATE=$PREVIOUS_STATE
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  CODE_BACKUP="$STATE_CODE_BACKUP"; CODE_BACKUP_SHA="$STATE_CODE_BACKUP_SHA"; PREVIOUS_STATE="$STATE_PREVIOUS_STATE"
}

command_compare() {
  check_environment; check_prerequisites; validate_artifact; candidate_checks; compare_file
  echo "M044_C01_STAGING_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA PRODUCTION_ACCESSED=NO"
}

command_preflight() {
  check_environment; check_prerequisites; validate_artifact; candidate_checks; compare_file
  echo "M044_C01_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; check_prerequisites; validate_artifact; candidate_checks; compare_file
  local target="$TARGET_ROOT/$SERVICE_REL"
  if [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$SERVICE_SHA" ]]; then
    live_checks || die 'NOOP_LIVE_CHECK_FAILED'
    echo "M044_C01_STAGING_APPLY=NOOP_ALREADY_APPLIED RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA PRODUCTION_ACCESSED=NO"
    return
  fi
  create_backup
  MUTATED=1
  apply_code
  live_checks || die 'LIVE_CHECK_FAILED'
  MUTATED=0
  save_state
  echo "M044_C01_STAGING_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PREVIOUS_STATE=$PREVIOUS_STATE DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_verify() {
  check_environment; check_prerequisites; validate_artifact; candidate_checks; load_state
  live_checks || die 'LIVE_VERIFY_FAILED'
  echo "M044_C01_STAGING_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; load_state
  local reapply="${BACKUP_ROOT}/m044-c01-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czpf "$reapply" -C "$TARGET_ROOT" "$SERVICE_REL"
  chmod 0600 "$reapply"
  restore_code || die 'CODE_RESTORE_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"
  echo "M044_C01_STAGING_ROLLBACK=PASS REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") PRODUCTION_ACCESSED=NO"
}

command_status() {
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
    grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|PREVIOUS_STATE|DEPLOYED_AT)=' "$STATE_FILE"
  else
    echo 'M044_C01_STAGING_STATUS=NOT_DEPLOYED'
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
