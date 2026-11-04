"""Current supplier source renders from a disposable native journey; never connects to ERP."""
import hashlib
import html
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
EVIDENCE = ROOT / 'outputs/M121/local-assembly-2026-11-03/native-v8-supplier-obligations'
OUTPUT = ROOT / 'outputs/M121/supplier-obligation-integration-2026-11-04'
WEB = ROOT / 'outputs/M121/local-assembly-2026-11-03/preview'
data = json.loads((EVIDENCE / 'native-statement-permissions.json').read_text())
assert data['status'] == 'passed' and data['failed'] == 0
css = (ROOT / 'custom/chinaplanetoperations/css/operations.css').read_text()
cards = []
renders = []
for locale, direction in [('en_US', 'ltr'), ('ar_SA', 'rtl')]:
    for index in range(1, 4):
        key = f'supplier_obligation_{locale}_{index}'
        source = data['views'][key]
        start = source.index('<section class="cpo-supplier-position"')
        end = source.index('</section>', start) + len('</section>')
        body = source[start:end]
        assert 'recorded_payments_only' in body and '<form' not in body
        # Snapshot links retain their visible native references but cannot navigate.
        frame = ('<!doctype html><html lang="' + locale + '" dir="' + direction
                 + '"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                 + '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; form-action \'none\'; base-uri \'none\'">'
                 + '<style>body{margin:0;font:16px/1.5 system-ui;color:#172b3b;background:white}a{pointer-events:none;color:#125ea5}'
                 + css + '</style><body>' + body + '</body></html>')
        title = f'{locale} · supplier {index}'
        cards.append('<details' + (' open' if index == 1 else '') + '><summary>' + title
                     + '</summary><iframe sandbox="" title="' + title + '" srcdoc="'
                     + html.escape(frame, quote=True) + '"></iframe></details>')
        renders.append({'key': key, 'source_sha256': hashlib.sha256(source.encode()).hexdigest(),
                        'section_sha256': hashlib.sha256(body.encode()).hexdigest()})
page = ('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        '<title>M121 supplier obligation source preview</title><style>body{margin:0;font:16px/1.5 system-ui;background:#f4f6fa;color:#172b3b}'
        'main{max-width:1100px;margin:24px auto;padding:0 12px}.note{padding:14px;background:#fff4d5;border-radius:8px}'
        'details{margin:16px 0;border:1px solid #dce2eb;border-radius:8px;background:white}summary{padding:14px;cursor:pointer}'
        'iframe{border:0;width:100%;height:1040px}a{color:#125ea5}</style><main><h1>M121 · linked supplier obligations</h1>'
        '<p class="note">Actual PHP statement sections rendered through the native supplier service and synthetic readonly identities in a fresh socket-only MySQL fixture. '
        'These are three separate linked operations, not an aggregated vendor balance. No authenticated ERP session or native top bar is represented. Snapshot source links are inactive.</p>'
        '<p>Native invoice totals: USD 300 / 250 / 200. Recorded payments: USD 203 / 150 / 100. Remaining after recorded payments: USD 97 / 100 / 100. '
        'The first payment includes the existing three USD 1 recovery probes. Credits, deposits and closure adjustments remain outside this limited remainder; no customer-funded attribution is inferred.</p>'
        '<p><a href="/">Back to integration evidence</a></p>' + ''.join(cards) + '</main></html>')
WEB.mkdir(parents=True, exist_ok=True)
(WEB / 'supplier-obligations.html').write_text(page)
OUTPUT.mkdir(parents=True, exist_ok=True)
(OUTPUT / 'preview-source.json').write_text(json.dumps({'native_evidence': str(EVIDENCE.relative_to(ROOT)),
    'native_views': renders, 'preview_sha256': hashlib.sha256(page.encode()).hexdigest(),
    'scope': 'static actual PHP sections; synthetic native fixture; no authenticated runtime'}, indent=2) + '\n')
print('Local source preview generated: /supplier-obligations.html')
