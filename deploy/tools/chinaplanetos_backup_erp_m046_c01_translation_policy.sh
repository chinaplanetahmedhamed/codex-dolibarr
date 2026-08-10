#!/usr/bin/env bash
set -Eeuo pipefail
CALLER_PATH=${PATH:-}
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M046-C01-R1'
readonly RUNTIME_COMMIT='15c19dda5842be992e6926db473878042a0a96d5'
readonly SHORT_COMMIT='15c19dd'
readonly ARTIFACT_SHA='79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596'
readonly ARTIFACT_MEMBERS='27'
readonly RUNTIME_MANIFEST_SHA='69d754158a34b54dbf3186a2ba69c7a9a2c958a5f43b3e0605462cc470c7f798'
readonly SUPPORT_MANIFEST_SHA='6c64b2f9441a1a3fdbb8950f295209f973c34c7e788d05b7303a5c5c7e4ec47b'
readonly RUNTIME_MANIFEST='deploy/releases/m046-c01-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m046-c01-support-files.txt'
readonly ARTIFACT_NAME="m046-c01-${SHORT_COMMIT}.tar.gz"

readonly -a RUNTIME_FILES=(
  'custom/chinaplanetshippingprices/admin/batch.php'
  'custom/chinaplanetshippingprices/admin/setup.php'
  'custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php'
  'custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
  'custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php'
  'custom/chinaplanetshippingprices/class/ShippingPriceService.class.php'
)
readonly -a RUNTIME_SHAS=(
  '715ee7c202bb9c4218941cfa34c752781690c06bc0b0fd6808c8c5e0fcbc58a3'
  '4727655d482f779e6e8a926f3d489d3ab3d4564e873caf4b56b874accf114cf9'
  '7fc8db52becbc90a2f40b014bc90b0f346d9832ee0f1a2e26dd6d3acf40adcba'
  '1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54'
  'e86e4df675318ab8f7a9a10baf885247ff006534e294965263fab1570071f684'
  '27cf3826310b1f4065b4bc5068b66f21cded32393a36ea1507e546c3d64e560e'
)
readonly -a TARGET_FILES=(
  'chinaplanetshippingprices/admin/batch.php'
  'chinaplanetshippingprices/admin/setup.php'
  'chinaplanetshippingprices/class/ShippingNoteReviewService.class.php'
  'chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php'
  'chinaplanetshippingprices/class/ShippingPriceImporter.class.php'
  'chinaplanetshippingprices/class/ShippingPriceService.class.php'
)
readonly -a SUPPORT_FILES=(
  'custom/chinaplanetshippingprices/admin/import.php'
  'custom/chinaplanetshippingprices/api/class/api_chinaplanetshippingprices.class.php'
  'custom/chinaplanetshippingprices/css/shippingprices.css'
  'custom/chinaplanetshippingprices/customer_booking.php'
  'custom/chinaplanetshippingprices/lib/main.lib.php'
  'custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql'
  'deploy/tests/check_m046_c01_runtime_policy.php'
  'deploy/tests/test_m034_shipping_price_publication_state.php'
  'deploy/tests/test_m041_shipping_note_translation.php'
  'deploy/tests/test_m041_shipping_note_translation_runtime.php'
  'deploy/tests/test_m041_shipping_note_translation_source.php'
  'deploy/tests/test_m041_translation_provider.php'
  'deploy/tests/test_m044_c03_note_review_ui.php'
  'deploy/tests/test_m044_c04_free_auto_translation.php'
  'deploy/tests/test_m044_c04_free_provider.php'
  'deploy/tests/test_m046_c01_call_sites.php'
  'deploy/tests/test_m046_c01_setup_route.php'
  'deploy/tests/test_m046_c01_translation_policy.php'
  'deploy/tests/test_shipping_prices_upload_csrf.php'
)
readonly -a CANDIDATE_TESTS=(
  'deploy/tests/test_m034_shipping_price_publication_state.php'
  'deploy/tests/test_m041_shipping_note_translation.php'
  'deploy/tests/test_m041_shipping_note_translation_runtime.php'
  'deploy/tests/test_m041_shipping_note_translation_source.php'
  'deploy/tests/test_m041_translation_provider.php'
  'deploy/tests/test_m044_c03_note_review_ui.php'
  'deploy/tests/test_m044_c04_free_auto_translation.php'
  'deploy/tests/test_m044_c04_free_provider.php'
  'deploy/tests/test_m046_c01_call_sites.php'
  'deploy/tests/test_m046_c01_setup_route.php'
  'deploy/tests/test_m046_c01_translation_policy.php'
  'deploy/tests/test_shipping_prices_upload_csrf.php'
)

