import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('probe', Path(__file__).with_name('readonly_preflight.py'))
probe = importlib.util.module_from_spec(spec)
spec.loader.exec_module(probe)


def resource(name, service, ports):
    return {'name': '/' + name, 'running': True, 'image': 'sha256:fixture', 'project': 'erp-staging',
            'service': service, 'ports': ports, 'mounts': []}


class ProbeTest(unittest.TestCase):
    def resources(self):
        app = resource(probe.APP, 'dolibarr', {'80/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18088'}]})
        for target, volume in (('/var/www/html/custom', 'erp-staging_erp-staging_custom'), ('/var/www/documents', 'erp-staging_erp-staging_docs')):
            app['mounts'].append({'Destination': target, 'Type': 'volume', 'Name': volume, 'Source': '/var/lib/docker/volumes/' + volume + '/_data', 'RW': True})
        return app, resource(probe.DB, 'mariadb', {'3306/tcp': None}), resource(probe.MAIL, 'mailpit', {'1025/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '11025'}], '8025/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18025'}]})

    def test_canonical_resources(self):
        probe.validate_resources(*self.resources())

    def test_public_binding_rejected(self):
        resources = self.resources()
        resources[0]['ports']['80/tcp'][0]['HostIp'] = '0.0.0.0'
        with self.assertRaises(RuntimeError):
            probe.validate_resources(*resources)

    def test_other_volume_rejected(self):
        resources = self.resources()
        resources[0]['mounts'][0]['Name'] = 'other_custom'
        with self.assertRaises(RuntimeError):
            probe.validate_resources(*resources)

    def test_additional_mail_binding_rejected(self):
        resources = self.resources()
        resources[2]['ports']['9999/tcp'] = [{'HostIp': '0.0.0.0', 'HostPort': '19999'}]
        with self.assertRaises(RuntimeError):
            probe.validate_resources(*resources)

    def test_file_snapshot_and_missing_helper(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve()
            (root / 'js').mkdir()
            (root / 'js/boards.js').write_bytes(b'synthetic code')
            value = probe.source('js/boards.js', root)
            self.assertEqual(value['size'], 14)
            self.assertEqual((root / 'js/boards.js').read_bytes(), b'synthetic code')
            self.assertIsNone(probe.source('js/boards_geometry.js', root))

    def test_paths_and_symlinks_rejected(self):
        with self.assertRaises(RuntimeError):
            probe.source('../conf/conf.php')
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve()
            (root / 'js').mkdir()
            (root / 'outside').write_bytes(b'not source')
            (root / 'js/boards.js').symlink_to(root / 'outside')
            with self.assertRaises(OSError):
                probe.source('js/boards.js', root)


if __name__ == '__main__':
    unittest.main()
