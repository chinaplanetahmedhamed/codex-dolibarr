#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M062-R03'
readonly RUNTIME_COMMIT='dcd24ddd99ab5668c2ace3ccddd580abec24b472'
readonly SCOPE_COMMIT='4c566135dcd7b9a5c37112583ce43d9ca44e136e'
readonly SHORT_COMMIT='dcd24dd'
readonly ARTIFACT_SHA='c1bd85e9d3e2ddcae887c96600511495443d8d64bf54ec194da3081ef4e51e48'
readonly RUNTIME_MANIFEST_SHA='1d5ba1dd234503b83db0c474535c51abe4d9bed1760e6dd81353b673025df35b'
readonly SUPPORT_MANIFEST_SHA='37c0392bb3cbc5a182827e2b1f9b13ab835a72f38c9b1cabfe6d65ba7101ecf7'
readonly PRESTATE_MANIFEST_SHA='18e49710f954a8e80d56cf6526e12b8ea8039e8b399b6144ff5962187dd5858a'
readonly RUNTIME_MANIFEST='deploy/releases/m062-r03-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m062-r03-support-files.txt'
readonly PRESTATE_MANIFEST='deploy/releases/m062-r03-prestate.sha256'
readonly RUNTIME_FILE='custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php'
readonly TARGET_FILE='chinaplanetshippingprices/class/PublicShippingRateService.class.php'
readonly RUNTIME_SHA='189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4'
readonly BASELINE_SHA='ce821a27a26bbde6915f07ee4c37925a528db8d393b200ece15332b4b13b11de'
readonly SUPPORT_DEPENDENCY='chinaplanetshippingprices/class/ShippingPriceService.class.php'
readonly SUPPORT_DEPENDENCY_SHA='27cf3826310b1f4065b4bc5068b66f21cded32393a36ea1507e546c3d64e560e'
readonly TRANSLATION_DEPENDENCY='chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
readonly TRANSLATION_DEPENDENCY_SHA='1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54'
readonly -a SUPPORT_FILES=(
  'custom/chinaplanetshippingprices/class/ShippingPriceService.class.php'
  'custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
  'deploy/tests/test_m043_public_rate_token.php'
  'deploy/tests/test_m062_public_alexandria_group.php'
)
readonly -a SUPPORT_SHAS=(
  '27cf3826310b1f4065b4bc5068b66f21cded32393a36ea1507e546c3d64e560e'
  '1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54'
  '7891f46f74db33ece18edaeabde7062cdf661d13f33af5709e97d40530dac267'
  '4c2c6fa1037afc4c4acbc04ccd61d82c3cbc7681bd20ebddfafe36ffc3c6d2a3'
)

readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
TEST_MODE=0
if [[ ${M062_R03_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M062_R03_TEST_ROOT:-}
  case "$test_root" in /tmp/m062-r03-helper-test.*|/private/tmp/m062-r03-helper-test.*) ;; *) echo 'M062_R03_STAGING_FAIL=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" && $(basename "$0") == 'chinaplanetos-backup-erp-m062-ports.test' ]] || { echo 'M062_R03_STAGING_FAIL=TEST_MODE_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  INBOX="$test_root/inbox"; TARGET_ROOT="$test_root/target"; TARGET_HTTP='http://127.0.0.1:18088'; BACKUP_ROOT="$test_root/backups"; STATE_ROOT="$test_root/state"
else
  INBOX='/home/codex-deploy/staging-inbox'; TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'; TARGET_HTTP='http://127.0.0.1:18088'; BACKUP_ROOT='/root/backup-erp-code-backups'; STATE_ROOT='/var/lib/chinaplanetos-m062-public-search-staging-r03'
