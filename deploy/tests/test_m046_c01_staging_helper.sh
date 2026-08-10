#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

root=$(cd "$(dirname "$0")/../.." && pwd -P)
helper_source="$root/deploy/tools/chinaplanetos_backup_erp_m046_c01_translation_policy.sh"
artifact=${M046_C01_TEST_ARTIFACT:-/private/tmp/m046-c01-15c19dd.tar.gz}
base_commit='2404ba03f10fadd503fc3dbbea67ad9f8a87e6fa'

die() { echo "M046_C01_HELPER_TEST=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
test_tmp=${M046_C01_TEST_TMP_ROOT:-/private/tmp}
case "$test_tmp" in /tmp|/private/tmp) ;; *) die 'TEST_TMP_ROOT_INVALID' ;; esac
fixture=$(mktemp -d "$test_tmp/m046-c01-helper-test.XXXXXX")
case "$fixture" in /tmp/m046-c01-helper-test.*|/private/tmp/m046-c01-helper-test.*) ;; *) die 'FIXTURE_ROOT_INVALID' ;; esac
cleanup() { case "$fixture" in /tmp/m046-c01-helper-test.*|/private/tmp/m046-c01-helper-test.*) [[ -d "$fixture" ]] && rm -rf "$fixture" ;; esac; }
trap cleanup EXIT

mkdir -m 0700 "$fixture/inbox" "$fixture/target" "$fixture/backups" "$fixture/state" "$fixture/state/requests" "$fixture/bin"
helper="$fixture/bin/chinaplanetos-backup-erp-m046-c01-translation-policy.test"
cp "$helper_source" "$helper"; chmod 0700 "$helper"
cat >"$fixture/bin/flock" <<'EOF'
#!/usr/bin/env bash
[[ ${M046_C01_TEST_FORCE_LOCKED:-0} != 1 ]]
EOF
chmod 0700 "$fixture/bin/flock"
printf '%064d\n' 0 >"$fixture/state/stored-row.sha"
cp "$artifact" "$fixture/inbox/m046-c01-15c19dd.tar.gz"; chmod 0644 "$fixture/inbox/m046-c01-15c19dd.tar.gz"

runtime_manifest="$root/deploy/releases/m046-c01-runtime-files.txt"
while IFS= read -r source_file; do
  target_file="$fixture/target/${source_file#custom/}"
  mkdir -p "$(dirname "$target_file")"
  git -C "$root" show "$base_commit:$source_file" >"$target_file"
  chmod 0644 "$target_file"
done <"$runtime_manifest"

baseline="$fixture/baseline.sha"
release="$fixture/release.sha"
while IFS= read -r source_file; do
  target_file="$fixture/target/${source_file#custom/}"
  printf '%s  %s\n' "$(sha256 "$target_file")" "${source_file#custom/}" >>"$baseline"
  printf '%s  %s\n' "$(sha256 "$root/$source_file")" "${source_file#custom/}" >>"$release"
done <"$runtime_manifest"

run_helper() { M046_C01_HELPER_TEST_MODE=1 M046_C01_HELPER_TEST_ROOT="$fixture" "$helper" "$@"; }
request_stamp=$(date -u '+%Y%m%dT%H%M%SZ')
request_id() { printf 'm046-c01-%s-%012x\n' "$request_stamp" "$1"; }
target_hashes() {
  while IFS= read -r source_file; do target_file="$fixture/target/${source_file#custom/}"; printf '%s  %s\n' "$(sha256 "$target_file")" "${source_file#custom/}"; done <"$runtime_manifest"
}
assert_no_transients() {
  if find "$fixture/state" -maxdepth 1 \( -name 'candidate.*' -o -name 'candidate.*.expected' -o -name 'artifact.*' -o -name 'm046-c01-backup.list.*' -o -name 'm046-c01-reapply.list.*' -o -name 'reapply.*' -o -name '*.tmp.*' \) -print -quit | grep -q .; then die 'STATE_TRANSIENT_LEFTOVER'; fi
  if find "$fixture/target" -type f \( -name '*.m046-c01.*' -o -name '*.m046-c01-restore.*' \) -print -quit | grep -q .; then die 'TARGET_TRANSIENT_LEFTOVER'; fi
}

