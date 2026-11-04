"""Read-only source-render snapshots, never an authenticated ERP or historical ledger."""
from pathlib import Path
import html
ROOT=Path(__file__).resolve().parents[2]
SOURCE=ROOT/'outputs/M121/history-coverage-2026-11-04/rendered'
WEB=ROOT/'outputs/M121/local-assembly-2026-11-03/preview'
css='\n'.join((ROOT/'custom/chinaplanetcustomercenter/css'/name).read_text() for name in ['admin_dashboard.css','ux_fixes.css'])
frames=[]
for name,title,direction in [('historical','Past date · English','ltr'),('historical-ar','Past date · Arabic','rtl'),('invalid-date','Invalid calendar date · English','ltr')]:
    body=(SOURCE/(name+'.html')).read_text()
    assert 'data-history-coverage="unavailable"' in body and 'cpad-kpi' not in body and '<table' not in body
    content='<html dir="'+direction+'"><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; form-action \'none\'; base-uri \'none\'"><style>'+css+'</style></head><body>'+body+'</body></html>'
    frames.append('<details'+(' open' if name=='historical' else '')+'><summary>'+html.escape(title)+'</summary><iframe sandbox="" title="'+html.escape(title)+'" srcdoc="'+html.escape(content,quote=True)+'"></iframe></details>')
page='<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>M121 historical coverage source preview</title><style>body{font:16px/1.5 system-ui;margin:0;background:#f4f6fa;color:#172b3b}main{max-width:1160px;margin:32px auto;padding:0 16px}.note{background:#fff7da;padding:16px;border-radius:8px}details{margin:18px 0;background:white;border:1px solid #dce2eb;border-radius:8px}summary{padding:14px;cursor:pointer}iframe{border:0;width:100%;height:780px;pointer-events:none}a{color:#125ea5}</style><main><h1>M121 · historical coverage guard</h1><p class="note">Read-only actual PHP page render with synthetic bootstrap/rights/SQLite fixtures. Unsupported dates show unavailable with no financial reads or totals. This does not reconstruct history and is not authenticated backup acceptance. Forms and links inside snapshots are disabled; no native top bar/session is represented.</p><p><a href="/">Back to local integration evidence</a></p>'+''.join(frames)+'</main></html>'
WEB.mkdir(parents=True,exist_ok=True)
p=WEB/'financial-history.html';p.write_text(page);print(p)