fi
readonly TEST_MODE INBOX TARGET_ROOT TARGET_HTTP BACKUP_ROOT STATE_ROOT
readonly ARTIFACT="${INBOX}/m062-public-search-${SHORT_COMMIT}.tar.gz"
readonly STATE_FILE="${STATE_ROOT}/m062-r03.state"
readonly LOCK_FILE="${STATE_ROOT}/m062-r03.lock"

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
PRESTATE_FILE=''
PRESTATE_SHA=''
MUTATED=0
MUTATION_KIND=''
REAPPLY_BACKUP=''
REAPPLY_SHA=''
PRIOR_STATE_PRESENT=0
PRIOR_STATE_BACKUP=''
PRIOR_STATE_SHA=''

die() { echo "M062_R03_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
fault() { [[ $TEST_MODE -eq 1 && ${M062_R03_FAULT:-} == "$1" ]]; }
fsync_path() {
  python3 - "$1" <<'PY'
import os, pathlib, sys
p = pathlib.Path(sys.argv[1])
flags = os.O_RDONLY | (getattr(os, 'O_DIRECTORY', 0) if p.is_dir() else 0)
fd = os.open(str(p), flags)
try: os.fsync(fd)
finally: os.close(fd)
PY
}

cleanup_paths() {
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m062-r03-candidate-* ]]; then
    docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
  fi
  if [[ $TEST_MODE -eq 1 ]]; then
    if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == "$test_root"/candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
    if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == "$test_root"/artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  else
    if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m062-r03-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
    if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m062-r03-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
  fi
}

restore_prior() {
  local target="$TARGET_ROOT/$TARGET_FILE" tmp="${TARGET_ROOT}/${TARGET_FILE}.m062-r03-restore.$$"
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" && $(sha256 "$PRESTATE_FILE") == "$PRESTATE_SHA" ]] || return 1
  local rel state expected_sha owner group mode extra
  IFS='|' read -r rel state expected_sha owner group mode extra <"$PRESTATE_FILE"
  [[ -z "${extra:-}" && "$rel" == "$TARGET_FILE" && "$state" == PRESENT && "$expected_sha" == "$BASELINE_SHA" ]] || return 1
  tar -xOf "$CODE_BACKUP" "$TARGET_FILE" >"$tmp" || return 1
  [[ $(sha256 "$tmp") == "$expected_sha" ]] || return 1
  if [[ $TEST_MODE -eq 0 ]]; then chown "$owner:$group" "$tmp" || return 1; fi
  chmod "$mode" "$tmp" || return 1
  mv -f "$tmp" "$target" || return 1
  fsync_path "$target" || return 1
  fsync_path "$(dirname "$target")" || return 1
  [[ $(sha256 "$target") == "$BASELINE_SHA" ]] || return 1
}

capture_prior_state() {
  PRIOR_STATE_PRESENT=0; PRIOR_STATE_BACKUP=''; PRIOR_STATE_SHA=''
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
    PRIOR_STATE_BACKUP=$(mktemp "${STATE_ROOT}/m062-r03-prior-state.XXXXXX")
    install -m 0600 "$STATE_FILE" "$PRIOR_STATE_BACKUP"
    fsync_path "$PRIOR_STATE_BACKUP"; fsync_path "$STATE_ROOT"
    PRIOR_STATE_SHA=$(sha256 "$PRIOR_STATE_BACKUP")
    PRIOR_STATE_PRESENT=1
  fi
}

restore_prior_state() {
  local tmp="${STATE_FILE}.recovery.$$"
  if [[ $PRIOR_STATE_PRESENT -eq 1 ]]; then
    [[ -f "$PRIOR_STATE_BACKUP" && ! -L "$PRIOR_STATE_BACKUP" && $(sha256 "$PRIOR_STATE_BACKUP") == "$PRIOR_STATE_SHA" ]] || return 1
    install -m 0600 "$PRIOR_STATE_BACKUP" "$tmp" || return 1
    fsync_path "$tmp" || return 1
    mv -f "$tmp" "$STATE_FILE" || return 1
    fsync_path "$STATE_FILE" || return 1
  else
    rm -f "$STATE_FILE" || return 1
  fi
  fsync_path "$STATE_ROOT" || return 1
  if [[ -n "$PRIOR_STATE_BACKUP" && -f "$PRIOR_STATE_BACKUP" ]]; then rm -f "$PRIOR_STATE_BACKUP" || return 1; fsync_path "$STATE_ROOT" || return 1; fi
}

