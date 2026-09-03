#!/usr/bin/env python3
"""Checksum-pinned, staging-only M074-R09 release controller."""

from __future__ import annotations

import csv
import ctypes
import datetime as dt
import gzip
import hashlib
import io
import json
import os
import pathlib
import pwd
import grp
import re
import secrets
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile
import urllib.error
import urllib.request


RELEASE = "M074-R09"
SOURCE_COMMIT = "7af85bbb32c50833e909ba68dc73b6b6290dbc65"
ARTIFACT_SHA = "893a72ca12f06bca0721b88dead4e36ff84353d1dbdc57400cad0c1d68cdf27c"
MANIFEST_SHA = "483919801ee7c0803b822ae279328e57568c2e04910ea7964c323b780e095fb1"
LAYOUT_SHA = "a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756"
BUNDLE_SHA = "25cf437d764327bf5d6727674f7ec6030c40d621850bcb6cdec7e8f11e6f8725"
PINS_SHA = "287fb4d33c5e8a9464343f9fbced000771d365a52ea8027c9133f2b79d20ed65"
EXPECTED_BASELINE_SHA = "39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094"
EXPECTED_LAYOUT_TREE_SHA = {
    "custom/chinaplanetcustomercenter/chinaplanetlogistics": "c0144d63da333b4eea47b3687d673d85a23d677cad4e9da0109bc06147501bcd",
    "custom/chinaplanetcustomercenter/cpkanban": "346f68609f447214cd241f4ec11474bb8a19502ba075e25846ac77c415d7374c",
}
DEPLOY_RUNTIME_FILES = 378

INBOX = pathlib.Path("/home/codex-deploy/staging-inbox")
INPUTS = {
    "dolibarr.tar.gz": ("m074-r09-dolibarr.tar.gz", ARTIFACT_SHA),
    "dolibarr.manifest.tsv": ("m074-r09-dolibarr.manifest.tsv", MANIFEST_SHA),
    "dolibarr.layout.tsv": ("m074-r09-dolibarr.layout.tsv", LAYOUT_SHA),
    "release-bundle.json": ("m074-r09-release-bundle.json", BUNDLE_SHA),
    "release-pins.sha256": ("m074-r09-release-pins.sha256", PINS_SHA),
}
TARGET_CONTAINER = "erp-staging-dolibarr-1"
DB_CONTAINER = "erp-staging-mariadb-1"
MAILPIT_CONTAINER = "erp-staging-mailpit-1"
TARGET_VOLUME = "erp-staging_erp-staging_custom"
DB_VOLUME = "erp-staging_erp-staging_mariadb_data"
TARGET_ROOT = pathlib.Path("/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data")
TARGET_HTTP = "http://127.0.0.1:18088"
BACKUP_ROOT = pathlib.Path("/root/backup-erp-code-backups")
DB_BACKUP_ROOT = pathlib.Path("/root/backup-erp-db-backups")
ARTIFACT_ROOT = pathlib.Path("/root/backup-erp-release-artifacts/m074-r09")
STATE_ROOT = pathlib.Path("/var/lib/chinaplanetos-m074-r09-staging")
STATE_FILE = STATE_ROOT / "state.json"
PLAN_FILE = STATE_ROOT / "preflight-plan.json"
LOCK_FILE = pathlib.Path("/var/lock/chinaplanetos-backup-erp-staging-deploy.lock")
MIGRATION = "deploy/migrations/m074_qc_schema.php"
DEPLOY_CONTEXT = "m074-r09-backup-erp-root-only"
PHP_SCHEMA_FLAGS = ("-d", "display_errors=0", "-d", "log_errors=0", "-d", "error_reporting=0")
MIN_FREE_KB = 2 * 1024 * 1024
PLAN_MAX_AGE_SECONDS = 6 * 60 * 60
STATIC_TESTS = (
    "deploy/tests/test_m061_customer_documents_contract.php",
    "deploy/tests/test_m070_customer_unit_projection.php",
    "deploy/tests/test_m068_lifecycle_source.php",
    "deploy/tests/test_m068_portal_invitation_security_source.php",
    "deploy/tests/test_m068_portal_currency_unit.php",
    "deploy/tests/test_m071_catalog_order_v2_source.php",
    "deploy/tests/test_m071_country_contract.php",
    "deploy/tests/test_customer_operation_reports_security.php",
    "deploy/tests/test_m017_mobile_api_data.php",
    "deploy/tests/test_m017_mobile_api_security.php",
    "deploy/tests/test_m032_admin_customer_responsive.php",
    "deploy/tests/test_m074_qc_contract.php",
    "deploy/tests/test_m074_rights_transaction.php",
)
ALLOWED_COMMANDS = ("compare", "preflight", "apply", "verify", "rollback", "status")
HEX64 = re.compile(r"^[0-9a-f]{64}$")
SAFE_ARCHIVE_PATH = re.compile(r"^[A-Za-z0-9_.()@+\-/]+$")
SAFE_REQUEST_ID = re.compile(r"^m074-r09-[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$")
SCHEMA_STATUSES = ("READY", "MIGRATION_REQUIRED_TABLES_MISSING", "MIGRATION_REQUIRED_RIGHTS_MISSING")
SCHEMA_FAILURE_REASONS = frozenset({
    "INVALID_COMMAND", "DEPLOYMENT_CONTEXT_REQUIRED", "DOL_DOCUMENT_ROOT_INVALID",
    "DATABASE_INSPECTION_FAILED", "DATABASE_IDENTITY_UNAVAILABLE", "ENTITY_INVALID",
    "MODULE_NOT_ENABLED", "ROOT_CONTEXT_UNAVAILABLE", "TABLE_CONTRACT_INVALID",
    "TABLE_MIGRATION_FAILED", "RIGHT_INSTALL_FAILED", "RIGHTS_NOT_READY", "SCHEMA_NOT_READY",
    "RIGHT_COLLISION_500528", "RIGHT_COLLISION_500529", "RIGHT_COLLISION_500530", "RIGHT_COLLISION_500531",
})
EXPECTED_LAYOUT = {
    "custom/chinaplanetcustomercenter/chinaplanetlogistics": "../chinaplanetlogistics",
    "custom/chinaplanetcustomercenter/cpkanban": "../cpkanban",
}


class ReleaseError(RuntimeError):
    pass


def fail(reason: str) -> None:
    raise ReleaseError(reason)


def sha256_file(path: pathlib.Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def canonical_sha(value: object) -> str:
    raw = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=True).encode("ascii")
    return hashlib.sha256(raw).hexdigest()


def run(args: list[str], *, input_file=None, capture: bool = True, check: bool = True) -> subprocess.CompletedProcess:
    env = {"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"}
    return subprocess.run(args, stdin=input_file, stdout=subprocess.PIPE if capture else subprocess.DEVNULL,
                          stderr=subprocess.PIPE, text=False, check=check, env=env)


def run_text(args: list[str], *, check: bool = True) -> str:
    return run(args, check=check).stdout.decode("utf-8", "strict").strip()


def fsync_path(path: pathlib.Path) -> None:
    flags = os.O_RDONLY | (getattr(os, "O_DIRECTORY", 0) if path.is_dir() else 0)
    fd = os.open(path, flags)
    try:
        os.fsync(fd)
    finally:
        os.close(fd)


def atomic_json(path: pathlib.Path, value: object, mode: int = 0o600) -> None:
    if os.path.lexists(path):
        validate_root_file(path)
    temporary = path.with_name(f".{path.name}.{os.getpid()}.{secrets.token_hex(4)}")
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0)
    fd = os.open(temporary, flags, mode)
    try:
        payload = (json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=True) + "\n").encode("ascii")
        os.write(fd, payload)
        os.fsync(fd)
    finally:
        os.close(fd)
    os.chmod(temporary, mode)
    os.replace(temporary, path)
    fsync_path(path.parent)


def validate_root_file(path: pathlib.Path, expected_sha: str | None = None) -> None:
    info = path.lstat()
    if not stat.S_ISREG(info.st_mode) or stat.S_IMODE(info.st_mode) != 0o600 or info.st_uid != 0 or info.st_gid != 0:
        fail("ROOT_FILE_METADATA_INVALID")
    if expected_sha and sha256_file(path) != expected_sha:
        fail("ROOT_FILE_HASH_INVALID")


def prepare_roots() -> None:
    for root in (BACKUP_ROOT, DB_BACKUP_ROOT, ARTIFACT_ROOT.parent, ARTIFACT_ROOT, STATE_ROOT):
        if not os.path.lexists(root):
            root.mkdir(mode=0o700)
        info = root.lstat()
        if not stat.S_ISDIR(info.st_mode) or stat.S_IMODE(info.st_mode) != 0o700 or info.st_uid != 0 or info.st_gid != 0:
            fail("PRIVATE_ROOT_INVALID")


def http_status(url: str) -> int:
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, request, fp, code, message, headers, new_url):
            return None

    request = urllib.request.Request(url, method="GET", headers={"User-Agent": "ChinaPlanetOS-M074-R09"})
    try:
        with urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect()).open(request, timeout=15) as response:
            return int(response.status)
    except urllib.error.HTTPError as exc:
        return int(exc.code)


def check_database_target() -> None:
    if shutil.which("docker") is None:
        fail("DOCKER_ABSENT")
    inspect = '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}'
    if run_text(["docker", "inspect", "-f", inspect, DB_CONTAINER]) != "true|erp-staging|mariadb":
        fail("DB_IDENTITY_MISMATCH")
    db_mount = run_text(["docker", "inspect", "-f", '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}|{{.RW}}{{end}}{{end}}', DB_CONTAINER])
    if db_mount != DB_VOLUME + "|true":
        fail("DB_MOUNT_MISMATCH")


def check_environment() -> None:
    if shutil.which("docker") is None:
        fail("DOCKER_ABSENT")
    target_info = TARGET_ROOT.lstat()
    if not stat.S_ISDIR(target_info.st_mode) or stat.S_ISLNK(target_info.st_mode):
        fail("TARGET_ROOT_INVALID")
    inspect = '{{.State.Running}}|{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}'
    if run_text(["docker", "inspect", "-f", inspect, TARGET_CONTAINER]) != "true|erp-staging|dolibarr":
        fail("ERP_IDENTITY_MISMATCH")
    mount = run_text(["docker", "inspect", "-f", '{{range .Mounts}}{{if eq .Destination "/var/www/html/custom"}}{{.Name}}|{{.RW}}{{end}}{{end}}', TARGET_CONTAINER])
    if mount != TARGET_VOLUME + "|true":
        fail("CUSTOM_MOUNT_MISMATCH")
    if run_text(["docker", "volume", "inspect", "-f", "{{.Driver}}|{{.Mountpoint}}", TARGET_VOLUME]) != "local|" + str(TARGET_ROOT):
        fail("CUSTOM_VOLUME_MISMATCH")
    if run_text(["docker", "port", TARGET_CONTAINER, "80/tcp"]) != "127.0.0.1:18088":
        fail("ERP_PORT_MISMATCH")
    check_database_target()
    if run_text(["docker", "inspect", "-f", inspect, MAILPIT_CONTAINER]) != "true|erp-staging|mailpit":
        fail("MAILPIT_IDENTITY_MISMATCH")
    if run_text(["docker", "port", MAILPIT_CONTAINER, "1025/tcp"]) != "127.0.0.1:11025" or run_text(["docker", "port", MAILPIT_CONTAINER, "8025/tcp"]) != "127.0.0.1:18025":
        fail("MAILPIT_PORT_MISMATCH")
    if http_status(TARGET_HTTP + "/") not in (200, 302):
        fail("ERP_HTTP_UNHEALTHY")
    codex = pwd.getpwnam("codex-deploy")
    info = INBOX.lstat()
    if not stat.S_ISDIR(info.st_mode) or stat.S_IMODE(info.st_mode) != 0o700 or (info.st_uid, info.st_gid) != (codex.pw_uid, codex.pw_gid):
        fail("INBOX_METADATA_INVALID")
    if shutil.disk_usage(TARGET_ROOT).free // 1024 < MIN_FREE_KB:
        fail("TARGET_DISK_LOW")


