#!/usr/bin/env python3
"""Fixed M119 allowlist from an immutable Git commit; no worktree/server data."""
import argparse
import gzip
import hashlib
import io
import json
from pathlib import Path
import re
import subprocess
import tarfile

MODULE = 'custom/chinaplanetoperations/office_expenses/'
RUNTIME_FILES = tuple(MODULE + p for p in (
    'index.php', 'employee.css', 'employee.js',
    'assets/Cairo-OFL.txt', 'assets/Cairo.ttf', 'assets/Font-Awesome-LICENSE.txt',
    'assets/fa-solid-900.woff2', 'assets/fontawesome-solid.css', 'assets/fontawesome.css',
    'class/OfficeExpenseDraftService.php', 'class/OfficeExpenseEmployeeController.php',
    'class/OfficeExpenseEmployeePage.php', 'class/OfficeExpenseMoney.php',
    'class/OfficeExpenseNativeConnection.php', 'class/OfficeExpenseNativeDraftStore.php',
    'class/OfficeExpenseNativeReceiptStore.php', 'class/OfficeExpenseNativeSchemaMigration.php',
    'class/OfficeExpenseNativeStorageFactory.php', 'class/OfficeExpensePilotConfiguration.php',
    'class/OfficeExpenseReceiptHttp.php', 'class/OfficeExpenseReceiptPanel.php',
    'class/OfficeExpenseReceiptService.php',
    'schema/001_drafts.sql', 'schema/002_draft_audit.sql', 'schema/003_schema_state.sql',
    'schema/manifest.json', 'schema/receipts/001_receipts.sql',
    'schema/receipts/002_receipt_audit.sql', 'schema/receipts/manifest.json',
))
WORKER = 'deploy/releases/m119/native_operations.php'
SOURCE_FILES = RUNTIME_FILES + (WORKER,)


def canonical(value):
    return (json.dumps(value, sort_keys=True, indent=2, ensure_ascii=True) + '\n').encode()


def git(repo, *args):
    return subprocess.check_output(['git', *args], cwd=repo, stderr=subprocess.DEVNULL)


def build(commit, output, repo=None, *, runtime_files=None, source_files=None, release_metadata=None):
    repo = Path(repo or Path(__file__).resolve().parents[3])
    runtime_files = RUNTIME_FILES if runtime_files is None else runtime_files
    source_files = SOURCE_FILES if source_files is None else source_files
    if not isinstance(commit, str) or re.fullmatch('[a-f0-9]{40}', commit) is None:
        raise ValueError('Use a full immutable commit, never a branch or unpinned worktree.')
    if git(repo, 'cat-file', '-t', commit).strip() != b'commit':
        raise ValueError('Source is not a Git commit.')
    payload = {}
    for path in source_files:
        tree = git(repo, 'ls-tree', commit, '--', path).decode().strip()
        if not tree or '\t' not in tree:
            raise ValueError('Required immutable release source is missing.')
        meta, actual = tree.split('\t', 1)
        if actual != path or meta.split()[0:2] != ['100644', 'blob']:
            raise ValueError('Only ordinary non-executable source files are allowed.')
        data = git(repo, 'show', commit + ':' + path)
        if b'CPO_OFFICEEXPENSES_COMPATIBILITY_SHIM' in data:
            raise ValueError('The standalone module needs a new complete release boundary; the legacy M119 package cannot contain relocation shims.')
        if not data or len(data) > 2 * 1024 * 1024:
            raise ValueError('Release source size differs from its bounded contract.')
        name = path if path in runtime_files else 'support/native_operations.php'
        payload[name] = (data, 'runtime' if path in runtime_files else 'support')
    payload['support/release.json'] = (canonical({'schema': 1, 'mission': 'M119', 'source_commit': commit, 'financial_posting': False, **(release_metadata or {})}), 'support')
    if sum(len(p[0]) for p in payload.values()) > 4 * 1024 * 1024:
        raise ValueError('Release exceeds bounded package size.')
    manifest_files = []
    compressed = io.BytesIO()
    with gzip.GzipFile(filename='', mode='wb', fileobj=compressed, mtime=0, compresslevel=9) as gz:
        with tarfile.open(fileobj=gz, mode='w', format=tarfile.GNU_FORMAT) as archive:
            for name, (data, kind) in sorted(payload.items()):
                info = tarfile.TarInfo(name)
                info.size, info.mode, info.mtime = len(data), 0o644, 0
                info.uid, info.gid, info.uname, info.gname = 0, 0, '', ''
                archive.addfile(info, io.BytesIO(data))
                manifest_files.append({'path': name, 'kind': kind, 'sha256': hashlib.sha256(data).hexdigest(), 'size': len(data), 'mode': '0644'})
    data = compressed.getvalue()
    digest = hashlib.sha256(data).hexdigest()
    manifest = {'schema': 1, 'mission': 'M119', 'commit': commit, 'artifact_name': 'artifact-' + digest + '.tar.gz', 'artifact_sha256': digest, 'files': manifest_files}
    output = Path(output)
    output.mkdir(mode=0o700, parents=True, exist_ok=True)
    artifact = output / manifest['artifact_name']
    manifest_path = output / ('manifest-' + digest + '.json')
    for path, content in ((artifact, data), (manifest_path, canonical(manifest))):
        if path.exists():
            if path.is_symlink() or path.read_bytes() != content:
                raise ValueError('Existing release artifact differs; no overwrite allowed.')
        else:
            with path.open('xb') as handle:
                handle.write(content)
            path.chmod(0o600)
    return {'artifact': str(artifact), 'manifest_path': str(manifest_path), 'manifest': manifest}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    result = build(args.commit, Path(args.output))
    print(json.dumps({'artifact': result['artifact'], 'manifest': result['manifest_path'], 'sha256': result['manifest']['artifact_sha256'], 'commit': result['manifest']['commit'], 'files': len(result['manifest']['files'])}))
