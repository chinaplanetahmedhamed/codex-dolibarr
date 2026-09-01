#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly RELEASE='M074-R01'
readonly SOURCE_COMMIT='7d1cdcd2f6c1343001449f35a95f4901bf3e0fb7'
readonly ARTIFACT_SHA='2f7e368dc5c4c1455166a5b6c56c55b33bb32c0ec0a3c14f5d55023f71e68830'
readonly MANIFEST_SHA='b621576dab047a76188914e9e053752ed3753e06cc685f52854acda5b43f80c7'
readonly LAYOUT_SHA='a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756'
readonly BUNDLE_SHA='ffc8a3d00c50a90be0dc7d2e2a64d4eccc14ac9da8aec1c46082b436a2313bfb'
readonly PINS_SHA='09fbb1f3ad18f43596ebfc09a1e4970932969678949f37839e168ebefe78dd77'

readonly INBOX='/home/codex-deploy/staging-inbox'
readonly ARTIFACT="${INBOX}/m074-r01-dolibarr.tar.gz"
readonly MANIFEST="${INBOX}/m074-r01-dolibarr.manifest.tsv"
readonly LAYOUT="${INBOX}/m074-r01-dolibarr.layout.tsv"
readonly BUNDLE="${INBOX}/m074-r01-release-bundle.json"
readonly PINS="${INBOX}/m074-r01-release-pins.sha256"

readonly TARGET_CONTAINER='erp-staging-dolibarr-1'
readonly DB_CONTAINER='erp-staging-mariadb-1'
readonly MAILPIT_CONTAINER='erp-staging-mailpit-1'
readonly TARGET_VOLUME='erp-staging_erp-staging_custom'
readonly TARGET_ROOT='/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data'
readonly TARGET_HTTP='http://127.0.0.1:18088'
readonly MIN_FREE_KB=1048576

readonly -a STATIC_TESTS=(
  'deploy/tests/test_m061_customer_documents_contract.php'
  'deploy/tests/test_m070_customer_unit_projection.php'
  'deploy/tests/test_m071_catalog_order_v2_source.php'
  'deploy/tests/test_m071_country_contract.php'
  'deploy/tests/test_customer_operation_reports_security.php'
  'deploy/tests/test_m017_mobile_api_data.php'
  'deploy/tests/test_m017_mobile_api_security.php'
  'deploy/tests/test_m032_admin_customer_responsive.php'
  'deploy/tests/test_m074_qc_contract.php'
)

CANDIDATE_ROOT=''
CANDIDATE_REMOTE=''
PRIVATE_ROOT=''
ARTIFACT_COPY=''
MANIFEST_COPY=''
LAYOUT_COPY=''
BUNDLE_COPY=''
PINS_COPY=''
CURRENT_COUNT=0
DIFFERENT_COUNT=0
ABSENT_COUNT=0
LAYOUT_CURRENT_COUNT=0
LAYOUT_DIFFERENT_COUNT=0
LAYOUT_ABSENT_COUNT=0

die() { echo "M074_STAGING_FAIL=$1" >&2; exit 1; }
sha256() { sha256sum "$1" | awk '{print $1}'; }

cleanup() {
  local rc=$?
  trap - EXIT
  if [[ -n "$CANDIDATE_REMOTE" && "$CANDIDATE_REMOTE" == /tmp/m074-candidate-* ]]; then
    docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE" >/dev/null 2>&1 || true
  fi
  if [[ -n "$PRIVATE_ROOT" && "$PRIVATE_ROOT" == /root/m074-preflight.* && -d "$PRIVATE_ROOT" ]]; then
    rm -rf "$PRIVATE_ROOT"
  fi
  exit "$rc"
}
trap cleanup EXIT

