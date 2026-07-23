#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M037_FULL_CURRENT_LOCAL'
readonly RUNTIME_COMMIT='cb9b2629dcb52ef3e97a82b8a7e3282289f86b18'
readonly SHORT_COMMIT='cb9b262'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m037-full-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='8c23d76f94abbe274b84c042a350451339f55f0b06616266359aa0f4c3bef33f'
readonly RUNTIME_MANIFEST='deploy/releases/m037-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m037-support-files.txt'
readonly RUNTIME_MANIFEST_SHA='f56c2924fa7490041700985523f628ea9dcd8fc7056c2a610960bb6ddf86f66a'
readonly SUPPORT_MANIFEST_SHA='69a19adb5d57c87b241ced78a9d3e128779a89de71e151d019a1b12cdcd79077'

readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly TARGET_DB_CONTAINER='erp-staging-mariadb-1'
readonly TARGET_MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_COMPOSE_PROJECT='erp-staging'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly DB_BACKUP_ROOT='/root/backup-erp-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-backup-erp-m037-deploy'
readonly STATE_FILE="${STATE_ROOT}/m037.state"
readonly LOCK_FILE="${STATE_ROOT}/m037.lock"
readonly SCHEMA='deploy/migrations/m035_customer_shipping_booking_schema.php'
readonly MIN_FREE_KB=1048576

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
	'deploy/tests/test_unified_admin_shell.php'
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
	'/custom/chinaplanetcustomercenter/operation_reports.php'
	'/custom/chinaplanetcustomercenter/projects.php'
	'/custom/chinaplanetcustomercenter/orders.php'
	'/custom/chinaplanetcustomercenter/balance.php'
	'/custom/chinaplanetcustomercenter/admin/financial_portfolio.php'
	'/custom/chinaplanetcustomercenter/admin/operations.php'
	'/custom/chinaplanetlogistics/index.php'
	'/custom/chinaplanetlogistics/list.php'
	'/custom/chinaplanetlogistics/reports.php'
	'/custom/chinaplanetoperations/operations/shipping_orders.php'
	'/custom/chinaplanetshippingprices/sop/index.php'
	'/custom/chinaplanetshippingprices/index.php'
)

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
	if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /root/m037-candidate.* && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
	if [[ -n "$ARTIFACT_COPY" && "$ARTIFACT_COPY" == /root/m037-artifact.* && -f "$ARTIFACT_COPY" ]]; then rm -f "$ARTIFACT_COPY"; fi
}