TEST_MODE=0
if [[ ${M046_C01_HELPER_TEST_MODE:-0} == 1 ]]; then
  TEST_MODE=1
  test_root=${M046_C01_HELPER_TEST_ROOT:-}
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m046-c01-translation-policy.test' ]] || { echo 'M046_C01_STAGING_FAIL=TEST_HELPER_NAME_INVALID' >&2; exit 1; }
  case "$test_root" in /tmp/m046-c01-helper-test.*|/private/tmp/m046-c01-helper-test.*) ;; *) echo 'M046_C01_STAGING_FAIL=TEST_ROOT_INVALID' >&2; exit 1 ;; esac
  [[ -d "$test_root" && ! -L "$test_root" ]] || { echo 'M046_C01_STAGING_FAIL=TEST_ROOT_INVALID' >&2; exit 1; }
  PATH="$test_root/bin:$CALLER_PATH:$PATH"
  INBOX="$test_root/inbox"
  TARGET_ROOT="$test_root/target"
  BACKUP_ROOT="$test_root/backups"
  STATE_ROOT="$test_root/state"
else
  INBOX='/home/codex-deploy/staging-inbox'
  TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
  BACKUP_ROOT='/root/backup-erp-code-backups'
  STATE_ROOT='/var/lib/chinaplanetos-m046-c01-staging'
fi
readonly TEST_MODE INBOX TARGET_ROOT BACKUP_ROOT STATE_ROOT
readonly ARTIFACT="${INBOX}/${ARTIFACT_NAME}"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly STATE_FILE="${STATE_ROOT}/m046-c01-r1.state"
readonly LOCK_FILE="${STATE_ROOT}/m046-c01.lock"
readonly REQUEST_ROOT="${STATE_ROOT}/requests"

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
EXPECTED_FILE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
PRESTATE_FILE=''
PRESTATE_SHA=''
CONFIG_ROW_SHA=''
MUTATED=0
REQUEST_ID=''
REQUEST_FILE=''
REQUEST_ACTION=''
RESTORE_CONTEXT='AUTO'
TEMP_TARGETS=('')
BACKUP_LIST=''
REAPPLY_LIST=''
REAPPLY_ROOT=''

die() { echo "M046_C01_STAGING_FAIL=$1" >&2; exit 1; }
sha256() {
  if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}';
  else shasum -a 256 "$1" | awk '{print $1}'; fi
}
mode_of() {
  if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Lp' "$1"; else stat -c '%a' "$1"; fi
}
owner_group_of() {
  if [[ $TEST_MODE -eq 1 ]]; then stat -f '%Su:%Sg' "$1"; else stat -c '%U:%G' "$1"; fi
}
fsync_path() {
  python3 - "$1" <<'PY'
import os, pathlib, sys
path = pathlib.Path(sys.argv[1])
flags = os.O_RDONLY | (getattr(os, 'O_DIRECTORY', 0) if path.is_dir() else 0)
fd = os.open(str(path), flags)
try:
    os.fsync(fd)
finally:
    os.close(fd)
PY
}
durable_move() {
  local source=$1 target=$2
  fsync_path "$source" || return 1
  mv -f "$source" "$target" || return 1
  fsync_path "$(dirname "$target")" || return 1
}
check_disk_space() {
  [[ $TEST_MODE -eq 1 && ${M046_C01_TEST_DISK_LOW:-0} == 1 ]] && die 'DISK_SPACE_INSUFFICIENT'
  local path available
  for path in "$TARGET_ROOT" "$BACKUP_ROOT" "$STATE_ROOT"; do
    available=$(df -Pk "$path" | awk 'END {print $4}')
    [[ "$available" =~ ^[0-9]+$ && $available -ge 51200 ]] || die 'DISK_SPACE_INSUFFICIENT'
  done
}

