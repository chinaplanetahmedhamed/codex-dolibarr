#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M014'
readonly COMMIT='49eadafd36955446468c3629f2c9d8b6aa3a7a2c'
readonly SHORT_COMMIT='49eadaf'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m014-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='4d9c05e1681ea9570861ef852f32071413846eaa664e0bbd1d78a3016580f118'
readonly MIGRATION_FILE='deploy/migrations/m014_shipment_command_center_schema.php'

readonly STAGING_CONTAINER='erp-staging-dolibarr-1'
readonly STAGING_DB_CONTAINER='erp-staging-mariadb-1'
readonly STAGING_VOLUME='erp-staging_erp-staging_custom'
readonly STAGING_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly STAGING_HTTP='http://127.0.0.1:18088/'

readonly PROD_CONTAINER='vision_erp'
readonly PROD_DB_CONTAINER='vision_db'
readonly PROD_VOLUME='vision-os_dolibarr_custom'
readonly PROD_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly PROD_HTTP='https://erp.visiontrading.biz/'

readonly -a RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/css/portal.css'
	'custom/chinaplanetcustomercenter/documents.php'
	'custom/chinaplanetcustomercenter/js/portal.js'
	'custom/chinaplanetcustomercenter/lib/export.lib.php'
	'custom/chinaplanetcustomercenter/lib/financial.lib.php'
	'custom/chinaplanetcustomercenter/lib/portal.lib.php'
	'custom/chinaplanetcustomercenter/projects.php'
	'custom/chinaplanetlogistics/card.php'
	'custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php'
	'custom/chinaplanetlogistics/core/modules/modChinaPlanetLogistics.class.php'
	'custom/chinaplanetlogistics/css/shipment-command-center.css'
	'custom/chinaplanetlogistics/css/shipment-list.css'
	'custom/chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang'
	'custom/chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang'
	'custom/chinaplanetlogistics/lib/shipment_customer.lib.php'
	'custom/chinaplanetlogistics/list.php'
	'custom/chinaplanetlogistics/sql/llx_chinaplanetlogistics.sql'
	'custom/chinaplanetlogistics/sql/llx_cpl_document_requirement.sql'
	'custom/chinaplanetlogistics/sql/llx_cpl_shipment_event.sql'
	'custom/chinaplanetoperations/class/OperationsService.class.php'
	'custom/chinaplanetoperations/operations/edit.php'
	'custom/cpkanban/asset.php'
	'custom/cpkanban/card.php'
	'custom/cpkanban/class/cpkanbancard.class.php'
	'custom/cpkanban/css/board.css'
	'custom/cpkanban/js/board.js'
	'custom/cpkanban/lib/cpkanban.lib.php'
	'custom/cpkanban/lib/customer_project_center.lib.php'
)

readonly -a RUNTIME_DIRS=(
	'chinaplanetlogistics/css'
	'chinaplanetlogistics/langs'
	'chinaplanetlogistics/langs/ar_SA'
	'chinaplanetlogistics/langs/en_US'
)

readonly -a SUPPORT_FILES=(
	'custom/chinaplanetcustomercenter/download.php'
	'custom/chinaplanetcustomercenter/orders.php'
	'custom/chinaplanetcustomercenter/admin_dashboard.php'
	'custom/chinaplanetcustomercenter/board.php'
	'custom/chinaplanetcustomercenter/lib/operations.lib.php'
	'custom/chinaplanetcustomercenter/project.php'
	'custom/chinaplanetcustomercenter/admin/operations.php'
	'custom/chinaplanetlogistics/public/track.php'
	'custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php'
	'custom/chinaplanetlogistics/index.php'
	'custom/chinaplanetlogistics/core/boxes/box_chinaplanetlogistics.php'
)

readonly -a TEST_FILES=(
	'deploy/tests/test_m003_portal_hardening.php'
	'deploy/tests/test_m004_shipping_status_sequence.php'
	'deploy/tests/test_m010_customer_project_command_center.php'
	'deploy/tests/test_m011_shipment_customer_command_center.php'
	'deploy/tests/test_freetime_automation.php'
	'deploy/tests/test_m014_schema_migration.php'
)

readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$MIGRATION_FILE" "${TEST_FILES[@]}")

MODE=''
TARGET_CONTAINER=''
TARGET_DB_CONTAINER=''
TARGET_VOLUME=''
TARGET_ROOT=''
TARGET_HTTP=''
BACKUP_ROOT=''
DB_BACKUP_ROOT=''
STATE_ROOT=''
STATE_FILE=''
LOCK_FILE=''
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
ABSENT_DIR_LIST=''
ABSENT_DIR_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''

die() { echo "${RELEASE}_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

configure_mode() {
	case "$(basename "$0")" in
		chinaplanetos-backup-erp-deploy)
			MODE='STAGING'
			TARGET_CONTAINER="$STAGING_CONTAINER"
			TARGET_DB_CONTAINER="$STAGING_DB_CONTAINER"
			TARGET_VOLUME="$STAGING_VOLUME"
			TARGET_ROOT="$STAGING_ROOT"
			TARGET_HTTP="$STAGING_HTTP"
			BACKUP_ROOT='/root/backup-erp-code-backups'
			DB_BACKUP_ROOT='/root/backup-erp-db-backups'
			STATE_ROOT='/var/lib/chinaplanetos-backup-erp-deploy'
			;;
		chinaplanetos-production-m007-deploy)
			MODE='PRODUCTION'
			TARGET_CONTAINER="$PROD_CONTAINER"
			TARGET_DB_CONTAINER="$PROD_DB_CONTAINER"
			TARGET_VOLUME="$PROD_VOLUME"
			TARGET_ROOT="$PROD_ROOT"
			TARGET_HTTP="$PROD_HTTP"
			BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
			DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
			STATE_ROOT='/var/lib/chinaplanetos-production-deploy'
			;;
		*) die 'HELPER_PATH_NOT_ALLOWED' ;;
	esac
	STATE_FILE="${STATE_ROOT}/m014.state"
	LOCK_FILE="${STATE_ROOT}/m014.lock"
}

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" && -n "$TARGET_CONTAINER" ]]; then
		docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
	fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then
		rm -rf "$CANDIDATE_ROOT"
	fi
}

