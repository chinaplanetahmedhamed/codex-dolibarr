#!/usr/bin/python3
"""Restricted controller for the isolated M073 backup-erp-rebuild stack."""

from __future__ import annotations

import fcntl
import hashlib
import json
import os
import pathlib
import pwd
import secrets
import shutil
import socket
import stat
import subprocess
import sys
import tarfile
import tempfile
import time
from typing import NoReturn


RELEASE_ID = "M073-R01"
COMPONENT_RELEASE = "M072-R01"
PROJECT = "backup-erp-rebuild"
SOURCE_BUNDLE = pathlib.Path("/home/codex-deploy/m073-rebuild-inbox/backup-erp-rebuild-m073-r01.tar.gz")
SOURCE_BUNDLE_SHA256 = "c0f42a9e159b8f4d1dc83b223a62205f7d598ef62f0b1b81662c55259709f840"
APP_ROOT = pathlib.Path("/opt/chinaplanetos/backup-erp-rebuild")
RELEASE_ROOT = APP_ROOT / "releases" / RELEASE_ID
ACTIVE_LINK = APP_ROOT / "current"
STATE_ROOT = pathlib.Path("/var/lib/chinaplanetos-backup-erp-rebuild")
LOCK_FILE = STATE_ROOT / "controller.lock"
LEDGER_FILE = STATE_ROOT / "release-ledger.json"
GLOBAL_LOCK = pathlib.Path("/run/lock/chinaplanetos-backup-erp-rebuild-m073.lock")
COMPOSE_FILE = RELEASE_ROOT / "compose.yaml"
SECRET_ROOT = RELEASE_ROOT / "secrets"
PORTS = (28025, 28088, 28089, 28180)

EXPECTED_CONTAINERS = {
    "backup-erp-rebuild-dolibarr-db",
    "backup-erp-rebuild-dolibarr",
    "backup-erp-rebuild-wordpress-db",
    "backup-erp-rebuild-wordpress",
    "backup-erp-rebuild-react",
    "backup-erp-rebuild-mailpit",
}
EXPECTED_VOLUMES = {
    "backup-erp-rebuild_dolibarr-db",
    "backup-erp-rebuild_dolibarr-documents",
    "backup-erp-rebuild_wordpress-db",
    "backup-erp-rebuild_wordpress-content",
    "backup-erp-rebuild_react-data",
    "backup-erp-rebuild_mailpit-data",
}
EXPECTED_NETWORK = "backup-erp-rebuild_internal"
EXPECTED_IMAGE_REFERENCES = {
    "backup-erp-rebuild-dolibarr-db": "mariadb:10.6@sha256:4ba945cc3998b4443d6c6937d9890bb4cf642229f3d95377369645c567a59b91",
    "backup-erp-rebuild-dolibarr": "chinaplanetos/backup-erp-rebuild-dolibarr:m072-r01",
    "backup-erp-rebuild-wordpress-db": "mariadb:10.6@sha256:4ba945cc3998b4443d6c6937d9890bb4cf642229f3d95377369645c567a59b91",
    "backup-erp-rebuild-wordpress": "chinaplanetos/backup-erp-rebuild-wordpress:m072-r01",
    "backup-erp-rebuild-react": "chinaplanetos/backup-erp-rebuild-react:m072-r01",
    "backup-erp-rebuild-mailpit": "axllent/mailpit:v1.21.8@sha256:b68fac5c01d90c31cd2dcabed60bec3ca0c9680dadac87c527c6a43c85e5b814",
}
EXPECTED_CUSTOM_IMAGE_TAGS = {
    reference for reference in EXPECTED_IMAGE_REFERENCES.values()
    if reference.startswith("chinaplanetos/backup-erp-rebuild-")
}
EXPECTED_PORT_BINDINGS = {
    "backup-erp-rebuild-dolibarr-db": set(),
    "backup-erp-rebuild-dolibarr": {("80/tcp", "127.0.0.1", "28088")},
    "backup-erp-rebuild-wordpress-db": set(),
    "backup-erp-rebuild-wordpress": {("80/tcp", "127.0.0.1", "28089")},
    "backup-erp-rebuild-react": {("8080/tcp", "127.0.0.1", "28180")},
    "backup-erp-rebuild-mailpit": {("8025/tcp", "127.0.0.1", "28025")},
}


def secret_mount(name: str) -> tuple[str, str, str, bool]:
    return ("bind", str(SECRET_ROOT / name), f"/run/secrets/{name}", False)


