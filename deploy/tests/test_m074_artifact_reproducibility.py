#!/usr/bin/env python3
"""Verify frozen M074-R10 artifacts and reproduce them byte-for-byte."""

from __future__ import annotations

import csv
import gzip
import hashlib
import json
import os
import pathlib
import subprocess
import tarfile
import tempfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
BUILDER = ROOT / "deploy/tools/build_m072_release_artifacts.py"
FROZEN = ROOT / "deploy/releases/m074-r11"
SOURCE_MANIFEST = ROOT / "docs/REPORTS/M074_SOURCE_TO_RUNTIME_MANIFEST.tsv"
COMPONENTS = ("dolibarr", "react", "wordpress", "mobile")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RuntimeError(message)


def digest(path: pathlib.Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


bundle = json.loads((FROZEN / "release-bundle.json").read_text(encoding="utf-8"))
require(bundle["releaseId"] == "M074-R11", "M074 release ID mismatch")
require(bundle["bindingStatus"] == "UNBOUND_REQUIRES_INDEPENDENT_REVIEW_AND_STAGING_APPROVAL", "M074 release is not gated")
require([item["id"] for item in bundle["components"]] == list(COMPONENTS), "M074 component set mismatch")

pin_rows = {}
for line in (FROZEN / "release-pins.sha256").read_text(encoding="ascii").splitlines():
    sha, name = line.split("  ", 1)
    require(name not in pin_rows, "duplicate release pin: " + name)
    pin_rows[name] = sha
    require(digest(FROZEN / name) == sha, "release pin mismatch: " + name)
require(set(pin_rows) == {path.name for path in FROZEN.iterdir() if path.is_file()} - {"release-pins.sha256"}, "release pin set mismatch")

with SOURCE_MANIFEST.open(encoding="utf-8", newline="") as handle:
    source_rows = list(csv.DictReader(handle, delimiter="\t"))
expected_runtime = {
    row["source_path"] for row in source_rows
    if row["repository"] == "dolibarr" and row["classification"] in ("dolibarr_runtime", "schema_baseline_source")
}
with (FROZEN / "dolibarr.manifest.tsv").open(encoding="utf-8", newline="") as handle:
    dolibarr_rows = list(csv.DictReader(handle, delimiter="\t"))
packaged_runtime = {row["source_path"] for row in dolibarr_rows if row["classification"] == "runtime"}
require(expected_runtime <= packaged_runtime, "M074 runtime source is absent from the frozen artifact")

for component in COMPONENTS:
    with (FROZEN / f"{component}.manifest.tsv").open(encoding="utf-8", newline="") as handle:
        manifest_rows = list(csv.DictReader(handle, delimiter="\t"))
    by_path = {row["source_path"]: row for row in manifest_rows}
    with gzip.open(FROZEN / f"{component}.tar.gz", "rb") as compressed:
        with tarfile.open(fileobj=compressed, mode="r:") as archive:
            members = archive.getmembers()
            require([member.name for member in members] == sorted(by_path), component + " archive member set mismatch")
            for member in members:
                row = by_path[member.name]
                require(member.isfile(), "unsupported archive entry: " + member.name)
                require((member.uid, member.gid, member.mtime) == (0, 0, 0), "non-deterministic archive metadata: " + member.name)
                content = archive.extractfile(member).read()
                require(len(content) == int(row["size"]), "archive size mismatch: " + member.name)
                require(hashlib.sha256(content).hexdigest() == row["sha256"], "archive hash mismatch: " + member.name)

roots = {
    "dolibarr": os.environ.get("M074_DOLIBARR_ROOT", str(ROOT)),
    "react": os.environ.get("M074_REACT_ROOT"),
    "wordpress": os.environ.get("M074_WORDPRESS_ROOT"),
    "mobile": os.environ.get("M074_DOLIBARR_ROOT", str(ROOT)),
}
require(all(roots.values()), "M074 source roots are required for reproducibility")
commits = {item["id"]: item["commit"] for item in bundle["components"]}
descriptor = {
    "schemaVersion": 1,
    "releaseId": "M074-R11",
    "sources": {name: {"root": roots[name], "commit": commits[name]} for name in COMPONENTS},
}
with tempfile.TemporaryDirectory(prefix="m074-repro-") as raw:
    temporary = pathlib.Path(raw)
    descriptor_path = temporary / "sources.json"
    descriptor_path.write_text(json.dumps(descriptor, sort_keys=True), encoding="utf-8")
    outputs = (temporary / "first", temporary / "second")
    for output in outputs:
        result = subprocess.run([str(BUILDER), "--sources", str(descriptor_path), "--output", str(output)], capture_output=True, text=True)
        require(result.returncode == 0, "rebuild failed: " + (result.stdout + result.stderr).strip())
    frozen_names = sorted(path.name for path in FROZEN.iterdir() if path.is_file())
    for output in outputs:
        require(sorted(path.name for path in output.iterdir()) == frozen_names, "rebuild file set mismatch")
        for name in frozen_names:
            require(output.joinpath(name).read_bytes() == FROZEN.joinpath(name).read_bytes(), "rebuild mismatch: " + name)

require(len(packaged_runtime) == 308, "M074-R11 composite runtime count mismatch")
print("M074_ARTIFACT_REPRODUCIBILITY=PASS RELEASE=M074-R11 RUNTIME_FILES=308 BUILDS=2")
