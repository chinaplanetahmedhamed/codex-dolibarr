#!/usr/bin/env python3
"""Build the fixed, one-file M085 staging reference-navigation correction."""
import base64
import hashlib
import json
from pathlib import Path
import subprocess
import sys

BASE='13656661953abdaa9cf41e01061d8094d076b117'
CANDIDATE='7d0ca969a164867ff11fb0e8c034ffff02bfcf5b'
TEMPLATE='c50b24e658d53c46065c9d5d918a326170a1c013'
PREFIX='custom/chinaplanetoperations/'
FILE='operations/public_requests.php'
PREREQUISITES=['class/PublicWebsiteRequestService.class.php','class/PublicRequestPdfService.class.php','class/PublicRequestLifecycleService.class.php','class/PublicRequestQuotationService.class.php','class/api_chinaplanetoperations.class.php','api/class/api_chinaplanetoperations.class.php','operations/request_pdf.php']

def blob(ref,path):return subprocess.check_output(['git','show',ref+':'+path])
def sha(data):return hashlib.sha256(data).hexdigest()

def build():
 commit=subprocess.check_output(['git','rev-parse',CANDIDATE],text=True).strip()
 data=blob(commit,PREFIX+FILE)
 payload={'release':'M085-REF-R01','commit':commit,'baseline':BASE,'files':{FILE:{'before':sha(blob(BASE,PREFIX+FILE)),'sha256':sha(data),'data':base64.b64encode(data).decode()}},'prerequisites':{n:sha(blob(BASE,PREFIX+n)) for n in PREREQUISITES}}
 source=blob(TEMPLATE,'deploy/tools/m085_stage_release.py').decode()
 replacements={'PAYLOAD = None':'PAYLOAD = '+repr(payload),'M085-R01':'M085-REF-R01','chinaplanetos-m085-staging':'chinaplanetos-m085-ref-staging','codex-deploy-m085-staging':'codex-deploy-m085-ref-staging'}
 for old,new in replacements.items():
  expected=2 if old=='chinaplanetos-m085-staging' else 1
  if source.count(old)!=expected:raise RuntimeError('TEMPLATE_MARKER_INVALID:'+old)
  source=source.replace(old,new)
 return source.encode(),payload

if __name__=='__main__':
 if len(sys.argv)!=2:raise SystemExit('Usage: build_m085_reference_release.py output-directory')
 code,payload=build();out=Path(sys.argv[1]);out.mkdir(parents=True,exist_ok=True)
 path=out/'m085-reference-stage-release.py';path.write_bytes(code)
 manifest={'helper_sha256':sha(code),**{k:v for k,v in payload.items() if k!='files'},'files':{n:{k:v for k,v in f.items() if k!='data'} for n,f in payload['files'].items()}}
 (out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n');print(json.dumps({'helper':str(path),'sha256':sha(code),'runtime_commit':payload['commit']}))