ensure_deployed_state() {
  local tmp="${STATE_FILE}.recovery.$$"
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || return 1
  awk 'BEGIN{done=0} /^STATE_STATUS=/{print "STATE_STATUS=DEPLOYED"; done=1; next} {print} END{if(!done)exit 1}' "$STATE_FILE" >"$tmp" || return 1
  chmod 0600 "$tmp" || return 1
  fsync_path "$tmp" || return 1
  mv -f "$tmp" "$STATE_FILE" || return 1
  fsync_path "$STATE_FILE" || return 1
  fsync_path "$STATE_ROOT" || return 1
  grep -Fxq 'STATE_STATUS=DEPLOYED' "$STATE_FILE"
}

reapply_release() {
  local target="$TARGET_ROOT/$TARGET_FILE" tmp="${TARGET_ROOT}/${TARGET_FILE}.m062-r03-reapply.$$"
  [[ -f "$REAPPLY_BACKUP" && ! -L "$REAPPLY_BACKUP" && $(sha256 "$REAPPLY_BACKUP") == "$REAPPLY_SHA" ]] || return 1
  [[ $(tar -tzf "$REAPPLY_BACKUP") == "$TARGET_FILE" ]] || return 1
  tar -xOf "$REAPPLY_BACKUP" "$TARGET_FILE" >"$tmp" || return 1
  [[ $(sha256 "$tmp") == "$RUNTIME_SHA" ]] || return 1
  if [[ $TEST_MODE -eq 0 ]]; then chown www-data:www-data "$tmp" || return 1; fi
  chmod 0644 "$tmp" || return 1
  mv -f "$tmp" "$target" || return 1
  fsync_path "$target" || return 1
  fsync_path "$(dirname "$target")" || return 1
  [[ $(sha256 "$target") == "$RUNTIME_SHA" ]] || return 1
  docker exec "$TARGET_CONTAINER" php -l "/var/www/html/custom/$TARGET_FILE" >/dev/null || return 1
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
}

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    case "$MUTATION_KIND" in
      apply) if restore_prior && restore_prior_state; then echo 'M062_R03_STAGING_AUTO_ROLLBACK=PASS STATE_RECONCILED=YES' >&2; else echo 'M062_R03_STAGING_AUTO_ROLLBACK=FAIL RECOVERY_REQUIRED=YES' >&2; fi ;;
      rollback) if reapply_release && ensure_deployed_state; then echo 'M062_R03_STAGING_ROLLBACK_REAPPLY=PASS STATE_RECONCILED=YES' >&2; else echo 'M062_R03_STAGING_ROLLBACK_REAPPLY=FAIL RECOVERY_REQUIRED=YES' >&2; fi ;;
      *) echo 'M062_R03_STAGING_RECOVERY=FAIL MUTATION_KIND_INVALID' >&2 ;;
    esac
  fi
  cleanup_paths
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $TEST_MODE -eq 1 || $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  if [[ $TEST_MODE -eq 1 ]]; then [[ $(basename "$0") == 'chinaplanetos-backup-erp-m062-ports.test' ]] || die 'HELPER_PATH_NOT_ALLOWED'; else [[ $(basename "$0") == 'chinaplanetos-backup-erp-m062-ports' ]] || die 'HELPER_PATH_NOT_ALLOWED'; fi
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
  [[ -d "$INBOX" && ! -L "$INBOX" && -d "$BACKUP_ROOT" && ! -L "$BACKUP_ROOT" && -d "$STATE_ROOT" && ! -L "$STATE_ROOT" ]] || die 'RELEASE_ROOT_INVALID'
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
    [[ $(stat -c '%U:%G %a' "$BACKUP_ROOT") == 'root:root 700' ]] || die 'BACKUP_ROOT_INVALID'
    [[ $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
    [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge 1048576 ]] || die 'TARGET_DISK_LOW'
  fi
  local dependency="$TARGET_ROOT/$SUPPORT_DEPENDENCY"
  [[ -f "$dependency" && ! -L "$dependency" && $(sha256 "$dependency") == "$SUPPORT_DEPENDENCY_SHA" ]] || die 'SUPPORT_DEPENDENCY_DRIFT'
  local translation_dependency="$TARGET_ROOT/$TRANSLATION_DEPENDENCY"
  [[ -f "$translation_dependency" && ! -L "$translation_dependency" && $(sha256 "$translation_dependency") == "$TRANSLATION_DEPENDENCY_SHA" ]] || die 'TRANSLATION_DEPENDENCY_DRIFT'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
    ARTIFACT_COPY=$(mktemp /root/m062-r03-artifact.XXXXXX); install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  else
    ARTIFACT_COPY=$(mktemp "$test_root/artifact.XXXXXX"); install -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  fi
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual index
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 8 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  if [[ $TEST_MODE -eq 0 ]]; then CANDIDATE_ROOT=$(mktemp -d /root/m062-r03-candidate.XXXXXX); else CANDIDATE_ROOT=$(mktemp -d "$test_root/candidate.XXXXXX"); fi
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$PRESTATE_MANIFEST") == "$PRESTATE_MANIFEST_SHA" ]] || die 'PRESTATE_MANIFEST_HASH_MISMATCH'
  printf '%s\n' "$RUNTIME_FILE" | diff -u - "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" >/dev/null || die 'RUNTIME_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "${SUPPORT_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$SUPPORT_MANIFEST" >/dev/null || die 'SUPPORT_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "$RUNTIME_FILE" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" "$PRESTATE_MANIFEST" | LC_ALL=C sort >"$expected"
  (cd "$CANDIDATE_ROOT" && find . -type f -print | sed 's#^\./##' | LC_ALL=C sort) >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_FILE") == "$RUNTIME_SHA" ]] || die 'RUNTIME_FILE_HASH_MISMATCH'
  for index in "${!SUPPORT_FILES[@]}"; do [[ $(sha256 "$CANDIDATE_ROOT/${SUPPORT_FILES[$index]}") == "${SUPPORT_SHAS[$index]}" ]] || die 'SUPPORT_FILE_HASH_MISMATCH'; done
  grep -Fxq "$BASELINE_SHA  $RUNTIME_FILE" "$CANDIDATE_ROOT/$PRESTATE_MANIFEST" || die 'PRESTATE_SCOPE_INVALID'
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m062-r03-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/class" "$CANDIDATE_REMOTE/deploy/tests"
  local rel
  for rel in "$RUNTIME_FILE" "${SUPPORT_FILES[@]}"; do docker cp "$CANDIDATE_ROOT/$rel" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$rel"; done
  for rel in "$RUNTIME_FILE" "${SUPPORT_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_PHP_LINT_FAILED'; done
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m043_public_rate_token.php" | grep -Fq 'M043_RATE_TOKEN=PASS' || die 'CANDIDATE_TOKEN_TEST_FAILED'
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m062_public_alexandria_group.php" | grep -Fq 'M062_PUBLIC_ALEXANDRIA_GROUP=PASS' || die 'CANDIDATE_ALEXANDRIA_TEST_FAILED'
}

