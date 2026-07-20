#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M020-C03_M023_M024_M024-C01_M025'
readonly RUNTIME_COMMIT='7b4a216d0dba2f611b457d804e85f1e07317812d'
readonly SHORT_COMMIT='7b4a216'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m024-c01-r2-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='dcb020f773e399f83df99f1416358f18f2dc96ee9559699e86c9049ab0974e35'

readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly TARGET_DB_CONTAINER='erp-staging-mariadb-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088/'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly DB_BACKUP_ROOT='/root/backup-erp-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-backup-erp-m024-c01-deploy'
readonly STATE_FILE="${STATE_ROOT}/m024-c01.state"
readonly LOCK_FILE="${STATE_ROOT}/m024-c01.lock"

readonly M020_SCHEMA='deploy/migrations/m020_c01_shipment_public_document_schema.php'
readonly M024_SCHEMA='deploy/migrations/m024_shipment_customer_collaboration_schema.php'
readonly SECRET_TOOL='deploy/tools/m020_mobile_api_config.php'
readonly C01_CONFIG='deploy/tools/m020_c01_shipment_gallery_config.php'
readonly C02_CONFIG='deploy/tools/m020_c02_customer_web_gallery_config.php'
readonly DEMO_TOOL='deploy/tools/m024_seed_backup_erp_demo.php'
readonly DEMO_CUSTOMER_LOGIN='mohamed.azmy'
readonly DEMO_STATE_FILE="${STATE_ROOT}/m024-c01-demo.state"

readonly -a PHP_RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/documents.php'
	'custom/chinaplanetcustomercenter/download.php'
	'custom/chinaplanetcustomercenter/lib/portal.lib.php'
	'custom/chinaplanetlogistics/card.php'
	'custom/chinaplanetlogistics/lib/shipment_customer.lib.php'
)
readonly -a RUNTIME_FILES=(
	"${PHP_RUNTIME_FILES[@]}"
	'custom/chinaplanetcustomercenter/css/portal.css'
	'custom/chinaplanetcustomercenter/langs/ar_SA/chinaplanetcustomercenter.lang'
	'custom/chinaplanetcustomercenter/langs/en_US/chinaplanetcustomercenter.lang'
	'custom/chinaplanetlogistics/css/shipment-command-center.css'
	'custom/chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang'
	'custom/chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang'
	'custom/chinaplanetlogistics/sql/llx_cpl_shipment_customer_event.sql'
	'custom/chinaplanetlogistics/sql/llx_cpl_shipment_public_document.sql'
)
readonly -a TEST_FILES=(
	'deploy/tests/test_m011_shipment_customer_command_center.php'
	'deploy/tests/test_m020_c01_schema_migration.php'
	'deploy/tests/test_m020_c01_shipment_gallery.php'
	'deploy/tests/test_m020_c02_customer_web_shipment_gallery.php'
	'deploy/tests/test_m023_remove_document_checklist.php'
	'deploy/tests/test_m024_shipment_customer_collaboration.php'
)
readonly -a PHP_TEST_DEPENDENCY_FILES=(
	'custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php'
	'custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php'
	'custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php'
	'custom/chinaplanetlogistics/list.php'
	'custom/chinaplanetoperations/class/OperationsService.class.php'
	'custom/chinaplanetoperations/operations/edit.php'
	'custom/cpkanban/lib/customer_project_center.lib.php'
)
readonly -a STATIC_TEST_DEPENDENCY_FILES=(
	'custom/chinaplanetlogistics/css/shipment-list.css'
	'custom/chinaplanetlogistics/sql/llx_chinaplanetlogistics.sql'
	'custom/chinaplanetlogistics/sql/llx_cpl_document_requirement.sql'
	'custom/chinaplanetlogistics/sql/llx_cpl_shipment_event.sql'
	'docs/API/mobile-v1.openapi.json'
)
readonly -a SUPPORT_FILES=(
	"$M020_SCHEMA"
	"$M024_SCHEMA"
	"$SECRET_TOOL"
	"$C01_CONFIG"
	"$C02_CONFIG"
	"$DEMO_TOOL"
	"${TEST_FILES[@]}"
	"${PHP_TEST_DEPENDENCY_FILES[@]}"
	"${STATIC_TEST_DEPENDENCY_FILES[@]}"
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}")

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
DB_BACKUP=''
DB_BACKUP_SHA=''

