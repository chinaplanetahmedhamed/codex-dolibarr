#!/usr/bin/env python3
"""Synthetic cookie/CSRF HTTP acceptance. No native config, credentials or real rows."""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
FIXTURE = ROOT / "deploy/tests/fixtures/m119_http"

def prepare(state):
    state.mkdir(mode=0o700, parents=True, exist_ok=True)
    if any(state.iterdir()):
        raise RuntimeError("Use a fresh empty synthetic fixture directory")
    (state / ".m119-ui-synthetic").write_text("M119 synthetic employee UI only")
    for name in ("tickets", "sessions"):
        (state / name).mkdir(mode=0o700)
    actors = {
        "egypt": {"id": 7, "entity": 1, "base": "EGP", "offices": {"egypt": ["EGP", "USD"]}},
        "other": {"id": 8, "entity": 1, "base": "EGP", "offices": {"egypt": ["EGP", "USD"]}},
        "china": {"id": 7, "entity": 2, "base": "CNY", "offices": {"china": ["CNY", "USD"]}},
        "denied": {"id": 9, "entity": 1, "base": "EGP", "allowed": False, "offices": {"egypt": ["EGP"]}},
        "external": {"id": 10, "socid": 99, "entity": 1, "base": "EGP", "offices": {"egypt": ["EGP"]}},
        "unconfigured": {"id": 11, "entity": 1, "base": "EGP", "unconfigured": True, "offices": {"egypt": ["EGP"]}},
        "native_expiry": {"id": 12, "entity": 1, "base": "EGP", "native_sanitizes_tokens": True, "offices": {"egypt": ["EGP"]}},
        "existing_csp": {"id": 13, "entity": 1, "base": "EGP", "existing_csp": True, "offices": {"egypt": ["EGP"]}},
        "menu_admin": {"id": 1, "admin": 1, "entity": 1, "base": "EGP", "offices": {"egypt": ["EGP"]}},
        "module_disabled": {"id": 1, "admin": 1, "entity": 1, "base": "EGP", "module_disabled": True, "offices": {"egypt": ["EGP"]}},
        "native_only": {"id": 14, "entity": 1, "base": "EGP", "own_allowed": False, "offices": {"egypt": ["EGP"]}},
    }
    (state / "actors.json").write_text(json.dumps(actors))

