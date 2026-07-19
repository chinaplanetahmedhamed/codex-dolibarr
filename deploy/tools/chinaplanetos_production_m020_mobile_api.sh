#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M020'
readonly RUNTIME_COMMIT='c46783002ef1902108d938a745b8f9701436dcdb'
readonly SHORT_COMMIT='c467830'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m020-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='17c5d26628a9a40be6373a9647208121c99afcd14fbe0b11ec3c9eeb7365b924'
readonly PROD_CONTAINER='vision_erp'
readonly PROD_DB_CONTAINER='vision_db'
readonly PROD_VOLUME='vision-os_dolibarr_custom'
readonly PROD_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly PROD_HTTP='https://erp.visiontrading.biz/'
readonly API_URL='https://erp.visiontrading.biz/api/index.php/chinaplanetcustomercenter/mobile/v1'
readonly BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
readonly DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-production-deploy'
readonly STATE_FILE="${STATE_ROOT}/m020.state"
readonly LOCK_FILE="${STATE_ROOT}/m020.lock"
readonly MIGRATION_FILE='deploy/migrations/m017_customer_mobile_api_schema.php'
readonly CONFIG_TOOL='deploy/tools/m020_mobile_api_config.php'

readonly -a RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php'
	'custom/chinaplanetcustomercenter/lib/mobile_api.lib.php'
	'custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php'
)
readonly -a SCHEMA_FILES=(
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_token_family.sql'
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_access_token.sql'
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_refresh_token.sql'
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_public_id.sql'
	'custom/chinaplanetcustomercenter/sql/llx_cpcc_mobile_rate_limit.sql'
)
readonly -a CONTRACT_FILES=(
	'docs/API/mobile-v1.openapi.json'
)
readonly -a TEST_FILES=(
	'deploy/tests/test_m017_mobile_api_contract.php'
	'deploy/tests/test_m017_mobile_api_data.php'
	'deploy/tests/test_m017_mobile_api_route.php'
	'deploy/tests/test_m017_mobile_api_security.php'
	'deploy/tests/test_m017_schema_migration.php'
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "${SCHEMA_FILES[@]}" "${CONTRACT_FILES[@]}" "$MIGRATION_FILE" "$CONFIG_TOOL" "${TEST_FILES[@]}")

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''
CONFIG_PRESTATE=''
ROUTE_PRESTATE=''

die() { echo "${RELEASE}_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-production-m020-mobile-api-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" ]]; then docker exec "$PROD_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_environment() {
	[[ -d "$PROD_ROOT" && ! -L "$PROD_ROOT" ]] || die 'PROD_ROOT_INVALID'
	[[ $(docker inspect -f '{{.State.Running}}' "$PROD_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PROD_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$PROD_CONTAINER") == "${PROD_VOLUME}|true" ]] || die 'PROD_MOUNT_MISMATCH'
	[[ $(docker inspect -f '{{.State.Running}}' "$PROD_DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PROD_DB_NOT_RUNNING'
	docker exec "$PROD_DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$PROD_HTTP") =~ ^(200|302)$ ]] || die 'PROD_HTTP_UNHEALTHY'
}

