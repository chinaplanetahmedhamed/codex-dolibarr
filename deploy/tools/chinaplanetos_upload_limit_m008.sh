#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly COMMIT='7bd5a4e'
readonly ARTIFACT='/home/codex-deploy/staging-inbox/m008-7bd5a4e.tar.gz'
readonly CONFIG_FILE='custom/chinaplanetshippingprices/admin/.htaccess'
readonly TEST_FILE='deploy/tests/test_shipping_prices_upload_limits.php'
readonly CONFIG_SHA='7ce2319805cacaff83fc00e095f970a288611b7a37ed56567648f0fefaf6fc72'
readonly TEST_SHA='0e5ee0f0b09def37caba425eced746ed3bf76f5ceebce890e6c1b98cdde72c7c'
readonly EXPECTED_LIMITS='200M|210M|1024M|900|900'
readonly STAGING_CONTAINER='erp-staging-dolibarr-1'
readonly STAGING_VOLUME='erp-staging_erp-staging_custom'
readonly STAGING_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly STAGING_URL='https://backup-erp.visiontrading.biz'
readonly PROD_CONTAINER='vision_erp'
readonly PROD_VOLUME='vision-os_dolibarr_custom'
readonly PROD_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly PROD_URL='https://erp.visiontrading.biz'
readonly PROXY_CONTAINER='management-nginx-proxy-manager-1'
readonly BACKUP_ROOT='/root/chinaplanetos-upload-limit-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-upload-limit-m008'

