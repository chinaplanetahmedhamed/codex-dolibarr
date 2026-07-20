#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M026-C01'
readonly COMMIT='faac0d94329481558b7a36992caadea1e75022b1'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m026-c01-production-faac0d9.tar.gz"
readonly ARTIFACT_SHA='bb64bb977627ec3675271392787db9878be1201587cce2911244e20299677d0d'
readonly TARGET_CONTAINER='vision_erp'
readonly TARGET_VOLUME='vision-os_dolibarr_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly TARGET_HTTP='https://erp.visiontrading.biz/'
readonly RELATIVE_FILE='chinaplanetlogistics/css/shipment-command-center.css'
readonly ARCHIVE_FILE="custom/${RELATIVE_FILE}"
readonly TARGET_FILE="${TARGET_ROOT}/${RELATIVE_FILE}"
readonly BACKUP_ROOT='/root/chinaplanetos-production-code-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-production-m026-c01-deploy'
readonly STATE_FILE="${STATE_ROOT}/m026-c01.state"
readonly LOCK_FILE="${STATE_ROOT}/m026-c01.lock"

CANDIDATE_ROOT=''
BACKUP=''
BACKUP_SHA=''

die() { echo "M026_C01_PRODUCTION_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

require_root() {
	[[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
	[[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
	[[ $(basename "$0") == 'chinaplanetos-production-m026-c01-hero-contrast-deploy' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

cleanup() {
	if [[ -n "$CANDIDATE_ROOT" && -d "$CANDIDATE_ROOT" ]]; then rm -rf "$CANDIDATE_ROOT"; fi
}

check_environment() {
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'PRODUCTION_ROOT_INVALID'
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true' ]] || die 'PRODUCTION_CONTAINER_NOT_RUNNING'
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'PRODUCTION_CUSTOM_MOUNT_MISMATCH'
	[[ -f "$TARGET_FILE" && ! -L "$TARGET_FILE" ]] || die 'TARGET_FILE_INVALID'
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die 'PRODUCTION_HTTP_UNHEALTHY'
}

validate_candidate() {
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die 'ARTIFACT_ABSENT'
	[[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die 'ARTIFACT_METADATA_INVALID'
	[[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die 'ARTIFACT_HASH_MISMATCH'
	local listing
	listing=$(tar -tzf "$ARTIFACT") || die 'ARTIFACT_TAR_INVALID'
	[[ "$listing" == "$ARCHIVE_FILE" ]] || die 'ARTIFACT_ALLOWLIST_MISMATCH'
	[[ "$listing" != /* && "$listing" != *'../'* ]] || die 'ARTIFACT_PATH_UNSAFE'
	CANDIDATE_ROOT=$(mktemp -d)
	tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
	local candidate="${CANDIDATE_ROOT}/${ARCHIVE_FILE}"
	[[ -f "$candidate" && ! -L "$candidate" ]] || die 'CANDIDATE_INVALID'
	[[ $(grep -Foc '.cpl-command-center .cpl-command-hero h1{color:#fff!important}' "$candidate") -eq 1 ]] || die 'H1_COLOR_CONTRACT_FAILED'
	[[ $(grep -Foc '.cpl-command-center .cpl-command-hero p{color:#dce7f5!important}' "$candidate") -eq 1 ]] || die 'SUBTITLE_COLOR_CONTRACT_FAILED'
}

verify_live() {
	[[ $(sha256 "$TARGET_FILE") == "$ARTIFACT_SHA_FILE" ]] || return 1
	[[ $(grep -Foc '.cpl-command-center .cpl-command-hero h1{color:#fff!important}' "$TARGET_FILE") -eq 1 ]] || return 1
	[[ $(grep -Foc '.cpl-command-center .cpl-command-hero p{color:#dce7f5!important}' "$TARGET_FILE") -eq 1 ]] || return 1
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]]
}

command_preflight() {
	check_environment; validate_candidate
	ARTIFACT_SHA_FILE=$(sha256 "${CANDIDATE_ROOT}/${ARCHIVE_FILE}")
	echo "M026_C01_PRODUCTION_PREFLIGHT=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILE_SHA=$ARTIFACT_SHA_FILE"
}

command_apply() {
	check_environment; install -d -o root -g root -m 0700 "$BACKUP_ROOT" "$STATE_ROOT"
	exec 9>"$LOCK_FILE"; flock -n 9 || die 'LOCKED'
	validate_candidate
	ARTIFACT_SHA_FILE=$(sha256 "${CANDIDATE_ROOT}/${ARCHIVE_FILE}")
	local stamp target_uid target_gid target_mode tmp mutated=0
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	BACKUP="${BACKUP_ROOT}/m026-c01-before-faac0d9-${stamp}.css"
	cp --preserve=mode,ownership,timestamps "$TARGET_FILE" "$BACKUP"
	chmod 0600 "$BACKUP"; BACKUP_SHA=$(sha256 "$BACKUP")
	target_uid=$(stat -c '%u' "$TARGET_FILE"); target_gid=$(stat -c '%g' "$TARGET_FILE"); target_mode=$(stat -c '%a' "$TARGET_FILE")
	tmp="${TARGET_FILE}.m026-c01.$$"
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then install -o "$target_uid" -g "$target_gid" -m "$target_mode" "$BACKUP" "$TARGET_FILE" && echo M026_C01_PRODUCTION_AUTO_ROLLBACK=PASS >&2 || echo M026_C01_PRODUCTION_AUTO_ROLLBACK=FAIL >&2; fi; rm -f "$tmp"; exit $rc' ERR
	install -o "$target_uid" -g "$target_gid" -m "$target_mode" "${CANDIDATE_ROOT}/${ARCHIVE_FILE}" "$tmp"
	mutated=1; mv -f "$tmp" "$TARGET_FILE"; verify_live; mutated=0; trap - ERR
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_COMMIT=$COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_FILE_SHA=$ARTIFACT_SHA_FILE
STATE_BACKUP=$BACKUP
STATE_BACKUP_SHA=$BACKUP_SHA
STATE_TARGET_UID=$target_uid
STATE_TARGET_GID=$target_gid
STATE_TARGET_MODE=$target_mode
STATE_DEPLOYED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
	echo "M026_C01_PRODUCTION_APPLY=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILE_SHA=$ARTIFACT_SHA_FILE BACKUP=$BACKUP BACKUP_SHA=$BACKUP_SHA"
}

command_verify() {
	check_environment; validate_candidate
	ARTIFACT_SHA_FILE=$(sha256 "${CANDIDATE_ROOT}/${ARCHIVE_FILE}")
	verify_live || die 'LIVE_VERIFY_FAILED'
	echo "M026_C01_PRODUCTION_VERIFY=PASS COMMIT=$COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILE_SHA=$ARTIFACT_SHA_FILE"
}

command_rollback() {
	check_environment
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" ]] || die 'STATE_ABSENT'
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_COMMIT" == "$COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die 'STATE_MISMATCH'
	[[ -f "$STATE_BACKUP" && ! -L "$STATE_BACKUP" && $(sha256 "$STATE_BACKUP") == "$STATE_BACKUP_SHA" ]] || die 'BACKUP_INVALID'
	local reapply="${BACKUP_ROOT}/m026-c01-reapply-faac0d9-$(date -u '+%Y%m%dT%H%M%SZ').css"
	cp --preserve=mode,ownership,timestamps "$TARGET_FILE" "$reapply"; chmod 0600 "$reapply"
	install -o "$STATE_TARGET_UID" -g "$STATE_TARGET_GID" -m "$STATE_TARGET_MODE" "$STATE_BACKUP" "$TARGET_FILE"
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || { install -o "$STATE_TARGET_UID" -g "$STATE_TARGET_GID" -m "$STATE_TARGET_MODE" "$reapply" "$TARGET_FILE"; die 'ROLLBACK_HTTP_FAILED'; }
	echo "M026_C01_PRODUCTION_ROLLBACK=PASS RESTORED=$STATE_BACKUP REAPPLY=$reapply REAPPLY_SHA=$(sha256 "$reapply")"
}

command_status() {
	if [[ -f "$STATE_FILE" ]]; then grep -E '^STATE_(RELEASE|COMMIT|ARTIFACT_SHA|FILE_SHA|BACKUP|BACKUP_SHA|TARGET_UID|TARGET_GID|TARGET_MODE|DEPLOYED_AT)=' "$STATE_FILE"; else echo 'M026_C01_PRODUCTION_STATUS=NOT_DEPLOYED'; fi
}

ARTIFACT_SHA_FILE=''
require_root "$@"
trap cleanup EXIT
case "$1" in
	preflight) command_preflight ;;
	apply) command_apply ;;
	verify) command_verify ;;
	rollback) command_rollback ;;
	status) command_status ;;
	*) die 'COMMAND_NOT_ALLOWED' ;;
esac
