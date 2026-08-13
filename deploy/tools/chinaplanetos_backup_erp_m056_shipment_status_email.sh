#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M056'
readonly RUNTIME_COMMIT='2646d0b3f1fb8b47c45325b2aedec7695a2ad73f'
readonly SHORT_COMMIT='2646d0b'
readonly ARTIFACT_SHA='55c5e18fbb382327a5432243f344246c9d2a08042fb6abf6d563740cb6077074'
readonly RUNTIME_MANIFEST_SHA='a83d2658f859ae2ff2cf2e67104ffb29cf2a1749001f4848e2046bfe2c90938f'
readonly SUPPORT_MANIFEST_SHA='a5b2567ee99cac08f4a141e55cf1cda1591e9e6e04520ff4fc3d90cf37fab9e7'

readonly RUNTIME_MANIFEST='deploy/releases/m056-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m056-support-files.txt'
readonly -a RUNTIME_FILES=(
  'custom/chinaplanetlogistics/card.php'
  'custom/chinaplanetlogistics/class/ShipmentStatusEmailService.class.php'
  'custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php'
  'custom/chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang'
  'custom/chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang'
  'custom/chinaplanetlogistics/langs/zh_CN/chinaplanetlogistics.lang'
  'custom/chinaplanetlogistics/lib/shipment_customer.lib.php'
)
readonly -a RUNTIME_SHAS=(
  'ba3282b2b1f8d6fdd8f6aa6daeeff9fa96f03b85174663fc0df2db10cc2bff4d'
  'e497738f6480a336c7573b3cfc53da2e41a95ebefb1bb8c2a2ae01f487509ef8'
  'da81d62b2d4aa2081abba356e8c0a18faff36d395b6c9d3a159f6d46866718a3'
  'f2617da31963b4ebed45e7f81f6c85fc5de7fd2c1af69590c861c898ca2927d6'
  '097d8b6ca20ae0fc02780dc8090d129a5422802cf546757117ba567bc37f278e'
  '7f31adb9975c1555ac4334b4144bd6b0e9a2b63bf66c5a1db07b59375cf085c4'
  'c9413816f8e08dd68accab133bca11919103c4c64e4ebcb8d8f6436f1465d51e'
)
readonly -a BASELINE_SHAS=(
  '96ed3bacb97292fee7d0ba8df73be99bab5a4089d6d10156545b3a18d3ab7227'
  'ABSENT'
  'a223391dcbf6242db4faa264192fad07c0580457d23cb14617ffb11a3ebed093'
  '200014bbf96659200078a84c884e4880bae908c15071497ce9fcc5a07fce00b1'
  '24195f34f70bea6474ac8a6dd9755b257ed61a9025c66ba03b980dcfc7bee9ec'
  'ABSENT'
  '87ffcaffc3b493695a2945d0988fbdf69f70e477570a1539aaecc7ee30e23f7b'
)
readonly -a TARGET_FILES=(
  'chinaplanetlogistics/card.php'
  'chinaplanetlogistics/class/ShipmentStatusEmailService.class.php'
  'chinaplanetlogistics/class/chinaplanetlogistics.class.php'
  'chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang'
  'chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang'
  'chinaplanetlogistics/langs/zh_CN/chinaplanetlogistics.lang'
  'chinaplanetlogistics/lib/shipment_customer.lib.php'
)
readonly -a SUPPORT_FILES=(
  'deploy/tests/test_m056_shipment_status_email_service.php'
  'deploy/tests/test_m056_shipment_status_email_source.php'
  'deploy/tests/test_m056_shipment_update_commit.php'
)
readonly -a SUPPORT_SHAS=(
  '46beae6741f7654acebca9ab51da28f3f45a37e84178aacd18a19fbbd152931d'
  '78d55e8e5de7eec25e234534ce41431961f93d6cf1864e0b09cfa02e12aaabe0'
  '2c88f8f4e266071db187059a9b3a11237678381172eeace06ee366ca7128519e'
)

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m056-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m056-staging'
readonly STATE_FILE="${STATE_ROOT}/m056.state"
readonly LOCK_FILE="${STATE_ROOT}/m056.lock"

CANDIDATE_ROOT=''; CANDIDATE_REMOTE=''; ARTIFACT_COPY=''
CODE_BACKUP=''; CODE_BACKUP_SHA=''; PRESTATE_FILE=''; PRESTATE_SHA=''; MUTATED=0
REAPPLY_BACKUP=''; REAPPLY_SHA=''; ROLLBACK_MUTATED=0

die() { echo "M056_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup_paths() {
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m056-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m056-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m056-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
}

