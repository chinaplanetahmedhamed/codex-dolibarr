#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M055'
readonly RUNTIME_COMMIT='991011e8db9a95f0fa98ce5d518d09fca551f4bc'
readonly SHORT_COMMIT='991011e'
readonly ARTIFACT_SHA='983541e2a3f4f0200ba1aeab891efc68226dfe0a34c1f9672b0f4e036a6c3a68'
readonly RUNTIME_MANIFEST_SHA='b1efd61fed028901e6d08d803e3b553e4d9b893566be5c27edde65b5c1795e0e'
readonly SUPPORT_MANIFEST_SHA='8a4811792d228e3436083ac9d8695f85d67d021af6efecd847b002ff5d4af6e7'

readonly RUNTIME_MANIFEST='deploy/releases/m055-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m055-support-files.txt'
readonly -a RUNTIME_FILES=(
  'custom/chinaplanetoperations/class/PublicRequestContactService.class.php'
  'custom/chinaplanetoperations/css/operations.css'
  'custom/chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang'
  'custom/chinaplanetoperations/langs/en_US/chinaplanetoperations.lang'
  'custom/chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang'
  'custom/chinaplanetoperations/operations/public_requests.php'
)
readonly -a RUNTIME_SHAS=(
  'cbf2bef4f0b8986bb63ab0766bf319bb294e8e8b331d334327386f1370d8a9c7'
  '4bc6caa91e52a5b8e768280ce638580962b48f685821a71ec8d9226b0bd0d84b'
  '37f2598611559f6cc474f862f065ea07c17f518eb006efaab9bbaceb6235a7fb'
  'ed81c39e359777b2840309e0540a40c4092f7c44af75dae22ff5b72e424c7fbb'
  'a6dc0ec11399ea15cf419154a4c5528a1f0ff0d623ce43ced8013b622d636641'
  '37081ee920e4c9bff8f905aacb96ada1c985a2204bd7234a62299df9d329c883'
)
readonly -a BASELINE_SHAS=(
  'ABSENT'
  'c16fbaa89adb6a3bfd4f04fcf427f17b4c7676b88035b27947f00fc892241103'
  '7616ebdcc9a6c3f24b3633fb4dc4a6dba3a42288a5570586cdb88e2ad77682f4'
  '37fef2886595bc1a7fb7f7ecec962f5ea7fd3ceb6db00987267b44fae9f9092e'
  'd0f2fe0a1484d88f51f24e335e62509fb47ae069423b4345fe5bc297d50025e5'
  '867059f0e364bf76780591597c248f54e7cdf40228bdc10e710b1605d4f50166'
)
readonly -a TARGET_FILES=(
  'chinaplanetoperations/class/PublicRequestContactService.class.php'
  'chinaplanetoperations/css/operations.css'
  'chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang'
  'chinaplanetoperations/langs/en_US/chinaplanetoperations.lang'
  'chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang'
  'chinaplanetoperations/operations/public_requests.php'
)
readonly -a SUPPORT_FILES=(
  'deploy/tests/test_m055_public_request_contact_service.php'
  'deploy/tests/test_m055_public_request_customer_replies_source.php'
)
readonly -a SUPPORT_SHAS=(
  '2d525ea3f406be2a2a751f96b2d3f936756d495a907182f449587b6e8465dd67'
  'a748bbfaa4cce2a95e3ef87ca633cd62f0261a2399e481ad7e8e70fa1452ae49'
)

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m055-${SHORT_COMMIT}.tar.gz"
readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m055-staging'
readonly STATE_FILE="${STATE_ROOT}/m055.state"
readonly LOCK_FILE="${STATE_ROOT}/m055.lock"

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
PRESTATE_FILE=''
PRESTATE_SHA=''
MUTATED=0

die() { echo "M055_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup_paths() {
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m055-candidate-* ]]; then
    docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
  fi
  if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m055-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
  if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m055-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
}