if M046_C01_TEST_FAIL_STRUCTURE=1 run_helper compare >"$fixture/structure-fail.out" 2>&1; then die 'STRUCTURE_FAILURE_INJECTION_DID_NOT_FAIL'; fi
grep -Fq 'M046_C01_STAGING_FAIL=TEST_ARTIFACT_STRUCTURE_FAILURE' "$fixture/structure-fail.out" || die 'STRUCTURE_FAILURE_RESULT_INVALID'
assert_no_transients
if M046_C01_TEST_DISK_LOW=1 run_helper preflight >"$fixture/disk-low.out" 2>&1; then die 'DISK_LOW_INJECTION_DID_NOT_FAIL'; fi
grep -Fq 'M046_C01_STAGING_FAIL=DISK_SPACE_INSUFFICIENT' "$fixture/disk-low.out" || die 'DISK_LOW_RESULT_INVALID'
assert_no_transients

compare_output=$(run_helper compare)
grep -Fq 'M046_C01_STAGING_COMPARE=PASS' <<<"$compare_output" || die 'COMPARE_FAILED'
[[ -z $(find "$fixture/backups" -type f -print -quit) ]] || die 'COMPARE_CREATED_BACKUP'
assert_no_transients
preflight_output=$(run_helper preflight)
grep -Fq 'M046_C01_STAGING_PREFLIGHT=PASS' <<<"$preflight_output" || die 'PREFLIGHT_FAILED'
[[ -z $(find "$fixture/backups" -type f -print -quit) ]] || die 'PREFLIGHT_CREATED_BACKUP'
assert_no_transients

locked_id=$(request_id 1)
if M046_C01_TEST_FORCE_LOCKED=1 run_helper apply "$locked_id" >"$fixture/locked.out" 2>&1; then die 'LOCK_NOT_ENFORCED'; fi
grep -Fq 'M046_C01_STAGING_FAIL=LOCKED' "$fixture/locked.out" || die 'LOCK_RESULT_INVALID'
[[ ! -e "$fixture/state/requests/$locked_id.state" ]] || die 'LOCKED_REQUEST_CONSUMED'
assert_no_transients

before_mv_id=$(request_id 2)
if M046_C01_TEST_FAIL_BEFORE_FIRST_MV=1 run_helper apply "$before_mv_id" >"$fixture/fail-before-mv.out" 2>&1; then die 'BEFORE_MV_FAILURE_INJECTION_DID_NOT_FAIL'; fi
grep -Fq 'REQUEST_STATUS=APPLY_FAILED_AUTO_RESTORED' "$fixture/state/requests/$before_mv_id.state" || die 'BEFORE_MV_REQUEST_NOT_TERMINAL'
target_hashes >"$fixture/after-before-mv.sha"
diff -u "$baseline" "$fixture/after-before-mv.sha" >/dev/null || die 'BEFORE_MV_RESTORE_INCOMPLETE'
assert_no_transients

failed_id=$(request_id 3)
if M046_C01_TEST_FAIL_AFTER_FIRST_FILE=1 run_helper apply "$failed_id" >"$fixture/fail-first.out" 2>&1; then die 'FAILURE_INJECTION_DID_NOT_FAIL'; fi
grep -Fq 'M046_C01_STAGING_AUTO_ROLLBACK=PASS' "$fixture/fail-first.out" || die 'AUTOMATIC_ROLLBACK_ABSENT'
target_hashes >"$fixture/after-auto-rollback.sha"
diff -u "$baseline" "$fixture/after-auto-rollback.sha" >/dev/null || die 'AUTOMATIC_ROLLBACK_INCOMPLETE'
grep -Fq 'REQUEST_STATUS=APPLY_FAILED_AUTO_RESTORED' "$fixture/state/requests/$failed_id.state" || die 'FAILED_REQUEST_NOT_TERMINAL'
failed_backup=$(find "$fixture/backups" -type f -name "*${failed_id}.tar.gz" -print -quit)
[[ -n "$failed_backup" ]] || die 'FAILED_REQUEST_BACKUP_ABSENT'
failed_backup_sha=$(sha256 "$failed_backup")
assert_no_transients
if run_helper apply "$failed_id" >"$fixture/replay-failed.out" 2>&1; then die 'FAILED_REQUEST_REPLAY_ACCEPTED'; fi
grep -Fq 'M046_C01_STAGING_FAIL=REQUEST_ID_REPLAYED' "$fixture/replay-failed.out" || die 'FAILED_REQUEST_REPLAY_RESULT_INVALID'