restore_prior() {
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" && $(sha256 "$PRESTATE_FILE") == "$PRESTATE_SHA" ]] || return 1
  while IFS='|' read -r rel state expected_sha owner group mode; do
    case "$rel" in
      chinaplanetlogistics/card.php|chinaplanetlogistics/class/ShipmentStatusEmailService.class.php|chinaplanetlogistics/class/chinaplanetlogistics.class.php|chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang|chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang|chinaplanetlogistics/langs/zh_CN/chinaplanetlogistics.lang|chinaplanetlogistics/lib/shipment_customer.lib.php) ;;
      *) return 1 ;;
    esac
    local target="$TARGET_ROOT/$rel" tmp="${TARGET_ROOT}/${rel}.m056-restore.$$"
    case "$state" in
      ABSENT) [[ "$target" == "$TARGET_ROOT/"* ]] || return 1; rm -f "$target"; sync -f "$(dirname "$target")" || return 1 ;;
      PRESENT) tar -xOf "$CODE_BACKUP" "$rel" >"$tmp" || return 1; [[ $(sha256 "$tmp") == "$expected_sha" ]] || return 1; chown "$owner:$group" "$tmp" || return 1; chmod "$mode" "$tmp" || return 1; sync -f "$tmp" || return 1; mv -f "$tmp" "$target" || return 1; sync -f "$(dirname "$target")" || return 1 ;;
      *) return 1 ;;
    esac
  done <"$PRESTATE_FILE"
}

restore_reapply() {
  [[ -f "$REAPPLY_BACKUP" && ! -L "$REAPPLY_BACKUP" && $(sha256 "$REAPPLY_BACKUP") == "$REAPPLY_SHA" ]] || return 1
  tar -xzpf "$REAPPLY_BACKUP" -C "$TARGET_ROOT" || return 1
  sync -f "$TARGET_ROOT" || return 1
}

cleanup() {
  local rc=$?; trap - EXIT
  if [[ $ROLLBACK_MUTATED -eq 1 ]]; then
    if restore_reapply && live_checks; then echo 'M056_STAGING_ROLLBACK_REAPPLY=PASS' >&2; else echo 'M056_STAGING_ROLLBACK_REAPPLY=FAIL' >&2; fi
  elif [[ $MUTATED -eq 1 ]]; then
    if restore_prior; then echo 'M056_STAGING_AUTO_ROLLBACK=PASS' >&2; else echo 'M056_STAGING_AUTO_ROLLBACK=FAIL' >&2; fi
  fi
  cleanup_paths; exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m056-shipment-status-email' ]] || die 'HELPER_PATH_NOT_ALLOWED'
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

require_disk_space() {
  local target available_kib required_bytes required_kib artifact_bytes current_bytes=0 rel path
  artifact_bytes=$(stat -c '%s' "$ARTIFACT")
  for rel in "${TARGET_FILES[@]}"; do path="$TARGET_ROOT/$rel"; if [[ -f "$path" && ! -L "$path" ]]; then current_bytes=$((current_bytes + $(stat -c '%s' "$path"))); fi; done
  required_bytes=$((67108864 + (artifact_bytes * 4) + (current_bytes * 6)))
  required_kib=$(((required_bytes + 1023) / 1024))
  for target in "$BACKUP_ROOT" "$TARGET_ROOT" "$STATE_ROOT"; do
    available_kib=$(df -Pk "$target" | awk 'NR==2 {print $4}')
    [[ "$available_kib" =~ ^[0-9]+$ && "$available_kib" -ge "$required_kib" ]] || die 'INSUFFICIENT_DISK_SPACE'
  done
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m056-artifact.XXXXXX); install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual index
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 23 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m056-candidate.XXXXXX)
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ $(sha256 "$CANDIDATE_ROOT/$RUNTIME_MANIFEST") == "$RUNTIME_MANIFEST_SHA" ]] || die 'RUNTIME_MANIFEST_HASH_MISMATCH'
  [[ $(sha256 "$CANDIDATE_ROOT/$SUPPORT_MANIFEST") == "$SUPPORT_MANIFEST_SHA" ]] || die 'SUPPORT_MANIFEST_HASH_MISMATCH'
  printf '%s\n' "${RUNTIME_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$RUNTIME_MANIFEST" >/dev/null || die 'RUNTIME_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "${SUPPORT_FILES[@]}" | diff -u - "$CANDIDATE_ROOT/$SUPPORT_MANIFEST" >/dev/null || die 'SUPPORT_MANIFEST_SCOPE_INVALID'
  printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
  find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
  diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  for index in "${!RUNTIME_FILES[@]}"; do [[ $(sha256 "$CANDIDATE_ROOT/${RUNTIME_FILES[$index]}") == "${RUNTIME_SHAS[$index]}" ]] || die 'RUNTIME_FILE_HASH_MISMATCH'; done
  for index in "${!SUPPORT_FILES[@]}"; do [[ $(sha256 "$CANDIDATE_ROOT/${SUPPORT_FILES[$index]}") == "${SUPPORT_SHAS[$index]}" ]] || die 'SUPPORT_FILE_HASH_MISMATCH'; done
  rm -f "$listing" "$types" "$expected" "$actual"
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m056-candidate-$$"; docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE/custom/chinaplanetlogistics/class" "$CANDIDATE_REMOTE/custom/chinaplanetlogistics/langs/ar_SA" "$CANDIDATE_REMOTE/custom/chinaplanetlogistics/langs/en_US" "$CANDIDATE_REMOTE/custom/chinaplanetlogistics/langs/zh_CN" "$CANDIDATE_REMOTE/custom/chinaplanetlogistics/lib" "$CANDIDATE_REMOTE/deploy/tests"
  local rel
  for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do docker cp "$CANDIDATE_ROOT/$rel" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$rel"; done
  for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_PHP_LINT_FAILED'; fi; done
  for rel in "${SUPPORT_FILES[@]}"; do docker exec -e M056_DOL_DOCUMENT_ROOT="$CANDIDATE_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_CONTRACT_TEST_FAILED'; done
}

