#!/usr/bin/env python3
"""Exercise safe legacy-directory conversion and exact rollback for M074-R07."""

from __future__ import annotations

import importlib.machinery
import importlib.util
import pathlib
import tempfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
HELPER = ROOT / "deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh"
loader = importlib.machinery.SourceFileLoader("m074_layout_helper", str(HELPER))
spec = importlib.util.spec_from_loader(loader.name, loader)
module = importlib.util.module_from_spec(spec)
loader.exec_module(module)


def require(condition: bool, reason: str) -> None:
    if not condition:
        raise SystemExit("M074_COMPATIBILITY_LAYOUT=FAIL REASON=" + reason)


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
    candidate = module.Candidate(pathlib.Path(temporary) / "work", {}, pathlib.Path(temporary), [], {
        "custom/chinaplanetcustomercenter/cpkanban": "../cpkanban",
    })
    snapshot = module.runtime_snapshot(candidate)
    require(snapshot["layout"][0]["state"] == "CONVERTIBLE", "IDENTICAL_DIRECTORY_NOT_CONVERTIBLE")

    original = snapshot["layout"][0]
    module.apply_layout(snapshot)
    require(legacy.is_symlink() and legacy.readlink().as_posix() == "../cpkanban", "SYMLINK_NOT_APPLIED")
    module.restore_layout(snapshot)
    require(legacy.is_dir() and not legacy.is_symlink(), "DIRECTORY_NOT_RESTORED")
    require(module.current_layout_item(original) == original, "DIRECTORY_PRESTATE_NOT_EXACT")

    (legacy / "index.php").write_bytes(b"<?php echo 'different';\n")
    mismatch = module.runtime_snapshot(candidate)
    require(mismatch["layout"][0]["state"] == "DIFFERENT", "MISMATCH_NOT_REJECTED")

print("M074_COMPATIBILITY_LAYOUT=PASS CONVERT=ATOMIC ROLLBACK=EXACT MISMATCH=REJECTED")