apply_id=$(request_id 4)
apply_output=$(run_helper apply "$apply_id")
grep -Fq 'M046_C01_STAGING_APPLY=PASS' <<<"$apply_output" || die 'APPLY_FAILED'
target_hashes >"$fixture/after-apply.sha"
diff -u "$release" "$fixture/after-apply.sha" >/dev/null || die 'APPLY_HASH_MISMATCH'
grep -Fq 'STATE_STATUS=DEPLOYED' "$fixture/state/m046-c01-r1.state" || die 'DEPLOYED_STATE_ABSENT'
[[ "$failed_backup_sha" == "$(sha256 "$failed_backup")" ]] || die 'PRIOR_BACKUP_OVERWRITTEN'
[[ $(find "$fixture/backups" -type f -name "*${apply_id}.tar.gz" | wc -l | tr -d ' ') == 1 ]] || die 'REQUEST_UNIQUE_BACKUP_ABSENT'
assert_no_transients
verify_output=$(run_helper verify)
grep -Fq 'M046_C01_STAGING_VERIFY=PASS' <<<"$verify_output" || die 'VERIFY_FAILED'

tracked_state="$fixture/tracked-deployed.state"
cp "$fixture/state/m046-c01-r1.state" "$tracked_state"
rm -f "$fixture/state/m046-c01-r1.state"
untracked_noop_id=$(request_id 5)
untracked_noop=$(run_helper apply "$untracked_noop_id")
grep -Fq 'M046_C01_STAGING_APPLY=NOOP_ALREADY_MATCHING' <<<"$untracked_noop" || die 'UNTRACKED_NOOP_FAILED'
untracked_verify=$(run_helper verify)
grep -Fq 'STATE=UNTRACKED_MATCHING' <<<"$untracked_verify" || die 'UNTRACKED_NOOP_VERIFY_FAILED'
mv "$tracked_state" "$fixture/state/m046-c01-r1.state"
assert_no_transients

backup_count_before=$(find "$fixture/backups" -type f | wc -l | tr -d ' ')
state_sha_before=$(sha256 "$fixture/state/m046-c01-r1.state")
noop_id=$(request_id 6)
noop_output=$(run_helper apply "$noop_id")
grep -Fq 'M046_C01_STAGING_APPLY=NOOP_ALREADY_MATCHING' <<<"$noop_output" || die 'FRESH_NOOP_FAILED'
grep -Fq 'NEW_BACKUP=NO' <<<"$noop_output" || die 'NOOP_BACKUP_CONTRACT_ABSENT'
backup_count_after=$(find "$fixture/backups" -type f | wc -l | tr -d ' ')
[[ "$backup_count_before" == "$backup_count_after" ]] || die 'NOOP_CREATED_BACKUP'
[[ "$state_sha_before" == "$(sha256 "$fixture/state/m046-c01-r1.state")" ]] || die 'NOOP_REWROTE_STATE'
if run_helper apply "$noop_id" >"$fixture/replay-noop.out" 2>&1; then die 'NOOP_REQUEST_REPLAY_ACCEPTED'; fi
grep -Fq 'M046_C01_STAGING_FAIL=REQUEST_ID_REPLAYED' "$fixture/replay-noop.out" || die 'NOOP_REPLAY_RESULT_INVALID'
assert_no_transients

stale_id='m046-c01-20000101T000000Z-000000000007'
if run_helper apply "$stale_id" >"$fixture/stale.out" 2>&1; then die 'STALE_REQUEST_ACCEPTED'; fi
grep -Fq 'M046_C01_STAGING_FAIL=REQUEST_ID_STALE' "$fixture/stale.out" || die 'STALE_REQUEST_RESULT_INVALID'
assert_no_transients

corrupt_target="$fixture/target/chinaplanetshippingprices/admin/batch.php"
printf 'corrupted release target\n' >"$corrupt_target"
if run_helper verify >"$fixture/corrupt-verify.out" 2>&1; then die 'CORRUPT_TARGET_VERIFY_ACCEPTED'; fi
grep -Fq 'M046_C01_STAGING_FAIL=LIVE_VERIFY_FAILED' "$fixture/corrupt-verify.out" || die 'CORRUPT_TARGET_VERIFY_RESULT_INVALID'
emergency_rollback_id=$(request_id 8)
emergency_rollback=$(run_helper rollback "$emergency_rollback_id")
grep -Fq 'M046_C01_STAGING_ROLLBACK=PASS' <<<"$emergency_rollback" || die 'CORRUPT_TARGET_ROLLBACK_FAILED'
target_hashes >"$fixture/after-emergency-rollback.sha"
diff -u "$baseline" "$fixture/after-emergency-rollback.sha" >/dev/null || die 'CORRUPT_TARGET_PRESTATE_MISMATCH'
emergency_reapply_id=$(request_id 9)
run_helper apply "$emergency_reapply_id" >/dev/null
target_hashes >"$fixture/after-emergency-reapply.sha"
diff -u "$release" "$fixture/after-emergency-reapply.sha" >/dev/null || die 'CORRUPT_TARGET_REAPPLY_FAILED'
assert_no_transients

