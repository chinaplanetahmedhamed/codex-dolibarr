#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M037_FULL_CURRENT_LOCAL'
readonly RUNTIME_COMMIT='8be3edb644c5da4b03a80da589dff466904b8e64'
readonly SHORT_COMMIT='8be3edb'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m037-full-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='a60968c489b016e58e4543751d9942498d4dda467b3007ab22ee89ed00bfb80a'
readonly RUNTIME_MANIFEST='deploy/releases/m037-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m037-support-files.txt'
readonly RUNTIME_MANIFEST_SHA='f56c2924fa7490041700985523f628ea9dcd8fc7056c2a610960bb6ddf86f66a'
readonly SUPPORT_MANIFEST_SHA='9412603d715ba7e111218f4c9b5ae56b2bd771f8c14de5b9124e16ce136df512'

readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly TARGET_DB_CONTAINER='erp-staging-mariadb-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly DB_BACKUP_ROOT='/root/backup-erp-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-backup-erp-m037-deploy'
readonly STATE_FILE="${STATE_ROOT}/m037.state"
readonly LOCK_FILE="${STATE_ROOT}/m037.lock"
readonly SCHEMA='deploy/migrations/m035_customer_shipping_booking_schema.php'

readonly -a STATIC_TESTS=(
	'deploy/tests/test_admin_accessibility.php'
	'deploy/tests/test_m028_current_source_release.php'
	'deploy/tests/test_m029_core_customer_funds.php'
	'deploy/tests/test_m030_generic_labels_navigation.php'
	'deploy/tests/test_m031_customer_document_count.php'
	'deploy/tests/test_m032_admin_customer_responsive.php'
	'deploy/tests/test_m033_shipping_customer_center.php'
	'deploy/tests/test_m034_shipping_price_publication_state.php'
	'deploy/tests/test_m035_customer_shipping_booking_source.php'
	'deploy/tests/test_m036_shipping_orders_source.php'
)
readonly -a RUNTIME_TESTS=(
	'deploy/tests/test_m034_shipping_price_publication_runtime.php'
	'deploy/tests/test_m035_customer_shipping_booking.php'
	'deploy/tests/test_m036_shipping_orders_runtime.php'
)
readonly -a ROUTES=(
	'/'
	'/custom/chinaplanetcustomercenter/admin_dashboard.php'
	'/custom/chinaplanetcustomercenter/board.php'
	'/custom/chinaplanetcustomercenter/documents.php'
	'/custom/chinaplanetcustomercenter/admin/financial_portfolio.php'
	'/custom/chinaplanetlogistics/index.php'
	'/custom/chinaplanetoperations/operations/shipping_orders.php'
	'/custom/chinaplanetshippingprices/index.php'
)

RUNTIME_FILES=()
SUPPORT_FILES=()
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''
SCHEMA_PRESTATE_B64=''
SCHEMA_PRESTATE_SHA=''

