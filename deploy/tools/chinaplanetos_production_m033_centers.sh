#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M033'
readonly RUNTIME_COMMIT='0f8508b078b68ee13e04cc8924078d485dac5640'
readonly SHORT_COMMIT='0f8508b'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m033-${SHORT_COMMIT}-flat.tar.gz"
readonly ARTIFACT_SHA='a6f5ba633ac315a773663c87af826d106ce00a9acdcf2657aa079bb5bde8e63c'

readonly TARGET_CONTAINER='vision_erp'
readonly TARGET_VOLUME='vision-os_dolibarr_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly TARGET_HTTP='https://erp.visiontrading.biz/'
readonly BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-production-m033-deploy'
readonly STATE_FILE="${STATE_ROOT}/m033.state"
readonly LOCK_FILE="${STATE_ROOT}/m033.lock"

readonly -a PHP_FILES=(
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
	'custom/chinaplanetcustomercenter/board.php'
	'custom/chinaplanetlogistics/index.php'
)
readonly -a RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
	'custom/chinaplanetcustomercenter/board.php'
	'custom/chinaplanetcustomercenter/css/admin_dashboard.css'
	'custom/chinaplanetlogistics/index.php'
)

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''

die() { echo "M033_PRODUCTION_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-production-m033-centers-deploy-v4' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'PRODUCTION_ROOT_INVALID'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PRODUCTION_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'PRODUCTION_CUSTOM_MOUNT_MISMATCH'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'PRODUCTION_HTTP_UNHEALTHY'
}

ensure_state_dirs() { install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"; }

validate_artifact() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
	[[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
	local listing expected actual
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
	tar -tzf "$ARTIFACT" >"$listing" || die 'ARTIFACT_TAR_INVALID'
	[[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
	[[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'
	CANDIDATE_ROOT=$(mktemp -d)
	tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
	[[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
	printf '%s\n' "${RUNTIME_FILES[@]}" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	local file
	for file in "${RUNTIME_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die "ARTIFACT_FILE_INVALID_${file//\//_}"; done
	rm -f "$listing" "$expected" "$actual"
}

candidate_checks() {
	CANDIDATE_REMOTE="/tmp/m033-production-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${PHP_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die "CANDIDATE_LINT_FAILED_${file//\//_}"; done
	grep -q "Shipping Center / مركز الشحن" "$CANDIDATE_ROOT/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php" || die 'SHIPPING_CENTER_ASSERTION_FAILED'
	grep -q "Customer Center / مركز العملاء" "$CANDIDATE_ROOT/custom/chinaplanetcustomercenter/board.php" || die 'CUSTOMER_CENTER_ASSERTION_FAILED'
	grep -q "function cpcc_board_customer_document_count" "$CANDIDATE_ROOT/custom/chinaplanetcustomercenter/board.php" || die 'AUTHENTICATED_PORTAL_HELPER_MISSING'
	! grep -q 'cpcc_portal_customer_document_count($db' "$CANDIDATE_ROOT/custom/chinaplanetcustomercenter/board.php" || die 'UNDEPLOYED_PORTAL_HELPER_DEPENDENCY'
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M033_PRODUCTION_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		target_hash=$(sha256 "$target")
		if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M033_PRODUCTION_COMPARE FILE=$file STATE=$state PRODUCTION_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

create_code_backup() {
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m033-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	ABSENT_LIST="${BACKUP_ROOT}/m033-absent-before-${SHORT_COMMIT}-${stamp}.txt"
	: >"$ABSENT_LIST"
	local -a existing=()
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		if [[ -e "$target" || -L "$target" ]]; then [[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"; existing+=("$rel"); else printf '%s\n' "$rel" >>"$ABSENT_LIST"; fi
	done
	if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST"
	CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
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
		tmp="${target}.m033.$$"; install -o "$uid" -g "$gid" -m "$mode" "$src" "$tmp"; mv -f "$tmp" "$target"
	done
}

live_checks() {
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" ]] || return 1; [[ $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1; done
	for file in "${PHP_FILES[@]}"; do docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; done
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
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA
}

command_compare() { check_environment; validate_artifact; candidate_checks; compare_files; echo "M033_PRODUCTION_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() { check_environment; validate_artifact; candidate_checks; echo "M033_PRODUCTION_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} DB_CHANGED=NO"; }
command_apply() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; validate_artifact; candidate_checks; create_code_backup
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_code_backup && echo M033_PRODUCTION_AUTO_ROLLBACK=PASS >&2 || echo M033_PRODUCTION_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
	mutated=1; apply_files; live_checks; mutated=0; trap - ERR; save_state
	echo "M033_PRODUCTION_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_CHANGED=NO"
}
command_verify() { check_environment; validate_artifact; candidate_checks; live_checks || die 'LIVE_VERIFY_FAILED'; echo "M033_PRODUCTION_VERIFY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} DB_CHANGED=NO"; }
command_rollback() {
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; load_state
	local reapply="${BACKUP_ROOT}/m033-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"; local -a current=(); local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" ]] && current+=("$rel"); done
	if [[ ${#current[@]} -gt 0 ]]; then tar -czf "$reapply" -C "$TARGET_ROOT" "${current[@]}"; else tar -czf "$reapply" --files-from /dev/null; fi
	chmod 0600 "$reapply"; restore_code_backup || die 'ROLLBACK_RESTORE_FAILED'; [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M033_PRODUCTION_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}
command_status() { if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M033_PRODUCTION_STATUS=NOT_DEPLOYED'; fi; }

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
