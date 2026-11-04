#!/usr/bin/env python3
"""Loopback-only, synthetic card adapter preview. No Dolibarr runtime, database or native identity."""
import argparse
import json
import subprocess
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse
ROOT = Path(__file__).resolve().parents[2]
ASSETS = {'/card_operation.js': ('custom/chinaplanetoperations/js/card_operation.js','text/javascript'), '/card_operation.css': ('custom/chinaplanetoperations/css/card_operation.css','text/css')}
class Handler(BaseHTTPRequestHandler):
    def send(self, data, mime='text/html; charset=utf-8', status=200):
        self.send_response(status); self.send_header('Content-Type', mime); self.send_header('Cache-Control','no-store'); self.end_headers(); self.wfile.write(data.encode() if isinstance(data,str) else data)
    def do_POST(self): self.send('Synthetic preview denies writes',status=405)
    def do_GET(self):
        url=urlparse(self.path); q=parse_qs(url.query); ar=q.get('lang',['en'])[0]=='ar'; mode=q.get('mode',['normal'])[0]
        if url.path in ASSETS:
            path,mime=ASSETS[url.path]; self.send((ROOT/path).read_bytes(),mime); return
        if url.path=='/context_api.php':
            if mode in ('fail','denied'):
                self.send(json.dumps({'ok':False,'error':'Synthetic failure'}),'application/json',403 if mode=='denied' else 422); return
            cmd=['php',str(ROOT/'deploy/tests/test_m121_shipment_context.php'),'--fixture']
            if ar: cmd.append('--ar')
            if mode=='delivered': cmd.append('--delivered')
            tracking=json.loads(subprocess.check_output(cmd,cwd=ROOT))
            context={'version':1,'customer':{'id':23,'label':'Synthetic customer'},'project':{'id':31,'label':'Synthetic project'},'shipment':{'id':17,'label':'SYNTH-17','tracking':tracking},'restricted':False}
            if mode=='restricted': context.update(customer=None,project=None,shipment=None,restricted=True)
            if mode=='none': context['shipment']=None
            self.send(json.dumps({'ok':True,'data':{'context':context,'permissions':{'link':False,'readDocuments':True,'uploadDocuments':False,'archiveDocuments':False},'documents':[],'events':[]}}),'application/json'); return
        if url.path!='/': self.send('Synthetic route only',status=404); return
        self.send('''<!doctype html><html lang="LANG" dir="DIR"><meta name="viewport" content="width=device-width,initial-scale=1"><title>M121 synthetic shipping card</title>
<link rel="stylesheet" href="/card_operation.css"><style>body{margin:0;padding:16px;font:16px system-ui;background:#f5f5f5;color:#222}*{box-sizing:border-box}main{max-width:960px;margin:auto;--card-surface:white;--card-muted:#555;--card-soft-line:#ccc;--card-ink:#222;--card-bg:white}button{min-height:44px;margin:4px}h1{font-size:20px}</style>
<h1>Synthetic fixture · no native authentication/database</h1><button id="refresh">Simulate source update</button><button id="fail">Simulate failed refresh</button><button id="deny">Simulate denied refresh</button>
<script>let fixtureMode=new URLSearchParams(location.search).get('mode')||'normal';const nativeFetch=window.fetch.bind(window);window.fetch=(url,options)=>{const u=new URL(url,location.href);u.searchParams.set('mode',fixtureMode);u.searchParams.set('lang',document.documentElement.lang);return nativeFetch(u,options)};for(const [id,mode]of [['refresh','delivered'],['fail','fail'],['deny','denied']])document.getElementById(id).onclick=()=>{fixtureMode=mode;window.dispatchEvent(new Event('focus'))};</script>
<script id="cbo-card-config" type="application/json">{"operationEnabled":true,"id":41,"token":"synthetic-only","canFiles":false}</script>
<main class="cpt-rich-card cbo-ready cpo-board-card"><div class="cbo-card-toolbar">Synthetic task title · shipment document owner unchanged</div><div class="cbo-card-content"></div><div class="cbo-add-items cbo-floating-menu"></div></main><script defer src="/card_operation.js"></script></html>'''.replace('LANG','ar' if ar else 'en').replace('DIR','rtl' if ar else 'ltr'))
    def log_message(self,*args): pass
if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--port',type=int,default=18831);args=parser.parse_args()
    print(f'Synthetic preview: http://127.0.0.1:{args.port}/',flush=True)
    HTTPServer(('127.0.0.1',args.port),Handler).serve_forever()
