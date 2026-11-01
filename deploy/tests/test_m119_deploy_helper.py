#!/usr/bin/env python3
"""Owned temporary files and synthetic command boundaries only; never invokes Docker."""
import gzip
import copy
import fcntl
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import sys
import tarfile
import tempfile
import types
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
ROOT = Path(__file__).resolve().parents[2]
FROZEN = ROOT.parent / 'm119-staging-release/frozen'
ARTIFACT = 'artifact-faa869ec8d05b6e631887bb1dbe0c403deb9d97909cea84ede492ee172bca698.tar.gz'
MANIFEST = 'manifest-faa869ec8d05b6e631887bb1dbe0c403deb9d97909cea84ede492ee172bca698.json'
TEST_RUNTIME = '''assets/Cairo-OFL.txt
assets/Cairo.ttf
assets/Font-Awesome-LICENSE.txt
assets/fa-solid-900.woff2
assets/fontawesome-solid.css
assets/fontawesome.css
class/OfficeExpenseDraftService.php
class/OfficeExpenseEmployeeController.php
class/OfficeExpenseEmployeePage.php
class/OfficeExpenseMoney.php
class/OfficeExpenseNativeConnection.php
class/OfficeExpenseNativeDraftStore.php
class/OfficeExpenseNativeReceiptStore.php
class/OfficeExpenseNativeSchemaMigration.php
class/OfficeExpenseNativeStorageFactory.php
class/OfficeExpensePilotConfiguration.php
class/OfficeExpenseReceiptHttp.php
class/OfficeExpenseReceiptPanel.php
class/OfficeExpenseReceiptService.php
employee.css
employee.js
index.php
schema/001_drafts.sql
schema/002_draft_audit.sql
schema/003_schema_state.sql
schema/manifest.json
schema/receipts/001_receipts.sql
schema/receipts/002_receipt_audit.sql
schema/receipts/manifest.json'''.splitlines()


def synthetic_package():
    """Independent literal file contract; no external artifact or Git dependency."""
    files={'custom/chinaplanetoperations/office_expenses/'+p:('synthetic '+p+'\n').encode() for p in TEST_RUNTIME}
    files['support/native_operations.php']=b'<?php // synthetic worker; never executed\n'
    files['support/release.json']=json.dumps({'schema':1,'mission':'M119','source_commit':'8b14b9e9c609ed296cb18368f576bbf86fffadbb','financial_posting':False}).encode()
    result=io.BytesIO();entries=[]
    with tarfile.open(fileobj=result,mode='w:gz',format=tarfile.GNU_FORMAT) as archive:
        for path,data in sorted(files.items()):
            member=tarfile.TarInfo(path);member.mode=0o644;member.size=len(data)
            archive.addfile(member,io.BytesIO(data))
            entries.append({'path':path,'kind':'support' if path.startswith('support/') else 'runtime','mode':'0644','size':len(data),'sha256':hashlib.sha256(data).hexdigest()})
    artifact=result.getvalue();sha=hashlib.sha256(artifact).hexdigest()
    manifest=json.dumps({'schema':1,'mission':'M119','commit':'8b14b9e9c609ed296cb18368f576bbf86fffadbb','artifact_name':'artifact-'+sha+'.tar.gz','artifact_sha256':sha,'files':entries}).encode()
    return manifest,artifact,files


class HelperTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        path = ROOT / 'deploy/releases/m119/deploy_helper.py'
        if not path.is_file():
            raise AssertionError('The guarded M119 deployment helper is not implemented.')
        spec = importlib.util.spec_from_file_location('m119_deploy_helper', path)
        cls.m = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(cls.m)
        cls.manifest,cls.artifact,cls.fixture_files=synthetic_package()
        cls.artifact_sha=hashlib.sha256(cls.artifact).hexdigest()
        cls.patches=[patch.object(cls.m,'ARTIFACT_SHA',cls.artifact_sha),
                     patch.object(cls.m,'MANIFEST_SHA',hashlib.sha256(cls.manifest).hexdigest()),
                     patch.object(cls.m,'BASE_SCHEMA',hashlib.sha256(cls.fixture_files['custom/chinaplanetoperations/office_expenses/schema/manifest.json']).hexdigest()),
                     patch.object(cls.m,'RECEIPT_SCHEMA',hashlib.sha256(cls.fixture_files['custom/chinaplanetoperations/office_expenses/schema/receipts/manifest.json']).hexdigest())]
        for p in cls.patches:p.start()

    @classmethod
    def tearDownClass(cls):
        for p in cls.patches:p.stop()

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='m119-helper-', dir='/tmp')
        self.root = Path(self.temp.name).resolve()
        self.addCleanup(self.temp.cleanup)

    def request(self, action='preflight'):
        return {'schema': 1, 'action': action, 'mission_id': 'M119',
                'commit': '8b14b9e9c609ed296cb18368f576bbf86fffadbb',
                'artifact_sha256': self.artifact_sha,
                'artifact_name': 'artifact-'+self.artifact_sha+'.tar.gz', 'manifest_name': 'manifest-'+self.artifact_sha+'.json'}

    def test_exact_request_cannot_select_paths_action_source_or_extra_settings(self):
        self.m.validate_request(self.request(), 'preflight')
        for key, value in [('action', 'apply'), ('commit', 'main'), ('mission_id', 'M120'),
                           ('schema', True), ('artifact_name', '../secret'), ('manifest_name', '/etc/passwd')]:
            obj = self.request(); obj[key] = value
            with self.subTest(key=key), self.assertRaises(self.m.Refused):
                self.m.validate_request(obj, 'preflight')
        obj = self.request(); obj['image'] = 'anything'
        with self.assertRaises(self.m.Refused): self.m.validate_request(obj, 'preflight')

    def test_json_duplicate_keys_are_rejected(self):
        with self.assertRaises(self.m.Refused): self.m.decode_json(b'{"action":"apply","action":"status"}')

    def test_stable_file_reader_refuses_links_modes_bounds_and_wrong_owners(self):
        p = self.root / 'request.json'; p.write_bytes(b'{}'); p.chmod(0o600)
        read = lambda path=p, **kw: self.m.secure_read(path, os.getuid(), 0o600, 8, root=self.root, **kw)
        self.assertEqual(read(), b'{}')
        p.chmod(0o644)
        with self.assertRaises(self.m.Refused): read()
        p.chmod(0o600)
        os.link(p, self.root / 'alias')
        with self.assertRaises(self.m.Refused): read()
        (self.root / 'alias').unlink(); (self.root / 'symlink').symlink_to(p)
        with self.assertRaises(self.m.Refused): read(self.root / 'symlink')
        p.write_bytes(b'x' * 9)
        with self.assertRaises(self.m.Refused): read()
        p.write_bytes(b'{}')
        with self.assertRaises(self.m.Refused): self.m.secure_read(p, os.getuid()+1, 0o600, 8, root=self.root)

    def test_file_reader_rejects_writable_parent(self):
        p = self.root / 'request.json'; p.write_bytes(b'{}'); p.chmod(0o600)
        self.root.chmod(0o777)
        try:
            with self.assertRaises(self.m.Refused): self.m.secure_read(p, os.getuid(), 0o600, 8, root=self.root)
        finally: self.root.chmod(0o700)

    def test_access_time_changes_do_not_fake_a_private_file_content_race(self):
        p=self.root/'request.json';p.write_bytes(b'{}');p.chmod(0o600)
        actual=self.m.os.fstat;calls=[]
        def observed(fd):
            value=actual(fd);calls.append(fd)
            if len(calls)==1:return value
            fields={k:getattr(value,k) for k in dir(value) if k.startswith('st_')}
            fields['st_atime']=value.st_atime+5;fields['st_atime_ns']=value.st_atime_ns+5000000000
            return types.SimpleNamespace(**fields)
        with patch.object(self.m.os,'fstat',side_effect=observed):
            self.assertEqual(self.m.secure_read(p,os.getuid(),0o600,8,root=self.root),b'{}')

    def test_synthetic_package_validates_all_31_entries_and_pinned_ddl_bytes(self):
        files = self.m.validate_package(self.manifest, self.artifact)
        self.assertEqual(len(files), 31)
        self.assertIn('support/native_operations.php', files)
        self.assertEqual(files['custom/chinaplanetoperations/office_expenses/schema/manifest.json'],b'synthetic schema/manifest.json\n')
        self.assertNotIn('conf/conf.php', files)

    def test_actual_frozen_package_when_locally_available(self):
        if not (FROZEN/MANIFEST).is_file():self.skipTest('External frozen artifact not present; synthetic guards remain self-contained.')
        spec=importlib.util.spec_from_file_location('m119_frozen_check',ROOT/'deploy/releases/m119/deploy_helper.py')
        original=importlib.util.module_from_spec(spec);spec.loader.exec_module(original)
        files=original.validate_package((FROZEN/MANIFEST).read_bytes(),(FROZEN/ARTIFACT).read_bytes())
        self.assertEqual(len(files),31)
        self.assertEqual(hashlib.sha256(files['custom/chinaplanetoperations/office_expenses/schema/manifest.json']).hexdigest(),'a88a40ae71abc63fa836036488b51d3edef72645e349f396a1ed3a4d4aeef88b')

    def tampered(self, change):
        out = io.BytesIO()
        with tarfile.open(fileobj=io.BytesIO(self.artifact), mode='r:gz') as src, tarfile.open(fileobj=out, mode='w:gz') as dest:
            members = list(src)
            change(members)
            for item in members:
                data = src.extractfile(item).read() if item.isfile() and item.name in src.getnames() else b''
                dest.addfile(item, io.BytesIO(data) if item.isfile() else None)
        return out.getvalue()

    def test_archive_semantic_checks_survive_a_matching_outer_digest(self):
        changes = [lambda xs: xs.append(xs[0]), lambda xs: setattr(xs[0], 'mode', 0o777),
                   lambda xs: setattr(xs[0], 'type', tarfile.SYMTYPE),
                   lambda xs: setattr(xs[0], 'name', '../outside'), lambda xs: xs.pop()]
        for change in changes:
            raw = self.tampered(change)
            with self.subTest(change=change), patch.object(self.m, 'ARTIFACT_SHA', hashlib.sha256(raw).hexdigest()):
                # Manifest outer identity changes too, but the fixed allowlist/metadata must still reject.
                obj = json.loads(self.manifest); obj['artifact_sha256'] = self.m.ARTIFACT_SHA
                obj['artifact_name'] = 'artifact-' + self.m.ARTIFACT_SHA + '.tar.gz'
                manifest = json.dumps(obj).encode()
                with patch.object(self.m, 'MANIFEST_SHA', hashlib.sha256(manifest).hexdigest()):
                    with self.assertRaises(self.m.Refused): self.m.validate_package(manifest, raw)

    def test_modified_payload_or_manifest_is_rejected(self):
        for manifest, artifact in [(self.manifest+b' ', self.artifact), (self.manifest, self.artifact[:-1]+b'!')]:
            with self.assertRaises(self.m.Refused): self.m.validate_package(manifest, artifact)

    def test_unpinned_runtime_and_bad_cli_fail_before_any_commands(self):
        with patch.object(self.m, 'docker', side_effect=AssertionError('No Docker permitted')):
            with patch.object(self.m,'FINAL_IMAGE','UNPINNED_FAIL_CLOSED'):
                with self.assertRaises(self.m.Refused): self.m.require_runtime_pin()
            for args in ([], ['apply', '/caller/path'], ['destroy']):
                with self.assertRaises(self.m.Refused): self.m.cli(args)

    def test_local_docker_boundary_ignores_caller_context_and_never_exports_stderr(self):
        seen = []
        def fake(args, **kw):
            seen.append((args, kw)); return type('Result', (), {'returncode': 1, 'stdout': b'', 'stderr': b'synthetic-secret'})()
        with patch.object(self.m.subprocess, 'run', side_effect=fake):
            with self.assertRaises(self.m.Refused) as caught: self.m.docker(['inspect', 'fixed'])
        self.assertNotIn('synthetic-secret', str(caught.exception))
        self.assertEqual(seen[0][0][:3], ['docker', '--host', 'unix:///var/run/docker.sock'])
        self.assertEqual(set(seen[0][1]['env']), {'PATH'})

    def test_atomic_install_never_overwrites_existing_or_symlink_destination(self):
        p = self.root / 'index.php'
        self.m.install_file(p, b'first')
        with self.assertRaises(self.m.Refused): self.m.install_file(p, b'second')
        self.assertEqual(p.read_bytes(), b'first')
        p.unlink(); p.symlink_to(self.root / 'missing')
        with self.assertRaises(self.m.Refused): self.m.install_file(p, b'second')
        self.assertFalse((self.root / 'missing').exists())

    def lifecycle(self):
        # Keep actual install/state/quarantine logic; replace external host/native/HTTP boundaries only.
        owner = os.getuid()
        state = self.root/'state'; state.mkdir(mode=0o700)
        custom=self.root/'custom';custom.mkdir(mode=0o755)
        parent = custom/'chinaplanetoperations'; parent.mkdir(mode=0o755)
        target = parent/'office_expenses'
        patches = [patch.object(self.m, 'STATE', state), patch.object(self.m, 'TARGET', target),
                   patch.object(self.m, 'CUSTOM',custom),
                   patch.object(self.m, 'ROOT_UID', owner), patch.object(self.m, 'SAFE_ROOT', self.root)]
        for p in patches: p.start(); self.addCleanup(p.stop)
        files = self.m.validate_package(self.manifest, self.artifact)
        helper = self.m.Deployment(files)
        events = []
        def native(mode):
            events.append(mode)
            if mode in ('migrate', 'verify') and 'activate' not in events:
                self.assertFalse((target/'index.php').exists(), 'Entrypoint activated before schema/native verification')
            return {'status': 'native_context_verified' if mode=='inspect' else 'native_storage_verified',
                    'owned_table_presence': {k: 0 for k in self.m.TABLES}}
        helper.context = lambda: {'Config': {'Hostname': 'synthetic-host'}}
        helper.native = native
        helper.backup = lambda database: events.append('backup')
        helper.backup_library = lambda: b'synthetic boundary only'
        helper.lint = lambda: events.append('lint')
        helper.health = lambda: events.append('health')
        original = helper.activate
        def activate(): events.append('activate'); original()
        helper.activate = activate
        return helper, events, state, target

    def test_apply_backs_up_and_verifies_native_before_atomic_entrypoint(self):
        helper, events, state, target = self.lifecycle()
        helper.apply()
        self.assertTrue((target/'index.php').is_file())
        self.assertLess(events.index('backup'), events.index('migrate'))
        self.assertLess(events.index('lint'), events.index('migrate'))
        self.assertLess(events.index('verify'), events.index('activate'))
        self.assertEqual(json.loads((state/'state.json').read_bytes())['phase'], 'active')
        with self.assertRaises(self.m.Refused): helper.apply()

    def test_private_cli_umask_does_not_hide_runtime_dependencies_from_service_user(self):
        helper,events,state,target=self.lifecycle()
        previous=os.umask(0o077)
        try:helper.apply()
        finally:os.umask(previous)
        for relative in ('assets','class','schema','schema/receipts'):
            self.assertEqual((target/relative).stat().st_mode&0o777,0o755)

    def test_partial_ddl_failure_disables_module_retains_evidence_and_refuses_replay(self):
        helper, events, state, target = self.lifecycle()
        original = helper.native
        def fail(mode):
            if mode == 'migrate': raise self.m.Refused('Synthetic uncertain DDL')
            return original(mode)
        helper.native = fail
        evidence = self.root/'retained-db-and-blobs'; evidence.write_bytes(b'keep')
        with self.assertRaises(self.m.Refused): helper.apply()
        self.assertTrue(target.exists());self.assertEqual(target.stat().st_mode&0o777,0)
        self.assertEqual(evidence.read_bytes(), b'keep')
        self.assertEqual(json.loads((state/'state.json').read_bytes())['phase'], 'failed_disabled_retained')
        with self.assertRaises(self.m.Refused): helper.apply()

    def test_post_activation_failure_disables_and_retains_only_its_own_inode(self):
        helper, events, state, target = self.lifecycle()
        helper.health = lambda: (_ for _ in ()).throw(self.m.Refused('Synthetic health failure'))
        with self.assertRaises(self.m.Refused): helper.apply()
        self.assertEqual(target.stat().st_mode&0o777,0)
        target.chmod(0o700)  # Test owner restores access only to inspect/clean its disposable fixture.
        self.assertFalse((target/'index.php').exists())

    def test_missing_backup_dependency_is_rejected_before_recording_an_apply(self):
        helper,events,state,target=self.lifecycle()
        helper.backup_library=lambda:(_ for _ in ()).throw(self.m.Refused('Synthetic missing root library'))
        with self.assertRaises(self.m.Refused):helper.apply()
        self.assertFalse((state/'state.json').exists());self.assertFalse(target.exists())

    def test_activation_refuses_a_replacement_directory_before_writing_entrypoint(self):
        helper,events,state,target=self.lifecycle()
        original=helper.activate
        def replacement():
            target.rename(self.root/'recorded-original');target.mkdir(mode=0o755)
            original()
        helper.activate=replacement
        with self.assertRaises(self.m.Refused):helper.apply()
        self.assertFalse((target/'index.php').exists())

    def test_rollback_requires_recorded_deployment_and_never_restores_database(self):
        helper, events, state, target = self.lifecycle()
        with self.assertRaises(self.m.Refused): helper.rollback()
        helper.apply(); events.clear(); helper.rollback()
        self.assertEqual(events, [])
        self.assertEqual(target.stat().st_mode&0o777,0)
        self.assertEqual(json.loads((state/'state.json').read_bytes())['phase'], 'rolled_back_disabled_retained')

    def test_preexisting_target_is_never_adopted_or_deleted(self):
        helper, events, state, target = self.lifecycle()
        target.mkdir(); (target/'unrelated').write_bytes(b'keep')
        with self.assertRaises(self.m.Refused): helper.apply()
        self.assertEqual((target/'unrelated').read_bytes(), b'keep')
        self.assertEqual(events, [])

    def test_target_appearing_after_preflight_is_not_mistaken_for_created_module(self):
        helper,events,state,target=self.lifecycle()
        def concurrent_target(database):
            target.mkdir(mode=0o755);(target/'index.php').write_bytes(b'foreign')
        helper.backup=concurrent_target
        with self.assertRaises(Exception):helper.apply()
        self.assertEqual((target/'index.php').read_bytes(),b'foreign')
        self.assertEqual(target.stat().st_mode&0o777,0o755)

    def test_rollback_refuses_replacement_module_even_with_a_valid_deployment_record(self):
        helper,events,state,target=self.lifecycle();helper.apply()
        target.rename(self.root/'recorded-original')
        target.mkdir(mode=0o755);(target/'index.php').write_bytes(b'foreign')
        with self.assertRaises(self.m.Refused):helper.rollback()
        self.assertEqual((target/'index.php').read_bytes(),b'foreign')

    def test_hash_drift_and_unknown_files_fail_verification(self):
        helper, events, state, target = self.lifecycle(); helper.apply()
        (target/'employee.js').write_bytes(b'drift')
        with self.assertRaises(self.m.Refused): helper.verify()
        (target/'unknown.php').write_bytes(b'unknown')
        with self.assertRaises(self.m.Refused): helper.verify()

    def test_context_rejects_resource_exposure_mount_and_identity_drift_before_writes(self):
        helper,events,state,target=self.lifecycle()
        helper.context=types.MethodType(self.m.Deployment.context,helper)
        helper.check_private=lambda:None
        def resource(service):
            return {'Id':'synthetic','Image':self.m.FINAL_IMAGE,'State':{'Running':True,'Paused':False},
                    'Config':{'Hostname':'synthetic-host','Labels':{'com.docker.compose.project':'erp-staging','com.docker.compose.service':service}},
                    'NetworkSettings':{'Ports':{},'Networks':{}},'Mounts':[]}
        app=resource('dolibarr');db=resource('mariadb');mail=resource('mailpit')
        db['Id']='b00e094f86f9fbe8cb62e47e54146ef46baaf7c69eb25091dd176b60bf04f79d'
        mail['Id']='92ab1e651f4b6892d67f0fb83a6f75a185f29ca8c74883bf8904fe71a6a7d295'
        app['NetworkSettings']={'Ports':{'80/tcp':[{'HostIp':'127.0.0.1','HostPort':'18088'}]},'Networks':{'erp-staging_erp-staging_internal':{},'proxy_net':{}}}
        mail['NetworkSettings']['Ports']={'1025/tcp':[{'HostIp':'127.0.0.1','HostPort':'11025'}],'8025/tcp':[{'HostIp':'127.0.0.1','HostPort':'18025'}]}
        for destination,name in [('/var/www/html/custom','erp-staging_erp-staging_custom'),('/var/www/documents','erp-staging_erp-staging_docs'),('/var/lib/chinaplanetos-office-expenses','erp-staging_m119_office_expenses_private')]:
            source=str(self.m.CUSTOM) if destination=='/var/www/html/custom' else '/var/lib/docker/volumes/'+name+'/_data'
            app['Mounts'].append({'Destination':destination,'Type':'volume','Name':name,'Source':source,'RW':True})
        base={self.m.APP:app,self.m.DB:db,self.m.MAIL:mail}
        with patch.object(self.m,'inspect',side_effect=lambda name:base[name]):helper.context()
        changes=[lambda f:f[self.m.APP].update(Image='sha256:'+'0'*64),
                 lambda f:f[self.m.DB].update(Id='wrong'),lambda f:f[self.m.MAIL].update(Id='wrong'),
                 lambda f:f[self.m.APP]['State'].update(Paused=True),
                 lambda f:f[self.m.APP]['NetworkSettings']['Ports']['80/tcp'][0].update(HostIp='0.0.0.0'),
                 lambda f:f[self.m.DB]['NetworkSettings']['Ports'].update({'3306/tcp':[{'HostIp':'0.0.0.0','HostPort':'3306'}]}),
                 lambda f:f[self.m.APP]['Mounts'][2].update(RW=False),
                 lambda f:f[self.m.APP]['Mounts'][2].update(Source='/wrong'),
                 lambda f:f[self.m.APP]['Config']['Labels'].update({'com.docker.compose.project':'production'})]
        for change in changes:
            fixtures=copy.deepcopy(base);change(fixtures)
            with self.subTest(change=change),patch.object(self.m,'inspect',side_effect=lambda name:fixtures[name]):
                with self.assertRaises(self.m.Refused):helper.context()
        self.assertFalse(target.exists());self.assertEqual(events,[])

    def test_recorded_app_owned_755_ancestors_are_accepted_without_chmod(self):
        helper,events,state,target=self.lifecycle()
        actual=self.m.os.fstat;parents={inode for inode in (self.m.inode(self.m.CUSTOM.stat()),self.m.inode(target.parent.stat()))}
        def app_owned(fd):
            s=actual(fd)
            if self.m.inode(s) not in parents:return s
            fields={k:getattr(s,k) for k in dir(s) if k.startswith('st_')};fields['st_uid']=33;fields['st_gid']=33
            return types.SimpleNamespace(**fields)
        with patch.object(self.m.os,'fstat',side_effect=app_owned):helper.apply()
        self.assertEqual(self.m.CUSTOM.stat().st_mode&0o777,0o755)
        self.assertEqual(target.parent.stat().st_mode&0o777,0o755)

    def test_held_runtime_descriptor_never_writes_or_disables_a_replacement_path(self):
        helper,events,state,target=self.lifecycle();helper.apply()
        tree=self.m.RuntimeTree(value=helper.read_state())
        original=self.root/'original-runtime';target.rename(original)
        target.mkdir(mode=0o755);(target/'index.php').write_bytes(b'foreign index')
        try:
            # Actual write through the captured FD must reach the original inode only.
            with self.assertRaises(self.m.Refused):tree.install('unknown.php',b'bad')
            (original/'employee.js').unlink()
            tree.install('employee.js',b'owned update')
            self.assertEqual((original/'employee.js').read_bytes(),b'owned update')
            self.assertFalse((target/'employee.js').exists())
            tree.disable()
            self.assertEqual(original.stat().st_mode&0o777,0)
            self.assertEqual((target/'index.php').read_bytes(),b'foreign index')
            self.assertEqual(target.stat().st_mode&0o777,0o755)
        finally:tree.close()

    def test_atomic_publication_never_replaces_a_concurrent_empty_directory(self):
        helper,events,state,target=self.lifecycle()
        tree=self.m.RuntimeTree(create=True);target.mkdir(mode=0o755)
        foreign=self.m.inode(target.stat())
        try:
            with self.assertRaises(self.m.Refused):tree.publish()
            self.assertEqual(self.m.inode(target.stat()),foreign)
            self.assertEqual(target.stat().st_mode&0o777,0o755)
        finally:tree.close()

    def test_parallel_cli_action_cannot_enter_with_an_existing_exclusive_lock(self):
        helper,events,state,target=self.lifecycle()
        request=self.root/'request.json';request.write_bytes(json.dumps(self.request('status')).encode());request.chmod(0o600)
        actual=self.m.secure_read
        def read(path,owner,mode,limit,**kwargs):
            if Path(path)==request:return actual(path,os.getuid(),mode,limit,root=self.root)
            return actual(path,owner,mode,limit,**kwargs)
        fd=os.open(state/'lock',os.O_RDWR|os.O_CREAT,0o600);fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        previous=os.umask(0o077)
        try:
            with patch.object(self.m,'INBOX',self.root),patch.object(self.m.os,'geteuid',return_value=0),patch.object(self.m.os,'uname',return_value=types.SimpleNamespace(nodename='srv1011043')),patch.object(self.m,'secure_read',side_effect=read),patch.object(self.m,'docker',side_effect=AssertionError('No Docker permitted')):
                with self.assertRaises(self.m.Refused):self.m.cli(['status'])
        finally:os.umask(previous);os.close(fd)
        self.assertFalse(target.exists())

    def test_private_pilot_guards_match_php_strict_types_and_key_order(self):
        helper,events,state,target=self.lifecycle()
        private=self.root/'private';private.mkdir();private.chmod(0o750)
        receipts=private/'receipts';receipts.mkdir();receipts.chmod(0o700)
        marker=receipts/'.cpo-office-expenses-private';marker.write_bytes(b'cpo-office-expenses-private-v1');marker.chmod(0o600)
        pilot=private/'pilot.json'
        config={'version':1,'enabled':True,'environment':'backup-erp','entity':1,'user_id':1,'base_currency':'USD','offices':{'egypt':['EGP'],'china':['CNY']},'precision':{'EGP':2,'CNY':2,'USD':2},'private_root':'/var/lib/chinaplanetos-office-expenses/receipts'}
        def save(obj):pilot.write_text(json.dumps(obj));pilot.chmod(0o640)
        with patch.object(self.m,'PRIVATE',private),patch.object(self.m,'SERVICE_UID',os.getuid()),patch.object(self.m,'SERVICE_GID',private.stat().st_gid):
            save(config);self.m.Deployment.check_private(helper)
            for key,value in [('version',True),('entity',True),('user_id',True),('enabled',1),('offices',{'china':['CNY'],'egypt':['EGP']}),('precision',{'USD':2,'CNY':2,'EGP':2})]:
                obj=copy.deepcopy(config);obj[key]=value;save(obj)
                with self.subTest(key=key),self.assertRaises(self.m.Refused):self.m.Deployment.check_private(helper)


if __name__ == '__main__': unittest.main()
