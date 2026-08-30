#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m068_preflight.sh"
installer="$root/deploy/tools/install_backup_erp_m068_preflight_helper.sh"
builder="$root/deploy/tools/build_m068_staging_artifact.py"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m068-preflight.sudoers"
mailpit="$root/deploy/migrations/m068_staging_mailpit_configuration.php"
portal_repair="$root/deploy/migrations/m068_staging_portal_group_repair.php"
artifact=${M068_ARTIFACT:-/private/tmp/m068-website-lifecycle-32e82ff.tar.gz}

fail() { echo "M068_STAGING_RELEASE_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }
constant() { sed -n "s/^readonly $1='\([^']*\)'$/\1/p" "$2"; }

bash -n "$helper"
bash -n "$installer"
php -l "$mailpit" >/dev/null
php -l "$portal_repair" >/dev/null
python3 -c 'import pathlib,sys; p=pathlib.Path(sys.argv[1]); compile(p.read_text(),str(p),"exec")' "$builder"
[[ -f "$artifact" && ! -L "$artifact" ]] || fail ARTIFACT_ABSENT
artifact_sha=$(sha256 "$artifact")
[[ "$artifact_sha" == "$(constant ARTIFACT_SHA "$helper")" ]] || fail HELPER_ARTIFACT_PIN
grep -Fq "EXPECTED_SHA256 = \"$artifact_sha\"" "$builder" || fail BUILDER_ARTIFACT_PIN

test_root=$(mktemp -d /private/tmp/m068-release-contract.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
python3 "$builder" --output "$test_root/rebuilt.tar.gz" >/dev/null
cmp "$artifact" "$test_root/rebuilt.tar.gz"
python3 - "$artifact" "$root" <<'PY'
import pathlib, sys, tarfile
archive, root = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
with tarfile.open(archive) as tf:
    members=tf.getmembers(); names=[m.name for m in members]
    assert len(members)==37 and len(set(names))==37
    assert all(m.isfile() and not m.issym() and not m.islnk() for m in members)
    assert all(m.uid==0 and m.gid==0 and m.uname=='root' and m.gname=='root' and m.mode==0o644 and not m.pax_headers for m in members)
    runtime=(root/'deploy/releases/m068-runtime-files.txt').read_text().splitlines()
    support=(root/'deploy/releases/m068-support-files.txt').read_text().splitlines()
    manifests=['deploy/releases/m068-runtime-files.txt','deploy/releases/m068-support-files.txt','deploy/releases/m068-m062-r04-baseline.sha256','deploy/releases/m068-m062-r04-dependencies.sha256']
    assert sorted(names)==sorted(runtime+support+manifests)
PY

mkdir -p "$test_root/extracted"
tar -xzf "$artifact" -C "$test_root/extracted"
while IFS= read -r rel; do
  git -C "$root" show "32e82ffbd82248f2a84f468172195267fb6d37a7:$rel" >"$test_root/source"
  cmp "$test_root/source" "$test_root/extracted/$rel" || fail RUNTIME_SOURCE_MISMATCH
done <"$root/deploy/releases/m068-runtime-files.txt"
[[ $(wc -l <"$root/deploy/releases/m068-runtime-files.txt" | tr -d ' ') -eq 26 ]] || fail RUNTIME_COUNT
[[ $(wc -l <"$root/deploy/releases/m068-support-files.txt" | tr -d ' ') -eq 7 ]] || fail SUPPORT_COUNT
[[ $(wc -l <"$root/deploy/releases/m068-m062-r04-baseline.sha256" | tr -d ' ') -eq 26 ]] || fail BASELINE_COUNT
[[ $(wc -l <"$root/deploy/releases/m068-m062-r04-dependencies.sha256" | tr -d ' ') -eq 5 ]] || fail DEPENDENCY_COUNT
[[ $(grep -c '^ABSENT  ' "$root/deploy/releases/m068-m062-r04-baseline.sha256") -eq 14 ]] || fail BASELINE_ABSENT_COUNT
for name in RUNTIME_MANIFEST SUPPORT_MANIFEST BASELINE_MANIFEST DEPENDENCY_MANIFEST; do
  path=$(constant "$name" "$helper"); pin=$(constant "${name}_SHA" "$helper")
  [[ -f "$root/$path" && $(sha256 "$root/$path") == "$pin" ]] || fail "${name}_PIN"
done

grep -Fq "readonly RELEASE='M068-R03'" "$helper" || fail RELEASE
grep -Fq "readonly RUNTIME_COMMIT='32e82ffbd82248f2a84f468172195267fb6d37a7'" "$helper" || fail RUNTIME_COMMIT
grep -Fq "readonly BASELINE_RELEASE='M062-R04'" "$helper" || fail BASELINE_RELEASE
grep -Fq "readonly BASELINE_COMMIT='f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b'" "$helper" || fail BASELINE_COMMIT
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper" || fail TARGET_CONTAINER
grep -Fq "readonly DB_CONTAINER='erp-staging-mariadb-1'" "$helper" || fail DB_CONTAINER
grep -Fq "readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'" "$helper" || fail MAILPIT_CONTAINER
grep -Fq "readonly TARGET_HTTP='http://127.0.0.1:18088'" "$helper" || fail TARGET_HTTP
grep -Fq "readonly MAX_ARTIFACT_BYTES=4194304" "$helper" || fail MAX_ARTIFACT_BYTES
grep -Fq 'DB_PORT_EXPOSED' "$helper" || fail DB_PORT_GUARD
grep -Fq 'DATABASE_RESTORE_CHECK_MISMATCH' "$helper" || fail DATABASE_RESTORE_GUARD
grep -Fq 'configuration_check' "$helper" || fail CONFIGURATION_GATE
grep -Fq 'compare_dependencies' "$helper" || fail DEPENDENCY_GATE

commands='configure-mailpit repair-portal-group compare preflight apply verify rollback status'
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 8 ]] || fail SUDOERS_COMMAND_COUNT
for command in $commands; do grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-preflight $command" "$sudoers" || fail "SUDOERS_${command}"; done
if grep -Eq 'vision_erp|vision_db|https://erp\.visiontrading\.biz|chinaplanetos-production' "$helper" "$installer" "$sudoers" "$mailpit" "$portal_repair"; then fail PRODUCTION_IDENTIFIER; fi
if grep -Eq 'CMailFile|mail[[:space:]]*\(' "$mailpit"; then fail MAIL_SEND_SURFACE; fi
grep -Fq "'MAIN_MAIL_SMTP_SERVER' => 'mailpit'" "$mailpit" || fail MAILPIT_HOST
grep -Fq "'MAIN_MAIL_SMTP_PORT' => '1025'" "$mailpit" || fail MAILPIT_PORT
grep -Fq 'MAIL_SEND_EXECUTED=NO' "$mailpit" || fail MAIL_SEND_ASSERTION
grep -Fq 'SELECT GET_LOCK(' "$portal_repair" || fail PORTAL_REPAIR_LOCK
grep -Fq 'SELECT RELEASE_LOCK(' "$portal_repair" || fail PORTAL_REPAIR_UNLOCK
grep -Fq 'FOR UPDATE' "$portal_repair" || fail PORTAL_REPAIR_ROW_LOCK
grep -Fq 'NONCANONICAL_RIGHT_DELETE_FAILED' "$portal_repair" || fail PORTAL_NONCANONICAL_CONTROL
grep -Fq 'FORBIDDEN_RIGHT_DELETE_FAILED' "$portal_repair" || fail PORTAL_FORBIDDEN_CONTROL
grep -Fq 'MEMBERSHIPS_CHANGED=NO USERS_CHANGED=NO BUSINESS_RECORDS=NONE EMAIL_SENT=NO' "$portal_repair" || fail PORTAL_NO_BUSINESS_MUTATION_ASSERTION
if grep -Eq 'DELETE FROM .*usergroup(_user)?([^_A-Za-z]|$)|DELETE FROM .*[^_]user([^_A-Za-z]|$)' "$portal_repair"; then fail PORTAL_DESTRUCTIVE_IDENTITY_MUTATION; fi
if grep -Eq '(INSERT|UPDATE|DELETE|ALTER|DROP|CREATE)[[:space:]]' "$root/deploy/tests/test_m068_staging_configuration.php"; then fail CONFIG_TEST_MUTATES; fi

