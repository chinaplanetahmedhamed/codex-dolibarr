#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
test_root=$(mktemp -d /private/tmp/m068-c01-hmac-helper-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/config" "$test_root/target" "$test_root/m062-state" "$test_root/system/usr/local/sbin" "$test_root/bin"
printf '#!/bin/sh\nexit 0\n' >"$test_root/bin/flock"
chmod 0700 "$test_root/bin/flock"
cp "$root/deploy/tools/chinaplanetos_backup_erp_m068_c01_hmac.sh" "$test_root/chinaplanetos-backup-erp-m068-c01-hmac.test"
cp "$root/deploy/tools/m068_c01_portal_hmac_config.php" "$test_root/config/m068_c01_portal_hmac_config.test.php"
cp "$root/deploy/tests/fixtures/m068_c01_config_bootstrap.php" "$test_root/config/bootstrap.php"
printf '[]\n' >"$test_root/config-state.json"
cat >"$test_root/m062-state/m062-r04.state" <<'EOF'
STATE_RELEASE=M062-R04
STATE_STATUS=DEPLOYED
STATE_RUNTIME_COMMIT=f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b
STATE_SCOPE_COMMIT=3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67
STATE_ARTIFACT_SHA=a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c
EOF
chmod 0700 "$test_root/chinaplanetos-backup-erp-m068-c01-hmac.test"
chmod 0600 "$test_root/m062-state/m062-r04.state"

helper="$test_root/chinaplanetos-backup-erp-m068-c01-hmac.test"
export M068_C01_HELPER_TEST_MODE=1
export M068_C01_HELPER_TEST_ROOT="$test_root"
export M068_C01_CONFIG_TEST_STATE="$test_root/config-state.json"
export M068_C01_CONFIG_TEST_ENTITY=1
unset CHINAPLANET_PORTAL_INVITATION_HMAC_KEY

preflight=$($helper preflight)
grep -Fq 'M068_C01_PREFLIGHT=PASS' <<<"$preflight"
grep -Fq 'CONFIG=ABSENT ACTION_REQUIRED=YES' <<<"$preflight"
status=$($helper status)
grep -Fq 'CONFIG=ABSENT STATE=NOT_APPLIED' <<<"$status"
[[ ! -e "$test_root/state" && ! -e "$test_root/db-backups" ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=READ_ONLY_COMMAND_CREATED_STATE' >&2; exit 1; }

if "$helper" apply unexpected >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=EXTRA_ARGUMENT_ACCEPTED' >&2; exit 1; fi
if "$helper" shell >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=FORBIDDEN_COMMAND_ACCEPTED' >&2; exit 1; fi

apply_output=$($helper apply)
grep -Fq 'M068_C01_APPLY=PASS' <<<"$apply_output"
grep -Fq 'DATABASE_BACKUP_CREATED=YES DATABASE_RESTORE_TEST=PASS' <<<"$apply_output"
state_file="$test_root/state/m068-c01.state"
[[ -f "$state_file" && $(stat -f '%Lp' "$state_file") == 600 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=STATE_METADATA' >&2; exit 1; }
backup_path=$(sed -n 's/^STATE_DB_BACKUP=//p' "$state_file")
backup_sha=$(sed -n 's/^STATE_DB_BACKUP_SHA=//p' "$state_file")
[[ -f "$backup_path" && -s "$backup_path" && $(stat -f '%Lp' "$backup_path") == 600 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=DATABASE_BACKUP_METADATA' >&2; exit 1; }
[[ $(shasum -a 256 "$backup_path" | awk '{print $1}') == "$backup_sha" ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=DATABASE_BACKUP_HASH' >&2; exit 1; }
grep -Fq 'STATE_SECRET_PRESTATE=ABSENT' "$state_file"
grep -Fxq 'STATE_RELEASE=M068-C01-R01' "$state_file"

stored=$(php -r '$rows=json_decode(file_get_contents($argv[1]),true);echo count($rows)===1?$rows[0]["value"]:"";' "$test_root/config-state.json")
secret=$(php -r '$v=$argv[1];echo str_starts_with($v,"dolcrypt:test:")?base64_decode(substr($v,14),true):"";' "$stored")
[[ ${#secret} -eq 64 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=SECRET_NOT_GENERATED' >&2; exit 1; }
[[ "$stored" == dolcrypt:test:* && "$apply_output" != *"$secret"* && "$apply_output" != *"$stored"* ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=APPLY_OUTPUT_SECRET_LEAK' >&2; exit 1; }
! grep -Fq "$secret" "$state_file"
! grep -Fq "$stored" "$state_file"
! grep -Fq "$secret" "$backup_path"
! grep -Fq "$stored" "$backup_path"

$helper verify | grep -Fq 'M068_C01_VERIFY=PASS RELEASE=M068-C01-R03'
if $helper apply >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=SECOND_APPLY_ACCEPTED' >&2; exit 1; fi

cp "$test_root/config-state.json" "$test_root/config-state.good.json"
php -r '$p=$argv[1];$rows=json_decode(file_get_contents($p),true);$rows[0]["value"]="dolcrypt:test:".base64_encode(str_repeat("z",63));file_put_contents($p,json_encode($rows));' "$test_root/config-state.json"
if "$helper" verify >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=OWNERSHIP_VALUE_DRIFT_ACCEPTED' >&2; exit 1; fi
if "$helper" rollback >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=OWNERSHIP_VALUE_DRIFT_DELETED' >&2; exit 1; fi
[[ $(php -r '$r=json_decode(file_get_contents($argv[1]),true);echo count($r);' "$test_root/config-state.json") == 1 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=REFUSED_ROLLBACK_CHANGED_VALUE' >&2; exit 1; }
mv "$test_root/config-state.good.json" "$test_root/config-state.json"

$helper rollback | grep -Fq 'M068_C01_ROLLBACK=PASS'
[[ $(sed -n 's/^STATE_STATUS=//p' "$state_file") == ROLLED_BACK ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=ROLLBACK_STATE' >&2; exit 1; }
[[ $(php -r '$rows=json_decode(file_get_contents($argv[1]),true);echo count($rows);' "$test_root/config-state.json") == 0 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=ROLLBACK_NOT_ABSENT' >&2; exit 1; }
if "$helper" verify >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=VERIFY_AFTER_ROLLBACK_ACCEPTED' >&2; exit 1; fi

reset_case() {
  rm -rf "$test_root/state" "$test_root/db-backups"
  printf '[]\n' >"$test_root/config-state.json"
}

reset_case
if M068_C01_HELPER_TEST_FAULT=BACKUP "$helper" apply >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=BACKUP_FAILURE_ACCEPTED' >&2; exit 1; fi
[[ $(php -r '$r=json_decode(file_get_contents($argv[1]),true);echo count($r);' "$test_root/config-state.json") == 0 && ! -e "$test_root/state/m068-c01.state" ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=BACKUP_FAILURE_MUTATED_CONFIG' >&2; exit 1; }
[[ -z $(find "$test_root/db-backups" -type f -name '*.sql' -print -quit) ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=FAILED_BACKUP_RETAINED' >&2; exit 1; }

reset_case
if M068_C01_HELPER_TEST_FAULT=RESTORE "$helper" apply >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=RESTORE_FAILURE_ACCEPTED' >&2; exit 1; fi
[[ $(php -r '$r=json_decode(file_get_contents($argv[1]),true);echo count($r);' "$test_root/config-state.json") == 0 && ! -e "$test_root/state/m068-c01.state" ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=RESTORE_FAILURE_MUTATED_CONFIG' >&2; exit 1; }
[[ -z $(find "$test_root/db-backups" -type f -name '*.sql' -print -quit) ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=UNVALIDATED_RESTORE_BACKUP_RETAINED' >&2; exit 1; }

reset_case
if M068_C01_HELPER_TEST_FAULT=RESTORED_KEY "$helper" apply >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=BACKUP_WITH_KEY_ACCEPTED' >&2; exit 1; fi
[[ $(php -r '$r=json_decode(file_get_contents($argv[1]),true);echo count($r);' "$test_root/config-state.json") == 0 && ! -e "$test_root/state/m068-c01.state" ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=BACKUP_KEY_RACE_MUTATED_CONFIG' >&2; exit 1; }
[[ -z $(find "$test_root/db-backups" -type f -name '*.sql' -print -quit) ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=BACKUP_WITH_KEY_RETAINED' >&2; exit 1; }

reset_case
if M068_C01_HELPER_TEST_FAULT=AFTER_CONFIG "$helper" apply >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=AFTER_CONFIG_FAILURE_ACCEPTED' >&2; exit 1; fi
[[ $(sed -n 's/^STATE_STATUS=//p' "$test_root/state/m068-c01.state") == APPLY_FAILED_ROLLED_BACK ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=AUTO_ROLLBACK_STATE' >&2; exit 1; }
[[ $(php -r '$r=json_decode(file_get_contents($argv[1]),true);echo count($r);' "$test_root/config-state.json") == 0 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=AUTO_ROLLBACK_LEFT_SECRET' >&2; exit 1; }

reset_case
if M068_C01_CONFIG_TEST_FAULT=AFTER_COMMIT "$helper" apply >/dev/null 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=LOST_ACK_FAILURE_ACCEPTED' >&2; exit 1; fi
[[ $(sed -n 's/^STATE_STATUS=//p' "$test_root/state/m068-c01.state") == APPLY_FAILED_ROLLED_BACK ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=LOST_ACK_ROLLBACK_STATE' >&2; exit 1; }
[[ $(php -r '$r=json_decode(file_get_contents($argv[1]),true);echo count($r);' "$test_root/config-state.json") == 0 ]] || { echo 'M068_C01_HELPER_TEST=FAIL REASON=LOST_ACK_LEFT_SECRET' >&2; exit 1; }

conflict='do-not-output-this-secret-value'
if CHINAPLANET_PORTAL_INVITATION_HMAC_KEY="$conflict" "$helper" preflight >"$test_root/conflict.out" 2>&1; then echo 'M068_C01_HELPER_TEST=FAIL REASON=ENV_CONFLICT_ACCEPTED' >&2; exit 1; fi
! grep -Fq "$conflict" "$test_root/conflict.out"

echo 'M068_C01_HELPER_TEST=PASS PREFLIGHT_STATUS=READ_ONLY EXACT_CONSTANT=1 APPLY=SERVER_GENERATED ENCRYPTED_AT_REST=YES FULL_DATABASE_BACKUP=PASS BACKUP_KEY_ABSENCE=PASS COMPLETE_OBJECT_FINGERPRINT=PASS RESTORE_TEST=PASS VERIFY=PRESENCE_LENGTH_ONLY ROLLBACK=OWNERSHIP_BOUND_EXACT FAILURE_ROLLBACK=PASS STATE_SECRET=NONE BACKUP_SECRET=NONE SERVER_ACCESSED=NO PRODUCTION_ACCESSED=NO'
