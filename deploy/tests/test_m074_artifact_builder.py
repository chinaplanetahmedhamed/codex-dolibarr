#!/usr/bin/env python3
"""Source contract for the M074 checksum-pinned release builder."""

from __future__ import annotations

import csv
import pathlib


ROOT = pathlib.Path(__file__).resolve().parents[2]
BUILDER = ROOT / "deploy/tools/build_m072_release_artifacts.py"
MANIFEST = ROOT / "docs/REPORTS/M074_SOURCE_TO_RUNTIME_MANIFEST.tsv"


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RuntimeError(message)


source = BUILDER.read_text(encoding="utf-8")
require("def parse_m074_runtime" in source, "M074 runtime manifest parser is missing")
require("parse_m074_runtime(repo, commit, tracked)" in source, "M074 runtime rows are not included")
require("UNBOUND_REQUIRES_INDEPENDENT_REVIEW_AND_STAGING_APPROVAL" in source, "M074 artifact is not release-gated")
require("config[\"releaseId\"] == \"M072-R01\"" in source, "M072 frozen binding compatibility is not preserved")

with MANIFEST.open(encoding="utf-8", newline="") as handle:
    rows = list(csv.DictReader(handle, delimiter="\t"))

require(
    rows and list(rows[0]) == ["repository", "source_path", "runtime_target", "classification"],
    "M074 manifest header is invalid",
)
runtime = [
    row for row in rows
    if row["repository"] == "dolibarr" and row["classification"] in ("dolibarr_runtime", "schema_baseline_source")
]
require(len(runtime) == 42, "M074 runtime surface must contain exactly 42 reviewed files")
require(len({row["source_path"] for row in runtime}) == len(runtime), "M074 runtime paths are duplicated")
for row in runtime:
    require(row["runtime_target"] == row["source_path"], "M074 runtime target mismatch: " + row["source_path"])
    require(ROOT.joinpath(row["source_path"]).is_file(), "M074 runtime source is absent: " + row["source_path"])

required = {
    "custom/chinaplanetoperations/class/QcService.class.php",
    "custom/chinaplanetoperations/qc/service-worker.js",
    "custom/chinaplanetoperations/sql/llx_cpo_qc_publication.sql",
    "custom/chinaplanetcustomercenter/qc_report.php",
    "custom/chinaplanetcustomercenter/lib/customer_documents.lib.php",
}
require(required <= {row["source_path"] for row in runtime}, "M074 critical runtime source is missing")

print("M074_ARTIFACT_BUILDER=PASS RUNTIME_FILES=42 BINDING=UNBOUND_REVIEW_REQUIRED")