helper_sha=$(sha256 "$helper"); sudoers_sha=$(sha256 "$sudoers")
[[ "$(constant HELPER_SHA "$installer")" == "$helper_sha" ]] || fail INSTALLER_HELPER_PIN
[[ "$(constant SUDOERS_SHA "$installer")" == "$sudoers_sha" ]] || fail INSTALLER_SUDOERS_PIN
[[ "$(constant PREDECESSOR_HELPER_SHA "$installer")" == 2905d15d4f4a2c1a9c49cbf45faf37049ef9d9040b807910f885299a32a2f2e7 ]] || fail PREDECESSOR_HELPER_PIN
[[ "$(constant PREDECESSOR_SUDOERS_SHA "$installer")" == d59be9bd212a0602e1ce966e9d07d4f018e6fe6b0c9b15f280c1915e009a878e ]] || fail PREDECESSOR_SUDOERS_PIN
while read -r expected rel; do
  if [[ "$rel" == m068-website-lifecycle-32e82ff.tar.gz ]]; then
    [[ "$expected" == "$artifact_sha" ]] || fail RELEASE_MANIFEST_ARTIFACT
  else
    [[ -f "$root/$rel" && $(sha256 "$root/$rel") == "$expected" ]] || fail RELEASE_MANIFEST_COMPONENT
  fi
done <"$root/deploy/releases/m068-staging-deployment.sha256"
echo "M068_STAGING_RELEASE_CONTRACT=PASS ARTIFACT_SHA=$artifact_sha MEMBERS=37 RUNTIME=26 SUPPORT=7 DEPENDENCIES=5 COMMANDS=8 TARGET=ERP_STAGING_ONLY MAIL_SEND=NO PRODUCTION_ACCESSED=NO"
