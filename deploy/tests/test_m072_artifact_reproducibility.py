#!/usr/bin/env python3
"""Rebuild M072-R01 twice and verify the frozen local artifact byte-for-byte."""

from __future__ import annotations

import csv
import gzip
import hashlib
import io
import json
import os
import pathlib
import subprocess
import tarfile
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
BUILDER = ROOT / "deploy/tools/build_m072_release_artifacts.py"
FROZEN = ROOT / "deploy/releases/m072-r01"
COMPONENTS = ("dolibarr", "react", "wordpress", "mobile")
RESIDUE = {
    "custom/chinaplanetcustomercenter/admin/acceptance_demo.php",
    "custom/chinaplanetcustomercenter/admin/demo/seed_customer_portal_demo.php",
    "custom/chinaplanetcustomercenter/admin/demo/seed_full_order_cycles.php",
    "custom/chinaplanetcustomercenter/admin/demo/seed_operations_sops.php",
}
EXPECTED_LINKS = {
    "custom/chinaplanetcustomercenter/chinaplanetlogistics": "../chinaplanetlogistics",
    "custom/chinaplanetcustomercenter/cpkanban": "../cpkanban",
}


def digest(path: pathlib.Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class FrozenArtifactReproducibilityTest(unittest.TestCase):
    def setUp(self):
        if not (FROZEN / "release-bundle.json").is_file():
            self.skipTest("RELEASE_NOT_FROZEN")
        roots = {
            "dolibarr": os.environ.get("M072_DOLIBARR_ROOT"),
            "react": os.environ.get("M072_REACT_ROOT"),
            "wordpress": os.environ.get("M072_WORDPRESS_ROOT"),
            "mobile": os.environ.get("M072_DOLIBARR_ROOT"),
        }
        missing = [name for name, value in roots.items() if not value]
        if missing:
            self.fail("SOURCE_ROOTS_REQUIRED:" + ",".join(missing))
        self.roots = {name: pathlib.Path(value).resolve() for name, value in roots.items()}
        self.bundle = json.loads((FROZEN / "release-bundle.json").read_text(encoding="utf-8"))

    def _build(self, destination: pathlib.Path) -> None:
        commits = {item["id"]: item["commit"] for item in self.bundle["components"]}
        descriptor = {
            "schemaVersion": 1,
            "releaseId": self.bundle["releaseId"],
            "sources": {
                name: {"root": str(self.roots[name]), "commit": commits[name]}
                for name in COMPONENTS
            },
        }
        descriptor_path = destination.parent / (destination.name + "-sources.json")
        descriptor_path.write_text(json.dumps(descriptor, sort_keys=True), encoding="utf-8")
        subprocess.run(
            [str(BUILDER), "--sources", str(descriptor_path), "--output", str(destination)],
            check=True,
            capture_output=True,
            text=True,
        )

    def test_two_builds_match_each_other_and_frozen_release(self):
        with tempfile.TemporaryDirectory(prefix="m072-repro-") as raw:
            root = pathlib.Path(raw)
            first, second = root / "first", root / "second"
            self._build(first)
            self._build(second)
            expected_names = sorted(path.name for path in FROZEN.iterdir() if path.is_file())
            self.assertEqual(sorted(path.name for path in first.iterdir()), expected_names)
            self.assertEqual(sorted(path.name for path in second.iterdir()), expected_names)
            for name in expected_names:
                self.assertEqual(first.joinpath(name).read_bytes(), second.joinpath(name).read_bytes(), name)
                self.assertEqual(first.joinpath(name).read_bytes(), FROZEN.joinpath(name).read_bytes(), name)

    def test_pins_members_layout_and_non_runtime_support_are_exact(self):
        pins = {}
        for line in (FROZEN / "release-pins.sha256").read_text(encoding="ascii").splitlines():
            sha, name = line.split("  ", 1)
            pins[name] = sha
            self.assertEqual(sha, digest(FROZEN / name), name)
        self.assertEqual(set(pins), {path.name for path in FROZEN.iterdir() if path.is_file()} - {"release-pins.sha256"})

        for component in COMPONENTS:
            manifest_path = FROZEN / f"{component}.manifest.tsv"
            with manifest_path.open(encoding="utf-8", newline="") as handle:
                rows = list(csv.DictReader(handle, delimiter="\t"))
            rows_by_path = {row["source_path"]: row for row in rows}
            self.assertFalse(RESIDUE & set(rows_by_path), component)
            for row in rows:
                if row["classification"] in ("support", "evidence", "mobile-source"):
                    self.assertEqual(row["runtime_target"], "-", row["source_path"])

            with gzip.open(FROZEN / f"{component}.tar.gz", "rb") as compressed:
                archive_bytes = compressed.read()
            with tarfile.open(fileobj=io.BytesIO(archive_bytes), mode="r:") as archive:
                members = archive.getmembers()
                self.assertEqual([member.name for member in members], sorted(rows_by_path))
                for member in members:
                    row = rows_by_path[member.name]
                    self.assertTrue(member.isfile(), member.name)
                    self.assertEqual((member.uid, member.gid, member.mtime), (0, 0, 0), member.name)
                    self.assertEqual(member.mode, int(row["mode"], 8), member.name)
                    content = archive.extractfile(member).read()
                    self.assertEqual(len(content), int(row["size"]), member.name)
                    self.assertEqual(hashlib.sha256(content).hexdigest(), row["sha256"], member.name)

        with (FROZEN / "dolibarr.layout.tsv").open(encoding="utf-8", newline="") as handle:
            layout = list(csv.DictReader(handle, delimiter="\t"))
        self.assertEqual({row["runtime_path"]: row["link_target"] for row in layout}, EXPECTED_LINKS)
        ledger = (FROZEN / "historical-helper-deprecation-ledger.tsv").read_text(encoding="utf-8")
        self.assertIn("deploy/tools/recover_backup_erp_m062_database.sh", ledger)
        self.assertIn("deploy/tools/recover_production_m062_database.sh", ledger)
        self.assertIn(".github/workflows/mobile-store-release.yml", ledger)
        self.assertIn(".github/workflows/mobile-internal-update.yml", ledger)
        self.assertIn("react\t.github/workflows/deploy-staging.yml", ledger)
        self.assertIn("wordpress\t.github/workflows/release.yml", ledger)


if __name__ == "__main__":
    unittest.main()
