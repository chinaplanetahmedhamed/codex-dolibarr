#!/usr/bin/env python3
"""Exercise safe legacy-directory conversion and exact rollback for M074-R07."""

from __future__ import annotations

import importlib.machinery
import importlib.util
import csv
import pathlib
import tempfile
import types


ROOT = pathlib.Path(__file__).resolve().parents[2]
HELPER = ROOT / "deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh"
loader = importlib.machinery.SourceFileLoader("m074_layout_helper", str(HELPER))
spec = importlib.util.spec_from_loader(loader.name, loader)
module = importlib.util.module_from_spec(spec)
loader.exec_module(module)


def require(condition: bool, reason: str) -> None:
    if not condition:
        raise SystemExit("M074_COMPATIBILITY_LAYOUT=FAIL REASON=" + reason)


with (ROOT / "deploy/releases/m074-r07/dolibarr.manifest.tsv").open(newline="", encoding="utf-8") as handle:
    manifest_rows = list(csv.DictReader(handle, delimiter="\t"))
with (ROOT / "deploy/releases/m074-r07/dolibarr.layout.tsv").open(newline="", encoding="utf-8") as handle:
    deployment_layout = {row["runtime_path"]: row["link_target"] for row in csv.DictReader(handle, delimiter="\t")}
expanded = module.deployment_runtime_rows(manifest_rows, deployment_layout)
expanded_targets = [row["runtime_target"] for row in expanded]
overlay_targets = [path for path in expanded_targets if path.startswith("custom/chinaplanetcustomercenter/cpkanban/")
                   or path.startswith("custom/chinaplanetcustomercenter/chinaplanetlogistics/")]
require(len(expanded_targets) == 378 and len(set(expanded_targets)) == 378, "DEPLOYMENT_TARGET_SET_INVALID")
require(len(overlay_targets) == 70, "COMPATIBILITY_OVERLAY_COUNT_INVALID")


with tempfile.TemporaryDirectory(prefix="m074-layout-") as temporary:
    target = pathlib.Path(temporary) / "custom"
    canonical = target / "cpkanban"
    legacy = target / "chinaplanetcustomercenter/cpkanban"
    canonical.mkdir(parents=True)
    legacy.mkdir(parents=True)
    (canonical / "nested").mkdir()
    (legacy / "nested").mkdir()
    (canonical / "index.php").write_bytes(b"<?php echo 'same';\n")
    (legacy / "index.php").write_bytes(b"<?php echo 'same';\n")
    (canonical / "nested/value.txt").write_bytes(b"same\n")
    (legacy / "nested/value.txt").write_bytes(b"same\n")

    module.TARGET_ROOT = target
    candidate = types.SimpleNamespace(runtime_rows=[], layout={
        "custom/chinaplanetcustomercenter/cpkanban": "../cpkanban",
    })
    baseline_tree = module.directory_tree_snapshot("chinaplanetcustomercenter/cpkanban")
    module.EXPECTED_LAYOUT_TREE_SHA = {
        "custom/chinaplanetcustomercenter/cpkanban": module.canonical_sha(baseline_tree),
    }
    snapshot = module.runtime_snapshot(candidate)
    require(snapshot["layout"][0]["state"] == "OVERLAY_DIRECTORY", "PINNED_DIRECTORY_NOT_ACCEPTED")

    (legacy / "index.php").write_bytes(b"<?php echo 'different';\n")
    mismatch = module.runtime_snapshot(candidate)
    require(mismatch["layout"][0]["state"] == "DIFFERENT", "MISMATCH_NOT_REJECTED")

    # Overlay verification permits only the 70 declared deployment copies to change.
    (legacy / "index.php").write_bytes(b"old-overlay\n")
    (legacy / "extra.txt").write_bytes(b"preserve-me\n")
    baseline_tree = module.directory_tree_snapshot("chinaplanetcustomercenter/cpkanban")
    release_bytes = b"new-overlay\n"
    overlay_row = {"source_path": "custom/cpkanban/index.php",
                   "runtime_target": "custom/chinaplanetcustomercenter/cpkanban/index.php",
                   "classification": "runtime", "mode": "644", "size": str(len(release_bytes)),
                   "sha256": module.hashlib.sha256(release_bytes).hexdigest()}
    overlay_candidate = types.SimpleNamespace(runtime_rows=[overlay_row])
    overlay_item = {"path": "chinaplanetcustomercenter/cpkanban", "state": "OVERLAY_DIRECTORY",
                    "expected": "../cpkanban", "actual": "NON_SYMLINK_DIRECTORY", "tree": baseline_tree}
    (legacy / "index.php").write_bytes(release_bytes)
    module.verify_overlay_preserved(overlay_candidate, overlay_item)
    require((legacy / "extra.txt").read_bytes() == b"preserve-me\n", "UNMANAGED_FILE_NOT_PRESERVED")
    (legacy / "extra.txt").write_bytes(b"unexpected-change\n")
    overlay_blocked = False
    try:
        module.verify_overlay_preserved(overlay_candidate, overlay_item)
    except module.ReleaseError:
        overlay_blocked = True
    require(overlay_blocked, "UNMANAGED_OVERLAY_DRIFT_ACCEPTED")

    # Missing declared parent directories are managed additions; unrelated additions remain forbidden.
    (legacy / "extra.txt").write_bytes(b"preserve-me\n")
    missing_tree = module.directory_tree_snapshot("chinaplanetcustomercenter/cpkanban")
    managed_release = b"managed-release\n"
    managed_row = {"source_path": "custom/cpkanban/new/asset.php",
                   "runtime_target": "custom/chinaplanetcustomercenter/cpkanban/new/asset.php",
                   "classification": "runtime", "mode": "644", "size": str(len(managed_release)),
                   "sha256": module.hashlib.sha256(managed_release).hexdigest()}
    managed_candidate = types.SimpleNamespace(runtime_rows=[managed_row])
    managed_item = {"path": "chinaplanetcustomercenter/cpkanban", "state": "OVERLAY_DIRECTORY",
                    "expected": "../cpkanban", "actual": "NON_SYMLINK_DIRECTORY", "tree": missing_tree}
    (legacy / "new").mkdir(mode=0o755)
    (legacy / "new/asset.php").write_bytes(managed_release)
    module.verify_overlay_preserved(managed_candidate, managed_item)
    (legacy / "new/unmanaged.txt").write_bytes(b"forbidden\n")
    missing_parent_drift_blocked = False
    try:
        module.verify_overlay_preserved(managed_candidate, managed_item)
    except module.ReleaseError:
        missing_parent_drift_blocked = True
    require(missing_parent_drift_blocked, "UNMANAGED_FILE_IN_MANAGED_PARENT_ACCEPTED")

print("M074_COMPATIBILITY_LAYOUT=PASS DEPLOY_TARGETS=378 OVERLAYS=70 TREE_PIN=EXACT MANAGED_PARENTS=SAFE UNMANAGED_DRIFT=REJECTED")