compare_files() {
  local index rel target_rel target target_sha state
  for index in "${!RUNTIME_FILES[@]}"; do
    rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"
    if [[ ! -e "$target" && ! -L "$target" ]]; then state='ABSENT'; target_sha='none'
    else [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; target_sha=$(sha256 "$target"); if [[ "$target_sha" == "${RUNTIME_SHAS[$index]}" ]]; then state='CURRENT'; elif [[ "$target_sha" == "${BASELINE_SHAS[$index]}" ]]; then state='UPDATE_REQUIRED'; else die 'TARGET_FILE_UNEXPECTED_DRIFT'; fi; fi
    if [[ "$state" == 'ABSENT' && "${BASELINE_SHAS[$index]}" != 'ABSENT' ]]; then die 'TARGET_FILE_UNEXPECTED_ABSENCE'; fi
    echo "M056_STAGING_COMPARE FILE=$rel STATE=$state TARGET_SHA=$target_sha RELEASE_SHA=${RUNTIME_SHAS[$index]}"
  done
}

create_backup() {
  local stamp backup_list rel target
  stamp=$(date -u '+%Y%m%dT%H%M%SZ'); CODE_BACKUP="${BACKUP_ROOT}/m056-before-${SHORT_COMMIT}-${stamp}.tar.gz"; PRESTATE_FILE="${BACKUP_ROOT}/m056-before-${SHORT_COMMIT}-${stamp}.prestate"
  backup_list=$(mktemp); : >"$PRESTATE_FILE"
  for rel in "${TARGET_FILES[@]}"; do target="$TARGET_ROOT/$rel"; if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; printf '%s|PRESENT|%s|%s|%s|%s\n' "$rel" "$(sha256 "$target")" "$(stat -c '%U' "$target")" "$(stat -c '%G' "$target")" "$(stat -c '%a' "$target")" >>"$PRESTATE_FILE"; printf '%s\n' "$rel" >>"$backup_list"; else printf '%s|ABSENT|none|none|none|none\n' "$rel" >>"$PRESTATE_FILE"; fi; done
  tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" --files-from "$backup_list"; rm -f "$backup_list"; chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"
  sync -f "$CODE_BACKUP"; sync -f "$PRESTATE_FILE"; sync -f "$BACKUP_ROOT"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); PRESTATE_SHA=$(sha256 "$PRESTATE_FILE"); [[ -s "$CODE_BACKUP" && -s "$PRESTATE_FILE" ]] || die 'BACKUP_EMPTY'
}

apply_files() {
  local index rel target_rel target tmp
  for index in "${!RUNTIME_FILES[@]}"; do rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"; tmp="${TARGET_ROOT}/${target_rel}.m056.$$"; install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"; install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp"; [[ $(sha256 "$tmp") == "${RUNTIME_SHAS[$index]}" ]] || die 'TEMP_FILE_HASH_MISMATCH'; sync -f "$tmp"; mv -f "$tmp" "$target"; sync -f "$(dirname "$target")"; done
}

prior_checks() {
  local rel state expected_sha owner group mode target
  while IFS='|' read -r rel state expected_sha owner group mode; do
    target="$TARGET_ROOT/$rel"
    case "$state" in
      ABSENT) [[ ! -e "$target" && ! -L "$target" ]] || return 1 ;;
      PRESENT) [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$expected_sha" ]] || return 1; [[ $(stat -c '%U:%G %a' "$target") == "$owner:$group $mode" ]] || return 1; if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/custom/$rel" >/dev/null || return 1; fi ;;
      *) return 1 ;;
    esac
  done <"$PRESTATE_FILE"
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
}

