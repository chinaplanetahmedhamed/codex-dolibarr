#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M039_SHIPMENT_UPLOAD_200MB'
readonly COMMIT='851c231cc96f51d2c3fafcb32c0a172e4f9973d5'
readonly ARTIFACT='/home/codex-deploy/staging-inbox/m039-851c231.tar.gz'
readonly ARTIFACT_SHA='076280035b2116bdcccbcac42d76508bd9f03170fde16dff7ecf8b614b9e7809'
readonly PORTAL_FILE='custom/chinaplanetcustomercenter/lib/portal.lib.php'
readonly CARD_FILE='custom/chinaplanetlogistics/card.php'
readonly CONFIG_FILE='custom/chinaplanetlogistics/.htaccess'
readonly TEST_FILE='deploy/tests/test_m039_shipment_upload_limits.php'
readonly PORTAL_BEFORE_SHA='63b3defa8acf1018855d28a6e96545e0701c85f2f53c23cb1134128ef0c9fae7'
readonly CARD_BEFORE_SHA='ec5f1ba5743a860eb5c93fbf142bf1e19bfae63a5755e83eaf6c838a29444744'
readonly PORTAL_AFTER_SHA='c1ed927d103517af8a1873fa6f508b7b3f56bafbcb869d9e1231bf7343b16448'
readonly CARD_AFTER_SHA='96ed3bacb97292fee7d0ba8df73be99bab5a4089d6d10156545b3a18d3ab7227'
readonly CONFIG_AFTER_SHA='45b9e1fbfdb9279beeba83af81ebfc881f3edad9706d54e7ee50806afa169daf'
readonly TEST_SHA='c5068e96d03099676515a805aa525f3882f4121fe5a8725f9291dbc23248c158'
readonly EXPECTED_LIMITS='200M|210M|1024M|900|900'
readonly EXACT_UPLOAD_BYTES='209715200'
readonly STAGING_CONTAINER='erp-staging-dolibarr-1'
readonly STAGING_VOLUME='erp-staging_erp-staging_custom'
readonly STAGING_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly STAGING_URL='https://backup-erp.visiontrading.biz'
readonly PRODUCTION_CONTAINER='vision_erp'
readonly PRODUCTION_VOLUME='vision-os_dolibarr_custom'
readonly PRODUCTION_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly PRODUCTION_URL='https://erp.visiontrading.biz'
readonly PROXY_CONTAINER='management-nginx-proxy-manager-1'
readonly BACKUP_ROOT='/root/chinaplanetos-m039-upload-limit-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m039-upload-limit'