def copy_inbox_file(name: str, expected_sha: str, destination: pathlib.Path) -> None:
    codex = pwd.getpwnam("codex-deploy")
    parent_fd = os.open(INBOX.parent, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0))
    inbox_fd = source_fd = destination_fd = -1
    try:
        inbox_fd = os.open(INBOX.name, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0), dir_fd=parent_fd)
        inbox_info = os.fstat(inbox_fd)
        if stat.S_IMODE(inbox_info.st_mode) != 0o700 or (inbox_info.st_uid, inbox_info.st_gid) != (codex.pw_uid, codex.pw_gid):
            fail("INBOX_METADATA_INVALID")
        source_fd = os.open(name, os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0), dir_fd=inbox_fd)
        source_info = os.fstat(source_fd)
        if not stat.S_ISREG(source_info.st_mode) or stat.S_IMODE(source_info.st_mode) != 0o644 or (source_info.st_uid, source_info.st_gid) != (codex.pw_uid, codex.pw_gid) or source_info.st_nlink != 1:
            fail("RELEASE_INPUT_METADATA_INVALID")
        destination_fd = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600)
        digest = hashlib.sha256()
        while True:
            chunk = os.read(source_fd, 1024 * 1024)
            if not chunk:
                break
            digest.update(chunk); os.write(destination_fd, chunk)
        os.fsync(destination_fd)
        if digest.hexdigest() != expected_sha:
            fail("RELEASE_INPUT_HASH_MISMATCH")
    finally:
        for fd in (destination_fd, source_fd, inbox_fd, parent_fd):
            if fd >= 0:
                os.close(fd)


class Candidate:
    def __init__(self, work: pathlib.Path, files: dict[str, pathlib.Path], root: pathlib.Path,
                 rows: list[dict[str, str]], layout: dict[str, str]):
        self.work = work; self.files = files; self.root = root; self.rows = rows
        self.layout = layout
        self.runtime_rows = deployment_runtime_rows(rows, layout)
        self.remote = f"/tmp/m074-r09-candidate-{os.getpid()}-{secrets.token_hex(4)}"

    def cleanup(self) -> None:
        try:
            run(["docker", "exec", "-u", "0:0", TARGET_CONTAINER, "rm", "-rf", self.remote], capture=False, check=False)
        finally:
            shutil.rmtree(self.work, ignore_errors=True)


def deployment_runtime_rows(rows: list[dict[str, str]], layout: dict[str, str]) -> list[dict[str, str]]:
    runtime = [dict(row) for row in rows if row["classification"] == "runtime"]
    expanded = list(runtime)
    for compatibility, target in sorted(layout.items()):
        canonical = os.path.normpath(pathlib.PurePosixPath(compatibility).parent.joinpath(target).as_posix())
        if not canonical.startswith("custom/") or canonical.startswith("custom/../"):
            fail("COMPATIBILITY_TARGET_INVALID")
        prefix = canonical + "/"
        for row in runtime:
            if row["runtime_target"].startswith(prefix):
                duplicate = dict(row)
                duplicate["runtime_target"] = compatibility + "/" + row["runtime_target"][len(prefix):]
                expanded.append(duplicate)
    targets = [row["runtime_target"] for row in expanded]
    if len(expanded) != DEPLOY_RUNTIME_FILES or len(set(targets)) != len(targets):
        fail("DEPLOYMENT_RUNTIME_EXPANSION_INVALID")
    return expanded


def validate_release(source_root: pathlib.Path = INBOX) -> Candidate:
    work = pathlib.Path(tempfile.mkdtemp(prefix="m074-r09-release-", dir="/root")); os.chmod(work, 0o700)
    files: dict[str, pathlib.Path] = {}
    try:
        for canonical, (inbox_name, expected_sha) in INPUTS.items():
            destination = work / canonical
            if source_root == INBOX:
                copy_inbox_file(inbox_name, expected_sha, destination)
            else:
                source = source_root / canonical; validate_root_file(source, expected_sha)
                shutil.copyfile(source, destination, follow_symlinks=False); os.chmod(destination, 0o600); fsync_path(destination)
            files[canonical] = destination
        bundle = json.loads(files["release-bundle.json"].read_text("utf-8"))
        if bundle.get("schemaVersion") != 1 or bundle.get("releaseId") != RELEASE or bundle.get("bindingStatus") != "UNBOUND_REQUIRES_INDEPENDENT_REVIEW_AND_STAGING_APPROVAL":
            fail("BUNDLE_IDENTITY_INVALID")
        components = {item.get("id"): item for item in bundle.get("components", [])}
        if set(components) != {"dolibarr", "react", "wordpress", "mobile"}:
            fail("BUNDLE_COMPONENTS_INVALID")
        expected_component = {"artifact": "dolibarr.tar.gz", "artifactSha256": ARTIFACT_SHA, "commit": SOURCE_COMMIT,
                              "manifest": "dolibarr.manifest.tsv", "manifestSha256": MANIFEST_SHA,
                              "layout": "dolibarr.layout.tsv", "layoutSha256": LAYOUT_SHA, "members": 559,
                              "configPolicy": "external-retain", "dataPolicy": "retain", "schemaPolicy": "additive-retain-on-code-rollback"}
        if any(components["dolibarr"].get(key) != value for key, value in expected_component.items()):
            fail("DOLIBARR_BUNDLE_RECORD_INVALID")
        pin_lines = set(files["release-pins.sha256"].read_text("ascii").splitlines())
        required_pins = {f"{ARTIFACT_SHA}  dolibarr.tar.gz", f"{MANIFEST_SHA}  dolibarr.manifest.tsv",
                         f"{LAYOUT_SHA}  dolibarr.layout.tsv", f"{BUNDLE_SHA}  release-bundle.json"}
        if not required_pins <= pin_lines:
            fail("RELEASE_PINS_INVALID")
        with files["dolibarr.manifest.tsv"].open(newline="", encoding="utf-8") as handle:
            rows = list(csv.DictReader(handle, delimiter="\t"))
        if not rows or list(rows[0]) != ["source_path", "runtime_target", "classification", "mode", "size", "sha256"] or len(rows) != 559:
            fail("MANIFEST_CONTRACT_INVALID")
        counts = {"runtime": 0, "support": 0, "evidence": 0}; seen: set[str] = set()
        for row in rows:
            source = row["source_path"]
            if not SAFE_ARCHIVE_PATH.fullmatch(source) or source.startswith("/") or ".." in pathlib.PurePosixPath(source).parts or source in seen:
                fail("MANIFEST_PATH_INVALID")
            seen.add(source); classification = row["classification"]
            if classification not in counts:
                fail("MANIFEST_CLASSIFICATION_INVALID")
            counts[classification] += 1
            if classification == "runtime":
                if row["runtime_target"] != source or not source.startswith("custom/"):
                    fail("RUNTIME_TARGET_INVALID")
            elif row["runtime_target"] != "-":
                fail("NONRUNTIME_TARGET_INVALID")
            if row["mode"] not in ("644", "755") or not row["size"].isdigit() or not HEX64.fullmatch(row["sha256"]):
                fail("MANIFEST_METADATA_INVALID")
        if counts != {"runtime": 308, "support": 218, "evidence": 33}:
            fail("MANIFEST_COUNTS_INVALID")
        root = work / "candidate"; root.mkdir(mode=0o700)
        with gzip.open(files["dolibarr.tar.gz"], "rb") as compressed, tarfile.open(fileobj=compressed, mode="r:") as archive:
            members = archive.getmembers()
            if len(members) != 559 or [member.name for member in members] != sorted(seen):
                fail("ARTIFACT_MEMBER_SET_INVALID")
            for member in members:
                if not member.isfile() or member.name not in seen or member.uid != 0 or member.gid != 0 or member.mtime != 0 or stat.S_IMODE(member.mode) not in (0o644, 0o755):
                    fail("ARTIFACT_ENTRY_INVALID")
            archive.extractall(root)
        by_source = {row["source_path"]: row for row in rows}
        actual = {path.relative_to(root).as_posix() for path in root.rglob("*") if path.is_file()}
        if actual != seen or any(path.is_symlink() for path in root.rglob("*")):
            fail("CANDIDATE_TREE_INVALID")
        for source, row in by_source.items():
            path = root / source
            if path.stat().st_size != int(row["size"]) or sha256_file(path) != row["sha256"]:
                fail("CANDIDATE_HASH_INVALID")
        with files["dolibarr.layout.tsv"].open(newline="", encoding="utf-8") as handle:
            layout_rows = list(csv.DictReader(handle, delimiter="\t"))
        layout = {row["runtime_path"]: row["link_target"] for row in layout_rows}
        if layout != EXPECTED_LAYOUT or len(layout_rows) != 2:
            fail("LAYOUT_CONTRACT_INVALID")
        for row in layout_rows:
            if row["entry_type"] != "symlink" or hashlib.sha256(row["link_target"].encode()).hexdigest() != row["source_sha256"]:
                fail("LAYOUT_HASH_INVALID")
        return Candidate(work, files, root, rows, layout)
    except Exception:
        shutil.rmtree(work, ignore_errors=True); raise


def candidate_checks(candidate: Candidate) -> None:
    run(["docker", "exec", "-u", "0:0", TARGET_CONTAINER, "rm", "-rf", candidate.remote], capture=False)
    run(["docker", "exec", "-u", "0:0", TARGET_CONTAINER, "mkdir", "-p", candidate.remote], capture=False)
    run(["docker", "cp", str(candidate.root) + "/.", TARGET_CONTAINER + ":" + candidate.remote], capture=False)
    for path, target in candidate.layout.items():
        run(["docker", "exec", "-u", "0:0", TARGET_CONTAINER, "ln", "-s", target, candidate.remote + "/" + path], capture=False)
    lint_script = r'find "$1/custom" "$1/deploy" -type f -name "*.php" -exec php -l {} \; >/dev/null'
    run(["docker", "exec", TARGET_CONTAINER, "sh", "-eu", "-c", lint_script, "sh", candidate.remote], capture=False)
    for test in STATIC_TESTS:
        command = ["docker", "exec", "-e", "M074_DOL_DOCUMENT_ROOT=" + candidate.remote, TARGET_CONTAINER,
                   "env", "-u", "M017_DOL_DOCUMENT_ROOT", "-u", "M017_CREDENTIAL_FILE", "-u", "M017_API_BASE",
                   "php", candidate.remote + "/" + test]
        run(command, capture=False)


def open_dir_chain(root_fd: int, parts: tuple[str, ...], *, create: bool = False) -> int:
    current = os.dup(root_fd)
    try:
        for part in parts:
            if not part or part in (".", "..") or "/" in part:
                fail("TARGET_PATH_INVALID")
            created = False
            try:
                next_fd = os.open(part, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0), dir_fd=current)
            except FileNotFoundError:
                if not create:
                    raise
                os.mkdir(part, 0o755, dir_fd=current)
                created = True
                next_fd = os.open(part, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0), dir_fd=current)
            if not stat.S_ISDIR(os.fstat(next_fd).st_mode):
                fail("TARGET_PARENT_INVALID")
            if created:
                os.fchown(next_fd, pwd.getpwnam("www-data").pw_uid, grp.getgrnam("www-data").gr_gid)
                os.fchmod(next_fd, 0o755); os.fsync(next_fd); os.fsync(current)
            os.close(current); current = next_fd
        return current
    except Exception:
        os.close(current); raise


def read_file_at(root_fd: int, relative: str) -> tuple[bytes, os.stat_result]:
    parts = tuple(pathlib.PurePosixPath(relative).parts); parent = open_dir_chain(root_fd, parts[:-1])
    try:
        fd = os.open(parts[-1], os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0), dir_fd=parent)
        try:
            info = os.fstat(fd)
            if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
                fail("TARGET_FILE_INVALID")
            data = bytearray()
            while True:
                chunk = os.read(fd, 1024 * 1024)
                if not chunk:
                    break
                data.extend(chunk)
            return bytes(data), info
        finally:
            os.close(fd)
    finally:
        os.close(parent)


