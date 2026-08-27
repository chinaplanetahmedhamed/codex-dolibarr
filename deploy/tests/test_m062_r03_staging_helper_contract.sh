#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m062_r03_public_search.sh"
installer="$root/deploy/tools/install_backup_erp_m062_r03_public_search_helper.sh"
builder="$root/deploy/tools/build_m062_r03_artifact.py"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m062-ports.sudoers"
artifact=${M062_R03_ARTIFACT:-/private/tmp/m062-public-search-dcd24dd.tar.gz}

fail() { echo "M062_R03_HELPER_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

bash -n "$helper"
bash -n "$installer"
python3 -c 'import pathlib,sys; p=pathlib.Path(sys.argv[1]); compile(p.read_text(), str(p), "exec")' "$builder"
[[ -f "$artifact" && ! -L "$artifact" ]] || fail 'ARTIFACT_ABSENT'
[[ $(sha256 "$artifact") == 'c1bd85e9d3e2ddcae887c96600511495443d8d64bf54ec194da3081ef4e51e48' ]] || fail 'ARTIFACT_HASH'

test_root=$(mktemp -d /private/tmp/m062-r03-contract.XXXXXX)
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

grep -Fq "readonly RELEASE='M062-R03'" "$helper"
grep -Fq "readonly RUNTIME_COMMIT='dcd24ddd99ab5668c2ace3ccddd580abec24b472'" "$helper"
grep -Fq "readonly SCOPE_COMMIT='4c566135dcd7b9a5c37112583ce43d9ca44e136e'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='c1bd85e9d3e2ddcae887c96600511495443d8d64bf54ec194da3081ef4e51e48'" "$helper"
grep -Fq "readonly TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "readonly TARGET_VOLUME='erp-staging_erp-staging_custom'" "$helper"
grep -Fq "BACKUP_ROOT='/root/backup-erp-code-backups'; STATE_ROOT='/var/lib/chinaplanetos-m062-public-search-staging-r03'" "$helper"
grep -Fq "readonly BASELINE_SHA='ce821a27a26bbde6915f07ee4c37925a528db8d393b200ece15332b4b13b11de'" "$helper"
grep -Fq "readonly RUNTIME_SHA='189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4'" "$helper"
grep -Fq "M062_R03_STAGING_AUTO_ROLLBACK=PASS" "$helper"
grep -Fq "M062_R03_STAGING_ROLLBACK_REAPPLY=PASS" "$helper"
grep -Fq "TARGET_FILE_UNEXPECTED_DRIFT" "$helper"
grep -Fq "FILES=1 DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO" "$helper"

helper_sha=$(sha256 "$helper")
grep -Fq "readonly HELPER_SHA='$helper_sha'" "$installer"
grep -Fq "readonly PREVIOUS_HELPER_SHA='6cb72966c1a6e88dcd0f7071727f524a7259eae2f0b337ffabadc24cc86435f4'" "$installer"
grep -Fq "readonly SUDOERS_SHA='958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231'" "$installer"
[[ $(sha256 "$sudoers") == '958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231' ]] || fail 'SUDOERS_HASH'

if grep -Eq 'vision_erp|vision_db|vision-os_dolibarr_custom|https://erp\.visiontrading\.biz|production-(apply|verify|rollback)' "$helper" "$installer"; then fail 'PRODUCTION_IDENTIFIER'; fi
if grep -Eq 'mysql|mariadb-dump|mysqldump|ALTER[[:space:]]+TABLE|DROP[[:space:]]+TABLE|deploy/migrations' "$helper"; then fail 'DATABASE_OR_MIGRATION_COMMAND'; fi
[[ $(wc -l < "$root/deploy/releases/m062-r03-runtime-files.txt" | tr -d ' ') -eq 1 ]]
[[ $(wc -l < "$root/deploy/releases/m062-r03-support-files.txt" | tr -d ' ') -eq 4 ]]
[[ $(grep -c '^codex-deploy ALL=(root) NOPASSWD:' "$sudoers") -eq 6 ]]
for command in compare preflight apply verify rollback status; do
  grep -Fxq "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m062-ports $command" "$sudoers"
done

echo "M062_R03_HELPER_CONTRACT=PASS TARGET=BACKUP_ERP_ONLY COMMANDS=6 FILES=1 HELPER_SHA=$helper_sha DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
