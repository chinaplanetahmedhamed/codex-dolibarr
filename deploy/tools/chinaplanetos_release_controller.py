#!/usr/bin/env python3
"""Restricted entry point for one immutable ChinaPlanetOS release."""

import json
import pathlib
import sys

resolved_controller = pathlib.Path(__file__).resolve()
installed_library = resolved_controller.parent
source_library = pathlib.Path(__file__).resolve().parents[1] / "lib"
sys.path.insert(0, str(installed_library if (installed_library / "chinaplanetos_release.py").is_file() else source_library))

from chinaplanetos_release import ReleaseError, run_cli  # noqa: E402


if __name__ == "__main__":
    try:
        raise SystemExit(run_cli(sys.argv))
    except ReleaseError as failure:
        print(json.dumps({
            "ok": False,
            "reason": failure.reason,
            "recovery": failure.recovery,
        }, sort_keys=True, separators=(",", ":")), file=sys.stderr)
        raise SystemExit(1)