def directory_tree_snapshot(relative: str) -> list[dict[str, object]]:
    root = TARGET_ROOT / relative
    if not root.is_dir() or root.is_symlink():
        fail("COMPATIBILITY_DIRECTORY_INVALID")
    entries: list[dict[str, object]] = []
    for path in [root, *sorted(root.rglob("*"))]:
        info = path.lstat()
        item_relative = "" if path == root else path.relative_to(root).as_posix()
        if stat.S_ISDIR(info.st_mode):
            kind = "DIRECTORY"; digest = ""
        elif stat.S_ISREG(info.st_mode):
            kind = "FILE"; digest = sha256_file(path)
        else:
            fail("COMPATIBILITY_DIRECTORY_UNSAFE_ENTRY")
        entries.append({"path": item_relative, "kind": kind, "sha256": digest,
                        "uid": info.st_uid, "gid": info.st_gid, "mode": stat.S_IMODE(info.st_mode)})
    return entries


def semantic_tree(tree: list[dict[str, object]]) -> list[dict[str, object]]:
    return [{key: item[key] for key in ("path", "kind", "sha256", "mode")} for item in tree]


def overlay_relative_targets(candidate: Candidate, layout_relative: str) -> set[str]:
    prefix = "custom/" + layout_relative.rstrip("/") + "/"
    return {row["runtime_target"][len(prefix):] for row in candidate.runtime_rows
            if row["runtime_target"].startswith(prefix)}


def overlay_parents_exist(tree: list[dict[str, object]], targets: set[str]) -> bool:
    directories = {str(item["path"]) for item in tree if item.get("kind") == "DIRECTORY"}
    for target in targets:
        parent = pathlib.PurePosixPath(target).parent
        if ("" if parent.as_posix() == "." else parent.as_posix()) not in directories:
            return False
    return True


def overlay_managed_directories(targets: set[str]) -> set[str]:
    directories: set[str] = set()
    for target in targets:
        parent = pathlib.PurePosixPath(target).parent
        while parent.as_posix() != ".":
            directories.add(parent.as_posix())
            parent = parent.parent
    return directories


def overlay_managed_parents_ready(tree: list[dict[str, object]], targets: set[str]) -> bool:
    entries = {str(item["path"]): item for item in tree}
    existing = {path for path, item in entries.items() if item.get("kind") == "DIRECTORY"}
    if not (overlay_managed_directories(targets) - existing):
        return True
    root = entries.get("")
    return root is not None and root.get("kind") == "DIRECTORY" and \
        (root.get("uid"), root.get("gid")) == (pwd.getpwnam("www-data").pw_uid, grp.getgrnam("www-data").gr_gid)


def overlay_release_tree_matches(candidate: Candidate, layout_relative: str,
                                 tree: list[dict[str, object]]) -> bool:
    entries = {str(item["path"]): item for item in tree}
    prefix = "custom/" + layout_relative.rstrip("/") + "/"
    rows = [row for row in candidate.runtime_rows if row["runtime_target"].startswith(prefix)]
    if not rows:
        return False
    for row in rows:
        entry = entries.get(row["runtime_target"][len(prefix):])
        if entry is None or entry.get("kind") != "FILE" or entry.get("sha256") != row["sha256"] or entry.get("mode") != int(row["mode"], 8):
            return False
    return True


def runtime_snapshot(candidate: Candidate) -> dict[str, object]:
    root_fd = os.open(TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0)); files = []
    try:
        for row in candidate.runtime_rows:
            relative = row["runtime_target"][len("custom/"):]
            try:
                data, info = read_file_at(root_fd, relative)
                files.append({"path": relative, "state": "PRESENT", "sha256": hashlib.sha256(data).hexdigest(),
                              "uid": info.st_uid, "gid": info.st_gid, "mode": stat.S_IMODE(info.st_mode)})
            except FileNotFoundError:
                files.append({"path": relative, "state": "ABSENT"})
        layout = []
        for source, expected in sorted(candidate.layout.items()):
            relative = source[len("custom/"):]; parts = tuple(pathlib.PurePosixPath(relative).parts)
            try:
                parent = open_dir_chain(root_fd, parts[:-1])
                try:
                    info = os.stat(parts[-1], dir_fd=parent, follow_symlinks=False)
                    if stat.S_ISLNK(info.st_mode):
                        actual = os.readlink(parts[-1], dir_fd=parent); state = "CURRENT" if actual == expected else "DIFFERENT"
                    elif stat.S_ISDIR(info.st_mode):
                        actual = "NON_SYMLINK_DIRECTORY"
                        tree = directory_tree_snapshot(relative)
                        expected_tree_sha = EXPECTED_LAYOUT_TREE_SHA.get(source)
                        tree_sha = canonical_sha(tree)
                        targets = overlay_relative_targets(candidate, relative)
                        baseline_ready = expected_tree_sha == tree_sha and overlay_managed_parents_ready(tree, targets)
                        state = "OVERLAY_DIRECTORY" if baseline_ready or overlay_release_tree_matches(candidate, relative, tree) else "DIFFERENT"
                    else:
                        actual = "NON_DIRECTORY_NON_SYMLINK"; state = "DIFFERENT"
                finally:
                    os.close(parent)
            except FileNotFoundError:
                actual = "ABSENT"; state = "ABSENT"
            item = {"path": relative, "state": state, "expected": expected, "actual": actual}
            if state in ("OVERLAY_DIRECTORY", "DIFFERENT") and actual == "NON_SYMLINK_DIRECTORY":
                item["tree"] = tree
            layout.append(item)
    finally:
        os.close(root_fd)
    return {"files": files, "layout": layout}


def compare_summary(candidate: Candidate, snapshot: dict[str, object], *, emit: bool) -> dict[str, int]:
    expected = {row["runtime_target"][len("custom/"):]: row for row in candidate.runtime_rows}
    counts = {"current": 0, "different": 0, "absent": 0, "layout_current": 0,
              "layout_overlay_directory": 0, "layout_different": 0, "layout_absent": 0}
    for item in snapshot["files"]:
        row = expected[item["path"]]
        if item["state"] == "ABSENT": state = "ABSENT"; counts["absent"] += 1
        elif item["sha256"] == row["sha256"] and item["mode"] == int(row["mode"], 8): state = "CURRENT"; counts["current"] += 1
        else: state = "DIFFERENT"; counts["different"] += 1
        if emit:
            target_sha = item.get("sha256", "ABSENT")
            target_mode = format(int(item["mode"]), "03o") if item["state"] == "PRESENT" else "ABSENT"
            target_uid = item.get("uid", "ABSENT"); target_gid = item.get("gid", "ABSENT")
            print(f"M074_STAGING_COMPARE FILE=custom/{item['path']} STATE={state} RELEASE_SHA={row['sha256']} RELEASE_MODE={row['mode']} TARGET_SHA={target_sha} TARGET_MODE={target_mode} TARGET_UID={target_uid} TARGET_GID={target_gid}")
    for item in snapshot["layout"]:
        counts["layout_" + item["state"].lower()] += 1
        if emit:
            tree_sha = canonical_sha(item["tree"]) if isinstance(item.get("tree"), list) else "NONE"
            print(f"M074_STAGING_COMPARE LAYOUT=custom/{item['path']} STATE={item['state']} EXPECTED_TARGET={item['expected']} ACTUAL_TARGET={item['actual']} TREE_SHA={tree_sha}")
    return counts


def schema_diagnostic(raw: bytes) -> str:
    if not raw:
        return ""
    if len(raw) > 192 or not raw.endswith(b"\n") or raw.count(b"\n") != 1 or b"\r" in raw:
        return "SCHEMA_DIAGNOSTIC_INVALID"
    try:
        line = raw.decode("ascii", "strict").rstrip("\n")
    except UnicodeDecodeError:
        return "SCHEMA_DIAGNOSTIC_INVALID"
    match = re.fullmatch(r"M074_SCHEMA=FAIL REASON=([A-Z0-9_]+)", line)
    if not match or match.group(1) not in SCHEMA_FAILURE_REASONS:
        return "SCHEMA_DIAGNOSTIC_INVALID"
    return match.group(1)


def schema_command(candidate: Candidate, action: str) -> tuple[int, str, str]:
    command = ["docker", "exec", "-u", "0:0", "-e", "M074_DOL_DOCUMENT_ROOT=/var/www/html"]
    if action == "apply": command += ["-e", "M074_DEPLOY_CONTEXT=" + DEPLOY_CONTEXT]
    command += [TARGET_CONTAINER, "php", *PHP_SCHEMA_FLAGS, candidate.remote + "/" + MIGRATION, action]
    result = run(command, check=False)
    try:
        output = result.stdout.decode("ascii", "strict").strip()
    except UnicodeDecodeError:
        fail("SCHEMA_STDOUT_INVALID")
    return result.returncode, output, schema_diagnostic(result.stderr)


def schema_status(candidate: Candidate) -> str:
    code, output, diagnostic = schema_command(candidate, "check")
    if code == 0 and not diagnostic and output == "M074_SCHEMA_CHECK=READY RIGHTS_DEFAULT=DISABLED": return "READY"
    if code == 2 and not diagnostic and output == "M074_SCHEMA_CHECK=MIGRATION_REQUIRED REASON=TABLES_MISSING": return "MIGRATION_REQUIRED_TABLES_MISSING"
    if code == 2 and not diagnostic and output == "M074_SCHEMA_CHECK=MIGRATION_REQUIRED REASON=RIGHTS_MISSING": return "MIGRATION_REQUIRED_RIGHTS_MISSING"
    if diagnostic: fail("SCHEMA_CHECK_FAILED_" + diagnostic)
    fail("SCHEMA_CHECK_FAILED")


DB_IDENTITY_SCRIPT = r'''set -eu
db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
[ -n "$db" ] && [ -n "$pass" ]
MYSQL_PWD="$pass" exec mariadb -N -u root "$db" -e "SELECT LOWER(SHA2(CONCAT(DATABASE(),CHAR(31),@@hostname,CHAR(31),@@port),256))"
'''


def database_identity(candidate: Candidate) -> str:
    code, output, diagnostic = schema_command(candidate, "identity"); prefix = "M074_SCHEMA_DATABASE_IDENTITY="
    if code != 0 or diagnostic or not output.startswith(prefix) or not HEX64.fullmatch(output[len(prefix):]): fail("ERP_DATABASE_IDENTITY_INVALID")
    erp_identity = output[len(prefix):]
    db_identity = run_text(["docker", "exec", DB_CONTAINER, "sh", "-eu", "-c", DB_IDENTITY_SCRIPT])
    if not HEX64.fullmatch(db_identity) or db_identity != erp_identity: fail("DATABASE_BACKUP_TARGET_MISMATCH")
    return erp_identity


def retain_release(candidate: Candidate) -> None:
    for canonical, (_, expected_sha) in INPUTS.items():
        target = ARTIFACT_ROOT / canonical
        if target.exists() or target.is_symlink(): validate_root_file(target, expected_sha); continue
        temporary = ARTIFACT_ROOT / ("." + canonical + "." + secrets.token_hex(4))
        fd = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600)
        try:
            with candidate.files[canonical].open("rb") as handle:
                for chunk in iter(lambda: handle.read(1024 * 1024), b""): os.write(fd, chunk)
            os.fsync(fd)
        finally: os.close(fd)
        if sha256_file(temporary) != expected_sha: temporary.unlink(missing_ok=True); fail("RETAINED_RELEASE_HASH_INVALID")
        try: os.link(temporary, target, follow_symlinks=False)
        except FileExistsError: validate_root_file(target, expected_sha)
        temporary.unlink(missing_ok=True); fsync_path(ARTIFACT_ROOT)


