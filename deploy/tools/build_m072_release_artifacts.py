#!/usr/bin/env python3
"""Build deterministic M072 component artifacts from pinned Git commits."""

from __future__ import annotations

import argparse
import csv
import gzip
import hashlib
import io
import json
import pathlib
import re
import subprocess
import tarfile
import tempfile

SHA_RE = re.compile(r"^[0-9a-f]{40}$")
RELEASE_RE = re.compile(r"^M\d{3}-R\d{2}$")
COMPONENTS = ("dolibarr", "react", "wordpress", "mobile")
ALLOWED_COMPATIBILITY_SYMLINKS = {
    "custom/chinaplanetcustomercenter/chinaplanetlogistics",
    "custom/chinaplanetcustomercenter/cpkanban",
}
EXPECTED_COMPATIBILITY_LINKS = {
    "custom/chinaplanetcustomercenter/chinaplanetlogistics": "../chinaplanetlogistics",
    "custom/chinaplanetcustomercenter/cpkanban": "../cpkanban",
}
M069_C04_RESIDUE = {
    "custom/chinaplanetcustomercenter/admin/acceptance_demo.php",
    "custom/chinaplanetcustomercenter/admin/demo/seed_customer_portal_demo.php",
    "custom/chinaplanetcustomercenter/admin/demo/seed_full_order_cycles.php",
    "custom/chinaplanetcustomercenter/admin/demo/seed_operations_sops.php",
}


def fail(reason: str) -> None:
    raise SystemExit(f"M072_ARTIFACT_BUILD=FAIL REASON={reason}")


def git(repo: pathlib.Path, *args: str, text: bool = True):
    try:
        return subprocess.check_output(["git", "-C", str(repo), *args], text=text)
    except subprocess.CalledProcessError:
        fail("GIT_READ_FAILED")


def check_repo(repo: pathlib.Path, commit: str) -> None:
    if not repo.is_dir() or not SHA_RE.fullmatch(commit):
        fail("SOURCE_REPOSITORY_INVALID")
    if git(repo, "rev-parse", "--verify", f"{commit}^{{commit}}").strip() != commit:
        fail("SOURCE_COMMIT_MISSING")
    if git(repo, "status", "--porcelain", "--untracked-files=all").strip():
        fail("SOURCE_WORKTREE_NOT_CLEAN")


def git_blob(repo: pathlib.Path, commit: str, path: str) -> bytes:
    return git(repo, "show", f"{commit}:{path}", text=False)


def tree_files(repo: pathlib.Path, commit: str, prefix: str = "") -> dict[str, int]:
    arguments = ["ls-tree", "-r", "-z", commit]
    if prefix:
        arguments.extend(("--", prefix))
    raw = git(repo, *arguments, text=False)
    result: dict[str, int] = {}
    for entry in raw.split(b"\0"):
        if not entry:
            continue
        metadata, path_bytes = entry.split(b"\t", 1)
        mode, kind, _ = metadata.decode("ascii").split(" ", 2)
        path = path_bytes.decode("utf-8")
        if kind == "blob" and mode == "120000" and path in ALLOWED_COMPATIBILITY_SYMLINKS:
            continue
        if kind != "blob" or mode not in ("100644", "100755"):
            fail("UNSUPPORTED_GIT_ENTRY")
        if path.startswith("/") or ".." in pathlib.PurePosixPath(path).parts or "\n" in path or "\t" in path:
            fail("UNSAFE_SOURCE_PATH")
        result[path] = 0o755 if mode == "100755" else 0o644
    return result


def parse_m070_runtime(repo: pathlib.Path, commit: str) -> set[str]:
    content = git_blob(repo, commit, "docs/REPORTS/M070_DOLIBARR_RUNTIME_SOURCE_MAP.tsv").decode("utf-8")
    rows = csv.DictReader(io.StringIO(content), delimiter="\t")
    expected = ["runtime_path", "canonical_source_path", "selected_sha256", "source_layer", "source_commit", "production_sha256", "staging_sha256", "disposition"]
    if rows.fieldnames != expected:
        fail("M070_RUNTIME_MAP_HEADER_INVALID")
    paths = {row["canonical_source_path"] for row in rows if row["canonical_source_path"] != "-"}
    return paths


def parse_m071_runtime(repo: pathlib.Path, commit: str) -> set[str]:
    content = git_blob(repo, commit, "docs/REPORTS/M071_SOURCE_TO_RUNTIME_MANIFEST.tsv").decode("utf-8")
    rows = csv.DictReader(io.StringIO(content), delimiter="\t")
    if rows.fieldnames != ["repository", "source_path", "runtime_target", "classification"]:
        fail("M071_RUNTIME_MAP_HEADER_INVALID")
    return {
        row["source_path"] for row in rows
        if row["repository"] == "dolibarr" and row["classification"] in ("dolibarr_runtime", "schema_baseline_source")
    }