die() { echo "M024_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-backup-erp-m024-shipment-collaboration-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_container_mount() {
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'STAGING_CUSTOM_MOUNT_MISMATCH'
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'STAGING_ROOT_INVALID'
	check_container_mount
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_DB_NOT_RUNNING'
	docker exec "$TARGET_DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1' || die 'DB_DUMP_TOOL_ABSENT'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'STAGING_HTTP_UNHEALTHY'
}

ensure_state_dirs() { install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT"; }

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
	CANDIDATE_REMOTE="/tmp/m024-c01-staging-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${PHP_RUNTIME_FILES[@]}" "${PHP_TEST_DEPENDENCY_FILES[@]}" "$M020_SCHEMA" "$M024_SCHEMA" "$SECRET_TOOL" "$C01_CONFIG" "$C02_CONFIG" "$DEMO_TOOL" "${TEST_FILES[@]}"; do
		docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"
	done
	for file in "${TEST_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_TEST_FAILED_${file//\//_}"; done
}

tool_state() {
	local tool=$1 absent_code=$2 rc=0
	if docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$tool" check >/dev/null 2>&1; then echo 'PRESENT'; return 0; else rc=$?; fi
	[[ $rc -eq $absent_code ]] || die "CHECK_FAILED_${tool//\//_}"
	echo 'ABSENT'
}

secret_state() {
	local rc=0
	if docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SECRET_TOOL" check >/dev/null 2>&1; then echo 'PRESENT'; return 0; else rc=$?; fi
	[[ $rc -eq 2 ]] || die 'SECRET_CHECK_FAILED'
	echo 'ABSENT'
}