EXPECTED_MOUNTS = {
    "backup-erp-rebuild-dolibarr-db": {
        ("volume", "backup-erp-rebuild_dolibarr-db", "/var/lib/mysql", True),
        secret_mount("dolibarr_db_user"),
        secret_mount("dolibarr_db_password"),
        secret_mount("dolibarr_db_root_password"),
    },
    "backup-erp-rebuild-dolibarr": {
        ("volume", "backup-erp-rebuild_dolibarr-documents", "/var/www/documents", True),
        secret_mount("dolibarr_db_user"),
        secret_mount("dolibarr_db_password"),
        secret_mount("dolibarr_admin_login"),
        secret_mount("dolibarr_admin_password"),
        secret_mount("dolibarr_instance_id"),
    },
    "backup-erp-rebuild-wordpress-db": {
        ("volume", "backup-erp-rebuild_wordpress-db", "/var/lib/mysql", True),
        secret_mount("wordpress_db_user"),
        secret_mount("wordpress_db_password"),
        secret_mount("wordpress_db_root_password"),
    },
    "backup-erp-rebuild-wordpress": {
        ("volume", "backup-erp-rebuild_wordpress-content", "/var/www/html/wp-content", True),
        secret_mount("wordpress_db_user"),
        secret_mount("wordpress_db_password"),
    },
    "backup-erp-rebuild-react": {
        ("volume", "backup-erp-rebuild_react-data", "/app/data", True),
    },
    "backup-erp-rebuild-mailpit": {
        ("volume", "backup-erp-rebuild_mailpit-data", "/data", True),
    },
}
EXPECTED_SECRET_LENGTHS = {
    "dolibarr_db_user": 20,
    "dolibarr_db_password": 64,
    "dolibarr_db_root_password": 64,
    "dolibarr_admin_login": 20,
    "dolibarr_admin_password": 64,
    "dolibarr_instance_id": 64,
    "wordpress_db_user": 20,
    "wordpress_db_password": 64,
    "wordpress_db_root_password": 64,
}
FIXED_SECRET_VALUES = {
    "dolibarr_db_user": "cpo_rebuild_dolibarr",
    "dolibarr_admin_login": "cpo_rebuild_admin___",
    "wordpress_db_user": "cpo_rebuild_wordpres",
}


class Failure(Exception):
    def __init__(self, reason: str, recovery: str):
        super().__init__(reason)
        self.reason = reason
        self.recovery = recovery


def sha256(path: pathlib.Path) -> str:
    hasher = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            hasher.update(chunk)
    return hasher.hexdigest()


def fail(reason: str, recovery: str) -> NoReturn:
    raise Failure(reason, recovery)


def output(ok: bool, **fields: object) -> None:
    print(json.dumps({"ok": ok, "release": RELEASE_ID, **fields}, sort_keys=True, separators=(",", ":")))


def run(arguments: list[str], *, check: bool = True, capture: bool = True) -> subprocess.CompletedProcess[str]:
    completed = subprocess.run(
        arguments,
        check=False,
        capture_output=capture,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin", "LANG": "C.UTF-8"},
    )
    if check and completed.returncode != 0:
        fail("COMMAND_FAILED", f"inspect {arguments[0]} for the isolated {PROJECT} target")
    return completed


def require_root() -> None:
    if os.geteuid() != 0:
        fail("ROOT_REQUIRED", "run only through the installed restricted sudo command")


def safe_stat(path: pathlib.Path, mode: int, uid: int, gid: int) -> None:
    try:
        info = path.lstat()
    except FileNotFoundError:
        fail("SOURCE_MISSING", f"restore the checksum-pinned {path.name} to the fixed inbox")
    if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
        fail("SOURCE_TYPE_INVALID", "replace the source with a regular non-link file")
    if stat.S_IMODE(info.st_mode) != mode or info.st_uid != uid or info.st_gid != gid:
        fail("SOURCE_METADATA_INVALID", "restore fixed owner, group, and mode before retrying")


def validate_source_bundle() -> None:
    account = pwd.getpwnam("codex-deploy")
    parent = SOURCE_BUNDLE.parent
    parent_info = parent.lstat()
    if not stat.S_ISDIR(parent_info.st_mode) or stat.S_ISLNK(parent_info.st_mode):
        fail("SOURCE_ROOT_INVALID", "restore the fixed non-link M073 inbox")
    if parent_info.st_uid != account.pw_uid or parent_info.st_gid != account.pw_gid or stat.S_IMODE(parent_info.st_mode) != 0o700:
        fail("SOURCE_ROOT_METADATA_INVALID", "set the fixed inbox owner and mode to codex-deploy 0700")
    safe_stat(SOURCE_BUNDLE, 0o600, account.pw_uid, account.pw_gid)
    if SOURCE_BUNDLE.stat().st_size > 8 * 1024 * 1024:
        fail("BUNDLE_SIZE_INVALID", "re-upload the exact bounded M073 bundle")
    if sha256(SOURCE_BUNDLE) != SOURCE_BUNDLE_SHA256:
        fail("BUNDLE_CHECKSUM_MISMATCH", "re-upload the exact frozen M073 bundle")


