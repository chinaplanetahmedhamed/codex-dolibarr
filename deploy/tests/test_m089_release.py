#!/usr/bin/env python3
"""Exercise fixed release integrity and recovery locally; never contacts a server."""
import base64
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

REPO = Path(__file__).resolve().parents[2]
VARIANT = os.environ.get('M089_RELEASE_VARIANT', 'm089-c02-r2')


def load(env):
    spec = importlib.util.spec_from_file_location('m089_' + env, REPO / 'deploy/releases' / VARIANT / (env + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class Integrity(unittest.TestCase):
    def test_both_payloads_match_reviewed_commit(self):
        for env in ('staging', 'production'):
            m = load(env)
            m.validate_payload()
            self.assertEqual(len(m.PAYLOAD['files']), 11 if VARIANT == 'm089-c02-r3' else 10)
            self.assertEqual(len(m.PAYLOAD['prerequisites']), 12)
            for name, item in m.PAYLOAD['files'].items():
                source = 'custom/' + name
                overlay = 'deploy/releases/m089-staging-overlay/' + source
                if env == 'staging' and (REPO / overlay).exists():
                    source = overlay
                data = subprocess.check_output(['git', 'show', m.PAYLOAD['commit'] + ':' + source], cwd=REPO)
                self.assertEqual(data, base64.b64decode(item['data']))
                self.assertEqual(hashlib.sha256(data).hexdigest(), item['sha256'])

    def test_shared_authorization_is_identical(self):
        a, b = load('staging').PAYLOAD, load('production').PAYLOAD
        for name in a['files']:
            if '/class/' in name or '/js/' in name or name.endswith('/tasks/board.php'):
                self.assertEqual(a['files'][name]['sha256'], b['files'][name]['sha256'])

    def test_payload_tampering_rejected(self):
        m = load('staging')
        next(iter(m.PAYLOAD['files'].values()))['data'] = base64.b64encode(b'tampered').decode()
        with self.assertRaisesRegex(RuntimeError, 'PAYLOAD_CHECKSUM'):
            m.validate_payload()


class Recovery(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory(prefix='m089-release-test-')
        self.m = m = load('staging')
        m.ROOT = Path(self.tmp.name).resolve() / 'custom'
        m.STATE = Path(self.tmp.name).resolve() / 'state'
        m.ROOT.mkdir(); m.STATE.mkdir(mode=0o700)
        m.PAYLOAD = copy.deepcopy(m.PAYLOAD)
        self.before = {}
        for name, item in m.PAYLOAD['files'].items():
            data = ('baseline:' + name).encode()
            self.before[name] = data
            p = m.ROOT / name
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_bytes(data); p.chmod(0o644)
            item.update(before=m.digest(data), uid=os.getuid(), gid=os.getgid(), mode=0o644)
        for name in m.PAYLOAD['prerequisites']:
            p = m.ROOT / name
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_bytes(b'dependency')
            m.PAYLOAD['prerequisites'][name] = m.digest(b'dependency')
        self.patches = [patch.object(m, 'environment'), patch.object(m, 'schema'),
                        patch.object(m, 'lint'), patch.object(m, 'health'),
                        patch.object(m, 'state_directory_valid', return_value=True),
                        patch.object(m.os, 'geteuid', return_value=0),
                        patch.object(m.os, 'fchown')]
        for p in self.patches: p.start()

    def tearDown(self):
        for p in reversed(self.patches): p.stop()
        self.tmp.cleanup()

    def action(self, action):
        with patch.object(sys, 'argv', ['helper', action]):
            self.m.main()

    def unchanged(self):
        for name, data in self.before.items():
            self.assertEqual((self.m.ROOT / name).read_bytes(), data)

    def test_apply_verify_rollback_preserves_bytes(self):
        self.action('apply'); self.action('verify')
        self.assertEqual(self.m.load_state()['status'], 'DEPLOYED')
        self.action('rollback'); self.unchanged()
        self.assertEqual(self.m.load_state()['status'], 'ROLLED_BACK')

    def test_replay_rejected(self):
        self.action('apply')
        with self.assertRaisesRegex(RuntimeError, 'REPLAY_REFUSED'): self.action('apply')

    def test_baseline_drift_stops_before_backup_or_mutation(self):
        name = next(iter(self.before)); (self.m.ROOT / name).write_bytes(b'drift')
        with self.assertRaisesRegex(RuntimeError, 'BASELINE_MISMATCH'): self.action('apply')
        self.assertFalse((self.m.STATE / 'before.json').exists())

    def test_permissions_drift_stops(self):
        (self.m.ROOT / next(iter(self.before))).chmod(0o666)
        with self.assertRaisesRegex(RuntimeError, 'FILE_METADATA_MISMATCH'): self.action('apply')
        self.unchanged()

    def test_symlink_and_hardlink_rejected(self):
        p = self.m.ROOT / next(iter(self.before)); p.unlink()
        target = Path(self.tmp.name).resolve() / 'outside'; target.write_bytes(b'protected')
        p.symlink_to(target)
        with self.assertRaises((RuntimeError, OSError)): self.action('apply')
        p.unlink(); os.link(target, p)
        with self.assertRaisesRegex(RuntimeError, 'UNSAFE_FILE'): self.action('apply')
        self.assertEqual(target.read_bytes(), b'protected')

    def test_failed_post_apply_health_restores_every_file(self):
        self.m.health.side_effect = [None, RuntimeError('health-failure'), None]
        with self.assertRaisesRegex(RuntimeError, 'health-failure'): self.action('apply')
        self.unchanged()
        self.assertEqual(self.m.load_state()['status'], 'AUTO_ROLLED_BACK')

    def test_failed_recovery_is_recorded(self):
        self.m.health.side_effect = [None, RuntimeError('health-failure'), RuntimeError('still-unhealthy')]
        with self.assertRaisesRegex(RuntimeError, 'AUTO_ROLLBACK_FAILED'): self.action('apply')
        self.unchanged()
        self.assertEqual(self.m.load_state()['status'], 'RECOVERY_REQUIRED')

    def test_backup_tampering_refuses_rollback(self):
        self.action('apply')
        (self.m.STATE / 'before.json').write_text('{}')
        with self.assertRaisesRegex(RuntimeError, 'BACKUP_CHECKSUM'): self.action('rollback')
        self.m.compare(True)

    def test_post_deploy_drift_refuses_rollback(self):
        self.action('apply'); (self.m.ROOT / next(iter(self.before))).write_bytes(b'newer-release')
        with self.assertRaisesRegex(RuntimeError, 'ROLLBACK_DRIFT'): self.action('rollback')

    def test_failed_rollback_restores_deployed_candidate(self):
        self.action('apply')
        self.m.health.side_effect = [RuntimeError('rollback-health'), None]
        with self.assertRaisesRegex(RuntimeError, 'rollback-health'): self.action('rollback')
        self.m.compare(True)

    def test_extra_argument_refused(self):
        with patch.object(sys, 'argv', ['helper', 'apply', '--force']):
            with self.assertRaisesRegex(RuntimeError, 'EXACT_ROOT_ACTION_REQUIRED'): self.m.main()
        self.unchanged()


if __name__ == '__main__':
    unittest.main()
