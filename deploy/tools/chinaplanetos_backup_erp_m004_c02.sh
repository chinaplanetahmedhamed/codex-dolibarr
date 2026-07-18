#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly COMMIT='2c032eb59a4547b72429e07cf730f6e9963cc98e'
readonly CONTAINER='erp-staging-dolibarr-1'
readonly MAILPIT='erp-staging-mailpit-1'
readonly VOLUME='erp-staging_erp-staging_custom'
readonly CUSTOM_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m004-c02-${COMMIT}.tar.gz"
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-backup-erp-deploy'
readonly STATE_FILE="${STATE_ROOT}/m004-c02.state"
readonly LOCK_FILE="${STATE_ROOT}/m004-c02.lock"

readonly -a RUNTIME_FILES=(
  'custom/chinaplanetcustomercenter/admin/operations.php'
  'custom/chinaplanetcustomercenter/admin_dashboard.php'
  'custom/chinaplanetcustomercenter/board.php'
  'custom/chinaplanetcustomercenter/documents.php'
  'custom/chinaplanetcustomercenter/download.php'
  'custom/chinaplanetcustomercenter/lib/operations.lib.php'
  'custom/chinaplanetcustomercenter/lib/portal.lib.php'
  'custom/chinaplanetcustomercenter/project.php'
  'custom/chinaplanetlogistics/card.php'
  'custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php'
  'custom/chinaplanetlogistics/core/boxes/box_chinaplanetlogistics.php'
  'custom/chinaplanetlogistics/index.php'
  'custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php'
  'custom/chinaplanetlogistics/list.php'
  'custom/chinaplanetlogistics/public/track.php'
)
readonly -a TEST_FILES=(
  'deploy/tests/test_m003_portal_hardening.php'
  'deploy/tests/test_m004_shipping_status_sequence.php'
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "${TEST_FILES[@]}")

declare -Ar EXPECTED_SHA=(
  ['custom/chinaplanetcustomercenter/admin/operations.php']='0c2cc645316ce1d43a93be5257eee3b681efaf60173071fec314d92c542c4ffb'
  ['custom/chinaplanetcustomercenter/admin_dashboard.php']='8985c05c024cf95d043c8e80b2445968f699b126710feaea7a3f176bf820dcc6'
  ['custom/chinaplanetcustomercenter/board.php']='b8bb8eda5a0aaaf82119a8c2b5575bcf06f5bdde880b06c786b47952c576fcc7'
  ['custom/chinaplanetcustomercenter/documents.php']='055a7a308cf187db6c178bf19c084b7506165a00137081699ffce6be89ea719a'
  ['custom/chinaplanetcustomercenter/download.php']='97e71d640217cc84cb25376febebf91d9a5aa14b2db60294cc274d9363222fb1'
  ['custom/chinaplanetcustomercenter/lib/operations.lib.php']='74b84b63fef29e54c3b0eee97ebd1b6a3fd4a748a940931ee331755994a19034'
  ['custom/chinaplanetcustomercenter/lib/portal.lib.php']='e8b0a31fd4626595e765e31b7eb6d70f5b903dca13b2da46cf42142090858b7f'
  ['custom/chinaplanetcustomercenter/project.php']='eaf2b044eee82c4a724330fca80998d15b9e45b82253409fd628f3128c97b0ad'
  ['custom/chinaplanetlogistics/card.php']='ef52a9554a50497b25bf496f202dcd8210e74ea377ef6e866e412a3759fc9280'
  ['custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php']='a29a7833bd514742fbe2840732f18d2779f0ee0a3c16d8f610f39ed4e9fd947b'
  ['custom/chinaplanetlogistics/core/boxes/box_chinaplanetlogistics.php']='1543ff76b4af328f2469a61698d8aa027c077a81a4fbcf376fde9d39995198bb'
  ['custom/chinaplanetlogistics/index.php']='aec3689e9b050afbd58d724d4d580929c59b1f1895e8ce09bf305e49e2e9fc58'
  ['custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php']='e7e8019b30d756a901b0e54c82d238942ea177a46db2a7e2a47bfb755ab376ac'
  ['custom/chinaplanetlogistics/list.php']='e1a2f07bd104183bef5991f3b2310501b0d4de3457dfa9ce5e5435f512b0a3ba'
  ['custom/chinaplanetlogistics/public/track.php']='c64e737498bd988b01fa5d3ab3b3cc0e577d518fe9e15133a6d79b8dd335b90b'
  ['deploy/tests/test_m003_portal_hardening.php']='a4936fc4340a9d02fcb86155c9ef0f63185b9a15bf611fc8069245f05f400077'
  ['deploy/tests/test_m004_shipping_status_sequence.php']='ae4b8dae785d892d3cc39defbcea8a3f7f473766bd61a0dca6dc7b9e0c208a09'
)