die() { echo "M008_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
require_root() { [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'; [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'; }

check_target() {
  local container=$1 volume=$2 root=$3 url=$4
  [[ -d "$root" && ! -L "$root" ]] || die 'CUSTOM_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null) == 'true' ]] || die 'ERP_CONTAINER_NOT_RUNNING'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$container") == "${volume}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$url/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
  [[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
  [[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
  local listing expected tmp
  listing=$(mktemp); expected=$(mktemp)
  tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
  printf '%s\n' "$CONFIG_FILE" "$TEST_FILE" | LC_ALL=C sort >"$expected"
  [[ $(wc -l <"$listing") -eq 2 ]] || die 'ARTIFACT_ENTRY_COUNT'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  diff -u "$expected" <(LC_ALL=C sort "$listing") >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
  tmp=$(mktemp -d); tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$tmp"
  [[ -f "$tmp/$CONFIG_FILE" && ! -L "$tmp/$CONFIG_FILE" && $(sha256 "$tmp/$CONFIG_FILE") == "$CONFIG_SHA" ]] || die 'CONFIG_HASH_MISMATCH'
  [[ -f "$tmp/$TEST_FILE" && ! -L "$tmp/$TEST_FILE" && $(sha256 "$tmp/$TEST_FILE") == "$TEST_SHA" ]] || die 'TEST_HASH_MISMATCH'
  php "$tmp/$TEST_FILE" >/dev/null || die 'STATIC_TEST_FAILED'
  CANDIDATE_ROOT="$tmp"; ARTIFACT_SHA=$(sha256 "$ARTIFACT")
  rm -f "$listing" "$expected"
}

probe_limits() {
  local root=$1 url=$2 probe="m008-limit-probe-$$.php" path output
  path="$root/chinaplanetshippingprices/admin/$probe"
  printf '%s\n' '<?php header("Content-Type: text/plain"); echo ini_get("upload_max_filesize")."|".ini_get("post_max_size")."|".ini_get("memory_limit")."|".ini_get("max_execution_time")."|".ini_get("max_input_time");' >"$path"
  chmod 0644 "$path"
  output=$(curl -fsS --max-time 10 "$url/custom/chinaplanetshippingprices/admin/$probe") || { rm -f "$path"; return 1; }
  rm -f "$path"
  [[ "$output" == "$EXPECTED_LIMITS" ]]
}

inspect_proxy() {
  [[ $(docker inspect -f '{{.State.Running}}' "$PROXY_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PROXY_CONTAINER_NOT_RUNNING'
  local values
  values=$(docker exec "$PROXY_CONTAINER" nginx -T 2>/dev/null | awk '$1=="client_max_body_size" {gsub(";","",$2); print $2}' | LC_ALL=C sort -u | paste -sd, -)
  [[ -n "$values" ]] || die 'PROXY_UPLOAD_LIMIT_UNKNOWN'
  echo "M008_PROXY_CLIENT_MAX_BODY_SIZE=$values"
}

create_backup() {
  local env=$1 root=$2 target="$root/chinaplanetshippingprices/admin/.htaccess" stamp
  stamp=$(date -u '+%Y%m%dT%H%M%SZ'); BACKUP="${BACKUP_ROOT}/${env}-before-${COMMIT}-${stamp}.htaccess"
  if [[ -e "$target" ]]; then
    [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_INVALID'
    install -m 0600 "$target" "$BACKUP"; EXISTED=1; BACKUP_SHA=$(sha256 "$BACKUP")
  else
    : >"$BACKUP"; chmod 0600 "$BACKUP"; EXISTED=0; BACKUP_SHA=$(sha256 "$BACKUP")
  fi
}

restore_backup() {
  local root=$1 target="$root/chinaplanetshippingprices/admin/.htaccess"
  [[ -f "$BACKUP" && $(sha256 "$BACKUP") == "$BACKUP_SHA" ]] || return 1
  if [[ "$EXISTED" == 1 ]]; then install -m 0644 "$BACKUP" "$target"; else rm -f "$target"; fi
}

apply_config() {
  local root=$1 target="$root/chinaplanetshippingprices/admin/.htaccess" dir uid gid tmp
  dir=$(dirname "$target"); uid=$(stat -c '%u' "$dir"); gid=$(stat -c '%g' "$dir"); tmp="${target}.m008.$$"
  install -o "$uid" -g "$gid" -m 0644 "$CANDIDATE_ROOT/$CONFIG_FILE" "$tmp"; mv -f "$tmp" "$target"
}

save_state() {
  local env=$1 file="${STATE_ROOT}/${env}.state"
  cat >"$file" <<EOF
STATE_COMMIT=$COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_BACKUP=$BACKUP
STATE_BACKUP_SHA=$BACKUP_SHA
STATE_EXISTED=$EXISTED
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
  chmod 0600 "$file"
}

load_state() {
  local env=$1 file="${STATE_ROOT}/${env}.state"
  [[ -f "$file" && ! -L "$file" ]] || die 'STATE_ABSENT'
  source "$file"; [[ "$STATE_COMMIT" == "$COMMIT" ]] || die 'STATE_COMMIT_MISMATCH'
  BACKUP="$STATE_BACKUP"; BACKUP_SHA="$STATE_BACKUP_SHA"; EXISTED="$STATE_EXISTED"
}

apply_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5
  exec 9>"${STATE_ROOT}/${env}.lock"; flock -n 9 || die 'LOCKED'
  check_target "$container" "$volume" "$root" "$url"; validate_artifact; create_backup "$env" "$root"
  local mutated=0; trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_backup "$root" && echo M008_AUTO_ROLLBACK=PASS >&2 || echo M008_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
  mutated=1; apply_config "$root"; probe_limits "$root" "$url" || false; mutated=0; trap - ERR; save_state "$env"
  echo "M008_${env^^}_APPLY=PASS LIMITS=$EXPECTED_LIMITS ARTIFACT_SHA=$ARTIFACT_SHA BACKUP=$BACKUP BACKUP_SHA=$BACKUP_SHA"
}

verify_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5 target="$root/chinaplanetshippingprices/admin/.htaccess"
  check_target "$container" "$volume" "$root" "$url"; validate_artifact
  [[ -f "$target" && ! -L "$target" && $(sha256 "$target") == "$CONFIG_SHA" ]] || die 'LIVE_CONFIG_HASH_MISMATCH'
  probe_limits "$root" "$url" || die 'EFFECTIVE_LIMITS_MISMATCH'
  echo "M008_${env^^}_VERIFY=PASS LIMITS=$EXPECTED_LIMITS ARTIFACT_SHA=$ARTIFACT_SHA"
}

rollback_environment() {
  local env=$1 container=$2 volume=$3 root=$4 url=$5
  exec 9>"${STATE_ROOT}/${env}.lock"; flock -n 9 || die 'LOCKED'
  check_target "$container" "$volume" "$root" "$url"; load_state "$env"; restore_backup "$root" || die 'ROLLBACK_RESTORE_FAILED'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$url/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
  echo "M008_${env^^}_ROLLBACK=PASS RESTORED=$BACKUP"
}

command_inspect() {
  check_target "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"
  check_target "$PROD_CONTAINER" "$PROD_VOLUME" "$PROD_ROOT" "$PROD_URL"
  inspect_proxy
  echo "M008_INSPECT=PASS"
}
command_staging_apply() { apply_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_staging_verify() { verify_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_staging_rollback() { rollback_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL"; }
command_production_apply() { verify_environment staging "$STAGING_CONTAINER" "$STAGING_VOLUME" "$STAGING_ROOT" "$STAGING_URL" >/dev/null; inspect_proxy; apply_environment production "$PROD_CONTAINER" "$PROD_VOLUME" "$PROD_ROOT" "$PROD_URL"; }
command_production_verify() { inspect_proxy; verify_environment production "$PROD_CONTAINER" "$PROD_VOLUME" "$PROD_ROOT" "$PROD_URL"; }
command_production_rollback() { rollback_environment production "$PROD_CONTAINER" "$PROD_VOLUME" "$PROD_ROOT" "$PROD_URL"; }

require_root "$@"
case "$1" in
  inspect) command_inspect ;;
  staging-apply) command_staging_apply ;;
  staging-verify) command_staging_verify ;;
  staging-rollback) command_staging_rollback ;;
  production-apply) command_production_apply ;;
  production-verify) command_production_verify ;;
  production-rollback) command_production_rollback ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
