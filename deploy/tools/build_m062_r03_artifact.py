#!/usr/bin/env python3
"""Build the code-only M062-R03 artifact with canonical tar metadata."""

import argparse
import gzip
import hashlib
import io
import pathlib
import subprocess
import tarfile


RUNTIME_COMMIT = "dcd24ddd99ab5668c2ace3ccddd580abec24b472"
SCOPE_COMMIT = "ee073e681d52f97a52c8b6c1bf723ca1ba78012b"
RUNTIME_MANIFEST = "deploy/releases/m062-r03-runtime-files.txt"
SUPPORT_MANIFEST = "deploy/releases/m062-r03-support-files.txt"
PRESTATE_MANIFEST = "deploy/releases/m062-r03-prestate.sha256"
EXPECTED_SHA256 = "8ab43813389f06476ff6f3843cca26f09b780bfbe5c727510ff4a48270c746e7"


def git_bytes(repo: pathlib.Path, commit: str, path: str) -> bytes:
    return subprocess.check_output(["git", "show", f"{commit}:{path}"], cwd=repo)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", required=True)
    parser.add_argument("--freeze", action="store_true")
    args = parser.parse_args()

    repo = pathlib.Path(__file__).resolve().parents[2]
    epoch = int(subprocess.check_output(
        ["git", "show", "-s", "--format=%ct", RUNTIME_COMMIT], cwd=repo, text=True
    ).strip())
    runtime = git_bytes(repo, SCOPE_COMMIT, RUNTIME_MANIFEST).decode().splitlines()
    support = git_bytes(repo, SCOPE_COMMIT, SUPPORT_MANIFEST).decode().splitlines()
    sourced = [(RUNTIME_COMMIT, path) for path in runtime + support]
    sourced += [(SCOPE_COMMIT, path) for path in (
        RUNTIME_MANIFEST, SUPPORT_MANIFEST, PRESTATE_MANIFEST
    )]
    paths = [path for _, path in sourced]
    if len(paths) != 7 or len(set(paths)) != 7 or any(
        not path or path.startswith("/") or ".." in path.split("/") for path in paths
    ):
        raise SystemExit("M062_R03_ARTIFACT_BUILD=FAIL REASON=ALLOWLIST_INVALID")

    tar_buffer = io.BytesIO()
    with tarfile.open(fileobj=tar_buffer, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for commit, path in sourced:
            content = git_bytes(repo, commit, path)
            info = tarfile.TarInfo(path)
            info.size = len(content)
            info.mode = 0o644
            info.uid = 0
            info.gid = 0
            info.uname = "root"
            info.gname = "root"
            info.mtime = epoch
            archive.addfile(info, io.BytesIO(content))

    output = pathlib.Path(args.output).resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", compresslevel=6, fileobj=raw, mtime=0) as compressed:
            compressed.write(tar_buffer.getvalue())
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    if EXPECTED_SHA256 == "TO_BE_FROZEN":
        if not args.freeze:
            raise SystemExit("M062_R03_ARTIFACT_BUILD=FAIL REASON=SHA_NOT_FROZEN")
    elif digest != EXPECTED_SHA256:
        raise SystemExit("M062_R03_ARTIFACT_BUILD=FAIL REASON=SHA_MISMATCH ACTUAL=" + digest)
    print(f"M062_R03_ARTIFACT_BUILD=PASS FILES=7 SHA256={digest} RUNTIME_COMMIT={RUNTIME_COMMIT} SCOPE_COMMIT={SCOPE_COMMIT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
