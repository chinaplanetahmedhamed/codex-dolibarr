"""Loopback-only acceptance of the actual R13 renderers, without posting."""
import pathlib
import hashlib
import socket
import subprocess
import sys
import time
import unittest
import urllib.error
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
NATIVE = sys.argv.pop(1)


class FullPreviewTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            cls.port = sock.getsockname()[1]
        cls.server = subprocess.Popen([
            sys.executable, str(ROOT / 'deploy/tools/office_expenses/serve_workspace_preview.py'),
            '--native-htdocs', NATIVE, '--port', str(cls.port)
        ], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        cls.url = f'http://127.0.0.1:{cls.port}'
        for _ in range(100):
            if cls.server.poll() is not None:
                raise AssertionError(cls.server.stderr.read().decode())
            try:
                urllib.request.urlopen(cls.url + '/health', timeout=1).close()
                return
            except OSError:
                time.sleep(.05)
        raise AssertionError('Preview did not start')

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.server.stderr.close()

    def get(self, path, data=None):
        try:
            with urllib.request.urlopen(self.url + path, data=data, timeout=20) as response:
                return response.status, response.read().decode()
        except urllib.error.HTTPError as error:
            with error:
                return error.code, error.read().decode()

    def test_all_sections_in_both_languages_and_offices(self):
        views = ['overview', 'treasury', 'receipts', 'expenses', 'advances',
                 'categories', 'reports', 'employees', 'payroll', 'settings', 'users']
        for office, currency in [('egypt', 'EGP'), ('china', 'CNY')]:
            for lang in ['ar', 'en']:
                for view in views:
                    with self.subTest(office=office, lang=lang, view=view):
                        path = 'users.php' if view == 'users' else 'workspace.php'
                        status, html = self.get(f'/{path}?office={office}&lang={lang}&view={view}')
                        self.assertEqual(status, 200, html[:500])
                        self.assertIn('SYNTHETIC', html)
                        self.assertIn('read-only', html)
                        self.assertIn(currency, html)
                        self.assertIn(f'lang="{lang}"', html)
                        self.assertIn('office=' + office, html)
                        self.assertIn('workspace.php?', html)
                        self.assertNotIn('aria-disabled="true"', html)

    def test_drafts_and_original_money_remain_visible(self):
        for office, currency, amount in [('egypt', 'EGP', '120.50'), ('china', 'CNY', '88.20')]:
            status, html = self.get(f'/workspace.php?office={office}&lang=en&view=expenses')
            self.assertEqual(status, 200)
            self.assertIn(amount + ' ' + currency, html)
            self.assertIn('Draft', html)
            self.assertIn('SYNTHETIC', html)

    def test_post_unknown_routes_and_private_source_are_refused(self):
        self.assertEqual(self.get('/workspace.php', b'action=post')[0], 405)
        self.assertEqual(self.get('/users.php', b'action=assign')[0], 405)
        for path in ['/main.inc.php', '/.git/config', '/custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeFinance.php']:
            self.assertEqual(self.get(path)[0], 404)
        self.assertEqual(self.get('/workspace.php?office=unknown&view=expenses')[0], 403)

    def test_local_assets_and_actual_js_are_available(self):
        for asset in ['workspace.js', 'workspace.css', 'employee.css', 'staff.css', 'assets/fontawesome.css']:
            status, body = self.get('/custom/chinaplanetofficeexpenses/' + asset)
            self.assertEqual(status, 200)
            self.assertTrue(body)

    def test_stable_detail_and_three_copy_receipt_print(self):
        ref = hashlib.md5(b'm121-synthetic-preview:china:receipt').hexdigest()
        status, html = self.get('/workspace.php?office=china&lang=en&view=receipts&edit=' + ref + '&print=1')
        self.assertEqual(status, 200)
        self.assertEqual(html.count('class="receipt-slip"'), 3)
        self.assertIn('250.10 CNY', html)
        self.assertIn('127.0.0.1:' + str(self.port), html)

    def test_host_validation_and_currency_separation(self):
        request = urllib.request.Request(self.url + '/health', headers={'Host': 'untrusted.example'})
        with self.assertRaises(urllib.error.HTTPError) as raised:
            urllib.request.urlopen(request)
        with raised.exception as response:
            self.assertEqual(response.code, 403)
        status, html = self.get('/workspace.php?office=china&lang=en&view=overview')
        self.assertEqual(status, 200)
        self.assertIn('789.01 CNY', html)
        self.assertIn('19.45 USD', html)
        self.assertNotIn('100.30 EGP', html)

    def test_financial_export_uses_existing_exporter_with_no_posted_rows(self):
        status, csv = self.get('/workspace.php?office=china&lang=en&view=reports&export=financial_csv')
        self.assertEqual(status, 200)
        self.assertNotIn('<html', csv)
        self.assertEqual(len(csv.splitlines()), 1)


if __name__ == '__main__':
    unittest.main(verbosity=2)
