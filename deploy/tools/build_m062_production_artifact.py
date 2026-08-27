#!/usr/bin/env python3
"""Build the checksum-pinned M062 production payload."""

import argparse
import gzip
import hashlib
import io
import pathlib
import subprocess
import tarfile


RUNTIME_COMMIT = "1d42fdb77214ca5f7b8613bc16fde8a04cc5dc64"
RUNTIME_MANIFEST = "deploy/releases/m062-production-runtime-files.txt"
SUPPORT_MANIFEST = "deploy/releases/m062-production-support-files.txt"
PRESTATE_MANIFEST = "deploy/releases/m062-production-prestate.sha256"
EXPECTED_SHA256 = "694b029d61777c4b542f5d9f56f0586e936f8b161fd939af3149c8b171b9f3f3"


def git_bytes(repo: pathlib.Path, path: str) -> bytes:
    return subprocess.check_output(["git", "show", f"{RUNTIME_COMMIT}:{path}"], cwd=repo)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", required=True)
    parser.add_argument("--freeze", action="store_true")
    args = parser.parse_args()
    if RUNTIME_COMMIT == "TO_BE_FROZEN":
        raise SystemExit("M062_PRODUCTION_ARTIFACT_BUILD=FAIL REASON=COMMIT_NOT_FROZEN")

    repo = pathlib.Path(__file__).resolve().parents[2]
    commit_epoch = int(subprocess.check_output(
        ["git", "show", "-s", "--format=%ct", RUNTIME_COMMIT], cwd=repo, text=True
    ).strip())
    runtime = git_bytes(repo, RUNTIME_MANIFEST).decode().splitlines()
    support = git_bytes(repo, SUPPORT_MANIFEST).decode().splitlines()
    paths = runtime + support + [RUNTIME_MANIFEST, SUPPORT_MANIFEST, PRESTATE_MANIFEST]
    if len(paths) != 19 or len(set(paths)) != 19 or any(
        not path or path.startswith("/") or ".." in path.split("/") for path in paths
    ):
        raise SystemExit("M062_PRODUCTION_ARTIFACT_BUILD=FAIL REASON=ALLOWLIST_INVALID")

    tar_buffer = io.BytesIO()
    with tarfile.open(fileobj=tar_buffer, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for path in paths:
            content = git_bytes(repo, path)
            info = tarfile.TarInfo(path)
            info.size = len(content)
            info.mode = 0o644
            info.uid = info.gid = 0
            info.uname = info.gname = "root"
            info.mtime = commit_epoch
            archive.addfile(info, io.BytesIO(content))

    output = pathlib.Path(args.output).resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", compresslevel=6, fileobj=raw, mtime=0) as compressed:
            compressed.write(tar_buffer.getvalue())
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    if EXPECTED_SHA256 == "TO_BE_FROZEN":
        if not args.freeze:
            raise SystemExit("M062_PRODUCTION_ARTIFACT_BUILD=FAIL REASON=SHA_NOT_FROZEN")
    elif digest != EXPECTED_SHA256:
        raise SystemExit("M062_PRODUCTION_ARTIFACT_BUILD=FAIL REASON=SHA_MISMATCH ACTUAL=" + digest)
    print(f"M062_PRODUCTION_ARTIFACT_BUILD=PASS FILES=19 SHA256={digest} COMMIT={RUNTIME_COMMIT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