def write_backup(candidate: Candidate, snapshot: dict[str, object], prefix: str) -> tuple[pathlib.Path, str, pathlib.Path, str]:
    archive_path = BACKUP_ROOT / f"{prefix}.tar.gz"; prestate_path = BACKUP_ROOT / f"{prefix}.prestate.json"
    if archive_path.exists() or prestate_path.exists() or archive_path.is_symlink() or prestate_path.is_symlink(): fail("CODE_BACKUP_COLLISION")
    atomic_json(prestate_path, snapshot)
    root_fd = os.open(TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0))
    try:
        archive_fd = os.open(archive_path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600)
        try:
            with os.fdopen(os.dup(archive_fd), "wb") as raw, gzip.GzipFile(fileobj=raw, mode="wb", mtime=0) as zipped, tarfile.open(fileobj=zipped, mode="w|") as archive:
                for item in snapshot["files"]:
                    if item["state"] != "PRESENT": continue
                    data, info = read_file_at(root_fd, item["path"])
                    if hashlib.sha256(data).hexdigest() != item["sha256"] or (info.st_uid, info.st_gid, stat.S_IMODE(info.st_mode)) != (item["uid"], item["gid"], item["mode"]): fail("CODE_BACKUP_SOURCE_CHANGED")
                    member = tarfile.TarInfo(item["path"]); member.size = len(data); member.uid = item["uid"]; member.gid = item["gid"]; member.mode = item["mode"]; member.mtime = 0
                    archive.addfile(member, io.BytesIO(data))
            os.fsync(archive_fd)
        finally: os.close(archive_fd)
    finally: os.close(root_fd)
    fsync_path(BACKUP_ROOT); archive_sha = sha256_file(archive_path); prestate_sha = sha256_file(prestate_path)
    validate_code_backup(archive_path, archive_sha, prestate_path, prestate_sha)
    return archive_path, archive_sha, prestate_path, prestate_sha


def validate_code_backup(archive_path: pathlib.Path, archive_sha: str, prestate_path: pathlib.Path, prestate_sha: str) -> dict[str, object]:
    validate_root_file(archive_path, archive_sha); validate_root_file(prestate_path, prestate_sha)
    snapshot = json.loads(prestate_path.read_text("ascii")); present = {item["path"]: item for item in snapshot.get("files", []) if item.get("state") == "PRESENT"}
    with gzip.open(archive_path, "rb") as compressed, tarfile.open(fileobj=compressed, mode="r:") as archive:
        members = archive.getmembers()
        if len(members) != len(present) or len({member.name for member in members}) != len(members): fail("CODE_BACKUP_MEMBER_SET_INVALID")
        for member in members:
            if member.name not in present or not member.isfile(): fail("CODE_BACKUP_ENTRY_INVALID")
            data = archive.extractfile(member).read(); item = present[member.name]
            if hashlib.sha256(data).hexdigest() != item["sha256"] or (member.uid, member.gid, stat.S_IMODE(member.mode)) != (item["uid"], item["gid"], item["mode"]): fail("CODE_BACKUP_CONTENT_INVALID")
    return snapshot


RENAME_NOREPLACE = 1
RENAME_EXCHANGE = 2
AT_FDCWD = -100
_LIBC = ctypes.CDLL(None, use_errno=True)


def renameat2(old_dir_fd: int, old: str, new_dir_fd: int, new: str, flags: int) -> None:
    function = getattr(_LIBC, "renameat2", None)
    if function is None:
        fail("RENAMEAT2_UNAVAILABLE")
    result = function(ctypes.c_int(old_dir_fd), ctypes.c_char_p(os.fsencode(old)),
                      ctypes.c_int(new_dir_fd), ctypes.c_char_p(os.fsencode(new)), ctypes.c_uint(flags))
    if result != 0:
        error = ctypes.get_errno()
        raise OSError(error, os.strerror(error), old, new)


def item_signature(data: bytes, info: os.stat_result) -> dict[str, object]:
    return {"state": "PRESENT", "sha256": hashlib.sha256(data).hexdigest(), "uid": info.st_uid,
            "gid": info.st_gid, "mode": stat.S_IMODE(info.st_mode)}


def signature_matches(actual: dict[str, object], expected: dict[str, object]) -> bool:
    if actual.get("state") != expected.get("state"):
        return False
    if actual.get("state") == "ABSENT":
        return True
    return all(actual.get(key) == expected.get(key) for key in ("sha256", "uid", "gid", "mode"))


def atomic_write_target(root_fd: int, relative: str, data: bytes, uid: int, gid: int, mode: int,
                        expected: dict[str, object] | None = None) -> None:
    parts = tuple(pathlib.PurePosixPath(relative).parts); parent = open_dir_chain(root_fd, parts[:-1], create=True); temporary = ".m074-r09-" + secrets.token_hex(12); fd = -1
    try:
        fd = os.open(temporary, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600, dir_fd=parent)
        offset = 0
        while offset < len(data): offset += os.write(fd, data[offset:offset + 1024 * 1024])
        os.fchown(fd, uid, gid); os.fchmod(fd, mode); os.fsync(fd); os.close(fd); fd = -1
        if expected is None:
            os.replace(temporary, parts[-1], src_dir_fd=parent, dst_dir_fd=parent)
        elif expected.get("state") == "ABSENT":
            try:
                renameat2(parent, temporary, parent, parts[-1], RENAME_NOREPLACE)
            except FileExistsError:
                fail("TARGET_BASELINE_CHANGED")
        elif expected.get("state") == "PRESENT":
            try:
                renameat2(parent, temporary, parent, parts[-1], RENAME_EXCHANGE)
            except FileNotFoundError:
                fail("TARGET_BASELINE_CHANGED")
            old_data, old_info = read_file_at(parent, temporary)
            if not signature_matches(item_signature(old_data, old_info), expected):
                renameat2(parent, temporary, parent, parts[-1], RENAME_EXCHANGE)
                fail("TARGET_BASELINE_CHANGED")
            os.unlink(temporary, dir_fd=parent)
        else:
            fail("EXPECTED_TARGET_STATE_INVALID")
        os.fsync(parent)
    finally:
        if fd >= 0: os.close(fd)
        try: os.unlink(temporary, dir_fd=parent)
        except FileNotFoundError: pass
        os.close(parent)


def release_snapshot(candidate: Candidate) -> dict[str, object]:
    web_uid = pwd.getpwnam("www-data").pw_uid; web_gid = grp.getgrnam("www-data").gr_gid
    return {"files": [{"path": row["runtime_target"][len("custom/"):], "state": "PRESENT",
                        "sha256": row["sha256"], "uid": web_uid, "gid": web_gid,
                        "mode": int(row["mode"], 8)} for row in candidate.runtime_rows]}


def apply_candidate(candidate: Candidate, snapshot: dict[str, object]) -> None:
    target_fd = os.open(TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0)); candidate_fd = os.open(candidate.root, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0))
    web_uid = pwd.getpwnam("www-data").pw_uid; web_gid = grp.getgrnam("www-data").gr_gid
    try:
        baseline = {item["path"]: item for item in snapshot["files"]}
        for row in candidate.runtime_rows:
            data, info = read_file_at(candidate_fd, row["source_path"])
            if hashlib.sha256(data).hexdigest() != row["sha256"] or stat.S_IMODE(info.st_mode) != int(row["mode"], 8): fail("CANDIDATE_CHANGED_BEFORE_APPLY")
            relative = row["runtime_target"][len("custom/"):]
            atomic_write_target(target_fd, relative, data, web_uid, web_gid, int(row["mode"], 8), baseline[relative])
        apply_layout(candidate, snapshot)
    finally: os.close(candidate_fd); os.close(target_fd)


def current_layout_item(item: dict[str, object]) -> dict[str, object]:
    path = str(item["path"]); expected = str(item["expected"]); target = TARGET_ROOT / path
    if target.is_symlink():
        actual = os.readlink(target)
        return {"path": path, "state": "CURRENT" if actual == expected else "DIFFERENT",
                "expected": expected, "actual": actual}
    if not target.exists():
        return {"path": path, "state": "ABSENT", "expected": expected, "actual": "ABSENT"}
    if target.is_dir():
        tree = directory_tree_snapshot(path)
        result = {"path": path, "state": "CONVERTIBLE", "expected": expected,
                  "actual": "NON_SYMLINK_DIRECTORY", "tree": tree}
        return result
    return {"path": path, "state": "DIFFERENT", "expected": expected,
            "actual": "NON_DIRECTORY_NON_SYMLINK"}


def overlay_unmanaged_matches(candidate: Candidate, item: dict[str, object],
                              current_tree: list[dict[str, object]] | None = None) -> bool:
    if item.get("state") != "OVERLAY_DIRECTORY" or not isinstance(item.get("tree"), list):
        return False
    before = {str(entry["path"]): entry for entry in item.get("tree", [])}
    after_tree = current_tree if current_tree is not None else directory_tree_snapshot(str(item["path"]))
    after = {str(entry["path"]): entry for entry in after_tree}
    targets = overlay_relative_targets(candidate, str(item["path"]))
    root = before.get("")
    if not targets or root is None or root.get("kind") != "DIRECTORY":
        return False
    for target in targets:
        before.pop(target, None); after.pop(target, None)
    for directory in overlay_managed_directories(targets):
        if directory in before:
            continue
        entry = after.pop(directory, None)
        if entry is None or entry.get("kind") != "DIRECTORY" or entry.get("mode") != 0o755 or \
                (entry.get("uid"), entry.get("gid")) != (root.get("uid"), root.get("gid")):
            return False
    return before == after and overlay_release_tree_matches(candidate, str(item["path"]), after_tree)


def verify_overlay_preserved(candidate: Candidate, item: dict[str, object]) -> None:
    if not overlay_unmanaged_matches(candidate, item):
        fail("COMPATIBILITY_OVERLAY_CHANGED")


def apply_layout(candidate: Candidate, snapshot: dict[str, object]) -> None:
    for item in snapshot["layout"]:
        state = str(item["state"])
        if state == "CURRENT":
            if current_layout_item(item)["state"] != "CURRENT": fail("TARGET_BASELINE_CHANGED")
            continue
        if state == "OVERLAY_DIRECTORY":
            verify_overlay_preserved(candidate, item)
            continue
        if state not in ("ABSENT", "CONVERTIBLE"):
            fail("COMPATIBILITY_LAYOUT_NOT_READY")
        current = current_layout_item(item)
        if current != item:
            fail("TARGET_BASELINE_CHANGED")
        target = TARGET_ROOT / str(item["path"]); parent = target.parent
        temporary = parent / (".m074-r09-layout-" + secrets.token_hex(12))
        moved = False; linked = False
        try:
            if state == "CONVERTIBLE":
                os.rename(target, temporary); moved = True
            os.symlink(str(item["expected"]), target); linked = True; fsync_path(parent)
            if moved:
                shutil.rmtree(temporary); moved = False; fsync_path(parent)
        except Exception:
            if linked and target.is_symlink() and os.readlink(target) == str(item["expected"]):
                target.unlink(); linked = False
            if moved and not target.exists() and not target.is_symlink():
                os.rename(temporary, target); moved = False
            fsync_path(parent)
            raise


def snapshot_overlay_targets(snapshot: dict[str, object], layout_relative: str) -> set[str]:
    prefix = layout_relative.rstrip("/") + "/"
    return {str(item["path"])[len(prefix):] for item in snapshot.get("files", [])
            if isinstance(item, dict) and isinstance(item.get("path"), str) and str(item["path"]).startswith(prefix)}