def ticket(state, actor):
    value = secrets.token_hex(24)
    (state / "tickets" / value).write_text(actor)
    return value

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
    def request(self, path, data=None, method=None, binary=False):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        req = urllib.request.Request(self.base + path, body, method=method)
        try:
            response = self.opener.open(req, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        payload = response.read()
        return response.code, response.headers, payload if binary else payload.decode()
    def sign_in(self, state, actor):
        assert self.request("/__fixture/session?ticket=" + ticket(state, actor))[0] == 303

def fields(html):
    # Only the POST draft form; register filters have their own GET-only hidden fields.
    form = re.search(r'<form[^>]*data-draft-form[^>]*>(.*?)</form>', html, re.S)
    assert form, "Draft form is missing"
    values = dict(re.findall(r'<input type="hidden" name="([^"]+)" value="([^"]*)"', form.group(1)))
    return {**values, "kind": "expense_claim", "office": "egypt", "amount": "700.10", "currency": "EGP", "occurred_on": "2026-10-31", "source_ref": "SYNTHETIC HTTP receipt", "purpose": "SYNTHETIC employee seven", "quote_rate": "", "quote_date": "", "quote_source": "", "settles_ref": ""}

def run_tests(state, base):
    count = 0
    guest = Client(base)
    assert guest.request("/index.php")[0] == 401
    assert guest.request("/main.inc.php")[0] == 404
    count += 1
    employee = Client(base); employee.sign_in(state, "egypt")
    staff_route = "/custom/chinaplanetofficeexpenses/users.php"
    assert guest.request(staff_route)[0] == 401
    for data in (None, {"action": "assign", "staff_id": "8", "office": "egypt", "token": "forged"}):
        status, _, body = employee.request(staff_route, data)
        assert status == 403 and "not activated" in body and "<form" not in body
    count += 1
    for actor in ("module_disabled", "native_only"):
        denied = Client(base); denied.sign_in(state, actor)
        for path in ("/index.php", "/custom/chinaplanetofficeexpenses/index.php", "/custom/chinaplanetoperations/office_expenses/index.php"):
            denied_status, _, denied_html = denied.request(path)
            assert denied_status == 403 and '<form ' not in denied_html
    count += 1
    status, headers, html = employee.request("/index.php")
    assert status == 200 and "no-store" in headers["Cache-Control"] and "form-action 'self'" in headers["Content-Security-Policy"]
    assert headers["Content-Security-Policy"] == "default-src 'none'; script-src 'self' 'nonce-synthetic-nonce'; style-src 'self' 'nonce-synthetic-nonce'; style-src-attr 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'"
    assert html.count("<!doctype html>") == 1 and html.count("<html ") == 1
    assert 'id="id-top" data-mainmenu="chinaplanetexpenses"' in html
    assert 'class="office-expenses-app" lang="en" dir="ltr"' in html and "Finens SaaS" in html
    stylesheets = re.findall(r'<link rel="stylesheet" href="([^"]+)"', html)
    assert len(stylesheets) == 3 and all("chinaplanetofficeexpenses/" in href for href in stylesheets)
    for href in stylesheets:
        asset_path = urllib.parse.urlparse(href).path + (("?" + urllib.parse.urlparse(href).query) if urllib.parse.urlparse(href).query else "")
        assert employee.request(asset_path)[0] == 200
    assert "I paid an expense" in html and 'value="china"' not in html
    csp_client = Client(base); csp_client.sign_in(state, "existing_csp")
    _, configured_headers, _ = csp_client.request("/index.php")
    assert configured_headers["Content-Security-Policy"] == "default-src 'self'; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'"
    count += 1
    body = fields(html)
    status, response, _ = employee.request("/index.php", body)
    assert status == 303
    location = response["Location"]
    assert employee.request("/" + location)[0] == 200
    assert employee.request("/index.php", body)[1]["Location"] == location
    connection = sqlite3.connect(state / "drafts.sqlite")
    assert connection.execute("SELECT COUNT(*) FROM oe_draft").fetchone()[0] == 1
    for path in ("/custom/chinaplanetofficeexpenses/index.php", "/custom/chinaplanetoperations/office_expenses/index.php"):
        assert employee.request(path, body)[1]["Location"] == location
        assert "SYNTHETIC employee seven" in employee.request(path)[2]
    assert connection.execute("SELECT COUNT(*) FROM oe_draft").fetchone()[0] == 1
    count += 1
    bad = {**body, "token": "expired", "submission_key": "synthetic-csrf-denied"}
    assert employee.request("/index.php", bad)[0] == 403
    assert employee.request("/index.php", {**body, "action": "sync_office_menu"})[0] == 403
    assert connection.execute("SELECT COUNT(*) FROM oe_draft").fetchone()[0] == 1
    count += 1
    menu_admin = Client(base); menu_admin.sign_in(state, "menu_admin")
    status, _, menu_html = menu_admin.request("/index.php")
    assert status == 200 and 'name="action" value="sync_office_menu"' in menu_html
    menu_form = re.search(r'<form class="menu-sync-prompt"[^>]*>(.*?)</form>', menu_html, re.S)
    assert menu_form and 'name="token"' in menu_form.group(1)
    token = re.search(r'name="token" value="([^"]+)"', menu_form.group(1)).group(1)
    assert menu_admin.request("/index.php", {"action": "sync_office_menu", "token": "expired"})[0] == 403
    menu_db = sqlite3.connect(state / "menus.sqlite")
    assert menu_db.execute("SELECT COUNT(*) FROM menu").fetchone()[0] == 0
    status, headers, _ = menu_admin.request("/index.php", {"action": "sync_office_menu", "token": token})
    assert status == 303
    _, _, menu_html = menu_admin.request("/" + headers["Location"])
    assert 'name="action" value="sync_office_menu"' not in menu_html
    assert menu_db.execute("SELECT COUNT(*) FROM menu").fetchone()[0] == 1
    menu_db.close()
    count += 1
    assert employee.request("/index.php", {**body, "amount": "701"})[0] == 409
    assert employee.request("/index.php", {**body, "owner_id": "8"})[0] == 422
    assert employee.request("/index.php", {**body, "office": "china"})[0] == 403
    assert employee.request("/index.php", {**body, "action": "post"})[0] == 403
    status, _, invalid_filter = employee.request("/index.php?from=2026-02-30")
    assert status == 400 and "Warning" not in invalid_filter and "Fatal error" not in invalid_filter
    assert re.fullmatch(r"[a-f0-9]{32}", fields(invalid_filter)["submission_key"])
    assert employee.request("/custom/chinaplanetofficeexpenses/employee.js")[0] == 200
    status, _, font = employee.request("/custom/chinaplanetofficeexpenses/assets/fa-solid-900.woff2", binary=True)
    assert status == 200 and font.startswith(b"wOF2")
    assert employee.request("/assets/private.php")[0] == 404
    count += 1
    other = Client(base); other.sign_in(state, "other")
    other_form = fields(other.request("/index.php")[2]); other_form["purpose"] = "SYNTHETIC private employee eight"
    assert other.request("/index.php", other_form)[0] == 303
    draft_id = urllib.parse.parse_qs(urllib.parse.urlparse(location).query)["saved"][0]
    assert other.request("/index.php?draft=" + draft_id)[0] == 404
    html = employee.request("/index.php?entity=2&owner_id=8")[2]
    assert "private employee eight" not in html
    count += 1
    china = Client(base); china.sign_in(state, "china")
    html = china.request("/index.php")[2]
    assert 'value="china"' in html and 'value="egypt"' not in html and "employee seven" not in html
    china_form = {**fields(html), "office": "china", "currency": "CNY", "amount": "180.29", "purpose": "SYNTHETIC China"}
    assert china.request("/index.php", china_form)[0] == 303
    count += 1
    for actor, expected in (("denied", 403), ("external", 403), ("unconfigured", 503)):
        client = Client(base); client.sign_in(state, actor)
        status, _, text = client.request("/index.php")
        assert status == expected and '<form ' not in text
    count += 1
    html = employee.request("/index.php?lang=ar")[2]
    assert 'dir="rtl"' in html and "حفظ المسودة" in html
    assert employee.request("/index.php", method="DELETE")[0] == 405
    html = employee.request("/index.php")[2]
    malicious = {**fields(html), "purpose": "<script>alert(1)</script>", "submission_key": "synthetic-http-xss"}
    assert employee.request("/index.php", malicious)[0] == 303
    html = employee.request("/index.php")[2]
    assert '<script>' not in html and '&lt;script&gt;' in html
    count += 1
    assert connection.execute("SELECT COUNT(*) FROM oe_draft").fetchone()[0] == 4
    assert connection.execute("SELECT COUNT(*) FROM oe_audit").fetchone()[0] == 4
    native_expiry = Client(base); native_expiry.sign_in(state, "native_expiry")
    native_form = fields(native_expiry.request("/index.php")[2])
    status, _, text = native_expiry.request("/index.php", {**native_form, "token": "expired"})
    assert status == 403 and "protection expired" in text and "Warning" not in text and "TypeError" not in text
    assert connection.execute("SELECT COUNT(*) FROM oe_draft").fetchone()[0] == 4
    count += 1
    connection.close()
    print(f"{count} HTTP scenarios passed; synthetic sessions only, no native authentication claim")

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--prepare", type=Path, help="Prepare a fresh persistent synthetic review fixture and exit")
    parser.add_argument("--port", type=int, default=8849)
    args = parser.parse_args()
    if args.prepare:
        state = args.prepare.resolve(); prepare(state)
        (state / "review-url.txt").write_text(f"http://127.0.0.1:{args.port}/__fixture/session?ticket=" + ticket(state, "egypt"))
        print("Prepared synthetic review fixture; one-use review URL stored in review-url.txt")
        return
    with tempfile.TemporaryDirectory(prefix="m119-http-") as directory:
        state = Path(directory); prepare(state)
        with socket.socket() as port_socket:
            port_socket.bind(("127.0.0.1", 0)); port = port_socket.getsockname()[1]
        log = open(state / "server.log", "w+")
        env = {**os.environ, "M119_UI_STATE": str(state)}
        server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(FIXTURE), str(FIXTURE / "router.php")], env=env, stdout=log, stderr=log)
        try:
            base = f"http://127.0.0.1:{port}"
            for _ in range(40):
                try:
                    Client(base).request("/index.php"); break
                except urllib.error.URLError:
                    time.sleep(0.05)
            run_tests(state, base)
        except Exception:
            log.flush(); log.seek(0)
            # Do not print URLs containing one-use fixture tickets.
            for line in log:
                if "ticket=" not in line:
                    print(line.rstrip())
            raise
        finally:
            server.terminate(); server.wait(timeout=5); log.close()

if __name__ == "__main__":
    main()
