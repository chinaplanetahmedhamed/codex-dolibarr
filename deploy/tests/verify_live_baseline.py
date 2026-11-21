#!/usr/bin/env python3
"""Verify the captured deployed application bytes without database/configuration access."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--ref', help='Verify Git objects at this revision instead of checkout bytes')
args = parser.parse_args()
repo = Path(subprocess.check_output(['git', 'rev-parse', '--show-toplevel'], text=True).strip())
manifest_path = 'deploy/live-baseline/source-manifest.json'
if args.ref:
    manifest = json.loads(subprocess.check_output(['git', 'show', args.ref + ':' + manifest_path]))
    listing = subprocess.check_output(['git', 'ls-tree', '-r', '-z', args.ref, '--', 'custom', 'htdocs'])
    tracked = {}
    for entry in listing.split(b'\0'):
        if entry:
            meta, path = entry.split(b'\t', 1)
            mode, kind, oid = meta.decode().split()
            tracked[path.decode()] = (mode, kind, oid)
    reader = subprocess.Popen(['git', 'cat-file', '--batch'], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
    def read_object(path):
        reader.stdin.write((tracked[path][2] + '\n').encode())
        reader.stdin.flush()
        _, kind, length = reader.stdout.readline().decode().split()
        if kind != 'blob':
            raise ValueError('Expected an application blob: ' + path)
        content = reader.stdout.read(int(length))
        if reader.stdout.read(1) != b'\n':
            raise ValueError('Invalid Git object stream')
        return content
else:
    manifest = json.loads((repo / manifest_path).read_text())
    tracked = {p.decode(): None for p in subprocess.check_output(['git', 'ls-files', '-z', '--', 'custom', 'htdocs']).split(b'\0') if p}
    def read_object(path):
        target = repo / path
        if target.is_symlink():
            return target.readlink().as_posix().encode()
        return target.read_bytes()

if manifest['version'] != '24.0.1' or len(manifest['files']) != 16560:
    raise ValueError('Unexpected captured distribution')
expected = {}
for item in manifest['files']:
    source = item['path']
    if not source.startswith('htdocs/') or '..' in Path(source).parts:
        raise ValueError('Unsafe manifest path')
    path = source.removeprefix('htdocs/') if source.startswith('htdocs/custom/') else source
    if path in expected or source == 'htdocs/conf/conf.php' or Path(path).name == '.env':
        raise ValueError('Duplicate or private configuration path')
    expected[path] = item
if set(tracked) != set(expected) | {'htdocs/custom'}:
    raise ValueError('Tracked application inventory differs from the capture')
if read_object('htdocs/custom') != b'../custom':
    raise ValueError('Custom module link differs from the relative repository layout')
for path, item in expected.items():
    content = read_object(path)
    if len(content) != item['size'] or hashlib.sha256(content).hexdigest() != item['sha256']:
        raise ValueError('Application bytes differ: ' + path)
if args.ref:
    reader.stdin.close()
    if reader.wait() != 0:
        raise ValueError('Git object reader failed')
print('PASS: 16,560 captured application files and relative custom-module link')
