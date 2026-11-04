#!/usr/bin/env python3
"""Loopback-only synthetic UI fixture; serves unmodified source assets, never PHP/DB."""
import argparse, html, json, mimetypes, base64, binascii, os, re, importlib.util, subprocess, datetime
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from pathlib import Path
from urllib.parse import urlsplit, parse_qs

DEFAULT = str(Path(__file__).resolve().parents[2])
ASSETS = {
 '/boards.js':'custom/chinaplanetoperations/js/boards.js',
 '/boards.css':'custom/chinaplanetoperations/css/boards.css',
 '/kanban_pointer.js':'custom/chinaplanetcustomercenter/js/kanban_pointer.js',
}
OPTIONAL_ASSETS = {('/'+name):'custom/chinaplanetoperations/js/'+name for name in ('boards_live_runtime.js','boards_sounds.js','boards_geometry.js')}
# Read only fixed public core-theme sources; never include PHP, config, or database code.
NATIVE_CORE=Path('/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1/htdocs')
NATIVE_FONT=NATIVE_CORE/'theme/common/fontawesome-5/webfonts/fa-solid-900.woff2'
def native_chrome(lang):
 raw=(NATIVE_CORE/'theme/eldy/theme_vars.inc.php').read_text()
 def value(key):
  match=re.search(r"^\$"+re.escape(key)+r"\s*=\s*'([0-9, .a-z]+)'",raw,re.M)
  if not match: raise ValueError('Missing fixed native theme default: '+key)
  return match.group(1)
 colors={key:'rgb('+value(key)+')' for key in ('colorbackhmenu1','colorbackvmenu1','colorbacklinepairhover','colorbackbody','colortopbordertitle1','colortext','colortextlink')}
 for key,back in [('colortextbackhmenu','colorbackhmenu1'),('colortextbackvmenu','colorbackvmenu1')]:
  dark=sum(int(x.strip()) for x in value(back).split(','))<=460
  colors[key]='#ffffff' if dark else '#222222'
 variables=':root{'+''.join('--'+key+':'+val+';' for key,val in colors.items())+'--inputbackgroundcolor:#fff}'
 css=variables+"""@font-face{font-family:NativeFixtureIcons;src:url('/native-fa-solid.woff2') format('woff2');font-weight:900;font-display:block}html,body{margin:0;font-family:arial,tahoma,verdana,helvetica;font-size:.94em;line-height:1.4}#mainmenutop{height:56px}#id-top{height:56px;background:var(--colorbackhmenu1);color:var(--colortextbackhmenu);display:flex;align-items:center;gap:12px;padding:0 10px;box-sizing:border-box}#id-top a{color:inherit;text-decoration:none}#id-top .tmenudiv{flex:1;min-width:0;overflow:auto;font-size:13px}#id-top ul.tmenu{display:flex;list-style:none;margin:0;padding:0;width:max-content}#id-top li.tmenu{text-align:center;min-width:68px;max-width:85px}#id-top .tmenucenter{padding:2px;height:50px}#id-top a.tmenulabel{display:flex;flex-direction:column;align-items:center;min-height:44px;padding:0 4px;white-space:nowrap}#id-top .native-menu-icon{font:900 23px/28px NativeFixtureIcons}#id-top .mainmenuaspan{font-size:13px;opacity:.9}#id-top a:focus-visible{outline:3px solid #fff;outline-offset:-3px}#id-top .preview-session{display:flex;align-items:center;gap:8px}#id-top .preview-avatar{border:1px solid currentColor;border-radius:50%;padding:7px 10px}#id-top .preview-chrome-note{font-size:11px;max-width:155px}#id-left{position:fixed;left:0;top:56px;bottom:0;width:240px;background:var(--colorbackvmenu1);color:var(--colortextbackvmenu);border-right:1px solid var(--colortopbordertitle1);overflow:auto;box-sizing:border-box;z-index:201;direction:ltr}#id-left .preview-native-menu{padding:0 12px;font-size:11px}#id-right{margin-left:240px}@media(max-width:760px){#id-left{display:none}#id-right{margin-left:0}#id-top{gap:5px;padding:0 5px}#id-top li.tmenu{min-width:60px}#id-top .preview-chrome-note{display:none}}"""
 icons=(NATIVE_CORE/'theme/common/fontawesome-5/css/all.css').read_text()
 entries=[('home','Home','الرئيسية'),('building','Third parties','الأطراف الأخرى'),('cubes','Products','المنتجات'),('project-diagram','Projects','المشروعات'),('calculator','Financial','المالية'),('tools','Tools','الأدوات')]
 items=[]
 for icon,en,ar in entries:
  code=re.search(r'\.fa-'+icon+r':before\s*\{\s*content:\s*"\\([a-f0-9]+)"',icons).group(1)
  title=ar if lang=='ar' else en
  items.append('<li class="tmenu"><div class="tmenucenter"><a class="tmenu tmenulabel" href="/native-preview-only"><span class="native-menu-icon" aria-hidden="true">&#x'+code+';</span><span class="mainmenuaspan">'+title+'</span></a></div></li>')
 header='<div class="tmenudiv"><ul class="tmenu" aria-label="Synthetic native top menu">'+''.join(items)+'</ul></div><div class="preview-session"><span class="preview-chrome-note">Local fixture · Dolibarr 24 defaults<br>Not an authenticated ERP session</span><span class="preview-avatar" aria-label="Synthetic user">S</span></div>'
 return '<style>'+css+'</style>',header