def dolibarr_files(repo: pathlib.Path, commit: str) -> dict[str, tuple[int, str]]:
    tracked = tree_files(repo, commit)
    runtime = parse_m070_runtime(repo, commit) | parse_m071_runtime(repo, commit)
    if runtime & M069_C04_RESIDUE:
        fail("M069_C04_RESIDUE_INCLUDED")
    missing = sorted(path for path in runtime if path not in tracked)
    if missing:
        fail("DOLIBARR_RUNTIME_SOURCE_MISSING")
    selected: dict[str, tuple[int, str]] = {path: (tracked[path], "runtime") for path in runtime}
    support_prefixes = ("deploy/migrations/", "deploy/tests/", "deploy/lib/")
    support_exact = {
        "deploy/tools/chinaplanetos_release_controller.py",
        "deploy/tools/install_chinaplanetos_release_controller.sh",
        "deploy/tools/build_m072_release_artifacts.py",
        "deploy/config/codex-deploy-chinaplanetos-release.sudoers",
    }
    for path, mode in tracked.items():
        if path.startswith(support_prefixes) or path in support_exact:
            selected.setdefault(path, (mode, "support"))
        if path.startswith("docs/MISSIONS/M069") or path.startswith("docs/MISSIONS/M070") or path.startswith("docs/MISSIONS/M071") or path.startswith("docs/MISSIONS/M072"):
            selected.setdefault(path, (mode, "evidence"))
        if path.startswith("docs/REPORTS/M069") or path.startswith("docs/REPORTS/M070") or path.startswith("docs/REPORTS/M071") or path.startswith("docs/REPORTS/M072"):
            selected.setdefault(path, (mode, "evidence"))
    if not any(path.startswith("deploy/migrations/") for path in selected):
        fail("MIGRATIONS_MISSING")
    return selected


def all_files(repo: pathlib.Path, commit: str, classification: str, prefix: str = "") -> dict[str, tuple[int, str]]:
    return {path: (mode, classification) for path, mode in tree_files(repo, commit, prefix).items()}


def build_component(repo: pathlib.Path, commit: str, component: str, files: dict[str, tuple[int, str]], output: pathlib.Path) -> tuple[dict, bytes]:
    rows = []
    archive_buffer = io.BytesIO()
    with tarfile.open(fileobj=archive_buffer, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for path in sorted(files):
            mode, classification = files[path]
            content = git_blob(repo, commit, path)
            digest = hashlib.sha256(content).hexdigest()
            runtime_target = path if classification in ("runtime", "container-build-input", "plugin-source") else "-"
            rows.append((path, runtime_target, classification, f"{mode:o}", str(len(content)), digest))
            info = tarfile.TarInfo(path)
            info.size = len(content)
            info.mode = mode
            info.uid = info.gid = 0
            info.uname = info.gname = "root"
            info.mtime = 0
            archive.addfile(info, io.BytesIO(content))

    manifest_buffer = io.StringIO(newline="")
    writer = csv.writer(manifest_buffer, delimiter="\t", lineterminator="\n")
    writer.writerow(("source_path", "runtime_target", "classification", "mode", "size", "sha256"))
    writer.writerows(rows)
    manifest_bytes = manifest_buffer.getvalue().encode("utf-8")
    manifest_name = f"{component}.manifest.tsv"
    artifact_name = f"{component}.tar.gz"
    manifest_path = output / manifest_name
    artifact_path = output / artifact_name
    manifest_path.write_bytes(manifest_bytes)
    with artifact_path.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", compresslevel=9, fileobj=raw, mtime=0) as compressed:
            compressed.write(archive_buffer.getvalue())
    return ({
        "id": component,
        "commit": commit,
        "artifact": artifact_name,
        "artifactSha256": hashlib.sha256(artifact_path.read_bytes()).hexdigest(),
        "manifest": manifest_name,
        "manifestSha256": hashlib.sha256(manifest_bytes).hexdigest(),
        "members": len(rows),
        "schemaPolicy": "additive-retain-on-code-rollback" if component == "dolibarr" else "none",
        "configPolicy": "external-retain",
        "dataPolicy": "retain",
    }, manifest_bytes)


def write_deprecation_ledger(sources: dict[str, tuple[pathlib.Path, str]], output: pathlib.Path) -> None:
    rows = []
    current = {
        "deploy/tools/chinaplanetos_release_controller.py",
        "deploy/tools/install_chinaplanetos_release_controller.sh",
        "deploy/tools/build_m072_release_artifacts.py",
        "deploy/config/codex-deploy-chinaplanetos-release.sudoers",
    }
    for repository in ("dolibarr", "react", "wordpress"):
        repo, commit = sources[repository]
        tracked = tree_files(repo, commit)
        for path in sorted(tracked):
            workflow = path.startswith(".github/workflows/") and path.endswith((".yml", ".yaml"))
            dolibarr_surface = repository == "dolibarr" and (
                path.startswith("deploy/tools/") or (path.startswith("deploy/config/") and path.endswith(".sudoers"))
            )
            if not (workflow or dolibarr_surface) or (repository == "dolibarr" and path in current):
                continue
            rows.append((
                repository,
                path,
                hashlib.sha256(git_blob(repo, commit, path)).hexdigest(),
                "LEGACY_RETAINED_NOT_AUTHORIZED",
                "M072_CONTROLLER_AFTER_ENVIRONMENT_ACCEPTANCE",
                "NO_REMOVAL_BEFORE_SEPARATE_CLEANUP_MISSION",
            ))
    target = output / "historical-helper-deprecation-ledger.tsv"
    with target.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle, delimiter="\t", lineterminator="\n")
        writer.writerow(("repository", "path", "sha256", "status", "successor", "removal_gate"))
        writer.writerows(rows)


