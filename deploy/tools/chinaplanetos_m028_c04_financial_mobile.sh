#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M028-C04'
readonly COMMIT='a9afe048ddfb21e96297ae6cbe34b7ab181c8a98'
readonly SHORT_COMMIT='a9afe04'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m028-c04-${SHORT_COMMIT}.tar.gz"
readonly ARTIFACT_SHA='8fd9deababd889471fcfd3e1fdd6ccbc687fbd88be7ba5a66cec4eb3ead83b06'
readonly TEST_FILE='deploy/tests/test_m028_c04_financial_portfolio_mobile.php'
readonly -a RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/admin/financial_portfolio.php'
	'custom/chinaplanetcustomercenter/css/ux_fixes.css'
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "$TEST_FILE")

ENVIRONMENT=''
TARGET_CONTAINER=''
TARGET_VOLUME=''
TARGET_ROOT=''
TARGET_HTTP=''
BACKUP_ROOT=''
STATE_ROOT=''
STATE_FILE=''
LOCK_FILE=''
CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''

die() { echo "M028_C04_DEPLOY_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-m028-c04-financial-mobile-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

select_environment() {
	case "$1" in
		live-*)
			ENVIRONMENT='LIVE'
			TARGET_CONTAINER='vision_erp'
			TARGET_VOLUME='vision-os_dolibarr_custom'
			TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
			TARGET_HTTP='https://erp.visiontrading.biz/'
			BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
			STATE_ROOT='/var/lib/chinaplanetos-m028-c04-live-deploy'
			;;
		backup-*)
			ENVIRONMENT='BACKUP_ERP'
			TARGET_CONTAINER='erp-staging-dolibarr-1'
			TARGET_VOLUME='erp-staging_erp-staging_custom'
			TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
			TARGET_HTTP='http://127.0.0.1:18088/'
			BACKUP_ROOT='/root/backup-erp-code-backups'
			STATE_ROOT='/var/lib/chinaplanetos-m028-c04-backup-deploy'
			;;
		*) die 'COMMAND_NOT_ALLOWED' ;;
	esac
	STATE_FILE="${STATE_ROOT}/m028-c04.state"
	LOCK_FILE="${STATE_ROOT}/m028-c04.lock"
}

cleanup() {
	if [[ -n "$CANDIDATE_REMOTE" ]]; then docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die "${ENVIRONMENT}_ROOT_INVALID"
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die "${ENVIRONMENT}_CONTAINER_NOT_RUNNING"
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die "${ENVIRONMENT}_CUSTOM_MOUNT_MISMATCH"
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die "${ENVIRONMENT}_HTTP_UNHEALTHY"
}

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
	printf '%s\n' "${ALL_FILES[@]}" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	rm -f "$listing" "$expected" "$actual"
	CANDIDATE_REMOTE="/tmp/m028-c04-${ENVIRONMENT,,}-candidate-$$"
	docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/custom/chinaplanetcustomercenter/admin/financial_portfolio.php" >/dev/null || die 'CANDIDATE_PHP_LINT_FAILED'
	docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$TEST_FILE" >/dev/null || die 'CANDIDATE_TEST_FAILED'
}

compare_files() {
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		target_hash=$(sha256 "$target")
		if [[ "$target_hash" == "$release_hash" ]]; then state='CURRENT'; else state='UPDATE_REQUIRED'; fi
		echo "M028_C04_COMPARE ENV=$ENVIRONMENT FILE=$file STATE=$state TARGET_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

create_backup() {
	local stamp file rel
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="${BACKUP_ROOT}/m028-c04-before-${SHORT_COMMIT}-${stamp}.tar.gz"
	local -a relative_files=()
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; relative_files+=("$rel"); done
	tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${relative_files[@]}"
	chmod 0600 "$CODE_BACKUP"; CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP")
}

restore_backup() {
	[[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT"
}

apply_files() {
	local file rel source target uid gid mode tmp
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; source="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"
		[[ -f "$target" && ! -L "$target" ]] || die "TARGET_INVALID_${file//\//_}"
		uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target")
		tmp="${target}.m028-c04.$$"
		install -o "$uid" -g "$gid" -m "$mode" "$source" "$tmp"
		mv -f "$tmp" "$target"
	done
}

verify_live() {
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"
		[[ -f "$target" && ! -L "$target" ]] || return 1
		[[ $(sha256 "$target") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1
	done
	docker exec "$TARGET_CONTAINER" php -l '/var/www/html/custom/chinaplanetcustomercenter/admin/financial_portfolio.php' >/dev/null || return 1
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]]
}

save_state() {
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_COMMIT=$COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state() {
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_COMMIT" == "$COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA
}

command_compare() { check_environment; validate_artifact; compare_files; echo "M028_C04_${ENVIRONMENT}_COMPARE=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA"; }
command_preflight() { check_environment; validate_artifact; echo "M028_C04_${ENVIRONMENT}_PREFLIGHT=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=2 DB_CHANGED=NO"; }

command_apply() {
	check_environment; install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_artifact; create_backup
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then restore_backup && echo M028_C04_AUTO_ROLLBACK=PASS >&2 || echo M028_C04_AUTO_ROLLBACK=FAIL >&2; fi; exit $rc' ERR
	mutated=1; apply_files; verify_live; mutated=0; trap - ERR; save_state
	echo "M028_C04_${ENVIRONMENT}_APPLY=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA BACKUP=$CODE_BACKUP BACKUP_SHA=$CODE_BACKUP_SHA DB_CHANGED=NO"
}

command_verify() { check_environment; validate_artifact; verify_live || die 'LIVE_VERIFY_FAILED'; echo "M028_C04_${ENVIRONMENT}_VERIFY=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=2 DB_CHANGED=NO"; }

command_rollback() {
	check_environment; install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'; load_state
	local reapply="${BACKUP_ROOT}/m028-c04-reapply-${SHORT_COMMIT}-$(date -u '+%Y%m%dT%H%M%SZ').tar.gz"
	local -a relative_files=(); local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; relative_files+=("$rel"); done
	tar -czf "$reapply" -C "$TARGET_ROOT" "${relative_files[@]}"; chmod 0600 "$reapply"
	restore_backup || die 'ROLLBACK_RESTORE_FAILED'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'ROLLBACK_HTTP_FAILED'
	echo "M028_C04_${ENVIRONMENT}_ROLLBACK=PASS RESTORED=$CODE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|DEPLOYED_AT)=' "$STATE_FILE"; else echo "M028_C04_${ENVIRONMENT}_STATUS=NOT_DEPLOYED"; fi
}

require_root "$@"
select_environment "$1"
trap cleanup EXIT
case "$1" in
	live-compare|backup-compare) command_compare ;;
	live-preflight|backup-preflight) command_preflight ;;
	live-apply|backup-apply) command_apply ;;
	live-verify|backup-verify) command_verify ;;
	live-rollback|backup-rollback) command_rollback ;;
	live-status|backup-status) command_status ;;
	*) die 'COMMAND_NOT_ALLOWED' ;;
esac
