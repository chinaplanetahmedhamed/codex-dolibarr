#!/usr/bin/env python3
"""Exact three-file M090 release for canonical backup-erp staging only."""

import fcntl
import hashlib
import io
import json
import os
from pathlib import Path
import pwd
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile
from datetime import datetime, timezone


HOST = 'srv1011043'
CONTAINER = 'erp-staging-dolibarr-1'
ROOT = Path('/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data')
ARTIFACT = Path('/home/codex-deploy/staging-inbox/chinaplanetlogistics-api-abbc485.tar.gz')
ARTIFACT_SHA = '4a1e25ceeb54f7bd3d7e7961ec649e9ce81173acb47396303891f100b3367374'
COMMIT = 'abbc485'
STATE_DIR = Path('/root/chinaplanetos-m090-staging')
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


def check_host():
    if os.geteuid() != 0 or run('hostname') != HOST:
        fail('HOST_OR_USER')


def load_artifact():
    if ARTIFACT.parent.is_symlink() or not ARTIFACT.parent.is_dir():
        fail('ARTIFACT_INBOX')
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


def current(path):
    try:
        info = path.lstat()
    except FileNotFoundError:
        return {'exists': False}
    if not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
        fail('TARGET_NOT_REGULAR')
    return {
        'exists': True,
        'sha256': sha(path.read_bytes()),
        'uid': info.st_uid,
        'gid': info.st_gid,
        'mode': stat.S_IMODE(info.st_mode),
    }


def check_runtime():
    if not ROOT.is_dir() or ROOT.is_symlink():
        fail('STAGING_ROOT')
    root_info = ROOT.stat()
    if (root_info.st_uid, root_info.st_gid, stat.S_IMODE(root_info.st_mode)) != (33, 33, 0o755):
        fail('STAGING_ROOT_METADATA')
    running = run('docker', 'inspect', '--format', '{{.State.Running}}', CONTAINER)
    if running != 'true':
        fail('STAGING_CONTAINER')
    mounts = json.loads(run('docker', 'inspect', '--format', '{{json .Mounts}}', CONTAINER))
    matching = [m for m in mounts if m.get('Destination') == '/var/www/html/custom']
    if len(matching) != 1 or matching[0].get('Source') != str(ROOT) or not matching[0].get('RW'):
        fail('STAGING_MOUNT')
    for relative in ('chinaplanetlogistics/class/chinaplanetlogistics.class.php',
                     'chinaplanetcustomercenter/lib/portal.lib.php'):
        path = ROOT / relative
        if not path.is_file() or path.is_symlink():
            fail('STAGING_DEPENDENCY')
    if shutil.disk_usage(ROOT).free < 20 * 1024 * 1024:
        fail('STAGING_DISK_SPACE')
    for relative in TARGETS:
        parent = (ROOT / relative).parent
        while parent != ROOT:
            if parent.is_symlink():
                fail('TARGET_PARENT_SYMLINK')
            parent = parent.parent
        current(ROOT / relative)


def preflight():
    check_host()
    check_runtime()
    data = load_artifact()
    print('M090_STAGING_PREFLIGHT=PASS')
    print('COMMIT=' + COMMIT + ' ARTIFACT_SHA256=' + ARTIFACT_SHA)
    for relative in TARGETS:
        before = current(ROOT / relative)
        print('TARGET=' + relative + ' BASELINE=' + (before.get('sha256') or 'ABSENT') +
              ' CANDIDATE=' + sha(data[relative]))
    return data


def sync_dir(path):
    descriptor = os.open(str(path), os.O_RDONLY | os.O_DIRECTORY)
    try:
        os.fsync(descriptor)
    finally:
        os.close(descriptor)


def atomic_file(path, content, uid, gid, mode):
    fd, temp = tempfile.mkstemp(prefix='.m090-', dir=str(path.parent))
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(content)
            stream.flush()
            os.fsync(stream.fileno())
        os.chown(temp, uid, gid)
        os.chmod(temp, mode)
        os.replace(temp, path)
        sync_dir(path.parent)
    finally:
        if os.path.exists(temp):
            os.unlink(temp)


