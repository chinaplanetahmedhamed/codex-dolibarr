"""Fixed loopback source UI/evidence adapter, no native PHP/session/DB or business writes."""
from pathlib import Path
from http.server import ThreadingHTTPServer
from urllib.parse import urlsplit,parse_qs,urlencode
import importlib.util,json,os
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'outputs/M121/local-assembly-2026-11-03';WEB=OUT/'preview'
spec=importlib.util.spec_from_file_location('boards_fixture',Path(__file__).with_name('m121_boards_shipping_preview.py'));boards=importlib.util.module_from_spec(spec);spec.loader.exec_module(boards)
boards.CAPTURE_DIR=OUT/'screenshots';boards.CAPTURE_NAMES=('assembly-home','assembly-boards','assembly-office');boards.CAPTURE_DIR.mkdir(exist_ok=True);boards.shipping_fixture.ROOT=ROOT
# Every spawned synthetic helper uses candidate-local disposable files.
os.environ['TMPDIR']=str(OUT/'tmp')
class Handler(boards.Handler):
 def send(self,data,status=200,kind='application/json; charset=utf-8'):
  if urlsplit(self.path).path=='/context_api.php' and isinstance(data,str):
   try:
    payload=json.loads(data)
    if payload.get('ok')and payload.get('data',{}).get('context',{}).get('shipment'):
     journey=json.loads((OUT/'assembled-journey.json').read_text());ctx=payload['data']['context'];ctx['customer']={'id':7,'label':'Synthetic journey customer'};ctx['project']={'id':11,'label':'P11 · Synthetic journey project'};ctx['shipment']={'id':17,'label':'SYN-M121-INTEGRATED','tracking':journey['finance']['phases'][4]['data']};data=json.dumps(payload)
   except (ValueError,KeyError):pass
  return super().send(data,status,kind)
 def do_GET(self):
  u=urlsplit(self.path);fixed={'/':'index.html','/office-en.html':'office-en.html','/office-ar.html':'office-ar.html','/documents-en.html':'documents-en.html','/documents-ar.html':'documents-ar.html'}
  fixed['/native-statements.html']='native-statements.html'
  fixed['/financial-history.html']='financial-history.html'
  fixed['/supplier-obligations.html']='supplier-obligations.html'
  if u.path=='/journey.json':return self.send((OUT/'assembled-journey.json').read_bytes(),kind='application/json')
  if u.path.endswith('/custom/chinaplanetdocuments/index.php'):
   q=parse_qs(u.query)
   if any(q.get(k)!=[v]for k,v in {'socid':'7','projectid':'11','shipmentid':'17'}.items()):return self.send('Synthetic context unavailable',403,'text/plain')
   return self.send((WEB/'documents-en.html').read_bytes(),kind='text/html; charset=utf-8')
  if u.path in fixed:return self.send((WEB/fixed[u.path]).read_bytes(),kind='text/html; charset=utf-8')
  if u.path=='/boards.php':
   query=parse_qs(u.query);query['finance']=['0'];self.path=u.path+'?'+urlencode(query,doseq=True) # do not present unrelated manual-ledger fixture totals as this journey's funds

  return super().do_GET()
server=ThreadingHTTPServer(('127.0.0.1',18780),Handler);server.source_root=ROOT
print('M121 unified source fixture http://127.0.0.1:18780/ (loopback only; no native runtime)',flush=True)
server.serve_forever()