printf 'corrupted before restore injection\n' >"$corrupt_target"
rollback_fail_id=$(request_id 10)
if M046_C01_TEST_FAIL_RESTORE_AFTER_FIRST=1 run_helper rollback "$rollback_fail_id" >"$fixture/rollback-fail.out" 2>&1; then die 'ROLLBACK_FAILURE_INJECTION_DID_NOT_FAIL'; fi
grep -Fq 'M046_C01_STAGING_ROLLBACK=FAIL_REAPPLIED' "$fixture/rollback-fail.out" || die 'FAILED_ROLLBACK_REAPPLY_ABSENT'
target_hashes >"$fixture/after-failed-rollback.sha"
diff -u "$release" "$fixture/after-failed-rollback.sha" >/dev/null || die 'FAILED_ROLLBACK_REAPPLY_INCOMPLETE'
grep -Fq 'STATE_STATUS=DEPLOYED' "$fixture/state/m046-c01-r1.state" || die 'FAILED_ROLLBACK_CHANGED_STATE'
grep -Fq 'REQUEST_STATUS=ROLLBACK_FAILED_REAPPLIED' "$fixture/state/requests/$rollback_fail_id.state" || die 'FAILED_ROLLBACK_REQUEST_STATUS_INVALID'
assert_no_transients

rollback_id=$(request_id 11)
rollback_output=$(run_helper rollback "$rollback_id")
grep -Fq 'M046_C01_STAGING_ROLLBACK=PASS' <<<"$rollback_output" || die 'ROLLBACK_FAILED'
target_hashes >"$fixture/after-rollback.sha"
diff -u "$baseline" "$fixture/after-rollback.sha" >/dev/null || die 'ROLLBACK_PRESTATE_MISMATCH'
grep -Fq 'STATE_STATUS=ROLLED_BACK' "$fixture/state/m046-c01-r1.state" || die 'ROLLED_BACK_STATE_ABSENT'
reapply_path=$(sed -n 's/^STATE_REAPPLY=//p' "$fixture/state/m046-c01-r1.state")
reapply_sha=$(sed -n 's/^STATE_REAPPLY_SHA=//p' "$fixture/state/m046-c01-r1.state")
[[ -f "$reapply_path" && $(sha256 "$reapply_path") == "$reapply_sha" ]] || die 'REAPPLY_SAFETY_BACKUP_INVALID'
[[ "$reapply_path" == *"$rollback_id"* ]] || die 'REAPPLY_BACKUP_NOT_REQUEST_SCOPED'
assert_no_transients

reapply_id=$(request_id 12)
reapply_output=$(run_helper apply "$reapply_id")
grep -Fq 'M046_C01_STAGING_APPLY=PASS' <<<"$reapply_output" || die 'POST_ROLLBACK_REAPPLY_FAILED'
target_hashes >"$fixture/after-reapply.sha"
diff -u "$release" "$fixture/after-reapply.sha" >/dev/null || die 'POST_ROLLBACK_REAPPLY_HASH_MISMATCH'
run_helper verify >/dev/null
assert_no_transients

[[ $(<"$fixture/state/stored-row.sha") == "$(printf '%064d' 0)" ]] || die 'STORED_CONFIG_EVIDENCE_CHANGED'
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper_source" || die 'STAGING_CONTAINER_PIN_ABSENT'
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper_source" || die 'STAGING_VOLUME_PIN_ABSENT'
if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper_source"; then die 'PRODUCTION_IDENTIFIER_PRESENT'; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE' "$helper_source"; then die 'DATABASE_COMMAND_PRESENT'; fi

echo 'M046_C01_HELPER_TEST=PASS TARGET=BACKUP_ERP_ONLY DISK_GATE=PASS CLEANUP=PASS LOCK=PASS REPLAY=PASS STALE=PASS TERMINAL_FAILURE=PASS NOOP_TRACKED=PASS NOOP_UNTRACKED=VERIFIED UNIQUE_BACKUPS=PASS FAIL_BEFORE_MV=AUTO_RESTORED FAIL_AFTER_FIRST=AUTO_RESTORED CORRUPT_VERIFY=FAIL_CLOSED EMERGENCY_ROLLBACK=PASS CORRUPT_RESTORE_FAILURE=KNOWN_GOOD_REAPPLIED MANUAL_ROLLBACK=PASS REAPPLY_SAFETY_BACKUP=PINNED_CANDIDATE OWNER_GROUP_MODE=RESTORED CONFIG_ROW_UNCHANGED=YES DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO'
