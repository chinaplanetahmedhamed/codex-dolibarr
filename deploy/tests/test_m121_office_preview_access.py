#!/usr/bin/env python3
"""Fresh-browser access to disposable loopback-only synthetic Office previews."""
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import test_m119_employee_http as fixture

def main():
    with tempfile.TemporaryDirectory(prefix="m121-preview-access-") as directory:
        state = Path(directory)
        fixture.prepare(state)
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        env = {**os.environ, "M119_UI_STATE": str(state)}
        server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(fixture.FIXTURE), str(fixture.FIXTURE / "router.php")], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            base = f"http://127.0.0.1:{port}"
            for _ in range(40):
                try:
                    fixture.Client(base).request("/index.php")
                    break
                except urllib.error.URLError:
                    time.sleep(0.05)
            guest = fixture.Client(base)
            status, _, body = guest.request("/index.php?lang=ar&view=advances")
            assert status == 401 and '/__fixture/preview' in body, "Fresh browser needs an explicit synthetic preview entry link"
            status, headers, body = guest.request("/__fixture/preview")
            assert status == 200 and headers.get("X-M121-Synthetic-Preview") == "1", "Synthetic preview landing must be identifiable"
            assert 'SYNTHETIC LOCAL PREVIEW' in body
            links = re.findall(r'href="(/__fixture/session\?ticket=[a-f0-9]{48})"', body)
            assert len(links) == 2 and 'Egypt / مصر' in body and 'China / الصين' in body
            for link, office, currency in zip(links, ["egypt", "china"], ["EGP", "CNY"]):
                employee = fixture.Client(base)
                assert employee.request(link)[0] == 303
                status, _, page = employee.request('/index.php?lang=ar&view=advances')
                assert status == 200 and f'data-base="{currency}"' in page and f'value="{office}"' in page
                assert 'No linked approval status' not in page and 'لا توجد حالة موافقة مرتبطة' in page
                assert guest.request(link)[0] == 401, "One-use synthetic tickets must not be reusable"
            assert guest.request('/main.inc.php')[0] == 404
            with sqlite3.connect(state / 'drafts.sqlite') as db:
                assert db.execute('SELECT COUNT(*) FROM oe_draft').fetchone()[0] == 0
            print('7 preview access checks passed; fresh-browser Egypt/China sessions, one-use tickets, no records/posting')
        finally:
            server.terminate()
            server.wait(timeout=5)

if __name__ == '__main__':
    main()
