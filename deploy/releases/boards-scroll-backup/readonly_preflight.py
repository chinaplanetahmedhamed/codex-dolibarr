#!/usr/bin/env python3
"""Fixed backup-ERP source snapshot. No files, configuration, database or service writes.

Run only through an already authorized administrative session after the shared
backup deployment queue is idle. stdout contains hashes and selected metadata, never source/configuration
contents or runtime records. This is not a deployment helper or a substitute for CI gates.
"""
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import sys

APP = 'erp-staging-dolibarr-1'
DB = 'erp-staging-mariadb-1'
MAIL = 'erp-staging-mailpit-1'
ROOT = Path('/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data/chinaplanetoperations')
FILES = ('tasks/boards.php', 'tasks/card.php', 'js/boards.js', 'js/card_board.js',
         'js/card_operation.js', 'css/boards.css', 'css/card_board.css', 'js/boards_geometry.js')
ENV = {'PATH': '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'}


def run(args):
    p = subprocess.run(args, cwd='/', env=ENV, capture_output=True, timeout=20)
    if p.returncode or len(p.stdout) > 131072:
        raise RuntimeError('Fixed read-only runtime command failed; details withheld.')
    return p.stdout


def selected(name):
    # Deliberately exclude environment, command, logs and other containers.
    fields = '{"name":{{json .Name}},"running":{{json .State.Running}},"image":{{json .Image}},"project":{{json (index .Config.Labels "com.docker.compose.project")}},"service":{{json (index .Config.Labels "com.docker.compose.service")}},"ports":{{json .NetworkSettings.Ports}},"mounts":{{json .Mounts}}}'
    item = json.loads(run(['docker', '--host', 'unix:///var/run/docker.sock', 'inspect', '--format', fields, name]))
    # Docker builds this array from a map; order is not a resource identity.
    item['mounts'] = sorted(item['mounts'], key=lambda m: m['Destination'])
    return item


def validate_resources(app, database, mail):
    for item, name, service in ((app, APP, 'dolibarr'), (database, DB, 'mariadb'), (mail, MAIL, 'mailpit')):
        if item['name'] != '/' + name or item['running'] is not True or item['project'] != 'erp-staging' or item['service'] != service:
            raise RuntimeError('Canonical backup resource identity differs.')
    if app['ports'] != {'80/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18088'}]}:
        raise RuntimeError('Canonical backup HTTP binding differs.')
    expected_mail = {'1025/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '11025'}],
                     '8025/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18025'}]}
    published_mail = {key: value for key, value in mail['ports'].items() if value}
    if any(database['ports'].values()) or published_mail != expected_mail:
        raise RuntimeError('Canonical backup database/mail exposure differs.')
    mounts = {m['Destination']: m for m in app['mounts']}
    for target, volume in (('/var/www/html/custom', 'erp-staging_erp-staging_custom'), ('/var/www/documents', 'erp-staging_erp-staging_docs')):
        m = mounts.get(target, {})
        if m.get('Type') != 'volume' or m.get('Name') != volume or m.get('Source') != '/var/lib/docker/volumes/' + volume + '/_data' or m.get('RW') is not True:
            raise RuntimeError('Canonical backup mount differs.')


def source(rel, root=ROOT):
    if rel not in FILES:
        raise RuntimeError('Source path is outside the fixed allowlist.')
    # Walk using directory descriptors; never follow a substituted parent/file.
    fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY)
    try:
        for part in root.parts[1:] + tuple(rel.split('/')[:-1]):
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd)
            fd = child
        try:
            leaf = os.open(rel.split('/')[-1], os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=fd)
        except FileNotFoundError:
            if rel == FILES[-1]:
                return None
            raise RuntimeError('An existing Boards source file is missing.') from None
        try:
            s = os.fstat(leaf)
            if not stat.S_ISREG(s.st_mode) or s.st_nlink != 1 or s.st_size > 2 * 1024 * 1024:
                raise RuntimeError('Source must be one bounded ordinary file.')
            raw = b''
            while len(raw) <= 2 * 1024 * 1024:
                block = os.read(leaf, 65536)
                if not block:
                    break
                raw += block
            after = os.fstat(leaf)
            if len(raw) != s.st_size or (s.st_mtime_ns, s.st_ctime_ns) != (after.st_mtime_ns, after.st_ctime_ns):
                raise RuntimeError('Source changed during snapshot.')
            return {'sha256': hashlib.sha256(raw).hexdigest(), 'size': len(raw), 'uid': s.st_uid,
                    'gid': s.st_gid, 'mode': format(stat.S_IMODE(s.st_mode), '04o'),
                    'inode': s.st_ino, 'mtime_ns': s.st_mtime_ns}
        finally:
            os.close(leaf)
    finally:
        os.close(fd)


def main():
    if len(sys.argv) != 1 or os.geteuid() != 0 or os.uname().nodename != 'srv1011043':
        raise RuntimeError('No arguments; authorized administrative session on srv1011043 required.')
    before = [selected(n) for n in (APP, DB, MAIL)]
    validate_resources(*before)
    files = {rel: source(rel) for rel in FILES}
    again = {rel: source(rel) for rel in FILES}
    after = [selected(n) for n in (APP, DB, MAIL)]
    validate_resources(*after)
    if files != again or before != after:
        raise RuntimeError('Backup source/runtime changed; snapshot is not stable.')
    print(json.dumps({'schema': 1, 'result': 'BACKUP_BOARDS_READONLY_SNAPSHOT',
                      'timestamp': datetime.now(timezone.utc).isoformat(), 'host': 'srv1011043',
                      'resources': before, 'files': files,
                      'writes': False, 'deployment_authorized_by_snapshot': False}))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(json.dumps({'result': 'BLOCKED', 'reason': str(error) if isinstance(error, RuntimeError) else type(error).__name__, 'writes': False}), file=sys.stderr)
        sys.exit(1)
