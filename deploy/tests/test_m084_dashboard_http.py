"""Authenticated synthetic QA only. Rejects HTTP-200 login/denied/partial pages.
M084_QA_ROOT must name the isolated m084-qa runtime prepared for this Mission.
Never use this write-capable test against staging, production or the working DB.
"""
import html, json, os, pathlib, re, subprocess, urllib.request, urllib.parse, urllib.error, http.cookiejar
ROOT=pathlib.Path(os.environ['M084_QA_ROOT']).resolve()
assert ROOT.name=='m084-qa'
state=json.loads((ROOT/'state.json').read_text())
assert re.fullmatch(r'm084_qa_[a-f0-9]{10}',state['database']) and state['port']==8094 and state['outbound_disabled']
PHP=os.environ.get('M084_PHP','php')
BASE='http://127.0.0.1:8094'
creds=json.loads((ROOT/'qa-credentials.json').read_text())
checks=[]
def check(ok,label):
 checks.append({'test':label,'pass':bool(ok)})
 if not ok: raise AssertionError(label)
def dbmode(mode='snapshot'):
 return subprocess.check_output([PHP,str(pathlib.Path(__file__).with_name('m084_qa_state.php')),mode],text=True)
def snapshot():return json.loads(dbmode())
def fetch(op,path,data=None):
 assert path.startswith('/') and not path.startswith('//')
 req=urllib.request.Request(BASE+path,urllib.parse.urlencode(data).encode() if data is not None else None)
 try:r=op.open(req,timeout=30)
 except urllib.error.HTTPError as e:r=e
 return r.status,r.read().decode(errors='replace'),r.url

def session(role):
 op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 _,b,_=fetch(op,'/index.php')
 token=re.search(r'name="token"[^>]*value="([^"]*)"',b)[1]
 _,b,_=fetch(op,'/index.php',{'token':token,'username':'m084_'+role,'password':creds['password'],'actionlogin':'login'})
 check('name="username"' not in b,'login_'+role)
 return op

def good(response,marker,label):
 status,b,url=response
 check(status==200 and '</html>' in b.lower() and marker in b and not re.search(r'Fatal error|Uncaught |Access denied|name="username"',b,re.I),label)
 return b

