#!/usr/bin/env python3
"""Read-only M090 production API preflight for the exact three-file artifact."""

import hashlib
import io
import json
import os
from pathlib import Path
import pwd
import shutil
import stat
import subprocess
import tarfile


HOST = 'srv1011043'
CONTAINER = 'vision_erp'
ROOT = Path('/var/lib/docker/volumes/vision-os_dolibarr_custom/_data')
ARTIFACT = Path('/home/codex-deploy/staging-inbox/chinaplanetlogistics-api-abbc485.tar.gz')
ARTIFACT_SHA = '4a1e25ceeb54f7bd3d7e7961ec649e9ce81173acb47396303891f100b3367374'
TARGETS = (
    'chinaplanetlogistics/class/api_chinaplanetlogistics.class.php',
    'chinaplanetlogistics/api/class/api_chinaplanetlogistics.class.php',
    'chinaplanetlogistics/core/modules/modChinaPlanetLogistics.class.php',
)


def fail(reason):
    raise RuntimeError(reason)


def run(*argv):
    return subprocess.run(argv, check=True, capture_output=True, text=True).stdout.strip()


def sha(data):
    return hashlib.sha256(data).hexdigest()


def inspect_file(path):
    try:
        info = path.lstat()
    except FileNotFoundError:
        return {'exists': False}
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
        fail('TARGET_NOT_REGULAR:' + path.name)
    return {
        'exists': True,
        'sha256': sha(path.read_bytes()),
        'size': info.st_size,
        'uid': info.st_uid,
        'gid': info.st_gid,
        'mode': '{:04o}'.format(stat.S_IMODE(info.st_mode)),
    }


def load_artifact():
    if ARTIFACT.parent.is_symlink() or not ARTIFACT.parent.is_dir():
        fail('INBOX_METADATA')
    info = ARTIFACT.lstat()
    deploy_uid = pwd.getpwnam('codex-deploy').pw_uid
    if not stat.S_ISREG(info.st_mode) or info.st_uid != deploy_uid or info.st_mode & 0o022:
        fail('ARTIFACT_METADATA')
    blob = ARTIFACT.read_bytes()
    if sha(blob) != ARTIFACT_SHA:
        fail('ARTIFACT_SHA')
    data = {}
    with tarfile.open(fileobj=io.BytesIO(blob), mode='r:gz') as archive:
        members = archive.getmembers()
        if len(members) != len(TARGETS) or set(m.name for m in members) != set(TARGETS):
            fail('ARTIFACT_MEMBERS')
        for member in members:
            if not member.isfile() or member.mode != 0o644 or member.size > 1024 * 1024:
                fail('ARTIFACT_MEMBER_TYPE_OR_MODE')
            payload = archive.extractfile(member)
            if payload is None:
                fail('ARTIFACT_MEMBER_READ')
            data[member.name] = payload.read()
    return data


def http_status(path):
    return int(run('curl', '--silent', '--show-error', '--output', '/dev/null',
                   '--write-out', '%{http_code}', '--max-time', '15',
                   'https://erp.visiontrading.biz' + path))


def main():
    if os.geteuid() != 0 or run('hostname') != HOST:
        fail('HOST_OR_USER')
    if not ROOT.is_dir() or ROOT.is_symlink():
        fail('PRODUCTION_ROOT')
    running = run('docker', 'inspect', '--format', '{{.State.Running}}', CONTAINER)
    if running != 'true':
        fail('PRODUCTION_CONTAINER')
    mounts = json.loads(run('docker', 'inspect', '--format', '{{json .Mounts}}', CONTAINER))
    matching = [m for m in mounts if m.get('Destination') == '/var/www/html/custom']
    if len(matching) != 1 or matching[0].get('Source') != str(ROOT) or not matching[0].get('RW'):
        fail('PRODUCTION_MOUNT')
    root_info = ROOT.stat()
    root_meta = {'uid': root_info.st_uid, 'gid': root_info.st_gid,
                 'mode': '{:04o}'.format(stat.S_IMODE(root_info.st_mode))}
    if shutil.disk_usage(ROOT).free < 20 * 1024 * 1024:
        fail('PRODUCTION_DISK_SPACE')
    for relative in ('chinaplanetlogistics/class/chinaplanetlogistics.class.php',
                     'chinaplanetcustomercenter/lib/portal.lib.php'):
        path = ROOT / relative
        if not path.is_file() or path.is_symlink():
            fail('PRODUCTION_DEPENDENCY:' + relative)
    candidate = load_artifact()
    baseline = {}
    for relative in TARGETS:
        path = ROOT / relative
        parent = path.parent
        while parent != ROOT:
            if parent.is_symlink():
                fail('TARGET_PARENT_SYMLINK:' + relative)
            parent = parent.parent
        baseline[relative] = inspect_file(path)
    root_status = http_status('/')
    if root_status not in (200, 301, 302):
        fail('PRODUCTION_ROOT_HTTP:' + str(root_status))
    api_status = http_status('/api/index.php/chinaplanetlogisticsapi/shipments')
    print(json.dumps({
        'result': 'M090_PRODUCTION_READ_ONLY_PREFLIGHT_PASS',
        'host': HOST,
        'container': CONTAINER,
        'custom_volume': str(ROOT),
        'custom_root_metadata': root_meta,
        'artifact_sha256': ARTIFACT_SHA,
        'baseline': baseline,
        'candidate_sha256': {name: sha(candidate[name]) for name in TARGETS},
        'root_http_status': root_status,
        'current_unauthenticated_api_http_status': api_status,
        'production_changes': False,
    }, sort_keys=True))


if __name__ == '__main__':
    main()
