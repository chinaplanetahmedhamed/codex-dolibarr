#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M044-C04-R2'
readonly RUNTIME_COMMIT='99124ee517bb022b7220f4bafb90c34ca1e49069'
readonly SHORT_COMMIT='99124ee'
readonly ARTIFACT_SHA='516853c0fda282ef5b7f8e8cbe263cca285b196516b00f3ce768aac254435e61'
readonly RUNTIME_MANIFEST_SHA='00ce85c831fb14b33f391cebd79779c32b0559af17520cb64430b5ad34f256c9'
readonly SUPPORT_MANIFEST_SHA='105369eb9c2d98e950091e588bb5e6b0d01daef6d6a0905aed7585d22bb300c1'

readonly RUNTIME_MANIFEST='deploy/releases/m044-c04-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m044-c04-support-files.txt'
readonly -a RUNTIME_FILES=(
  'custom/chinaplanetshippingprices/admin/batch.php'
  'custom/chinaplanetshippingprices/admin/import.php'
  'custom/chinaplanetshippingprices/admin/setup.php'
  'custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php'
  'custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
  'custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php'
)
readonly -a RUNTIME_SHAS=(
  '95377602c5898c698fa6e23729c842c452336a28e632e9f0e234d264eccdaac9'
  'f0a0148fd560971273f132860ed5bf6a34b27bb235e147eb3eaf3e186b86c00e'
  '7b90f28d70e12e055ceec6f9c80f1da7852c0c773bf541b9e61ba41f87e12349'
  'bba37e89ef252937ef4d79e69a9cef6e01ed7573fcabe292fcd319f172b92a6d'
  'cfe9d925762add8741743f86be029cfa6a94f953c35bf1d72b3e441d2552f40a'
  '666a81699e8b764a6a32429c4654f2843745a1a0edf66bef3c4bdc6d0e0ac543'
)
readonly -a TARGET_FILES=(
  'chinaplanetshippingprices/admin/batch.php'
  'chinaplanetshippingprices/admin/import.php'
  'chinaplanetshippingprices/admin/setup.php'
  'chinaplanetshippingprices/class/ShippingNoteReviewService.class.php'
  'chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
  'chinaplanetshippingprices/class/ShippingPriceImporter.class.php'
)
readonly -a SUPPORT_FILES=(
  'deploy/tests/test_m041_shipping_note_translation.php'
  'deploy/tests/test_m041_shipping_note_translation_source.php'
  'deploy/tests/test_m044_c04_free_auto_translation.php'
)
readonly -a CANDIDATE_TESTS=(
  'deploy/tests/test_m041_shipping_note_translation.php'
  'deploy/tests/test_m044_c04_free_auto_translation.php'
)

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m044-c04-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m044-c04-staging'
readonly STATE_FILE="${STATE_ROOT}/m044-c04-r2.state"
readonly LOCK_FILE="${STATE_ROOT}/m044-c04.lock"

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
PRESTATE_FILE=''
PRESTATE_SHA=''
MUTATED=0

die() { echo "M044_C04_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup_paths() {
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m044-c04-candidate-* ]]; then
    docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
  fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m044-c04-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m044-c04-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
}

restore_prior() {
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" && $(sha256 "$PRESTATE_FILE") == "$PRESTATE_SHA" ]] || return 1
  while IFS='|' read -r rel state expected_sha owner group mode; do
    case "$rel" in
      chinaplanetshippingprices/admin/batch.php|chinaplanetshippingprices/admin/import.php|chinaplanetshippingprices/admin/setup.php|chinaplanetshippingprices/class/ShippingNoteReviewService.class.php|chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php|chinaplanetshippingprices/class/ShippingPriceImporter.class.php) ;;
      *) return 1 ;;
    esac
    local target="$TARGET_ROOT/$rel" tmp="${TARGET_ROOT}/${rel}.m044-c04-restore.$$"
    case "$state" in
      ABSENT)
        [[ "$target" == "$TARGET_ROOT/"* ]] || return 1
        rm -f "$target"
        ;;
      PRESENT)
        tar -xOf "$CODE_BACKUP" "$rel" >"$tmp" || return 1
        [[ $(sha256 "$tmp") == "$expected_sha" ]] || return 1
        chown "$owner:$group" "$tmp" || return 1
        chmod "$mode" "$tmp" || return 1
        mv -f "$tmp" "$target" || return 1
        ;;
      *) return 1 ;;
    esac
  done <"$PRESTATE_FILE"
}

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    if restore_prior; then echo 'M044_C04_STAGING_AUTO_ROLLBACK=PASS' >&2; else echo 'M044_C04_STAGING_AUTO_ROLLBACK=FAIL' >&2; fi
  fi
  cleanup_paths
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m044-c04-auto-translation' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