cleanup_paths() {
  if [[ $TEST_MODE -eq 0 && -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m046-c01-candidate-* ]]; then
    docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
  fi
  if [[ -n "$CANDIDATE_ROOT" ]]; then
    case "$CANDIDATE_ROOT" in /root/m046-c01-candidate.*|/tmp/m046-c01-helper-test.*/state/candidate.*|/private/tmp/m046-c01-helper-test.*/state/candidate.*) [[ -d "$CANDIDATE_ROOT" ]] && rm -rf "$CANDIDATE_ROOT" ;; esac
  fi
  if [[ -n "$ARTIFACT_COPY" ]]; then
    case "$ARTIFACT_COPY" in /root/m046-c01-artifact.*|/tmp/m046-c01-helper-test.*/state/artifact.*|/private/tmp/m046-c01-helper-test.*/state/artifact.*) [[ -f "$ARTIFACT_COPY" ]] && rm -f "$ARTIFACT_COPY" ;; esac
  fi
  if [[ -n "$EXPECTED_FILE" ]]; then
    case "$EXPECTED_FILE" in /root/m046-c01-candidate.*.expected|/tmp/m046-c01-helper-test.*/state/candidate.*.expected|/private/tmp/m046-c01-helper-test.*/state/candidate.*.expected) [[ -f "$EXPECTED_FILE" ]] && rm -f "$EXPECTED_FILE" ;; esac
  fi
  for temporary in "$BACKUP_LIST" "$REAPPLY_LIST"; do
    [[ -n "$temporary" ]] || continue
    case "$temporary" in /root/m046-c01-*.list.*|/tmp/m046-c01-helper-test.*/state/*.list.*|/private/tmp/m046-c01-helper-test.*/state/*.list.*) [[ -f "$temporary" ]] && rm -f "$temporary" ;; esac
  done
  if [[ -n "$REAPPLY_ROOT" ]]; then
    case "$REAPPLY_ROOT" in /root/m046-c01-reapply.*|/tmp/m046-c01-helper-test.*/state/reapply.*|/private/tmp/m046-c01-helper-test.*/state/reapply.*) [[ -d "$REAPPLY_ROOT" ]] && rm -rf "$REAPPLY_ROOT" ;; esac
  fi
  local temporary
  for temporary in "${TEMP_TARGETS[@]}"; do
    [[ -n "$temporary" ]] || continue
    case "$temporary" in "$TARGET_ROOT"/*.m046-c01.*|"$TARGET_ROOT"/*.m046-c01-restore.*) [[ -f "$temporary" ]] && rm -f "$temporary" ;; esac
  done
  return 0
}

release_file_current() {
  local index=$1 target="$TARGET_ROOT/${TARGET_FILES[$1]}" expected_owner_group
  [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "${RUNTIME_SHAS[$index]}" && $(mode_of "$target") == 644 ]] || return 1
  if [[ $TEST_MODE -eq 1 ]]; then expected_owner_group=$(owner_group_of "$TARGET_ROOT"); else expected_owner_group='www-data:www-data'; fi
  [[ $(owner_group_of "$target") == "$expected_owner_group" ]]
}

restore_prior() {
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" && $(sha256 "$PRESTATE_FILE") == "$PRESTATE_SHA" ]] || return 1
  local count=0 rel state expected_sha owner group mode target tmp
  while IFS='|' read -r rel state expected_sha owner group mode; do
    case "$rel" in
      chinaplanetshippingprices/admin/batch.php|chinaplanetshippingprices/admin/setup.php|chinaplanetshippingprices/class/ShippingNoteReviewService.class.php|chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php|chinaplanetshippingprices/class/ShippingPriceImporter.class.php|chinaplanetshippingprices/class/ShippingPriceService.class.php) ;;
      *) return 1 ;;
    esac
    target="$TARGET_ROOT/$rel"; tmp="${target}.m046-c01-restore.$$"; TEMP_TARGETS+=("$tmp")
    case "$state" in
      ABSENT) rm -f "$target"; fsync_path "$(dirname "$target")" || return 1 ;;
      PRESENT)
        tar -xOf "$CODE_BACKUP" "$rel" >"$tmp" || return 1
        [[ $(sha256 "$tmp") == "$expected_sha" ]] || return 1
        if [[ $TEST_MODE -eq 0 ]]; then chown "$owner:$group" "$tmp" || return 1; fi
        chmod "$mode" "$tmp" || return 1
        durable_move "$tmp" "$target" || return 1
        ;;
      *) return 1 ;;
    esac
    count=$((count + 1))
    if [[ $TEST_MODE -eq 1 && "$RESTORE_CONTEXT" == 'ROLLBACK' && ${M046_C01_TEST_FAIL_RESTORE_AFTER_FIRST:-0} == 1 && $count -eq 1 ]]; then return 1; fi
  done <"$PRESTATE_FILE"
}

cleanup() {
  local rc=$?
  local terminal_status='' restore_result='NO_MUTATION' action_upper='UNKNOWN'
  trap - EXIT
  if [[ $MUTATED -eq 1 ]]; then
    RESTORE_CONTEXT='AUTO'
    if restore_prior && prestate_matches && basic_http_check; then restore_result='AUTO_RESTORED'; echo 'M046_C01_STAGING_AUTO_ROLLBACK=PASS' >&2; else restore_result='AUTO_RESTORE_FAILED'; echo 'M046_C01_STAGING_AUTO_ROLLBACK=FAIL' >&2; fi
  fi
  if [[ -n "$REQUEST_FILE" && -f "$REQUEST_FILE" ]] && grep -Fxq 'REQUEST_STATUS=PENDING' "$REQUEST_FILE"; then
    action_upper=$(printf '%s' "$REQUEST_ACTION" | tr '[:lower:]' '[:upper:]')
    terminal_status="${action_upper}_FAILED_${restore_result}"
    if [[ "$REQUEST_ACTION" == apply && -n "$CODE_BACKUP" && -n "$PRESTATE_FILE" && -n "$CONFIG_ROW_SHA" ]]; then save_state "$terminal_status" || true; fi
    finish_request "$terminal_status" || true
  fi
  cleanup_paths
  exit "$rc"
}
trap cleanup EXIT

request_epoch() {
  local stamp=$1
  date -u -d "$stamp" '+%s' 2>/dev/null || date -j -u -f '%Y%m%dT%H%M%SZ' "$stamp" '+%s' 2>/dev/null
}
validate_request_id() {
  REQUEST_ID=$1
  [[ $REQUEST_ID =~ ^m046-c01-([0-9]{8}T[0-9]{6}Z)-[a-f0-9]{12}$ ]] || die 'REQUEST_ID_INVALID'
  local issued now delta
  issued=$(request_epoch "${BASH_REMATCH[1]}") || die 'REQUEST_ID_TIME_INVALID'
  now=$(date -u '+%s'); delta=$((now - issued))
  [[ $delta -ge -300 && $delta -le 1800 ]] || die 'REQUEST_ID_STALE'
  REQUEST_FILE="${REQUEST_ROOT}/${REQUEST_ID}.state"
}
begin_request() {
  [[ ! -e "$REQUEST_FILE" && ! -L "$REQUEST_FILE" ]] || die 'REQUEST_ID_REPLAYED'
  (set -o noclobber; printf 'REQUEST_ID=%s\nREQUEST_STATUS=PENDING\n' "$REQUEST_ID" >"$REQUEST_FILE") 2>/dev/null || die 'REQUEST_ID_REPLAYED'
  chmod 0600 "$REQUEST_FILE"; fsync_path "$REQUEST_FILE"; fsync_path "$REQUEST_ROOT"
}
finish_request() {
  local status=$1 tmp="${REQUEST_FILE}.tmp.$$"
  grep -Fxq 'REQUEST_STATUS=PENDING' "$REQUEST_FILE" || return 1
  printf 'REQUEST_ID=%s\nREQUEST_STATUS=%s\nREQUEST_FINISHED_AT=%s\n' "$REQUEST_ID" "$status" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >"$tmp"
  chmod 0600 "$tmp"; durable_move "$tmp" "$REQUEST_FILE"
}

require_invocation() {
  REQUEST_ACTION=${1:-UNKNOWN}
  if [[ $TEST_MODE -eq 0 ]]; then
    [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
    [[ $(basename "$0") == 'chinaplanetos-backup-erp-m046-c01-translation-policy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
  fi
  case "${1:-}" in
    compare|preflight|verify|status) [[ $# -eq 1 ]] || die 'ARGUMENT_COUNT_INVALID' ;;
    apply|rollback) [[ $# -eq 2 ]] || die 'ARGUMENT_COUNT_INVALID'; validate_request_id "$2" ;;
    *) die 'COMMAND_NOT_ALLOWED' ;;
  esac
}

check_environment() {
  if [[ $TEST_MODE -eq 1 ]]; then
    for path in "$INBOX" "$TARGET_ROOT" "$BACKUP_ROOT" "$STATE_ROOT" "$REQUEST_ROOT"; do [[ -d "$path" && ! -L "$path" ]] || die 'TEST_ENVIRONMENT_INVALID'; done
    check_disk_space
    return
  fi
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  [[ -d "$BACKUP_ROOT" && ! -L "$BACKUP_ROOT" && $(stat -c '%U:%G %a' "$BACKUP_ROOT") == 'root:root 700' ]] || die 'BACKUP_ROOT_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
  [[ -d "$REQUEST_ROOT" && ! -L "$REQUEST_ROOT" && $(stat -c '%U:%G %a' "$REQUEST_ROOT") == 'root:root 700' ]] || die 'REQUEST_ROOT_INVALID'
  check_disk_space
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  if [[ $TEST_MODE -eq 0 ]]; then [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'; fi
  if [[ $TEST_MODE -eq 1 ]]; then ARTIFACT_COPY=$(mktemp "$STATE_ROOT/artifact.XXXXXX"); CANDIDATE_ROOT=$(mktemp -d "$STATE_ROOT/candidate.XXXXXX");
  else ARTIFACT_COPY=$(mktemp /root/m046-c01-artifact.XXXXXX); CANDIDATE_ROOT=$(mktemp -d /root/m046-c01-candidate.XXXXXX); fi
  install -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  EXPECTED_FILE="$CANDIDATE_ROOT.expected"
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$EXPECTED_FILE"
  if [[ $TEST_MODE -eq 1 && ${M046_C01_TEST_FAIL_STRUCTURE:-0} == 1 ]]; then die 'TEST_ARTIFACT_STRUCTURE_FAILURE'; fi
  python3 - "$ARTIFACT_COPY" "$EXPECTED_FILE" "$ARTIFACT_MEMBERS" <<'PY' || die 'ARTIFACT_STRUCTURE_INVALID'
import gzip, pathlib, re, sys, tarfile
archive, expected_path, expected_count = sys.argv[1], sys.argv[2], int(sys.argv[3])
header = pathlib.Path(archive).read_bytes()[:10]
assert header == bytes([31,139,8,0,0,0,0,0,0,3])
expected = pathlib.Path(expected_path).read_text().splitlines()
assert len(expected) == expected_count == len(set(expected))
for name in expected:
    assert name and not name.startswith('/') and '..' not in pathlib.PurePosixPath(name).parts
    assert '/._' not in name and '.DS_Store' not in name and not name.endswith(('.env','.pem','.key'))
with tarfile.open(archive, 'r:gz') as bundle:
    members = bundle.getmembers()
    assert bundle.pax_headers == {}
    assert [m.name for m in members] == expected
    for member in members:
        assert member.isfile() and not member.issym() and not member.islnk()
        assert member.pax_headers == {} and member.mode == 0o644
        assert (member.uid,member.gid,member.uname,member.gname) == (0,0,'root','root')
        assert member.mtime == 315532800
        data = bundle.extractfile(member).read()
        assert b'BEGIN PRIVATE KEY' not in data and b'BEGIN OPENSSH PRIVATE KEY' not in data
        assert re.search(rb'AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{36}|sk-proj-[A-Za-z0-9_-]{20,}', data) is None
PY
  rm -f "$EXPECTED_FILE"; EXPECTED_FILE=''
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  printf '%s\n' "${RUNTIME_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" >/dev/null || die 'RUNTIME_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "${SUPPORT_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$SUPPORT_MANIFEST" >/dev/null || die 'SUPPORT_MANIFEST_SCOPE_INVALID'
  local index
  for index in "${!RUNTIME_FILES[@]}"; do [[ $(sha256 "$CANDIDATE_ROOT/${RUNTIME_FILES[$index]}") == "${RUNTIME_SHAS[$index]}" ]] || die 'RUNTIME_FILE_HASH_MISMATCH'; done
}

candidate_checks() {
  local rel
  if [[ $TEST_MODE -eq 1 ]]; then
    for rel in "${RUNTIME_FILES[@]}"; do php -l "$CANDIDATE_ROOT/$rel" >/dev/null || die 'CANDIDATE_RUNTIME_LINT_FAILED'; done
    for rel in "${CANDIDATE_TESTS[@]}"; do php "$CANDIDATE_ROOT/$rel" >/dev/null || die 'CANDIDATE_CONTRACT_TEST_FAILED'; done
    return
  fi
  CANDIDATE_REMOTE="/tmp/m046-c01-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
  docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE/"
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
      if release_file_current "$index"; then state='CURRENT'; elif [[ "$target_sha" == "${RUNTIME_SHAS[$index]}" ]]; then state='METADATA_MISMATCH'; else state='UPDATE_REQUIRED'; fi
    fi
    echo "M046_C01_STAGING_COMPARE FILE=$rel STATE=$state TARGET_SHA=$target_sha RELEASE_SHA=${RUNTIME_SHAS[$index]}"
  done
}

all_current() {
  local index
  for index in "${!TARGET_FILES[@]}"; do release_file_current "$index" || return 1; done
}

create_backup() {
  local rel target metadata owner group
  [[ -n "$REQUEST_ID" ]] || die 'BACKUP_REQUEST_ID_ABSENT'
  CODE_BACKUP="${BACKUP_ROOT}/m046-c01-r1-before-${SHORT_COMMIT}-${REQUEST_ID}.tar.gz"
  PRESTATE_FILE="${BACKUP_ROOT}/m046-c01-r1-before-${SHORT_COMMIT}-${REQUEST_ID}.prestate"
  [[ ! -e "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && ! -e "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" ]] || die 'BACKUP_PATH_ALREADY_EXISTS'
  BACKUP_LIST=$(mktemp "${STATE_ROOT}/m046-c01-backup.list.XXXXXX")
  : >"$PRESTATE_FILE"
  for rel in "${TARGET_FILES[@]}"; do
    target="$TARGET_ROOT/$rel"
    if [[ -e "$target" || -L "$target" ]]; then
      [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'
      metadata=$(owner_group_of "$target"); owner=${metadata%%:*}; group=${metadata#*:}
      printf '%s|PRESENT|%s|%s|%s|%s\n' "$rel" "$(sha256 "$target")" "$owner" "$group" "$(mode_of "$target")" >>"$PRESTATE_FILE"
      printf '%s\n' "$rel" >>"$BACKUP_LIST"
    else printf '%s|ABSENT|none|none|none|none\n' "$rel" >>"$PRESTATE_FILE"; fi
  done
  tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" --files-from "$BACKUP_LIST"
  rm -f "$BACKUP_LIST"; BACKUP_LIST=''; chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"
  fsync_path "$CODE_BACKUP"; fsync_path "$PRESTATE_FILE"; fsync_path "$BACKUP_ROOT"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); PRESTATE_SHA=$(sha256 "$PRESTATE_FILE")
  [[ -s "$CODE_BACKUP" && -s "$PRESTATE_FILE" ]] || die 'BACKUP_EMPTY'
}

prestate_matches() {
  [[ -f "$PRESTATE_FILE" && $(sha256 "$PRESTATE_FILE") == "$PRESTATE_SHA" ]] || return 1
  local rel state expected_sha owner group mode target
  while IFS='|' read -r rel state expected_sha owner group mode; do
    target="$TARGET_ROOT/$rel"
    case "$state" in ABSENT) [[ ! -e "$target" && ! -L "$target" ]] || return 1 ;; PRESENT) [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$expected_sha" && $(owner_group_of "$target") == "$owner:$group" && $(mode_of "$target") == "$mode" ]] || return 1 ;; *) return 1 ;; esac
  done <"$PRESTATE_FILE"
}

apply_files() {
  local index rel target tmp
  for index in "${!RUNTIME_FILES[@]}"; do
    rel=${RUNTIME_FILES[$index]}; target="$TARGET_ROOT/${TARGET_FILES[$index]}"; tmp="${target}.m046-c01.$$"; TEMP_TARGETS+=("$tmp")
    if [[ $TEST_MODE -eq 1 ]]; then install -d -m 0755 "$(dirname "$target")"; install -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp";
    else install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"; install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp"; fi
    [[ $(sha256 "$tmp") == "${RUNTIME_SHAS[$index]}" ]] || die 'TEMP_FILE_HASH_MISMATCH'
    if [[ $TEST_MODE -eq 1 && ${M046_C01_TEST_FAIL_BEFORE_FIRST_MV:-0} == 1 && $index -eq 0 ]]; then die 'TEST_FAIL_BEFORE_FIRST_MV'; fi
    durable_move "$tmp" "$target"
    if [[ $TEST_MODE -eq 1 && ${M046_C01_TEST_FAIL_AFTER_FIRST_FILE:-0} == 1 && $index -eq 0 ]]; then die 'TEST_FAIL_AFTER_FIRST_FILE'; fi
  done
}

runtime_policy_check() {
  local mode=${1:-live} output service_path
  if [[ $TEST_MODE -eq 1 ]]; then output="M046_C01_POLICY=PASS ENTITY=1 STORED_ENABLED=1 STORED_ROW_SHA256=$(<"$STATE_ROOT/stored-row.sha") EFFECTIVE_ENABLED=0 PROVIDER_CALLS=0";
  elif [[ "$mode" == candidate ]]; then
    service_path="$CANDIDATE_REMOTE/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php"
    output=$(docker exec -e M046_C01_DOL_DOCUMENT_ROOT=/var/www/html -e M046_C01_TRANSLATION_SERVICE_PATH="$service_path" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/check_m046_c01_runtime_policy.php") || die 'CANDIDATE_POLICY_CHECK_FAILED'
  elif [[ "$mode" == live ]]; then output=$(docker exec -e M046_C01_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/check_m046_c01_runtime_policy.php") || die 'LIVE_POLICY_CHECK_FAILED'
  else die 'POLICY_CHECK_MODE_INVALID'; fi
  [[ "$output" =~ ^M046_C01_POLICY=PASS\ ENTITY=[0-9]+\ STORED_ENABLED=[01]\ STORED_ROW_SHA256=([a-f0-9]{64}|ABSENT)\ EFFECTIVE_ENABLED=0\ PROVIDER_CALLS=0$ ]] || die 'RUNTIME_POLICY_OUTPUT_INVALID'
  printf '%s\n' "$output"
}
policy_row_sha() { sed -n 's/.* STORED_ROW_SHA256=\([^ ]*\) .*/\1/p' <<<"$1"; }