die() { echo "M037_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }
value_sha256() { printf '%s' "$1" | sha256sum | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-backup-erp-m037-full-release-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'STAGING_ROOT_INVALID'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'STAGING_CUSTOM_MOUNT_MISMATCH'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_DB_NOT_RUNNING'
	docker exec "$TARGET_DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'STAGING_HTTP_UNHEALTHY'
}

ensure_state_dirs() { install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"; }

validate_manifest() {
	local manifest=$1 expected_sha=$2 prefix_pattern=$3
	[[ -f "$CANDIDATE_ROOT/$manifest" && ! -L "$CANDIDATE_ROOT/$manifest" ]] || die 'MANIFEST_INVALID'
	[[ $(sha256 "$CANDIDATE_ROOT/$manifest") == "$expected_sha" ]] || die 'MANIFEST_HASH_MISMATCH'
	[[ -z $(awk 'NF==0 || $0 ~ /^\\// || $0 ~ /(^|\\/)\\.\\.($|\\/)/ || $0 !~ /^[A-Za-z0-9_.\\/-]+$/ {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_ENTRY_INVALID'
	[[ -z $(LC_ALL=C sort "$CANDIDATE_ROOT/$manifest" | uniq -d) ]] || die 'MANIFEST_DUPLICATE_ENTRY'
	[[ -z $(awk -v pattern="$prefix_pattern" '$0 !~ pattern {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_SCOPE_INVALID'
}

validate_artifact() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
	[[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
	local listing expected actual
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
	tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
	[[ -z $(awk '/^\\// || /(^|\\/)\\.\\.($|\\/)/ || /\\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
	[[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
	[[ -z $(grep -E '(^|/)(\\.env|conf\\.php|\\.DS_Store|[^/]*\\.(pem|key|p12|dump|log|bak|tmp))$' "$listing" || true) ]] || die 'ARTIFACT_FORBIDDEN_FILE'
	CANDIDATE_ROOT=$(mktemp -d)
	tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
	[[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
	[[ -z $(find "$CANDIDATE_ROOT" ! -type d ! -type f -print -quit) ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
	validate_manifest "$RUNTIME_MANIFEST" "$RUNTIME_MANIFEST_SHA" '^custom/(chinaplanetcustomercenter|chinaplanetlogistics|chinaplanetoperations|chinaplanetshippingprices|cpkanban)/'
	validate_manifest "$SUPPORT_MANIFEST" "$SUPPORT_MANIFEST_SHA" '^deploy/(migrations|tests)/'
	mapfile -t RUNTIME_FILES <"$CANDIDATE_ROOT/$RUNTIME_MANIFEST"
	mapfile -t SUPPORT_FILES <"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
	printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	local file
	for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
		[[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die "ARTIFACT_FILE_INVALID_${file//\//_}"
	done
	rm -f "$listing" "$expected" "$actual"
}

candidate_checks() {
	CANDIDATE_REMOTE="/tmp/m037-staging-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
		if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"; fi
	done
	for file in "${STATIC_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_TEST_FAILED_${file//\//_}"; done
}

schema_state() {
	local rc=0
	if docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" check >/dev/null 2>&1; then echo 'PRESENT'; return 0; else rc=$?; fi
	[[ $rc -eq 2 ]] || die 'SCHEMA_CHECK_FAILED'
	echo 'ABSENT'
}

capture_schema_prestate() {
	local output
	output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" snapshot) || die 'SCHEMA_SNAPSHOT_FAILED'
	[[ "$output" =~ ^M035_SCHEMA_SNAPSHOT=([A-Za-z0-9+/=]+)$ ]] || die 'SCHEMA_SNAPSHOT_INVALID'
	SCHEMA_PRESTATE_B64="${BASH_REMATCH[1]}"
	SCHEMA_PRESTATE_SHA=$(value_sha256 "$SCHEMA_PRESTATE_B64")
}

create_database_backup() {
	DB_BACKUP="${DB_BACKUP_ROOT}/m037-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
	docker exec "$TARGET_DB_CONTAINER" sh -eu -c '
		db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
		user=${MARIADB_USER:-${MYSQL_USER:-root}}
		pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
		[ -n "$db" ] && [ -n "$pass" ]
		if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
		MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables -u "$user" "$db"
	' >"$DB_BACKUP" || die 'DATABASE_BACKUP_FAILED'
	[[ -s "$DB_BACKUP" ]] || die 'DATABASE_BACKUP_EMPTY'
	chmod 0600 "$DB_BACKUP"
	DB_BACKUP_SHA=$(sha256 "$DB_BACKUP")
}

create_code_backup() {
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m037-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m037-absent-before-${SHORT_COMMIT}-${stamp}.txt"
	: >"$ABSENT_LIST"
	local -a existing=()
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then
			[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
			existing+=("$rel")
		else
			printf '%s\n' "$rel" >>"$ABSENT_LIST"
		fi
	done
	if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"
	CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP")
	ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}

restore_code_backup() {
	[[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	[[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		[[ ! -d "$target" ]] || return 1
		rm -f "$target"
	done
	[[ $(tar -tzf "$CODE_BACKUP" | wc -l) -eq 0 ]] || tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT"
}

apply_files() {
	local file rel src target parent uid gid mode tmp
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
		[[ -d "$parent" && ! -L "$parent" ]] || die "TARGET_PARENT_INVALID_${file//\//_}"
		if [[ -e "$target" || -L "$target" ]]; then
			[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
			uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target")
		else
			uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode='644'
		fi
		tmp="${target}.m037.$$"
		install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"
		mv -f "$tmp" "$target"
	done
}

apply_database_changes() { docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" apply >/dev/null || die 'SCHEMA_APPLY_FAILED'; }

rollback_database_changes() {
	[[ -n "$SCHEMA_PRESTATE_B64" && $(value_sha256 "$SCHEMA_PRESTATE_B64") == "$SCHEMA_PRESTATE_SHA" ]] || return 1
	docker exec -e M035_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" rollback >/dev/null
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M037_STAGING_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		target_hash=$(sha256 "$target")
		if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M037_STAGING_COMPARE FILE=$file STATE=$state STAGING_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

route_checks() {
	local route status
	for route in "${ROUTES[@]}"; do
		status=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}${route}") || return 1
		[[ "$status" =~ ^(200|302|403)$ ]] || return 1
	done
}

runtime_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}
		[[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ]] || return 1
		[[ $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1
		if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; fi
	done
	[[ $(schema_state) == 'PRESENT' ]] || return 1
	docker exec -e M034_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/${RUNTIME_TESTS[0]}" >/dev/null || return 1
	docker exec -e M035_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/${RUNTIME_TESTS[1]}" >/dev/null || return 1
	docker exec -e M036_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/${RUNTIME_TESTS[2]}" >/dev/null || return 1
	route_checks
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
STATE_SCHEMA_PRESTATE_B64=$SCHEMA_PRESTATE_B64
STATE_SCHEMA_PRESTATE_SHA=$SCHEMA_PRESTATE_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA
	ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
	DB_BACKUP=$STATE_DB_BACKUP; DB_BACKUP_SHA=$STATE_DB_BACKUP_SHA
	SCHEMA_PRESTATE_B64=$STATE_SCHEMA_PRESTATE_B64; SCHEMA_PRESTATE_SHA=$STATE_SCHEMA_PRESTATE_SHA
}

automatic_rollback() {
	set +e
	rollback_database_changes
	local db_rc=$?
	restore_code_backup
	local code_rc=$?
	if [[ $db_rc -eq 0 ]]; then echo 'M037_STAGING_AUTO_DB_ROLLBACK=PASS' >&2; else echo 'M037_STAGING_AUTO_DB_ROLLBACK=FAIL' >&2; fi
	if [[ $code_rc -eq 0 ]]; then echo 'M037_STAGING_AUTO_CODE_ROLLBACK=PASS' >&2; else echo 'M037_STAGING_AUTO_CODE_ROLLBACK=FAIL' >&2; fi
	set -e
}

command_compare() {
	check_environment; validate_artifact; candidate_checks; compare_files
	echo "M037_STAGING_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$(schema_state)"
}

command_preflight() {
	check_environment; validate_artifact; candidate_checks; capture_schema_prestate
	echo "M037_STAGING_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA RUNTIME_FILES=${#RUNTIME_FILES[@]} SUPPORT_FILES=${#SUPPORT_FILES[@]} SCHEMA=$(schema_state) SCHEMA_PRESTATE_SHA=$SCHEMA_PRESTATE_SHA"
}

command_apply() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks; capture_schema_prestate; create_code_backup; create_database_backup
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then automatic_rollback; fi; exit $rc' ERR
	mutated=1
	apply_database_changes
	apply_files
	runtime_checks
	mutated=0
	trap - ERR
	save_state
	echo "M037_STAGING_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA SCHEMA_PRESTATE_SHA=$SCHEMA_PRESTATE_SHA"
}

command_verify() {
	check_environment; validate_artifact; candidate_checks
	runtime_checks || die 'RUNTIME_VERIFY_FAILED'
	echo "M037_STAGING_VERIFY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"
}

command_rollback() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks; load_state
	local stamp reapply_code reapply_db file rel
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	reapply_code="${BACKUP_ROOT}/m037-reapply-${SHORT_COMMIT}-${stamp}.tar.gz"
	local -a current=()
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" ]] && current+=("$rel"); done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply_code" -C "$TARGET_ROOT" "${current[@]}"; else tar -czf "$reapply_code" --files-from /dev/null; fi
	chmod 0600 "$reapply_code"
	create_database_backup
	reapply_db=$DB_BACKUP
	restore_code_backup || die 'ROLLBACK_CODE_RESTORE_FAILED'
	rollback_database_changes || die 'ROLLBACK_SCHEMA_RESTORE_FAILED'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M037_STAGING_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY_CODE=$reapply_code REAPPLY_CODE_SHA=$(sha256 "$reapply_code") REAPPLY_DB=$reapply_db REAPPLY_DB_SHA=$DB_BACKUP_SHA"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then
		grep -E '^STATE_(RELEASE|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE_SHA|DEPLOYED_AT)=' "$STATE_FILE"
	else
		echo 'M037_STAGING_STATUS=NOT_DEPLOYED'
	fi
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
