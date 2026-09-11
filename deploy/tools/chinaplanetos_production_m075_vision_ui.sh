#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M075-R01'
readonly RUNTIME_COMMIT='2de2d564ba3cb347a9ae3ae1402896d64c853e1b'
readonly ARTIFACT_NAME='m075-vision-os-ui-2de2d56.tar.gz'
readonly ARTIFACT_SHA='5a5a356798b7ab4410f03a86c01008d6c2d012fa238f2d12bbea5898630acb86'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="$INBOX/$ARTIFACT_NAME"
readonly TARGET_CONTAINER='vision_erp'
readonly TARGET_VOLUME='vision-os_dolibarr_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly TARGET_HTTP='https://erp.visiontrading.biz/'
readonly BACKUP_ROOT='/root/chinaplanetos-production-m075-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m075-production-r01'
readonly STATE_FILE="$STATE_ROOT/m075.state"
readonly LOCK_FILE="$STATE_ROOT/m075.lock"
readonly CONFIG_TOOL='deploy/tools/m075_vision_os_config.php'

readonly -a PHP_RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/admin_dashboard.php'
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
	'custom/chinaplanetoperations/admin/setup.php'
	'custom/chinaplanetoperations/index.php'
	'custom/chinaplanetoperations/operations/edit.php'
	'custom/chinaplanetoperations/operations/index.php'
	'custom/chinaplanetoperations/operations/view.php'
	'custom/chinaplanetoperations/sop/categories.php'
	'custom/chinaplanetoperations/sop/edit.php'
	'custom/chinaplanetoperations/sop/index.php'
	'custom/chinaplanetoperations/sop/view.php'
	'custom/chinaplanetoperations/tasks/board.php'
	'custom/visiontradingui/admin/setup.php'
	'custom/visiontradingui/core/hooks/actions_visiontradingui.class.php'
	'custom/visiontradingui/core/modules/modVisionTradingUI.class.php'
)
readonly -a RUNTIME_FILES=(
	'custom/chinaplanetcustomercenter/admin_dashboard.php'
	'custom/chinaplanetcustomercenter/css/admin_dashboard.css'
	'custom/chinaplanetcustomercenter/lib/admin_shell.lib.php'
	'custom/chinaplanetoperations/admin/setup.php'
	'custom/chinaplanetoperations/css/operations.css'
	'custom/chinaplanetoperations/index.php'
	'custom/chinaplanetoperations/operations/edit.php'
	'custom/chinaplanetoperations/operations/index.php'
	'custom/chinaplanetoperations/operations/view.php'
	'custom/chinaplanetoperations/sop/categories.php'
	'custom/chinaplanetoperations/sop/edit.php'
	'custom/chinaplanetoperations/sop/index.php'
	'custom/chinaplanetoperations/sop/view.php'
	'custom/chinaplanetoperations/tasks/board.php'
	'custom/visiontradingui/admin/setup.php'
	'custom/visiontradingui/core/hooks/actions_visiontradingui.class.php'
	'custom/visiontradingui/core/modules/modVisionTradingUI.class.php'
	'custom/visiontradingui/css/branding.css'
	'custom/visiontradingui/img/vision-system-logo.svg'
	'custom/visiontradingui/img/vision-system-mark.svg'
	'custom/visiontradingui/js/branding.js'
	'custom/visiontradingui/langs/en_US/visiontradingui.lang'
)
readonly -a SUPPORT_FILES=(
	'deploy/releases/m075-runtime-files.txt'
	'deploy/releases/m075-support-files.txt'
	'deploy/tests/test_m075_vision_ui_release.php'
	'deploy/tests/test_vision_whitelabel.php'
	'deploy/tools/m075_vision_os_config.php'
	'docs/MISSIONS/M075_VISION_CHINA_PLANET_OS_PRODUCTION_UI.md'
)
readonly -a ALL_FILES=("${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}")

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
CODE_BACKUP=''
CODE_BACKUP_SHA=''
ABSENT_LIST=''
ABSENT_LIST_SHA=''
CONFIG_BACKUP=''
CONFIG_BACKUP_SHA=''

die(){ echo "M075_PRODUCTION_FAIL=$1" >&2; exit 1; }
sha256(){ sha256sum "$1" | awk '{print $1}'; }

require_root(){
	[[ $(id -u) -eq 0 ]] || die ROOT_REQUIRED
	[[ $# -eq 1 ]] || die EXACTLY_ONE_COMMAND_REQUIRED
	[[ $(basename "$0") == 'chinaplanetos-production-m075-vision-ui' ]] || die HELPER_PATH_NOT_ALLOWED
	case "$1" in compare|preflight|apply|verify|rollback|status) ;; *) die COMMAND_NOT_ALLOWED ;; esac
}

