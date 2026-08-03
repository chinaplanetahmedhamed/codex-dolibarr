#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M041'
readonly RUNTIME_COMMIT='47ceae0d56848c7badbfbcca4abc9e7af3badbc0'
readonly SHORT_COMMIT='47ceae0'
readonly ARTIFACT_SHA='6ee49c1fb298aaa248d56ce771d37d018001b1fc56c0813234e836d8facc7beb'
readonly RUNTIME_MANIFEST='deploy/releases/m041-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m041-support-files.txt'
readonly RUNTIME_MANIFEST_SHA='dcb358f6544694a0c9167b0b355bbef90c689d4cc903bf3ae45a3bea47ed0cbc'
readonly SUPPORT_MANIFEST_SHA='807fdf1fa8b579745fdfb34c8e4f6cd29418772179d2dc1679c1b11263b5749b'
readonly SCHEMA='deploy/migrations/m041_shipping_note_translation_schema.php'
readonly PROVIDER_TEST='deploy/tests/test_m041_translation_provider.php'
readonly PROVIDER_ENDPOINT='https://api.deepseek.com/chat/completions'
readonly PROVIDER_MODEL='deepseek-v4-flash'
readonly MIN_FREE_KB=1048576

readonly -a STATIC_TESTS=(
	'deploy/tests/test_m034_shipping_price_publication_state.php'
	'deploy/tests/test_m041_schema_migration.php'
	'deploy/tests/test_m041_shipping_note_translation.php'
	'deploy/tests/test_m041_shipping_note_translation_source.php'
	'deploy/tests/test_shipping_prices_module.php'
	'deploy/tests/test_shipping_prices_upload_csrf.php'
)
readonly -a RUNTIME_TESTS=(
	'deploy/tests/test_m041_shipping_note_translation_runtime.php'
	'deploy/tests/test_shipping_prices_integration.php'
	'deploy/tests/test_m035_customer_shipping_booking.php'
)

ENVIRONMENT=''
DEPLOY_ACCOUNT=''
INBOX=''
ARTIFACT=''
TARGET_CONTAINER=''
TARGET_DB_CONTAINER=''
TARGET_PROJECT=''
TARGET_SERVICE='dolibarr'
TARGET_DB_SERVICE='mariadb'
TARGET_VOLUME=''
TARGET_ROOT=''
TARGET_HTTP=''
TARGET_MAILPIT_CONTAINER=''
BACKUP_ROOT=''
DB_BACKUP_ROOT=''
STATE_ROOT=''
STATE_FILE=''
PROVIDER_STATE_ROOT=''
LOCK_FILE=''
KEY_FILE=''
OVERRIDE_FILE=''
APPROVAL_MESSAGE=''
APPROVAL_SIGNATURE=''
readonly APPROVAL_PUBLIC_KEY='/etc/chinaplanetos/m041-production-approval-public.pem'
readonly APPROVAL_PUBLIC_KEY_SHA='683ff83437ab5df160ca8e4f49673f0eba01c9fbbf744a1b27e58df4c33a6e9a'

RUNTIME_FILES=()
SUPPORT_FILES=()
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
ARTIFACT_COPY=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''
SCHEMA_PRESTATE_B64=''
SCHEMA_PRESTATE_SHA=''
REMOVE_KEY_ON_EXIT=0
REMOVE_APPROVAL_ON_EXIT=0

die() { echo "M041_${ENVIRONMENT^^}_FAIL=$1" >&2; return 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
value_sha256() { printf '%s' "$1" | sha256sum | awk '{print $1}'; }

select_environment() {
	case "$1" in
		staging-*)
			ENVIRONMENT='staging'
			DEPLOY_ACCOUNT='cp-m041-staging'
			INBOX='/home/cp-m041-staging/inbox'
			TARGET_CONTAINER='erp-staging-dolibarr-1'
			TARGET_DB_CONTAINER='erp-staging-mariadb-1'
			TARGET_PROJECT='erp-staging'
			TARGET_DB_SERVICE='mariadb'
			TARGET_VOLUME='erp-staging_erp-staging_custom'
			TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
			TARGET_HTTP='http://127.0.0.1:18088'
			TARGET_MAILPIT_CONTAINER='erp-staging-mailpit-1'
			BACKUP_ROOT='/root/backup-erp-code-backups'
			DB_BACKUP_ROOT='/root/backup-erp-db-backups'
			STATE_ROOT='/var/lib/chinaplanetos-m041-staging'
			KEY_FILE="${INBOX}/m041-staging-deepseek.key"
			OVERRIDE_FILE='/etc/chinaplanetos/m041-staging-translation.override.yml'
			;;
		production-*)
			ENVIRONMENT='production'
			DEPLOY_ACCOUNT='cp-m041-production'
			INBOX='/home/cp-m041-production/inbox'
			TARGET_CONTAINER='vision_erp'
			TARGET_DB_CONTAINER='vision_db'
			TARGET_PROJECT='vision-os'
			TARGET_DB_SERVICE='mariadb'
			TARGET_VOLUME='vision-os_dolibarr_custom'
			TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
			TARGET_HTTP='https://erp.visiontrading.biz'
			BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
			DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
			STATE_ROOT='/var/lib/chinaplanetos-m041-production'
			KEY_FILE="${INBOX}/m041-production-deepseek.key"
			OVERRIDE_FILE='/etc/chinaplanetos/m041-production-translation.override.yml'
			;;
		*) ENVIRONMENT='unknown'; die 'ENVIRONMENT_COMMAND_INVALID' ;;
	esac
	STATE_FILE="${STATE_ROOT}/m041.state"
	PROVIDER_STATE_ROOT="${STATE_ROOT}/provider"
	LOCK_FILE="${STATE_ROOT}/m041.lock"
	ARTIFACT="${INBOX}/m041-${SHORT_COMMIT}.tar.gz"
	APPROVAL_MESSAGE="${INBOX}/m041-production-approval.message"
	APPROVAL_SIGNATURE="${INBOX}/m041-production-approval.signature"
}

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-m041-delivery' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m041-candidate-* ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m041-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
	if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m041-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
	if [[ $REMOVE_KEY_ON_EXIT -eq 1 && ( "$KEY_FILE" == '/home/cp-m041-staging/inbox/m041-staging-deepseek.key' || "$KEY_FILE" == '/home/cp-m041-production/inbox/m041-production-deepseek.key' ) ]]; then rm -f "$KEY_FILE"; fi
	if [[ $REMOVE_APPROVAL_ON_EXIT -eq 1 && "$ENVIRONMENT" == 'production' ]]; then rm -f "$APPROVAL_MESSAGE" "$APPROVAL_SIGNATURE"; fi
}

