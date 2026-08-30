#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
artifact=${M068_ARTIFACT:-/private/tmp/m068-website-lifecycle-32e82ff.tar.gz}
test_root=$(mktemp -d /private/tmp/m068-preflight-helper-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/inbox" "$test_root/target" "$test_root/m062-state" "$test_root/bin" \
  "$test_root/code-backups" "$test_root/db-backups" "$test_root/state/requests" \
  "$test_root/state/container-root" "$test_root/artifacts"
cp "$root/deploy/tools/chinaplanetos_backup_erp_m068_preflight.sh" "$test_root/chinaplanetos-backup-erp-m068-preflight.test"
cp "$artifact" "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"
chmod 0700 "$test_root/chinaplanetos-backup-erp-m068-preflight.test"
printf '#!/bin/sh\nexit 0\n' >"$test_root/bin/flock"
chmod 0700 "$test_root/bin/flock"
helper="$test_root/chinaplanetos-backup-erp-m068-preflight.test"
baseline='f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b'

while read -r expected rel extra; do
  [[ -z "${extra:-}" ]] || exit 1
  [[ "$expected" == ABSENT ]] && continue
  target="$test_root/target/${rel#custom/}"
  mkdir -p "$(dirname "$target")"
  git -C "$root" show "$baseline:$rel" >"$target"
done <"$root/deploy/releases/m068-m062-r04-baseline.sha256"
while read -r expected rel extra; do
  [[ -z "${extra:-}" ]] || exit 1
  target="$test_root/target/${rel#custom/}"
  mkdir -p "$(dirname "$target")"
  git -C "$root" show "$baseline:$rel" >"$target"
done <"$root/deploy/releases/m068-m062-r04-dependencies.sha256"
cat >"$test_root/m062-state/m062-r04.state" <<'EOF'
STATE_RELEASE=M062-R04
STATE_STATUS=DEPLOYED
STATE_RUNTIME_COMMIT=f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b
STATE_SCOPE_COMMIT=3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67
STATE_ARTIFACT_SHA=a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c
EOF
chmod 0600 "$test_root/m062-state/m062-r04.state"
export M068_PREFLIGHT_HELPER_TEST_MODE=1 M068_PREFLIGHT_HELPER_TEST_ROOT="$test_root"

snapshot() { find "$test_root/inbox" "$test_root/target" "$test_root/m062-state" -type f -print0 | LC_ALL=C sort -z | xargs -0 shasum -a 256; }
before=$(snapshot)
"$helper" status | grep -Fq 'M068_DEPLOY_STATUS=NOT_DEPLOYED'
"$helper" configure-mailpit | grep -Fq 'M068_DEPLOY_MAILPIT=PASS RESULT=NOOP_ALREADY_CONFIGURED'
"$helper" compare | grep -Fq 'M068_DEPLOY_COMPARE=PASS'
"$helper" preflight | grep -Fq 'M068_DEPLOY_PREFLIGHT=PASS'
[[ "$(snapshot)" == "$before" ]] || { echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=READ_ONLY_INPUT_MUTATION' >&2; exit 1; }
[[ -z $(find "$test_root/state" -maxdepth 1 \( -name 'candidate.*' -o -name 'artifact.*' \) -print) ]] || { echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=TEMP_NOT_CLEANED' >&2; exit 1; }
if "$helper" dry-run >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=FORBIDDEN_DRY_RUN' >&2; exit 1; fi
if "$helper" compare extra >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=ARBITRARY_ARGS' >&2; exit 1; fi

cp "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz" "$test_root/inbox/good.tar.gz"
printf 'corrupt\n' >>"$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"
if "$helper" preflight >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=CORRUPT_ARTIFACT_ACCEPTED' >&2; exit 1; fi
mv "$test_root/inbox/good.tar.gz" "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"

cp "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz" "$test_root/inbox/good.tar.gz"
truncate -s 2097153 "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"
if "$helper" preflight >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=OVERSIZED_ARTIFACT_ACCEPTED' >&2; exit 1; fi
mv "$test_root/inbox/good.tar.gz" "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"

mv "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz" "$test_root/inbox/good.tar.gz"
ln -s "$test_root/inbox/good.tar.gz" "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"
if "$helper" preflight >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=SYMLINK_ARTIFACT_ACCEPTED' >&2; exit 1; fi
rm "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"
mv "$test_root/inbox/good.tar.gz" "$test_root/inbox/m068-website-lifecycle-32e82ff.tar.gz"

drift_file="$test_root/target/chinaplanetoperations/class/PublicWebsiteRequestService.class.php"
cp "$drift_file" "$test_root/original"
printf '\nM068 synthetic drift\n' >>"$drift_file"
if "$helper" preflight >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=RUNTIME_DRIFT_ACCEPTED' >&2; exit 1; fi
mv "$test_root/original" "$drift_file"

dependency="$test_root/target/chinaplanetoperations/lib/main.lib.php"
cp "$dependency" "$test_root/original"
printf '\nM068 synthetic dependency drift\n' >>"$dependency"
if "$helper" preflight >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=DEPENDENCY_DRIFT_ACCEPTED' >&2; exit 1; fi
mv "$test_root/original" "$dependency"

cp "$test_root/m062-state/m062-r04.state" "$test_root/original"
sed 's/STATE_STATUS=DEPLOYED/STATE_STATUS=ROLLED_BACK/' "$test_root/original" >"$test_root/m062-state/m062-r04.state"
if "$helper" status >/dev/null 2>&1; then echo 'M068_PREFLIGHT_FAILURES=FAIL REASON=NONDEPLOYED_BASELINE_ACCEPTED' >&2; exit 1; fi
mv "$test_root/original" "$test_root/m062-state/m062-r04.state"

"$helper" apply | grep -Fq 'M068_DEPLOY_APPLY=PASS'
"$helper" verify | grep -Fq 'M068_DEPLOY_VERIFY=PASS'
"$helper" status | grep -Fq 'STATE_STATUS=DEPLOYED'
"$helper" rollback | grep -Fq 'M068_DEPLOY_ROLLBACK=PASS'
"$helper" status | grep -Fq 'STATE_STATUS=ROLLED_BACK_SCHEMA_RETAINED'

echo 'M068_PREFLIGHT_FAILURES=PASS READ_ONLY_GATES=YES APPLY_VERIFY_ROLLBACK=PASS CORRUPT_ARTIFACT=DENIED OVERSIZED_ARTIFACT=DENIED SYMLINK_ARTIFACT=DENIED RUNTIME_DRIFT=DENIED DEPENDENCY_DRIFT=DENIED BASELINE_MISMATCH=DENIED ARBITRARY_COMMANDS=DENIED PRODUCTION_ACCESSED=NO'