def remove_managed_overlay_directories(item: dict[str, object], allowed_snapshot: dict[str, object]) -> None:
    prior_tree = item.get("tree")
    if not isinstance(prior_tree, list):
        fail("ROLLBACK_PRESTATE_INVALID")
    prior = {str(entry["path"]): entry for entry in prior_tree}
    root = prior.get("")
    if root is None or root.get("kind") != "DIRECTORY":
        fail("ROLLBACK_PRESTATE_INVALID")
    targets = snapshot_overlay_targets(allowed_snapshot, str(item["path"]))
    if not targets:
        fail("ROLLBACK_LAYOUT_MISMATCH")
    current = {str(entry["path"]): entry for entry in directory_tree_snapshot(str(item["path"]))}
    removable = [path for path in overlay_managed_directories(targets) if path not in prior]
    target_fd = os.open(TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0))
    try:
        for relative in sorted(removable, key=lambda value: (value.count("/"), value), reverse=True):
            entry = current.get(relative)
            if entry is None:
                continue
            if entry.get("kind") != "DIRECTORY" or entry.get("mode") != 0o755 or \
                    (entry.get("uid"), entry.get("gid")) != (root.get("uid"), root.get("gid")):
                fail("ROLLBACK_LAYOUT_MISMATCH")
            parts = tuple(pathlib.PurePosixPath(str(item["path"]), relative).parts)
            parent = open_dir_chain(target_fd, parts[:-1])
            quarantine = ".m074-r09-rmdir-" + secrets.token_hex(12)
            moved = False
            try:
                renameat2(parent, parts[-1], parent, quarantine, RENAME_NOREPLACE); moved = True
                directory_fd = os.open(quarantine, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0), dir_fd=parent)
                try:
                    info = os.fstat(directory_fd)
                    named = os.stat(quarantine, dir_fd=parent, follow_symlinks=False)
                    if not stat.S_ISDIR(info.st_mode) or (info.st_dev, info.st_ino) != (named.st_dev, named.st_ino) or \
                            stat.S_IMODE(info.st_mode) != 0o755 or (info.st_uid, info.st_gid) != (root.get("uid"), root.get("gid")) or \
                            os.listdir(directory_fd):
                        fail("ROLLBACK_LAYOUT_MISMATCH")
                    os.rmdir(quarantine, dir_fd=parent); moved = False; os.fsync(parent)
                finally:
                    os.close(directory_fd)
            except Exception:
                if moved:
                    try:
                        renameat2(parent, quarantine, parent, parts[-1], RENAME_NOREPLACE); moved = False; os.fsync(parent)
                    except Exception:
                        fail("ROLLBACK_LAYOUT_RECOVERY_REQUIRED")
                raise
            finally:
                os.close(parent)
    finally:
        os.close(target_fd)


def restore_layout(snapshot: dict[str, object], allowed_snapshot: dict[str, object] | None = None) -> None:
    for item in snapshot.get("layout", []):
        prior = str(item["state"]); target = TARGET_ROOT / str(item["path"])
        if prior == "CURRENT":
            if current_layout_item(item)["state"] != "CURRENT": fail("ROLLBACK_BASELINE_CHANGED")
            continue
        if prior == "OVERLAY_DIRECTORY":
            if directory_tree_snapshot(str(item["path"])) != item.get("tree"):
                if allowed_snapshot is None:
                    fail("ROLLBACK_LAYOUT_MISMATCH")
                remove_managed_overlay_directories(item, allowed_snapshot)
            if directory_tree_snapshot(str(item["path"])) != item.get("tree"):
                fail("ROLLBACK_LAYOUT_MISMATCH")
            continue
        if prior == "CONVERTIBLE" and target.is_dir() and not target.is_symlink():
            if current_layout_item(item) == item: continue
            fail("ROLLBACK_BASELINE_CHANGED")
        if not target.is_symlink() or os.readlink(target) != str(item["expected"]):
            fail("ROLLBACK_BASELINE_CHANGED")
        target.unlink(); fsync_path(target.parent)
        if prior == "ABSENT":
            continue
        if prior != "CONVERTIBLE":
            fail("ROLLBACK_PRESTATE_INVALID")
        tree = item.get("tree")
        if not isinstance(tree, list) or not tree or tree[0].get("path") != "" or tree[0].get("kind") != "DIRECTORY":
            fail("ROLLBACK_PRESTATE_INVALID")
        canonical = os.path.normpath(os.path.join(os.path.dirname(str(item["path"])), str(item["expected"])))
        source_root = TARGET_ROOT / canonical
        for entry in tree:
            if entry.get("kind") != "DIRECTORY": continue
            destination = target / str(entry["path"])
            destination.mkdir(mode=0o700, parents=True, exist_ok=False)
            os.chown(destination, int(entry["uid"]), int(entry["gid"]), follow_symlinks=False)
        for entry in tree:
            if entry.get("kind") != "FILE": continue
            relative = str(entry["path"]); source = source_root / relative
            if not source.is_file() or source.is_symlink() or sha256_file(source) != entry.get("sha256"):
                fail("ROLLBACK_LAYOUT_SOURCE_MISMATCH")
            destination = target / relative
            with source.open("rb") as source_handle, destination.open("xb") as destination_handle:
                shutil.copyfileobj(source_handle, destination_handle)
                destination_handle.flush(); os.fsync(destination_handle.fileno())
            os.chown(destination, int(entry["uid"]), int(entry["gid"]), follow_symlinks=False)
            os.chmod(destination, int(entry["mode"]), follow_symlinks=False)
        for entry in reversed(tree):
            if entry.get("kind") == "DIRECTORY":
                destination = target / str(entry["path"])
                os.chmod(destination, int(entry["mode"]), follow_symlinks=False)
        fsync_path(target.parent)
        if current_layout_item(item) != item:
            fail("ROLLBACK_LAYOUT_MISMATCH")


def runtime_snapshot_from_rows(snapshot: dict[str, object]) -> dict[str, object]:
    root_fd = os.open(TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0)); files = []
    try:
        for prior in snapshot["files"]:
            try:
                data, info = read_file_at(root_fd, prior["path"])
                files.append({"path": prior["path"], "state": "PRESENT", "sha256": hashlib.sha256(data).hexdigest(), "uid": info.st_uid, "gid": info.st_gid, "mode": stat.S_IMODE(info.st_mode)})
            except FileNotFoundError: files.append({"path": prior["path"], "state": "ABSENT"})
    finally: os.close(root_fd)
    return {"files": files}


def remove_target_if_expected(root_fd: int, relative: str, expected: dict[str, object]) -> None:
    parts = tuple(pathlib.PurePosixPath(relative).parts)
    try:
        parent = open_dir_chain(root_fd, parts[:-1])
    except FileNotFoundError:
        if expected.get("state") == "ABSENT":
            return
        fail("ROLLBACK_BASELINE_CHANGED")
    temporary = ".m074-r09-remove-" + secrets.token_hex(12)
    try:
        try:
            renameat2(parent, parts[-1], parent, temporary, RENAME_NOREPLACE)
        except FileNotFoundError:
            if expected.get("state") == "ABSENT":
                return
            fail("ROLLBACK_BASELINE_CHANGED")
        data, info = read_file_at(parent, temporary)
        if not signature_matches(item_signature(data, info), expected):
            try:
                renameat2(parent, temporary, parent, parts[-1], RENAME_NOREPLACE)
            except FileExistsError:
                fail("ROLLBACK_BASELINE_CHANGED_RECOVERY_REQUIRED")
            fail("ROLLBACK_BASELINE_CHANGED")
        os.unlink(temporary, dir_fd=parent); os.fsync(parent)
    finally:
        try: os.unlink(temporary, dir_fd=parent)
        except FileNotFoundError: pass
        os.close(parent)


def restore_snapshot(archive_path: pathlib.Path, archive_sha: str, prestate_path: pathlib.Path, prestate_sha: str,
                     allowed_snapshot: dict[str, object] | None = None) -> None:
    snapshot = validate_code_backup(archive_path, archive_sha, prestate_path, prestate_sha); archived = {}
    with gzip.open(archive_path, "rb") as compressed, tarfile.open(fileobj=compressed, mode="r:") as archive:
        for member in archive.getmembers(): archived[member.name] = archive.extractfile(member).read()
    target_fd = os.open(TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY | getattr(os, "O_NOFOLLOW", 0))
    try:
        current = {item["path"]: item for item in runtime_snapshot_from_rows(snapshot)["files"]}
        allowed = {item["path"]: item for item in (allowed_snapshot or snapshot)["files"]}
        for item in snapshot["files"]:
            actual = current[item["path"]]
            if signature_matches(actual, item):
                continue
            expected = allowed.get(item["path"])
            if expected is None or not signature_matches(actual, expected):
                fail("ROLLBACK_BASELINE_CHANGED")
            if item["state"] == "PRESENT":
                atomic_write_target(target_fd, item["path"], archived[item["path"]], item["uid"], item["gid"], item["mode"], expected)
            elif item["state"] == "ABSENT":
                remove_target_if_expected(target_fd, item["path"], expected)
            else: fail("PRESTATE_INVALID")
    finally: os.close(target_fd)
    if runtime_snapshot_from_rows(snapshot)["files"] != snapshot["files"]: fail("ROLLBACK_PRESTATE_MISMATCH")
    restore_layout(snapshot, allowed_snapshot)


DB_DUMP_SCRIPT = r'''set -eu
db=${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}
pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
[ -n "$db" ] && [ -n "$pass" ]
if command -v mariadb-dump >/dev/null 2>&1; then dump=mariadb-dump; else dump=mysqldump; fi
MYSQL_PWD="$pass" exec "$dump" --single-transaction --quick --skip-lock-tables --hex-blob --routines --triggers --events --default-character-set=utf8mb4 -u root "$db"
'''


def database_fingerprint(database: str, aliases: tuple[str, ...]) -> str:
    script = r'''set -eu
db=$M074_FINGERPRINT_DB
pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
[ -n "$pass" ]
case "$db" in *[!A-Za-z0-9_]*) exit 1;; esac
q(){ MYSQL_PWD="$pass" mariadb -N -u root -e "$1"; }
q "SELECT TABLE_TYPE,TABLE_NAME,IFNULL(ENGINE,''),IFNULL(TABLE_COLLATION,'') FROM information_schema.tables WHERE table_schema='$db' ORDER BY TABLE_TYPE,TABLE_NAME"
q "SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,IFNULL(COLUMN_DEFAULT,'<NULL>'),EXTRA,IFNULL(GENERATION_EXPRESSION,'') FROM information_schema.columns WHERE table_schema='$db' ORDER BY TABLE_NAME,ORDINAL_POSITION"
q "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,IFNULL(SUB_PART,0),INDEX_TYPE FROM information_schema.statistics WHERE table_schema='$db' ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX"
q "SELECT CONSTRAINT_NAME,TABLE_NAME,UNIQUE_CONSTRAINT_NAME,REFERENCED_TABLE_NAME,MATCH_OPTION,UPDATE_RULE,DELETE_RULE FROM information_schema.referential_constraints WHERE constraint_schema='$db' ORDER BY CONSTRAINT_NAME"
q "SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,POSITION_IN_UNIQUE_CONSTRAINT,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.key_column_usage WHERE constraint_schema='$db' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION"
q "SELECT tc.TABLE_NAME,tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.table_constraints tc JOIN information_schema.check_constraints cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA='$db' AND tc.CONSTRAINT_TYPE='CHECK' ORDER BY tc.TABLE_NAME,tc.CONSTRAINT_NAME"
q "SELECT TABLE_NAME,IFNULL(PARTITION_NAME,''),IFNULL(SUBPARTITION_NAME,''),IFNULL(PARTITION_METHOD,''),IFNULL(SUBPARTITION_METHOD,''),IFNULL(PARTITION_EXPRESSION,''),IFNULL(SUBPARTITION_EXPRESSION,''),IFNULL(PARTITION_DESCRIPTION,'') FROM information_schema.partitions WHERE table_schema='$db' ORDER BY TABLE_NAME,PARTITION_ORDINAL_POSITION,SUBPARTITION_ORDINAL_POSITION"
q "SELECT TABLE_NAME,REPLACE(VIEW_DEFINITION,'$db','<DB>'),CHECK_OPTION,IS_UPDATABLE FROM information_schema.views WHERE table_schema='$db' ORDER BY TABLE_NAME"
q "SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_ORDER,ACTION_CONDITION,REPLACE(ACTION_STATEMENT,'$db','<DB>'),ACTION_ORIENTATION,ACTION_TIMING FROM information_schema.triggers WHERE trigger_schema='$db' ORDER BY TRIGGER_NAME"
q "SELECT ROUTINE_NAME,ROUTINE_TYPE,DATA_TYPE,IS_DETERMINISTIC,SQL_DATA_ACCESS,SECURITY_TYPE,REPLACE(IFNULL(ROUTINE_DEFINITION,''),'$db','<DB>') FROM information_schema.routines WHERE routine_schema='$db' ORDER BY ROUTINE_TYPE,ROUTINE_NAME"
q "SELECT EVENT_NAME,EVENT_TYPE,EXECUTE_AT,INTERVAL_VALUE,INTERVAL_FIELD,STARTS,ENDS,STATUS,ON_COMPLETION,REPLACE(EVENT_DEFINITION,'$db','<DB>') FROM information_schema.events WHERE event_schema='$db' ORDER BY EVENT_NAME"
q "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema='$db' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME" | while read -r table; do
  case "$table" in ''|*[!A-Za-z0-9_]*) exit 1;; esac
  printf 'TABLE=%s\n' "$table"
  MYSQL_PWD="$pass" mariadb -N -u root "$db" -e "SELECT COUNT(*) FROM \`$table\`"
  MYSQL_PWD="$pass" mariadb -N -u root "$db" -e "CHECKSUM TABLE \`$table\`" | awk -F '\t' '{print "CHECKSUM=" $2}'
done
'''
    output = run(["docker", "exec", "-e", "M074_FINGERPRINT_DB=" + database, DB_CONTAINER, "sh", "-eu", "-c", script]).stdout
    for alias in aliases:
        output = output.replace(alias.encode("ascii"), b"<DB>")
    return hashlib.sha256(output).hexdigest()