die() { echo "M039_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
require_root() { [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'; [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'; }

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
  [[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
  local listing expected verbose
  listing=$(mktemp); expected=$(mktemp); verbose=$(mktemp)
  tar -tzf "$ARTIFACT" | awk '!/\/$/' | LC_ALL=C sort >"$listing" || die 'ARTIFACT_LIST_FAILED'
  printf '%s\n' "$PORTAL_FILE" "$CARD_FILE" "$CONFIG_FILE" "$TEST_FILE" | LC_ALL=C sort >"$expected"
  diff -u "$expected" "$listing" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  tar -tvzf "$ARTIFACT" >"$verbose" || die 'ARTIFACT_VERBOSE_LIST_FAILED'
  [[ -z $(awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}' "$verbose") ]] || die 'ARTIFACT_SPECIAL_ENTRY'
  CANDIDATE_ROOT=$(mktemp -d)
  tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ $(sha256 "$CANDIDATE_ROOT/$PORTAL_FILE") == "$PORTAL_AFTER_SHA" ]] || die 'PORTAL_CANDIDATE_HASH'
  [[ $(sha256 "$CANDIDATE_ROOT/$CARD_FILE") == "$CARD_AFTER_SHA" ]] || die 'CARD_CANDIDATE_HASH'
  [[ $(sha256 "$CANDIDATE_ROOT/$CONFIG_FILE") == "$CONFIG_AFTER_SHA" ]] || die 'CONFIG_CANDIDATE_HASH'
  [[ $(sha256 "$CANDIDATE_ROOT/$TEST_FILE") == "$TEST_SHA" ]] || die 'TEST_CANDIDATE_HASH'
  rm -f "$listing" "$expected" "$verbose"
}

cleanup_candidate() {
  if [[ -n ${CANDIDATE_ROOT:-} && "$CANDIDATE_ROOT" == /tmp/* && -d "$CANDIDATE_ROOT" ]]; then
    rm -rf -- "$CANDIDATE_ROOT"
  fi
}

check_target() {
  local container=$1 volume=$2 root=$3 url=$4
  [[ -d "$root" && ! -L "$root" ]] || die 'CUSTOM_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null) == 'true' ]] || die 'ERP_CONTAINER_NOT_RUNNING'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$container") == "${volume}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$url/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ $(df -Pk "$root" | awk 'NR==2 {print $4}') -ge 1048576 ]] || die 'INSUFFICIENT_DISK'
  [[ -d "$BACKUP_ROOT" && ! -L "$BACKUP_ROOT" && $(stat -c '%U:%G|%a' "$BACKUP_ROOT") == 'root:root|700' ]] || die 'BACKUP_ROOT_INVALID'
  [[ -d "$STATE_ROOT" && ! -L "$STATE_ROOT" && $(stat -c '%U:%G|%a' "$STATE_ROOT") == 'root:root|700' ]] || die 'STATE_ROOT_INVALID'
}

inspect_proxy() {
  [[ $(docker inspect -f '{{.State.Running}}' "$PROXY_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PROXY_CONTAINER_NOT_RUNNING'
  local values
  values=$(docker exec "$PROXY_CONTAINER" nginx -T 2>/dev/null | awk '$1=="client_max_body_size" {gsub(";","",$2); print $2}' | LC_ALL=C sort -u | paste -sd, -)
  [[ -n "$values" && "$values" == *"2000m"* ]] || die 'PROXY_UPLOAD_LIMIT_UNACCEPTED'
  echo "M039_PROXY_CLIENT_MAX_BODY_SIZE=$values"
}

check_before_hashes() {
  local root=$1
  [[ -f "$root/chinaplanetcustomercenter/lib/portal.lib.php" && ! -L "$root/chinaplanetcustomercenter/lib/portal.lib.php" ]] || die 'PORTAL_TARGET_INVALID'
  [[ -f "$root/chinaplanetlogistics/card.php" && ! -L "$root/chinaplanetlogistics/card.php" ]] || die 'CARD_TARGET_INVALID'
  [[ $(sha256 "$root/chinaplanetcustomercenter/lib/portal.lib.php") == "$PORTAL_BEFORE_SHA" ]] || die 'PORTAL_BASELINE_DRIFT'
  [[ $(sha256 "$root/chinaplanetlogistics/card.php") == "$CARD_BEFORE_SHA" ]] || die 'CARD_BASELINE_DRIFT'
  [[ ! -e "$root/chinaplanetlogistics/.htaccess" ]] || die 'CONFIG_BASELINE_DRIFT'
}

check_after_hashes() {
  local root=$1
  [[ $(sha256 "$root/chinaplanetcustomercenter/lib/portal.lib.php") == "$PORTAL_AFTER_SHA" ]] || die 'PORTAL_DEPLOYED_DRIFT'
  [[ $(sha256 "$root/chinaplanetlogistics/card.php") == "$CARD_AFTER_SHA" ]] || die 'CARD_DEPLOYED_DRIFT'
  [[ -f "$root/chinaplanetlogistics/.htaccess" && ! -L "$root/chinaplanetlogistics/.htaccess" ]] || die 'CONFIG_TARGET_INVALID'
  [[ $(sha256 "$root/chinaplanetlogistics/.htaccess") == "$CONFIG_AFTER_SHA" ]] || die 'CONFIG_DEPLOYED_DRIFT'
}

create_backup() {
  local env=$1 root=$2 stamp
  PORTAL_UID=$(stat -c '%u' "$root/chinaplanetcustomercenter/lib/portal.lib.php")
  PORTAL_GID=$(stat -c '%g' "$root/chinaplanetcustomercenter/lib/portal.lib.php")
  PORTAL_MODE=$(stat -c '%a' "$root/chinaplanetcustomercenter/lib/portal.lib.php")
  CARD_UID=$(stat -c '%u' "$root/chinaplanetlogistics/card.php")
  CARD_GID=$(stat -c '%g' "$root/chinaplanetlogistics/card.php")
  CARD_MODE=$(stat -c '%a' "$root/chinaplanetlogistics/card.php")
  stamp=$(date -u '+%Y%m%dT%H%M%SZ')
  BACKUP="$BACKUP_ROOT/${env}-before-851c231-${stamp}.tar.gz"
  tar -czf "$BACKUP" -C "$root" chinaplanetcustomercenter/lib/portal.lib.php chinaplanetlogistics/card.php
  chmod 0600 "$BACKUP"
  BACKUP_SHA=$(sha256 "$BACKUP")
}

restore_backup() {
  local root=$1 tmp
  [[ -f "$BACKUP" && ! -L "$BACKUP" && $(sha256 "$BACKUP") == "$BACKUP_SHA" ]] || return 1
  tmp=$(mktemp -d)
  tar -xzf "$BACKUP" --no-same-owner --no-same-permissions -C "$tmp" || return 1
  [[ $(sha256 "$tmp/chinaplanetcustomercenter/lib/portal.lib.php") == "$PORTAL_BEFORE_SHA" ]] || return 1
  [[ $(sha256 "$tmp/chinaplanetlogistics/card.php") == "$CARD_BEFORE_SHA" ]] || return 1
  install -o "$PORTAL_UID" -g "$PORTAL_GID" -m "$PORTAL_MODE" "$tmp/chinaplanetcustomercenter/lib/portal.lib.php" "$root/chinaplanetcustomercenter/lib/portal.lib.php"
  install -o "$CARD_UID" -g "$CARD_GID" -m "$CARD_MODE" "$tmp/chinaplanetlogistics/card.php" "$root/chinaplanetlogistics/card.php"
  rm -f -- "$root/chinaplanetlogistics/.htaccess"
  rm -rf -- "$tmp"
  check_before_hashes "$root"
}

install_candidate_file() {
  local source=$1 target=$2 parent uid gid tmp
  parent=$(dirname "$target")
  [[ -d "$parent" && ! -L "$parent" ]] || die 'TARGET_PARENT_INVALID'
  if [[ -f "$target" && ! -L "$target" ]]; then
    uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target")
  else
    uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent")
  fi
  tmp="${target}.m039.$$"
  install -o "$uid" -g "$gid" -m 0644 "$source" "$tmp"
  mv -f -- "$tmp" "$target"
}

apply_candidate() {
  local root=$1
  install_candidate_file "$CANDIDATE_ROOT/$PORTAL_FILE" "$root/chinaplanetcustomercenter/lib/portal.lib.php"
  install_candidate_file "$CANDIDATE_ROOT/$CARD_FILE" "$root/chinaplanetlogistics/card.php"
  install_candidate_file "$CANDIDATE_ROOT/$CONFIG_FILE" "$root/chinaplanetlogistics/.htaccess"
}

probe_limits() {
  local root=$1 url=$2 full_upload=${3:-0}
  local token probe path get_output post_output payload
  token=$(head -c 32 /dev/urandom | sha256sum | awk '{print $1}')
  probe="m039-limit-probe-$$.php"
  path="$root/chinaplanetlogistics/$probe"
  printf '%s\n' "<?php if (!hash_equals('$token', (string) (\$_GET['token'] ?? ''))) { http_response_code(404); exit; } header('Content-Type: text/plain'); if ((\$_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { \$u=\$_FILES['probe'] ?? array(); echo (int) (\$u['error'] ?? 99).'|'.(int) (\$u['size'] ?? 0); } else { echo ini_get('upload_max_filesize').'|'.ini_get('post_max_size').'|'.ini_get('memory_limit').'|'.ini_get('max_execution_time').'|'.ini_get('max_input_time'); }" >"$path"
  chmod 0644 "$path"
  if ! get_output=$(curl -fsS --max-time 20 "$url/custom/chinaplanetlogistics/$probe?token=$token"); then rm -f -- "$path"; return 1; fi
  [[ "$get_output" == "$EXPECTED_LIMITS" ]] || { rm -f -- "$path"; return 1; }
  if [[ "$full_upload" == 1 ]]; then
    payload=$(mktemp)
    truncate -s "$EXACT_UPLOAD_BYTES" "$payload"
    printf '%s\n' '%PDF-1.4' | dd of="$payload" conv=notrunc status=none
    if ! post_output=$(curl -fsS --max-time 300 -F "probe=@$payload;type=application/pdf" "$url/custom/chinaplanetlogistics/$probe?token=$token"); then
      rm -f -- "$payload" "$path"; return 1
    fi
    rm -f -- "$payload"
    [[ "$post_output" == "0|$EXACT_UPLOAD_BYTES" ]] || { rm -f -- "$path"; return 1; }
  fi
  rm -f -- "$path"
}

run_runtime_tests() {
  local container=$1 root=$2 url=$3 full_upload=${4:-0}
  docker exec "$container" php -l /var/www/html/custom/chinaplanetcustomercenter/lib/portal.lib.php >/dev/null
  docker exec "$container" php -l /var/www/html/custom/chinaplanetlogistics/card.php >/dev/null
  docker cp "$CANDIDATE_ROOT/$TEST_FILE" "$container:/tmp/m039-upload-test.php" >/dev/null
  if ! docker exec -e M039_REPO_ROOT=/var/www/html "$container" php /tmp/m039-upload-test.php; then
    docker exec "$container" rm -f /tmp/m039-upload-test.php >/dev/null 2>&1 || true
    return 1
  fi
  docker exec "$container" rm -f /tmp/m039-upload-test.php >/dev/null
  probe_limits "$root" "$url" "$full_upload"
}

save_state() {
  local env=$1 file="$STATE_ROOT/$env.state"
  cat >"$file" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=DEPLOYED
STATE_COMMIT=$COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_BACKUP=$BACKUP
STATE_BACKUP_SHA=$BACKUP_SHA
STATE_PORTAL_UID=$PORTAL_UID
STATE_PORTAL_GID=$PORTAL_GID
STATE_PORTAL_MODE=$PORTAL_MODE
STATE_CARD_UID=$CARD_UID
STATE_CARD_GID=$CARD_GID
STATE_CARD_MODE=$CARD_MODE
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$file"
}

load_state() {
  local env=$1 file
  file="$STATE_ROOT/$env.state"
  [[ -f "$file" && ! -L "$file" ]] || die 'STATE_ABSENT'
  source "$file"
  [[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_COMMIT" == "$COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
  BACKUP=$STATE_BACKUP; BACKUP_SHA=$STATE_BACKUP_SHA
  PORTAL_UID=$STATE_PORTAL_UID; PORTAL_GID=$STATE_PORTAL_GID; PORTAL_MODE=$STATE_PORTAL_MODE
  CARD_UID=$STATE_CARD_UID; CARD_GID=$STATE_CARD_GID; CARD_MODE=$STATE_CARD_MODE
}

preflight_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5
  check_target "$container" "$volume" "$root" "$url"
  validate_artifact
  check_before_hashes "$root"
  inspect_proxy
  cleanup_candidate
  echo "M039_${env^^}_PREFLIGHT=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"
}

apply_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5
  exec 9>"$STATE_ROOT/$env.lock"; flock -n 9 || die 'LOCKED'
  check_target "$container" "$volume" "$root" "$url"
  validate_artifact
  check_before_hashes "$root"
  inspect_proxy
  create_backup "$env" "$root"
  local mutated=0
  trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_backup "$root" && echo M039_AUTO_ROLLBACK=PASS >&2 || echo M039_AUTO_ROLLBACK=FAIL >&2; fi; cleanup_candidate; exit $rc' ERR
  mutated=1
  apply_candidate "$root"
  check_after_hashes "$root"
  run_runtime_tests "$container" "$root" "$url" 1
  save_state "$env"
  mutated=0
  trap - ERR
  cleanup_candidate
  echo "M039_${env^^}_APPLY=PASS LIMITS=$EXPECTED_LIMITS EXACT_UPLOAD_BYTES=$EXACT_UPLOAD_BYTES BACKUP=$BACKUP BACKUP_SHA=$BACKUP_SHA"
}

verify_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5
  check_target "$container" "$volume" "$root" "$url"
  validate_artifact
  check_after_hashes "$root"
  inspect_proxy
  run_runtime_tests "$container" "$root" "$url" 0
  load_state "$env"
  cleanup_candidate
  echo "M039_${env^^}_VERIFY=PASS LIMITS=$EXPECTED_LIMITS ARTIFACT_SHA=$ARTIFACT_SHA"
}

rollback_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5 safety safety_sha
  exec 9>"$STATE_ROOT/$env.lock"; flock -n 9 || die 'LOCKED'
  check_target "$container" "$volume" "$root" "$url"
  load_state "$env"
  check_after_hashes "$root"
  safety="$BACKUP_ROOT/${env}-deployed-before-rollback-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
  tar -czf "$safety" -C "$root" chinaplanetcustomercenter/lib/portal.lib.php chinaplanetlogistics/card.php chinaplanetlogistics/.htaccess
  chmod 0600 "$safety"; safety_sha=$(sha256 "$safety")
  restore_backup "$root" || die 'ROLLBACK_RESTORE_FAILED'
  docker exec "$container" php -l /var/www/html/custom/chinaplanetcustomercenter/lib/portal.lib.php >/dev/null
  docker exec "$container" php -l /var/www/html/custom/chinaplanetlogistics/card.php >/dev/null
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$url/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  echo "M039_${env^^}_ROLLBACK=PASS RESTORED=$BACKUP SAFETY_BACKUP=$safety SAFETY_SHA=$safety_sha"
}

require_staging_accepted() {
  check_target "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"
  check_after_hashes "$STAGING_ROOT"
  load_state staging
  probe_limits "$STAGING_ROOT" "$STAGING_URL" 0 || die 'STAGING_ACCEPTANCE_REQUIRED'
}

status() {
  local env file
  for env in staging production; do
    file="$STATE_ROOT/$env.state"
    if [[ -f "$file" && ! -L "$file" ]]; then
      echo "M039_${env^^}_STATE_PRESENT=YES"
      awk -F= '/^STATE_(RELEASE|STATUS|COMMIT|ARTIFACT_SHA|BACKUP|BACKUP_SHA|DEPLOYED_AT)=/ {print "M039_" toupper("'"$env"'") "_" $0}' "$file"
    else
      echo "M039_${env^^}_STATE_PRESENT=NO"
    fi
  done
}

command_staging_preflight() { preflight_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_staging_apply() { apply_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_staging_verify() { verify_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_staging_rollback() { rollback_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_production_preflight() { require_staging_accepted; preflight_environment production "$PRODUCTION_CONTAINER" "$PRODUCTION_VOLUME" "$PRODUCTION_ROOT" "$PRODUCTION_URL"; }
command_production_apply() { require_staging_accepted; apply_environment production "$PRODUCTION_CONTAINER" "$PRODUCTION_VOLUME" "$PRODUCTION_ROOT" "$PRODUCTION_URL"; }
command_production_verify() { require_staging_accepted; verify_environment production "$PRODUCTION_CONTAINER" "$PRODUCTION_VOLUME" "$PRODUCTION_ROOT" "$PRODUCTION_URL"; }
command_production_rollback() { rollback_environment production "$PRODUCTION_CONTAINER" "$PRODUCTION_VOLUME" "$PRODUCTION_ROOT" "$PRODUCTION_URL"; }

require_root "$@"
trap cleanup_candidate EXIT
case "$1" in
  staging-preflight) command_staging_preflight ;;
  staging-apply) command_staging_apply ;;
  staging-verify) command_staging_verify ;;
  staging-rollback) command_staging_rollback ;;
  production-preflight) command_production_preflight ;;
  production-apply) command_production_apply ;;
  production-verify) command_production_verify ;;
  production-rollback) command_production_rollback ;;
  status) status ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
