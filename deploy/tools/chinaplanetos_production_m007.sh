#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M007'
readonly COMMIT='d903a14'
readonly PROD_CONTAINER='vision_erp'
readonly PROD_VOLUME='vision-os_dolibarr_custom'
readonly PROD_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly STAGING_CONTAINER='erp-staging-dolibarr-1'
readonly STAGING_VOLUME='erp-staging_erp-staging_custom'
readonly STAGING_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m007-${COMMIT}.tar.gz"
readonly BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-production-deploy'
readonly STATE_FILE="${STATE_ROOT}/m007.state"
readonly LOCK_FILE="${STATE_ROOT}/m007.lock"

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
  'custom/chinaplanetshippingprices/admin/import.php'
)
readonly -a TEST_FILES=(
  'deploy/tests/test_m003_portal_hardening.php'
  'deploy/tests/test_m004_shipping_status_sequence.php'
  'deploy/tests/test_shipping_prices_upload_csrf.php'
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
  ['custom/chinaplanetshippingprices/admin/import.php']='47bd4b26a818110052262edef364597cf72191e1cc399f79fd99324ebbded745'
  ['deploy/tests/test_m003_portal_hardening.php']='a4936fc4340a9d02fcb86155c9ef0f63185b9a15bf611fc8069245f05f400077'
  ['deploy/tests/test_m004_shipping_status_sequence.php']='ae4b8dae785d892d3cc39defbcea8a3f7f473766bd61a0dca6dc7b9e0c208a09'
  ['deploy/tests/test_shipping_prices_upload_csrf.php']='2ee7d9c4e8f6a30d1385d5299684d5560651ebbe3b710895087ed3903291a900'
)