basic_http_check() {
  [[ $TEST_MODE -eq 1 ]] && return 0
  local body status path
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
  for path in '/custom/chinaplanetshippingprices/admin/setup.php' '/custom/chinaplanetshippingprices/admin/batch.php?id=21'; do
    body=$(mktemp); status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}${path}") || { rm -f "$body"; return 1; }
    if [[ "$status" =~ ^(302|401|403)$ ]] || { [[ "$status" == 200 ]] && grep -Fq 'name="actionlogin"' "$body"; }; then :; else rm -f "$body"; return 1; fi
    rm -f "$body"
  done
}

live_checks() {
  all_current || return 1
  local index rel target
  for index in "${!RUNTIME_FILES[@]}"; do
    target="$TARGET_ROOT/${TARGET_FILES[$index]}"; [[ $(mode_of "$target") == 644 ]] || return 1
    if [[ $TEST_MODE -eq 0 ]]; then [[ $(stat -c '%U:%G' "$target") == 'www-data:www-data' ]] || return 1; fi
  done
  [[ $TEST_MODE -eq 1 ]] && return 0
  for rel in "${RUNTIME_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; done
  basic_http_check
}

save_state() {
  local status=$1 reapply=${2:-} reapply_sha=${3:-} tmp="${STATE_FILE}.tmp.$$"
  cat >"$tmp" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=$status
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_PRESTATE_FILE=$PRESTATE_FILE
STATE_PRESTATE_SHA=$PRESTATE_SHA
STATE_CONFIG_ROW_SHA=$CONFIG_ROW_SHA
STATE_REAPPLY=$reapply
STATE_REAPPLY_SHA=$reapply_sha
STATE_UPDATED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$tmp"; durable_move "$tmp" "$STATE_FILE"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(mode_of "$STATE_FILE") == 600 ]] || die 'STATE_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; PRESTATE_FILE=$STATE_PRESTATE_FILE; PRESTATE_SHA=$STATE_PRESTATE_SHA; CONFIG_ROW_SHA=$STATE_CONFIG_ROW_SHA
}

