#!/usr/bin/env python3
"""Pinned standalone installer/package/recovery tests; no Docker, native configuration or VPS."""
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import tarfile
import tempfile
import types
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[2]

def load(name,path):
    spec=importlib.util.spec_from_file_location(name,ROOT/path);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m

helper=load('standalone_helper','deploy/releases/m119/standalone_deploy_helper.py')
installer=load('standalone_installer','deploy/releases/m119/install_standalone_helper.py')
builder=load('standalone_builder','deploy/releases/m119/build_standalone_artifact.py')

class StandaloneDeployerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.raw=(ROOT/'deploy/releases/m119/deploy_helper.py').read_bytes()
        cls.core=helper.configured(cls.raw)
        cls.temp=tempfile.TemporaryDirectory(prefix='m119-standalone-test-')
        cls.package=builder.build(helper.SOURCE,Path(cls.temp.name),ROOT)
        cls.manifest=Path(cls.package['manifest_path']).read_bytes();cls.artifact=Path(cls.package['artifact']).read_bytes()
    @classmethod
    def tearDownClass(cls):cls.temp.cleanup()
    def test_pins_match_committed_dependency_closed_source(self):
        self.assertEqual(hashlib.sha256(self.raw).hexdigest(),helper.FROZEN_SHA)
        self.assertEqual(hashlib.sha256((ROOT/'deploy/releases/m119/standalone_deploy_helper.py').read_bytes()).hexdigest(),installer.NEW_SHA)
        self.assertEqual(hashlib.sha256((ROOT/'deploy/releases/m119/standalone_native_operations.php').read_bytes()).hexdigest(),helper.WORKER_SHA)
        self.assertEqual(helper.WORKER_SHA,installer.WORKER_SHA)
        self.assertEqual(hashlib.sha256(self.manifest).hexdigest(),helper.MANIFEST)
        self.assertEqual(hashlib.sha256(self.artifact).hexdigest(),helper.ARTIFACT)
        self.assertEqual(helper.RUNTIME,tuple(p.removeprefix(builder.MODULE)for p in builder.RUNTIME_FILES))
        self.assertEqual(set(helper.validate_package(self.core,self.manifest,self.artifact)),self.core.ALLOWLIST)
    def test_wrong_release_and_action_fail_before_server_state(self):
        request={'schema':1,'action':'apply','mission_id':'M119','commit':helper.SOURCE,'artifact_sha256':helper.ARTIFACT,'artifact_name':'artifact-'+helper.ARTIFACT+'.tar.gz','manifest_name':'manifest-'+helper.ARTIFACT+'.json'}
        self.core.validate_request(request,'apply')
        for key,value in [('commit','0'*40),('action','rollback'),('artifact_name','../artifact'),('schema',True)]:
            with self.subTest(key=key),self.assertRaises(self.core.Refused):self.core.validate_request(dict(request,**{key:value}),'apply')
        for arguments in [[],['apply','extra'],['shell'],['--force']]:
            with self.assertRaises(RuntimeError):helper.cli(arguments)
    def test_corruption_and_duplicate_manifest_keys_are_refused(self):
        for raw,archive in [(self.manifest+b' ',self.artifact),(self.manifest,self.artifact+b'x')]:
            with self.assertRaises(RuntimeError):helper.validate_package(self.core,raw,archive)
        raw=self.manifest.replace(b'"schema": 1',b'"schema": 1, "schema": 1')
        with patch.object(helper,'MANIFEST',hashlib.sha256(raw).hexdigest()),self.assertRaises(self.core.Refused):helper.validate_package(self.core,raw,self.artifact)
    def test_missing_member_and_symlink_never_reach_extraction(self):
        for change in ['missing','symlink','extra']:
            with self.subTest(change=change):
                result=io.BytesIO()
                with tarfile.open(fileobj=io.BytesIO(self.artifact),mode='r:gz')as src,tarfile.open(fileobj=result,mode='w:gz')as dst:
                    for index,member in enumerate(src):
                        if index==0 and change=='missing':continue
                        data=src.extractfile(member).read()
                        if index==0 and change=='symlink':member.type=tarfile.SYMTYPE;member.linkname='/etc/passwd';member.size=0;dst.addfile(member)
                        else:dst.addfile(member,io.BytesIO(data))
                    if change=='extra':member=tarfile.TarInfo('../foreign.php');member.size=1;dst.addfile(member,io.BytesIO(b'x'))
                archive=result.getvalue();digest=hashlib.sha256(archive).hexdigest();obj=json.loads(self.manifest);obj['artifact_sha256']=digest;obj['artifact_name']='artifact-'+digest+'.tar.gz';raw=json.dumps(obj).encode()
                with patch.object(helper,'ARTIFACT',digest),patch.object(helper,'MANIFEST',hashlib.sha256(raw).hexdigest()),self.assertRaises(RuntimeError):helper.validate_package(self.core,raw,archive)
    def test_backup_failure_disables_and_retains_before_installation(self):
        events=[]
        class Base:
            def record(self,value,phase):events.append(phase);return dict(value,phase=phase)
            def context(self):return None
            def backup(self,database):raise RuntimeError('SYNTHETIC backup failure')
            def close_tree(self):events.append('closed')
        d=helper.Deployment.__new__(helper.Deployment);d.c=types.SimpleNamespace();d.base=Base();d.preflight=lambda:{'legacy_menu':[]};d.disable=lambda value:events.append('disabled-retained')
        with self.assertRaises(RuntimeError):d.apply()
        self.assertEqual(events,['backing_up','disabled-retained','failed_disabled_retained','closed'])
    def test_unconfirmed_disable_never_reports_safe_rollback(self):
        events=[]
        class Base:
            def record(self,value,phase):events.append(phase);return dict(value,phase=phase)
            def context(self):return None
            def backup(self,database):raise RuntimeError('SYNTHETIC backup failure')
            def close_tree(self):pass
        d=helper.Deployment.__new__(helper.Deployment);d.c=types.SimpleNamespace();d.base=Base();d.preflight=lambda:{'legacy_menu':[]}
        def fail(value):raise RuntimeError('SYNTHETIC disable failed')
        d.disable=fail
        with self.assertRaises(RuntimeError):d.apply()
        self.assertEqual(events,['backing_up','failed_disable_unconfirmed'])
    def test_worker_mode_guard_refuses_before_native_bootstrap(self):
        for mode in ['shell','--force']:
            r=subprocess.run(['php',str(ROOT/'deploy/releases/m119/standalone_native_operations.php'),mode],capture_output=True)
            self.assertEqual(r.returncode,1);self.assertEqual(r.stdout,b'')

if __name__=='__main__':unittest.main()