check_environment() {
	[[ "$ENVIRONMENT" == 'staging' || "$ENVIRONMENT" == 'production' ]] || die 'ENVIRONMENT_INVALID'
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'TARGET_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$TARGET_CONTAINER" 2>/dev/null) == "$TARGET_PROJECT" ]] || die 'TARGET_COMPOSE_PROJECT_MISMATCH'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == "$TARGET_SERVICE" ]] || die 'TARGET_COMPOSE_SERVICE_MISMATCH'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'TARGET_CUSTOM_MOUNT_MISMATCH'
	[[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'TARGET_VOLUME_IDENTITY_MISMATCH'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'TARGET_DB_NOT_RUNNING'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == "$TARGET_PROJECT" ]] || die 'TARGET_DB_PROJECT_MISMATCH'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == "$TARGET_DB_SERVICE" ]] || die 'TARGET_DB_SERVICE_MISMATCH'
	if [[ "$ENVIRONMENT" == 'staging' ]]; then
		[[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'STAGING_ERP_PORT_MISMATCH'
		[[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'STAGING_MAILPIT_IDENTITY_MISMATCH'
		[[ $(docker port "$TARGET_MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'STAGING_SMTP_PORT_MISMATCH'
		[[ $(docker port "$TARGET_MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'STAGING_MAILPIT_PORT_MISMATCH'
	fi
	[[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == "${DEPLOY_ACCOUNT}:${DEPLOY_ACCOUNT} 700" ]] || die 'INBOX_METADATA_INVALID'
	[[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'TARGET_DISK_LOW'
	[[ $(df -Pk "$INBOX" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'INBOX_DISK_LOW'
	docker exec "$TARGET_DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'TARGET_HTTP_UNHEALTHY'
	for root in "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"; do
		if [[ -e "$root" || -L "$root" ]]; then [[ -d "$root" && ! -L "$root" && $(stat -c '%U:%G %a' "$root") == 'root:root 700' ]] || die 'STATE_METADATA_INVALID'; fi
	done
}

ensure_state_dirs() { install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT" "$PROVIDER_STATE_ROOT" /etc/chinaplanetos; }

validate_manifest() {
	local manifest=$1 expected_sha=$2 expected_pattern=$3
	[[ -f "$CANDIDATE_ROOT/$manifest" && ! -L "$CANDIDATE_ROOT/$manifest" ]] || die 'MANIFEST_ABSENT'
	[[ $(sha256 "$CANDIDATE_ROOT/$manifest") == "$expected_sha" ]] || die 'MANIFEST_HASH_MISMATCH'
	[[ -n $(tail -c 1 "$CANDIDATE_ROOT/$manifest") ]] && die 'MANIFEST_FINAL_NEWLINE_MISSING'
	[[ -z $(LC_ALL=C sort "$CANDIDATE_ROOT/$manifest" | uniq -d) ]] || die 'MANIFEST_DUPLICATE_ENTRY'
	[[ -z $(awk -v pattern="$expected_pattern" '$0 !~ pattern {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_SCOPE_INVALID'
}

validate_artifact() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G %a' "$ARTIFACT") == "${DEPLOY_ACCOUNT}:${DEPLOY_ACCOUNT} 644" ]] || die 'ARTIFACT_METADATA_INVALID'
	ARTIFACT_COPY=$(mktemp /root/m041-artifact.XXXXXX)
	install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
	[[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
	local listing expected actual tar_stderr
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp); tar_stderr=$(mktemp)
	if ! tar -tzf "$ARTIFACT_COPY" >"$listing" 2>"$tar_stderr"; then die 'ARTIFACT_TAR_INVALID'; fi
	[[ ! -s "$tar_stderr" ]] || die 'ARTIFACT_TAR_WARNING'
	[[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
	[[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
	[[ -z $(grep -E '(^|/)(\.env|conf\.php|\.DS_Store|[^/]*\.(pem|key|p12|dump|sql|log|bak|tmp))$' "$listing" || true) ]] || die 'ARTIFACT_FORBIDDEN_FILE'
	[[ -z $(tar -tvzf "$ARTIFACT_COPY" | awk 'substr($1,1,1)!="-" && substr($1,1,1)!="d" {print; exit}') ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
	CANDIDATE_ROOT=$(mktemp -d /root/m041-candidate.XXXXXX)
	tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" || die 'ARTIFACT_EXTRACT_FAILED'
	[[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
	[[ -z $(find "$CANDIDATE_ROOT" ! -type d ! -type f -print -quit) ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
	validate_manifest "$RUNTIME_MANIFEST" "$RUNTIME_MANIFEST_SHA" '^custom/chinaplanetshippingprices/'
	validate_manifest "$SUPPORT_MANIFEST" "$SUPPORT_MANIFEST_SHA" '^deploy/(migrations|tests)/'
	mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
	mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
	printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	local file
	for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die 'ARTIFACT_FILE_INVALID'; done
	rm -f "$listing" "$expected" "$actual" "$tar_stderr"
}

candidate_checks() {
	CANDIDATE_REMOTE="/tmp/m041-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
		if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die 'CANDIDATE_LINT_FAILED'; fi
	done
	for file in "${STATIC_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die 'CANDIDATE_STATIC_TEST_FAILED'; done
}

schema_state() {
	local output rc=0
	set +e; output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" check 2>&1); rc=$?; set -e
	if [[ $rc -eq 0 && "$output" == 'M041_SCHEMA_CHECK=PASS' ]]; then echo 'PRESENT'; return; fi
	if [[ $rc -eq 2 && "$output" == 'M041_SCHEMA_CHECK=ABSENT' ]]; then echo 'ABSENT'; return; fi
	die 'SCHEMA_CHECK_DRIFT_OR_FAILURE'
}

capture_schema_prestate() {
	local output
	output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" snapshot) || die 'SCHEMA_SNAPSHOT_FAILED'
	[[ "$output" =~ ^M041_SCHEMA_SNAPSHOT=([A-Za-z0-9+/=]+)$ ]] || die 'SCHEMA_SNAPSHOT_INVALID'
	SCHEMA_PRESTATE_B64="${BASH_REMATCH[1]}"
	SCHEMA_PRESTATE_SHA=$(value_sha256 "$SCHEMA_PRESTATE_B64")
}

dump_database_to() {
	local destination=$1
	docker exec "$TARGET_DB_CONTAINER" sh -eu -c '
		db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
		user=${MARIADB_USER:-${MYSQL_USER:-root}}
		pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
		[ -n "$db" ] && [ -n "$pass" ]
		if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
		MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
	' >"$destination" || die 'DATABASE_BACKUP_FAILED'
	[[ -s "$destination" ]] || die 'DATABASE_BACKUP_EMPTY'
	chmod 0600 "$destination"
}

create_database_backup() {
	DB_BACKUP="${DB_BACKUP_ROOT}/m041-${ENVIRONMENT}-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
	dump_database_to "$DB_BACKUP"
	DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

restore_database_file() {
	local source=$1 expected_sha=$2
	[[ "$source" == "$DB_BACKUP_ROOT/"*.sql && -f "$source" && ! -L "$source" ]] || return 1
	[[ $(sha256 "$source") == "$expected_sha" ]] || return 1
	docker exec -i "$TARGET_DB_CONTAINER" sh -eu -c '
		db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
		user=${MARIADB_USER:-${MYSQL_USER:-root}}
		pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
		if command -v mariadb >/dev/null 2>&1; then client=mariadb; else client=mysql; fi
		MYSQL_PWD="$pass" exec "$client" -u "$user" "$db"
	' <"$source"
}

restore_database_backup() { restore_database_file "$DB_BACKUP" "$DB_BACKUP_SHA"; }

create_code_backup() {
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m041-${ENVIRONMENT}-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m041-${ENVIRONMENT}-absent-${SHORT_COMMIT}-${stamp}.txt"
	: >"$ABSENT_LIST"; local -a existing=()
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; existing+=("$rel"); else printf '%s\n' "$rel" >>"$ABSENT_LIST"; fi
	done
	if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"; CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}

restore_code_snapshot() {
	local archive=$1 archive_sha=$2 absent=$3 absent_sha=$4
	[[ -f "$archive" && $(sha256 "$archive") == "$archive_sha" ]] || return 1
	[[ -f "$absent" && $(sha256 "$absent") == "$absent_sha" ]] || return 1
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; [[ ! -d "$target" ]] || return 1; rm -f "$target"; done
	[[ $(tar -tzf "$archive" | wc -l) -eq 0 ]] || tar -xzf "$archive" --same-owner --same-permissions -C "$TARGET_ROOT"
}

restore_code_backup() { restore_code_snapshot "$CODE_BACKUP" "$CODE_BACKUP_SHA" "$ABSENT_LIST" "$ABSENT_LIST_SHA"; }

verify_code_backup_restored() {
	[[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	[[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
	tar -dzf "$CODE_BACKUP" -C "$TARGET_ROOT" >/dev/null 2>&1 || return 1
	local rel
	while IFS= read -r rel; do [[ -z "$rel" || ( ! -e "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ) ]] || return 1; done <"$ABSENT_LIST"
}

apply_files() {
	local file rel src target parent uid gid mode tmp
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
		[[ -d "$parent" && ! -L "$parent" ]] || die 'TARGET_PARENT_INVALID'
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target"); else uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode='644'; fi
		tmp="${target}.m041.$$"; install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"; mv -f "$tmp" "$target"
	done
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M041_${ENVIRONMENT^^}_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die 'TARGET_FILE_INVALID'; target_hash=$(sha256 "$target")
		if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M041_${ENVIRONMENT^^}_COMPARE FILE=$file STATE=$state TARGET_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

runtime_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" && $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1; done
	for file in "${RUNTIME_FILES[@]}"; do if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; fi; done
	[[ $(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" check) == 'M041_SCHEMA_CHECK=PASS' ]] || return 1
	for file in "${RUNTIME_TESTS[@]}"; do
		if [[ "$file" == *test_m035_customer_shipping_booking.php ]]; then
			docker exec -e M035_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || return 1
		else
			docker exec -e M041_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || return 1
		fi
	done
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]]
}

save_state() {
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_ENVIRONMENT=$ENVIRONMENT
STATE_STATUS=APPLIED
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_SCHEMA_PRESTATE_B64=$SCHEMA_PRESTATE_B64
STATE_SCHEMA_PRESTATE_SHA=$SCHEMA_PRESTATE_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die 'STATE_ABSENT_OR_INVALID'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_ENVIRONMENT" == "$ENVIRONMENT" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
	DB_BACKUP=$STATE_DB_BACKUP; DB_BACKUP_SHA=$STATE_DB_BACKUP_SHA; SCHEMA_PRESTATE_B64=$STATE_SCHEMA_PRESTATE_B64; SCHEMA_PRESTATE_SHA=$STATE_SCHEMA_PRESTATE_SHA
}

record_verified_state() {
	load_state
	local tmp="${STATE_FILE}.tmp.$$"
	grep -Ev '^STATE_(STATUS|VERIFIED_AT)=' "$STATE_FILE" >"$tmp"
	printf 'STATE_STATUS=VERIFIED\nSTATE_VERIFIED_AT=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >>"$tmp"
	chmod 0600 "$tmp"; chown root:root "$tmp"; mv -f "$tmp" "$STATE_FILE"
}

require_staging_verified() {
	local staging_state='/var/lib/chinaplanetos-m041-staging/m041.state'
	[[ -f "$staging_state" && ! -L "$staging_state" && $(stat -c '%U:%G %a' "$staging_state") == 'root:root 600' ]] || die 'STAGING_VERIFIED_STATE_ABSENT'
	grep -Fxq 'STATE_RELEASE=M041' "$staging_state" || die 'STAGING_RELEASE_MISMATCH'
	grep -Fxq 'STATE_ENVIRONMENT=staging' "$staging_state" || die 'STAGING_ENVIRONMENT_MISMATCH'
	grep -Fxq 'STATE_STATUS=VERIFIED' "$staging_state" || die 'STAGING_NOT_VERIFIED'
	grep -Fxq "STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT" "$staging_state" || die 'STAGING_COMMIT_MISMATCH'
	grep -Fxq "STATE_ARTIFACT_SHA=$ARTIFACT_SHA" "$staging_state" || die 'STAGING_ARTIFACT_MISMATCH'
	grep -Eq '^STATE_VERIFIED_AT=[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$' "$staging_state" || die 'STAGING_VERIFIED_TIME_INVALID'
}

require_production_approval() {
	local expected_command=$1 now expires
	[[ "$ENVIRONMENT" == 'production' ]] || return 0
	REMOVE_APPROVAL_ON_EXIT=1
	require_staging_verified
	[[ -f "$APPROVAL_PUBLIC_KEY" && ! -L "$APPROVAL_PUBLIC_KEY" && $(stat -c '%U:%G %a' "$APPROVAL_PUBLIC_KEY") == 'root:root 644' ]] || die 'APPROVAL_PUBLIC_KEY_INVALID'
	[[ $(sha256 "$APPROVAL_PUBLIC_KEY") == "$APPROVAL_PUBLIC_KEY_SHA" ]] || die 'APPROVAL_PUBLIC_KEY_HASH_MISMATCH'
	for file in "$APPROVAL_MESSAGE" "$APPROVAL_SIGNATURE"; do
		[[ -f "$file" && ! -L "$file" && $(stat -c '%U:%G %a' "$file") == 'cp-m041-production:cp-m041-production 600' ]] || die 'PRODUCTION_APPROVAL_FILE_INVALID'
	done
	[[ $(stat -c '%s' "$APPROVAL_MESSAGE") -le 512 && $(stat -c '%s' "$APPROVAL_SIGNATURE") -eq 64 ]] || die 'PRODUCTION_APPROVAL_SIZE_INVALID'
	local -a approval=(); mapfile -t approval <"$APPROVAL_MESSAGE"
	[[ ${#approval[@]} -eq 6 ]] || die 'PRODUCTION_APPROVAL_FORMAT_INVALID'
	[[ "${approval[0]}" == 'M041' && "${approval[1]}" == "$expected_command" && "${approval[2]}" == "$RUNTIME_COMMIT" && "${approval[3]}" == "$ARTIFACT_SHA" ]] || die 'PRODUCTION_APPROVAL_SCOPE_MISMATCH'
	[[ "${approval[4]}" =~ ^[0-9]{1,20}$ && "${approval[5]}" =~ ^[0-9]{10}$ ]] || die 'PRODUCTION_APPROVAL_METADATA_INVALID'
	now=$(date -u '+%s'); expires=${approval[5]}
	[[ $expires -ge $now && $expires -le $((now + 900)) ]] || die 'PRODUCTION_APPROVAL_EXPIRED_OR_TOO_LONG'
	openssl pkeyutl -verify -pubin -inkey "$APPROVAL_PUBLIC_KEY" -rawin -in "$APPROVAL_MESSAGE" -sigfile "$APPROVAL_SIGNATURE" >/dev/null 2>&1 || die 'PRODUCTION_APPROVAL_SIGNATURE_INVALID'
}

provider_status() {
	docker exec "$TARGET_CONTAINER" sh -eu -c '
		[ "$CHINAPLANETSP_TRANSLATION_ENDPOINT" = "https://api.deepseek.com/chat/completions" ]
		[ "$CHINAPLANETSP_TRANSLATION_MODEL" = "deepseek-v4-flash" ]
		[ -n "$CHINAPLANETSP_TRANSLATION_API_KEY" ]
	' >/dev/null 2>&1 || return 1
	echo "M041_${ENVIRONMENT^^}_PROVIDER_STATUS=CONFIGURED PROVIDER=api.deepseek.com MODEL=deepseek-v4-flash KEY=REDACTED"
}

recreate_target_service() {
	local override_present=$1 working_dir config_files project service config resolved
	working_dir=$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project.working_dir"}}' "$TARGET_CONTAINER")
	config_files=$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project.config_files"}}' "$TARGET_CONTAINER")
	project=$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$TARGET_CONTAINER")
	service=$(docker inspect -f '{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER")
	[[ "$project" == "$TARGET_PROJECT" && "$service" == "$TARGET_SERVICE" ]] || die 'PROVIDER_COMPOSE_IDENTITY_MISMATCH'
	[[ "$working_dir" == /* && -d "$working_dir" && ! -L "$working_dir" && -n "$config_files" ]] || die 'PROVIDER_COMPOSE_METADATA_INVALID'
	local -a compose_args=() configs=(); IFS=',' read -r -a configs <<<"$config_files"
	for config in "${configs[@]}"; do
		if [[ "$config" == /* ]]; then resolved=$config; else resolved="$working_dir/$config"; fi
		if [[ "$resolved" == "$OVERRIDE_FILE" ]]; then continue; fi
		[[ -f "$resolved" && ! -L "$resolved" ]] || die 'PROVIDER_COMPOSE_FILE_INVALID'
		compose_args+=(-f "$resolved")
	done
	[[ ${#compose_args[@]} -gt 0 ]] || die 'PROVIDER_BASE_COMPOSE_FILES_ABSENT'
	if [[ "$override_present" == 'PRESENT' ]]; then
		[[ -f "$OVERRIDE_FILE" && ! -L "$OVERRIDE_FILE" && $(stat -c '%U:%G %a' "$OVERRIDE_FILE") == 'root:root 600' ]] || die 'PROVIDER_OVERRIDE_INVALID'
		compose_args+=(-f "$OVERRIDE_FILE")
	elif [[ "$override_present" != 'ABSENT' ]]; then
		die 'PROVIDER_SNAPSHOT_STATUS_INVALID'
	fi
	(
		cd "$working_dir"
		docker compose --project-name "$TARGET_PROJECT" "${compose_args[@]}" up -d --no-deps "$TARGET_SERVICE"
	) >/dev/null || die 'PROVIDER_CONTAINER_RECREATE_FAILED'
	for _ in $(seq 1 30); do [[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] && break; sleep 1; done
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PROVIDER_CONTAINER_NOT_RUNNING'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'PROVIDER_HTTP_VERIFY_FAILED'
}

restore_provider_snapshot() {
	local status=$1 backup=$2 expected_sha=$3 mode=$4
	if [[ "$status" == 'PRESENT' ]]; then
		[[ -f "$backup" && ! -L "$backup" && $(sha256 "$backup") == "$expected_sha" && "$mode" =~ ^[0-7]{3,4}$ ]] || return 1
		install -o root -g root -m "$mode" "$backup" "${OVERRIDE_FILE}.restore.$$"
		mv -f "${OVERRIDE_FILE}.restore.$$" "$OVERRIDE_FILE"
	elif [[ "$status" == 'ABSENT' ]]; then
		rm -f "$OVERRIDE_FILE"
	else
		return 1
	fi
	recreate_target_service "$status"
}

persist_provider_prestate() {
	local status=$1 backup=$2 expected_sha=$3 mode=$4
	local status_file="${PROVIDER_STATE_ROOT}/prestate.status"
	if [[ -e "$status_file" || -L "$status_file" ]]; then
		[[ -f "$status_file" && ! -L "$status_file" && $(stat -c '%U:%G %a' "$status_file") == 'root:root 600' ]] || die 'PROVIDER_PRESTATE_EXISTING_INVALID'
		return 0
	fi
	if [[ "$status" == 'PRESENT' ]]; then
		install -o root -g root -m 0600 "$backup" "${PROVIDER_STATE_ROOT}/prestate.override.yml"
		printf '%s\n' "$expected_sha" >"${PROVIDER_STATE_ROOT}/prestate.sha"
		printf '%s\n' "$mode" >"${PROVIDER_STATE_ROOT}/prestate.mode"
		chmod 0600 "${PROVIDER_STATE_ROOT}/prestate.sha" "${PROVIDER_STATE_ROOT}/prestate.mode"
	fi
	printf '%s\n' "$status" >"${status_file}.tmp.$$"; chmod 0600 "${status_file}.tmp.$$"; chown root:root "${status_file}.tmp.$$"; mv -f "${status_file}.tmp.$$" "$status_file"
}

restore_saved_provider_prestate() {
	local status_file="${PROVIDER_STATE_ROOT}/prestate.status" status backup='' expected_sha='' mode='600'
	[[ -f "$status_file" && ! -L "$status_file" && $(stat -c '%U:%G %a' "$status_file") == 'root:root 600' ]] || return 0
	status=$(<"$status_file")
	if [[ "$status" == 'PRESENT' ]]; then
		backup="${PROVIDER_STATE_ROOT}/prestate.override.yml"
		[[ -f "${PROVIDER_STATE_ROOT}/prestate.sha" && -f "${PROVIDER_STATE_ROOT}/prestate.mode" ]] || return 1
		expected_sha=$(<"${PROVIDER_STATE_ROOT}/prestate.sha"); mode=$(<"${PROVIDER_STATE_ROOT}/prestate.mode")
	fi
	restore_provider_snapshot "$status" "$backup" "$expected_sha" "$mode"
}

clear_saved_provider_prestate() {
	rm -f "${PROVIDER_STATE_ROOT}/prestate.status" "${PROVIDER_STATE_ROOT}/prestate.override.yml" "${PROVIDER_STATE_ROOT}/prestate.sha" "${PROVIDER_STATE_ROOT}/prestate.mode"
}

configure_provider() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks
	require_production_approval "${ENVIRONMENT}-configure-provider"
	REMOVE_KEY_ON_EXIT=1
	command -v docker >/dev/null && docker compose version >/dev/null 2>&1 || die 'DOCKER_COMPOSE_PLUGIN_ABSENT'
	[[ -f "$KEY_FILE" && ! -L "$KEY_FILE" && $(stat -c '%U:%G %a' "$KEY_FILE") == "${DEPLOY_ACCOUNT}:${DEPLOY_ACCOUNT} 600" ]] || die 'PROVIDER_KEY_FILE_INVALID'
	[[ $(wc -l <"$KEY_FILE") -eq 0 ]] || die 'PROVIDER_KEY_MULTILINE_FORBIDDEN'
	local key; key=$(tr -d '\r\n' <"$KEY_FILE")
	[[ "$key" =~ ^sk-[A-Za-z0-9_-]{12,500}$ ]] || die 'PROVIDER_KEY_FORMAT_INVALID'
	local previous_status='ABSENT' previous_backup='' previous_sha='' previous_mode='600' mutated=0
	if [[ -e "$OVERRIDE_FILE" || -L "$OVERRIDE_FILE" ]]; then
		[[ -f "$OVERRIDE_FILE" && ! -L "$OVERRIDE_FILE" && $(stat -c '%U:%G' "$OVERRIDE_FILE") == 'root:root' ]] || die 'PROVIDER_EXISTING_OVERRIDE_INVALID'
		previous_status='PRESENT'; previous_backup=$(mktemp "${PROVIDER_STATE_ROOT}/immediate.XXXXXX.yml")
		install -o root -g root -m 0600 "$OVERRIDE_FILE" "$previous_backup"
		previous_sha=$(sha256 "$previous_backup"); previous_mode=$(stat -c '%a' "$OVERRIDE_FILE")
	fi
	local override_tmp="${OVERRIDE_FILE}.tmp.$$"
	trap 'rc=$?; trap - ERR; if [[ $mutated -eq 1 ]]; then restore_provider_snapshot "$previous_status" "$previous_backup" "$previous_sha" "$previous_mode" && echo "M041_PROVIDER_AUTO_ROLLBACK=PASS" >&2 || echo "M041_PROVIDER_AUTO_ROLLBACK=FAIL" >&2; fi; rm -f "$override_tmp"; if [[ -n "$previous_backup" && -f "$previous_backup" ]]; then rm -f "$previous_backup"; fi; exit $rc' ERR
	cat >"$override_tmp" <<-EOF
services:
  ${TARGET_SERVICE}:
    environment:
      CHINAPLANETSP_TRANSLATION_ENDPOINT: "${PROVIDER_ENDPOINT}"
      CHINAPLANETSP_TRANSLATION_MODEL: "${PROVIDER_MODEL}"
      CHINAPLANETSP_TRANSLATION_API_KEY: "${key}"
	EOF
	mutated=1
	chmod 0600 "$override_tmp"; chown root:root "$override_tmp"; mv -f "$override_tmp" "$OVERRIDE_FILE"
	recreate_target_service 'PRESENT'
	provider_status >/dev/null || die 'PROVIDER_ENV_VERIFY_FAILED'
	candidate_checks
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$PROVIDER_TEST" >/dev/null || die 'PROVIDER_ACCEPTANCE_FAILED'
	persist_provider_prestate "$previous_status" "$previous_backup" "$previous_sha" "$previous_mode"
	mutated=0; trap - ERR
	[[ -z "$previous_backup" || ! -f "$previous_backup" ]] || rm -f "$previous_backup"
	echo "M041_${ENVIRONMENT^^}_PROVIDER_CONFIGURE=PASS PROVIDER=api.deepseek.com MODEL=deepseek-v4-flash KEY=REDACTED SERVICE_RECREATED=$TARGET_SERVICE"
}

command_compare() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M041_${ENVIRONMENT^^}_SCHEMA_STATE=$(schema_state)"; echo "M041_${ENVIRONMENT^^}_COMPARE=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; schema_state >/dev/null; echo "M041_${ENVIRONMENT^^}_PREFLIGHT=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]}"; }
command_apply() {
	check_environment; ensure_state_dirs; require_production_approval "${ENVIRONMENT}-apply"; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks; schema_state >/dev/null
	if [[ -e "$STATE_FILE" || -L "$STATE_FILE" ]]; then
		load_state
		case "${STATE_STATUS:-}" in
			APPLIED|VERIFIED)
				runtime_checks || die 'EXISTING_APPLY_STATE_RUNTIME_DRIFT'
				echo "M041_${ENVIRONMENT^^}_APPLY=NOOP_ALREADY_APPLIED COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA ORIGINAL_BACKUPS_PRESERVED=YES"
				return 0
				;;
			ROLLED_BACK) ;;
			*) die 'EXISTING_APPLY_STATE_INVALID' ;;
		esac
	fi
	capture_schema_prestate; create_code_backup; create_database_backup
	local mutated=0
	trap 'rc=$?; trap - ERR; if [[ $mutated -eq 1 ]]; then restore_code_backup && restore_database_backup && verify_code_backup_restored && restore_saved_provider_prestate && clear_saved_provider_prestate && echo "M041_AUTO_ROLLBACK=PASS" >&2 || echo "M041_AUTO_ROLLBACK=FAIL" >&2; fi; exit $rc' ERR
	mutated=1
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" apply >/dev/null || die 'SCHEMA_APPLY_FAILED'
	apply_files; runtime_checks || die 'RUNTIME_CHECK_FAILED'
	mutated=0; trap - ERR; save_state
	echo "M041_${ENVIRONMENT^^}_APPLY=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA"
}
command_verify() {
	check_environment; validate_artifact; candidate_checks; runtime_checks || die 'VERIFY_FAILED'; provider_status >/dev/null || die 'PROVIDER_NOT_CONFIGURED'
	if [[ "$ENVIRONMENT" == 'staging' ]]; then record_verified_state; else load_state; [[ "${STATE_STATUS:-}" == 'APPLIED' ]] || die 'PRODUCTION_APPLY_STATE_INVALID'; fi
	echo "M041_${ENVIRONMENT^^}_VERIFY=PASS COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"
}
command_rollback() {
	check_environment; ensure_state_dirs; require_production_approval "${ENVIRONMENT}-rollback"; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks; load_state
	local stamp reapply reapply_sha reapply_absent reapply_absent_sha reapply_db reapply_db_sha
	local provider_reapply_status='ABSENT' provider_reapply='' provider_reapply_sha='' provider_reapply_mode='600' schema_result=''
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	reapply="${BACKUP_ROOT}/m041-${ENVIRONMENT}-reapply-${SHORT_COMMIT}-${stamp}.tar.gz"
	reapply_absent="${BACKUP_ROOT}/m041-${ENVIRONMENT}-reapply-absent-${SHORT_COMMIT}-${stamp}.txt"
	reapply_db="${DB_BACKUP_ROOT}/m041-${ENVIRONMENT}-reapply-${SHORT_COMMIT}-${stamp}.sql"
	: >"$reapply_absent"; local -a current=(); local file rel
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}
		if [[ -e "$TARGET_ROOT/$rel" || -L "$TARGET_ROOT/$rel" ]]; then [[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ]] || die 'ROLLBACK_CURRENT_FILE_INVALID'; current+=("$rel"); else printf '%s\n' "$rel" >>"$reapply_absent"; fi
	done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply" -C "$TARGET_ROOT" "${current[@]}"; else tar -czf "$reapply" --files-from /dev/null; fi
	chmod 0600 "$reapply" "$reapply_absent"; reapply_sha=$(sha256 "$reapply"); reapply_absent_sha=$(sha256 "$reapply_absent")
	dump_database_to "$reapply_db"; reapply_db_sha=$(sha256 "$reapply_db")
	if [[ -e "$OVERRIDE_FILE" || -L "$OVERRIDE_FILE" ]]; then
		[[ -f "$OVERRIDE_FILE" && ! -L "$OVERRIDE_FILE" && $(stat -c '%U:%G' "$OVERRIDE_FILE") == 'root:root' ]] || die 'ROLLBACK_PROVIDER_CURRENT_INVALID'
		provider_reapply_status='PRESENT'; provider_reapply="${PROVIDER_STATE_ROOT}/reapply-${stamp}.yml"; provider_reapply_mode=$(stat -c '%a' "$OVERRIDE_FILE")
		install -o root -g root -m 0600 "$OVERRIDE_FILE" "$provider_reapply"; provider_reapply_sha=$(sha256 "$provider_reapply")
	fi
	local mutated=0
	trap 'rc=$?; trap - ERR; if [[ $mutated -eq 1 ]]; then restore_code_snapshot "$reapply" "$reapply_sha" "$reapply_absent" "$reapply_absent_sha" && restore_database_file "$reapply_db" "$reapply_db_sha" && restore_provider_snapshot "$provider_reapply_status" "$provider_reapply" "$provider_reapply_sha" "$provider_reapply_mode" && echo "M041_ROLLBACK_REAPPLY=PASS" >&2 || echo "M041_ROLLBACK_REAPPLY=FAIL" >&2; fi; exit $rc' ERR
	mutated=1
	restore_code_backup || die 'ROLLBACK_CODE_RESTORE_FAILED'
	verify_code_backup_restored || die 'ROLLBACK_CODE_VERIFY_FAILED'
	if [[ $(value_sha256 "$SCHEMA_PRESTATE_B64") == "$SCHEMA_PRESTATE_SHA" ]] && docker exec -e M041_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" rollback >/dev/null 2>&1; then
		docker exec -e M041_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" verify-prestate >/dev/null || die 'ROLLBACK_SCHEMA_VERIFY_FAILED'
		schema_result='RESTORED_PRESTATE'
	else
		restore_database_backup || die 'ROLLBACK_DATABASE_RESTORE_FAILED'
		docker exec -e M041_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" verify-prestate >/dev/null || die 'ROLLBACK_DATABASE_PRESTATE_VERIFY_FAILED'
		schema_result='RESTORED_DATABASE_BACKUP'
	fi
	restore_saved_provider_prestate || die 'ROLLBACK_PROVIDER_RESTORE_FAILED'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	local state_tmp="${STATE_FILE}.tmp.$$"; grep -Ev '^STATE_(STATUS|ROLLED_BACK_AT)=' "$STATE_FILE" >"$state_tmp"; printf 'STATE_STATUS=ROLLED_BACK\nSTATE_ROLLED_BACK_AT=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" >>"$state_tmp"; chmod 0600 "$state_tmp"; chown root:root "$state_tmp"; mv -f "$state_tmp" "$STATE_FILE"
	clear_saved_provider_prestate
	mutated=0; trap - ERR
	echo "M041_${ENVIRONMENT^^}_ROLLBACK=PASS CODE_RESTORED=$CODE_BACKUP SCHEMA=$schema_result PROVIDER=RESTORED_PRESTATE REAPPLY=$reapply REAPPLY_SHA=$reapply_sha REAPPLY_DB=$reapply_db REAPPLY_DB_SHA=$reapply_db_sha"
}
command_status() { if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|ENVIRONMENT|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|DB_BACKUP|DB_BACKUP_SHA|DEPLOYED_AT|VERIFIED_AT|ROLLED_BACK_AT)=' "$STATE_FILE"; else echo "M041_${ENVIRONMENT^^}_STATUS=NOT_DEPLOYED"; fi; }
command_provider_status() { check_environment; provider_status || die 'PROVIDER_NOT_CONFIGURED'; }
command_provider_test() { check_environment; provider_status >/dev/null || die 'PROVIDER_NOT_CONFIGURED'; validate_artifact; candidate_checks; docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$PROVIDER_TEST"; }

select_environment "${1:-}"
require_root "$@"
trap cleanup EXIT
case "$1" in
	staging-compare|production-compare) command_compare ;;
	staging-preflight|production-preflight) command_preflight ;;
	staging-apply|production-apply) command_apply ;;
	staging-verify|production-verify) command_verify ;;
	staging-rollback|production-rollback) command_rollback ;;
	staging-status|production-status) command_status ;;
	staging-configure-provider|production-configure-provider) configure_provider ;;
	staging-provider-status|production-provider-status) command_provider_status ;;
	staging-provider-test|production-provider-test) command_provider_test ;;
	*) die 'COMMAND_NOT_ALLOWED' ;;
esac