die() { echo "M004_C02_FAIL=$1" >&2; exit 1; }
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
  [[ $(docker port "$MAILPIT" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:18088/) =~ ^(200|302)$ ]] || die 'LOCAL_HTTP_UNHEALTHY'
  install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
  local listing expected tmp file actual
  listing=$(mktemp)
  expected=$(mktemp)
  tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  printf '%s\n' "${ALL_FILES[@]}" | LC_ALL=C sort >"$expected"
  [[ $(wc -l <"$listing") -eq ${#ALL_FILES[@]} ]] || die 'ARTIFACT_ENTRY_COUNT'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  diff -u "$expected" <(LC_ALL=C sort "$listing") >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  tmp=$(mktemp -d)
  tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$tmp"
  for file in "${ALL_FILES[@]}"; do
    [[ -f "$tmp/$file" && ! -L "$tmp/$file" ]] || die 'ARTIFACT_FILE_INVALID'
    actual=$(sha256 "$tmp/$file")
    [[ "$actual" == "${EXPECTED_SHA[$file]}" ]] || die "HASH_MISMATCH_${file//\//_}"
  done
  CANDIDATE_ROOT="$tmp"
  ARTIFACT_SHA=$(sha256 "$ARTIFACT")
  rm -f "$listing" "$expected"
}

candidate_checks() {
  local remote="/tmp/m004-c02-candidate-$$" file
  docker exec "$CONTAINER" rm -rf "$remote"
  docker cp "$CANDIDATE_ROOT/." "$CONTAINER:$remote"
  for file in "${RUNTIME_FILES[@]}" "${TEST_FILES[@]}"; do
    docker exec "$CONTAINER" php -l "$remote/$file" >/dev/null || die 'CANDIDATE_LINT_FAILED'
  done
  docker exec "$CONTAINER" php "$remote/deploy/tests/test_m003_portal_hardening.php" >/dev/null || die 'CANDIDATE_M003_TEST_FAILED'
  docker exec "$CONTAINER" php "$remote/deploy/tests/test_m004_shipping_status_sequence.php" >/dev/null || die 'CANDIDATE_M004_TEST_FAILED'
  docker exec "$CONTAINER" rm -rf "$remote"
}

live_checks() {
  local file remote="/tmp/m004-c02-live-$$"
  for file in "${RUNTIME_FILES[@]}"; do
    docker exec "$CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1
    [[ $(sha256 "$CUSTOM_ROOT/${file#custom/}") == "${EXPECTED_SHA[$file]}" ]] || return 1
  done
  docker exec "$CONTAINER" rm -rf "$remote"
  docker exec "$CONTAINER" mkdir -p "$remote/deploy/tests"
  docker exec "$CONTAINER" ln -s /var/www/html/custom "$remote/custom"
  docker cp "$CANDIDATE_ROOT/deploy/tests/test_m003_portal_hardening.php" "$CONTAINER:$remote/deploy/tests/"
  docker cp "$CANDIDATE_ROOT/deploy/tests/test_m004_shipping_status_sequence.php" "$CONTAINER:$remote/deploy/tests/"
  docker exec "$CONTAINER" php "$remote/deploy/tests/test_m003_portal_hardening.php" >/dev/null || return 1
  docker exec "$CONTAINER" php "$remote/deploy/tests/test_m004_shipping_status_sequence.php" >/dev/null || return 1
  docker exec "$CONTAINER" rm -rf "$remote"
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:18088/) =~ ^(200|302)$ ]]
}