create_reapply_backup() {
  local reapply index target
  [[ -n "$REQUEST_ID" ]] || die 'REAPPLY_REQUEST_ID_ABSENT'
  reapply="${BACKUP_ROOT}/m046-c01-r1-reapply-${SHORT_COMMIT}-${REQUEST_ID}.tar.gz"
  [[ ! -e "$reapply" && ! -L "$reapply" ]] || die 'REAPPLY_PATH_ALREADY_EXISTS'
  REAPPLY_ROOT=$(mktemp -d "${STATE_ROOT}/reapply.XXXXXX"); REAPPLY_LIST=$(mktemp "${STATE_ROOT}/m046-c01-reapply.list.XXXXXX")
  for index in "${!RUNTIME_FILES[@]}"; do
    target="$REAPPLY_ROOT/${TARGET_FILES[$index]}"; install -d -m 0755 "$(dirname "$target")"; install -m 0644 "$CANDIDATE_ROOT/${RUNTIME_FILES[$index]}" "$target"
    [[ $(sha256 "$target") == "${RUNTIME_SHAS[$index]}" ]] || die 'REAPPLY_CANDIDATE_HASH_INVALID'
  done
  printf '%s\n' "${TARGET_FILES[@]}" >"$REAPPLY_LIST"; tar -czpf "$reapply" -C "$REAPPLY_ROOT" --files-from "$REAPPLY_LIST"
  rm -f "$REAPPLY_LIST"; REAPPLY_LIST=''; rm -rf "$REAPPLY_ROOT"; REAPPLY_ROOT=''; chmod 0600 "$reapply"
  fsync_path "$reapply"; fsync_path "$BACKUP_ROOT"
  printf '%s|%s\n' "$reapply" "$(sha256 "$reapply")"
}

