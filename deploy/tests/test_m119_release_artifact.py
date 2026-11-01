#!/usr/bin/env python3
"""Immutable package boundaries; no server, database, credential or network access."""
import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.dont_write_bytecode = True
BUILDER = ROOT / 'deploy/releases/m119/build_artifact.py'


class ArtifactTests(unittest.TestCase):
    def setUp(self):
        self.assertTrue(BUILDER.is_file(), 'M119 immutable artifact builder is missing')
        spec = importlib.util.spec_from_file_location('m119_artifact', BUILDER)
        self.builder = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(self.builder)
        self.commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()

    def test_repeat_builds_have_identical_bytes_and_provenance(self):
        with tempfile.TemporaryDirectory(prefix='m119-artifact-') as owned:
            a = self.builder.build(self.commit, Path(owned) / 'a', ROOT)
            b = self.builder.build(self.commit, Path(owned) / 'b', ROOT)
            self.assertEqual(a['manifest'], b['manifest'])
            self.assertEqual(Path(a['artifact']).read_bytes(), Path(b['artifact']).read_bytes())
            self.assertEqual(a['manifest']['commit'], self.commit)
            self.assertEqual(hashlib.sha256(Path(a['artifact']).read_bytes()).hexdigest(), a['manifest']['artifact_sha256'])

    def test_archive_contains_only_complete_runtime_and_private_release_support(self):
        with tempfile.TemporaryDirectory(prefix='m119-artifact-') as owned:
            result = self.builder.build(self.commit, Path(owned), ROOT)
            with tarfile.open(result['artifact'], 'r:gz') as archive:
                members = archive.getmembers()
                self.assertEqual({m.name for m in members}, {f['path'] for f in result['manifest']['files']})
                for member in members:
                    self.assertTrue(member.isfile())
                    self.assertEqual(member.mode, 0o644)
                    self.assertEqual(member.mtime, 0)
                    self.assertNotIn('LocalNativeDraftWriter', member.name)
                    self.assertNotIn('NativeDraftBridge', member.name)
                    self.assertNotIn('/tests/', member.name)
                    self.assertNotIn('..', member.name.split('/'))
                    self.assertFalse(member.name.endswith(('.db', '.sqlite', '.env', '.key')))
                    record = next(f for f in result['manifest']['files'] if f['path'] == member.name)
                    self.assertEqual(hashlib.sha256(archive.extractfile(member).read()).hexdigest(), record['sha256'])
                release = json.load(archive.extractfile('support/release.json'))
                self.assertEqual(release['source_commit'], self.commit)
                self.assertIs(release['financial_posting'], False)

    def test_unpinned_source_is_rejected_before_packaging(self):
        with tempfile.TemporaryDirectory(prefix='m119-artifact-') as owned:
            for ref in ('HEAD', 'main', '../main', self.commit + '\n'):
                with self.assertRaises(ValueError):
                    self.builder.build(ref, Path(owned), ROOT)
            self.assertEqual(list(Path(owned).iterdir()), [])

    def test_git_symlink_cannot_import_outside_source(self):
        with tempfile.TemporaryDirectory(prefix='m119-artifact-symlink-') as owned:
            repo = Path(owned) / 'repo'
            repo.mkdir()
            subprocess.run(['git', 'init', '-q'], cwd=repo, check=True)
            for path in self.builder.SOURCE_FILES:
                p = repo / path
                p.parent.mkdir(parents=True, exist_ok=True)
                p.write_text('synthetic fixture')
            target = repo / self.builder.SOURCE_FILES[0]
            target.unlink()
            target.symlink_to('/etc/passwd')
            subprocess.run(['git', 'add', '.'], cwd=repo, check=True)
            subprocess.run(['git', '-c', 'user.name=Synthetic', '-c', 'user.email=synthetic@example.invalid', 'commit', '-qm', 'synthetic artifact boundary'], cwd=repo, check=True)
            commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repo, text=True).strip()
            with self.assertRaises(ValueError):
                self.builder.build(commit, Path(owned) / 'output', repo)
            self.assertFalse((Path(owned) / 'output').exists())


if __name__ == '__main__':
    unittest.main()