compare_file() {
  local target="$TARGET_ROOT/$TARGET_FILE" target_sha state
  [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
  target_sha=$(sha256 "$target")
  if [[ "$target_sha" == "$RUNTIME_SHA" ]]; then state='CURRENT'; elif [[ "$target_sha" == "$BASELINE_SHA" ]]; then state='UPDATE_REQUIRED'; else die 'TARGET_FILE_UNEXPECTED_DRIFT'; fi
  echo "M062_R03_STAGING_COMPARE FILE=$RUNTIME_FILE STATE=$state TARGET_SHA=$target_sha RELEASE_SHA=$RUNTIME_SHA"
}

create_backup() {
  local stamp target="$TARGET_ROOT/$TARGET_FILE"
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m062-r03-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  PRESTATE_FILE="${BACKUP_ROOT}/m062-r03-before-${SHORT_COMMIT}-${stamp}.prestate"
  if [[ $TEST_MODE -eq 0 ]]; then
    printf '%s|PRESENT|%s|%s|%s|%s\n' "$TARGET_FILE" "$(sha256 "$target")" "$(stat -c '%U' "$target")" "$(stat -c '%G' "$target")" "$(stat -c '%a' "$target")" >"$PRESTATE_FILE"
  else
    printf '%s|PRESENT|%s|root|root|644\n' "$TARGET_FILE" "$(sha256 "$target")" >"$PRESTATE_FILE"
  fi
  tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" "$TARGET_FILE"
  chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"
  fsync_path "$CODE_BACKUP"; fsync_path "$PRESTATE_FILE"; fsync_path "$BACKUP_ROOT"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); PRESTATE_SHA=$(sha256 "$PRESTATE_FILE")
  [[ -s "$CODE_BACKUP" && -s "$PRESTATE_FILE" ]] || die 'BACKUP_EMPTY'
}