CAPTURE_DIR=Path(DEFAULT)/'docs/evidence/m121-upcoming-deadlines/screenshots'
CAPTURE_NAMES=('combined-en-desktop','combined-ar-desktop','combined-en-phone','combined-ar-phone')
def one(q,k,d=''): return q.get(k,[d])[0]
def number(v,d=0):
 try: return max(0,int(v))
 except (ValueError,TypeError): return d
def context(q):
 return dict(lang='ar' if one(q,'lang').startswith('ar') else 'en', role=one(q,'role','editor'), finance=one(q,'finance','1')!='0', user=number(one(q,'user','7'),7), entity=number(one(q,'entity','2'),2))
def language(root,lang):
 p=root/'custom/chinaplanetoperations/langs'/('ar_SA' if lang=='ar' else 'en_US')/'chinaplanetoperations.lang'
 values={}
 for line in p.read_text().splitlines():
  if '=' in line and not line.lstrip().startswith('#'):
   k,v=line.split('=',1); values[k.strip()]=v.strip()
 keys={'density':'BoardsDensity','displayDensity':'BoardsDisplayDensity','compact':'BoardsDensityCompact','comfortable':'BoardsDensityComfortable','priority':'Priority','sidebarMenu':'BoardsSidebarMenu','sidebarClose':'BoardsSidebarClose','sidebarNavigation':'BoardsSidebarNavigation','sidebarActions':'BoardsSidebarActions',**{'priority_'+p:'Priority_'+p for p in ('urgent','high','normal','low')}}
 keys.update({key:'BoardsWork'+value for key,value in {'dashboardTitle':'Dashboard','dashboardOpen':'Open','dashboardUrgent':'Urgent','dashboardOverdue':'Overdue','dashboardEmpty':'Empty','dashboardError':'Unavailable','dashboardNote':'Note','dashboardLimit':'Limit','dashboardMore':'More','dashboardUpcoming':'Upcoming','dashboardDue':'Due','dashboardNoDue':'NoDue','dashboardCustomer':'Customer','dashboardProject':'Project'}.items()})
 return {k:values.get(v,v) for k,v in keys.items()}