def validate_member_name(name: str) -> pathlib.PurePosixPath:
    path = pathlib.PurePosixPath(name)
    if path.is_absolute() or not path.parts or any(part in ("", ".", "..") for part in path.parts):
        fail("BUNDLE_PATH_INVALID", "rebuild the M073 bundle from the frozen local commit")
    return path


def read_bundle_members() -> dict[str, tuple[str, int, int]]:
    with tarfile.open(SOURCE_BUNDLE, "r:gz") as archive:
        members: dict[str, tuple[str, int, int]] = {}
        raw_members = archive.getmembers()
        if len(raw_members) > 64:
            fail("BUNDLE_MEMBER_COUNT_INVALID", "rebuild the bounded M073 bundle")
        for member in raw_members:
            validate_member_name(member.name)
            if member.name in members:
                fail("BUNDLE_DUPLICATE_MEMBER", "rebuild the deterministic M073 bundle")
            if not member.isfile() or member.issym() or member.islnk():
                fail("BUNDLE_MEMBER_TYPE_INVALID", "rebuild without links or special files")
            if member.uid != 0 or member.gid != 0 or member.mode != 0o444 or member.size > 8 * 1024 * 1024:
                fail("BUNDLE_MEMBER_METADATA_INVALID", "rebuild the canonical metadata-free M073 bundle")
            stream = archive.extractfile(member)
            if stream is None:
                fail("BUNDLE_MEMBER_READ_FAILED", "rebuild the M073 bundle")
            data = stream.read()
            members[member.name] = (hashlib.sha256(data).hexdigest(), len(data), member.mode)
    required = {
        "bundle.json", "bundle-manifest.tsv", "compose.yaml", "target-profile.json",
        "images/Dockerfile.dolibarr", "images/Dockerfile.react", "images/Dockerfile.wordpress",
        "m072/release-bundle.json", "m072/release-pins.sha256", "m072/dolibarr.tar.gz",
        "m072/react.tar.gz", "m072/wordpress.tar.gz", "m072/mobile.tar.gz",
    }
    if not required.issubset(members):
        fail("BUNDLE_INCOMPLETE", "rebuild the complete M073 bundle")
    return members


def verify_extracted(root: pathlib.Path) -> None:
    manifest = root / "bundle-manifest.tsv"
    descriptor = json.loads((root / "bundle.json").read_text(encoding="utf-8"))
    if descriptor.get("releaseId") != RELEASE_ID or descriptor.get("componentRelease") != COMPONENT_RELEASE:
        fail("BUNDLE_IDENTITY_INVALID", "use the exact M073-R01 bundle")
    if descriptor.get("environment") != PROJECT or descriptor.get("productionData") != "ABSENT":
        fail("BUNDLE_TARGET_INVALID", "rebuild with the isolated target and absent Production data")
    seen: set[str] = set()
    for line in manifest.read_text(encoding="utf-8").splitlines():
        parts = line.split("\t")
        if len(parts) != 4:
            fail("BUNDLE_MANIFEST_INVALID", "rebuild the frozen bundle")
        expected_hash, raw_size, raw_mode, name = parts
        validate_member_name(name)
        path = root / name
        info = path.lstat()
        if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
            fail("EXTRACTED_MEMBER_INVALID", "remove the candidate and rebuild it")
        if info.st_size != int(raw_size) or stat.S_IMODE(info.st_mode) != int(raw_mode, 8) or sha256(path) != expected_hash:
            fail("EXTRACTED_MEMBER_MISMATCH", f"rebuild the frozen member {name}")
        seen.add(name)
    actual = {
        path.relative_to(root).as_posix()
        for path in root.rglob("*")
        if path.is_file() and path.name not in {"bundle-manifest.tsv", "bundle.json"}
    }
    if seen != actual:
        fail("EXTRACTED_MEMBER_SET_MISMATCH", "rebuild the complete M073 bundle")


def verify_pinned_release_files() -> None:
    if not ACTIVE_LINK.is_symlink() or ACTIVE_LINK.resolve() != RELEASE_ROOT:
        fail("ACTIVE_RELEASE_LINK_INVALID", "stop and restore only the exact M073 release link")
    manifest = RELEASE_ROOT / "bundle-manifest.tsv"
    if not manifest.is_file() or manifest.is_symlink():
        fail("ACTIVE_MANIFEST_INVALID", "restore the exact checksum-pinned M073 release")
    for line in manifest.read_text(encoding="utf-8").splitlines():
        parts = line.split("\t")
        if len(parts) != 4:
            fail("ACTIVE_MANIFEST_INVALID", "restore the exact checksum-pinned M073 release")
        expected_hash, raw_size, raw_mode, name = parts
        validate_member_name(name)
        path = RELEASE_ROOT / name
        try:
            info = path.lstat()
        except FileNotFoundError:
            fail("ACTIVE_RELEASE_MEMBER_MISSING", f"restore the exact M073 member {name}")
        if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode):
            fail("ACTIVE_RELEASE_MEMBER_INVALID", f"restore the exact M073 member {name}")
        if info.st_size != int(raw_size) or stat.S_IMODE(info.st_mode) != int(raw_mode, 8) or sha256(path) != expected_hash:
            fail("ACTIVE_RELEASE_MEMBER_MISMATCH", f"restore the exact M073 member {name}")