check_container_mount() {
	local container=$1 volume=$2
	[[ $(docker inspect -f '{{.State.Running}}' "$container" 2>/dev/null) == 'true' ]] || die "CONTAINER_NOT_RUNNING_${container//-/_}"
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$container") == "${volume}|true" ]] || die "CUSTOM_MOUNT_MISMATCH_${container//-/_}"
}

check_dump_tool() {
	docker exec "$1" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'CUSTOM_ROOT_INVALID'
	check_container_mount "$TARGET_CONTAINER" "$TARGET_VOLUME"
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'DB_CONTAINER_NOT_RUNNING'
	check_dump_tool "$TARGET_DB_CONTAINER"
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'HTTP_UNHEALTHY'
	install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"
}

validate_artifact() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G' "$ARTIFACT") == 'codex-deploy:codex-deploy' ]] || die 'ARTIFACT_OWNER_INVALID'
	[[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'

	local listing expected actual
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
	tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
	[[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
	CANDIDATE_ROOT=$(mktemp -d)
	tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
	[[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
	printf '%s\n' "${ALL_FILES[@]}" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	local file
	for file in "${ALL_FILES[@]}"; do
		[[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die 'ARTIFACT_FILE_INVALID'
	done
	rm -f "$listing" "$expected" "$actual"
}

candidate_checks() {
	CANDIDATE_REMOTE="/tmp/m014-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${RUNTIME_FILES[@]}" "$MIGRATION_FILE" "${TEST_FILES[@]}"; do
		[[ "$file" == *.php ]] || continue
		docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"
	done
	for file in "${TEST_FILES[@]}"; do
		docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_TEST_FAILED_${file//\//_}"
	done
}

schema_state() {
	local rc=0
	if docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION_FILE" check >/dev/null 2>&1; then
		echo 'PRESENT'
		return 0
	else
		rc=$?
	fi
	[[ $rc -eq 2 ]] || die 'SCHEMA_CHECK_FAILED'
	echo 'ABSENT'
}

apply_schema() {
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$MIGRATION_FILE" apply >/dev/null || die 'SCHEMA_APPLY_FAILED'
	[[ $(schema_state) == 'PRESENT' ]] || die 'SCHEMA_VERIFY_FAILED'
}

create_database_backup() {
	DB_BACKUP="${DB_BACKUP_ROOT}/m014-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
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
	CODE_BACKUP="${BACKUP_ROOT}/m014-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m014-absent-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').txt"
	ABSENT_DIR_LIST="${BACKUP_ROOT}/m014-absent-dirs-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').txt"
	: >"$ABSENT_LIST"
	: >"$ABSENT_DIR_LIST"
	local -a existing=()
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then
			[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
			existing+=("$rel")
		else
			printf '%s\n' "$rel" >>"$ABSENT_LIST"
		fi
	done
	for rel in "${RUNTIME_DIRS[@]}"; do
		target="$TARGET_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then
			[[ -d "$target" && ! -L "$target" ]] || die "TARGET_DIR_INVALID_${rel//\//_}"
		else
			printf '%s\n' "$rel" >>"$ABSENT_DIR_LIST"
		fi
	done
	[[ ${#existing[@]} -gt 0 ]] || die 'NO_EXISTING_RUNTIME_FILES'
	tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST" "$ABSENT_DIR_LIST"
	CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP")
	ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
	ABSENT_DIR_LIST_SHA=$(sha256 "$ABSENT_DIR_LIST")
}

restore_code_backup() {
	[[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	[[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
	[[ -f "$ABSENT_DIR_LIST" && $(sha256 "$ABSENT_DIR_LIST") == "$ABSENT_DIR_LIST_SHA" ]] || return 1
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		[[ ! -d "$target" ]] || return 1
		rm -f "$target"
	done
	tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT"
	while IFS= read -r rel; do
		[[ -n "$rel" ]] || continue
		rmdir "$TARGET_ROOT/$rel" 2>/dev/null || true
	done < <(tac "$ABSENT_DIR_LIST")
}

ensure_runtime_directories() {
	local rel target parent uid gid
	for rel in "${RUNTIME_DIRS[@]}"; do
		target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
		if [[ -e "$target" || -L "$target" ]]; then
			[[ -d "$target" && ! -L "$target" ]] || die "TARGET_DIR_INVALID_${rel//\//_}"
			continue
		fi
		[[ -d "$parent" && ! -L "$parent" ]] || die "TARGET_DIR_PARENT_INVALID_${rel//\//_}"
		uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent")
		install -d -o "$uid" -g "$gid" -m 0755 "$target"
	done
}

apply_files() {
	local file rel src target parent uid gid mode tmp
	ensure_runtime_directories
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
		[[ -d "$parent" && ! -L "$parent" ]] || die "TARGET_PARENT_INVALID_${file//\//_}"
		if [[ -e "$target" || -L "$target" ]]; then
			[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
			uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target")
		else
			uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode='644'
		fi
		tmp="${target}.m014.$$"
		install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"
		mv -f "$tmp" "$target"
	done
}

compare_target_files() {
	local root=$1 label=$2 file rel target_hash release_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}
		[[ -f "$root/$rel" && ! -L "$root/$rel" ]] || die "${label}_FILE_INVALID_${file//\//_}"
		target_hash=$(sha256 "$root/$rel"); release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M014_COMPARE ENV=$label FILE=$file STATE=$state"
	done
}

require_staging_release() {
	check_container_mount "$STAGING_CONTAINER" "$STAGING_VOLUME"
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}
		[[ -f "$STAGING_ROOT/$rel" && ! -L "$STAGING_ROOT/$rel" ]] || die "STAGING_FILE_INVALID_${file//\//_}"
		[[ $(sha256 "$STAGING_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || die "STAGING_RELEASE_MISMATCH_${file//\//_}"
	done
	local migration_remote="/tmp/m014-staging-schema-$$.php"
	docker cp "$CANDIDATE_ROOT/$MIGRATION_FILE" "$STAGING_CONTAINER:$migration_remote"
	docker exec "$STAGING_CONTAINER" php "$migration_remote" check >/dev/null || die 'STAGING_SCHEMA_NOT_READY'
	docker exec "$STAGING_CONTAINER" rm -f "$migration_remote"
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$STAGING_HTTP") =~ ^(200|302)$ ]] || die 'STAGING_HTTP_UNHEALTHY'
}

live_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}
		[[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ]] || return 1
		[[ $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1
		if [[ "$file" == *.php ]]; then
			docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1
		fi
	done
	[[ $(schema_state) == 'PRESENT' ]] || return 1

	local live_remote="/tmp/m014-live-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$live_remote"
	docker exec "$TARGET_CONTAINER" mkdir -p "$live_remote/deploy/tests" "$live_remote/deploy/migrations"
	docker exec "$TARGET_CONTAINER" ln -s /var/www/html/custom "$live_remote/custom"
	for file in "${TEST_FILES[@]}"; do
		docker cp "$CANDIDATE_ROOT/$file" "$TARGET_CONTAINER:$live_remote/deploy/tests/"
	done
	docker cp "$CANDIDATE_ROOT/$MIGRATION_FILE" "$TARGET_CONTAINER:$live_remote/deploy/migrations/"
	for file in "${TEST_FILES[@]}"; do
		docker exec "$TARGET_CONTAINER" php "$live_remote/$file" >/dev/null || return 1
	done
	docker exec "$TARGET_CONTAINER" rm -rf "$live_remote"
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]]
}

save_state() {
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_COMMIT=$COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_ABSENT_DIR_LIST=$ABSENT_DIR_LIST
STATE_ABSENT_DIR_LIST_SHA=$ABSENT_DIR_LIST_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_COMMIT" == "$COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP
	CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA
	ABSENT_LIST=$STATE_ABSENT_LIST
	ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
	ABSENT_DIR_LIST=$STATE_ABSENT_DIR_LIST
	ABSENT_DIR_LIST_SHA=$STATE_ABSENT_DIR_LIST_SHA
}

command_compare() {
	[[ "$MODE" == 'PRODUCTION' ]] || die 'COMMAND_NOT_ALLOWED'
	check_environment; validate_artifact; candidate_checks; require_staging_release
	compare_target_files "$PROD_ROOT" 'PRODUCTION'
	echo "M014_COMPARE=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"
}

command_preflight() {
	check_environment; validate_artifact; candidate_checks
	if [[ "$MODE" == 'PRODUCTION' ]]; then require_staging_release; fi
	local current_schema
	current_schema=$(schema_state)
	echo "M014_PREFLIGHT=PASS ENV=$MODE COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$current_schema"
}

command_apply() {
	check_environment
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks
	if [[ "$MODE" == 'PRODUCTION' ]]; then require_staging_release; fi
	create_code_backup; create_database_backup; apply_schema
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_code_backup && echo M014_AUTO_CODE_ROLLBACK=PASS >&2 || echo M014_AUTO_CODE_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
	mutated=1; apply_files; live_checks || false; mutated=0; trap - ERR
	save_state
	echo "M014_APPLY=PASS ENV=$MODE COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA"
}

command_verify() {
	check_environment; validate_artifact; candidate_checks
	if [[ "$MODE" == 'PRODUCTION' ]]; then require_staging_release; fi
	live_checks || die 'LIVE_VERIFY_FAILED'
	echo "M014_VERIFY=PASS ENV=$MODE COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"
}

command_rollback() {
	check_environment
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks; load_state
	local reapply="${BACKUP_ROOT}/m014-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
	local -a current=()
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" ]] && current+=("$rel"); done
	[[ ${#current[@]} -gt 0 ]] || die 'REAPPLY_SET_EMPTY'
	tar -czf "$reapply" -C "$TARGET_ROOT" "${current[@]}"; chmod 0600 "$reapply"
	restore_code_backup || die 'ROLLBACK_RESTORE_FAILED'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M014_ROLLBACK=PASS ENV=$MODE RESTORED=$CODE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") SCHEMA_RETAINED=1"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then
		grep -E '^STATE_(RELEASE|COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|ABSENT_DIR_LIST|ABSENT_DIR_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|DEPLOYED_AT)=' "$STATE_FILE"
	else
		echo "M014_STATUS=NOT_DEPLOYED ENV=$MODE"
	fi
}

configure_mode
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
