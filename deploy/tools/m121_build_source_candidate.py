#!/usr/bin/env python3
"""Freeze reviewed Git objects into a source-only M121 archive. Never deploys."""
import argparse
import gzip
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[2]
PREFIXES = (
    "custom/chinaplanetcustomercenter/", "custom/chinaplanetoperations/",
    "custom/chinaplanetlogistics/", "custom/chinaplanetofficeexpenses/",
    "custom/chinaplanetdocuments/", "deploy/tests/",
    "deploy/tools/office_expenses/",
)
EXACT = {
    "AGENTS.md", "deploy/releases/m119/run_ci.sh",
    "deploy/tools/m121_build_source_candidate.py",
    "docs/REPORTS/M121_FINANCE_OFFICE_INTEGRATION_2026-11-03.md",
    "docs/REPORTS/M121_LOCAL_ASSEMBLY_2026-11-03.md",
    "docs/REPORTS/M121_IMMUTABLE_CANDIDATE_GAPS_2026-11-04.md",
    "docs/REPORTS/M121_NATIVE_THREE_SUPPLIER_JOURNEY_2026-11-04.md",
    "docs/REPORTS/M121_NATIVE_ROLE_STATEMENT_ACCEPTANCE_2026-11-04.md",
    "docs/REPORTS/M121_DOCUMENT_COMMIT_RECOVERY_2026-11-04.md",
    "docs/REPORTS/M121_HISTORY_COVERAGE_GUARD_2026-11-04.md",
}
FORBIDDEN = {".env", ".git", "node_modules", "__pycache__"}
FORBIDDEN_SUFFIXES = {".db", ".sqlite", ".sqlite3", ".log", ".pyc", ".pem", ".key"}
BASELINE_LINKS = {
    "custom/chinaplanetcustomercenter/chinaplanetlogistics": "../chinaplanetlogistics",
    "custom/chinaplanetcustomercenter/cpkanban": "../cpkanban",
}


def git(*args):
    return subprocess.check_output(["git", *args], cwd=ROOT)