def port_available(port: int) -> bool:
    probe = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
        probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 0)
        probe.bind(("127.0.0.1", port))
        return True
    except OSError:
        return False
    finally:
        probe.close()


def docker_names(kind: str) -> set[str]:
    result = run(["docker", kind, "ls", "--filter", f"label=com.docker.compose.project={PROJECT}", "--format", "{{.Name}}"])
    return {line for line in result.stdout.splitlines() if line}


def container_names() -> set[str]:
    result = run(["docker", "ps", "-a", "--filter", f"label=com.docker.compose.project={PROJECT}", "--format", "{{.Names}}"])
    return {line for line in result.stdout.splitlines() if line}


def all_docker_names(kind: str) -> set[str]:
    if kind == "container":
        arguments = ["docker", "ps", "-a", "--format", "{{.Names}}"]
    elif kind in {"network", "volume"}:
        arguments = ["docker", kind, "ls", "--format", "{{.Name}}"]
    elif kind == "image":
        arguments = ["docker", "image", "ls", "--format", "{{.Repository}}:{{.Tag}}"]
    else:
        fail("DOCKER_OBJECT_KIND_INVALID", "correct the frozen helper source")
    result = run(arguments)
    return {line for line in result.stdout.splitlines() if line}


def target_collision_present() -> bool:
    exact_collision = bool(
        (all_docker_names("container") & EXPECTED_CONTAINERS)
        or (all_docker_names("network") & {EXPECTED_NETWORK})
        or (all_docker_names("volume") & EXPECTED_VOLUMES)
        or (all_docker_names("image") & EXPECTED_CUSTOM_IMAGE_TAGS)
    )
    labeled_collision = bool(container_names() or docker_names("network") or docker_names("volume"))
    return exact_collision or labeled_collision


def preflight(*, creating: bool) -> dict[str, object]:
    require_root()
    validate_source_bundle()
    read_bundle_members()
    for command in ("docker", "openssl", "python3"):
        if shutil.which(command) is None:
            fail("PREREQUISITE_MISSING", f"install the approved prerequisite {command}")
    compose_version = run(["docker", "compose", "version"], check=False)
    if compose_version.returncode != 0:
        fail("COMPOSE_MISSING", "install Docker Compose v2 before retrying")
    if os.uname().machine not in {"x86_64", "amd64"}:
        fail("ARCHITECTURE_INVALID", "use the reviewed linux/amd64 target")
    available_kib = 0
    for line in pathlib.Path("/proc/meminfo").read_text(encoding="ascii").splitlines():
        if line.startswith("MemAvailable:"):
            available_kib = int(line.split()[1])
    if available_kib < 3_000_000:
        fail("MEMORY_INSUFFICIENT", "free at least 3 GB before creating the isolated stack")
    if creating and (target_collision_present() or APP_ROOT.exists() or STATE_ROOT.exists()):
        fail("TARGET_NOT_EMPTY", "inspect the isolated M073 target; do not overwrite or delete retained volumes")
    if creating:
        busy = [port for port in PORTS if not port_available(port)]
        if busy:
            fail("LOCAL_PORT_UNAVAILABLE", "free only the approved M073 localhost port or choose a reviewed successor package")
    return {
        "architecture": os.uname().machine,
        "bundleSha256": SOURCE_BUNDLE_SHA256,
        "componentRelease": COMPONENT_RELEASE,
        "externalRuntimeNetwork": False,
        "productionData": "ABSENT",
    }