check_environment() {
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  [[ -d "$BACKUP_ROOT" && ! -L "$BACKUP_ROOT" && $(stat -c '%U:%G %a' "$BACKUP_ROOT") == 'root:root 700' ]] || die 'BACKUP_ROOT_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m044-c04-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 11 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m044-c04-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  printf '%s\n' "${RUNTIME_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" >/dev/null || die 'RUNTIME_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "${SUPPORT_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$SUPPORT_MANIFEST" >/dev/null || die 'SUPPORT_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  local index
  for index in "${!RUNTIME_FILES[@]}"; do
    [[ $(sha256 "$CANDIDATE_ROOT/${RUNTIME_FILES[$index]}") == "${RUNTIME_SHAS[$index]}" ]] || die 'RUNTIME_FILE_HASH_MISMATCH'
  done
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m044-c04-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/admin" "$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/class" "$CANDIDATE_REMOTE/deploy/tests"
  local rel
  for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do docker cp "$CANDIDATE_ROOT/$rel" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$rel"; done
  for rel in "${RUNTIME_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_RUNTIME_LINT_FAILED'; done
  for rel in "${CANDIDATE_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_CONTRACT_TEST_FAILED'; done
}

compare_files() {
  local index rel target_rel target target_sha state
  for index in "${!RUNTIME_FILES[@]}"; do
    rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"
    if [[ ! -e "$target" && ! -L "$target" ]]; then state='ABSENT'; target_sha='none'
    else
      [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
      target_sha=$(sha256 "$target")
      if [[ "$target_sha" == "${RUNTIME_SHAS[$index]}" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
    fi
    echo "M044_C04_STAGING_COMPARE FILE=$rel STATE=$state TARGET_SHA=$target_sha RELEASE_SHA=${RUNTIME_SHAS[$index]}"
  done
}

create_backup() {
  local stamp backup_list rel target
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m044-c04-r2-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  PRESTATE_FILE="${BACKUP_ROOT}/m044-c04-r2-before-${SHORT_COMMIT}-${stamp}.prestate"
  backup_list=$(mktemp)
  : >"$PRESTATE_FILE"
  for rel in "${TARGET_FILES[@]}"; do
    target="$TARGET_ROOT/$rel"
    if [[ -e "$target" || -L "$target" ]]; then
      [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
      printf '%s|PRESENT|%s|%s|%s|%s\n' "$rel" "$(sha256 "$target")" "$(stat -c '%U' "$target")" "$(stat -c '%G' "$target")" "$(stat -c '%a' "$target")" >>"$PRESTATE_FILE"
      printf '%s\n' "$rel" >>"$backup_list"
    else
      printf '%s|ABSENT|none|none|none|none\n' "$rel" >>"$PRESTATE_FILE"
    fi
  done
  tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" --files-from "$backup_list"
  rm -f "$backup_list"
  chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP")
  PRESTATE_SHA=$(sha256 "$PRESTATE_FILE")
  [[ -s "$CODE_BACKUP" && -s "$PRESTATE_FILE" ]] || die 'BACKUP_EMPTY'
}

apply_files() {
  local index rel target_rel target tmp
  for index in "${!RUNTIME_FILES[@]}"; do
    rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"; tmp="${TARGET_ROOT}/${target_rel}.m044-c04.$$"
    install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"
    install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp"
    [[ $(sha256 "$tmp") == "${RUNTIME_SHAS[$index]}" ]] || die 'TEMP_FILE_HASH_MISMATCH'
    mv -f "$tmp" "$target"
  done
}

live_checks() {
  local index rel target_rel target body status
  for index in "${!RUNTIME_FILES[@]}"; do
    rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"
    [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "${RUNTIME_SHAS[$index]}" ]] || return 1
    [[ $(stat -c '%U:%G %a' "$target") == 'www-data:www-data 644' ]] || return 1
  done
  for rel in "${RUNTIME_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; done
  for rel in "${CANDIDATE_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || return 1; done
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
  body=$(mktemp)
  status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/custom/chinaplanetshippingprices/admin/batch.php?id=21") || { rm -f "$body"; return 1; }
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
STATE_PRESTATE_FILE=$PRESTATE_FILE
STATE_PRESTATE_SHA=$PRESTATE_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; PRESTATE_FILE=$STATE_PRESTATE_FILE; PRESTATE_SHA=$STATE_PRESTATE_SHA
}

command_compare() {
  check_environment; validate_artifact; candidate_checks; compare_files
  echo "M044_C04_STAGING_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA PRODUCTION_ACCESSED=NO"
}

command_preflight() {
  check_environment; validate_artifact; candidate_checks; compare_files
  echo "M044_C04_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; candidate_checks
  if [[ -f "$STATE_FILE" ]]; then
    load_state
    if live_checks; then
      echo "M044_C04_STAGING_APPLY=NOOP_ALREADY_APPLIED RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA PRODUCTION_ACCESSED=NO"
      return
    fi
  fi
  compare_files
  create_backup
  MUTATED=1
  apply_files
  live_checks || die 'LIVE_CHECK_FAILED'
  MUTATED=0
  save_state
  echo "M044_C04_STAGING_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_verify() {
  check_environment; validate_artifact; candidate_checks; load_state
  live_checks || die 'LIVE_VERIFY_FAILED'
  echo "M044_C04_STAGING_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; validate_artifact; load_state
  local reapply="${BACKUP_ROOT}/m044-c04-r2-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czpf "$reapply" -C "$TARGET_ROOT" "${TARGET_FILES[@]}"
  chmod 0600 "$reapply"
  restore_prior || die 'CODE_RESTORE_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"
  echo "M044_C04_STAGING_ROLLBACK=PASS REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_status() {
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
    grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|PRESTATE_FILE|PRESTATE_SHA|DEPLOYED_AT)=' "$STATE_FILE"
  else
    echo 'M044_C04_STAGING_STATUS=NOT_DEPLOYED'
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
