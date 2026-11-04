#!/usr/bin/env python3
"""Synthetic pipe/archive failure tests; no Docker, DB, credentials or network."""
import gzip
import hashlib
import importlib.util
import io
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import types
import unittest
from unittest.mock import patch

sys.dont_write_bytecode=True
ROOT=Path(__file__).resolve().parents[2]


def module(name):
    spec=importlib.util.spec_from_file_location(name,ROOT/'deploy/releases/m119'/ (name+'.py'))
    m=importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m


def archive(data,kind=tarfile.REGTYPE):
    result=io.BytesIO()
    with tarfile.open(fileobj=result,mode='w') as a:
        info=tarfile.TarInfo('docker-compose.yml')
        info.type=kind
        info.size=len(data) if kind==tarfile.REGTYPE else 0
        info.linkname='/etc/passwd' if kind!=tarfile.REGTYPE else ''
        a.addfile(info,io.BytesIO(data) if info.size else None)
    return result.getvalue()


class DefinitionTests(unittest.TestCase):
    def setUp(self):
        self.m=module('preflight_readonly')

    def source(self,data,kind=tarfile.REGTYPE):
        return patch.object(self.m.subprocess,'run',return_value=types.SimpleNamespace(returncode=0,stdout=archive(data,kind),stderr=b''))

    def test_staging_source_uses_local_daemon_and_empty_interpolation_context(self):
        with self.source(b'{"services":{}}') as cp,patch.object(self.m.os.path,'isfile',return_value=False),patch.object(self.m,'run',return_value='{"services":{"dolibarr":{},"mariadb":{},"mailpit":{}}}') as render:
            result,location=self.m.staging_definition()
            self.assertEqual(location,'Portainer-stack2')
            args=cp.call_args
            self.assertEqual(args.args[0][:3],['docker','--host','unix:///var/run/docker.sock'])
            self.assertEqual(args.kwargs['env']['COMPOSE_DISABLE_ENV_FILE'],'true')
            self.assertIn('/dev/null',render.call_args.args[0])
            self.assertEqual(set(result['services']),{'dolibarr','mariadb','mailpit'})

    def test_interpolation_and_external_context_dependencies_stop_before_render(self):
        for data in (b'image: ${INHERITED}',b'env_file: .env',b'include: other.yml',b'extends: other.yml'):
            with self.source(data),patch.object(self.m.os.path,'isfile',return_value=False),patch.object(self.m,'run') as render:
                with self.assertRaises(RuntimeError):self.m.staging_definition()
                render.assert_not_called()

    def test_symlink_source_cannot_be_read_as_compose(self):
        with self.source(b'',tarfile.SYMTYPE),patch.object(self.m.os.path,'isfile',return_value=False),patch.object(self.m,'run') as render:
            with self.assertRaises(RuntimeError):self.m.staging_definition()
            render.assert_not_called()

    def test_context_dependent_bind_source_is_rejected(self):
        rendered='{"services":{"dolibarr":{"volumes":[{"type":"bind","source":"/wrong"}]},"mariadb":{},"mailpit":{}}}'
        with self.source(b'{}'),patch.object(self.m.os.path,'isfile',return_value=False),patch.object(self.m,'run',return_value=rendered):
            with self.assertRaises(RuntimeError):self.m.staging_definition()


class BackupTests(unittest.TestCase):
    def setUp(self):
        self.m=module('prepare_runtime')
        self.actual_popen=subprocess.Popen
        self.children=[]
        self.temp=tempfile.TemporaryDirectory(prefix='m119-pipe-proof-')
        self.dest=Path(self.temp.name)/'synthetic.sql.gz'

    def tearDown(self):
        for child in self.children:
            if child.poll() is None:child.kill();child.wait()
        self.temp.cleanup()

    def spawn(self,script):
        def start(args,**kwargs):
            self.assertEqual(args[:3],['docker','--host','unix:///var/run/docker.sock'])
            self.assertIn('--no-defaults --protocol=socket --socket=/run/mysqld/mysqld.sock',args[-1])
            child=self.actual_popen([sys.executable,'-c',script],**kwargs)
            self.children.append(child)
            return child
        return patch.object(self.m.subprocess,'Popen',side_effect=start)

    def identity(self,host='synthetic-host'):
        results=iter([('dolibarr_db\t'+host+'\t10.6.27-MariaDB-test\n').encode(),b'GNU timeout'])
        def answer(args):
            self.assertEqual(args[:2],['docker','exec'])
            self.assertNotIn('--host',args)
            return next(results)
        return patch.object(self.m,'run',side_effect=answer)

    def test_streams_and_drains_both_pipes_without_losing_dump_bytes(self):
        script="import sys;sys.stderr.buffer.write(b'x'*700000);sys.stderr.flush();sys.stdout.buffer.write(b'a'*200000+b'\\n-- Dump completed\\n');sys.stdout.flush()"
        with self.identity(),self.spawn(script):
            count,digest=self.m.database_backup(self.dest,'synthetic-host')
        content=gzip.decompress(self.dest.read_bytes())
        self.assertEqual(len(content),count)
        self.assertTrue(content.endswith(b'-- Dump completed\n'))
        self.assertEqual(hashlib.sha256(self.dest.read_bytes()).hexdigest(),digest)
        self.assertEqual(self.dest.stat().st_mode&0o777,0o600)
        self.assertFalse(self.dest.with_name(self.dest.name+'.incomplete').exists())
        self.assertIsNotNone(self.children[0].poll())

    def test_wrong_dump_endpoint_refuses_before_starting_child_or_creating_data(self):
        with self.identity('wrong-host'),patch.object(self.m.subprocess,'Popen') as start:
            with self.assertRaises(RuntimeError):self.m.database_backup(self.dest,'synthetic-host')
            start.assert_not_called()
        self.assertEqual(list(self.dest.parent.iterdir()),[])

    def test_partial_dump_never_becomes_completed_backup(self):
        with self.identity(),self.spawn("import sys;sys.stdout.write('x'*4096);sys.exit(1)"):
            with self.assertRaises(RuntimeError):self.m.database_backup(self.dest,'synthetic-host')
        self.assertFalse(self.dest.exists())
        self.assertTrue(self.dest.with_name(self.dest.name+'.incomplete').exists())
        self.assertIsNotNone(self.children[0].poll())

    def test_deadline_terminates_and_reaps_its_own_child(self):
        with self.identity(),self.spawn('import time;time.sleep(30)'),patch.object(self.m.time,'monotonic',side_effect=[0,200]):
            with self.assertRaises(RuntimeError):self.m.database_backup(self.dest,'synthetic-host')
        self.assertFalse(self.dest.exists())
        self.assertIsNotNone(self.children[0].poll())

    def test_existing_backup_is_never_overwritten(self):
        self.dest.write_bytes(b'preserve-original')
        with self.identity(),self.spawn("print('x'*4096+'Dump completed')"):
            with self.assertRaises(RuntimeError):self.m.database_backup(self.dest,'synthetic-host')
        self.assertEqual(self.dest.read_bytes(),b'preserve-original')


if __name__=='__main__':unittest.main()
