#!/usr/bin/env python3
"""Dependency closure and reproducibility of the standalone immutable code contract."""
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('standalone', ROOT / 'deploy/releases/m119/build_standalone_artifact.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class StandaloneArtifactTests(unittest.TestCase):
    def fixture(self, root):
        repo = root / 'repo'
        repo.mkdir()
        subprocess.run(['git', 'init', '-q'], cwd=repo, check=True)
        for relative in builder.FINANCE_RUNTIME_FILES:
            target = repo / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes((ROOT / relative).read_bytes())
        return repo

    def commit(self, repo):
        subprocess.run(['git', 'add', '.'], cwd=repo, check=True)
        subprocess.run(['git', '-c', 'user.name=Synthetic', '-c', 'user.email=synthetic@example.invalid', 'commit', '-qm', 'synthetic package'], cwd=repo, check=True)
        return subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repo, text=True).strip()

    def test_package_closes_local_dependencies_and_schema_hashes(self):
        paths = set(builder.FINANCE_RUNTIME_FILES)
        for path in paths:
            if path.endswith('.php'):
                for relative in re.findall(r"__DIR__\s*\.\s*'([^']+)'", (ROOT / path).read_text()):
                    required = (ROOT / path).parent / relative.lstrip('/')
                    self.assertIn(required.resolve().relative_to(ROOT).as_posix(), paths)
        for folder in ('schema', 'schema/receipts', 'schema/access', 'schema/workspace'):
            manifest = json.loads((ROOT / builder.MODULE / folder / 'manifest.json').read_text())
            for name, expected in manifest['files'].items():
                path = builder.MODULE + folder + '/' + name
                self.assertIn(path, paths)
                self.assertEqual(hashlib.sha256((ROOT / path).read_bytes()).hexdigest(), expected)
        with tempfile.TemporaryDirectory(prefix='m119-standalone-') as tmp:
            root = Path(tmp)
            repo = self.fixture(root)
            commit = self.commit(repo)
            first = builder.build(commit, root / 'a', repo)
            second = builder.build(commit, root / 'b', repo)
            self.assertEqual(Path(first['artifact']).read_bytes(), Path(second['artifact']).read_bytes())
            with tarfile.open(first['artifact']) as archive:
                self.assertEqual(set(archive.getnames()), paths | {'support/release.json'})
                release = json.load(archive.extractfile('support/release.json'))
                self.assertFalse(release['financial_posting'])
                self.assertFalse(release['staff_activation'])
                self.assertFalse(release['installer_included'])
                for item in archive.getmembers():
                    self.assertTrue(item.isfile())
                    self.assertEqual(item.mode, 0o644)
                    self.assertNotIn('NativeDraftBridge', item.name)

    def test_missing_access_dependency_and_symlink_fail_before_output(self):
        for change in ('missing', 'symlink'):
            with self.subTest(change=change), tempfile.TemporaryDirectory(prefix='m119-standalone-') as tmp:
                root = Path(tmp)
                repo = self.fixture(root)
                target = repo / builder.MODULE / 'schema/access/001_membership.sql'
                target.unlink()
                if change == 'symlink':
                    target.symlink_to('/etc/passwd')
                commit = self.commit(repo)
                with self.assertRaises(ValueError):
                    builder.build(commit, root / 'output', repo)
                self.assertFalse((root / 'output').exists())


if __name__ == '__main__':
    unittest.main()