def http_status(path):
    value = run('curl', '--silent', '--show-error', '--output', '/dev/null',
                '--write-out', '%{http_code}', '--max-time', '15',
                'http://127.0.0.1:18088' + path)
    return int(value)


def runtime_check(expected):
    for relative in TARGETS:
        path = ROOT / relative
        if current(path).get('sha256') != sha(expected[relative]):
            fail('TARGET_HASH_MISMATCH')
        run('docker', 'exec', CONTAINER, 'php', '-l', '/var/www/html/custom/' + relative)
    if http_status('/') not in (200, 302):
        fail('ERP_HEALTH')
    if http_status('/api/index.php/chinaplanetlogisticsapi/shipments') not in (401, 403):
        fail('CPL_API_HTTP')


def write_json(path, value):
    atomic_file(path, (json.dumps(value, sort_keys=True, indent=2) + '\n').encode(), 0, 0, 0o600)


def restore(backup_dir, manifest):
    for relative in reversed(TARGETS):
        path = ROOT / relative
        before = manifest['files'][relative]
        if before['exists']:
            backup = backup_dir / relative
            content = backup.read_bytes()
            if sha(content) != before['sha256']:
                fail('BACKUP_SHA_MISMATCH')
            if not path.parent.exists():
                path.parent.mkdir(mode=0o755, parents=True)
                os.chown(path.parent, 33, 33)
            atomic_file(path, content, before['uid'], before['gid'], before['mode'])
        elif path.exists() or path.is_symlink():
            path.unlink()
            sync_dir(path.parent)
    for relative in reversed(manifest['created_dirs']):
        path = ROOT / relative
        if path.is_dir() and not any(path.iterdir()):
            path.rmdir()


def apply():
    data = preflight()
    STATE_DIR.mkdir(mode=0o700, exist_ok=True)
    state_info = STATE_DIR.stat()
    if STATE_DIR.is_symlink() or state_info.st_uid != 0 or stat.S_IMODE(state_info.st_mode) != 0o700:
        fail('STATE_DIR_METADATA')
    with (STATE_DIR / 'lock').open('a+b') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        state_file = STATE_DIR / 'state.json'
        if state_file.exists() and json.loads(state_file.read_text()).get('status') == 'DEPLOYED':
            fail('ALREADY_DEPLOYED')
        stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
        backup_dir = STATE_DIR / ('backup-' + stamp)
        backup_dir.mkdir(mode=0o700)
        manifest = {'commit': COMMIT, 'artifact_sha256': ARTIFACT_SHA,
                    'files': {}, 'created_dirs': []}
        for relative in TARGETS:
            path = ROOT / relative
            before = current(path)
            manifest['files'][relative] = before
            if before['exists']:
                backup = backup_dir / relative
                backup.parent.mkdir(parents=True, exist_ok=True)
                atomic_file(backup, path.read_bytes(), 0, 0, 0o600)
        write_json(backup_dir / 'manifest.json', manifest)
        try:
            for relative in TARGETS:
                path = ROOT / relative
                missing = []
                parent = path.parent
                while not parent.exists() and parent != ROOT:
                    missing.append(parent)
                    parent = parent.parent
                for directory in reversed(missing):
                    directory.mkdir(mode=0o755)
                    os.chown(directory, 33, 33)
                    manifest['created_dirs'].append(str(directory.relative_to(ROOT)))
                atomic_file(path, data[relative], 33, 33, 0o644)
            write_json(backup_dir / 'manifest.json', manifest)
            runtime_check(data)
        except Exception:
            restore(backup_dir, manifest)
            write_json(state_file, {'status': 'ROLLED_BACK_AFTER_FAILURE',
                                    'commit': COMMIT, 'backup': str(backup_dir)})
            raise
        write_json(state_file, {'status': 'DEPLOYED', 'commit': COMMIT,
                                'artifact_sha256': ARTIFACT_SHA, 'backup': str(backup_dir),
                                'target_sha256': {name: sha(data[name]) for name in TARGETS}})
        print('M090_STAGING_APPLY=PASS BACKUP=' + str(backup_dir))