create_backup() {
  local stamp rel target
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  BACKUP="${BACKUP_ROOT}/m004-c02-before-${COMMIT:0:8}-${stamp}.tar.gz"
  for rel in "${RUNTIME_FILES[@]}"; do
    target="$CUSTOM_ROOT/${rel#custom/}"
    [[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${rel//\//_}"
  done
  tar -czf "$BACKUP" -C "$CUSTOM_ROOT" "${RUNTIME_FILES[@]#custom/}"
  chmod 0600 "$BACKUP"
  BACKUP_SHA=$(sha256 "$BACKUP")
  [[ -n "$BACKUP_SHA" ]] || die 'BACKUP_HASH_FAILED'
}

restore_backup() {
  [[ -f "$BACKUP" && $(sha256 "$BACKUP") == "$BACKUP_SHA" ]] || return 1
  tar -xzf "$BACKUP" --same-owner --same-permissions -C "$CUSTOM_ROOT"
}

apply_files() {
  local rel src target uid gid mode tmp
  for rel in "${RUNTIME_FILES[@]}"; do
    src="$CANDIDATE_ROOT/$rel"
    target="$CUSTOM_ROOT/${rel#custom/}"
    uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target")
    tmp="${target}.m004-c02.$$"
    install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"
    mv -f "$tmp" "$target"
  done
}

save_state() {
  cat >"$STATE_FILE" <<EOF
STATE_COMMIT=$COMMIT
STATE_ARTIFACT=$ARTIFACT
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
  BACKUP="$STATE_BACKUP"
  BACKUP_SHA="$STATE_BACKUP_SHA"
}

command_preflight() {
  check_environment
  if [[ -f "$ARTIFACT" ]]; then validate_artifact; candidate_checks; echo "M004_C02_PREFLIGHT=PASS ARTIFACT_SHA=$ARTIFACT_SHA"; else echo 'M004_C02_PREFLIGHT=PASS ARTIFACT=ABSENT'; fi
}

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; create_backup
  local mutated=0
  trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_backup && echo M004_C02_AUTO_ROLLBACK=PASS >&2 || echo M004_C02_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
  mutated=1
  apply_files
  live_checks || false
  mutated=0
  trap - ERR
  save_state
  echo "M004_C02_APPLY=PASS ARTIFACT_SHA=$ARTIFACT_SHA BACKUP=$BACKUP BACKUP_SHA=$BACKUP_SHA"
}

command_verify() {
  check_environment; validate_artifact; live_checks || die 'LIVE_VERIFY_FAILED'
  echo "M004_C02_VERIFY=PASS ARTIFACT_SHA=$ARTIFACT_SHA"
}

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; load_state
  local reapply="${BACKUP_ROOT}/m004-c02-reapply-${COMMIT:0:8}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czf "$reapply" -C "$CUSTOM_ROOT" "${RUNTIME_FILES[@]#custom/}"
  chmod 0600 "$reapply"
  restore_backup || die 'ROLLBACK_RESTORE_FAILED'
  for file in "${RUNTIME_FILES[@]}"; do docker exec "$CONTAINER" php -l "/var/www/html/$file" >/dev/null || die 'ROLLBACK_LINT_FAILED'; done
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 http://127.0.0.1:18088/) =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  echo "M004_C02_ROLLBACK=PASS RESTORED=$BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}

command_status() {
  if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(COMMIT|ARTIFACT_SHA|BACKUP|BACKUP_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M004_C02_STATUS=NOT_DEPLOYED'; fi
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