def links(b):return [html.unescape(v) for v in re.findall(r'href="([^"]+)"',b)]
def invoice_links(b):return [x for x in links(b) if '/invoice.php?' in x]
C='/custom/chinaplanetcustomercenter/'
L='/custom/chinaplanetlogistics/'
try:
 sessions={role:session(role) for role in ['admin','staff','customer_a','customer_b','no_access']}
 dbmode('sync_probe')
 before=snapshot()
 for role in ['admin','staff','customer_a','customer_b']:
  op=sessions[role];sid=102 if role=='customer_b' else 101;letter='B' if sid==102 else 'A'
  cases=[('projects.php?status=active',f'M084-P{letter}'),('invoices.php?socid='+str(sid),f'M084-INV-{letter}'),('invoice.php?id='+str(sid)+'&socid='+str(sid),'Linked Payments'),('balance.php?socid='+str(sid),'statement'),('commercial_documents.php?socid='+str(sid),f'M084-EXP-{letter}'),('export_document.php?id='+str(sid)+'&socid='+str(sid),'cpcc-export-total'),('operation_report.php?id='+str(sid)+'&socid='+str(sid),'Approved current files only')]
  for path,marker in cases:
   b=good(fetch(op,C+path),marker,role+':'+path)
   check('M084 ENTITY2 PRIVATE' not in b and 'M084-INV-E2' not in b,'entity isolation '+role+':'+path)
   check('M084 INTERNAL SECRET' not in b,'private notes excluded '+role+':'+path)
   if role.startswith('customer'):
    other='A' if letter=='B' else 'B'
    check('M084-INV-'+other not in b and 'M084-P'+other not in b and 'M084-EXP-'+other not in b,'customer isolation '+role+':'+path)
  report=good(fetch(op,L+'reports.php'),'Financial Status',role+': financial report')
  for target in invoice_links(report):good(fetch(op,target),'Linked Payments',role+': report invoice link '+target)
  if role in ['admin','staff']:
   good(fetch(op,C+'admin/operations.php?socid=101&projectid=101'),'Open documents',role+': operations complete')
   for page in ['invoices.php','balance.php','commercial_documents.php']:
    b=good(fetch(op,C+page),'cpcc-customer-select',role+': customer selector '+page)
    check('M084 ENTITY2 PRIVATE' not in b,'selector entity '+role+page)
  if role.startswith('customer'):
   for page,marker in [('invoice.php?id=201&socid=201','Linked Payments'),('invoice.php?id='+str(101 if sid==102 else 102)+'&socid=201','Linked Payments'),('export_document.php?id='+str(101 if sid==102 else 102),'cpcc-export-total'),('operation_report.php?id='+str(101 if sid==102 else 102),'Approved current files only')]:
    _,b,_=fetch(op,C+page);check(marker not in b and ('Access denied' in b),'denied '+role+page)
   b=good(fetch(op,C+'invoices.php?socid='+str(101 if sid==102 else 102)),f'M084-INV-{letter}',role+': forged scope ignored')
   check('M084-INV-'+('A' if letter=='B' else 'B') not in b,role+': forged scope no leak')
 for page in ['projects.php','balance.php?socid=101','invoice.php?id=101&socid=101']:
  _,b,_=fetch(sessions['no_access'],C+page);check('Access denied' in b,'no rights '+page)
 check(snapshot()==before,'all read routes left records and financial amounts unchanged')
 admin=sessions['admin']; b=good(fetch(admin,C+'admin_dashboard.php'),'cpad-layout-data','admin dashboard')
 allLink=re.search(r'<a href="([^"]+)"><span class="fas fa-list"></span><b>All Shipments',b)
 check(allLink and 'status=' not in allLink[1],'all shipments unfiltered')
 live=b.split('data-cpad-widget="live_shipments"')[1].split('</section>')[0]
 check('M084-TEST-104' not in live and 'M084-TEST-105' not in live,'terminal shipments excluded from live panel')
 for target in links(live):good(fetch(admin,target),'cpl-command-center' if '/card.php?' in target else 'M084-TEST-101','live shipment link '+target)
 flow=b.split('data-cpad-widget="workflow"')[1].split('</section>')[0]
 check(re.search(r'<b>3</b><small>Live shipments',flow) is not None,'live shipment metric equals active list 3')
 months=[x for x in links(b) if 'search_date_startmonth=' in x]
 check(len(months)==7 and len(set(months))==7,'seven distinct invoice month links')
 for target in months:
  body=good(fetch(admin,target),'search_date_startmonth','monthly invoice destination')
  if 'search_date_startmonth=9&' in target:check('M084-INV-A' in body and 'M084-INV-B' in body,'September invoices shown')
  else:check('M084-INV-A' not in body and 'M084-INV-B' not in body,'outside-month invoices excluded')
 for page in ['projects.php?action=syncprojectcards&socid=101','projects.php?action=syncprojectcards&socid=999999']:
  status,body,_=fetch(admin,C+page);check(status in [403,405] or 'Access denied' in body,'GET sync refused '+page)
 _,body,_=fetch(admin,C+'projects.php',{'action':'syncprojectcards','socid':101,'token':'invalid'})
 check('Access denied' in body or 'Security token' in body,'invalid csrf refused')
 for role in ['staff','customer_a','customer_b','no_access']:
  _,body,_=fetch(sessions[role],C+'projects.php')
  match=re.search(r'name="anti-csrf-newtoken" content="([^"]+)"',body)
  check(bool(match),'form token for '+role)
  _,body,_=fetch(sessions[role],C+'projects.php',{'action':'syncprojectcards','socid':101,'token':match[1]})
  check('Access denied' in body,'valid-token sync permission denied '+role)
 check(snapshot()==before,'GET, bad-token and unauthorized sync did not write')
 # Explicit authorized sync writes only synthetic QA cards and redirects to a clean GET.
 body=good(fetch(admin,C+'projects.php?socid=101'),'M084-PA','sync form')
 form=re.search(r'<form method="POST".*?</form>',body,re.S)[0]
 token=re.search(r'name="token" value="([^"]+)"',form)[1]
 _,body,url=fetch(admin,C+'projects.php',{'action':'syncprojectcards','socid':101,'token':token})
 check('action=' not in url and 'M084-PA' in body,'sync uses redirect after POST')
 after=snapshot();check(after['cpkanban_card']['count']>=before['cpkanban_card']['count'] and after['cpkanban_card']['hash']!=before['cpkanban_card']['hash'],'sync record persists')
 for t in before:
  if t not in ['cpkanban_card','cpkanban_column']:check(before[t]==after[t],'sync preserved '+t)
 good(fetch(admin,C+'projects.php?socid=101'),'M084-PA','reload after sync')
 check(snapshot()==after,'reload did not repeat sync')
 # Fail a read query in the isolated database and restore the exact table in finally.
 dbmode('hide_card_table')
 try:
  _,body,_=fetch(admin,C+'projects.php')
  check('Projects could not be loaded.' in body and 'No projects found.' not in body and '</html>' in body,'database error visible, not a false empty list')
 finally:dbmode('restore_card_table')
 check(snapshot()==after,'failure injection restored exact records')
 print(json.dumps({'status':'PASS','checks':len(checks)}))
finally:
 output=pathlib.Path(os.environ.get('M084_RESULTS',str(ROOT/'strict-results.json')))
 output.write_text(json.dumps(checks,ensure_ascii=False,indent=2))