def drop_validation_database(database: str) -> None:
    if not re.fullmatch(r"m074_restore_check_[0-9]+_[0-9a-f]{8}", database):
        fail("DATABASE_RESTORE_CHECK_NAME_INVALID")
    check_database_target()
    script = r'''set -eu
db=$M074_TEMP_DB
pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
[ -n "$pass" ]
case "$db" in *[!A-Za-z0-9_]*) exit 1;; esac
MYSQL_PWD="$pass" exec mariadb -u root -e "DROP DATABASE IF EXISTS \`$db\`"
'''
    run(["docker", "exec", "-e", "M074_TEMP_DB=" + database, DB_CONTAINER, "sh", "-eu", "-c", script], capture=False)


def make_validation_database_name() -> str:
    return "m074_restore_check_" + str(os.getpid()) + "_" + secrets.token_hex(4)


def validate_database_backup(backup: pathlib.Path, backup_sha: str, database: str) -> None:
    validate_root_file(backup, backup_sha)
    if not re.fullmatch(r"m074_restore_check_[0-9]+_[0-9a-f]{8}", database):
        fail("DATABASE_RESTORE_CHECK_NAME_INVALID")
    create = r'''set -eu
db=$M074_TEMP_DB
pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
[ -n "$pass" ]
case "$db" in *[!A-Za-z0-9_]*) exit 1;; esac
MYSQL_PWD="$pass" exec mariadb -u root -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
'''
    run(["docker", "exec", "-e", "M074_TEMP_DB=" + database, DB_CONTAINER, "sh", "-eu", "-c", create], capture=False)
    try:
        import_script = r'''set -eu
db=$M074_TEMP_DB
pass=${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}
[ -n "$pass" ]
case "$db" in *[!A-Za-z0-9_]*) exit 1;; esac
MYSQL_PWD="$pass" exec mariadb -u root "$db"
'''
        with backup.open("rb") as handle: run(["docker", "exec", "-i", "-e", "M074_TEMP_DB=" + database, DB_CONTAINER, "sh", "-eu", "-c", import_script], input_file=handle, capture=False)
        database_name = run_text(["docker", "exec", DB_CONTAINER, "sh", "-eu", "-c", 'printf %s "${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}"'])
        if not re.fullmatch(r"[A-Za-z0-9_]+", database_name): fail("DATABASE_NAME_INVALID")
        aliases = (database_name, database)
        if database_fingerprint(database_name, aliases) != database_fingerprint(database, aliases): fail("DATABASE_RESTORE_CHECK_MISMATCH")
    finally: drop_validation_database(database)


