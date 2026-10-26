"""Negative contracts for the localhost synthetic harness; not native CSRF acceptance."""
import json
import os
import sqlite3
import urllib.error
import urllib.parse
import urllib.request

if os.environ.get('M116_HTTP_FIXTURE') != '1':
    raise SystemExit('Refused: M116_HTTP_FIXTURE=1 required')
fixture = os.environ.get('M116_FIXTURE_DB', '')
if not fixture.startswith('/tmp/m116-') or not fixture.endswith('.sqlite'):
    raise SystemExit('A known /tmp/m116-*.sqlite synthetic fixture is required')
db = sqlite3.connect('file:' + fixture + '?mode=ro', uri=True)
before = db.execute('SELECT COUNT(*) FROM llx_cpo_account_entry').fetchone()[0]
url = 'http://127.0.0.1:8765/tasks/account_api.php?customer=1'
checks = [
    ('GET', None, 405),
    ('POST', {'action': 'save', 'request': 'csrf-negative-00001', 'payload': '{}', 'token': 'invalid'}, 403),
    ('POST', {'action': 'save', 'request': 'validation-negative-00001', 'payload': '{', 'token': 'synthetic-only-csrf-token'}, 422),
]
for method, payload, expected in checks:
    request = urllib.request.Request(url, data=urllib.parse.urlencode(payload).encode() if payload else None, method=method)
    try:
        urllib.request.urlopen(request, timeout=5)
        raise AssertionError('Unexpected success')
    except urllib.error.HTTPError as error:
        assert error.code == expected, (error.code, expected)
assert before == db.execute('SELECT COUNT(*) FROM llx_cpo_account_entry').fetchone()[0]
print('PASS synthetic HTTP: GET 405, invalid fixture token 403, malformed payload 422; entry count unchanged. Native CSRF unverified.')
