#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_production_m062_r04_terminal_trend.sh"
installer="$root/deploy/tools/install_production_m062_r04_terminal_trend_helper.sh"
builder="$root/deploy/tools/build_m062_r04_artifact.py"
sudoers="$root/deploy/config/codex-deploy-production-m062-r04-terminal-trend.sudoers"
artifact=${M062_R04_ARTIFACT:-/private/tmp/m062-production-terminal-trend-f0d8d33.tar.gz}

fail() { echo "M062_R04_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

bash -n "$helper"
bash -n "$installer"
python3 -c 'import pathlib,sys; p=pathlib.Path(sys.argv[1]); compile(p.read_text(), str(p), "exec")' "$builder"
[[ -f "$artifact" && ! -L "$artifact" ]] || fail 'ARTIFACT_ABSENT'
[[ $(sha256 "$artifact") == 'a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c' ]] || fail 'ARTIFACT_HASH'

test_root=$(mktemp -d /tmp/m062-r04-contract.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
python3 "$builder" --output "$test_root/rebuilt.tar.gz" >/dev/null
cmp "$artifact" "$test_root/rebuilt.tar.gz"
python3 - "$artifact" <<'PY'
import pathlib, sys, tarfile
p = pathlib.Path(sys.argv[1])
with tarfile.open(p) as tf:
    members = tf.getmembers()
    assert len(members) == 8
    assert all(m.isfile() and not m.issym() and not m.islnk() for m in members)
    assert all(m.uid == 0 and m.gid == 0 and m.mode == 0o644 for m in members)
    assert len({m.name for m in members}) == 8
PY
mkdir -p "$test_root/extracted"
tar -xzf "$artifact" -C "$test_root/extracted"
php "$test_root/extracted/deploy/tests/test_m043_public_rate_token.php" | grep -Fq 'M043_RATE_TOKEN=PASS'
php "$test_root/extracted/deploy/tests/test_m062_public_alexandria_group.php" | grep -Fq 'M062_PUBLIC_ALEXANDRIA_GROUP=PASS'

grep -Fq "readonly RELEASE='M062-PROD-R04'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b'" "$helper"
grep -Fq "readonly SCOPE_COMMIT='3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='vision_erp'" "$helper"
grep -Fq "readonly DB_CONTAINER='vision_db'" "$helper"
grep -Fq "readonly TARGET_VOLUME='vision-os_dolibarr_custom'" "$helper"
grep -Fq "TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'" "$helper"
grep -Fq "BACKUP_ROOT='/root/chinaplanetos-production-code-backups'; STATE_ROOT='/var/lib/chinaplanetos-m062-terminal-trend-production-r04'" "$helper"
grep -Fq "readonly BASELINE_SHA='189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4'" "$helper"
grep -Fq "readonly RUNTIME_SHA='e8f83e15178811a83430e742d47e9477de92f33c8a135e15f858ec0dd536098b'" "$helper"
grep -Fq "readonly TRANSLATION_DEPENDENCY_SHA='1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54'" "$helper"
grep -Fq "M062_R04_PRODUCTION_AUTO_ROLLBACK=PASS" "$helper"
grep -Fq "M062_R04_PRODUCTION_ROLLBACK_REAPPLY=PASS" "$helper"
grep -Fq "APPLY_STATE_POST_RENAME" "$helper"
grep -Fq "ROLLBACK_STATE_POST_RENAME" "$helper"
grep -Fq "STATE_RECONCILED=YES" "$helper"
grep -Fq "TARGET_FILE_UNEXPECTED_DRIFT" "$helper"
grep -Fq "FILES=1 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES" "$helper"

helper_sha=$(sha256 "$helper")
grep -Fq "readonly HELPER_SHA='$helper_sha'" "$installer"
sudoers_sha=$(sha256 "$sudoers")
grep -Fq "readonly SUDOERS_SHA='$sudoers_sha'" "$installer"

if grep -Eq 'erp-staging|backup-erp|MAILPIT|18088|11025|18025|PRODUCTION_ACCESSED=NO' "$helper" "$installer" "$sudoers"; then fail 'STAGING_IDENTIFIER'; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE|deploy/migrations' "$helper"; then fail 'DATABASE_OR_MIGRATION_COMMAND'; fi
[[ $(wc -l < "$root/deploy/releases/m062-r04-runtime-files.txt" | tr -d ' ') -eq 1 ]]
[[ $(wc -l < "$root/deploy/releases/m062-r04-support-files.txt" | tr -d ' ') -eq 4 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-production-m062-r04-terminal-trend $command" "$sudoers"
done

echo "M062_R04_PRODUCTION_HELPER_CONTRACT=PASS TARGET=PRODUCTION_ONLY COMMANDS=6 FILES=1 HELPER_SHA=$helper_sha SUDOERS_SHA=$sudoers_sha DATABASE_CHANGED=NO PRODUCTION_ACCESSED=YES"
