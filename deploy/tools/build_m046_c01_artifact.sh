#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/bin:/usr/bin:/bin
umask 077

readonly EXPECTED_NAME='m046-c01-15c19dd.tar.gz'
readonly EXPECTED_SHA='79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596'
readonly RUNTIME_MANIFEST='deploy/releases/m046-c01-runtime-files.txt'
readonly SUPPORT_MANIFEST='deploy/releases/m046-c01-support-files.txt'

die() { echo "M046_C01_ARTIFACT_BUILD=FAIL REASON=$1" >&2; exit 1; }
sha256() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | awk '{print $1}'; else shasum -a 256 "$1" | awk '{print $1}'; fi; }

[[ $# -eq 1 ]] || die 'EXACTLY_ONE_OUTPUT_REQUIRED'
output=$1
[[ $(basename "$output") == "$EXPECTED_NAME" ]] || die 'OUTPUT_NAME_INVALID'
output_parent=$(cd "$(dirname "$output")" && pwd -P)
[[ -d "$output_parent" && ! -L "$output_parent" ]] || die 'OUTPUT_PARENT_INVALID'
output="$output_parent/$EXPECTED_NAME"

root=$(cd "$(dirname "$0")/../.." && pwd -P)
cd "$root"
[[ $(git rev-parse 15c19dd^{commit}) == '15c19dda5842be992e6926db473878042a0a96d5' ]] || die 'RUNTIME_COMMIT_INVALID'
[[ -f "$RUNTIME_MANIFEST" && -f "$SUPPORT_MANIFEST" ]] || die 'MANIFEST_ABSENT'
LC_ALL=C sort -c "$RUNTIME_MANIFEST" || die 'RUNTIME_MANIFEST_UNSORTED'
LC_ALL=C sort -c "$SUPPORT_MANIFEST" || die 'SUPPORT_MANIFEST_UNSORTED'

work=$(mktemp -d "${TMPDIR:-/tmp}/m046-c01-artifact.XXXXXX")
case "$work" in /tmp/m046-c01-artifact.*|/private/tmp/m046-c01-artifact.*|/var/folders/*/m046-c01-artifact.*|/private/var/folders/*/m046-c01-artifact.*) ;; *) die 'TEMP_ROOT_INVALID' ;; esac
list="$work.files"; temporary_output="$output.tmp.$$"
cleanup() {
  case "$work" in /tmp/m046-c01-artifact.*|/private/tmp/m046-c01-artifact.*|/var/folders/*/m046-c01-artifact.*|/private/var/folders/*/m046-c01-artifact.*) [[ -d "$work" ]] && rm -rf "$work" ;; esac
  rm -f "$list"
  if [[ -f "$temporary_output" ]]; then rm -f "$temporary_output"; fi
  return 0
}
trap cleanup EXIT

awk 'NF {print}' "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" >"$list"
printf '%s\n' "$RUNTIME_MANIFEST" "$SUPPORT_MANIFEST" >>"$list"
LC_ALL=C sort -u "$list" -o "$list"
[[ $(wc -l <"$list" | tr -d ' ') == 27 ]] || die 'MEMBER_COUNT_INVALID'
while IFS= read -r file; do
  [[ -f "$file" && ! -L "$file" ]] || die 'SOURCE_FILE_INVALID'
  git diff --quiet HEAD -- "$file" || die 'SOURCE_FILE_DIRTY'
done <"$list"

python3 - "$root" "$list" "$temporary_output" <<'PY'
import binascii, io, pathlib, struct, sys, tarfile

root = pathlib.Path(sys.argv[1])
names = pathlib.Path(sys.argv[2]).read_text().splitlines()
output = pathlib.Path(sys.argv[3])
tar_bytes = io.BytesIO()
with tarfile.open(fileobj=tar_bytes, mode='w', format=tarfile.USTAR_FORMAT) as bundle:
    for name in names:
        data = (root / name).read_bytes()
        member = tarfile.TarInfo(name)
        member.size = len(data)
        member.mode = 0o644
        member.uid = member.gid = 0
        member.uname = member.gname = 'root'
        member.mtime = 315532800
        member.type = tarfile.REGTYPE
        bundle.addfile(member, io.BytesIO(data))

raw = tar_bytes.getvalue()
gzip_bytes = bytearray([31, 139, 8, 0, 0, 0, 0, 0, 0, 3])
for offset in range(0, len(raw), 65535):
    block = raw[offset:offset + 65535]
    final = offset + len(block) == len(raw)
    gzip_bytes.append(1 if final else 0)
    gzip_bytes += struct.pack('<HH', len(block), 0xFFFF ^ len(block))
    gzip_bytes += block
gzip_bytes += struct.pack('<II', binascii.crc32(raw) & 0xFFFFFFFF, len(raw) & 0xFFFFFFFF)
output.write_bytes(gzip_bytes)
PY
actual_sha=$(sha256 "$temporary_output")
[[ "$actual_sha" == "$EXPECTED_SHA" ]] || die "ARTIFACT_HASH_MISMATCH_${actual_sha}"
mv -f "$temporary_output" "$output"
chmod 0644 "$output"
echo "M046_C01_ARTIFACT_BUILD=PASS PATH=$output SHA256=$EXPECTED_SHA MEMBERS=27 TYPE=USTAR MTIME=1980-01-01T00:00:00Z UID_GID=0_0 MODE=0644 PAX=NO XATTR=NO GZIP=STORED"