restore_prior() {
  [[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
  [[ -f "$PRESTATE_FILE" && ! -L "$PRESTATE_FILE" && $(sha256 "$PRESTATE_FILE") == "$PRESTATE_SHA" ]] || return 1
  while IFS='|' read -r rel state expected_sha owner group mode; do
    case "$rel" in
      chinaplanetoperations/class/PublicRequestContactService.class.php|chinaplanetoperations/css/operations.css|chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang|chinaplanetoperations/langs/en_US/chinaplanetoperations.lang|chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang|chinaplanetoperations/operations/public_requests.php) ;;
      *) return 1 ;;
    esac
    local target="$TARGET_ROOT/$rel" tmp="${TARGET_ROOT}/${rel}.m055-restore.$$"
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
    if restore_prior; then echo 'M055_STAGING_AUTO_ROLLBACK=PASS' >&2; else echo 'M055_STAGING_AUTO_ROLLBACK=FAIL' >&2; fi
  fi
  cleanup_paths
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m055-customer-replies' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

check_environment() {
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER" 2>/dev/null) == 'true|erp-staging|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) =~ ^127\.0\.0\.1:1102[56]$ ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) =~ ^127\.0\.0\.1:1802[56]$ ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  [[ -d "$BACKUP_ROOT" && ! -L "$BACKUP_ROOT" && $(stat -c '%U:%G %a' "$BACKUP_ROOT") == 'root:root 700' ]] || die 'BACKUP_ROOT_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G %a' "$STATE_ROOT") == 'root:root 700' ]] || die 'STATE_ROOT_INVALID'
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
  ARTIFACT_COPY=$(mktemp /root/m055-artifact.XXXXXX)
  install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
  [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing types expected actual index
  listing=$(mktemp); types=$(mktemp); expected=$(mktemp); actual=$(mktemp)
  tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  [[ $(wc -l <"$listing") -eq 19 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
  tar -tvzf "$ARTIFACT_COPY" >"$types" || die 'ARTIFACT_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$types") ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
  CANDIDATE_ROOT=$(mktemp -d /root/m055-candidate.XXXXXX)
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
  CANDIDATE_REMOTE="/tmp/m055-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE/custom/chinaplanetoperations/class" "$CANDIDATE_REMOTE/custom/chinaplanetoperations/css" "$CANDIDATE_REMOTE/custom/chinaplanetoperations/langs/ar_SA" "$CANDIDATE_REMOTE/custom/chinaplanetoperations/langs/en_US" "$CANDIDATE_REMOTE/custom/chinaplanetoperations/langs/zh_CN" "$CANDIDATE_REMOTE/custom/chinaplanetoperations/operations" "$CANDIDATE_REMOTE/deploy/tests"
  local rel
  for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do docker cp "$CANDIDATE_ROOT/$rel" "$TARGET_CONTAINER:$CANDIDATE_REMOTE/$rel"; done
  for rel in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_PHP_LINT_FAILED'; done
  for rel in "${SUPPORT_FILES[@]}"; do docker exec -e M055_DOL_DOCUMENT_ROOT="$CANDIDATE_REMOTE" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || die 'CANDIDATE_CONTRACT_TEST_FAILED'; done
}

compare_files() {
  local index rel target_rel target target_sha state
  for index in "${!RUNTIME_FILES[@]}"; do
    rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"
    if [[ ! -e "$target" && ! -L "$target" ]]; then state='ABSENT'; target_sha='none'
    else [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; target_sha=$(sha256 "$target"); if [[ "$target_sha" == "${RUNTIME_SHAS[$index]}" ]]; then state='CURRENT'; elif [[ "$target_sha" == "${BASELINE_SHAS[$index]}" ]]; then state='UPDATE_REQUIRED'; else die 'TARGET_FILE_UNEXPECTED_DRIFT'; fi; fi
    if [[ "$state" == 'ABSENT' && "${BASELINE_SHAS[$index]}" != 'ABSENT' ]]; then die 'TARGET_FILE_UNEXPECTED_ABSENCE'; fi
    echo "M055_STAGING_COMPARE FILE=$rel STATE=$state TARGET_SHA=$target_sha RELEASE_SHA=${RUNTIME_SHAS[$index]}"
  done
}

create_backup() {
  local stamp backup_list rel target
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  CODE_BACKUP="${BACKUP_ROOT}/m055-before-${SHORT_COMMIT}-${stamp}.tar.gz"
  PRESTATE_FILE="${BACKUP_ROOT}/m055-before-${SHORT_COMMIT}-${stamp}.prestate"
  backup_list=$(mktemp); : >"$PRESTATE_FILE"
  for rel in "${TARGET_FILES[@]}"; do target="$TARGET_ROOT/$rel"; if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; printf '%s|PRESENT|%s|%s|%s|%s\n' "$rel" "$(sha256 "$target")" "$(stat -c '%U' "$target")" "$(stat -c '%G' "$target")" "$(stat -c '%a' "$target")" >>"$PRESTATE_FILE"; printf '%s\n' "$rel" >>"$backup_list"; else printf '%s|ABSENT|none|none|none|none\n' "$rel" >>"$PRESTATE_FILE"; fi; done
  tar -czpf "$CODE_BACKUP" -C "$TARGET_ROOT" --files-from "$backup_list"; rm -f "$backup_list"; chmod 0600 "$CODE_BACKUP" "$PRESTATE_FILE"
  CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); PRESTATE_SHA=$(sha256 "$PRESTATE_FILE")
  [[ -s "$CODE_BACKUP" && -s "$PRESTATE_FILE" ]] || die 'BACKUP_EMPTY'
}

apply_files() {
  local index rel target_rel target tmp
  for index in "${!RUNTIME_FILES[@]}"; do rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"; tmp="${TARGET_ROOT}/${target_rel}.m055.$$"; install -d -o www-data -g www-data -m 0755 "$(dirname "$target")"; install -o www-data -g www-data -m 0644 "$CANDIDATE_ROOT/$rel" "$tmp"; [[ $(sha256 "$tmp") == "${RUNTIME_SHAS[$index]}" ]] || die 'TEMP_FILE_HASH_MISMATCH'; mv -f "$tmp" "$target"; done
}

live_checks() {
  local index rel target_rel target body status
  for index in "${!RUNTIME_FILES[@]}"; do rel=${RUNTIME_FILES[$index]}; target_rel=${TARGET_FILES[$index]}; target="$TARGET_ROOT/$target_rel"; [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "${RUNTIME_SHAS[$index]}" ]] || return 1; [[ $(stat -c '%U:%G %a' "$target") == 'www-data:www-data 644' ]] || return 1; docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$rel" >/dev/null || return 1; done
  for rel in "${SUPPORT_FILES[@]}"; do docker exec -e M055_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$rel" >/dev/null || return 1; done
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || return 1
  body=$(mktemp); status=$(curl -sS -o "$body" -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/custom/chinaplanetoperations/operations/public_requests.php") || { rm -f "$body"; return 1; }
  if [[ "$status" =~ ^(302|401|403)$ ]]; then rm -f "$body"; return 0; fi
  if [[ "$status" == '200' ]] && grep -Fq '<form id="login"' "$body" && grep -Fq 'name="actionlogin"' "$body"; then rm -f "$body"; return 0; fi
  rm -f "$body"; return 1
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

command_compare() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M055_STAGING_COMPARE=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M055_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_apply() { exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; candidate_checks; if [[ -f "$STATE_FILE" ]]; then load_state; if live_checks; then echo "M055_STAGING_APPLY=NOOP_ALREADY_APPLIED RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; return; fi; fi; compare_files; create_backup; MUTATED=1; apply_files; live_checks || die 'LIVE_CHECK_FAILED'; MUTATED=0; save_state; echo "M055_STAGING_APPLY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA PRESTATE=$PRESTATE_FILE PRESTATE_SHA=$PRESTATE_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_verify() { check_environment; validate_artifact; candidate_checks; load_state; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M055_STAGING_VERIFY=PASS RELEASE=$RELEASE COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=6 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_rollback() { exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; check_environment; validate_artifact; load_state; local reapply="${BACKUP_ROOT}/m055-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"; tar -czpf "$reapply" -C "$TARGET_ROOT" "${TARGET_FILES[@]}"; chmod 0600 "$reapply"; restore_prior || die 'CODE_RESTORE_FAILED'; [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'; sed -i 's/^STATE_STATUS=.*/STATE_STATUS=ROLLED_BACK/' "$STATE_FILE"; echo "M055_STAGING_ROLLBACK=PASS REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"; }
command_status() { if [[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|PRESTATE_FILE|PRESTATE_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M055_STAGING_STATUS=NOT_DEPLOYED'; fi; }

require_root "$@"
case "$1" in compare) command_compare ;; preflight) command_preflight ;; apply) command_apply ;; verify) command_verify ;; rollback) command_rollback ;; status) command_status ;; *) die 'COMMAND_NOT_ALLOWED' ;; esac
