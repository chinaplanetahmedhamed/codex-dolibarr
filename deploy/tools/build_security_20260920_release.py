#!/usr/bin/env python3
"""Build only the approved two-file Kanban correction, using reviewed release IO."""
import base64
import hashlib
import importlib.util
import json
from pathlib import Path
import subprocess
import sys

BASE = 'aaeb5d1'
CANDIDATE = 'ad76cd0'
FILES = ['ajax/move.php', 'class/cpkanbancard.class.php']
PREREQUISITES = ['lib/main.lib.php', 'js/board.js']
ROOT = Path(__file__).resolve().parent

def blob(ref, path): return subprocess.check_output(['git', 'show', ref+':'+path])
def sha(data): return hashlib.sha256(data).hexdigest()

def build(environment):
    if environment not in ('staging', 'production'): raise ValueError('Invalid fixed environment')
    commit = subprocess.check_output(['git','rev-parse', CANDIDATE], text=True).strip()
    payload = {'release':'SEC20260920', 'commit':commit, 'baseline':subprocess.check_output(['git','rev-parse',BASE],text=True).strip(), 'files':{}, 'prerequisites':{}}
    for name in FILES:
        data = blob(commit, 'custom/cpkanban/'+name)
        payload['files'][name] = {'before':sha(blob(BASE,'custom/cpkanban/'+name)), 'sha256':sha(data), 'data':base64.b64encode(data).decode()}
    for name in PREREQUISITES: payload['prerequisites'][name] = sha(blob(BASE,'custom/cpkanban/'+name))
    if environment == 'production':
        spec=importlib.util.spec_from_file_location('prior',ROOT/'build_m085_production_release.py')
        prior=importlib.util.module_from_spec(spec);spec.loader.exec_module(prior)
        code=prior.build()[0].decode()
        start=code.index('PAYLOAD = ');end=code.index('\n',start)
        code=code[:start]+'PAYLOAD = None'+code[end:]
        old_release='M085-P01'
    else:
        code=blob('c50b24e658d53c46065c9d5d918a326170a1c013','deploy/tools/m085_stage_release.py').decode()
        old_release='M085-R01'
    def replace(old,new,count=1):
        nonlocal code
        if code.count(old)!=count: raise RuntimeError('TEMPLATE_MARKER_INVALID:'+old)
        code=code.replace(old,new)
    replace('PAYLOAD = None','PAYLOAD = '+repr(payload))
    replace(old_release,'SEC20260920')
    replace('chinaplanetos-m085-'+environment,'chinaplanetos-security-20260920-'+environment,2)
    replace('codex-deploy-m085-'+environment,'codex-deploy-security-20260920-'+environment)
    replace("/ 'chinaplanetoperations'", "/ 'cpkanban'")
    if environment == 'production': replace("/_data/chinaplanetoperations'", "/_data/cpkanban'")
    start=code.index('def schema():');end=code.index('\ndef validate_payload',start)
    code=code[:start]+'''def schema():
    # SELECT-only capability check; no business records or secrets are emitted.
    code = r\'''define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);define('NOCSRFCHECK',1);
$_SERVER['DOCUMENT_ROOT']='/var/www/html';require '/var/www/html/master.inc.php';
if(DOL_VERSION !== '23.0.3' || !isModEnabled('cpkanban')) exit(12);
$checks=array('cpkanban_card'=>array('entity','fk_column','progress'),'cpkanban_column'=>array('entity','active','auto_progress'),'cpkanban_log'=>array('entity','fk_card','fk_column_old','fk_column_new'));
foreach($checks as $table=>$columns)foreach($columns as $column){$r=$db->query("SHOW COLUMNS FROM ".MAIN_DB_PREFIX.$table." LIKE '".$column."'");if(!$r||$db->num_rows($r)!==1)exit(12);}
echo 'SECURITY_SCHEMA_READY';\'''
    if php(code).strip()!=b'SECURITY_SCHEMA_READY':fail('SCHEMA_OR_VERSION_MISMATCH')

''' +code[end:]
    # Read-only baseline inspection requires installed fixed identity and no arbitrary paths.
    replace("ACTIONS = ('preflight',", "ACTIONS = ('compare', 'preflight',")
    replace("        if action=='preflight': preflight()", "        if action=='compare': environment(); compare(); schema()\n        elif action=='preflight': preflight()")
    return code.encode(),payload

if __name__=='__main__':
    if len(sys.argv)!=2: raise SystemExit('Usage: build_security_20260920_release.py output-directory')
    out=Path(sys.argv[1]);out.mkdir(parents=True,exist_ok=True)
    for environment in ('staging','production'):
        code,payload=build(environment)
        file=out/('security-20260920-'+environment+'.py');file.write_bytes(code)
        manifest={'environment':environment,'helper_sha256':sha(code),**{k:v for k,v in payload.items() if k!='files'},'files':{n:{k:v for k,v in f.items() if k!='data'} for n,f in payload['files'].items()}}
        (out/(environment+'-manifest.json')).write_text(json.dumps(manifest,indent=2)+'\n')
        print(json.dumps({'environment':environment,'helper':str(file),'sha256':sha(code),'runtime_commit':payload['commit']}))