check_environment() {
	local target_mode target_owner service_owner
	[[ "$TARGET_CONTAINER" == 'erp-staging-dolibarr-1' && "$TARGET_DB_CONTAINER" == 'erp-staging-mariadb-1' && "$TARGET_MAILPIT_CONTAINER" == 'erp-staging-mailpit-1' && "$TARGET_VOLUME" == 'erp-staging_erp-staging_custom' ]] || die 'STAGING_TARGET_CONSTANT_MISMATCH'
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'STAGING_ROOT_INVALID'
	target_owner=$(stat -c '%u:%g' "$TARGET_ROOT")
	service_owner=$(docker exec "$TARGET_CONTAINER" sh -c 'printf "%s:%s" "$(id -u www-data)" "$(id -g www-data)"')
	[[ "$target_owner" == '0:0' || "$target_owner" == "$service_owner" ]] || die 'STAGING_ROOT_OWNER_INVALID'
	[[ $(docker exec "$TARGET_CONTAINER" stat -c '%u:%g' /var/www/html/custom) == "$target_owner" ]] || die 'STAGING_ROOT_CONTAINER_OWNER_MISMATCH'
	target_mode=$(stat -c '%a' "$TARGET_ROOT")
	(( (8#$target_mode & 8#022) == 0 )) || die 'STAGING_ROOT_MODE_UNSAFE'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$TARGET_CONTAINER" 2>/dev/null) == "$TARGET_COMPOSE_PROJECT" ]] || die 'STAGING_COMPOSE_PROJECT_MISMATCH'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'STAGING_CUSTOM_MOUNT_MISMATCH'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_DB_NOT_RUNNING'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == "$TARGET_COMPOSE_PROJECT" ]] || die 'STAGING_DB_COMPOSE_PROJECT_MISMATCH'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_MAILPIT_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_MAILPIT_NOT_RUNNING'
	[[ $(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$TARGET_MAILPIT_CONTAINER" 2>/dev/null) == "$TARGET_COMPOSE_PROJECT" ]] || die 'STAGING_MAILPIT_COMPOSE_PROJECT_MISMATCH'
	[[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'STAGING_ERP_PORT_MISMATCH'
	[[ $(docker port "$TARGET_MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'STAGING_SMTP_PORT_MISMATCH'
	[[ $(docker port "$TARGET_MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'STAGING_MAILPIT_PORT_MISMATCH'
	[[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'STAGING_INBOX_METADATA_INVALID'
	[[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'STAGING_TARGET_DISK_LOW'
	[[ $(df -Pk "$INBOX" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'STAGING_INBOX_DISK_LOW'
	for root in "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"; do
		if [[ -e "$root" || -L "$root" ]]; then
			[[ -d "$root" && ! -L "$root" && $(stat -c '%U:%G %a' "$root") == 'root:root 700' ]] || die 'STAGING_STATE_METADATA_INVALID'
		fi
	done
	docker exec "$TARGET_DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'STAGING_HTTP_UNHEALTHY'
}

ensure_state_dirs() { install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"; }

validate_manifest() {
	local manifest=$1 expected_sha=$2 prefix_pattern=$3
	[[ -f "$CANDIDATE_ROOT/$manifest" && ! -L "$CANDIDATE_ROOT/$manifest" ]] || die 'MANIFEST_INVALID'
	[[ $(sha256 "$CANDIDATE_ROOT/$manifest") == "$expected_sha" ]] || die 'MANIFEST_HASH_MISMATCH'
	[[ -z $(awk 'NF==0 || $0 ~ /^\// || $0 ~ /(^|\/)\.\.($|\/)/ || $0 !~ /^[A-Za-z0-9_./-]+$/ {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_ENTRY_INVALID'
	[[ -z $(LC_ALL=C sort "$CANDIDATE_ROOT/$manifest" | uniq -d) ]] || die 'MANIFEST_DUPLICATE_ENTRY'
	[[ -z $(awk -v pattern="$prefix_pattern" '$0 !~ pattern {print; exit}' "$CANDIDATE_ROOT/$manifest") ]] || die 'MANIFEST_SCOPE_INVALID'
}

validate_artifact() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
	ARTIFACT_COPY=$(mktemp /root/m037-artifact.XXXXXX)
	install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
	[[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
	local listing expected actual
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
	tar -tzf "$ARTIFACT_COPY" >"$listing" || die 'ARTIFACT_TAR_INVALID'
	[[ -z $(tar -tvzf "$ARTIFACT_COPY" | awk 'substr($1,1,1)!="-" {print; exit}') ]] || die 'ARTIFACT_TYPE_FORBIDDEN'
	[[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
	[[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
	[[ -z $(grep -E '(^|/)(\.env|conf\.php|\.DS_Store|[^/]*\.(pem|key|p12|dump|log|bak|tmp))$' "$listing" || true) ]] || die 'ARTIFACT_FORBIDDEN_FILE'
	CANDIDATE_ROOT=$(mktemp -d /root/m037-candidate.XXXXXX)
	tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
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
	docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
	docker exec "$TARGET_CONTAINER" cp -a /var/www/html/custom "$CANDIDATE_REMOTE/custom"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}"; do
		if [[ "$file" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"; fi
	done
	for file in "${STATIC_TESTS[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_TEST_FAILED_${file//\//_}"; done
}

schema_state() {
	local rc=0 output
	set +e
	output=$(docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" check 2>&1)
	rc=$?
	set -e
	if [[ $rc -eq 0 && "$output" == 'M035_SCHEMA_CHECK=PASS' ]]; then echo 'PRESENT'; return 0; fi
	if [[ $rc -eq 2 && "$output" == 'M035_SCHEMA_CHECK=ABSENT' ]]; then echo 'ABSENT'; return 0; fi
	die 'SCHEMA_CHECK_DRIFT_OR_FAILURE'
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

restore_database_backup() {
	local backup=$1 expected_sha=$2
	[[ "$backup" == "$DB_BACKUP_ROOT/"*.sql && -f "$backup" && ! -L "$backup" ]] || return 1
	[[ $(stat -c '%U:%G %a' "$backup") == 'root:root 600' ]] || return 1
	[[ $(sha256 "$backup") == "$expected_sha" ]] || return 1
	docker exec -i "$TARGET_DB_CONTAINER" sh -eu -c '
		db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
		user=${MARIADB_USER:-${MYSQL_USER:-root}}
		pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}}
		[ -n "$db" ] && [ -n "$pass" ]
		if command -v mariadb >/dev/null 2>&1; then client=mariadb; else client=mysql; fi
		MYSQL_PWD="$pass" exec "$client" -u "$user" "$db"
	' <"$backup"
}

finalize_full_database_restore() {
	[[ -n "$SCHEMA_PRESTATE_B64" && $(value_sha256 "$SCHEMA_PRESTATE_B64") == "$SCHEMA_PRESTATE_SHA" ]] || return 1
	docker exec -e M035_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" finalize-full-db-restore >/dev/null
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

verify_code_backup_restored() {
	local restore_root file rel target restored
	restore_root=$(mktemp -d /root/m037-restore-check.XXXXXX)
	tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$restore_root" || { rm -rf "$restore_root"; return 1; }
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; restored="$restore_root/$rel"
		if grep -Fxq "$rel" "$ABSENT_LIST"; then
			[[ ! -e "$target" && ! -L "$target" ]] || { rm -rf "$restore_root"; return 1; }
			continue
		fi
		[[ -f "$restored" && ! -L "$restored" && -f "$target" && ! -L "$target" ]] || { rm -rf "$restore_root"; return 1; }
		[[ $(sha256 "$target") == $(sha256 "$restored") ]] || { rm -rf "$restore_root"; return 1; }
		[[ $(stat -c '%u:%g:%a' "$target") == $(stat -c '%u:%g:%a' "$restored") ]] || { rm -rf "$restore_root"; return 1; }
		if [[ "$target" == *.php ]]; then docker exec "$TARGET_CONTAINER" php -l "/var/www/html/custom/$rel" >/dev/null || { rm -rf "$restore_root"; return 1; }; fi
	done
	rm -rf "$restore_root"
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

verify_schema_prestate() {
	[[ -n "$SCHEMA_PRESTATE_B64" && $(value_sha256 "$SCHEMA_PRESTATE_B64") == "$SCHEMA_PRESTATE_SHA" ]] || return 1
	docker exec -e M035_SCHEMA_PRESTATE_B64="$SCHEMA_PRESTATE_B64" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" verify-prestate >/dev/null
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
		if [[ "$route" == '/' ]]; then
			[[ "$status" =~ ^(200|302)$ ]] || return 1
		else
			[[ "$status" =~ ^(302|401|403)$ ]] || return 1
		fi
	done
}

authenticated_route_checks() {
	docker exec -u www-data -e M037_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m037_authenticated_session.php" admin >/dev/null || return 1
	docker exec -u www-data -e M037_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m037_authenticated_session.php" external >/dev/null
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
	docker exec -e M037_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_customer_portal_scope_integration.php" >/dev/null || return 1
	route_checks || return 1
	authenticated_route_checks
}

rollback_checks() {
	verify_code_backup_restored || return 1
	verify_schema_prestate || return 1
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]]
}

save_state() {
	local state_status=$1
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=$state_status
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
	[[ "$STATE_STATUS" =~ ^(APPLY_IN_PROGRESS|DEPLOYED|APPLY_FAILED_ROLLED_BACK|APPLY_FAILED_ROLLBACK_FAILED|ROLLED_BACK)$ ]] || die 'STATE_STATUS_INVALID'
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
	local verify_rc=1
	if [[ $db_rc -eq 0 && $code_rc -eq 0 ]]; then rollback_checks; verify_rc=$?; fi
	if [[ $db_rc -eq 0 ]]; then echo 'M037_STAGING_AUTO_DB_ROLLBACK=PASS' >&2; else echo 'M037_STAGING_AUTO_DB_ROLLBACK=FAIL' >&2; fi
	if [[ $code_rc -eq 0 ]]; then echo 'M037_STAGING_AUTO_CODE_ROLLBACK=PASS' >&2; else echo 'M037_STAGING_AUTO_CODE_ROLLBACK=FAIL' >&2; fi
	if [[ $verify_rc -eq 0 ]]; then echo 'M037_STAGING_AUTO_ROLLBACK_VERIFY=PASS' >&2; else echo 'M037_STAGING_AUTO_ROLLBACK_VERIFY=FAIL' >&2; fi
	if [[ $verify_rc -eq 0 ]]; then
		save_state 'APPLY_FAILED_ROLLED_BACK'
		set -e
		return 0
	fi
	restore_database_backup "$DB_BACKUP" "$DB_BACKUP_SHA"
	local fallback_db_rc=$?
	if [[ $fallback_db_rc -eq 0 ]]; then finalize_full_database_restore; fallback_db_rc=$?; fi
	restore_code_backup
	local fallback_code_rc=$?
	local fallback_verify_rc=1
	if [[ $fallback_db_rc -eq 0 && $fallback_code_rc -eq 0 ]]; then rollback_checks; fallback_verify_rc=$?; fi
	if [[ $fallback_verify_rc -eq 0 ]]; then
		echo 'M037_STAGING_AUTO_FULL_BACKUP_ROLLBACK=PASS' >&2
		save_state 'APPLY_FAILED_ROLLED_BACK'
	else
		echo 'M037_STAGING_AUTO_FULL_BACKUP_ROLLBACK=FAIL' >&2
		save_state 'APPLY_FAILED_ROLLBACK_FAILED'
	fi
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
	save_state 'APPLY_IN_PROGRESS'
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then automatic_rollback; fi; exit $rc' ERR
	mutated=1
	apply_database_changes
	apply_files
	runtime_checks
	mutated=0
	trap - ERR
	save_state 'DEPLOYED'
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
	[[ "$STATE_STATUS" == 'DEPLOYED' || "$STATE_STATUS" == 'APPLY_IN_PROGRESS' || "$STATE_STATUS" == 'APPLY_FAILED_ROLLBACK_FAILED' ]] || die 'STATE_NOT_ROLLBACK_ELIGIBLE'
	local stamp reapply_code reapply_code_sha reapply_db reapply_db_sha original_db_backup original_db_sha file rel
	original_db_backup=$DB_BACKUP
	original_db_sha=$DB_BACKUP_SHA
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	reapply_code="${BACKUP_ROOT}/m037-reapply-${SHORT_COMMIT}-${stamp}.tar.gz"
	local -a current=()
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" ]] && current+=("$rel"); done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply_code" -C "$TARGET_ROOT" "${current[@]}"; else tar -czf "$reapply_code" --files-from /dev/null; fi
	chmod 0600 "$reapply_code"
	reapply_code_sha=$(sha256 "$reapply_code")
	create_database_backup
	reapply_db=$DB_BACKUP
	reapply_db_sha=$DB_BACKUP_SHA
	local rollback_ok=1
	restore_code_backup || rollback_ok=0
	rollback_database_changes || rollback_ok=0
	if [[ $rollback_ok -eq 1 ]]; then rollback_checks || rollback_ok=0; fi
	if [[ $rollback_ok -ne 1 ]]; then
		local original_restore_ok=1
		restore_database_backup "$original_db_backup" "$original_db_sha" || original_restore_ok=0
		if [[ $original_restore_ok -eq 1 ]]; then finalize_full_database_restore || original_restore_ok=0; fi
		restore_code_backup || original_restore_ok=0
		if [[ $original_restore_ok -eq 1 ]]; then rollback_checks || original_restore_ok=0; fi
		if [[ $original_restore_ok -eq 1 ]]; then
			DB_BACKUP=$original_db_backup; DB_BACKUP_SHA=$original_db_sha
			save_state 'ROLLED_BACK'
			echo "M037_STAGING_ROLLBACK=PASS RESTORED=$CODE_BACKUP DB_RESTORED=$original_db_backup DB_RESTORED_SHA=$original_db_sha REAPPLY_CODE=$reapply_code REAPPLY_CODE_SHA=$reapply_code_sha REAPPLY_DB=$reapply_db REAPPLY_DB_SHA=$reapply_db_sha VERIFY=PASS FULL_DB_FALLBACK=YES"
			return 0
		fi
		if [[ "$STATE_STATUS" != 'DEPLOYED' ]]; then
			DB_BACKUP=$original_db_backup; DB_BACKUP_SHA=$original_db_sha
			save_state 'APPLY_FAILED_ROLLBACK_FAILED'
			die 'ROLLBACK_FAILED_PRESTATE_RECOVERY_RETAINED'
		fi
		local reapply_root
		reapply_root=$(mktemp -d /root/m037-reapply.XXXXXX)
		[[ $(sha256 "$reapply_code") == "$reapply_code_sha" ]] || die 'ROLLBACK_REAPPLY_BACKUP_HASH_FAILED'
		tar -xzf "$reapply_code" --same-owner --same-permissions -C "$reapply_root" || die 'ROLLBACK_REAPPLY_EXTRACT_FAILED'
		for file in "${RUNTIME_FILES[@]}"; do
			rel=${file#custom/}
			[[ -f "$reapply_root/$rel" && ! -L "$reapply_root/$rel" ]] || die 'ROLLBACK_REAPPLY_FILE_MISSING'
			install -o "$(stat -c '%u' "$reapply_root/$rel")" -g "$(stat -c '%g' "$reapply_root/$rel")" -m "$(stat -c '%a' "$reapply_root/$rel")" "$reapply_root/$rel" "$TARGET_ROOT/$rel"
		done
		rm -rf "$reapply_root"
		restore_database_backup "$reapply_db" "$reapply_db_sha" || die 'ROLLBACK_FAILED_DB_REAPPLY_FAILED'
		runtime_checks || die 'ROLLBACK_FAILED_REAPPLY_VERIFY_FAILED'
		die 'ROLLBACK_FAILED_DEPLOYED_STATE_REAPPLIED'
	fi
	DB_BACKUP=$original_db_backup; DB_BACKUP_SHA=$original_db_sha
	save_state 'ROLLED_BACK'
	echo "M037_STAGING_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY_CODE=$reapply_code REAPPLY_CODE_SHA=$reapply_code_sha REAPPLY_DB=$reapply_db REAPPLY_DB_SHA=$reapply_db_sha VERIFY=PASS"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then
		grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE_SHA|DEPLOYED_AT)=' "$STATE_FILE"
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
