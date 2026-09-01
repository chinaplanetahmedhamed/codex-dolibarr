#!/usr/bin/env python3
"""Fail-closed unit checks that do not require Docker or root."""

from __future__ import annotations

import importlib.util
import io
import pathlib
import tarfile
import tempfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
HELPER_PATH = ROOT / "deploy/tools/chinaplanetos_backup_erp_rebuild_m073.py"


def load_helper():
    spec = importlib.util.spec_from_file_location("m073_helper", HELPER_PATH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def expect_failure(module, reason, callback):
    try:
        callback()
    except module.Failure as error:
        assert error.reason == reason, (error.reason, reason)
    else:
        raise AssertionError(f"expected {reason}")


def main() -> None:
    module = load_helper()
    expect_failure(module, "BUNDLE_PATH_INVALID", lambda: module.validate_member_name("../escape"))
    expect_failure(module, "BUNDLE_PATH_INVALID", lambda: module.validate_member_name("/absolute"))
    exact_ports = {("80/tcp", "127.0.0.1", "28088")}
    assert module.normalized_ports({"80/tcp": [{"HostIp": "127.0.0.1", "HostPort": "28088"}]}) == exact_ports
    assert module.normalized_ports({"80/tcp": [{"HostIp": "0.0.0.0", "HostPort": "28088"}]}) != exact_ports
    exact_mount = {("volume", "backup-erp-rebuild_dolibarr-db", "/var/lib/mysql", True)}
    assert module.normalized_mounts([{
        "Type": "volume",
        "Name": "backup-erp-rebuild_dolibarr-db",
        "Destination": "/var/lib/mysql",
        "RW": True,
    }]) == exact_mount
    assert module.normalized_mounts([{
        "Type": "volume",
        "Name": "erp-staging_dolibarr-db",
        "Destination": "/var/lib/mysql",
        "RW": True,
    }]) != exact_mount

    inventory = {
        "container": set(),
        "network": set(),
        "volume": {"backup-erp-rebuild_dolibarr-db"},
        "image": set(),
    }
    module.all_docker_names = lambda kind: inventory[kind]
    module.container_names = lambda: set()
    module.docker_names = lambda kind: set()
    assert module.target_collision_present() is True
    inventory["volume"] = set()
    assert module.target_collision_present() is False

    with tempfile.TemporaryDirectory() as raw:
        root = pathlib.Path(raw)
        malicious = root / "malicious.tar.gz"
        with tarfile.open(malicious, "w:gz") as archive:
            first = tarfile.TarInfo("bundle.json")
            data = b"{}"
            first.size = len(data)
            first.mode = 0o444
            first.uid = first.gid = 0
            archive.addfile(first, io.BytesIO(data))
            second = tarfile.TarInfo("bundle.json")
            second.size = len(data)
            second.mode = 0o444
            second.uid = second.gid = 0
            archive.addfile(second, io.BytesIO(data))
        module.SOURCE_BUNDLE = malicious
        expect_failure(module, "BUNDLE_DUPLICATE_MEMBER", module.read_bundle_members)

    module.ACTIVE_LINK = pathlib.Path("/definitely/absent/m073/current")
    expect_failure(module, "ACTIVE_RELEASE_LINK_INVALID", module.verify_pinned_release_files)
    print("M073_REBUILD_FAILURE_TEST=PASS")


if __name__ == "__main__":
    main()
