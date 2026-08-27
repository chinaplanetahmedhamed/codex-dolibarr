#!/usr/bin/env python3
"""Build the M062 payload from a fixed Git commit with canonical tar metadata."""

import argparse
import gzip
import hashlib
import io
import pathlib
import subprocess
import tarfile


RUNTIME_COMMIT = "525eca241a323d4ae073077a940de75d9d3b64e7"
RUNTIME_MANIFEST = "deploy/releases/m062-runtime-files.txt"
SUPPORT_MANIFEST = "deploy/releases/m062-support-files.txt"
PRESTATE_MANIFEST = "deploy/releases/m062-prestate.sha256"
EXPECTED_SHA256 = "3ec2073c80f8ea99ce01a8c1e975a9c6d0917c0a63b17253d672b43c37a7ac28"


def git_bytes(repo: pathlib.Path, path: str) -> bytes:
    return subprocess.check_output(
        ["git", "show", f"{RUNTIME_COMMIT}:{path}"], cwd=repo
    )


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", required=True)
    parser.add_argument("--freeze", action="store_true", help="allow the first build before the expected SHA is frozen")
    args = parser.parse_args()

    repo = pathlib.Path(__file__).resolve().parents[2]
    commit_epoch = int(
        subprocess.check_output(
            ["git", "show", "-s", "--format=%ct", RUNTIME_COMMIT], cwd=repo, text=True
        ).strip()
    )
    runtime = git_bytes(repo, RUNTIME_MANIFEST).decode("utf-8").splitlines()
    support = git_bytes(repo, SUPPORT_MANIFEST).decode("utf-8").splitlines()
    paths = runtime + support + [RUNTIME_MANIFEST, SUPPORT_MANIFEST, PRESTATE_MANIFEST]
    if len(paths) != 15 or len(set(paths)) != 15 or any(not p or p.startswith("/") or ".." in p.split("/") for p in paths):
        raise SystemExit("M062_ARTIFACT_BUILD=FAIL REASON=ALLOWLIST_INVALID")

    tar_buffer = io.BytesIO()
    with tarfile.open(fileobj=tar_buffer, mode="w", format=tarfile.USTAR_FORMAT) as archive:
        for path in paths:
            content = git_bytes(repo, path)
            info = tarfile.TarInfo(path)
            info.size = len(content)
            info.mode = 0o644
            info.uid = 0
            info.gid = 0
            info.uname = "root"
            info.gname = "root"
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
            raise SystemExit("M062_ARTIFACT_BUILD=FAIL REASON=SHA_NOT_FROZEN")
    elif digest != EXPECTED_SHA256:
        raise SystemExit("M062_ARTIFACT_BUILD=FAIL REASON=SHA_MISMATCH ACTUAL=" + digest)
    print(f"M062_ARTIFACT_BUILD=PASS FILES=15 SHA256={digest} COMMIT={RUNTIME_COMMIT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