ensure_state_dirs() {
	install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
	[[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
	local listing expected actual
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
	tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
	[[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
	[[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
	CANDIDATE_ROOT=$(mktemp -d)
	tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
	[[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
	printf '%s\n' "${ALL_FILES[@]}" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	local file
	for file in "${ALL_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die "ARTIFACT_FILE_INVALID_${file//\//_}"; done
	rm -f "$listing" "$expected" "$actual"
}

candidate_checks() {
	CANDIDATE_REMOTE="/tmp/m020-candidate-$$"
	docker exec "$PROD_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$PROD_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${RUNTIME_FILES[@]}" "$MIGRATION_FILE" "$CONFIG_TOOL" "${TEST_FILES[@]}"; do docker exec "$PROD_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"; done
	for file in "${TEST_FILES[@]}"; do docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_TEST_FAILED_${file//\//_}"; done
	docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TOOL" debug-check >/dev/null || die 'MAIN_API_DEBUG_ENABLED'
}

schema_state() {
	local rc=0
	if docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION_FILE" check >/dev/null 2>&1; then echo 'PRESENT'; return 0; else rc=$?; fi
	[[ $rc -eq 2 ]] || die 'SCHEMA_CHECK_FAILED'
	echo 'ABSENT'
}

config_state() {
	local rc=0
	if docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TOOL" check >/dev/null 2>&1; then echo 'PRESENT'; return 0; else rc=$?; fi
	[[ $rc -eq 2 ]] || die 'CONFIG_CHECK_FAILED'
	echo 'ABSENT'
}

api_route_status() {
	curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$API_URL/me"
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$PROD_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M020_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		target_hash=$(sha256 "$target"); if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M020_COMPARE FILE=$file STATE=$state PROD_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

create_code_backup() {
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m020-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m020-absent-before-${SHORT_COMMIT}-${stamp}.txt"
	: >"$ABSENT_LIST"
	local -a existing=()
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$PROD_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"; existing+=("$rel"); else printf '%s\n' "$rel" >>"$ABSENT_LIST"; fi
	done
	if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$PROD_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"
	CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}

restore_code_backup() {
	[[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	[[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$PROD_ROOT/$rel"; [[ ! -d "$target" ]] || return 1; rm -f "$target"; done
	[[ $(tar -tzf "$CODE_BACKUP" | wc -l) -eq 0 ]] || tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$PROD_ROOT"
}

create_database_backup() {
	DB_BACKUP="${DB_BACKUP_ROOT}/m020-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
	docker exec "$PROD_DB_CONTAINER" sh -eu -c '
		db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
		user=${MARIADB_USER:-${MYSQL_USER:-root}}
		pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
		[ -n "$db" ] && [ -n "$pass" ]
		if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
		MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
	' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
	[[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'
	chmod 0600 "$DB_BACKUP"; DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

apply_schema() {
	docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION_FILE" apply >/dev/null || die 'SCHEMA_APPLY_FAILED'
	[[ $(schema_state) == 'PRESENT' ]] || die 'SCHEMA_VERIFY_FAILED'
}

apply_config() {
	docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TOOL" apply >/dev/null || die 'CONFIG_APPLY_FAILED'
	[[ $(config_state) == 'PRESENT' ]] || die 'CONFIG_VERIFY_FAILED'
}

remove_created_config() {
	[[ "$CONFIG_PRESTATE" == 'ABSENT' ]] || return 0
	docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TOOL" remove >/dev/null
}

revoke_sessions() {
	docker exec "$PROD_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TOOL" revoke >/dev/null
}

apply_files() {
	local file rel src target parent uid gid mode tmp
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$PROD_ROOT/$rel"; parent=$(dirname "$target")
		[[ -d "$parent" && ! -L "$parent" ]] || die "TARGET_PARENT_INVALID_${file//\//_}"
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"; uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target"); else uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode='644'; fi
		tmp="${target}.m020.$$"; install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"; mv -f "$tmp" "$target"
	done
}

live_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$PROD_ROOT/$rel" && ! -L "$PROD_ROOT/$rel" ]] || return 1; [[ $(sha256 "$PROD_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1; docker exec "$PROD_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; done
	[[ $(schema_state) == 'PRESENT' && $(config_state) == 'PRESENT' ]] || return 1
	[[ $(api_route_status) == '401' ]] || return 1
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$PROD_HTTP") =~ ^(200|302)$ ]]
}

save_state() {
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_CONFIG_PRESTATE=$CONFIG_PRESTATE
STATE_ROUTE_PRESTATE=$ROUTE_PRESTATE
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA; DB_BACKUP=$STATE_DB_BACKUP; DB_BACKUP_SHA=$STATE_DB_BACKUP_SHA; CONFIG_PRESTATE=$STATE_CONFIG_PRESTATE; ROUTE_PRESTATE=$STATE_ROUTE_PRESTATE
}

command_compare() {
	check_environment; validate_artifact; candidate_checks; compare_files
	echo "M020_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$(schema_state) CONFIG=$(config_state) ROUTE_HTTP=$(api_route_status)"
}

command_preflight() {
	check_environment; validate_artifact; candidate_checks
	local schema config route
	schema=$(schema_state); config=$(config_state); route=$(api_route_status)
	[[ "$route" =~ ^(401|404|501)$ ]] || die 'API_ROUTE_BASELINE_UNEXPECTED'
	echo "M020_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$schema CONFIG=$config ROUTE_HTTP=$route"
}

command_apply() {
	check_environment; ensure_state_dirs
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks
	CONFIG_PRESTATE=$(config_state); ROUTE_PRESTATE=$(api_route_status)
	create_code_backup; create_database_backup
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then revoke_sessions >/dev/null 2>&1 || true; restore_code_backup && remove_created_config && echo M020_AUTO_ROLLBACK=PASS >&2 || echo M020_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
	mutated=1; apply_schema; apply_config; apply_files; live_checks; mutated=0; trap - ERR
	save_state
	echo "M020_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA CONFIG_PRESTATE=$CONFIG_PRESTATE"
}

command_verify() {
	check_environment; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'
	echo "M020_VERIFY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=PRESENT CONFIG=PRESENT ROUTE_HTTP=401"
}

command_rollback() {
	check_environment; ensure_state_dirs
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks; load_state
	local reapply="${BACKUP_ROOT}/m020-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
	local -a current=(); local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$PROD_ROOT/$rel" ]] && current+=("$rel"); done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply" -C "$PROD_ROOT" "${current[@]}"; else tar -czf "$reapply" --files-from /dev/null; fi
	chmod 0600 "$reapply"
	revoke_sessions || die 'SESSION_REVOKE_FAILED'
	restore_code_backup || die 'ROLLBACK_CODE_RESTORE_FAILED'
	remove_created_config || die 'ROLLBACK_CONFIG_RESTORE_FAILED'
	[[ $(api_route_status) == "$ROUTE_PRESTATE" ]] || die 'ROLLBACK_ROUTE_MISMATCH'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$PROD_HTTP") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M020_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") SCHEMA_RETAINED=1 CONFIG_PRESTATE=$CONFIG_PRESTATE TOKENS_REVOKED=1"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|CONFIG_PRESTATE|ROUTE_PRESTATE|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M020_STATUS=NOT_DEPLOYED'; fi
}

require_root "$@"
trap cleanup EXIT
case "$1" in
	compare) command_compare ;;
	preflight) command_preflight ;;
	apply) command_apply ;;
	verify) command_verify ;;
	rollback) command_rollback ;;
	status) command_status ;;
	*) die 'COMMAND_NOT_ALLOWED' ;;
esac