def seeds(c):
 ar=c['lang']=='ar'; edit=c['role'] in ('editor','manager')
 titles=['مراجعة مستندات الشحنة التجريبية الطويلة والتأكد من جميع التفاصيل قبل اعتماد الطلب','Review the fictional shipment documents and confirm packing quantities before approval','متابعة المشروع التجريبي مع فريق العمل — عنوان طويل متعدد اللغات EN/AR','Arrange synthetic inspection and prepare a clear summary','مهمة مكتملة للعرض فقط','No deadline — fictional task with a long reference']
 cards=[]
 for i,t in enumerate(titles):
  cards.append(dict(rowid=i+101,id=i+101,fk_task=i+101,fk_list=1+i//2,title=t,ref='SYN-TASK-'+str(i+101),priority=['urgent','high','normal','low','normal','high'][i],status='done' if i==4 else 'todo',task_version=1,version=1,editable=edit,can_complete=edit,date_deadline=None if i==5 else '2026-11-08 12:00:00',fk_user_assign=7,cover_asset_id=1 if i==0 else 0,details={'labels':[],'cover_color':'#73bfa5' if i==2 else ''},counters={'comments':2,'checklist_total':4,'checklist_done':1,'labels':[]},asset_count=1,erp={'customer':'عميل تجريبي · Fictional customer','project':'Synthetic shipment project · مشروع تجريبي','shipment':'SYN-SHIP-001'}))
 board=dict(rowid=1,title='مشروع تجريبي · Synthetic project board',version=1,role=c['role'],can_edit=edit,can_create_card=edit,archived=0,completed=False,background='#1c4a51',background_image='',workspace_project={'rowid':11,'fk_soc':7,'ref':'SYN-P11','title':'مشروع تجريبي · Fictional project','customer_name':'عميل تجريبي · Fictional customer'})
 lists=[dict(rowid=i+1,title=t,archived=0) for i,t in enumerate(['للتنفيذ · To do','قيد العمل · In progress','مراجعة طويلة الاسم · Review'])]
 customers=[dict(rowid=7+i,nom=n,priority=p,priority_version=1,starred=int(i==0),background=bg,background_image='',projects=2,boards=2,open_tasks=6,open_boards=1,done_boards=1,can_manage_priority=edit,can_disable_workspace=False) for i,(n,p,bg) in enumerate([('عميل افتراضي طويل الاسم · Fictional Aurora Trading','urgent','#234a70'),('شركة عرض تجريبية · Fictional Cedar Imports','high','#3e6655'),('Synthetic Horizon Workspace · مساحة تجريبية','normal','#634d78'),('عميل اختباري · Fictional Meadow','low','#576d76')])]
 return cards,board,lists,customers

class Handler(BaseHTTPRequestHandler):
 def log_message(self,fmt,*args): print('preview:',fmt%args,flush=True)
 def send(self,data,status=200,kind='application/json; charset=utf-8'):
  if not isinstance(data,bytes): data=data.encode()
  self.send_response(status); self.send_header('Content-Type',kind); self.send_header('Content-Length',str(len(data))); self.send_header('Cache-Control','no-store'); self.send_header('X-Content-Type-Options','nosniff'); self.send_header('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self'"); self.end_headers(); self.wfile.write(data)
 def reply(self,data,status=200): self.send(json.dumps(data,ensure_ascii=False),status)
 def request_context(self,q):
  # Synthetic UI context, never an authorization credential. Source fetches keep token.
  token=one(q,'token')
  if token.startswith('preview:'):
   try: return json.loads(token[8:])
   except (ValueError,TypeError): pass
  ref=parse_qs(urlsplit(self.headers.get('Referer','')).query)
  return context(q if any(k in q for k in ('role','lang','finance','user','entity')) else ref)
 def do_POST(self):
  if urlsplit(self.path).path=='/capture': return self.capture_save()
  try:
   size=int(self.headers.get('Content-Length','0'))
   if not 0<=size<=100000: return self.reply({'ok':False,'error':'Preview request too large'},413)
   data=parse_qs(self.rfile.read(size).decode())
  except (ValueError,UnicodeError): return self.reply({'ok':False,'error':'Malformed synthetic request'},400)
  if urlsplit(self.path).path.endswith('/boards_api.php') and one(data,'action')=='visit': return self.reply({'ok':True,'data':{}})
  self.reply({'ok':False,'error':'Local preview: business actions are disabled; no data was saved.'},403)
 def do_GET(self):
  u=urlsplit(self.path); q=parse_qs(u.query); path=u.path
  if path=='/capture': return self.capture_page()
  if path.startswith('/evidence/'):
   name=path.removeprefix('/evidence/').removesuffix('.jpg')
   file=CAPTURE_DIR/(name+'.jpg')
   if name not in CAPTURE_NAMES or not path.endswith('.jpg') or not file.is_file() or file.is_symlink(): return self.send('No fixed screenshot found',404,'text/plain')
   return self.send(file.read_bytes(),kind='image/jpeg')
  if path in ASSETS or path in OPTIONAL_ASSETS:
   p=self.server.source_root/(ASSETS.get(path) or OPTIONAL_ASSETS[path])
   if not p.is_file(): return self.send('Missing configured source asset',404,'text/plain')
   return self.send(p.read_bytes(),kind='text/javascript; charset=utf-8' if path.endswith('.js') else 'text/css; charset=utf-8')
  if path=='/native-fa-solid.woff2': return self.send(NATIVE_FONT.read_bytes(),kind='font/woff2')
  if path=='/favicon.ico': return self.send(b'',kind='image/x-icon')
  if path in ('/asset.php','/board_background.php','/workspace_background.php','/placeholder.svg'):
   return self.send('<svg xmlns="http://www.w3.org/2000/svg" width="900" height="240" viewBox="0 0 900 240"><rect width="900" height="240" fill="#467c8d"/><path d="M0 200L250 40 440 170 650 20 900 180V240H0Z" fill="#9ecdb9"/><text x="35" y="65" fill="white" font-size="28">Synthetic cover · Local preview</text></svg>',kind='image/svg+xml')
  c=self.request_context(q)
  if path.endswith('/boards_api.php'):
   if c['role']=='denied': return self.reply({'ok':False,'error':'Synthetic access denied (403).'},403)
   cards,board,lists,customers=seeds(c); action=one(q,'action'); edit=c['role'] in ('editor','manager')
   if action=='workspace': data=dict(board=board,lists=lists,cards=cards,members=[dict(fk_user=7,firstname='Synthetic',lastname='Reviewer',login='synthetic',role=c['role'])],people=[],grants=[],counts={'done_tasks':1,'open_tasks':5},personal=False)
   elif action in ('shared','search'): data={'tasks':cards}
   elif action=='mywork':
    today=datetime.date.today();cards[0]['date_deadline']=str(today-datetime.timedelta(days=1));cards[1]['date_deadline']=str(today);
    for card in cards[2:]: card['date_deadline']=str(today+datetime.timedelta(days=1))
    cards[2]['date_deadline']=str(today+datetime.timedelta(days=6));cards[3]['date_deadline']=str(today+datetime.timedelta(days=7));cards[5]['date_deadline']=None
    data={'mode':'summary','archived':False,'tasks':[{**t,'board':{'id':1,'title':board['title']},'list':{'id':t['fk_list'],'title':lists[t['fk_list']-1]['title']},'shipment':None,'customer':{'id':7,'label':'عميل تجريبي · Fictional customer'} if t['id']!=106 else None,'project':{'id':11,'title':'مشروع تجريبي · Fictional project','ref':'SYN-P11','label':'SYN-P11 · Fictional project'} if t['id']!=106 else None,'location':'available'} for t in cards]}
   elif action in ('customers','customer_projects'):
    data=dict(enhanced=True,customers=customers,customer={'rowid':7,'nom':customers[0]['nom']},projects=[dict(rowid=11,ref='SYN-P11',title=board['title'],open_tasks=5,done_tasks=1,board={**board,'starred':1},deadline={'date':'2026-11-08','display':'2026-11-08','indicator':'urgent'})],appearance={'background':'#1c4a51','background_image':'','appearance_version':1},can_manage_appearance=edit,can_create=edit,can_create_customer=False,can_create_project=False,people=[],teams=[])
   elif action in ('gallery','deleted_boards'): data={'boards':[{**board,'starred':1},{**board,'rowid':2,'title':'General synthetic board · لوحة عامة','background':'#4d5576','starred':0}],'can_create':edit,'teams':[],'people':[]}
   else: return self.reply({'ok':False,'error':'Unsupported synthetic read action'},403)
   return self.reply({'ok':True,'data':data})
  if path.endswith('/account_summary.php'):
   if not c['finance'] or c['role']=='denied': return self.reply({'ok':False,'error':'Synthetic finance access denied'},403)
   items=[]
   for raw in one(q,'scopes').split(',')[:20]:
    parts=raw.split(':')
    if len(parts)!=3 or not all(x.isdigit() for x in parts): return self.reply({'ok':False,'error':'Invalid synthetic scope'},400)
    customer,project,task=map(int,parts); allowed=customer in range(7,11) and project in (0,11) and task==0
    item={'scope':{'customer':customer,'project':project,'task':task},'available':allowed}
    if allowed: item['totals']={'base_currency':'USD','balance_minor':'125050','due_minor':'42025','currencies':{'USD':{'balance_minor':'100000','due_minor':'20000'},'CNY':{'balance_minor':'175050','due_minor':'22025'}}}
    items.append(item)
   return self.reply({'ok':True,'summaries':items})
  if path in ('/','/boards.php'): return self.page(q)
  # Card iframe and native navigation are deliberately fixture notices, never PHP.
  self.send('<!doctype html><meta charset="utf-8"><h1>Local preview · synthetic data</h1><p>Native routes and business actions are disabled.</p>',kind='text/html; charset=utf-8')
 def capture_page(self,notice=''):
  options=''.join('<option value="'+name+'">'+name+'</option>' for name in CAPTURE_NAMES)
  self.send('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Developer screenshot capture</title><h1>Developer capture tool · synthetic preview images only</h1><p>Local evidence utility. This is not a product feature.</p>'+notice+'<form method="post" action="/capture"><p><label>Screenshot name <select name="name">'+options+'</select></label></p><p><label>Screenshot data <textarea name="data" rows="8" cols="70" required></textarea></label></p><button type="submit">Save screenshot</button></form>',kind='text/html; charset=utf-8')
 def capture_save(self):
  try:
   size=int(self.headers.get('Content-Length','0'))
   if not 0<size<=6*1024*1024: raise ValueError('Screenshot request exceeds the fixed limit.')
   raw=self.rfile.read(size).decode('utf-8')
   if self.headers.get('Content-Type','').split(';')[0]=='application/json':
    payload=json.loads(raw)
    if not isinstance(payload,dict): raise ValueError('Screenshot JSON must be an object.')
    name=payload.get('name'); data=payload.get('data')
   else:
    payload=parse_qs(raw); name=one(payload,'name'); data=one(payload,'data')
   if name not in CAPTURE_NAMES or not isinstance(data,str): raise ValueError('Choose a fixed screenshot name and JPEG data.')
   if data.startswith('data:image/jpeg;base64,'): data=data[len('data:image/jpeg;base64,'):]
   image=base64.b64decode(data,validate=True)
   if not 0<len(image)<=4*1024*1024 or not image.startswith(b'\xff\xd8\xff') or not image.endswith(b'\xff\xd9'): raise ValueError('Only complete JPEG images up to 4 MB are accepted.')
   CAPTURE_DIR.mkdir(mode=0o700,parents=True,exist_ok=True)
   if CAPTURE_DIR.is_symlink(): raise ValueError('Screenshot directory must not be a symlink.')
   os.chmod(CAPTURE_DIR,0o700); target=CAPTURE_DIR/(name+'.jpg')
   fd=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_TRUNC|os.O_NOFOLLOW,0o600)
   with os.fdopen(fd,'wb') as saved: saved.write(image)
   os.chmod(target,0o600)
   self.capture_page('<p role="status">Saved screenshot: <a href="/evidence/'+name+'.jpg">'+name+'.jpg</a></p><p>'+html.escape(str(target))+'</p>')
  except (ValueError,TypeError,UnicodeError,binascii.Error,OSError) as error:
   self.send('<!doctype html><meta charset="utf-8"><h1>Screenshot not saved</h1><p>'+html.escape(str(error))+'</p>',400,'text/html; charset=utf-8')
 def page(self,q):
  c=context(q); ar=c['lang']=='ar'; mode=one(q,'view','boards'); mode=mode if mode in ('my','general','deleted') else 'boards'
  labels=language(self.server.source_root,c['lang'])
  native_sidebar=one(q,'sidebar','1')!='0'
  cfg={**c,'nativeSidebar':native_sidebar,'accountStatements':c['finance'] and c['role']!='denied','labels':labels,'request':'synthetic-preview-only','token':'preview:'+json.dumps(c),'board':number(one(q,'id')),'lang':c['lang'],'mode':mode,'customer':number(one(q,'customer')),'workspaces':True,'boardDelete':True,'completion':True}
  runtime_scripts=''.join('<script src="'+url+'"></script>' for url,rel in OPTIONAL_ASSETS.items() if (self.server.source_root/rel).is_file())
  escaped=json.dumps(cfg,ensure_ascii=False).replace('<','\\u003c').replace('>','\\u003e').replace('&','\\u0026')
  text=lambda en,arab:arab if ar else en
  page=f'''<!doctype html><html lang="{c['lang']}" dir="{'rtl' if ar else 'ltr'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Boards · Local synthetic preview</title><link rel="stylesheet" href="/boards.css"><style>html,body{{margin:0;font-family:Arial,sans-serif}}#id-top{{height:56px;background:#152536;color:white;display:flex;align-items:center;padding:0 14px;gap:12px;font-size:13px;box-sizing:border-box}}#id-top a{{color:#fff}}#id-top strong{{margin-inline-end:auto}}#cp-boards{{--cb-muted:#61708a}}</style></head><body class="cpo-board-canvas"><header id="id-top"><strong>Local preview · synthetic data</strong></header><main id="cp-boards" dir="{'rtl' if ar else 'ltr'}"><header class="cb-top"><a href="boards.php" class="cb-brand">▦ China Planet</a><input type="search" id="cb-search" placeholder="{text('Search boards and cards','بحث في اللوحات والبطاقات')}" aria-label="{text('Search','بحث')}"><a href="boards.php?view=my">{text('My Work','مهامي')}</a><button id="cb-shared">{text('Shared with me','مشترك معي')}</button><a href="inbox.php">{text('Notifications','التنبيهات')}</a><button id="cb-theme" aria-label="{text('Theme','المظهر')}">◐</button></header><div id="cb-content" aria-live="polite"></div><output id="cb-status" role="status"></output></main><dialog id="cb-dialog"><header><h2></h2><button type="button" data-dialog-close aria-label="{text('Close','إغلاق')}">×</button></header><div class="cb-dialog-body"></div></dialog><dialog id="cb-card-dialog"><header><strong>{text('Card details','تفاصيل البطاقة')}</strong><button type="button" data-card-close aria-label="{text('Close','إغلاق')}">×</button></header><iframe title="{text('Card details','تفاصيل البطاقة')}"></iframe></dialog><script type="application/json" id="cb-config">{escaped}</script><script src="/kanban_pointer.js"></script><script src="/boards.js"></script></body></html>'''
  page=page.replace('<script src="/boards.js"></script>',runtime_scripts+'<script src="/boards.js"></script>')
  if native_sidebar: page=page.replace('<body class="cpo-board-canvas">','<body class="cpo-board-canvas cpo-board-sidebar">')
  shell_css,native_header=native_chrome(c['lang'])
  page=page.replace('<strong>Local preview · synthetic data</strong>',native_header)
  page=page.replace('</head>',shell_css+'</head>')
  page=page.replace('<header id="id-top">','<div id="mainmenutop"><header id="id-top">')
  native='</div>'
  if native_sidebar:
   native+='<aside id="id-left" aria-label="Synthetic native Dolibarr left navigation"><div class="preview-native-menu"><small>Source-based native chrome fixture</small></div></aside><div id="id-right"><div class="fiche">'
  page=page.replace('</header><main id="cp-boards" dir=', '</header>'+native+'<main id="cp-boards" dir=')
  if native_sidebar: page=page.replace('</main><dialog','</main></div></div><dialog')
  self.send(page,kind='text/html; charset=utf-8')

# Compose the existing shipping fixture into the real Boards card iframe route.
# Both handlers remain synthetic; native PHP/authentication/database are never booted.
spec=importlib.util.spec_from_file_location('shipping_fixture',Path(__file__).with_name('m121_shipment_context_preview.py'))
shipping_fixture=importlib.util.module_from_spec(spec); spec.loader.exec_module(shipping_fixture)
shipping_fixture_source=Path(shipping_fixture.__file__).read_text()
fixture_controls=re.search(r'<script>(let fixtureMode=.*?)</script>',shipping_fixture_source,re.S).group(1)
BoardsHandler=Handler
class Handler(BoardsHandler):
 def send(self,data,status=200,kind='application/json; charset=utf-8'):
  # The reused shipping handler uses (data,mime,status); Boards uses (data,status,kind).
  if isinstance(status,str): status,kind=(kind if isinstance(kind,int) else 200),status
  if urlsplit(self.path).path=='/context_api.php' and status==200:
   payload=json.loads(data)
   c=payload['data']['context']
   if c.get('customer'): c['customer']={'id':7,'label':'عميل تجريبي · Fictional customer'}
   if c.get('project'): c['project']={'id':11,'label':'مشروع تجريبي · Fictional project'}
   if c.get('shipment'): c['shipment']['label']='SYN-SHIP-001'
   payload['data']['documentsWorkspace']=None
   if not c.get('restricted') and c.get('customer'):
    command=['php',str(self.server.source_root/'deploy/tests/test_m121_task_documents_link.php'),'--fixture']
    if one(parse_qs(urlsplit(self.path).query),'lang')=='ar': command.append('--ar')
    payload['data']['documentsWorkspace']=json.loads(subprocess.check_output(command,cwd=self.server.source_root))
   data=json.dumps(payload,ensure_ascii=False)
  if getattr(self,'shipping_page',False) and isinstance(data,str) and '<script>let fixtureMode=' in data:
   kind='text/html; charset=utf-8'
   data=data.replace('<h1>Synthetic fixture','<body class="cpo-board-card"><h1>Synthetic fixture',1)
   data=re.sub(r'<script>let fixtureMode=.*?</script>','<script src="/shipping-fixture-controls.js"></script>',data,flags=re.S)
  return super().send(data,status,kind)
 def do_GET(self):
  u=urlsplit(self.path); q=parse_qs(u.query)
  if u.path=='/shipping-fixture-controls.js': return self.send(fixture_controls,kind='text/javascript')
  if u.path=='/card.php':
   self.shipping_page=True
   self.path='/?lang='+('ar' if one(q,'lang').startswith('ar') else 'en')
   return shipping_fixture.Handler.do_GET(self)
  if u.path in ('/context_api.php','/card_operation.js','/card_operation.css'):
   return shipping_fixture.Handler.do_GET(self)
  return super().do_GET()

def main():
 p=argparse.ArgumentParser(description=__doc__); p.add_argument('--source-root',default=DEFAULT); p.add_argument('--port',type=int,default=18832); args=p.parse_args()
 root=Path(args.source_root).resolve()
 shipping_fixture.ROOT=root
 for asset in ASSETS.values():
  if not (root/asset).is_file(): p.error('Missing source asset: '+asset)
 # No address override: this service can only listen on IPv4 loopback. Busy port fails.
 server=ThreadingHTTPServer(('127.0.0.1',args.port),Handler); server.source_root=root
 print(f'Local synthetic preview: http://127.0.0.1:{args.port}/boards.php?id=1  source={root}',flush=True)
 try: server.serve_forever()
 except KeyboardInterrupt: pass
 finally: server.server_close()
if __name__=='__main__': main()