def digest(data):
    return hashlib.sha256(data).hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("commit", help="Exact local commit; no remote lookup")
    parser.add_argument("output", type=Path, help="New local output directory")
    args = parser.parse_args()
    commit = git("rev-parse", "--verify", args.commit + "^{commit}").decode().strip()
    assert args.commit == commit, "Use the full verified commit ID"
    output = args.output.resolve()
    assert output.is_relative_to(ROOT / "outputs" / "M121"), "Output must stay in this candidate's outputs/M121"
    assert not output.exists(), "Refuse to overwrite a previous candidate"
    records = []
    objects = []
    baseline_links = []
    for entry in git("ls-tree", "-rz", "--full-tree", commit).split(b"\0"):
        if not entry:
            continue
        metadata, encoded_path = entry.split(b"\t", 1)
        path = encoded_path.decode("utf-8")
        if path not in EXACT and not path.startswith(PREFIXES):
            continue
        safe = PurePosixPath(path)
        assert not safe.is_absolute() and ".." not in safe.parts
        assert not FORBIDDEN.intersection(safe.parts) and safe.suffix not in FORBIDDEN_SUFFIXES, path
        mode, kind, oid = metadata.decode().split()
        if path in BASELINE_LINKS:
            target = git("cat-file", "blob", oid).decode()
            local = ROOT / path
            assert mode == "120000" and kind == "blob" and target == BASELINE_LINKS[path]
            assert local.is_symlink() and str(local.readlink()) == target
            baseline_links.append({"path": path, "target": target, "git_blob": oid, "packaged": False})
            continue
        assert kind == "blob" and mode in {"100644", "100755"}, "No symlinks/submodules: " + path
        data = git("cat-file", "blob", oid)
        local = ROOT / path
        assert local.is_file() and not local.is_symlink() and local.read_bytes() == data, "Untested working-tree divergence: " + path
        records.append({"path": path, "git_blob": oid, "mode": mode, "size": len(data), "sha256": digest(data)})
        objects.append(data)
    assert EXACT.issubset({r["path"] for r in records}), "Missing required candidate record"
    assert all(any(r["path"].startswith(p) for r in records) for p in PREFIXES)
    packaged = {r["path"] for r in records}
    for prefix in PREFIXES[:5]:
        for local in (ROOT / prefix).rglob("*"):
            if local.is_file() and not local.is_symlink():
                assert str(local.relative_to(ROOT)) in packaged, "Uncommitted/ignored module source: " + str(local)
    output.mkdir(mode=0o700, parents=True)
    archive = output / "m121-integrated-source.tar.gz"
    with archive.open("xb") as raw:
        archive.chmod(0o600)
        with gzip.GzipFile(fileobj=raw, mode="wb", filename="", mtime=0) as zipped:
            with tarfile.open(fileobj=zipped, mode="w", format=tarfile.USTAR_FORMAT) as tar:
                for record, data in zip(records, objects):
                    info = tarfile.TarInfo(record["path"])
                    info.size = len(data)
                    info.mode = 0o755 if record["mode"] == "100755" else 0o644
                    info.uid = info.gid = info.mtime = 0
                    info.uname = info.gname = ""
                    tar.addfile(info, io.BytesIO(data))
    with tarfile.open(archive, "r:gz") as tar:
        members = tar.getmembers()
        assert [m.name for m in members] == [r["path"] for r in records]
        for member, record in zip(members, records):
            assert member.isfile() and not member.issym() and not member.islnk()
            assert digest(tar.extractfile(member).read()) == record["sha256"]
    manifest = {
        "type": "local_review_source_candidate_only", "commit": commit,
        "tree": git("rev-parse", commit + "^{tree}").decode().strip(),
        "archive": archive.name, "archive_sha256": digest(archive.read_bytes()),
        "file_count": len(records), "allowlisted_prefixes": PREFIXES, "allowlisted_exact": sorted(EXACT),
        "files": records,
        "preserve_existing_baseline_links": baseline_links,
        "paired_documents031_package_sha256": "85d19ccd2a4f159fb6153ee2986dcebda4ff423e4e300a7851b7c9c53895b632",
        "target_state_protected": ["Office finance-bridge.json and business-settings/mapping state", "Installed runtime CSS variants", "Module/config/rights/schema/data/private storage"],
        "not_authorized_by_packaging": ["Deployment", "Module activation or migrations", "Grant changes", "Real posting", "Production access"],
        "remaining_gates": "See docs/REPORTS/M121_IMMUTABLE_CANDIDATE_GAPS_2026-11-04.md",
        "current_journey_report": "docs/REPORTS/M121_NATIVE_THREE_SUPPLIER_JOURNEY_2026-11-04.md",
        "current_history_coverage_report": "docs/REPORTS/M121_HISTORY_COVERAGE_GUARD_2026-11-04.md",
        "current_document_recovery_report": "docs/REPORTS/M121_DOCUMENT_COMMIT_RECOVERY_2026-11-04.md",
        "current_role_statement_report": "docs/REPORTS/M121_NATIVE_ROLE_STATEMENT_ACCEPTANCE_2026-11-04.md",
        "verification": "Each archive byte re-read against SHA256 of exact Git blob; selected working tree matched commit before packaging",
    }
    path = output / "manifest.json"
    path.write_text(json.dumps(manifest, indent=2) + "\n")
    path.chmod(0o600)
    (output / "SHA256SUMS").write_text(manifest["archive_sha256"] + "  " + archive.name + "\n" + digest(path.read_bytes()) + "  manifest.json\n")
    (output / "SHA256SUMS").chmod(0o600)
    print(json.dumps({k: manifest[k] for k in ("commit", "tree", "archive", "archive_sha256", "file_count", "type")}, indent=2))


if __name__ == "__main__":
    main()
