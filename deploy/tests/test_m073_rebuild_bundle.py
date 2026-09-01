#!/usr/bin/env python3
"""Local integrity tests for the M073 deterministic rebuild bundle."""

from __future__ import annotations

import hashlib
import json
import pathlib
import subprocess
import tarfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
RELEASE = ROOT / "deploy/releases/m073-r01"
BUNDLE = RELEASE / "backup-erp-rebuild-m073-r01.tar.gz"


def digest(path: pathlib.Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> None:
    expected = (RELEASE / "release-pins.sha256").read_text().split()[0]
    assert digest(BUNDLE) == expected
    with tarfile.open(BUNDLE, "r:gz") as archive:
        members = archive.getmembers()
        names = [member.name for member in members]
        assert len(names) == len(set(names))
        assert all(member.isfile() and not member.issym() and not member.islnk() for member in members)
        assert all(not pathlib.PurePosixPath(name).is_absolute() and ".." not in pathlib.PurePosixPath(name).parts for name in names)
        descriptor = json.load(archive.extractfile("bundle.json"))
        assert descriptor["releaseId"] == "M073-R01"
        assert descriptor["componentRelease"] == "M072-R01"
        assert descriptor["environment"] == "backup-erp-rebuild"
        assert descriptor["productionData"] == "ABSENT"
        assert descriptor["externalWrites"] is False
        assert descriptor["m072Commits"] == {
            "dolibarr": "a6f6f88e2702686c6cd2e99544074804f644a837",
            "mobile": "a6f6f88e2702686c6cd2e99544074804f644a837",
            "react": "01c9d872ccdf5664cf470019194156b5503dc74b",
            "wordpress": "ae273e2d15fa89111336ef74f746a86cc9ac57bd",
        }
        manifest = archive.extractfile("bundle-manifest.tsv").read().decode().splitlines()
        manifest_names = set()
        for line in manifest:
            expected_hash, raw_size, raw_mode, name = line.split("\t")
            extracted = archive.extractfile(name).read()
            assert hashlib.sha256(extracted).hexdigest() == expected_hash
            assert len(extracted) == int(raw_size)
            assert archive.getmember(name).mode == int(raw_mode, 8)
            manifest_names.add(name)
        assert manifest_names == set(names) - {"bundle.json", "bundle-manifest.tsv"}

    first = expected
    result = subprocess.run(["python3", str(ROOT / "deploy/tools/build_m073_rebuild_bundle.py")], check=True, capture_output=True, text=True)
    assert "M073_BUNDLE_BUILD=PASS" in result.stdout
    assert digest(BUNDLE) == first
    print("M073_REBUILD_BUNDLE_TEST=PASS")


if __name__ == "__main__":
    main()