def write_dolibarr_layout(repo: pathlib.Path, commit: str, output: pathlib.Path) -> tuple[str, str]:
    target = output / "dolibarr.layout.tsv"
    with target.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle, delimiter="\t", lineterminator="\n")
        writer.writerow(("runtime_path", "entry_type", "link_target", "source_sha256"))
        for path, expected_target in sorted(EXPECTED_COMPATIBILITY_LINKS.items()):
            raw = git_blob(repo, commit, path)
            try:
                link_target = raw.decode("utf-8")
            except UnicodeDecodeError:
                fail("COMPATIBILITY_LINK_INVALID")
            if link_target != expected_target:
                fail("COMPATIBILITY_LINK_TARGET_MISMATCH")
            writer.writerow((path, "symlink", link_target, hashlib.sha256(raw).hexdigest()))
    return target.name, hashlib.sha256(target.read_bytes()).hexdigest()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--sources", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    source_file = pathlib.Path(args.sources).resolve()
    output = pathlib.Path(args.output).resolve()
    try:
        config = json.loads(source_file.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        fail("SOURCE_DESCRIPTOR_INVALID")
    if config.get("schemaVersion") != 1 or not RELEASE_RE.fullmatch(str(config.get("releaseId", ""))):
        fail("SOURCE_DESCRIPTOR_INVALID")
    sources = config.get("sources")
    if not isinstance(sources, dict) or set(sources) != set(COMPONENTS):
        fail("SOURCE_SET_INVALID")

    resolved = {}
    for component in COMPONENTS:
        value = sources[component]
        if not isinstance(value, dict):
            fail("SOURCE_ENTRY_INVALID")
        repo = pathlib.Path(str(value.get("root", ""))).resolve()
        commit = str(value.get("commit", ""))
        check_repo(repo, commit)
        resolved[component] = (repo, commit)

    output.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="m072-build-", dir=output.parent) as raw:
        temporary = pathlib.Path(raw)
        dol_repo, dol_commit = resolved["dolibarr"]
        react_repo, react_commit = resolved["react"]
        wp_repo, wp_commit = resolved["wordpress"]
        mobile_repo, mobile_commit = resolved["mobile"]
        file_sets = {
            "dolibarr": (dol_repo, dol_commit, dolibarr_files(dol_repo, dol_commit)),
            "react": (react_repo, react_commit, all_files(react_repo, react_commit, "container-build-input")),
            "wordpress": (wp_repo, wp_commit, all_files(wp_repo, wp_commit, "plugin-source")),
            "mobile": (mobile_repo, mobile_commit, all_files(mobile_repo, mobile_commit, "mobile-source", "mobile/chinaplanet-customer-app")),
        }
        components = []
        for component in COMPONENTS:
            repo, commit, files = file_sets[component]
            if not files:
                fail("COMPONENT_EMPTY")
            built, _ = build_component(repo, commit, component, files, temporary)
            if component == "dolibarr":
                layout_name, layout_sha = write_dolibarr_layout(dol_repo, dol_commit, temporary)
                built["layout"] = layout_name
                built["layoutSha256"] = layout_sha
            components.append(built)
        write_deprecation_ledger({
            "dolibarr": (dol_repo, dol_commit),
            "react": (react_repo, react_commit),
            "wordpress": (wp_repo, wp_commit),
        }, temporary)
        bundle = {
            "schemaVersion": 1,
            "releaseId": config["releaseId"],
            "bindingStatus": "TARGET_ADAPTERS_AND_ENVIRONMENT_REQUIRE_M073_APPROVAL",
            "components": components,
        }
        (temporary / "release-bundle.json").write_text(json.dumps(bundle, sort_keys=True, indent=2) + "\n", encoding="utf-8")
        pins = []
        for path in sorted(temporary.iterdir()):
            if path.name == "release-pins.sha256":
                continue
            pins.append(f"{hashlib.sha256(path.read_bytes()).hexdigest()}  {path.name}\n")
        (temporary / "release-pins.sha256").write_text("".join(pins), encoding="ascii")

        if output.exists():
            if output.is_symlink() or not output.is_dir() or any(output.iterdir()):
                fail("OUTPUT_NOT_EMPTY")
        else:
            output.mkdir(mode=0o755)
        for path in sorted(temporary.iterdir()):
            path.replace(output / path.name)

    print(f"M072_ARTIFACT_BUILD=PASS RELEASE={config['releaseId']} COMPONENTS=4 OUTPUT={output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