def create_database_backup(request_id: str, expected_identity: str) -> tuple[pathlib.Path, str]:
    backup = DB_BACKUP_ROOT / f"{request_id}.sql"
    if backup.exists() or backup.is_symlink(): fail("DATABASE_BACKUP_COLLISION")
    fd = os.open(backup, os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, "O_NOFOLLOW", 0), 0o600)
    try:
        with os.fdopen(os.dup(fd), "wb") as output:
            result = subprocess.run(["docker", "exec", DB_CONTAINER, "sh", "-eu", "-c", DB_DUMP_SCRIPT], stdout=output, stderr=subprocess.PIPE,
                                    env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"})
        if result.returncode != 0: fail("DATABASE_BACKUP_FAILED")
        os.fsync(fd)
    finally: os.close(fd)
    if backup.stat().st_size == 0: fail("DATABASE_BACKUP_EMPTY")
    fsync_path(DB_BACKUP_ROOT); backup_sha = sha256_file(backup)
    direct_identity = run_text(["docker", "exec", DB_CONTAINER, "sh", "-eu", "-c", DB_IDENTITY_SCRIPT])
    if direct_identity != expected_identity: fail("DATABASE_IDENTITY_CHANGED_DURING_BACKUP")
    return backup, backup_sha


def state_payload(status: str, **fields) -> dict[str, object]:
    payload = {"schema": 1, "release": RELEASE, "status": status, "source_commit": SOURCE_COMMIT,
               "artifact_sha256": ARTIFACT_SHA, "updated_at": dt.datetime.now(dt.timezone.utc).isoformat()}
    payload.update(fields); return payload


def save_state(status: str, **fields) -> dict[str, object]:
    payload = state_payload(status, **fields); atomic_json(STATE_FILE, payload); return payload


def preserve_state(state: dict[str, object], status: str, **fields) -> dict[str, object]:
    payload = dict(state); payload.update(fields); payload["status"] = status
    payload["updated_at"] = dt.datetime.now(dt.timezone.utc).isoformat(); atomic_json(STATE_FILE, payload); return payload


def load_state() -> dict[str, object]:
    validate_root_file(STATE_FILE); state = json.loads(STATE_FILE.read_text("ascii"))
    if state.get("schema") != 1 or state.get("release") != RELEASE or state.get("source_commit") != SOURCE_COMMIT or state.get("artifact_sha256") != ARTIFACT_SHA: fail("STATE_IDENTITY_INVALID")
    allowed = {"PENDING_APPLY_PREPARE", "PENDING_APPLY_CODE", "PENDING_APPLY_SCHEMA", "DEPLOYED", "APPLY_FAILED_NO_MUTATION",
               "APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED", "APPLY_FAILED_RECOVERY_REQUIRED", "PENDING_ROLLBACK_PREPARE",
               "PENDING_ROLLBACK", "ROLLED_BACK_SCHEMA_RETAINED", "ROLLBACK_FAILED_NO_MUTATION", "ROLLBACK_FAILED_REAPPLIED", "ROLLBACK_FAILED_RECOVERY_REQUIRED"}
    if state.get("status") not in allowed: fail("STATE_STATUS_INVALID")
    schema_reason = state.get("schema_failure_reason")
    if schema_reason is not None and schema_reason not in SCHEMA_FAILURE_REASONS | {"SCHEMA_DIAGNOSTIC_INVALID", "SCHEMA_RESPONSE_INVALID"}:
        fail("STATE_SCHEMA_DIAGNOSTIC_INVALID")
    return state


def state_backup(state: dict[str, object], prefix: str = "") -> tuple[pathlib.Path, str, pathlib.Path, str]:
    def value(name: str) -> str:
        result = state.get(prefix + name)
        if not isinstance(result, str) or not result: fail("STATE_BACKUP_FIELD_INVALID")
        return result
    archive = pathlib.Path(value("code_backup")); archive_sha = value("code_backup_sha256"); prestate = pathlib.Path(value("prestate")); prestate_sha = value("prestate_sha256")
    if archive.parent != BACKUP_ROOT or prestate.parent != BACKUP_ROOT or not HEX64.fullmatch(archive_sha) or not HEX64.fullmatch(prestate_sha): fail("STATE_BACKUP_PATH_INVALID")
    validate_code_backup(archive, archive_sha, prestate, prestate_sha); return archive, archive_sha, prestate, prestate_sha


def state_database_backup(state: dict[str, object]) -> tuple[pathlib.Path, str]:
    path = pathlib.Path(str(state.get("database_backup", ""))); digest = str(state.get("database_backup_sha256", ""))
    if path.parent != DB_BACKUP_ROOT or not HEX64.fullmatch(digest): fail("STATE_DATABASE_BACKUP_INVALID")
    validate_root_file(path, digest); return path, digest


def require_saved_database_identity(state: dict[str, object], current: str) -> None:
    saved = state.get("database_identity")
    if not isinstance(saved, str) or not HEX64.fullmatch(saved) or saved != current:
        fail("DEPLOYED_DATABASE_IDENTITY_MISMATCH")


def deployed_snapshot_matches(candidate: Candidate, baseline_snapshot: dict[str, object]) -> bool:
    snapshot = runtime_snapshot(candidate); expected = {row["runtime_target"][len("custom/"):]: row for row in candidate.runtime_rows}
    web_uid = pwd.getpwnam("www-data").pw_uid; web_gid = grp.getgrnam("www-data").gr_gid
    for item in snapshot["files"]:
        row = expected[item["path"]]
        if item["state"] != "PRESENT" or item["sha256"] != row["sha256"] or item["mode"] != int(row["mode"], 8) or (item["uid"], item["gid"]) != (web_uid, web_gid): return False
    baseline_layout = {str(item.get("path")): item for item in baseline_snapshot.get("layout", [])
                       if isinstance(item, dict) and isinstance(item.get("path"), str)}
    if len(baseline_layout) != len(snapshot["layout"]):
        return False
    for item in snapshot["layout"]:
        state = item.get("state")
        if state == "CURRENT":
            continue
        if state != "OVERLAY_DIRECTORY" or not isinstance(item.get("tree"), list):
            return False
        baseline_item = baseline_layout.get(str(item["path"]))
        if baseline_item is None or not overlay_unmanaged_matches(candidate, baseline_item, item["tree"]):
            return False
    return True


def live_checks(candidate: Candidate, baseline_snapshot: dict[str, object]) -> None:
    if not deployed_snapshot_matches(candidate, baseline_snapshot): fail("LIVE_RUNTIME_MISMATCH")
    if schema_status(candidate) != "READY": fail("LIVE_SCHEMA_NOT_READY")
    if http_status(TARGET_HTTP + "/") not in (200, 302): fail("LIVE_HTTP_FAILED")
    if http_status(TARGET_HTTP + "/custom/chinaplanetoperations/qc/app.php?id=1") not in (200, 302, 401, 403): fail("LIVE_QC_ROUTE_FAILED")


def lint_snapshot(snapshot: dict[str, object]) -> None:
    for item in snapshot["files"]:
        if item["state"] != "PRESENT" or not str(item["path"]).endswith(".php"):
            continue
        relative = str(item["path"])
        if not SAFE_ARCHIVE_PATH.fullmatch(relative) or relative.startswith("/") or ".." in pathlib.PurePosixPath(relative).parts:
            fail("ROLLBACK_LINT_PATH_INVALID")
        run(["docker", "exec", TARGET_CONTAINER, "php", "-l", "/var/www/html/custom/" + relative], capture=False)


def rollback_checks(candidate: Candidate, prior_snapshot: dict[str, object]) -> None:
    if runtime_snapshot_from_rows(prior_snapshot)["files"] != prior_snapshot["files"]:
        fail("ROLLBACK_PRESTATE_MISMATCH")
    lint_snapshot(prior_snapshot)
    if schema_status(candidate) != "READY": fail("ROLLBACK_SCHEMA_NOT_READY")
    if http_status(TARGET_HTTP + "/") not in (200, 302): fail("ROLLBACK_HTTP_FAILED")
    if http_status(TARGET_HTTP + "/custom/chinaplanetoperations/index.php") not in (200, 302, 401, 403):
        fail("ROLLBACK_OPERATIONS_ROUTE_FAILED")


def plan_payload(snapshot: dict[str, object], schema: str, db_identity: str) -> dict[str, object]:
    return {"schema": 1, "release": RELEASE, "source_commit": SOURCE_COMMIT, "artifact_sha256": ARTIFACT_SHA,
            "snapshot_sha256": canonical_sha(snapshot), "schema_status": schema, "database_identity": db_identity,
            "created_at": dt.datetime.now(dt.timezone.utc).isoformat()}


def require_expected_baseline(snapshot: dict[str, object]) -> str:
    actual = canonical_sha(snapshot)
    if EXPECTED_BASELINE_SHA == "UNBOUND_DIAGNOSTIC_ONLY":
        fail("BASELINE_NOT_PINNED")
    if not HEX64.fullmatch(EXPECTED_BASELINE_SHA) or actual != EXPECTED_BASELINE_SHA:
        fail("BASELINE_MISMATCH")
    return actual


def require_plan(snapshot: dict[str, object], schema: str, db_identity: str,
                 minimum_created_at: dt.datetime | None = None) -> str:
    validate_root_file(PLAN_FILE); plan = json.loads(PLAN_FILE.read_text("ascii")); expected = plan_payload(snapshot, schema, db_identity)
    for key in ("schema", "release", "source_commit", "artifact_sha256", "snapshot_sha256", "schema_status", "database_identity"):
        if plan.get(key) != expected[key]: fail("PREFLIGHT_PLAN_MISMATCH")
    created = dt.datetime.fromisoformat(str(plan.get("created_at")))
    if created.tzinfo is None or (dt.datetime.now(dt.timezone.utc) - created).total_seconds() > PLAN_MAX_AGE_SECONDS: fail("PREFLIGHT_PLAN_EXPIRED")
    if minimum_created_at is not None and created <= minimum_created_at:
        fail("PREFLIGHT_REQUIRED_AFTER_STATE")
    return canonical_sha(plan)


def status_plan() -> tuple[str, dt.datetime | None]:
    if not PLAN_FILE.exists() and not PLAN_FILE.is_symlink():
        return "ABSENT", None
    validate_root_file(PLAN_FILE)
    plan = json.loads(PLAN_FILE.read_text("ascii"))
    if (plan.get("schema") != 1 or plan.get("release") != RELEASE or plan.get("source_commit") != SOURCE_COMMIT
            or plan.get("artifact_sha256") != ARTIFACT_SHA or not HEX64.fullmatch(str(plan.get("snapshot_sha256", "")))
            or plan.get("schema_status") not in SCHEMA_STATUSES
            or not HEX64.fullmatch(str(plan.get("database_identity", "")))):
        fail("PREFLIGHT_PLAN_STATUS_INVALID")
    created = dt.datetime.fromisoformat(str(plan.get("created_at")))
    if created.tzinfo is None:
        fail("PREFLIGHT_PLAN_STATUS_INVALID")
    if (dt.datetime.now(dt.timezone.utc) - created).total_seconds() > PLAN_MAX_AGE_SECONDS:
        return "EXPIRED", created
    return "READY", created


def apply_status(plan_status: str, plan_created: dt.datetime | None, state: dict[str, object] | None) -> tuple[str, str]:
    if plan_status != "READY" or plan_created is None:
        return "NO", "PREFLIGHT_" + plan_status
    if state is None:
        return "YES", "PREFLIGHT_BOUND"
    status = str(state.get("status", ""))
    if status.startswith("PENDING_"):
        return "NO", "RECONCILIATION_REQUIRED"
    if status in ("APPLY_FAILED_RECOVERY_REQUIRED", "ROLLBACK_FAILED_RECOVERY_REQUIRED"):
        return "NO", "RECOVERY_REQUIRED"
    updated = dt.datetime.fromisoformat(str(state.get("updated_at", "")))
    if updated.tzinfo is None:
        fail("STATE_UPDATED_AT_INVALID")
    if plan_created <= updated:
        return "NO", "PREFLIGHT_REQUIRED_AFTER_STATE"
    return "YES", "PREFLIGHT_BOUND"


def make_request_id() -> str:
    value = "m074-r09-" + dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%SZ") + "-" + secrets.token_hex(6)
    if not SAFE_REQUEST_ID.fullmatch(value): fail("REQUEST_ID_INVALID")
    return value


def common_candidate(*, retained: bool = False) -> Candidate:
    check_environment(); candidate = validate_release(ARTIFACT_ROOT if retained else INBOX)
    try:
        candidate_checks(candidate)
        return candidate
    except Exception:
        candidate.cleanup()
        raise


def acquire_lock() -> int:
    fd = os.open(LOCK_FILE, os.O_RDWR | os.O_CREAT | getattr(os, "O_NOFOLLOW", 0), 0o600)
    info = os.fstat(fd)
    if not stat.S_ISREG(info.st_mode) or stat.S_IMODE(info.st_mode) != 0o600 or info.st_uid != 0 or info.st_gid != 0:
        os.close(fd)
        fail("LOCK_FILE_INVALID")
    return fd


def command_compare() -> None:
    candidate = common_candidate()
    try:
        snapshot = runtime_snapshot(candidate); counts = compare_summary(candidate, snapshot, emit=True); schema = schema_status(candidate); database_identity(candidate); snapshot_sha = canonical_sha(snapshot)
        baseline_match = "YES" if HEX64.fullmatch(EXPECTED_BASELINE_SHA) and snapshot_sha == EXPECTED_BASELINE_SHA else "NO"
        print(f"M074_STAGING_COMPARE=PASS RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} SNAPSHOT_SHA={snapshot_sha} BASELINE_MATCH={baseline_match} DEPLOY_FILES={DEPLOY_RUNTIME_FILES} CURRENT={counts['current']} DIFFERENT={counts['different']} ABSENT={counts['absent']} LAYOUT_CURRENT={counts['layout_current']} LAYOUT_OVERLAY_DIRECTORY={counts['layout_overlay_directory']} LAYOUT_DIFFERENT={counts['layout_different']} LAYOUT_ABSENT={counts['layout_absent']} SCHEMA={schema} DATABASE_IDENTITY_BOUND=YES APPLY_READY=NO PREFLIGHT_REQUIRED=YES APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO")
    finally: candidate.cleanup()


def command_preflight() -> None:
    prepare_roots(); candidate = common_candidate()
    try:
        snapshot = runtime_snapshot(candidate); counts = compare_summary(candidate, snapshot, emit=True)
        if counts["layout_different"]: fail("COMPATIBILITY_LAYOUT_NOT_READY")
        require_expected_baseline(snapshot)
        schema = schema_status(candidate); identity = database_identity(candidate); plan = plan_payload(snapshot, schema, identity); atomic_json(PLAN_FILE, plan); plan_sha = canonical_sha(plan)
        print(f"M074_STAGING_PREFLIGHT=PASS RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} PLAN_SHA={plan_sha} CURRENT={counts['current']} DIFFERENT={counts['different']} ABSENT={counts['absent']} SCHEMA={schema} DATABASE_IDENTITY_BOUND=YES BASELINE_BOUND=YES APPLY_READY=YES APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO")
    finally: candidate.cleanup()


def reconcile_pending(state: dict[str, object], deployed_release: dict[str, object] | None = None,
                      candidate: Candidate | None = None) -> str:
    status = str(state["status"])
    if status == "PENDING_APPLY_PREPARE":
        validation_database = str(state.get("validation_database", ""))
        try:
            if validation_database:
                drop_validation_database(validation_database)
            preserve_state(state, "APPLY_FAILED_NO_MUTATION", validation_database="", reconciliation="RESTORE_CHECK_DATABASE_REMOVED_NO_RUNTIME_MUTATION")
            return "APPLY_FAILED_NO_MUTATION"
        except Exception:
            preserve_state(state, "APPLY_FAILED_RECOVERY_REQUIRED", reconciliation="RESTORE_CHECK_DATABASE_RECOVERY_REQUIRED")
            raise
    if status in ("PENDING_APPLY_CODE", "PENDING_APPLY_SCHEMA"):
        try:
            if deployed_release is None: fail("RECONCILIATION_RELEASE_SNAPSHOT_REQUIRED")
            restore_snapshot(*state_backup(state), allowed_snapshot=deployed_release); database, database_sha = state_database_backup(state)
            preserve_state(state, "APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED", database_backup=str(database), database_backup_sha256=database_sha, reconciliation="CODE_RESTORED_SCHEMA_RETAINED")
            return "APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED"
        except Exception:
            preserve_state(state, "APPLY_FAILED_RECOVERY_REQUIRED", reconciliation="RECOVERY_REQUIRED"); raise
    if status == "PENDING_ROLLBACK_PREPARE":
        try:
            if candidate is None: fail("RECONCILIATION_CANDIDATE_REQUIRED")
            prior_snapshot = validate_code_backup(*state_backup(state))
            identity = database_identity(candidate); require_saved_database_identity(state, identity); live_checks(candidate, prior_snapshot)
            preserve_state(state, "DEPLOYED", reconciliation="ROLLBACK_NO_MUTATION", last_rollback_result="FAILED_NO_MUTATION")
            return "ROLLBACK_FAILED_NO_MUTATION_STATE_DEPLOYED"
        except Exception:
            preserve_state(state, "ROLLBACK_FAILED_RECOVERY_REQUIRED", reconciliation="RECOVERY_REQUIRED")
            raise
    if status == "PENDING_ROLLBACK":
        try:
            if candidate is None: fail("RECONCILIATION_CANDIDATE_REQUIRED")
            prior_snapshot = validate_code_backup(*state_backup(state))
            restore_snapshot(*state_backup(state, "reapply_"), allowed_snapshot=prior_snapshot)
            identity = database_identity(candidate); require_saved_database_identity(state, identity); live_checks(candidate, prior_snapshot)
            preserve_state(state, "DEPLOYED", reconciliation="REAPPLIED_DEPLOYED_CODE", last_rollback_result="FAILED_REAPPLIED")
            return "ROLLBACK_FAILED_REAPPLIED_STATE_DEPLOYED"
        except Exception:
            state["status"] = "ROLLBACK_FAILED_RECOVERY_REQUIRED"; state["reconciliation"] = "RECOVERY_REQUIRED"; atomic_json(STATE_FILE, state); raise
    fail("STATE_NOT_PENDING")


def execute_apply_mutation(candidate: Candidate, snapshot: dict[str, object], identity: str, fields: dict[str, object],
                           archive: pathlib.Path, archive_sha: str, prestate: pathlib.Path, prestate_sha: str) -> None:
    try:
        apply_candidate(candidate, snapshot)
        if database_identity(candidate) != identity:
            fail("DATABASE_IDENTITY_CHANGED_BEFORE_SCHEMA_APPLY")
        save_state("PENDING_APPLY_SCHEMA", **fields); code, output, diagnostic = schema_command(candidate, "apply")
        if code != 0 or diagnostic or output != "M074_SCHEMA_APPLY=PASS TABLES=3 RIGHTS=4 RIGHTS_DEFAULT=DISABLED":
            fields["schema_failure_reason"] = diagnostic or "SCHEMA_RESPONSE_INVALID"
            fail("SCHEMA_APPLY_FAILED_" + str(fields["schema_failure_reason"]))
        live_checks(candidate, snapshot)
        if database_identity(candidate) != identity:
            fail("DATABASE_IDENTITY_CHANGED_AFTER_SCHEMA_APPLY")
        save_state("DEPLOYED", **fields, files=DEPLOY_RUNTIME_FILES, tables=3, rights=4, rights_default="DISABLED")
    except Exception:
        try:
            restore_snapshot(archive, archive_sha, prestate, prestate_sha, allowed_snapshot=release_snapshot(candidate))
            save_state("APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED", **fields, reconciliation="CODE_RESTORED_SCHEMA_RETAINED")
            print("M074_STAGING_AUTO_ROLLBACK=PASS CODE=RESTORED SCHEMA=RETAINED DATA=RETAINED", file=sys.stderr)
        except Exception:
            save_state("APPLY_FAILED_RECOVERY_REQUIRED", **fields, reconciliation="RECOVERY_REQUIRED")
            print("M074_STAGING_AUTO_ROLLBACK=FAIL RECOVERY_REQUIRED=YES", file=sys.stderr)
        raise


def command_apply() -> None:
    prepare_roots(); lock_fd = acquire_lock()
    try:
        import fcntl
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
        existing = None
        if STATE_FILE.exists() or STATE_FILE.is_symlink():
            existing = load_state()
            if str(existing["status"]).startswith("PENDING_"):
                if existing["status"] == "PENDING_APPLY_PREPARE":
                    result = reconcile_pending(existing)
                    print(f"M074_STAGING_RECONCILE=PASS RESULT={result} NEW_APPLY_STARTED=NO PRODUCTION_ACCESSED=NO"); return
                retained = common_candidate(retained=True)
                try: result = reconcile_pending(existing, release_snapshot(retained), retained)
                finally: retained.cleanup()
                print(f"M074_STAGING_RECONCILE=PASS RESULT={result} NEW_APPLY_STARTED=NO PRODUCTION_ACCESSED=NO"); return
            if existing["status"] in ("APPLY_FAILED_RECOVERY_REQUIRED", "ROLLBACK_FAILED_RECOVERY_REQUIRED"): fail("RECOVERY_REQUIRED")
        candidate = common_candidate()
        try:
            snapshot = runtime_snapshot(candidate); counts = compare_summary(candidate, snapshot, emit=False)
            if counts["layout_different"]: fail("COMPATIBILITY_LAYOUT_NOT_READY")
            require_expected_baseline(snapshot)
            schema = schema_status(candidate); identity = database_identity(candidate)
            minimum_created_at = None
            if existing is not None and existing["status"] != "DEPLOYED":
                minimum_created_at = dt.datetime.fromisoformat(str(existing.get("updated_at", "")))
                if minimum_created_at.tzinfo is None: fail("STATE_UPDATED_AT_INVALID")
            plan_sha = require_plan(snapshot, schema, identity, minimum_created_at)
            if STATE_FILE.exists() and load_state()["status"] == "DEPLOYED":
                state = load_state(); prior_snapshot = validate_code_backup(*state_backup(state))
                live_checks(candidate, prior_snapshot); require_saved_database_identity(state, identity); state_database_backup(state)
                print(f"M074_STAGING_APPLY=NOOP_ALREADY_DEPLOYED RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} PLAN_SHA={plan_sha} PRODUCTION_ACCESSED=NO"); return
            retain_release(candidate); request_id = make_request_id(); save_state("PENDING_APPLY_PREPARE", request_id=request_id, plan_sha256=plan_sha)
            archive, archive_sha, prestate, prestate_sha = write_backup(candidate, snapshot, "m074-r09-before-" + request_id)
            database, database_sha = create_database_backup(request_id, identity)
            validation_database = make_validation_database_name()
            fields = {"request_id": request_id, "plan_sha256": plan_sha, "code_backup": str(archive), "code_backup_sha256": archive_sha,
                      "prestate": str(prestate), "prestate_sha256": prestate_sha, "database_backup": str(database), "database_backup_sha256": database_sha,
                      "database_identity": identity, "validation_database": validation_database}
            save_state("PENDING_APPLY_PREPARE", **fields)
            validate_database_backup(database, database_sha, validation_database)
            fields["validation_database"] = ""
            save_state("PENDING_APPLY_PREPARE", **fields)
            if database_identity(candidate) != identity:
                fail("DATABASE_IDENTITY_CHANGED_AFTER_RESTORE_CHECK")
            if runtime_snapshot(candidate) != snapshot:
                fail("BASELINE_CHANGED_AFTER_BACKUP")
            save_state("PENDING_APPLY_CODE", **fields)
            execute_apply_mutation(candidate, snapshot, identity, fields, archive, archive_sha, prestate, prestate_sha)
            print(f"M074_STAGING_APPLY=PASS REQUEST_ID={request_id} RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} PLAN_SHA={plan_sha} CODE_BACKUP={archive} CODE_BACKUP_SHA={archive_sha} PRESTATE={prestate} PRESTATE_SHA={prestate_sha} DB_BACKUP={database} DB_BACKUP_SHA={database_sha} FILES={DEPLOY_RUNTIME_FILES} TABLES=3 RIGHTS=4 RIGHTS_DEFAULT=DISABLED DATABASE_CHANGED=YES PRODUCTION_ACCESSED=NO")
        finally: candidate.cleanup()
    finally: os.close(lock_fd)


def command_verify() -> None:
    prepare_roots(); check_environment(); state = load_state()
    if state["status"] != "DEPLOYED": fail("STATE_NOT_DEPLOYED")
    prior_snapshot = validate_code_backup(*state_backup(state)); state_database_backup(state); candidate = validate_release(ARTIFACT_ROOT)
    try:
        candidate_checks(candidate); identity = database_identity(candidate); require_saved_database_identity(state, identity); live_checks(candidate, prior_snapshot)
        print(f"M074_STAGING_VERIFY=PASS STATE=DEPLOYED RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} REQUEST_ID={state['request_id']} FILES={DEPLOY_RUNTIME_FILES} TABLES=3 RIGHTS=4 RIGHTS_DEFAULT=DISABLED CODE_BACKUP_SHA={state['code_backup_sha256']} DB_BACKUP_SHA={state['database_backup_sha256']} BACKUPS_REVALIDATED=YES DATABASE_MUTATED_BY_COMMAND=NO PRODUCTION_ACCESSED=NO")
    finally: candidate.cleanup()


def command_rollback() -> None:
    prepare_roots(); lock_fd = acquire_lock()
    try:
        import fcntl
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB); state = load_state()
        if str(state["status"]).startswith("PENDING_"):
            if state["status"] == "PENDING_APPLY_PREPARE":
                result = reconcile_pending(state)
                print(f"M074_STAGING_RECONCILE=PASS RESULT={result} NEW_ROLLBACK_STARTED=NO PRODUCTION_ACCESSED=NO"); return
            check_environment()
            retained = common_candidate(retained=True)
            try: result = reconcile_pending(state, release_snapshot(retained), retained)
            finally: retained.cleanup()
            print(f"M074_STAGING_RECONCILE=PASS RESULT={result} NEW_ROLLBACK_STARTED=NO PRODUCTION_ACCESSED=NO"); return
        check_environment()
        if state["status"] != "DEPLOYED": fail("STATE_NOT_DEPLOYED")
        candidate = validate_release(ARTIFACT_ROOT)
        try:
            candidate_checks(candidate); identity = database_identity(candidate); require_saved_database_identity(state, identity); state_database_backup(state); prior = state_backup(state); prior_snapshot = validate_code_backup(*prior); live_checks(candidate, prior_snapshot); deployed = release_snapshot(candidate); rollback_id = make_request_id()
            state["status"] = "PENDING_ROLLBACK_PREPARE"; state["rollback_request_id"] = rollback_id; atomic_json(STATE_FILE, state)
            reapply = write_backup(candidate, runtime_snapshot(candidate), "m074-r09-reapply-" + rollback_id)
            state.update({"status": "PENDING_ROLLBACK", "reapply_code_backup": str(reapply[0]), "reapply_code_backup_sha256": reapply[1], "reapply_prestate": str(reapply[2]), "reapply_prestate_sha256": reapply[3]}); atomic_json(STATE_FILE, state)
            try:
                restore_snapshot(*prior, allowed_snapshot=deployed)
                rollback_checks(candidate, prior_snapshot)
                state["status"] = "ROLLED_BACK_SCHEMA_RETAINED"; state["updated_at"] = dt.datetime.now(dt.timezone.utc).isoformat(); atomic_json(STATE_FILE, state)
            except Exception:
                try:
                    restore_snapshot(*reapply, allowed_snapshot=prior_snapshot); live_checks(candidate, prior_snapshot); require_saved_database_identity(state, database_identity(candidate)); state["status"] = "DEPLOYED"; state["last_rollback_result"] = "FAILED_REAPPLIED"; state["updated_at"] = dt.datetime.now(dt.timezone.utc).isoformat(); atomic_json(STATE_FILE, state); print("M074_STAGING_ROLLBACK_REAPPLY=PASS STATE=DEPLOYED", file=sys.stderr)
                except Exception:
                    state["status"] = "ROLLBACK_FAILED_RECOVERY_REQUIRED"; state["updated_at"] = dt.datetime.now(dt.timezone.utc).isoformat(); atomic_json(STATE_FILE, state); print("M074_STAGING_ROLLBACK_REAPPLY=FAIL RECOVERY_REQUIRED=YES", file=sys.stderr)
                raise
            print(f"M074_STAGING_ROLLBACK=PASS RELEASE={RELEASE} ROLLBACK_REQUEST_ID={rollback_id} CODE=PRESTATE_RESTORED SCHEMA=RETAINED DATA=RETAINED REAPPLY_BACKUP={reapply[0]} REAPPLY_BACKUP_SHA={reapply[1]} DATABASE_MUTATED_BY_COMMAND=NO PRODUCTION_ACCESSED=NO")
        finally: candidate.cleanup()
    finally: os.close(lock_fd)