apply_file() {
  local target="$TARGET_ROOT/$TARGET_FILE" tmp="${TARGET_ROOT}/${TARGET_FILE}.m062-r03.$$"
  if [[ $TEST_MODE -eq 0 ]]; then install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$RUNTIME_FILE" "$tmp"; else install -m 0644 "$CANDIDATE_ROOT/$RUNTIME_FILE" "$tmp"; fi
  [[ $(sha256 "$tmp") == "$RUNTIME_SHA" ]] || die 'TEMP_FILE_HASH_MISMATCH'
  mv -f "$tmp" "$target"
  fsync_path "$target"; fsync_path "$(dirname "$target")"
}

live_checks() {
  local target="$TARGET_ROOT/$TARGET_FILE"
  [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$RUNTIME_SHA" ]] || return 1
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(stat -c '%U:%G %a' "$target") == 'www-data:www-data 644' ]] || return 1; fi
  docker exec "$TARGET_CONTAINER" php -l "/var/www/html/custom/$TARGET_FILE" >/dev/null || return 1
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m043_public_rate_token.php" | grep -Fq 'M043_RATE_TOKEN=PASS' || return 1
  docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m062_public_alexandria_group.php" | grep -Fq 'M062_PUBLIC_ALEXANDRIA_GROUP=PASS' || return 1
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
}

save_state() {
  local tmp="${STATE_FILE}.tmp.$$"
  cat >"$tmp" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=DEPLOYED
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_SCOPE_COMMIT=$SCOPE_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_PRESTATE_FILE=$PRESTATE_FILE
STATE_PRESTATE_SHA=$PRESTATE_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$tmp"; fsync_path "$tmp"
  if fault APPLY_STATE_PUBLISH || fault APPLY_STATE_PRE_RENAME; then rm -f "$tmp"; return 1; fi
  mv -f "$tmp" "$STATE_FILE"
  fault APPLY_STATE_POST_RENAME && return 1
  fsync_path "$STATE_FILE"; fsync_path "$STATE_ROOT"
  grep -Fxq 'STATE_STATUS=DEPLOYED' "$STATE_FILE"
}

publish_rollback_state() {
  local tmp="${STATE_FILE}.tmp.$$"
  awk 'BEGIN{done=0} /^STATE_STATUS=/{print "STATE_STATUS=ROLLED_BACK"; done=1; next} {print} END{if(!done)exit 1}' "$STATE_FILE" >"$tmp"
  chmod 0600 "$tmp"; fsync_path "$tmp"
  if fault ROLLBACK_STATE_PUBLISH || fault ROLLBACK_STATE_PRE_RENAME; then rm -f "$tmp"; return 1; fi
  mv -f "$tmp" "$STATE_FILE"
  fault ROLLBACK_STATE_POST_RENAME && return 1
  fsync_path "$STATE_FILE"; fsync_path "$STATE_ROOT"
  grep -Fxq 'STATE_STATUS=ROLLED_BACK' "$STATE_FILE"
}

