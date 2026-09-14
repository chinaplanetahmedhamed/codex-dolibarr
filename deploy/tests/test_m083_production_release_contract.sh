#!/usr/bin/env bash
set -Eeuo pipefail
root=$(cd "$(dirname "$0")/../.."&&pwd -P)
helper="$root/deploy/tools/chinaplanetos_production_m083_staff_first.sh"
installer="$root/deploy/tools/install_production_m083_staff_first_helper.sh"
sudoers="$root/deploy/config/codex-deploy-production-m083-staff-first.sudoers"
runtime="$root/deploy/releases/m083-runtime-files.txt"
support="$root/deploy/releases/m083-support-files.txt"
baseline="$root/deploy/releases/m083-production-baseline.sha256"
schema_test="$root/deploy/tests/test_m083_schema_contract_local.php"
bash -n "$helper";bash -n "$installer"
php -l "$schema_test" >/dev/null
[[ $(wc -l<"$runtime") -eq 58&&$(wc -l<"$baseline") -eq 58 ]]
[[ -z $(sort "$runtime"|uniq -d)&&-z $(sort "$support"|uniq -d) ]]
[[ -z $(awk '$0!~/^custom\/(chinaplanetcustomercenter|chinaplanetlogistics|chinaplanetoperations)\//{print;exit}' "$runtime") ]]
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper"
grep -Fq "readonly TARGET_DB_CONTAINER='vision_db'" "$helper"
grep -Fq "readonly TARGET_DB_VOLUME='vision-os_mariadb_data'" "$helper"
grep -Fq "readonly TARGET_DB_NAME='dolibarr_db'" "$helper"
grep -Fq "readonly TARGET_HTTP='https://erp.visiontrading.biz'" "$helper"
grep -Fq "readonly RELEASE='M083-R07'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='feecf3dcf58d362768ad384dc4b4569e7cc35a6484181a71a4a9b89632af4b24'" "$helper"
grep -Fq "readonly PRIOR_HELPER_SHA='72cadcb113e33fbf4aac3dd5d132780c12c259ff528773ad9f62165e95363cd6'" "$installer"
grep -Fq 'readonly PRIOR_BACKUP="$BACKUP_ROOT/chinaplanetos-production-m083-staff-first.$PRIOR_HELPER_SHA.bak"' "$installer"
grep -Fq '$(sha256 "$PRIOR_BACKUP") == "$PRIOR_HELPER_SHA"' "$installer"
grep -Fq 'mv -f "$restore_stage" "$TARGET_HELPER"' "$installer"
grep -Fq 'SCHEMA_PRESTATE_NOT_ABSENT' "$helper"
grep -Fq 'create_db_backup' "$helper"
grep -Fq 'rollback_schema_empty' "$helper"
grep -Fq 'PRODUCTION_MIXED_RELEASE_STATE' "$helper"
grep -Fq 'PRODUCTION_NOT_EXACT_BASELINE' "$helper"
grep -Fq 'state=UNCHANGED;((SAME_COUNT+=1))' "$helper"
grep -Fq 'is_exact_baseline(){ [[ $CURRENT_COUNT -eq 0&&$((BASELINE_COUNT+SAME_COUNT)) -eq ${#RUNTIME_FILES[@]} ]]; }' "$helper"
grep -Fq 'is_exact_release(){ [[ $BASELINE_COUNT -eq 0&&$((CURRENT_COUNT+SAME_COUNT)) -eq ${#RUNTIME_FILES[@]} ]]; }' "$helper"
grep -Fq 'RETAINED_ARTIFACT' "$helper"
grep -Fq 'schema_empty_check' "$helper"
grep -Fq 'check_core_environment' "$helper"
grep -Fq 'HTTP_REQUIRED=NO' "$helper"
grep -Fq 'stage_candidate;validate_restore_materials' "$helper"
! grep -Fq 'restore_database' "$helper"
[[ $(wc -l<"$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status;do grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m083-staff-first $command" "$sudoers";done
if [[ ${M083_FINAL_PINS:-0} == 1 ]];then ! grep -Rq '__[A-Z_]*__' "$helper" "$installer";fi
same_count=0
while read -r expected file;do if [[ "$expected" != ABSENT&&$(shasum -a 256 "$root/$file"|awk '{print $1}') == "$expected" ]];then same_count=$((same_count+1));fi;done<"$baseline"
[[ $same_count -ge 1 ]]
echo "M083_RELEASE_CONTRACT=PASS TARGET=PRODUCTION RELEASE=M083-R07 COMMANDS=6 UNCHANGED=$same_count DB_RESTORE=FORBIDDEN EMERGENCY_ROLLBACK=RETAINED_AND_STAGED"