def command_status() -> None:
    prepare_roots()
    plan, plan_created = status_plan()
    if not STATE_FILE.exists() and not STATE_FILE.is_symlink():
        available, reason = apply_status(plan, plan_created, None)
        print(f"M074_STAGING_STATUS=NOT_DEPLOYED RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} PREFLIGHT_PLAN={plan} COMMANDS=compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE={available} APPLY_REASON={reason} DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO"); return
    state = load_state()
    available, reason = apply_status(plan, plan_created, state)
    print(f"M074_STAGING_STATUS={state['status']} RELEASE={RELEASE} SOURCE_COMMIT={SOURCE_COMMIT} ARTIFACT_SHA={ARTIFACT_SHA} REQUEST_ID={state.get('request_id','none')} CODE_BACKUP_SHA={state.get('code_backup_sha256','none')} DB_BACKUP_SHA={state.get('database_backup_sha256','none')} SCHEMA_FAILURE_REASON={state.get('schema_failure_reason','none')} PREFLIGHT_PLAN={plan} COMMANDS=compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE={available} APPLY_REASON={reason} PRODUCTION_ACCESSED=NO")


def main() -> int:
    os.umask(0o077)
    if os.geteuid() != 0: fail("ROOT_REQUIRED")
    if len(sys.argv) != 2 or sys.argv[1] not in ALLOWED_COMMANDS: fail("COMMAND_NOT_ALLOWED")
    if pathlib.Path(sys.argv[0]).name != "chinaplanetos-backup-erp-m074-qc-preflight": fail("HELPER_PATH_NOT_ALLOWED")
    globals()["command_" + sys.argv[1]](); return 0


if __name__ == "__main__":
    try: raise SystemExit(main())
    except ReleaseError as exc: print("M074_STAGING_FAIL=" + str(exc), file=sys.stderr); raise SystemExit(1)
    except (OSError, subprocess.SubprocessError, json.JSONDecodeError, ValueError, KeyError) as exc:
        print("M074_STAGING_FAIL=UNEXPECTED_" + exc.__class__.__name__.upper(), file=sys.stderr); raise SystemExit(1)
    except Exception as exc:
        print("M074_STAGING_FAIL=UNEXPECTED_" + exc.__class__.__name__.upper(), file=sys.stderr); raise SystemExit(1)
