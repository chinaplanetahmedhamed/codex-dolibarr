#!/usr/bin/env python3
"""Build the deterministic, environment-bound M073 rebuild-stack bundle."""

from __future__ import annotations

import gzip
import hashlib
import io
import json
import pathlib
import shutil
import tarfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = ROOT / "deploy" / "m073"
M072 = ROOT / "deploy" / "releases" / "m072-r01"
OUTPUT = ROOT / "deploy" / "releases" / "m073-r01"

M072_PINS = {
    "dolibarr.tar.gz": "1e2ea0b7f8e1eefbe898fc52b1c61aaf33413dc9d65d40a391c9914d04e814f9",
    "react.tar.gz": "44c3bd853e681f12dbdc2579de48f018aa2a739a962e38bceb9389742d5a9c9a",
    "wordpress.tar.gz": "3a91e08a96562925881187ba5c3719188781e3edf74066284fec1301e1bfb332",
    "mobile.tar.gz": "315d3c5df193cfa5d4b655115c48a12131e4054342d791454840174161ad2964",
}
M072_COMMITS = {
    "dolibarr": "a6f6f88e2702686c6cd2e99544074804f644a837",
    "react": "01c9d872ccdf5664cf470019194156b5503dc74b",
    "wordpress": "ae273e2d15fa89111336ef74f746a86cc9ac57bd",
    "mobile": "a6f6f88e2702686c6cd2e99544074804f644a837",
}


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def fail(reason: str) -> None:
    raise SystemExit(f"M073_BUNDLE_BUILD=FAIL REASON={reason}")


def add_file(archive: tarfile.TarFile, name: str, data: bytes, mode: int) -> None:
    info = tarfile.TarInfo(name=name)
    info.size = len(data)
    info.mode = mode
    info.uid = 0
    info.gid = 0
    info.uname = "root"
    info.gname = "root"
    info.mtime = 0
    archive.addfile(info, io.BytesIO(data))


def build() -> None:
    if not SOURCE.is_dir() or not M072.is_dir():
        fail("SOURCE_MISSING")

    files: dict[str, tuple[bytes, int]] = {}
    for path in sorted(SOURCE.rglob("*")):
        if path.is_symlink() or not path.is_file():
            if path.is_symlink():
                fail("SOURCE_SYMLINK")
            continue
        relative = path.relative_to(SOURCE).as_posix()
        files[relative] = (path.read_bytes(), 0o444)

    for path in sorted(M072.iterdir()):
        if not path.is_file() or path.is_symlink():
            fail("M072_MEMBER_INVALID")
        files[f"m072/{path.name}"] = (path.read_bytes(), 0o444)

    for name, expected in M072_PINS.items():
        actual = digest(files[f"m072/{name}"][0])
        if actual != expected:
            fail(f"M072_PIN_MISMATCH_{name.upper().replace('.', '_')}")

    m072_descriptor = json.loads(files["m072/release-bundle.json"][0])
    if m072_descriptor.get("releaseId") != "M072-R01":
        fail("M072_RELEASE_ID_MISMATCH")
    actual_commits = {component.get("id"): component.get("commit") for component in m072_descriptor.get("components", [])}
    if actual_commits != M072_COMMITS:
        fail("M072_COMMIT_SET_MISMATCH")
    accepted_pin_lines = files["m072/release-pins.sha256"][0].decode("ascii").splitlines()
    accepted_pins = {line.split()[1]: line.split()[0] for line in accepted_pin_lines if line.strip()}
    for name, expected in M072_PINS.items():
        if accepted_pins.get(name) != expected:
            fail(f"M072_RELEASE_PIN_MISMATCH_{name.upper().replace('.', '_')}")

    manifest_lines = [
        f"{digest(data)}\t{len(data)}\t{mode:o}\t{name}"
        for name, (data, mode) in sorted(files.items())
    ]
    manifest = ("\n".join(manifest_lines) + "\n").encode()
    files["bundle-manifest.tsv"] = (manifest, 0o444)

    descriptor = {
        "componentRelease": "M072-R01",
        "environment": "backup-erp-rebuild",
        "externalWrites": False,
        "hostnameBinding": None,
        "m072ArtifactPins": M072_PINS,
        "m072Commits": M072_COMMITS,
        "productionData": "ABSENT",
        "releaseId": "M073-R01",
        "schemaVersion": 1,
    }
    files["bundle.json"] = ((json.dumps(descriptor, indent=2, sort_keys=True) + "\n").encode(), 0o444)

    OUTPUT.mkdir(parents=True, exist_ok=True)
    bundle = OUTPUT / "backup-erp-rebuild-m073-r01.tar.gz"
    raw = io.BytesIO()
    with tarfile.open(fileobj=raw, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for name, (data, mode) in sorted(files.items()):
            add_file(archive, name, data, mode)
    with bundle.open("wb") as target:
        with gzip.GzipFile(filename="", mode="wb", fileobj=target, mtime=0, compresslevel=9) as zipped:
            zipped.write(raw.getvalue())

    package_hash = digest(bundle.read_bytes())
    pins = OUTPUT / "release-pins.sha256"
    pins.write_text(f"{package_hash}  {bundle.name}\n", encoding="utf-8")
    print(f"M073_BUNDLE_BUILD=PASS RELEASE=M073-R01 SHA256={package_hash}")


if __name__ == "__main__":
    build()