def fsync_path(path: pathlib.Path) -> None:
    descriptor = os.open(path, os.O_RDONLY | (getattr(os, "O_DIRECTORY", 0) if path.is_dir() else 0))
    try:
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def atomic_json(path: pathlib.Path, value: dict[str, object]) -> None:
    path.parent.mkdir(parents=True, mode=0o700, exist_ok=True)
    temporary = path.with_name(f".{path.name}.{os.getpid()}.tmp")
    temporary.write_text(json.dumps(value, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    os.chmod(temporary, 0o600)
    fsync_path(temporary)
    os.replace(temporary, path)
    fsync_path(path.parent)


def extract_regular_tar(archive_path: pathlib.Path, destination: pathlib.Path) -> None:
    destination.mkdir(parents=True, mode=0o755)
    with tarfile.open(archive_path, "r:gz") as archive:
        for member in archive.getmembers():
            relative = validate_member_name(member.name)
            if not member.isfile() or member.issym() or member.islnk():
                fail("SOURCE_ARTIFACT_TYPE_INVALID", "rebuild the exact M072 source artifact")
            target = destination.joinpath(*relative.parts)
            target.parent.mkdir(parents=True, exist_ok=True)
            stream = archive.extractfile(member)
            if stream is None:
                fail("SOURCE_ARTIFACT_READ_FAILED", "rebuild the exact M072 source artifact")
            with target.open("wb") as output_file:
                shutil.copyfileobj(stream, output_file)
            os.chmod(target, member.mode & 0o777)


def generate_secrets() -> None:
    SECRET_ROOT.mkdir(mode=0o700)
    for name, length in EXPECTED_SECRET_LENGTHS.items():
        value = FIXED_SECRET_VALUES.get(name, secrets.token_hex(32))
        if len(value) != length:
            fail("SECRET_LENGTH_INTERNAL_INVALID", "correct the frozen M073 helper before installation")
        path = SECRET_ROOT / name
        descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o400)
        try:
            os.write(descriptor, value.encode("ascii"))
            os.fsync(descriptor)
        finally:
            os.close(descriptor)
    fsync_path(SECRET_ROOT)


def compose(*arguments: str, check: bool = True) -> subprocess.CompletedProcess[str]:
    return run(["docker", "compose", "--project-name", PROJECT, "--file", str(COMPOSE_FILE), *arguments], check=check)


def prepare_release() -> None:
    app_parent = APP_ROOT.parent
    app_grandparent = app_parent.parent
    grandparent_info = app_grandparent.lstat()
    if not stat.S_ISDIR(grandparent_info.st_mode) or stat.S_ISLNK(grandparent_info.st_mode) or grandparent_info.st_uid != 0 or grandparent_info.st_gid != 0:
        fail("INSTALL_PARENT_INVALID", "repair the root-owned /opt directory before retrying")
    if app_parent.exists():
        parent_info = app_parent.lstat()
        if not stat.S_ISDIR(parent_info.st_mode) or stat.S_ISLNK(parent_info.st_mode) or parent_info.st_uid != 0 or parent_info.st_gid != 0 or stat.S_IMODE(parent_info.st_mode) != 0o755:
            fail("INSTALL_ROOT_INVALID", "repair only the fixed /opt/chinaplanetos root")
    else:
        app_parent.mkdir(mode=0o755)
        os.chown(app_parent, 0, 0)
        fsync_path(app_grandparent)
    stage = pathlib.Path(tempfile.mkdtemp(prefix=".m073-r01.", dir=APP_ROOT.parent))
    try:
        with tarfile.open(SOURCE_BUNDLE, "r:gz") as archive:
            for member in archive.getmembers():
                relative = validate_member_name(member.name)
                if not member.isfile() or member.issym() or member.islnk():
                    fail("BUNDLE_MEMBER_TYPE_INVALID", "rebuild the exact bundle")
                target = stage.joinpath(*relative.parts)
                target.parent.mkdir(parents=True, exist_ok=True)
                stream = archive.extractfile(member)
                if stream is None:
                    fail("BUNDLE_MEMBER_READ_FAILED", "rebuild the exact bundle")
                with target.open("wb") as output_file:
                    shutil.copyfileobj(stream, output_file)
                os.chmod(target, member.mode & 0o777)
        verify_extracted(stage)
        context_root = stage / "contexts"
        extract_regular_tar(stage / "m072/dolibarr.tar.gz", context_root / "dolibarr")
        extract_regular_tar(stage / "m072/react.tar.gz", context_root / "react")
        extract_regular_tar(stage / "m072/wordpress.tar.gz", context_root / "wordpress")
        shutil.copy2(stage / "images/Dockerfile.dolibarr", context_root / "dolibarr/Dockerfile")
        shutil.copy2(stage / "images/Dockerfile.react", context_root / "react/Dockerfile.m073")
        shutil.copy2(stage / "images/Dockerfile.wordpress", context_root / "wordpress/Dockerfile")
        APP_ROOT.mkdir(mode=0o755)
        (APP_ROOT / "releases").mkdir(mode=0o755)
        os.replace(stage, RELEASE_ROOT)
        stage = pathlib.Path()
        generate_secrets()
        ACTIVE_LINK.symlink_to(RELEASE_ROOT)
        fsync_path(APP_ROOT)
    finally:
        if stage and stage.exists() and str(stage).startswith(str(APP_ROOT.parent / ".m073-r01.")):
            shutil.rmtree(stage)


def secret_presence() -> dict[str, int]:
    result: dict[str, int] = {}
    for name, expected in EXPECTED_SECRET_LENGTHS.items():
        path = SECRET_ROOT / name
        info = path.lstat()
        if not stat.S_ISREG(info.st_mode) or stat.S_ISLNK(info.st_mode) or info.st_uid != 0 or info.st_gid != 0 or stat.S_IMODE(info.st_mode) != 0o400:
            fail("SECRET_METADATA_INVALID", "stop the isolated stack and repair only the M073 secret metadata")
        length = info.st_size
        if length != expected:
            fail("SECRET_LENGTH_INVALID", "stop the isolated stack and regenerate a reviewed M073 target")
        result[name] = length
    return result


def normalized_ports(mapping: object) -> set[tuple[str, str, str]]:
    result: set[tuple[str, str, str]] = set()
    for container_port, values in (mapping or {}).items():
        if not values:
            result.add((str(container_port), "", ""))
            continue
        for value in values:
            result.add((str(container_port), str(value.get("HostIp", "")), str(value.get("HostPort", ""))))
    return result


def normalized_mounts(mounts: object) -> set[tuple[str, str, str, bool]]:
    result: set[tuple[str, str, str, bool]] = set()
    for mount in mounts or []:
        mount_type = str(mount.get("Type", ""))
        source = str(mount.get("Name", "")) if mount_type == "volume" else str(mount.get("Source", ""))
        result.add((mount_type, source, str(mount.get("Destination", "")), bool(mount.get("RW"))))
    return result


def inspect_isolation(*, wait_for_health: bool, expected_image_ids: dict[str, str] | None = None) -> dict[str, object]:
    deadline = time.monotonic() + (240 if wait_for_health else 0)
    details: dict[str, dict[str, object]] = {}
    while True:
        names = container_names()
        if names == EXPECTED_CONTAINERS:
            states: dict[str, str] = {}
            healthy = True
            for name in sorted(names):
                raw = run(["docker", "inspect", name]).stdout.strip()
                parsed = json.loads(raw)
                if not isinstance(parsed, list) or len(parsed) != 1:
                    fail("CONTAINER_INSPECT_INVALID", "rollback the isolated stack and inspect Docker integrity")
                details[name] = parsed[0]
                state = details[name].get("State") or {}
                status = state.get("Status", "unknown")
                health = state.get("Health", {}).get("Status", "none")
                states[name] = f"{status}:{health}"
                if status != "running" or health != "healthy":
                    healthy = False
            if healthy:
                break
        if not wait_for_health or time.monotonic() >= deadline:
            fail("CONTAINER_HEALTH_INVALID", "inspect only the isolated stack logs, then run rollback if recovery is required")
        time.sleep(5)

    network_raw = run(["docker", "network", "inspect", "--format", "{{json .}}", EXPECTED_NETWORK]).stdout.strip()
    network = json.loads(network_raw)
    if network.get("Internal") is not True or network.get("Name") != EXPECTED_NETWORK:
        fail("NETWORK_ISOLATION_INVALID", "rollback the isolated stack; do not connect it to an external network")
    network_members = {entry.get("Name") for entry in (network.get("Containers") or {}).values()}
    if network_members != EXPECTED_CONTAINERS:
        fail("NETWORK_MEMBER_SET_INVALID", "rollback; the internal M073 network must contain only its six reviewed containers")
    image_ids: dict[str, str] = {}
    for name in sorted(EXPECTED_CONTAINERS):
        detail = details[name]
        network_settings = detail.get("NetworkSettings") or {}
        attached = set((network_settings.get("Networks") or {}).keys())
        if attached != {EXPECTED_NETWORK}:
            fail("CONTAINER_NETWORK_INVALID", "rollback; each M073 container must use only the internal network")
        host_config = detail.get("HostConfig") or {}
        if host_config.get("NetworkMode") != EXPECTED_NETWORK:
            fail("CONTAINER_NETWORK_MODE_INVALID", "rollback; reject any non-M073 network mode")
        security_options = set(host_config.get("SecurityOpt") or [])
        if security_options not in ({"no-new-privileges:true"}, {"no-new-privileges"}):
            fail("CONTAINER_SECURITY_OPTIONS_INVALID", "rollback; restore the exact no-new-privileges setting")
        if host_config.get("Privileged") or host_config.get("CapAdd") or host_config.get("Devices"):
            fail("CONTAINER_PRIVILEGE_INVALID", "rollback; M073 containers must not add host privileges")
        if normalized_ports(host_config.get("PortBindings")) != EXPECTED_PORT_BINDINGS[name]:
            fail("PORT_BINDING_SET_INVALID", "rollback; restore the exact four reviewed localhost bindings")
        if normalized_mounts(detail.get("Mounts")) != EXPECTED_MOUNTS[name]:
            fail("CONTAINER_MOUNT_SET_INVALID", "rollback; reject every unreviewed volume, bind path, or mount mode")
        config = detail.get("Config") or {}
        image_reference = str(config.get("Image", ""))
        if image_reference != EXPECTED_IMAGE_REFERENCES[name]:
            fail("CONTAINER_IMAGE_REFERENCE_INVALID", "rollback; restore the exact reviewed image reference")
        image_id = str(detail.get("Image", ""))
        tagged_image_id = run(["docker", "image", "inspect", "--format", "{{.Id}}", image_reference]).stdout.strip()
        if not image_id or image_id != tagged_image_id:
            fail("CONTAINER_IMAGE_ID_INVALID", "rollback; rebuild from the checksum-pinned M073 bundle")
        if expected_image_ids is not None and image_id != expected_image_ids.get(name):
            fail("CONTAINER_IMAGE_DRIFT", "rollback; the running image differs from the ACTIVE release ledger")
        image_ids[name] = image_id
    volumes = docker_names("volume")
    if volumes != EXPECTED_VOLUMES:
        fail("VOLUME_SET_INVALID", "inspect only the isolated M073 volumes and preserve them for evidence")
    return {
        "containers": states,
        "imageIds": image_ids,
        "networkInternal": True,
        "secretLengths": secret_presence(),
        "volumesRetained": sorted(volumes),
    }


def cleanup_failed_create() -> bool:
    if COMPOSE_FILE.is_file() and ACTIVE_LINK.is_symlink():
        try:
            verify_pinned_release_files()
        except Failure:
            return False
        compose("down", "--remove-orphans", check=False)
    return not (
        container_names()
        or docker_names("network")
        or (all_docker_names("container") & EXPECTED_CONTAINERS)
        or (all_docker_names("network") & {EXPECTED_NETWORK})
    )


def create() -> None:
    facts = preflight(creating=True)
    STATE_ROOT.mkdir(mode=0o700)
    atomic_json(LEDGER_FILE, {"release": RELEASE_ID, "state": "CREATING", "bundleSha256": SOURCE_BUNDLE_SHA256})
    try:
        prepare_release()
        verify_pinned_release_files()
        compose("config", "--quiet")
        compose("pull", "dolibarr-db", "wordpress-db", "mailpit")
        compose("build", "--pull", "dolibarr", "wordpress", "react")
        compose("up", "--detach", "--remove-orphans")
        verified = inspect_isolation(wait_for_health=True)
        ledger = {
            "release": RELEASE_ID,
            "componentRelease": COMPONENT_RELEASE,
            "state": "ACTIVE",
            "bundleSha256": SOURCE_BUNDLE_SHA256,
            "productionData": "ABSENT",
            "canonicalStagingTouched": False,
            "productionTouched": False,
            "networkInternal": True,
            "volumesRetainedOnRollback": True,
            "imageIds": verified["imageIds"],
        }
        atomic_json(LEDGER_FILE, ledger)
        output(True, action="create", state="ACTIVE", **facts, **verified)
    except Failure as original:
        cleanup_ok = cleanup_failed_create()
        if STATE_ROOT.exists():
            atomic_json(LEDGER_FILE, {
                "release": RELEASE_ID,
                "state": "FAILED",
                "bundleSha256": SOURCE_BUNDLE_SHA256,
                "cleanupComplete": cleanup_ok,
                "volumesRetained": True,
            })
        if not cleanup_ok:
            fail("CREATE_CLEANUP_FAILED", "preserve evidence and inspect only exact M073 resources before rollback")
        raise original
    except Exception:
        cleanup_ok = cleanup_failed_create()
        if STATE_ROOT.exists():
            atomic_json(LEDGER_FILE, {
                "release": RELEASE_ID,
                "state": "FAILED",
                "bundleSha256": SOURCE_BUNDLE_SHA256,
                "cleanupComplete": cleanup_ok,
                "volumesRetained": True,
            })
        if not cleanup_ok:
            fail("CREATE_CLEANUP_FAILED", "preserve evidence and inspect only exact M073 resources before rollback")
        fail("CREATE_INTERNAL_FAILURE", "review the isolated M073 ledger and retry only with a successor release")


def verify() -> None:
    if not LEDGER_FILE.is_file():
        fail("LEDGER_MISSING", "run preflight; create requires separate approved execution")
    ledger = json.loads(LEDGER_FILE.read_text(encoding="utf-8"))
    if ledger.get("state") != "ACTIVE" or ledger.get("bundleSha256") != SOURCE_BUNDLE_SHA256:
        fail("LEDGER_STATE_INVALID", "inspect the M073 ledger and do not infer acceptance")
    image_ids = ledger.get("imageIds")
    if not isinstance(image_ids, dict) or set(image_ids) != EXPECTED_CONTAINERS:
        fail("LEDGER_IMAGE_SET_INVALID", "do not trust a release without all six recorded image IDs")
    verify_pinned_release_files()
    verified = inspect_isolation(wait_for_health=False, expected_image_ids=image_ids)
    output(True, action="verify", state="ACTIVE", productionData="ABSENT", **verified)


def status() -> None:
    if not LEDGER_FILE.is_file():
        if target_collision_present() or APP_ROOT.exists() or STATE_ROOT.exists():
            fail("LEDGERLESS_TARGET_PRESENT", "preserve the exact M073 resources and reconstruct the missing root-owned ledger")
        output(True, action="status", state="ABSENT", productionData="ABSENT")
        return
    ledger = json.loads(LEDGER_FILE.read_text(encoding="utf-8"))
    if ledger.get("state") == "ACTIVE":
        image_ids = ledger.get("imageIds")
        if not isinstance(image_ids, dict) or set(image_ids) != EXPECTED_CONTAINERS:
            fail("LEDGER_IMAGE_SET_INVALID", "do not trust a release without all six recorded image IDs")
        verify_pinned_release_files()
        inspected = inspect_isolation(wait_for_health=False, expected_image_ids=image_ids)
        output(True, action="status", state="ACTIVE", productionData="ABSENT", **inspected)
        return
    output(True, action="status", state=ledger.get("state", "UNKNOWN"), productionData=ledger.get("productionData", "ABSENT"), volumesRetained=ledger.get("volumesRetainedOnRollback", True))


def rollback() -> None:
    if not LEDGER_FILE.is_file():
        fail("LEDGER_MISSING", "nothing is authorized for removal")
    ledger = json.loads(LEDGER_FILE.read_text(encoding="utf-8"))
    if ledger.get("release") != RELEASE_ID:
        fail("LEDGER_RELEASE_INVALID", "stop and review the target ownership")
    previous_state = ledger.get("state")
    if previous_state == "ROLLED_BACK":
        exact_containers = all_docker_names("container") & EXPECTED_CONTAINERS
        exact_networks = all_docker_names("network") & {EXPECTED_NETWORK}
        if container_names() or docker_names("network") or exact_containers or exact_networks:
            fail("ROLLBACK_REMNANT_PRESENT", "reject the repeated rollback until exact M073 container/network remnants are resolved")
        output(True, action="rollback", state="ROLLED_BACK", volumesRetained=sorted(docker_names("volume")))
        return
    if COMPOSE_FILE.is_file() and ACTIVE_LINK.is_symlink():
        verify_pinned_release_files()
        compose("down", "--remove-orphans")
    elif container_names() or docker_names("network"):
        fail("ROLLBACK_SOURCE_UNAVAILABLE", "preserve evidence; no unpinned Compose teardown is allowed")
    exact_containers = all_docker_names("container") & EXPECTED_CONTAINERS
    exact_networks = all_docker_names("network") & {EXPECTED_NETWORK}
    if container_names() or docker_names("network") or exact_containers or exact_networks:
        fail("ROLLBACK_INCOMPLETE", "inspect only the isolated M073 resources; volumes remain retained")
    retained = docker_names("volume")
    if not retained.issubset(EXPECTED_VOLUMES) or (previous_state == "ACTIVE" and retained != EXPECTED_VOLUMES):
        fail("ROLLBACK_VOLUME_SET_INVALID", "preserve evidence and review the isolated volume set")
    atomic_json(LEDGER_FILE, {**ledger, "state": "ROLLED_BACK", "volumesRetained": True})
    output(True, action="rollback", state="ROLLED_BACK", volumesRetained=sorted(retained))


def main() -> int:
    if len(sys.argv) != 2 or sys.argv[1] not in {"preflight", "create", "verify", "status", "rollback"}:
        fail("ACTION_INVALID", "use exactly preflight, create, verify, status, or rollback")
    require_root()
    state_parent_info = STATE_ROOT.parent.lstat()
    if not stat.S_ISDIR(state_parent_info.st_mode) or stat.S_ISLNK(state_parent_info.st_mode) or state_parent_info.st_uid != 0 or state_parent_info.st_gid != 0:
        fail("STATE_PARENT_INVALID", "repair the root-owned /var/lib directory")
    lock_parent_info = GLOBAL_LOCK.parent.lstat()
    if not stat.S_ISDIR(lock_parent_info.st_mode) or stat.S_ISLNK(lock_parent_info.st_mode) or lock_parent_info.st_uid != 0 or lock_parent_info.st_gid != 0:
        fail("LOCK_PARENT_INVALID", "repair the root-owned /run/lock directory")
    descriptor = os.open(GLOBAL_LOCK, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    lock_info = os.fstat(descriptor)
    if not stat.S_ISREG(lock_info.st_mode) or lock_info.st_uid != 0 or lock_info.st_gid != 0 or stat.S_IMODE(lock_info.st_mode) != 0o600:
        os.close(descriptor)
        fail("LOCK_FILE_INVALID", "repair only the fixed M073 lock file")
    try:
        fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
        action = sys.argv[1]
        if action == "preflight":
            facts = preflight(creating=True)
            output(True, action=action, state="READY", **facts)
        elif action == "create":
            create()
        elif action == "verify":
            verify()
        elif action == "status":
            status()
        else:
            rollback()
        return 0
    except BlockingIOError:
        fail("LOCK_CONTENDED", "wait for the active M073 action to finish")
    finally:
        os.close(descriptor)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Failure as error:
        output(False, reason=error.reason, recovery=error.recovery)
        raise SystemExit(1)