require_root() {
  [[ $(id -u) -eq 0 ]] || die 'ROOT_REQUIRED'
  [[ $# -eq 1 ]] || die 'EXACTLY_ONE_COMMAND_REQUIRED'
  [[ $(basename "$0") == 'chinaplanetos-backup-erp-m074-qc-preflight' ]] || die 'HELPER_PATH_NOT_ALLOWED'
}

check_environment() {
  command -v docker >/dev/null || die 'DOCKER_ABSENT'
  command -v python3 >/dev/null || die 'PYTHON3_ABSENT'
  command -v tar >/dev/null || die 'TAR_ABSENT'
  command -v sha256sum >/dev/null || die 'SHA256SUM_ABSENT'
  command -v curl >/dev/null || die 'CURL_ABSENT'
  [[ -d "$TARGET_ROOT" && ! -L "$TARGET_ROOT" ]] || die 'TARGET_ROOT_INVALID'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$TARGET_CONTAINER" 2>/dev/null) == 'true|erp-staging|dolibarr' ]] || die 'ERP_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}' "$TARGET_CONTAINER") == "${TARGET_VOLUME}|true" ]] || die 'CUSTOM_MOUNT_MISMATCH'
  [[ $(docker volume inspect -f '{{.Driver}}|{{.Mountpoint}}' "$TARGET_VOLUME" 2>/dev/null) == "local|${TARGET_ROOT}" ]] || die 'CUSTOM_VOLUME_MISMATCH'
  [[ $(docker port "$TARGET_CONTAINER" 80/tcp 2>/dev/null) == '127.0.0.1:18088' ]] || die 'ERP_PORT_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$DB_CONTAINER" 2>/dev/null) == 'true|erp-staging|mariadb' ]] || die 'DB_IDENTITY_MISMATCH'
  [[ $(docker inspect -f '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' "$MAILPIT_CONTAINER" 2>/dev/null) == 'true|erp-staging|mailpit' ]] || die 'MAILPIT_IDENTITY_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 1025/tcp 2>/dev/null) == '127.0.0.1:11025' ]] || die 'SMTP_PORT_MISMATCH'
  [[ $(docker port "$MAILPIT_CONTAINER" 8025/tcp 2>/dev/null) == '127.0.0.1:18025' ]] || die 'MAILPIT_PORT_MISMATCH'
  [[ $(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "${TARGET_HTTP}/") =~ ^(200|302)$ ]] || die 'ERP_HTTP_UNHEALTHY'
  [[ -d "$INBOX" && ! -L "$INBOX" && $(stat -c '%U:%G %a' "$INBOX") == 'codex-deploy:codex-deploy 700' ]] || die 'INBOX_METADATA_INVALID'
  [[ $(df -Pk "$TARGET_ROOT" | awk 'NR==2 {print $4}') -ge $MIN_FREE_KB ]] || die 'TARGET_DISK_LOW'
}

copy_input() {
  local source=$1 expected_sha=$2 destination=$3
  [[ -f "$source" && ! -L "$source" ]] || die 'RELEASE_INPUT_ABSENT'
  [[ $(stat -c '%U:%G %a' "$source") == 'codex-deploy:codex-deploy 644' ]] || die 'RELEASE_INPUT_METADATA_INVALID'
  install -o root -g root -m 0600 "$source" "$destination"
  [[ $(sha256 "$destination") == "$expected_sha" ]] || die 'RELEASE_INPUT_HASH_MISMATCH'
}

validate_release() {
  PRIVATE_ROOT=$(mktemp -d /root/m074-preflight.XXXXXX)
  ARTIFACT_COPY="${PRIVATE_ROOT}/dolibarr.tar.gz"
  MANIFEST_COPY="${PRIVATE_ROOT}/dolibarr.manifest.tsv"
  LAYOUT_COPY="${PRIVATE_ROOT}/dolibarr.layout.tsv"
  BUNDLE_COPY="${PRIVATE_ROOT}/release-bundle.json"
  PINS_COPY="${PRIVATE_ROOT}/release-pins.sha256"
  copy_input "$ARTIFACT" "$ARTIFACT_SHA" "$ARTIFACT_COPY"
  copy_input "$MANIFEST" "$MANIFEST_SHA" "$MANIFEST_COPY"
  copy_input "$LAYOUT" "$LAYOUT_SHA" "$LAYOUT_COPY"
  copy_input "$BUNDLE" "$BUNDLE_SHA" "$BUNDLE_COPY"
  copy_input "$PINS" "$PINS_SHA" "$PINS_COPY"

  local listing verbose tar_stderr
  listing="${PRIVATE_ROOT}/archive.list"
  verbose="${PRIVATE_ROOT}/archive.verbose"
  tar_stderr="${PRIVATE_ROOT}/archive.stderr"
  tar -tzf "$ARTIFACT_COPY" >"$listing" 2>"$tar_stderr" || die 'ARTIFACT_TAR_INVALID'
  [[ ! -s "$tar_stderr" ]] || die 'ARTIFACT_TAR_METADATA_UNSUPPORTED'
  tar -tvzf "$ARTIFACT_COPY" >"$verbose" 2>"$tar_stderr" || die 'ARTIFACT_TAR_INVALID'
  [[ ! -s "$tar_stderr" ]] || die 'ARTIFACT_TAR_METADATA_UNSUPPORTED'
  [[ $(wc -l <"$listing") -eq 514 ]] || die 'ARTIFACT_MEMBER_COUNT_INVALID'
  [[ -z $(awk '/^\// || /(^|\/)\.\.($|\/)/ || /\/$/ || /\/\._/ || /\.DS_Store/ {print; exit}' "$listing") ]] || die 'ARTIFACT_PATH_UNSAFE'
  [[ -z $(awk 'substr($1,1,10)!="-rw-r--r--" && substr($1,1,10)!="-rwxr-xr-x" {print; exit}' "$verbose") ]] || die 'ARTIFACT_TYPE_OR_MODE_FORBIDDEN'
  [[ $(LC_ALL=C sort "$listing" | uniq -d | wc -l) -eq 0 ]] || die 'ARTIFACT_DUPLICATE_ENTRY'

  CANDIDATE_ROOT="${PRIVATE_ROOT}/candidate"
  install -d -o root -g root -m 0700 "$CANDIDATE_ROOT"
  tar -xzf "$ARTIFACT_COPY" --no-same-owner --no-same-permissions -C "$CANDIDATE_ROOT" 2>"$tar_stderr" || die 'ARTIFACT_EXTRACT_FAILED'
  [[ ! -s "$tar_stderr" ]] || die 'ARTIFACT_EXTRACT_METADATA_UNSUPPORTED'
  [[ -z $(find "$CANDIDATE_ROOT" -type l -print -quit) ]] || die 'ARTIFACT_SYMLINK_FORBIDDEN'
  [[ -z $(find "$CANDIDATE_ROOT" ! -type d ! -type f -print -quit) ]] || die 'ARTIFACT_TYPE_FORBIDDEN'

  python3 - "$CANDIDATE_ROOT" "$MANIFEST_COPY" "$LAYOUT_COPY" "$BUNDLE_COPY" "$PINS_COPY" <<'PY' || die 'RELEASE_CONTRACT_INVALID'
import csv
import hashlib
import json
import os
import pathlib
import re
import sys

root = pathlib.Path(sys.argv[1])
manifest_path = pathlib.Path(sys.argv[2])
layout_path = pathlib.Path(sys.argv[3])
bundle_path = pathlib.Path(sys.argv[4])
pins_path = pathlib.Path(sys.argv[5])
hex64 = re.compile(r"^[0-9a-f]{64}$")
safe_path = re.compile(r"^[A-Za-z0-9_.()@+\-/]+$")

def fail(reason):
    raise SystemExit(reason)

bundle = json.loads(bundle_path.read_text(encoding="utf-8"))
if bundle.get("releaseId") != "M074-R01" or bundle.get("schemaVersion") != 1:
    fail("bundle identity")
if bundle.get("bindingStatus") != "UNBOUND_REQUIRES_INDEPENDENT_REVIEW_AND_STAGING_APPROVAL":
    fail("bundle binding")
components = {item.get("id"): item for item in bundle.get("components", [])}
if set(components) != {"dolibarr", "react", "wordpress", "mobile"}:
    fail("bundle components")
dolibarr = components["dolibarr"]
expected = {
    "artifact": "dolibarr.tar.gz",
    "artifactSha256": "2f7e368dc5c4c1455166a5b6c56c55b33bb32c0ec0a3c14f5d55023f71e68830",
    "commit": "7d1cdcd2f6c1343001449f35a95f4901bf3e0fb7",
    "manifest": "dolibarr.manifest.tsv",
    "manifestSha256": "b621576dab047a76188914e9e053752ed3753e06cc685f52854acda5b43f80c7",
    "layout": "dolibarr.layout.tsv",
    "layoutSha256": "a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756",
    "members": 514,
    "configPolicy": "external-retain",
    "dataPolicy": "retain",
    "schemaPolicy": "additive-retain-on-code-rollback",
}
if any(dolibarr.get(key) != value for key, value in expected.items()):
    fail("dolibarr bundle record")

pin_lines = set(pins_path.read_text(encoding="utf-8").splitlines())
required_pins = {
    "2f7e368dc5c4c1455166a5b6c56c55b33bb32c0ec0a3c14f5d55023f71e68830  dolibarr.tar.gz",
    "b621576dab047a76188914e9e053752ed3753e06cc685f52854acda5b43f80c7  dolibarr.manifest.tsv",
    "a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756  dolibarr.layout.tsv",
    "ffc8a3d00c50a90be0dc7d2e2a64d4eccc14ac9da8aec1c46082b436a2313bfb  release-bundle.json",
}
if not required_pins <= pin_lines:
    fail("release pins")

with manifest_path.open(newline="", encoding="utf-8") as handle:
    rows = list(csv.DictReader(handle, delimiter="\t"))
if not rows or list(rows[0]) != ["source_path", "runtime_target", "classification", "mode", "size", "sha256"]:
    fail("manifest header")
if len(rows) != 514:
    fail("manifest count")
counts = {"runtime": 0, "support": 0, "evidence": 0}
seen = set()
for row in rows:
    source = row["source_path"]
    target = row["runtime_target"]
    classification = row["classification"]
    if not safe_path.fullmatch(source) or source.startswith("/") or ".." in pathlib.PurePosixPath(source).parts:
        fail("manifest source path")
    if source in seen:
        fail("manifest duplicate")
    seen.add(source)
    if classification not in counts:
        fail("manifest classification")
    counts[classification] += 1
    if classification == "runtime":
        if target != source or not source.startswith("custom/"):
            fail("runtime target")
    elif target != "-":
        fail("nonruntime target")
    if row["mode"] not in {"644", "755"} or not row["size"].isdigit() or not hex64.fullmatch(row["sha256"]):
        fail("manifest metadata")
    candidate = root / source
    if not candidate.is_file() or candidate.is_symlink():
        fail("candidate type")
    if candidate.stat().st_size != int(row["size"]):
        fail("candidate size")
    if hashlib.sha256(candidate.read_bytes()).hexdigest() != row["sha256"]:
        fail("candidate hash")
actual = {path.relative_to(root).as_posix() for path in root.rglob("*") if path.is_file()}
if actual != seen or counts != {"runtime": 298, "support": 193, "evidence": 23}:
    fail("candidate allowlist")

with layout_path.open(newline="", encoding="utf-8") as handle:
    layout = list(csv.DictReader(handle, delimiter="\t"))
if not layout or list(layout[0]) != ["runtime_path", "entry_type", "link_target", "source_sha256"]:
    fail("layout header")
expected_layout = {
    ("custom/chinaplanetcustomercenter/chinaplanetlogistics", "symlink", "../chinaplanetlogistics", "ff849783043d111acc12817d02817c0216551747e03701622006c7d92ff5cbf8"),
    ("custom/chinaplanetcustomercenter/cpkanban", "symlink", "../cpkanban", "f2f54b296d42ddc86eac8a070d938983640f12be21b22f8c54374aacb692307a"),
}
actual_layout = {(r["runtime_path"], r["entry_type"], r["link_target"], r["source_sha256"]) for r in layout}
if actual_layout != expected_layout or len(layout) != 2:
    fail("layout contract")
for _, _, link_target, source_hash in actual_layout:
    if hashlib.sha256(link_target.encode("utf-8")).hexdigest() != source_hash:
        fail("layout source hash")
PY
}

candidate_checks() {
  CANDIDATE_REMOTE="/tmp/m074-candidate-$$"
  docker exec "$TARGET_CONTAINER" rm -rf "$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" mkdir -p "$CANDIDATE_REMOTE"
  docker cp "$CANDIDATE_ROOT/." "$TARGET_CONTAINER:$CANDIDATE_REMOTE"
  docker exec "$TARGET_CONTAINER" ln -s ../chinaplanetlogistics "$CANDIDATE_REMOTE/custom/chinaplanetcustomercenter/chinaplanetlogistics"
  docker exec "$TARGET_CONTAINER" ln -s ../cpkanban "$CANDIDATE_REMOTE/custom/chinaplanetcustomercenter/cpkanban"
  docker exec "$TARGET_CONTAINER" sh -c 'find "$1/custom" "$1/deploy" -type f -name "*.php" -exec php -l {} \; >/dev/null' sh "$CANDIDATE_REMOTE" || die 'CANDIDATE_LINT_FAILED'
  local test_file
  for test_file in "${STATIC_TESTS[@]}"; do
    docker exec -e M074_DOL_DOCUMENT_ROOT="$CANDIDATE_REMOTE" "$TARGET_CONTAINER" env -u M017_DOL_DOCUMENT_ROOT -u M017_CREDENTIAL_FILE -u M017_API_BASE php "$CANDIDATE_REMOTE/$test_file" >/dev/null || die "CANDIDATE_TEST_FAILED_${test_file//\//_}"
  done
}

compare_runtime() {
  local source target classification mode size release_hash target_path target_hash target_mode state
  CURRENT_COUNT=0
  DIFFERENT_COUNT=0
  ABSENT_COUNT=0
  while IFS=$'\t' read -r source target classification mode size release_hash; do
    [[ "$classification" == 'runtime' ]] || continue
    target_path="$TARGET_ROOT/${target#custom/}"
    if [[ ! -e "$target_path" && ! -L "$target_path" ]]; then
      state='ABSENT'
      ((ABSENT_COUNT+=1))
      echo "M074_STAGING_COMPARE FILE=$source STATE=$state RELEASE_SHA=$release_hash RELEASE_MODE=$mode"
      continue
    fi
    [[ -f "$target_path" && ! -L "$target_path" ]] || die 'TARGET_FILE_INVALID'
    target_hash=$(sha256 "$target_path")
    target_mode=$(stat -c '%a' "$target_path")
    if [[ "$target_hash" == "$release_hash" && "$target_mode" == "$mode" ]]; then
      state='CURRENT'
      ((CURRENT_COUNT+=1))
    else
      state='DIFFERENT'
      ((DIFFERENT_COUNT+=1))
    fi
    echo "M074_STAGING_COMPARE FILE=$source STATE=$state TARGET_SHA=$target_hash RELEASE_SHA=$release_hash TARGET_MODE=$target_mode RELEASE_MODE=$mode"
  done < <(tail -n +2 "$MANIFEST_COPY")

  LAYOUT_CURRENT_COUNT=0
  LAYOUT_DIFFERENT_COUNT=0
  LAYOUT_ABSENT_COUNT=0
  local runtime_path entry_type link_target source_hash link_path actual_target
  while IFS=$'\t' read -r runtime_path entry_type link_target source_hash; do
    link_path="$TARGET_ROOT/${runtime_path#custom/}"
    actual_target='ABSENT'
    if [[ ! -e "$link_path" && ! -L "$link_path" ]]; then
      state='ABSENT'
      ((LAYOUT_ABSENT_COUNT+=1))
    elif [[ -L "$link_path" ]]; then
      actual_target=$(readlink "$link_path")
      if [[ "$actual_target" == "$link_target" ]]; then
        state='CURRENT'
        ((LAYOUT_CURRENT_COUNT+=1))
      else
        state='DIFFERENT'
        ((LAYOUT_DIFFERENT_COUNT+=1))
      fi
    else
      state='DIFFERENT'
      ((LAYOUT_DIFFERENT_COUNT+=1))
      actual_target='NON_SYMLINK'
    fi
    echo "M074_STAGING_COMPARE LAYOUT=$runtime_path STATE=$state EXPECTED_TARGET=$link_target ACTUAL_TARGET=${actual_target:-ABSENT}"
  done < <(tail -n +2 "$LAYOUT_COPY")
}

command_compare() {
  check_environment
  validate_release
  candidate_checks
  compare_runtime
  echo "M074_STAGING_COMPARE=PASS RELEASE=$RELEASE SOURCE_COMMIT=$SOURCE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CURRENT=$CURRENT_COUNT DIFFERENT=$DIFFERENT_COUNT ABSENT=$ABSENT_COUNT LAYOUT_CURRENT=$LAYOUT_CURRENT_COUNT LAYOUT_DIFFERENT=$LAYOUT_DIFFERENT_COUNT LAYOUT_ABSENT=$LAYOUT_ABSENT_COUNT APPLY_READY=NO BASELINE_REVIEW_REQUIRED=YES APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_preflight() {
  check_environment
  validate_release
  candidate_checks
  compare_runtime
  echo "M074_STAGING_PREFLIGHT=PASS RELEASE=$RELEASE SOURCE_COMMIT=$SOURCE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA CANDIDATE_TESTS=PASS CURRENT=$CURRENT_COUNT DIFFERENT=$DIFFERENT_COUNT ABSENT=$ABSENT_COUNT LAYOUT_CURRENT=$LAYOUT_CURRENT_COUNT LAYOUT_DIFFERENT=$LAYOUT_DIFFERENT_COUNT LAYOUT_ABSENT=$LAYOUT_ABSENT_COUNT SCHEMA=NOT_INSPECTED APPLY_READY=NO BASELINE_REVIEW_REQUIRED=YES APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

command_status() {
  echo "M074_STAGING_STATUS=PREFLIGHT_ONLY RELEASE=$RELEASE SOURCE_COMMIT=$SOURCE_COMMIT ARTIFACT_SHA=$ARTIFACT_SHA COMMANDS=compare,preflight,status APPLY_AVAILABLE=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"
}

require_root "$@"
case "$1" in
  compare) command_compare ;;
  preflight) command_preflight ;;
  status) command_status ;;
  *) die 'COMMAND_NOT_ALLOWED' ;;
esac
