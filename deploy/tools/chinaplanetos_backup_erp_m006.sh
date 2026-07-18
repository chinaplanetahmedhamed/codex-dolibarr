#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly COMMIT='9a1aa5d'
readonly CONTAINER='erp-staging-dolibarr-1'
readonly VOLUME='erp-staging_erp-staging_custom'
readonly CUSTOM_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m006-${COMMIT}.tar.gz"
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-backup-erp-deploy'
readonly STATE_FILE="${STATE_ROOT}/m006.state"
readonly LOCK_FILE="${STATE_ROOT}/m006.lock"
readonly RUNTIME_FILE='custom/chinaplanetshippingprices/admin/import.php'
readonly TEST_FILE='deploy/tests/test_shipping_prices_upload_csrf.php'
readonly RUNTIME_SHA='442f80fef69938ca744db87b7b74d26d3846db08eeb7637d283c16adf5524656'
readonly TEST_SHA='cc4193a398d8e1db6e2c88f286c34bf199c4f44f0c628ba51dceded5aa3ab540'

die() { echo "M006_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
}

check_environment() {
  [[ -d "$CUSTOM_ROOT" && ! -L "$CUSTOM_ROOT" ]] || die 'CUSTOM_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null) == 'true' ]] || die 'ERP_CONTAINER_NOT_RUNNING'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$CONTAINER") == "${VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker port "$CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:18088/) =~ ^(200|302)$ ]] || die 'LOCAL_HTTP_UNHEALTHY'
  install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
  local listing expected tmp
  listing=$(mktemp); expected=$(mktemp)
  tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  printf '%s\n' "$RUNTIME_FILE" "$TEST_FILE" | LC_ALL=C sort >"$expected"
  [[ $(wc -l <"$listing") -eq 2 ]] || die 'ARTIFACT_ENTRY_COUNT'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  diff -u "$expected" <(LC_ALL=C sort "$listing") >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  tmp=$(mktemp -d)
  tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$tmp"
  [[ -f "$tmp/$RUNTIME_FILE" && ! -L "$tmp/$RUNTIME_FILE" ]] || die 'RUNTIME_FILE_INVALID'
  [[ -f "$tmp/$TEST_FILE" && ! -L "$tmp/$TEST_FILE" ]] || die 'TEST_FILE_INVALID'
  [[ $(sha256 "$tmp/$RUNTIME_FILE") == "$RUNTIME_SHA" ]] || die 'RUNTIME_HASH_MISMATCH'
  [[ $(sha256 "$tmp/$TEST_FILE") == "$TEST_SHA" ]] || die 'TEST_HASH_MISMATCH'
  CANDIDATE_ROOT="$tmp"
  ARTIFACT_SHA=$(sha256 "$ARTIFACT")
  rm -f "$listing" "$expected"
}

candidate_checks() {
  local remote="/tmp/m006-candidate-$$"
  docker exec "$CONTAINER" rm -rf "$remote"
  docker cp "$CANDIDATE_ROOT/." "$CONTAINER:$remote"
  docker exec "$CONTAINER" php -l "$remote/$RUNTIME_FILE" >/dev/null || die 'CANDIDATE_LINT_FAILED'
  docker exec "$CONTAINER" php "$remote/$TEST_FILE" >/dev/null || die 'CANDIDATE_TEST_FAILED'
  docker exec "$CONTAINER" rm -rf "$remote"
}

live_checks() {
  local remote="/tmp/m006-live-$$"
  docker exec "$CONTAINER" php -l "/var/www/html/$RUNTIME_FILE" >/dev/null || return 1
  [[ $(sha256 "$CUSTOM_ROOT/${RUNTIME_FILE#custom/}") == "$RUNTIME_SHA" ]] || return 1
  docker exec "$CONTAINER" rm -rf "$remote"
  docker exec "$CONTAINER" mkdir -p "$remote/deploy/tests" "$remote/custom/chinaplanetshippingprices/admin"
  docker cp "$CUSTOM_ROOT/${RUNTIME_FILE#custom/}" "$CONTAINER:$remote/$RUNTIME_FILE"
  docker cp "$CANDIDATE_ROOT/$TEST_FILE" "$CONTAINER:$remote/$TEST_FILE"
  docker exec "$CONTAINER" php "$remote/$TEST_FILE" >/dev/null || return 1
  docker exec "$CONTAINER" rm -rf "$remote"
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:18088/) =~ ^(200|302)$ ]]
}

create_backup() {
  local target="$CUSTOM_ROOT/${RUNTIME_FILE#custom/}"
  [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_INVALID'
  BACKUP="${BACKUP_ROOT}/m006-before-${COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czf "$BACKUP" -C "$CUSTOM_ROOT" "${RUNTIME_FILE#custom/}"
  chmod 0600 "$BACKUP"
  BACKUP_SHA=$(sha256 "$BACKUP")
}

restore_backup() {
  [[ -f "$BACKUP" && $(sha256 "$BACKUP") == "$BACKUP_SHA" ]] || return 1
  tar -xzf "$BACKUP" --same-owner --same-permissions -C "$CUSTOM_ROOT"
}

apply_file() {
  local src="$CANDIDATE_ROOT/$RUNTIME_FILE" target="$CUSTOM_ROOT/${RUNTIME_FILE#custom/}" uid gid mode tmp
  uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target")
  tmp="${target}.m006.$$"
  install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"
  mv -f "$tmp" "$target"
}

save_state() {
  cat >"$STATE_FILE" <<EOF
STATE_COMMIT=$COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_BACKUP=$BACKUP
STATE_BACKUP_SHA=$BACKUP_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
  # shellcheck disable=SC1090
  source "$STATE_FILE"
  [[ "$STATE_COMMIT" == "$COMMIT" ]] || die 'STATE_COMMIT_MISMATCH'
  BACKUP="$STATE_BACKUP"; BACKUP_SHA="$STATE_BACKUP_SHA"
}

command_preflight() {
  check_environment
  if [[ -f "$ARTIFACT" ]]; then validate_artifact; candidate_checks; echo "M006_PREFLIGHT=PASS ARTIFACT_SHA=$ARTIFACT_SHA"; else echo 'M006_PREFLIGHT=PASS ARTIFACT=ABSENT'; fi
}

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; create_backup
  local mutated=0
  trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_backup && echo M006_AUTO_ROLLBACK=PASS >&2 || echo M006_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
  mutated=1; apply_file; live_checks || false; mutated=0; trap - ERR
  save_state
  echo "M006_APPLY=PASS ARTIFACT_SHA=$ARTIFACT_SHA BACKUP=$BACKUP BACKUP_SHA=$BACKUP_SHA"
}

command_verify() {
  check_environment; validate_artifact; live_checks || die 'LIVE_VERIFY_FAILED'
  echo "M006_VERIFY=PASS ARTIFACT_SHA=$ARTIFACT_SHA"
}

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; load_state
  local reapply="${BACKUP_ROOT}/m006-reapply-${COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czf "$reapply" -C "$CUSTOM_ROOT" "${RUNTIME_FILE#custom/}"; chmod 0600 "$reapply"
  restore_backup || die 'ROLLBACK_RESTORE_FAILED'
  docker exec "$CONTAINER" php -l "/var/www/html/$RUNTIME_FILE" >/dev/null || die 'ROLLBACK_LINT_FAILED'
  echo "M006_ROLLBACK=PASS RESTORED=$BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}

command_status() {
  if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(COMMIT|ARTIFACT_SHA|BACKUP|BACKUP_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M006_STATUS=NOT_DEPLOYED'; fi
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
