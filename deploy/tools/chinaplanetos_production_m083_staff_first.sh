#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M083-R01'
readonly RUNTIME_COMMIT='ecf151802ca8396c51214d758c6150cf1db49a9d'
readonly ARTIFACT_NAME='m083-r01-staff-first-ecf1518.tar.gz'
readonly ARTIFACT_SHA='__ARTIFACT_SHA__'
readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="$INBOX/$ARTIFACT_NAME"
readonly TARGET_CONTAINER='vision_erp'
readonly TARGET_DB_CONTAINER='vision_db'
readonly TARGET_VOLUME='vision-os_dolibarr_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'
readonly TARGET_HTTP='https://erp.visiontrading.biz'
readonly CODE_BACKUP_ROOT='/root/chinaplanetos-production-m083-code-backups'
readonly DB_BACKUP_ROOT='/root/chinaplanetos-production-db-backups'
readonly STATE_ROOT='/var/lib/chinaplanetos-m083-production-r01'
readonly STATE_FILE="$STATE_ROOT/m083.state"
readonly LOCK_FILE="$STATE_ROOT/m083.lock"
readonly RUNTIME_MANIFEST='deploy/releases/m083-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m083-support-files.txt'
readonly BASELINE_MANIFEST='deploy/releases/m083-production-baseline.sha256'
readonly SCHEMA='deploy/migrations/m083_staff_first_production_schema.php'
readonly PREFLIGHT='deploy/tools/m083_production_preflight.php'
readonly MIN_FREE_KB=1048576

readonly -a SOURCE_TESTS=(
 'deploy/tests/test_m077_connected_finance_shipment.php'
 'deploy/tests/test_m078_local_shipment_customer_flow.php'
 'deploy/tests/test_m079_commercial_workflow_source.php'
 'deploy/tests/test_m079_staff_dashboard_source.php'
 'deploy/tests/test_m080_stage1_billing_workflow.php'
 'deploy/tests/test_m081_customer_portal_clarity.php'
 'deploy/tests/test_m082_staff_first_local_completion.php'
)

CANDIDATE_ROOT=''; ARTIFACT_COPY=''; CANDIDATE_REMOTE=''; ROUTE_BODY=''
CODE_BACKUP=''; CODE_BACKUP_SHA=''; ABSENT_LIST=''; ABSENT_LIST_SHA=''; DB_BACKUP=''; DB_BACKUP_SHA=''; SCHEMA_PRESTATE=''
declare -a RUNTIME_FILES=() SUPPORT_FILES=()
die(){ echo "M083_PRODUCTION_FAIL=$1" >&2; return 1; }
sha256(){ sha256sum "$1" | awk '{print $1}'; }

