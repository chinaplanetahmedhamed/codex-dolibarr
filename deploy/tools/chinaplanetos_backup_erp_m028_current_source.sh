#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M028-C03'
readonly RUNTIME_COMMIT='3bf5a3fef38bfcc04fb024156f1b4975c59ac685'
readonly SHORT_COMMIT='3bf5a3f'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m028-c03-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='ee10b0dd122e24c1bcf97479e6d43639de7656308658c2b8d2681532b33082d5'

readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088/'
readonly BACKUP_ROOT='/root/backup-erp-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-backup-erp-m028-c03-deploy'
readonly STATE_FILE="${STATE_ROOT}/m028-c03.state"
readonly LOCK_FILE="${STATE_ROOT}/m028-c03.lock"
readonly TEST_FILE='deploy/tests/test_m028_current_source_release.php'

readonly -a PHP_RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/admin/financial.php'
	'custom/chinaplanetcustomercenter/admin/financial_portfolio.php'
	'custom/chinaplanetcustomercenter/admin_dashboard.php'
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
	'custom/chinaplanetcustomercenter/lib/financial.lib.php'
	'custom/chinaplanetlogistics/list.php'
	'custom/chinaplanetoperations/sop/index.php'
	'custom/cpkanban/board.php'
)
readonly -a RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/admin/financial.php'
	'custom/chinaplanetcustomercenter/admin/financial_portfolio.php'
	'custom/chinaplanetcustomercenter/admin_dashboard.php'
	'custom/chinaplanetcustomercenter/css/admin_dashboard.css'
	'custom/chinaplanetcustomercenter/css/ux_fixes.css'
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
	'custom/chinaplanetcustomercenter/lib/financial.lib.php'
	'custom/chinaplanetlogistics/list.php'
	'custom/chinaplanetoperations/css/operations.css'
	'custom/chinaplanetoperations/sop/index.php'
	'custom/cpkanban/board.php'
	'custom/cpkanban/css/board.css'
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "$TEST_FILE")

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''

die() { echo "M028_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-backup-erp-m028-current-source-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'STAGING_ROOT_INVALID'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'STAGING_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'STAGING_CUSTOM_MOUNT_MISMATCH'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'STAGING_HTTP_UNHEALTHY'
}

ensure_state_dirs() {
	install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
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
	CANDIDATE_REMOTE="/tmp/m028-c03-staging-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${PHP_RUNTIME_FILES[@]}" "$TEST_FILE"; do
		docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"
	done
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$TEST_FILE" >/dev/null || die 'CANDIDATE_TEST_FAILED'
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M028_STAGING_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		target_hash=$(sha256 "$target")
		if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M028_STAGING_COMPARE FILE=$file STATE=$state STAGING_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

create_code_backup() {
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m028-c03-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m028-c03-absent-before-${SHORT_COMMIT}-${stamp}.txt"
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
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; [[ ! -d "$target" ]] || return 1; rm -f "$target"; done
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
		tmp="${target}.m028.$$"
		install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"
		mv -f "$tmp" "$target"
	done
}

live_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}
		[[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ]] || return 1
		[[ $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1
	done
	for file in "${PHP_RUNTIME_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; done
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
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP
	CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA
	ABSENT_LIST=$STATE_ABSENT_LIST
	ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
}

command_compare() {
	check_environment; validate_artifact; candidate_checks; compare_files
	echo "M028_STAGING_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"
}

command_preflight() {
	check_environment; validate_artifact; candidate_checks
	echo "M028_STAGING_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} DB_CHANGED=NO"
}

command_apply() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; candidate_checks; create_code_backup
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_code_backup && echo M028_STAGING_AUTO_ROLLBACK=PASS >&2 || echo M028_STAGING_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
	mutated=1; apply_files; live_checks; mutated=0; trap - ERR; save_state
	echo "M028_STAGING_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_CHANGED=NO"
}

command_verify() {
	check_environment; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'
	echo "M028_STAGING_VERIFY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} PRODUCTION_ACCESSED=NO"
}

command_rollback() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; load_state
	local reapply="${BACKUP_ROOT}/m028-c03-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
	local -a current=(); local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" ]] && current+=("$rel"); done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply" -C "$TARGET_ROOT" "${current[@]}"; else tar -czf "$reapply" --files-from /dev/null; fi
	chmod 0600 "$reapply"
	restore_code_backup || die 'ROLLBACK_RESTORE_FAILED'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M028_STAGING_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then
		grep -E '^STATE_(RELEASE|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DEPLOYED_AT)=' "$STATE_FILE"
	else
		echo 'M028_STAGING_STATUS=NOT_DEPLOYED'
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
