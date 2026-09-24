#!/usr/bin/env python3
"""Local failure-path checks for the M090 three-file production release helper."""

import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest import mock


HELPER = Path(__file__).resolve().parents[1] / 'tools/m090_production_api_release.py'
spec = importlib.util.spec_from_file_location('m090_production_api_release', HELPER)
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ProductionReleaseTests(unittest.TestCase):
    def test_preflight_rejects_any_production_file_drift(self):
        known = {name: {'exists': True, 'sha256': digest, 'uid': 33, 'gid': 33,
                        'mode': 0o644} for name, digest in release.BASELINE_SHA.items()}
        with mock.patch.object(release, 'ROOT', Path('/fixed-test-root')), \
                mock.patch.object(release, 'current', side_effect=lambda path: known[str(path).split('/fixed-test-root/', 1)[1]]):
            release.check_baseline()
            known[release.TARGETS[1]]['sha256'] = '0' * 64
            with self.assertRaisesRegex(RuntimeError, 'PRODUCTION_BASELINE_DRIFT'):
                release.check_baseline()

    def test_artifact_contains_only_expected_file_payloads(self):
        artifact = Path('/private/tmp/chinaplanet-m090-release/chinaplanetlogistics-api-abbc485.tar.gz')
        if not artifact.is_file():
            self.skipTest('Local staging-tested artifact unavailable')
        with mock.patch.object(release, 'ARTIFACT', artifact), \
                mock.patch.object(release.pwd, 'getpwnam', return_value=type('User', (), {'pw_uid': artifact.stat().st_uid})()):
            payloads = release.load_artifact()
        self.assertEqual(set(payloads), set(release.TARGETS))
        self.assertTrue(all(payloads[name].startswith(b'<?php') for name in release.TARGETS))

    def test_runtime_failure_restores_all_existing_production_files(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / 'custom'
            root.mkdir()
            state = Path(temporary) / 'state'
            before = {}
            candidate = {}
            for index, relative in enumerate(release.TARGETS):
                path = root / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                original = ('baseline-' + str(index)).encode()
                path.write_bytes(original)
                path.chmod(0o644)
                before[relative] = original
                candidate[relative] = ('candidate-' + str(index)).encode()

            with mock.patch.object(release, 'ROOT', root), \
                    mock.patch.object(release, 'STATE_DIR', state), \
                    mock.patch.object(release, 'preflight', return_value=candidate), \
                    mock.patch.object(release, 'check_state_dir'), \
                    mock.patch.object(release, 'runtime_check', side_effect=RuntimeError('simulated validation failure')), \
                    mock.patch.object(release.os, 'chown'):
                with self.assertRaisesRegex(RuntimeError, 'simulated validation failure'):
                    release.apply()
                self.assertEqual(json.loads((state / 'state.json').read_text())['status'],
                                 'ROLLED_BACK_AFTER_FAILURE')
                for relative in release.TARGETS:
                    self.assertEqual((root / relative).read_bytes(), before[relative])
                    self.assertEqual(release.current(root / relative)['mode'], 0o644)


if __name__ == '__main__':
    unittest.main()