create_database_backup() {
	DB_BACKUP="${DB_BACKUP_ROOT}/m024-c01-before-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').sql"
	docker exec "$TARGET_DB_CONTAINER" sh -eu -c '
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

apply_database_changes() {
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$M020_SCHEMA" apply >/dev/null || die 'M020_SCHEMA_APPLY_FAILED'
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$M024_SCHEMA" apply >/dev/null || die 'M024_SCHEMA_APPLY_FAILED'
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SECRET_TOOL" apply >/dev/null || die 'TOKEN_SECRET_APPLY_FAILED'
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$C01_CONFIG" check >/dev/null || die 'C01_CONFIG_NOT_READY'
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$C02_CONFIG" check >/dev/null || die 'C02_CONFIG_NOT_READY'
}

create_code_backup() {
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m024-c01-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m024-c01-absent-before-${SHORT_COMMIT}-${stamp}.txt"
	: >"$ABSENT_LIST"; local -a existing=()
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"; existing+=("$rel"); else printf '%s\n' "$rel" >>"$ABSENT_LIST"; fi
	done
	if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"; CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}

restore_code_backup() {
	[[ -f "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	[[ -f "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; [[ ! -d "$target" ]] || return 1; rm -f "$target"; done
	[[ $(tar -tzf "$CODE_BACKUP" | wc -l) -eq 0 ]] || tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT"
}

apply_files() {
	local file rel src target parent uid gid mode tmp
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
		[[ -d "$parent" && ! -L "$parent" ]] || die "TARGET_PARENT_INVALID_${file//\//_}"
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"; uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target"); else uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode='644'; fi
		tmp="${target}.m024.$$"; install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"; mv -f "$tmp" "$target"
	done
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M024_STAGING_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		target_hash=$(sha256 "$target"); if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M024_STAGING_COMPARE FILE=$file STATE=$state STAGING_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

live_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ]] || return 1; [[ $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1; done
	for file in "${PHP_RUNTIME_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; done
	[[ $(tool_state "$M020_SCHEMA" 2) == 'PRESENT' ]] || return 1
	[[ $(tool_state "$M024_SCHEMA" 2) == 'PRESENT' ]] || return 1
	[[ $(secret_state) == 'PRESENT' ]] || return 1
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$C02_CONFIG" check >/dev/null || return 1
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]]
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
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
}

command_compare() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M024_STAGING_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() {
	check_environment; validate_artifact; candidate_checks
	echo "M024_STAGING_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA M020_SCHEMA=$(tool_state "$M020_SCHEMA" 2) M024_SCHEMA=$(tool_state "$M024_SCHEMA" 2) SECRET=$(secret_state)"
}
command_apply() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks; create_code_backup; create_database_backup; apply_database_changes
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_code_backup && echo M024_STAGING_AUTO_CODE_ROLLBACK=PASS >&2 || echo M024_STAGING_AUTO_CODE_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
	mutated=1; apply_files; live_checks; mutated=0; trap - ERR; save_state
	echo "M024_STAGING_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA"
}
command_verify() { check_environment; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M024_STAGING_VERIFY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
demo_inspect() {
	docker exec -e M024_DEMO_ALLOWED=backup-erp -e M024_DEMO_CUSTOMER_LOGIN="$DEMO_CUSTOMER_LOGIN" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$DEMO_TOOL" inspect
}
command_seed_demo() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'
	create_database_backup
	docker exec -e M024_DEMO_ALLOWED=backup-erp -e M024_DEMO_CUSTOMER_LOGIN="$DEMO_CUSTOMER_LOGIN" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$DEMO_TOOL" apply >/dev/null || die 'DEMO_APPLY_FAILED'
	local inspection; inspection=$(demo_inspect) || die 'DEMO_INSPECT_FAILED'
	[[ "$inspection" == *'"projects":1,"shipments":5,"documents":11,"events":26,"orders":3,"invoices":3,"financial_entries":3'* ]] || die 'DEMO_COUNTS_INVALID'
	cat >"$DEMO_STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_DEMO_DB_BACKUP=$DB_BACKUP
STATE_DEMO_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_DEMO_APPLIED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$DEMO_STATE_FILE"
	echo "M024_STAGING_DEMO_APPLY=PASS DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA COUNTS=projects:1,shipments:5,documents:11,events:26,orders:3,invoices:3,financial_entries:3"
}
command_verify_demo() {
	check_environment; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'
	local inspection; inspection=$(demo_inspect) || die 'DEMO_INSPECT_FAILED'
	[[ "$inspection" == *'"projects":1,"shipments":5,"documents":11,"events":26,"orders":3,"invoices":3,"financial_entries":3'* ]] || die 'DEMO_COUNTS_INVALID'
	echo "M024_STAGING_DEMO_VERIFY=PASS COUNTS=projects:1,shipments:5,documents:11,events:26,orders:3,invoices:3,financial_entries:3"
}
command_cleanup_demo() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'
	create_database_backup
	docker exec -e M024_DEMO_ALLOWED=backup-erp -e M024_DEMO_CUSTOMER_LOGIN="$DEMO_CUSTOMER_LOGIN" "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$DEMO_TOOL" cleanup >/dev/null || die 'DEMO_CLEANUP_FAILED'
	local inspection; inspection=$(demo_inspect) || die 'DEMO_INSPECT_FAILED'
	[[ "$inspection" == *'"projects":0,"shipments":0,"documents":0,"events":0,"orders":0,"invoices":0,"financial_entries":0'* ]] || die 'DEMO_CLEANUP_COUNTS_INVALID'
	echo "M024_STAGING_DEMO_CLEANUP=PASS DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA"
}
command_rollback() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks; load_state
	local reapply="${BACKUP_ROOT}/m024-c01-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"; local -a current=(); local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" ]] && current+=("$rel"); done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply" -C "$TARGET_ROOT" "${current[@]}"; else tar -czf "$reapply" --files-from /dev/null; fi
	chmod 0600 "$reapply"; restore_code_backup || die 'ROLLBACK_CODE_RESTORE_FAILED'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M024_STAGING_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply") ADDITIVE_SCHEMA_RETAINED=1 TOKEN_SECRET_RETAINED=1"
}
command_status() { if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M024_STAGING_STATUS=NOT_DEPLOYED'; fi; }

require_root "$@"
trap cleanup EXIT
case "$1" in
	compare) command_compare ;;
	preflight) command_preflight ;;
	apply) command_apply ;;
	verify) command_verify ;;
	seed-demo) command_seed_demo ;;
	verify-demo) command_verify_demo ;;
	cleanup-demo) command_cleanup_demo ;;
	rollback) command_rollback ;;
	status) command_status ;;
	*) die 'COMMAND_NOT_ALLOWED' ;;
esac