cleanup(){
	if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m075-production-candidate-* ]]; then docker exec -u 0:0 "$TARGET_CONTAINER" rm -rf -- "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true; fi
	if [[ -n "$CANDIDATE_ROOT" && "$CANDIDATE_ROOT" == /tmp/m075-production-candidate.* && -d "$CANDIDATE_ROOT" && ! -L "$CANDIDATE_ROOT" ]]; then rm -rf -- "$CANDIDATE_ROOT"; fi
}

check_environment(){
	[[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die PRODUCTION_ROOT_INVALID
	[[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == true ]] || die PRODUCTION_CONTAINER_NOT_RUNNING
	[[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "$TARGET_VOLUME|true" ]] || die PRODUCTION_CUSTOM_MOUNT_MISMATCH
	[[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP") =~ ^(200|302)$ ]] || die PRODUCTION_HTTP_UNHEALTHY
}

ensure_state_dirs(){
	local path
	for path in "$BACKUP_ROOT" "$STATE_ROOT"; do
		if [[ -e "$path" || -L "$path" ]]; then [[ -d "$path" && ! -L "$path" && $(stat -c '%U:%G %a' "$path") == 'root:root 700' ]] || die PRIVATE_ROOT_INVALID; else install -d -o root -g root -m 0700 "$path"; fi
	done
	[[ $(df -Pk "$BACKUP_ROOT" | awk 'NR==2{print $4}') -ge 524288 ]] || die FREE_SPACE_LOW
}

validate_artifact(){
	[[ -f "$ARTIFACT" && ! -L "$ARTIFACT" ]] || die ARTIFACT_ABSENT
	[[ $(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]] || die ARTIFACT_METADATA_INVALID
	[[ $(sha256 "$ARTIFACT") == "$ARTIFACT_SHA" ]] || die ARTIFACT_HASH_MISMATCH
	local listing expected actual
	listing=$(mktemp); expected=$(mktemp); actual=$(mktemp)
	tar -tzf "$ARTIFACT" >"$listing" || die ARTIFACT_TAR_INVALID
	[[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ {print; exit}' "$listing") ]] || die ARTIFACT_PATH_UNSAFE
	[[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die ARTIFACT_DUPLICATE_ENTRY
	CANDIDATE_ROOT=$(mktemp -d /tmp/m075-production-candidate.XXXXXX)
	tar -xzf "$ARTIFACT" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT"
	[[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die ARTIFACT_SYMLINK_FORBIDDEN
	printf '%s\n' "${ALL_FILES[@]}" | LC_ALL=C sort >"$expected"
	find "$CANDIDATE_ROOT" -type f -printf '%P\n' | LC_ALL=C sort >"$actual"
	diff -u "$expected" "$actual" >/dev/null || die ARTIFACT_ALLOWLIST_MISMATCH
	local file
	for file in "${ALL_FILES[@]}"; do [[ -f "$CANDIDATE_ROOT/$file" && ! -L "$CANDIDATE_ROOT/$file" ]] || die ARTIFACT_FILE_INVALID; done
	cmp -s "$CANDIDATE_ROOT/deploy/releases/m075-runtime-files.txt" <(printf '%s\n' "${RUNTIME_FILES[@]}") || die RUNTIME_MANIFEST_MISMATCH
	rm -f "$listing" "$expected" "$actual"
}

candidate_checks(){
	CANDIDATE_REMOTE="/tmp/m075-production-candidate-$$"
	docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
	local file
	for file in "${PHP_RUNTIME_FILES[@]}" "${SUPPORT_FILES[2]}" "${SUPPORT_FILES[3]}" "$CONFIG_TOOL"; do docker exec -u 0:0 "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$file" >/dev/null || die CANDIDATE_LINT_FAILED; done
	docker exec -u 0:0 "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_m075_vision_ui_release.php" >/dev/null || die UI_CONTRACT_FAILED
	docker exec -u 0:0 "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/deploy/tests/test_vision_whitelabel.php" >/dev/null || die BRAND_CONTRACT_FAILED
}

config_action(){ docker exec -i -u 0:0 -e M075_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$CONFIG_TOOL" "$1"; }

compare_files(){
	local file rel target release_hash target_hash state
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; target="$TARGET_ROOT/$rel"; release_hash=$(sha256 "$CANDIDATE_ROOT/$file")
		if [[ ! -e "$target" && ! -L "$target" ]]; then echo "M075_PRODUCTION_COMPARE FILE=$file STATE=ABSENT RELEASE_SHA=$release_hash"; continue; fi
		[[ -f "$target" && ! -L "$target" ]] || die TARGET_INVALID
		target_hash=$(sha256 "$target"); if [[ "$target_hash" == "$release_hash" ]]; then state=CURRENT; else state=UPDATE_REQUIRED; fi
		echo "M075_PRODUCTION_COMPARE FILE=$file STATE=$state PRODUCTION_SHA=$target_hash RELEASE_SHA=$release_hash"
	done
}

create_backups(){
	local stamp file rel target
	stamp=$(date -u '+%Y%m%dT%H%M%SZ')
	CODE_BACKUP="$BACKUP_ROOT/m075-code-before-${RUNTIME_COMMIT:0:7}-$stamp.tar.gz"
	ABSENT_LIST="$BACKUP_ROOT/m075-absent-before-${RUNTIME_COMMIT:0:7}-$stamp.txt"
	CONFIG_BACKUP="$BACKUP_ROOT/m075-config-before-${RUNTIME_COMMIT:0:7}-$stamp.json"
	: >"$ABSENT_LIST"; local -a existing=()
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; if [[ -f "$target" && ! -L "$target" ]]; then existing+=("$rel"); elif [[ ! -e "$target" && ! -L "$target" ]]; then printf '%s\n' "$rel" >>"$ABSENT_LIST"; else die TARGET_INVALID; fi; done
	if [[ ${#existing[@]} -gt 0 ]]; then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}"; else tar -czf "$CODE_BACKUP" --files-from /dev/null; fi
	config_action snapshot >"$CONFIG_BACKUP"
	chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST" "$CONFIG_BACKUP"
	CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP"); ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST"); CONFIG_BACKUP_SHA=$(sha256 "$CONFIG_BACKUP")
}

restore_backups(){
	[[ -f "$CODE_BACKUP" && ! -L "$CODE_BACKUP" && $(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA" ]] || return 1
	[[ -f "$ABSENT_LIST" && ! -L "$ABSENT_LIST" && $(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]] || return 1
	[[ -f "$CONFIG_BACKUP" && ! -L "$CONFIG_BACKUP" && $(sha256 "$CONFIG_BACKUP") == "$CONFIG_BACKUP_SHA" ]] || return 1
	local file rel target
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; target="$TARGET_ROOT/$rel"; [[ ! -L "$target" ]] || return 1; rm -f -- "$target"; done
	[[ $(tar -tzf "$CODE_BACKUP" | wc -l) -eq 0 ]] || tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT"
	config_action restore <"$CONFIG_BACKUP" >/dev/null
}

apply_files(){
	local file rel src target parent uid gid mode stage
	for file in "${RUNTIME_FILES[@]}"; do
		rel=${file#custom/}; src="$CANDIDATE_ROOT/$file"; target="$TARGET_ROOT/$rel"; parent=$(dirname "$target")
		[[ -d "$parent" && ! -L "$parent" ]] || die TARGET_PARENT_INVALID
		if [[ -f "$target" && ! -L "$target" ]]; then uid=$(stat -c '%u' "$target"); gid=$(stat -c '%g' "$target"); mode=$(stat -c '%a' "$target"); elif [[ ! -e "$target" && ! -L "$target" ]]; then uid=$(stat -c '%u' "$parent"); gid=$(stat -c '%g' "$parent"); mode=644; else die TARGET_INVALID; fi
		stage="$target.m075.$$"; install -o "$uid" -g "$gid" -m "$mode" "$src" "$stage"; [[ $(sha256 "$stage") == $(sha256 "$src") ]] || die STAGE_HASH_MISMATCH; mv -f "$stage" "$target"
	done
}

live_checks(){
	local file rel
	for file in "${RUNTIME_FILES[@]}"; do rel=${file#custom/}; [[ -f "$TARGET_ROOT/$rel" && ! -L "$TARGET_ROOT/$rel" && $(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$file") ]] || return 1; done
	for file in "${PHP_RUNTIME_FILES[@]}"; do docker exec -u 0:0 "$TARGET_CONTAINER" php -l "/var/www/html/$file" >/dev/null || return 1; done
	config_action verify >/dev/null || return 1
	curl -fsSL --max-time 15 "$TARGET_HTTP" | grep -Fq 'Vision-China Planet OS'
}

save_state(){
	local status=$1
	cat >"$STATE_FILE" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=$status
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_CONFIG_BACKUP=$CONFIG_BACKUP
STATE_CONFIG_BACKUP_SHA=$CONFIG_BACKUP_SHA
STATE_UPDATED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
	chmod 0600 "$STATE_FILE"
}

load_state(){
	[[ -f "$STATE_FILE" && ! -L "$STATE_FILE" && $(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]] || die STATE_INVALID
	# shellcheck disable=SC1090
	source "$STATE_FILE"
	[[ "$STATE_RELEASE" == "$RELEASE" && "$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT" && "$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]] || die STATE_MISMATCH
	case "$STATE_STATUS" in BACKED_UP|DEPLOYED|ROLLED_BACK|APPLY_FAILED_AUTO_ROLLED_BACK|APPLY_FAILED_RECOVERY_REQUIRED) ;; *) die STATE_STATUS_INVALID ;; esac
	CODE_BACKUP=$STATE_CODE_BACKUP; CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA; ABSENT_LIST=$STATE_ABSENT_LIST; ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA; CONFIG_BACKUP=$STATE_CONFIG_BACKUP; CONFIG_BACKUP_SHA=$STATE_CONFIG_BACKUP_SHA
	for path in "$CODE_BACKUP" "$ABSENT_LIST" "$CONFIG_BACKUP"; do [[ "$path" == "$BACKUP_ROOT"/m075-* ]] || die STATE_PATH_INVALID; done
}

command_compare(){ check_environment; validate_artifact; candidate_checks; compare_files; echo "M075_PRODUCTION_COMPARE=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA PRODUCTION_MUTATED=NO"; }
command_preflight(){ check_environment; validate_artifact; candidate_checks; echo "M075_PRODUCTION_PREFLIGHT=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} BACKUP_REQUIRED=YES PRODUCTION_MUTATED=NO"; }
command_apply(){
	check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die LOCKED; validate_artifact; candidate_checks
	if [[ -f "$STATE_FILE" ]]; then load_state; [[ "$STATE_STATUS" != DEPLOYED ]] || die ALREADY_DEPLOYED; fi
	create_backups; save_state BACKED_UP
	local mutated=0
	trap 'rc=$?; if [[ $mutated -eq 1 ]]; then if restore_backups; then save_state APPLY_FAILED_AUTO_ROLLED_BACK; echo M075_PRODUCTION_AUTO_ROLLBACK=PASS >&2; else save_state APPLY_FAILED_RECOVERY_REQUIRED; echo M075_PRODUCTION_AUTO_ROLLBACK=FAIL_RECOVERY_REQUIRED >&2; fi; fi; exit $rc' ERR
	mutated=1; apply_files; config_action apply >/dev/null; live_checks; mutated=0; trap - ERR; save_state DEPLOYED
	echo "M075_PRODUCTION_APPLY=PASS RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA CONFIG_BACKUP=$CONFIG_BACKUP CONFIG_BACKUP_SHA=$CONFIG_BACKUP_SHA ROLLBACK=READY"
}
command_verify(){ check_environment; validate_artifact; candidate_checks; live_checks || die LIVE_VERIFY_FAILED; load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die STATE_NOT_DEPLOYED; echo "M075_PRODUCTION_VERIFY=PASS STATE=DEPLOYED BRAND=Vision-China_Planet_OS"; }
command_rollback(){ check_environment; ensure_state_dirs; exec 9>"$LOCK_FILE"; flock -n 9 || die LOCKED; validate_artifact; candidate_checks; load_state; [[ "$STATE_STATUS" == DEPLOYED ]] || die STATE_NOT_DEPLOYED; restore_backups || die ROLLBACK_RESTORE_FAILED; save_state ROLLED_BACK; echo "M075_PRODUCTION_ROLLBACK=PASS CODE_RESTORED=$CODE_BACKUP CONFIG_RESTORED=$CONFIG_BACKUP"; }
command_status(){ if [[ -f "$STATE_FILE" ]]; then load_state; grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|CONFIG_BACKUP|CONFIG_BACKUP_SHA|UPDATED_AT)=' "$STATE_FILE"; else echo M075_PRODUCTION_STATUS=NOT_DEPLOYED; fi; }

require_root "$@"
trap cleanup EXIT
case "$1" in
	compare) command_compare ;;
	preflight) command_preflight ;;
	apply) command_apply ;;
	verify) command_verify ;;
	rollback) command_rollback ;;
	status) command_status ;;
esac