def verify():
    check_host()
    check_runtime()
    state = json.loads((STATE_DIR / 'state.json').read_text())
    if state.get('status') != 'DEPLOYED' or state.get('artifact_sha256') != ARTIFACT_SHA:
        fail('STAGING_STATE')
    runtime_check(load_artifact())
    print('M090_STAGING_VERIFY=PASS')


def rollback_locked():
    check_host()
    check_runtime()
    state_file = STATE_DIR / 'state.json'
    state = json.loads(state_file.read_text())
    if state.get('status') != 'DEPLOYED' or state.get('artifact_sha256') != ARTIFACT_SHA:
        fail('STAGING_STATE')
    backup_dir = Path(state['backup'])
    if backup_dir.parent != STATE_DIR or not backup_dir.name.startswith('backup-') or backup_dir.is_symlink():
        fail('BACKUP_PATH')
    manifest = json.loads((backup_dir / 'manifest.json').read_text())
    if manifest.get('commit') != COMMIT or manifest.get('artifact_sha256') != ARTIFACT_SHA or set(manifest['files']) != set(TARGETS):
        fail('BACKUP_MANIFEST')
    for relative in TARGETS:
        if current(ROOT / relative).get('sha256') != state['target_sha256'][relative]:
            fail('DEPLOYED_TARGET_DRIFT')
    reapply_dir = STATE_DIR / ('reapply-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
    reapply_dir.mkdir(mode=0o700)
    reapply = {'files': {}, 'created_dirs': []}
    for relative in TARGETS:
        path = ROOT / relative
        reapply['files'][relative] = current(path)
        saved = reapply_dir / relative
        saved.parent.mkdir(parents=True, exist_ok=True)
        atomic_file(saved, path.read_bytes(), 0, 0, 0o600)
    write_json(reapply_dir / 'manifest.json', reapply)
    try:
        restore(backup_dir, manifest)
        for relative in TARGETS:
            before = manifest['files'][relative]
            if current(ROOT / relative) != before:
                fail('ROLLBACK_TARGET_MISMATCH')
            if before['exists']:
                run('docker', 'exec', CONTAINER, 'php', '-l', '/var/www/html/custom/' + relative)
        if http_status('/') not in (200, 302):
            fail('ROLLBACK_ERP_HEALTH')
    except Exception:
        restore(reapply_dir, reapply)
        raise
    write_json(state_file, {'status': 'ROLLED_BACK', 'commit': COMMIT,
                            'artifact_sha256': ARTIFACT_SHA, 'backup': str(backup_dir),
                            'reapply_backup': str(reapply_dir)})
    print('M090_STAGING_ROLLBACK=PASS BACKUP=' + str(backup_dir))


def rollback():
    check_host()
    if STATE_DIR.is_symlink() or not STATE_DIR.is_dir():
        fail('STATE_DIR_METADATA')
    state_info = STATE_DIR.stat()
    if state_info.st_uid != 0 or stat.S_IMODE(state_info.st_mode) != 0o700:
        fail('STATE_DIR_METADATA')
    with (STATE_DIR / 'lock').open('a+b') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        rollback_locked()


if __name__ == '__main__':
    try:
        if len(sys.argv) != 2 or sys.argv[1] not in ('preflight', 'apply', 'verify', 'rollback'):
            fail('ARGUMENTS')
        {'preflight': preflight, 'apply': apply, 'verify': verify, 'rollback': rollback}[sys.argv[1]]()
    except Exception as error:
        print('M090_STAGING=FAIL ' + str(error), file=sys.stderr)
        sys.exit(1)
