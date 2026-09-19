#!/usr/bin/env python3
"""Build a self-contained root helper from committed scope; no server access."""
import base64
import hashlib
import json
from pathlib import Path
import subprocess
import sys

BASE='1f4e2dbdc49e813bf0c268d8f9461ad04711b426'
PREFIX='custom/chinaplanetoperations/'
FILES=[
'class/PublicWebsiteRequestService.class.php','class/PublicRequestPdfService.class.php',
'api/class/api_chinaplanetoperations.class.php','operations/public_requests.php','operations/request_pdf.php',
'langs/ar_SA/chinaplanetoperations.lang','langs/en_US/chinaplanetoperations.lang','langs/fr_FR/chinaplanetoperations.lang']
PREREQUISITES=['class/PublicRequestLifecycleService.class.php','class/PublicRequestQuotationService.class.php','class/api_chinaplanetoperations.class.php']

def blob(ref,name):
 p=subprocess.run(['git','show',ref+':'+PREFIX+name],capture_output=True)
 return p.stdout if p.returncode==0 else None

def sha(b):return hashlib.sha256(b).hexdigest() if b is not None else None

if __name__=='__main__':
 if len(sys.argv)!=2:raise SystemExit('Usage: build_m085_stage_release.py output-directory')
 commit=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
 if subprocess.check_output(['git','status','--porcelain','--',PREFIX]):raise SystemExit('APPLICATION_NOT_COMMITTED')
 payload={'release':'M085-R01','commit':commit,'baseline':BASE,'files':{},'prerequisites':{n:sha(blob(BASE,n)) for n in PREREQUISITES}}
 for name in FILES:
  data=blob(commit,name)
  if data is None:raise SystemExit('MISSING_CANDIDATE:'+name)
  payload['files'][name]={'before':sha(blob(BASE,name)),'sha256':sha(data),'data':base64.b64encode(data).decode()}
 template=subprocess.check_output(['git','show',commit+':deploy/tools/m085_stage_release.py'],text=True)
 if template.count('PAYLOAD = None')!=1:raise SystemExit('TEMPLATE_MARKER_INVALID')
 code=template.replace('PAYLOAD = None','PAYLOAD = '+repr(payload)).encode()
 output=Path(sys.argv[1]);output.mkdir(parents=True,exist_ok=True)
 path=output/'m085-stage-release.py';path.write_bytes(code)
 (output/'manifest.json').write_text(json.dumps({'helper_sha256':sha(code),**{k:v for k,v in payload.items() if k!='files'},'files':{n:{k:v for k,v in f.items() if k!='data'} for n,f in payload['files'].items()}},indent=2)+'\n')
 print(json.dumps({'helper':str(path),'sha256':sha(code),'commit':commit,'runtime_files':len(FILES)}))