create_reapply_backup() {
  REAPPLY_BACKUP="${BACKUP_ROOT}/m062-r03-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czpf "$REAPPLY_BACKUP" -C "$TARGET_ROOT" "$TARGET_FILE"
  chmod 0600 "$REAPPLY_BACKUP"; fsync_path "$REAPPLY_BACKUP"; fsync_path "$BACKUP_ROOT"
  REAPPLY_SHA=$(sha256 "$REAPPLY_BACKUP")
  [[ -s "$REAPPLY_BACKUP" && $(tar -tzf "$REAPPLY_BACKUP") == "$TARGET_FILE" ]] || die 'REAPPLY_BACKUP_INVALID'
  [[ $(tar -xOf "$REAPPLY_BACKUP" "$TARGET_FILE" | sha256sum | awk '{print $1}') == "$RUNTIME_SHA" ]] || die 'REAPPLY_BACKUP_CONTENT_INVALID'
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_INVALID'
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_METADATA_INVALID'; fi
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_SCOPE_COMMIT" == "$SCOPE_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  [[ "$STATE_STATUS" == DEPLOYED || "$STATE_STATUS" == ROLLED_BACK ]] || die 'STATE_STATUS_INVALID'
  CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; PRESTATE_FILE=$STATE_PRESTATE_FILE; PRESTATE_SHA=$STATE_PRESTATE_SHA
}

command_compare() { check_environment; validate_artifact; candidate_checks; compare_file; echo "M062_R03_STAGING_COMPARE=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; compare_file; echo "M062_R03_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=1 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks
  if [[ -f "$STATE_FILE" ]]; then load_state; if [[ "$STATE_STATUS" == DEPLOYED ]] && live_checks; then echo "M062_R03_STAGING_APPLY=NOOP_ALREADY_APPLIED RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; return; fi; fi
  compare_file; [[ $(sha256 "$TARGET_ROOT/$TARGET_FILE") == "$BASELINE_SHA" ]] || die 'BASELINE_REQUIRED'
  capture_prior_state; create_backup; MUTATION_KIND='apply'; MUTATED=1; apply_file; live_checks || die 'LIVE_CHECK_FAILED'; save_state; MUTATED=0; MUTATION_KIND=''
  if [[ $PRIOR_STATE_PRESENT -eq 1 ]]; then rm -f "$PRIOR_STATE_BACKUP"; fsync_path "$STATE_ROOT"; fi
  echo "M062_R03_STAGING_APPLY=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA FILES=1 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_verify() { check_environment; validate_artifact; candidate_checks; load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die 'STATE_NOT_DEPLOYED'; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M062_R03_STAGING_VERIFY=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=1 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks; load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die 'STATE_NOT_DEPLOYED'
  create_reapply_backup; MUTATION_KIND='rollback'; MUTATED=1
  restore_prior || die 'CODE_RESTORE_FAILED'
  fault ROLLBACK_LINT && die 'ROLLBACK_LINT_FAILED'
  docker exec "$TARGET_CONTAINER" php -l "/var/www/html/custom/$TARGET_FILE" >/dev/null || die 'ROLLBACK_LINT_FAILED'
  fault ROLLBACK_HTTP && die 'ROLLBACK_HTTP_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  publish_rollback_state || die 'ROLLBACK_STATE_PUBLISH_FAILED'
  MUTATED=0; MUTATION_KIND=''
  echo "M062_R03_STAGING_ROLLBACK=PASS REAPPLY=$REAPPLY_BACKUP REAPPLY_SHA=$REAPPLY_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_status() { if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|SCOPE_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|PRESTATE_FILE|PRESTATE_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M062_R03_STAGING_STATUS=NOT_DEPLOYED'; fi; }

require_root "$@"
case "$1" in compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