live_checks() {
  local index rel target_rel target body status
  for index in "${!RUNTIME_FILES[@]}"; do rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"; [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "${RUNTIME_SHAS[$index]}" ]] || return 1; [[ $(stat -c '%U:%G %a' "$target") == 'www-data:www-data 644' ]] || return 1; if [[ "$rel" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; fi; done
  for rel in "${SUPPORT_FILES[@]}"; do docker exec -e M056_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || return 1; done
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
  body=$(mktemp); status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/custom/chinaplanetlogistics/card.php?id=1") || { rm -f "$body"; return 1; }
  if [[ "$status" =~ ^(302|401|403)$ ]]; then rm -f "$body"; return 0; fi
  if [[ "$status" == '200' ]] && grep -Fq '<form id="login"' "$body" && grep -Fq 'name="actionlogin"' "$body"; then rm -f "$body"; return 0; fi
  rm -f "$body"; return 1
}

save_state() {
  save_state_status 'DEPLOYED'
}

save_state_status() {
  local status="$1" temporary
  [[ "$status" == 'DEPLOYED' || "$status" == 'ROLLED_BACK' ]] || return 1
  temporary=$(mktemp "${STATE_ROOT}/m056.state.XXXXXX")
  printf '%s\n' "STATE_RELEASE=$RELEASE" "STATE_STATUS=$status" "STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT" "STATE_ARTIFACT_SHA=$ARTIFACT_SHA" "STATE_CODE_BACKUP=$CODE_BACKUP" "STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA" "STATE_PRESTATE_FILE=$PRESTATE_FILE" "STATE_PRESTATE_SHA=$PRESTATE_SHA" "STATE_DEPLOYED_AT=${STATE_DEPLOYED_AT:-$(date -u '+%Y-%m-%dT%H:%M:%SZ')}" >"$temporary"
  chmod 0600 "$temporary"; sync -f "$temporary"; mv -f "$temporary" "$STATE_FILE"; sync -f "$STATE_ROOT"
}

load_state() {
  [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_INVALID'
  source "$STATE_FILE"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  [[ "$STATE_STATUS" == 'DEPLOYED' || "$STATE_STATUS" == 'ROLLED_BACK' ]] || die 'STATE_STATUS_INVALID'
  CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; PRESTATE_FILE=$STATE_PRESTATE_FILE; PRESTATE_SHA=$STATE_PRESTATE_SHA
}

command_compare() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M056_STAGING_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_preflight() { check_environment; validate_artifact; require_disk_space; candidate_checks; compare_files; echo "M056_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=7 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_apply() { exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; require_disk_space; candidate_checks; if [[ -f "$STATE_FILE" ]]; then load_state; [[ "$STATE_STATUS" == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'; if live_checks; then echo "M056_STAGING_APPLY=NOOP_ALREADY_APPLIED RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; return; fi; fi; compare_files; create_backup; MUTATED=1; apply_files; live_checks || die 'LIVE_CHECK_FAILED'; save_state; MUTATED=0; echo "M056_STAGING_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA FILES=7 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_verify() { check_environment; validate_artifact; candidate_checks; load_state; [[ "$STATE_STATUS" == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M056_STAGING_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=7 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_rollback() { exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; require_disk_space; candidate_checks; load_state; [[ "$STATE_STATUS" == 'DEPLOYED' ]] || die 'STATE_NOT_DEPLOYED'; REAPPLY_BACKUP="${BACKUP_ROOT}/m056-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"; tar -czpf "$REAPPLY_BACKUP" -C "$TARGET_ROOT" "${TARGET_FILES[@]}"; chmod 0600 "$REAPPLY_BACKUP"; sync -f "$REAPPLY_BACKUP"; sync -f "$BACKUP_ROOT"; REAPPLY_SHA=$(sha256 "$REAPPLY_BACKUP"); [[ -s "$REAPPLY_BACKUP" ]] || die 'REAPPLY_BACKUP_EMPTY'; ROLLBACK_MUTATED=1; restore_prior || die 'CODE_RESTORE_FAILED'; prior_checks || die 'ROLLBACK_VERIFY_FAILED'; save_state_status 'ROLLED_BACK'; ROLLBACK_MUTATED=0; echo "M056_STAGING_ROLLBACK=PASS REAPPLY=$REAPPLY_BACKUP REAPPLY_SHA=$REAPPLY_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_status() { if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|PRESTATE_FILE|PRESTATE_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M056_STAGING_STATUS=NOT_DEPLOYED'; fi; }

require_root "$@"
case "$1" in compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