die() { echo "M007_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
require_root() { [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'; [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'; }

check_environment() {
  [[ -d "$PROD_ROOT" && ! -L "$PROD_ROOT" ]] || die 'PROD_ROOT_INVALID'
  [[ -d "$STAGING_ROOT" && ! -L "$STAGING_ROOT" ]] || die 'STAGING_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}' "$PROD_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PROD_CONTAINER_NOT_RUNNING'
  [[ $(docker inspect -f '{{.State.Running}}' "$STAGING_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_CONTAINER_NOT_RUNNING'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$PROD_CONTAINER") == "${PROD_VOLUME}|true" ]] || die 'PROD_MOUNT_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$STAGING_CONTAINER") == "${STAGING_VOLUME}|true" ]] || die 'STAGING_MOUNT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 https://erp.visiontrading.biz/) =~ ^(200|302)$ ]] || die 'PROD_HTTP_UNHEALTHY'
  install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
  local listing expected tmp file
  listing=$(mktemp); expected=$(mktemp)
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
    [[ $(sha256 "$tmp/$file") == "${EXPECTED_SHA[$file]}" ]] || die "HASH_MISMATCH_${file//\//_}"
  done
  CANDIDATE_ROOT="$tmp"; ARTIFACT_SHA=$(sha256 "$ARTIFACT")
  rm -f "$listing" "$expected"
}

candidate_checks() {
  local remote="/tmp/m007-candidate-$$" file
  docker exec "$PROD_CONTAINER" rm -rf "$remote"
  docker cp "$CANDIDATE_ROOT/." "$PROD_CONTAINER:$remote"
  for file in "${RUNTIME_FILES[@]}" "${TEST_FILES[@]}"; do docker exec "$PROD_CONTAINER" php -l "$remote/$file" >/dev/null || die 'CANDIDATE_LINT_FAILED'; done
  docker exec "$PROD_CONTAINER" php "$remote/deploy/tests/test_m003_portal_hardening.php" >/dev/null || die 'CANDIDATE_M003_FAILED'
  docker exec "$PROD_CONTAINER" php "$remote/deploy/tests/test_m004_shipping_status_sequence.php" >/dev/null || die 'CANDIDATE_M004_FAILED'
  docker exec "$PROD_CONTAINER" php "$remote/deploy/tests/test_shipping_prices_upload_csrf.php" >/dev/null || die 'CANDIDATE_M006_FAILED'
  docker exec "$PROD_CONTAINER" rm -rf "$remote"
}

compare_files() {
  local file rel release_hash staging_hash prod_hash state
  for file in "${RUNTIME_FILES[@]}"; do
    rel="${file#custom/}"; release_hash="${EXPECTED_SHA[$file]}"
    [[ -f "$STAGING_ROOT/$rel" && ! -L "$STAGING_ROOT/$rel" ]] || die "STAGING_FILE_INVALID_${file//\//_}"
    [[ -f "$PROD_ROOT/$rel" && ! -L "$PROD_ROOT/$rel" ]] || die "PROD_FILE_INVALID_${file//\//_}"
    staging_hash=$(sha256 "$STAGING_ROOT/$rel"); prod_hash=$(sha256 "$PROD_ROOT/$rel")
    [[ "$staging_hash" == "$release_hash" ]] || die "STAGING_RELEASE_MISMATCH_${file//\//_}"
    if [[ "$prod_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
    echo "M007_COMPARE FILE=$file STATE=$state PROD_SHA=$prod_hash RELEASE_SHA=$release_hash"
  done
}

live_checks() {
  local file remote="/tmp/m007-live-$$"
  for file in "${RUNTIME_FILES[@]}"; do
    docker exec "$PROD_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1
    [[ $(sha256 "$PROD_ROOT/${file#custom/}") == "${EXPECTED_SHA[$file]}" ]] || return 1
  done
  docker exec "$PROD_CONTAINER" rm -rf "$remote"; docker exec "$PROD_CONTAINER" mkdir -p "$remote/deploy/tests"
  docker exec "$PROD_CONTAINER" ln -s /var/www/html/custom "$remote/custom"
  for file in "${TEST_FILES[@]}"; do docker cp "$CANDIDATE_ROOT/$file" "$PROD_CONTAINER:$remote/deploy/tests/"; done
  docker exec "$PROD_CONTAINER" php "$remote/deploy/tests/test_m003_portal_hardening.php" >/dev/null || return 1
  docker exec "$PROD_CONTAINER" php "$remote/deploy/tests/test_m004_shipping_status_sequence.php" >/dev/null || return 1
  docker exec "$PROD_CONTAINER" php "$remote/deploy/tests/test_shipping_prices_upload_csrf.php" >/dev/null || return 1
  docker exec "$PROD_CONTAINER" rm -rf "$remote"
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 https://erp.visiontrading.biz/) =~ ^(200|302)$ ]]
}

create_backup() {
  local file rel
  for file in "${RUNTIME_FILES[@]}"; do rel="${file#custom/}"; [[ -f "$PROD_ROOT/$rel" && ! -L "$PROD_ROOT/$rel" ]] || die 'TARGET_INVALID'; done
  BACKUP="${BACKUP_ROOT}/m007-before-${COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czf "$BACKUP" -C "$PROD_ROOT" "${RUNTIME_FILES[@]#custom/}"
  chmod 0600 "$BACKUP"; BACKUP_SHA=$(sha256 "$BACKUP")
}

restore_backup() { [[ -f "$BACKUP" && $(sha256 "$BACKUP") == "$BACKUP_SHA" ]] || return 1; tar -xzf "$BACKUP" --same-owner --same-permissions -C "$PROD_ROOT"; }

apply_files() {
  local file src target uid gid mode tmp
  for file in "${RUNTIME_FILES[@]}"; do
    src="$CANDIDATE_ROOT/$file"; target="$PROD_ROOT/${file#custom/}"
    uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target"); tmp="${target}.m007.$$"
    install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"; mv -f "$tmp" "$target"
  done
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
load_state() { [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'; source "$STATE_FILE"; [[ "$STATE_COMMIT" == "$COMMIT" ]] || die 'STATE_COMMIT_MISMATCH'; BACKUP="$STATE_BACKUP"; BACKUP_SHA="$STATE_BACKUP_SHA"; }

command_compare() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M007_COMPARE=PASS ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; compare_files >/dev/null; echo "M007_PREFLIGHT=PASS ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; candidate_checks; compare_files >/dev/null; create_backup
  local mutated=0; trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_backup && echo M007_AUTO_ROLLBACK=PASS >&2 || echo M007_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
  mutated=1; apply_files; live_checks || false; mutated=0; trap - ERR; save_state
  echo "M007_APPLY=PASS ARTIFACT_SHA=$ARTIFACT_SHA BACKUP=$BACKUP BACKUP_SHA=$BACKUP_SHA"
}
command_verify() { check_environment; validate_artifact; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M007_VERIFY=PASS ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; load_state
  local reapply="${BACKUP_ROOT}/m007-reapply-${COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czf "$reapply" -C "$PROD_ROOT" "${RUNTIME_FILES[@]#custom/}"; chmod 0600 "$reapply"
  restore_backup || die 'ROLLBACK_RESTORE_FAILED'
  for file in "${RUNTIME_FILES[@]}"; do docker exec "$PROD_CONTAINER" php -l "/var/www/html/$file" >/dev/null || die 'ROLLBACK_LINT_FAILED'; done
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 https://erp.visiontrading.biz/) =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  echo "M007_ROLLBACK=PASS RESTORED=$BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}
command_status() { if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(COMMIT|ARTIFACT_SHA|BACKUP|BACKUP_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M007_STATUS=NOT_DEPLOYED'; fi; }

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
