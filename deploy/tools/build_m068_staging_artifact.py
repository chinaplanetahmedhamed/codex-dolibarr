#!/usr/bin/env python3
"""Build the deterministic metadata-free M068 staging candidate."""

import argparse
import gzip
import hashlib
import io
import pathlib
import subprocess
import tarfile

RUNTIME_COMMIT = "32e82ffbd82248f2a84f468172195267fb6d37a7"
RUNTIME_MANIFEST = "deploy/releases/m068-runtime-files.txt"
SUPPORT_MANIFEST = "deploy/releases/m068-support-files.txt"
BASELINE_MANIFEST = "deploy/releases/m068-m062-r04-baseline.sha256"
DEPENDENCY_MANIFEST = "deploy/releases/m068-m062-r04-dependencies.sha256"
PREPARATION_PATHS = (RUNTIME_MANIFEST, SUPPORT_MANIFEST, BASELINE_MANIFEST, DEPENDENCY_MANIFEST, "deploy/tests/test_m068_staging_configuration.php")
EXPECTED_SHA256 = "369ba510238edc1d67bf8f2d64b14a51c680070b717806fc4029527e2ff19e46"


def git_bytes(repo: pathlib.Path, commit: str, rel: str) -> bytes:
    return subprocess.check_output(["git", "show", f"{commit}:{rel}"], cwd=repo)


def worktree_bytes(repo: pathlib.Path, rel: str) -> bytes:
    return (repo / rel).read_bytes()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", required=True)
    parser.add_argument("--freeze", action="store_true")
    args = parser.parse_args()
    repo = pathlib.Path(__file__).resolve().parents[2]
    runtime = worktree_bytes(repo, RUNTIME_MANIFEST).decode().splitlines()
    support = worktree_bytes(repo, SUPPORT_MANIFEST).decode().splitlines()
    if len(runtime) != 26 or len(support) != 5:
        raise SystemExit("M068_ARTIFACT_BUILD=FAIL REASON=MANIFEST_COUNT_INVALID")
    preparation = set(PREPARATION_PATHS)
    sourced = [(RUNTIME_COMMIT, rel) for rel in runtime]
    sourced.extend((None if rel in preparation else RUNTIME_COMMIT, rel) for rel in support)
    sourced.extend((None, rel) for rel in (RUNTIME_MANIFEST, SUPPORT_MANIFEST, BASELINE_MANIFEST, DEPENDENCY_MANIFEST))
    rels = [rel for _, rel in sourced]
    if len(rels) != 35 or len(set(rels)) != 35 or any(not rel or rel.startswith("/") or ".." in rel.split("/") for rel in rels):
        raise SystemExit("M068_ARTIFACT_BUILD=FAIL REASON=ALLOWLIST_INVALID")
    epoch = int(subprocess.check_output(["git", "show", "-s", "--format=%ct", RUNTIME_COMMIT], cwd=repo, text=True).strip())
    buffer = io.BytesIO()
    with tarfile.open(fileobj=buffer, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for commit, rel in sourced:
            content = worktree_bytes(repo, rel) if commit is None else git_bytes(repo, commit, rel)
            info = tarfile.TarInfo(rel)
            info.size = len(content)
            info.mode = 0o644
            info.uid = info.gid = 0
            info.uname = info.gname = "root"
            info.mtime = epoch
            archive.addfile(info, io.BytesIO(content))
    output = pathlib.Path(args.output).resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", compresslevel=6, fileobj=raw, mtime=0) as compressed:
            compressed.write(buffer.getvalue())
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    if EXPECTED_SHA256 == "TO_BE_FROZEN":
        if not args.freeze:
            raise SystemExit("M068_ARTIFACT_BUILD=FAIL REASON=SHA_NOT_FROZEN")
    elif digest != EXPECTED_SHA256:
        raise SystemExit("M068_ARTIFACT_BUILD=FAIL REASON=SHA_MISMATCH ACTUAL="+digest)
    print(f"M068_ARTIFACT_BUILD=PASS FILES=35 SHA256={digest} RUNTIME_COMMIT={RUNTIME_COMMIT} METADATA=USTAR_CANONICAL")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
