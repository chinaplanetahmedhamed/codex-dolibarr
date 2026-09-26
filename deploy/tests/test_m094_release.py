#!/usr/bin/env python3
"""Local integrity and rollback checks for the production-only M094 release."""
import base64, importlib.util, json, os, subprocess, sys, tempfile, unittest
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[2]
ARTIFACT=Path(os.environ.get('M094_RELEASE_DIR','/private/tmp/m094-artifact'))
def load():
    s=importlib.util.spec_from_file_location('m094',ROOT/'deploy/releases/m094/deploy.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m);m.INBOX=ARTIFACT;return m
class Release(unittest.TestCase):
    def setUp(self):
        self.m=load(); self.temp=tempfile.TemporaryDirectory(); self.root=Path(self.temp.name)/'custom';self.root.mkdir()
        self.m.STATE=Path(self.temp.name)/'state'; self.manifest,self.payload=self.m.load_payload()
        self.before={}
        for name,digest in {**self.manifest['before']['production'],**self.manifest['prerequisites']}.items():
            if digest is None:continue
            data=subprocess.check_output(['git','show',self.manifest['baseline_commit']+':custom/'+name],cwd=ROOT)
            p=self.root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data);p.chmod(0o644);self.before[name]=data
        for name in self.payload:(self.root/name).parent.mkdir(parents=True,exist_ok=True)
        self.patches=[patch.object(self.m,'target',return_value=('web','db',self.root)),patch.object(self.m,'readiness'),patch.object(self.m,'health'),patch.object(self.m,'controls'),patch.object(self.m,'upload_config'),patch.object(self.m,'report'),patch.object(self.m,'command'),patch.object(self.m.os,'geteuid',return_value=0),patch.object(self.m.os,'fchown')]
        self.patches.append(patch.object(self.m,'METADATA_OVERRIDES',{}))
        self.patches.append(patch.object(self.m,'ALLOWED_OWNERS',(os.getuid(),)))
        self.patches.append(patch.object(self.m,'ALLOWED_GROUPS',(os.getgid(),)))
        self.patches.append(patch.object(self.m,'backup_db',side_effect=lambda db,p:p.write_bytes(b'synthetic-backup')))
        for p in self.patches:p.start()
    def tearDown(self):
        for p in reversed(self.patches):p.stop()
        self.temp.cleanup()
    def action(self,action):
        with patch.object(sys,'argv',['helper','production',action]):self.m.main()
    def unchanged(self):
        for name,data in self.before.items():self.assertEqual((self.root/name).read_bytes(),data)
        for name,value in self.manifest['before']['production'].items():
            if value is None:self.assertFalse((self.root/name).exists())
    def test_exact_committed_payload(self):
        self.assertEqual(len(self.payload),19);self.assertEqual(set(self.m.TARGETS),{'production'})
        for name,data in self.payload.items():self.assertEqual(data,subprocess.check_output(['git','show',self.manifest['source_commit']+':custom/'+name],cwd=ROOT))
    def test_apply_verify_rollback(self):
        self.action('preflight');self.action('apply');self.action('verify');self.action('rollback');self.unchanged()
    def test_tampered_payload(self):
        other=Path(self.temp.name)/'inbox';other.mkdir()
        (other/'m094-manifest.json').write_bytes((ARTIFACT/'m094-manifest.json').read_bytes());(other/'m094-payload.tar.gz').write_bytes(b'tampered');self.m.INBOX=other
        with self.assertRaisesRegex(RuntimeError,'ARTIFACT_HASH'):self.action('apply')
        self.unchanged()
    def test_baseline_drift_stops_before_backup(self):
        (self.root/next(iter(self.before))).write_bytes(b'drift')
        with self.assertRaisesRegex(RuntimeError,'FILE_DRIFT'):self.action('apply')
        self.m.backup_db.assert_not_called()
    def test_dependency_drift_stops(self):
        (self.root/next(iter(self.manifest['prerequisites']))).write_bytes(b'drift')
        with self.assertRaisesRegex(RuntimeError,'FILE_DRIFT'):self.action('apply')
        self.m.backup_db.assert_not_called()
    def test_permissions_drift_stops(self):
        (self.root/next(iter(self.before))).chmod(0o666)
        with self.assertRaisesRegex(RuntimeError,'FILE_METADATA'):self.action('apply')
        self.m.backup_db.assert_not_called()
    def test_symlink_stops(self):
        p=self.root/next(iter(self.before));p.unlink();p.symlink_to('/etc/hosts')
        with self.assertRaisesRegex(RuntimeError,'UNSAFE_TARGET_FILE'):self.action('apply')
    def test_backup_failure_never_changes_code(self):
        self.m.backup_db.side_effect=RuntimeError('backup-failure')
        with self.assertRaisesRegex(RuntimeError,'backup-failure'):self.action('apply')
        self.unchanged()
    def test_controls_failure_rolls_back(self):
        self.m.controls.side_effect=RuntimeError('controls-failure')
        with self.assertRaisesRegex(RuntimeError,'controls-failure'):self.action('apply')
        self.unchanged();self.assertEqual(json.loads((self.m.STATE/'state.json').read_text())['status'],'AUTO_ROLLED_BACK')
    def test_health_failure_rolls_back(self):
        self.m.health.side_effect=[None,RuntimeError('health-failure'),None]
        with self.assertRaisesRegex(RuntimeError,'health-failure'):self.action('apply')
        self.unchanged()
    def test_upload_configuration_failure_rolls_back(self):
        self.m.upload_config.side_effect=RuntimeError('upload-configuration-failure')
        with self.assertRaisesRegex(RuntimeError,'upload-configuration-failure'):self.action('apply')
        self.unchanged();self.assertEqual(json.loads((self.m.STATE/'state.json').read_text())['status'],'AUTO_ROLLED_BACK')
    def test_recovery_failure_recorded(self):
        self.m.controls.side_effect=RuntimeError('controls-failure');self.patches.append(patch.object(self.m,'restore',side_effect=RuntimeError('restore-failure')));self.patches[-1].start()
        with self.assertRaises(RuntimeError):self.action('apply')
        self.assertEqual(json.loads((self.m.STATE/'state.json').read_text())['status'],'RECOVERY_REQUIRED')
    def test_replay_refused(self):
        self.action('apply')
        with self.assertRaisesRegex(RuntimeError,'REPLAY_REFUSED'):self.action('apply')
    def test_corrupt_backup_refuses_rollback(self):
        self.action('apply');(self.m.STATE/'code-backup.json').write_text('{}')
        with self.assertRaisesRegex(RuntimeError,'BACKUP_HASH'):self.action('rollback')
    def test_postdeploy_drift_refuses_rollback(self):
        self.action('apply');(self.root/next(iter(self.payload))).write_bytes(b'later-code')
        with self.assertRaisesRegex(RuntimeError,'FILE_DRIFT'):self.action('rollback')
    def test_upload_probe_uses_socket_and_cleans_up(self):
        m=load()
        def php(web,code):
            self.assertIn('fsockopen',code);self.assertNotIn('file_get_contents',code)
            result=subprocess.run(['php','-l'],input=('<?php '+code).encode(),capture_output=True);self.assertEqual(result.returncode,0,result.stdout+result.stderr)
            return b'{"sapi":"apache2handler","upload":"200M","post":"201M"}'
        with patch.object(m,'php',side_effect=php):m.upload_config('web',self.root)
        self.assertFalse((self.root/'chinaplanetoperations/tasks/m094-upload-probe').exists())
    def test_embedded_php_parses(self):
        capture=[]
        def php(web,code):
            result=subprocess.run(['php','-l'],input=('<?php '+code).encode(),capture_output=True);self.assertEqual(result.returncode,0,result.stdout+result.stderr)
            return b'CONTROLS_READY' if "CONTROLS_READY" in code else b'HEALTH_OK' if 'fsockopen' in code else b'READY'
        m=load()
        with patch.object(m,'php',side_effect=php):m.controls('web');m.readiness('web');m.readiness('web',True);m.health('web')
if __name__=='__main__':unittest.main()