reapply_release() {
  local archive=$1 expected=$2
  [[ -f "$archive" && ! -L "$archive" && $(sha256 "$archive") == "$expected" ]] || return 1
  tar -xzpf "$archive" -C "$TARGET_ROOT" || return 1
  local target
  for target in "${TARGET_FILES[@]}"; do
    if [[ $TEST_MODE -eq 0 ]]; then chown www-data:www-data "$TARGET_ROOT/$target" || return 1; fi
    chmod 0644 "$TARGET_ROOT/$target" || return 1; fsync_path "$TARGET_ROOT/$target" || return 1; fsync_path "$(dirname "$TARGET_ROOT/$target")" || return 1
  done
  live_checks
}

command_compare() {
  check_environment; validate_artifact; candidate_checks; compare_files
  local policy; policy=$(runtime_policy_check candidate); echo "$policy"
  echo "M046_C01_STAGING_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_preflight() {
  check_environment; validate_artifact; candidate_checks; compare_files
  local policy; policy=$(runtime_policy_check candidate); echo "$policy"
  echo "M046_C01_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_apply() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; begin_request; validate_artifact; candidate_checks
  local pre_policy post_policy pre_sha post_sha
  pre_policy=$(runtime_policy_check candidate); pre_sha=$(policy_row_sha "$pre_policy"); [[ -n "$pre_sha" ]] || die 'PRE_POLICY_HASH_ABSENT'
  if all_current; then
    if [[ -f "$STATE_FILE" ]]; then load_state; [[ "$CONFIG_ROW_SHA" == "$pre_sha" ]] || die 'CONFIG_ROW_CHANGED'; fi
    finish_request 'NOOP_ALREADY_MATCHING'
    echo "M046_C01_STAGING_APPLY=NOOP_ALREADY_MATCHING REQUEST_ID=$REQUEST_ID RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA NEW_BACKUP=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
    return
  fi
  compare_files; create_backup; CONFIG_ROW_SHA=$pre_sha; MUTATED=1; apply_files; live_checks || die 'LIVE_CHECK_FAILED'
  post_policy=$(runtime_policy_check live); post_sha=$(policy_row_sha "$post_policy"); [[ "$post_sha" == "$CONFIG_ROW_SHA" ]] || die 'CONFIG_ROW_CHANGED'
  save_state 'DEPLOYED'; finish_request 'APPLIED'; MUTATED=0
  echo "$post_policy"
  echo "M046_C01_STAGING_APPLY=PASS REQUEST_ID=$REQUEST_ID RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA STORED_ROW_SHA256=$CONFIG_ROW_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_verify() {
  check_environment; validate_artifact; candidate_checks
  local policy current_sha
  if [[ ! -e "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then
    live_checks || die 'LIVE_VERIFY_FAILED'
    policy=$(runtime_policy_check live)
    echo "$policy"; echo "M046_C01_STAGING_VERIFY=PASS STATE=UNTRACKED_MATCHING RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
    return
  fi
  load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die 'STATE_NOT_DEPLOYED'; live_checks || die 'LIVE_VERIFY_FAILED'
  policy=$(runtime_policy_check live); current_sha=$(policy_row_sha "$policy"); [[ "$current_sha" == "$CONFIG_ROW_SHA" ]] || die 'CONFIG_ROW_CHANGED'
  echo "$policy"; echo "M046_C01_STAGING_VERIFY=PASS STATE=DEPLOYED RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_rollback() {
  exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
  check_environment; begin_request; validate_artifact; candidate_checks; load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die 'STATE_NOT_DEPLOYED'
  local policy current_sha reapply_info reapply reapply_sha post_policy post_sha
  policy=$(runtime_policy_check candidate); current_sha=$(policy_row_sha "$policy"); [[ "$current_sha" == "$CONFIG_ROW_SHA" ]] || die 'CONFIG_ROW_CHANGED'
  reapply_info=$(create_reapply_backup); reapply=${reapply_info%%|*}; reapply_sha=${reapply_info#*|}
  RESTORE_CONTEXT='ROLLBACK'
  if ! restore_prior || ! prestate_matches || ! basic_http_check; then
    RESTORE_CONTEXT='AUTO'
    if reapply_release "$reapply" "$reapply_sha"; then finish_request 'ROLLBACK_FAILED_REAPPLIED'; echo 'M046_C01_STAGING_ROLLBACK=FAIL_REAPPLIED' >&2; die 'ROLLBACK_RESTORE_FAILED_REAPPLIED'; fi
    finish_request 'ROLLBACK_FAILED_REAPPLY_FAILED'; die 'ROLLBACK_AND_REAPPLY_FAILED'
  fi
  post_policy=$(runtime_policy_check candidate); post_sha=$(policy_row_sha "$post_policy")
  if [[ "$post_sha" != "$CONFIG_ROW_SHA" ]]; then
    RESTORE_CONTEXT='AUTO'
    if reapply_release "$reapply" "$reapply_sha"; then finish_request 'ROLLBACK_CONFIG_CHANGED_REAPPLIED'; die 'ROLLBACK_CONFIG_CHANGED_REAPPLIED'; fi
    finish_request 'ROLLBACK_CONFIG_CHANGED_REAPPLY_FAILED'; die 'ROLLBACK_CONFIG_CHANGED_REAPPLY_FAILED'
  fi
  save_state 'ROLLED_BACK' "$reapply" "$reapply_sha"; finish_request 'ROLLED_BACK'
  echo "$post_policy"; echo "M046_C01_STAGING_ROLLBACK=PASS REQUEST_ID=$REQUEST_ID REAPPLY=$reapply REAPPLY_SHA=$reapply_sha PRESTATE_SHA=$PRESTATE_SHA STORED_ROW_SHA256=$CONFIG_ROW_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}
command_status() {
  if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|PRESTATE_FILE|PRESTATE_SHA|CONFIG_ROW_SHA|REAPPLY|REAPPLY_SHA|UPDATED_AT)=' "$STATE_FILE"; else echo 'M046_C01_STAGING_STATUS=NOT_DEPLOYED'; fi
}

require_invocation "$@"
case "$1" in
  compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;; *) die 'COMMAND_NOT_ALLOWED' ;;
esac