require_root(){
 [[ $(id -u) -eq 0 ]]||die ROOT_REQUIRED
 [[ $# -eq 1 ]]||die EXACTLY_ONE_COMMAND_REQUIRED
 [[ $(basename "$0") == 'chinaplanetos-production-m083-staff-first' ]]||die HELPER_PATH_NOT_ALLOWED
 case "$1" in compare|preflight|apply|verify|rollback|status);;*)die COMMAND_NOT_ALLOWED;;esac
}
cleanup(){
 [[ -z "$CANDIDATE_REMOTE" ]]||docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1||true
 [[ -z "$CANDIDATE_ROOT" || "$CANDIDATE_ROOT" != /root/m083-candidate.* || ! -d "$CANDIDATE_ROOT" || -L "$CANDIDATE_ROOT" ]]||rm -rf -- "$CANDIDATE_ROOT"
 [[ -z "$ARTIFACT_COPY" || "$ARTIFACT_COPY" != /root/m083-artifact.* || ! -f "$ARTIFACT_COPY" || -L "$ARTIFACT_COPY" ]]||rm -f -- "$ARTIFACT_COPY"
 [[ -z "$ROUTE_BODY" || "$ROUTE_BODY" != /root/m083-route.* || ! -f "$ROUTE_BODY" || -L "$ROUTE_BODY" ]]||rm -f -- "$ROUTE_BODY"
}
check_environment(){
 [[ "$TARGET_CONTAINER|$TARGET_DB_CONTAINER|$TARGET_VOLUME|$TARGET_HTTP" == 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp.visiontrading.biz' ]]||die TARGET_CONSTANT_MISMATCH
 [[ -d "$TARGET_ROOT"&&! -L "$TARGET_ROOT" ]]||die PRODUCTION_ROOT_INVALID
 [[ $(docker inspect -f '{{.State.Running}}' "$TARGET_CONTAINER" 2>/dev/null) == true ]]||die ERP_CONTAINER_NOT_RUNNING
 [[ $(docker inspect -f '{{.State.Running}}' "$TARGET_DB_CONTAINER" 2>/dev/null) == true ]]||die DB_CONTAINER_NOT_RUNNING
 [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "$TARGET_VOLUME|true" ]]||die CUSTOM_MOUNT_MISMATCH
 [[ -d "$INBOX"&&! -L "$INBOX"&&$(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]]||die INBOX_METADATA_INVALID
 [[ $(df -Pk "$TARGET_ROOT"|awk 'NR==2{print $4}') -ge $MIN_FREE_KB ]]||die TARGET_DISK_LOW
 docker exec "$TARGET_DB_CONTAINER" sh -c 'command -v mariadb-dump >/dev/null 2>&1 || command -v mysqldump >/dev/null 2>&1'||die DB_DUMP_TOOL_ABSENT
 [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$TARGET_HTTP/") =~ ^(200|302)$ ]]||die PRODUCTION_HTTP_UNHEALTHY
}
ensure_state_dirs(){ local p;for p in "$CODE_BACKUP_ROOT" "$DB_BACKUP_ROOT" "$STATE_ROOT";do if [[ -e "$p"||-L "$p" ]];then [[ -d "$p"&&! -L "$p"&&$(stat -c '%U:%G %a' "$p") == 'root:root 700' ]]||die PRIVATE_ROOT_INVALID;else install -d -o root -g root -m 0700 "$p";fi;done; }
validate_manifest(){
 local file=$1 pattern=$2
 [[ -f "$CANDIDATE_ROOT/$file"&&! -L "$CANDIDATE_ROOT/$file" ]]||die MANIFEST_INVALID
 [[ -z $(awk 'NF==0||/^\//||/(^|\/)\.\.($|\/)/||$0!~/^[A-Za-z0-9_./-]+$/{print;exit}' "$CANDIDATE_ROOT/$file") ]]||die MANIFEST_ENTRY_INVALID
 [[ -z $(LC_ALL=C sort "$CANDIDATE_ROOT/$file"|uniq -d) ]]||die MANIFEST_DUPLICATE
 [[ -z $(awk -v p="$pattern" '$0!~p{print;exit}' "$CANDIDATE_ROOT/$file") ]]||die MANIFEST_SCOPE_INVALID
}
validate_artifact(){
 [[ -f "$ARTIFACT"&&! -L "$ARTIFACT"&&$(stat -c '%U:%G %a' "$ARTIFACT") == 'codex-deploy:codex-deploy 644' ]]||die ARTIFACT_METADATA_INVALID
 ARTIFACT_COPY=$(mktemp /root/m083-artifact.XXXXXX);install -o root -g root -m 0600 "$ARTIFACT" "$ARTIFACT_COPY"
 [[ $(sha256 "$ARTIFACT_COPY") == "$ARTIFACT_SHA" ]]||die ARTIFACT_HASH_MISMATCH
 local listing expected actual stderr;listing=$(mktemp);expected=$(mktemp);actual=$(mktemp);stderr=$(mktemp)
 tar -tzf "$ARTIFACT_COPY">"$listing" 2>"$stderr"||die ARTIFACT_TAR_INVALID
 [[ ! -s "$stderr" ]]||die ARTIFACT_METADATA_UNSUPPORTED
 [[ -z $(tar -tvzf "$ARTIFACT_COPY"|awk 'substr($1,1,1)!="-"{print;exit}') ]]||die ARTIFACT_TYPE_FORBIDDEN
 [[ -z $(awk '/^\//||/(^|\/)\.\.($|\/)/||/\/$/{print;exit}' "$listing") ]]||die ARTIFACT_PATH_UNSAFE
 [[ -z $(LC_ALL=C sort "$listing"|uniq -d) ]]||die ARTIFACT_DUPLICATE
 [[ -z $(grep -E '(^|/)(\.env|conf\.php|\.DS_Store|[^/]*\.(pem|key|p12|dump|log|bak|tmp))$' "$listing"||true) ]]||die ARTIFACT_FORBIDDEN_FILE
 CANDIDATE_ROOT=$(mktemp -d /root/m083-candidate.XXXXXX);tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" 2>"$stderr"||die ARTIFACT_EXTRACT_FAILED
 [[ ! -s "$stderr"&&-z $(find "$CANDIDATE_ROOT" -type l -print -quit)&&-z $(find "$CANDIDATE_ROOT" ! -type d ! -type f -print -quit) ]]||die ARTIFACT_CONTENT_INVALID
 validate_manifest "$RUNTIME_MANIFEST" '^custom/(chinaplanetcustomercenter|chinaplanetlogistics|chinaplanetoperations)/'
 validate_manifest "$SUPPORT_MANIFEST" '^(deploy/(migrations|tests|tools)|docs/MISSIONS)/'
 mapfile -t RUNTIME_FILES<"$CANDIDATE_ROOT/$RUNTIME_MANIFEST";mapfile -t SUPPORT_FILES<"$CANDIDATE_ROOT/$SUPPORT_MANIFEST"
 printf '%s\n' "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}" "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" "$BASELINE_MANIFEST"|LC_ALL=C sort>"$expected"
 find "$CANDIDATE_ROOT" -type f -printf '%P\n'|LC_ALL=C sort>"$actual";diff -u "$expected" "$actual">/dev/null||die ARTIFACT_ALLOWLIST_MISMATCH
 [[ $(wc -l<"$CANDIDATE_ROOT/$BASELINE_MANIFEST") -eq ${#RUNTIME_FILES[@]} ]]||die BASELINE_COUNT_MISMATCH
 rm -f "$listing" "$expected" "$actual" "$stderr"
}
candidate_checks(){
 CANDIDATE_REMOTE="/tmp/m083-candidate-$$";docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE";docker exec "$TARGET_CONTAINER" mkdir -m 0700 "$CANDIDATE_REMOTE";docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
 local f;for f in "${RUNTIME_FILES[@]}" "${SUPPORT_FILES[@]}";do [[ "$f" != *.php ]]||docker exec "$TARGET_CONTAINER" php -l "$CANDIDATE_REMOTE/$f">/dev/null||die CANDIDATE_LINT_FAILED;done
 for f in "${SOURCE_TESTS[@]}";do docker exec "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$f">/dev/null||die CANDIDATE_TEST_FAILED;done
}
expected_baseline(){ awk -v p="$1" '$2==p{print $1}' "$CANDIDATE_ROOT/$BASELINE_MANIFEST"; }
compare_files(){
 local f rel target release_hash target_hash baseline state
 for f in "${RUNTIME_FILES[@]}";do rel=${f#custom/};target="$TARGET_ROOT/$rel";release_hash=$(sha256 "$CANDIDATE_ROOT/$f");baseline=$(expected_baseline "$f");[[ -n "$baseline" ]]||die BASELINE_ENTRY_MISSING
  if [[ ! -e "$target"&&! -L "$target" ]];then [[ "$baseline" == ABSENT ]]||die PRODUCTION_DRIFT_ABSENT;echo "M083_PRODUCTION_COMPARE FILE=$f STATE=CREATE_REQUIRED RELEASE_SHA=$release_hash";continue;fi
  [[ -f "$target"&&! -L "$target" ]]||die TARGET_INVALID;target_hash=$(sha256 "$target");if [[ "$target_hash" == "$release_hash" ]];then state=CURRENT;elif [[ "$target_hash" == "$baseline" ]];then state=UPDATE_REQUIRED;else die PRODUCTION_DRIFT_HASH;fi
  echo "M083_PRODUCTION_COMPARE FILE=$f STATE=$state PRODUCTION_SHA=$target_hash RELEASE_SHA=$release_hash"
 done
}
schema_state(){ local out rc=0;set +e;out=$(docker exec -e M083_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" check 2>&1);rc=$?;set -e;if [[ $rc -eq 0&&"$out" == 'M083_SCHEMA_CHECK=PASS TABLES=6 GUARDS=4' ]];then echo READY;elif [[ $rc -eq 2&&"$out" == 'M083_SCHEMA_CHECK=ABSENT TABLES=0 GUARDS=ABSENT' ]];then echo ABSENT;else echo "$out" >&2;die SCHEMA_INVALID;fi; }
run_preflight(){ docker exec -e M083_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$PREFLIGHT"; }
create_code_backup(){
 local stamp f rel target;stamp=$(date -u '+%Y%m%dT%H%M%SZ');CODE_BACKUP="$CODE_BACKUP_ROOT/m083-code-before-${RUNTIME_COMMIT:0:7}-$stamp.tar.gz";ABSENT_LIST="$CODE_BACKUP_ROOT/m083-absent-before-${RUNTIME_COMMIT:0:7}-$stamp.txt";:>"$ABSENT_LIST";local -a existing=()
 for f in "${RUNTIME_FILES[@]}";do rel=${f#custom/};target="$TARGET_ROOT/$rel";if [[ -f "$target"&&! -L "$target" ]];then existing+=("$rel");elif [[ ! -e "$target"&&! -L "$target" ]];then printf '%s\n' "$rel">>"$ABSENT_LIST";else die TARGET_INVALID;fi;done
 if [[ ${#existing[@]} -gt 0 ]];then tar -czf "$CODE_BACKUP" -C "$TARGET_ROOT" "${existing[@]}";else tar -czf "$CODE_BACKUP" --files-from /dev/null;fi;chmod 0600 "$CODE_BACKUP" "$ABSENT_LIST";CODE_BACKUP_SHA=$(sha256 "$CODE_BACKUP");ABSENT_LIST_SHA=$(sha256 "$ABSENT_LIST")
}
create_db_backup(){ DB_BACKUP="$DB_BACKUP_ROOT/m083-before-${RUNTIME_COMMIT:0:7}-$(date -u '+%Y%m%dT%H%M%SZ').sql";docker exec "$TARGET_DB_CONTAINER" sh -eu -c 'db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}};user=${MARIADB_USER:-${MYSQL_USER:-root}};pass=${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}};[ -n "$db" ]&&[ -n "$pass" ];if command -v mariadb-dump>/dev/null 2>&1;then dump=mariadb-dump;else dump=mysqldump;fi;MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables --hex-blob --routines --triggers --events -u "$user" "$db"'>"$DB_BACKUP"||die DATABASE_BACKUP_FAILED;[[ -s "$DB_BACKUP" ]]||die DATABASE_BACKUP_EMPTY;chmod 0600 "$DB_BACKUP";DB_BACKUP_SHA=$(sha256 "$DB_BACKUP"); }
restore_code(){
 [[ -f "$CODE_BACKUP"&&! -L "$CODE_BACKUP"&&$(sha256 "$CODE_BACKUP") == "$CODE_BACKUP_SHA"&&-f "$ABSENT_LIST"&&! -L "$ABSENT_LIST"&&$(sha256 "$ABSENT_LIST") == "$ABSENT_LIST_SHA" ]]||return 1
 local f rel target;for f in "${RUNTIME_FILES[@]}";do rel=${f#custom/};target="$TARGET_ROOT/$rel";[[ ! -d "$target"&&! -L "$target" ]]||return 1;rm -f -- "$target"||return 1;done
 [[ $(tar -tzf "$CODE_BACKUP"|wc -l) -eq 0 ]]||tar -xzf "$CODE_BACKUP" --same-owner --same-permissions -C "$TARGET_ROOT"||return 1
}
apply_files(){ local f rel src target parent uid gid mode stage;for f in "${RUNTIME_FILES[@]}";do rel=${f#custom/};src="$CANDIDATE_ROOT/$f";target="$TARGET_ROOT/$rel";parent=$(dirname "$target");[[ -d "$parent"&&! -L "$parent" ]]||die TARGET_PARENT_INVALID;if [[ -f "$target"&&! -L "$target" ]];then uid=$(stat -c %u "$target");gid=$(stat -c %g "$target");mode=$(stat -c %a "$target");else uid=$(stat -c %u "$parent");gid=$(stat -c %g "$parent");mode=644;fi;stage="$target.m083.$$";install -o "$uid" -g "$gid" -m "$mode" "$src" "$stage";[[ $(sha256 "$stage") == $(sha256 "$src") ]]||die STAGE_HASH_MISMATCH;mv -f "$stage" "$target";done; }
apply_schema(){ docker exec -e M083_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" apply>/dev/null||die SCHEMA_APPLY_FAILED; }
rollback_schema_empty(){ docker exec -e M083_DOL_DOCUMENT_ROOT=/var/www/html "$TARGET_CONTAINER" php "$CANDIDATE_REMOTE/$SCHEMA" rollback-empty>/dev/null; }
route_checks(){ local route code;ROUTE_BODY=$(mktemp /root/m083-route.XXXXXX);for route in /custom/chinaplanetoperations/workspace.php /custom/chinaplanetcustomercenter/board.php /custom/chinaplanetlogistics/list.php;do code=$(curl -sS -L -o "$ROUTE_BODY" -w '%{http_code}' --max-time 20 --max-redirs 2 "$TARGET_HTTP$route")||return 1;[[ "$code" =~ ^(200|302|401|403)$ ]]||return 1;! grep -Eq 'Fatal error|Uncaught [A-Za-z]+Exception' "$ROUTE_BODY"||return 1;done;rm -f "$ROUTE_BODY";ROUTE_BODY=''; }
live_checks(){ local f rel;for f in "${RUNTIME_FILES[@]}";do rel=${f#custom/};[[ -f "$TARGET_ROOT/$rel"&&! -L "$TARGET_ROOT/$rel"&&$(sha256 "$TARGET_ROOT/$rel") == $(sha256 "$CANDIDATE_ROOT/$f") ]]||return 1;[[ "$f" != *.php ]]||docker exec "$TARGET_CONTAINER" php -l "/var/www/html/$f">/dev/null||return 1;done;[[ $(schema_state) == READY ]]||return 1;run_preflight>/dev/null||return 1;route_checks; }
save_state(){ local status=$1 stage="$STATE_FILE.stage.$$";cat >"$stage" <<EOF
STATE_RELEASE=$RELEASE
STATE_STATUS=$status
STATE_RUNTIME_COMMIT=$RUNTIME_COMMIT
STATE_ARTIFACT_SHA=$ARTIFACT_SHA
STATE_CODE_BACKUP=$CODE_BACKUP
STATE_CODE_BACKUP_SHA=$CODE_BACKUP_SHA
STATE_ABSENT_LIST=$ABSENT_LIST
STATE_ABSENT_LIST_SHA=$ABSENT_LIST_SHA
STATE_DB_BACKUP=$DB_BACKUP
STATE_DB_BACKUP_SHA=$DB_BACKUP_SHA
STATE_SCHEMA_PRESTATE=$SCHEMA_PRESTATE
STATE_UPDATED_AT=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
EOF
 chmod 0600 "$stage";sync -f "$stage";mv -f "$stage" "$STATE_FILE";sync -f "$STATE_ROOT"; }
load_state(){ [[ -f "$STATE_FILE"&&! -L "$STATE_FILE"&&$(stat -c '%U:%G %a' "$STATE_FILE") == 'root:root 600' ]]||die STATE_INVALID;source "$STATE_FILE";[[ "$STATE_RELEASE" == "$RELEASE"&&"$STATE_RUNTIME_COMMIT" == "$RUNTIME_COMMIT"&&"$STATE_ARTIFACT_SHA" == "$ARTIFACT_SHA" ]]||die STATE_MISMATCH;case "$STATE_STATUS" in APPLY_IN_PROGRESS|DEPLOYED|ROLLED_BACK|APPLY_FAILED_AUTO_ROLLED_BACK|APPLY_FAILED_RECOVERY_REQUIRED);;*)die STATE_STATUS_INVALID;;esac;CODE_BACKUP=$STATE_CODE_BACKUP;CODE_BACKUP_SHA=$STATE_CODE_BACKUP_SHA;ABSENT_LIST=$STATE_ABSENT_LIST;ABSENT_LIST_SHA=$STATE_ABSENT_LIST_SHA;DB_BACKUP=$STATE_DB_BACKUP;DB_BACKUP_SHA=$STATE_DB_BACKUP_SHA;SCHEMA_PRESTATE=$STATE_SCHEMA_PRESTATE; }
automatic_rollback(){ set +e;restore_code;local code_rc=$?;rollback_schema_empty;local schema_rc=$?;if [[ $code_rc -eq 0&&$schema_rc -eq 0 ]];then save_state APPLY_FAILED_AUTO_ROLLED_BACK;echo M083_PRODUCTION_AUTO_ROLLBACK=PASS >&2;else save_state APPLY_FAILED_RECOVERY_REQUIRED;echo "M083_PRODUCTION_AUTO_ROLLBACK=FAIL CODE_RC=$code_rc SCHEMA_RC=$schema_rc DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA" >&2;fi;set -e; }
command_compare(){ check_environment;validate_artifact;candidate_checks;compare_files;echo "M083_PRODUCTION_COMPARE=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA SCHEMA=$(schema_state) PRODUCTION_MUTATED=NO"; }
command_preflight(){ check_environment;validate_artifact;candidate_checks;compare_files>/dev/null;local schema;schema=$(schema_state);[[ "$schema" == ABSENT ]]||die SCHEMA_PRESTATE_NOT_ABSENT;run_preflight;echo "M083_PRODUCTION_PREFLIGHT=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA FILES=${#RUNTIME_FILES[@]} SCHEMA=$schema FULL_DB_BACKUP_REQUIRED=YES PRODUCTION_MUTATED=NO"; }
command_apply(){ check_environment;ensure_state_dirs;exec 9>"$LOCK_FILE";flock -n 9||die LOCKED;validate_artifact;candidate_checks;compare_files>/dev/null;SCHEMA_PRESTATE=$(schema_state);[[ "$SCHEMA_PRESTATE" == ABSENT ]]||die SCHEMA_PRESTATE_NOT_ABSENT;run_preflight>/dev/null;if [[ -f "$STATE_FILE" ]];then load_state;case "$STATE_STATUS" in DEPLOYED)die ALREADY_DEPLOYED;;APPLY_IN_PROGRESS|APPLY_FAILED_RECOVERY_REQUIRED)die RECOVERY_REQUIRED;;esac;SCHEMA_PRESTATE=ABSENT;fi;create_code_backup;create_db_backup;save_state APPLY_IN_PROGRESS;local mutated=0;trap 'rc=$?;if [[ $mutated -eq 1 ]];then automatic_rollback;fi;exit $rc' ERR;mutated=1;apply_schema;apply_files;live_checks;mutated=0;trap - ERR;save_state DEPLOYED;echo "M083_PRODUCTION_APPLY=PASS RELEASE=$RELEASE RUNTIME_COMMIT=$RUNTIME_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CODE_BACKUP=$CODE_BACKUP CODE_BACKUP_SHA=$CODE_BACKUP_SHA DB_BACKUP=$DB_BACKUP DB_BACKUP_SHA=$DB_BACKUP_SHA"; }
command_verify(){ check_environment;validate_artifact;candidate_checks;load_state;[[ "$STATE_STATUS" == DEPLOYED ]]||die STATE_NOT_DEPLOYED;live_checks||die LIVE_VERIFY_FAILED;echo "M083_PRODUCTION_VERIFY=PASS STATE=DEPLOYED SCHEMA=READY DATABASE_MUTATED_BY_COMMAND=NO"; }
command_rollback(){ check_environment;ensure_state_dirs;exec 9>"$LOCK_FILE";flock -n 9||die LOCKED;validate_artifact;candidate_checks;load_state;[[ "$STATE_STATUS" == DEPLOYED||"$STATE_STATUS" == APPLY_IN_PROGRESS||"$STATE_STATUS" == APPLY_FAILED_RECOVERY_REQUIRED ]]||die STATE_NOT_ROLLBACKABLE;create_db_backup;rollback_schema_empty||die ROLLBACK_BLOCKED_SCHEMA_NOT_EMPTY;restore_code||die ROLLBACK_CODE_RESTORE_FAILED;save_state ROLLED_BACK;echo "M083_PRODUCTION_ROLLBACK=PASS CODE_RESTORED=$CODE_BACKUP SCHEMA=ABSENT SAFETY_DB_BACKUP=$DB_BACKUP SAFETY_DB_BACKUP_SHA=$DB_BACKUP_SHA"; }
command_status(){ if [[ -f "$STATE_FILE" ]];then load_state;grep -E '^STATE_(RELEASE|STATUS|RUNTIME_COMMIT|ARTIFACT_SHA|CODE_BACKUP|CODE_BACKUP_SHA|ABSENT_LIST|ABSENT_LIST_SHA|DB_BACKUP|DB_BACKUP_SHA|SCHEMA_PRESTATE|UPDATED_AT)=' "$STATE_FILE";else echo M083_PRODUCTION_STATUS=NOT_DEPLOYED;fi; }

require_root "$@";trap cleanup EXIT
case "$1" in compare)command_compare;;preflight)command_preflight;;apply)command_apply;;verify)command_verify;;rollback)command_rollback;;status)command_status;;esac
