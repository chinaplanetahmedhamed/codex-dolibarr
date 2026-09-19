#!/usr/bin/env python3
"""Build the fixed M085 production receiver from staging-tested source bytes."""
import base64
import hashlib
import json
from pathlib import Path
import subprocess
import sys

BASE='1f4e2dbdc49e813bf0c268d8f9461ad04711b426'
CANDIDATE='7d0ca969a164867ff11fb0e8c034ffff02bfcf5b'
TEMPLATE='c50b24e658d53c46065c9d5d918a326170a1c013'
PREFIX='custom/chinaplanetoperations/'
FILES=['class/PublicWebsiteRequestService.class.php','class/PublicRequestPdfService.class.php','api/class/api_chinaplanetoperations.class.php','operations/public_requests.php','operations/request_pdf.php','langs/ar_SA/chinaplanetoperations.lang','langs/en_US/chinaplanetoperations.lang','langs/fr_FR/chinaplanetoperations.lang']
PREREQUISITES=['class/PublicRequestLifecycleService.class.php','class/PublicRequestQuotationService.class.php','class/api_chinaplanetoperations.class.php']

def blob(ref,path,optional=False):
 p=subprocess.run(['git','show',ref+':'+path],capture_output=True)
 if p.returncode and not optional:raise RuntimeError('MISSING_COMMITTED_SOURCE:'+path)
 return None if p.returncode else p.stdout

def sha(data):return hashlib.sha256(data).hexdigest() if data is not None else None

def build():
 payload={'release':'M085-P01','commit':CANDIDATE,'baseline':BASE,'files':{},'prerequisites':{n:sha(blob(BASE,PREFIX+n)) for n in PREREQUISITES}}
 for name in FILES:
  data=blob(CANDIDATE,PREFIX+name)
  payload['files'][name]={'before':sha(blob(BASE,PREFIX+name,True)),'sha256':sha(data),'data':base64.b64encode(data).decode()}
 source=blob(TEMPLATE,'deploy/tools/m085_stage_release.py').decode()
 def replace(old,new,count=1):
  nonlocal source
  if source.count(old)!=count:raise RuntimeError('TEMPLATE_MARKER_INVALID:'+old)
  source=source.replace(old,new)
 replace('Fixed M085 staging code release.','Fixed M085 production code release.')
 replace('PAYLOAD = None','PAYLOAD = '+repr(payload))
 replace("CONTAINER = 'erp-staging-dolibarr-1'","CONTAINER = 'vision_erp'")
 replace("VOLUME = 'erp-staging_erp-staging_custom'","VOLUME = 'vision-os_dolibarr_custom'")
 replace('chinaplanetos-m085-staging','chinaplanetos-m085-production',2)
 replace('codex-deploy-m085-staging','codex-deploy-m085-production')
 replace("parts[3] != 'erp-staging'","parts[3] != 'vision-os'")
 start=source.index("    if ports.get('80/tcp')")
 end=source.index('    check_directory(STATE,0,0,0o700)',start)
 old=source[start:end]
 replace(old,"""    # Production HTTP health is checked inside the fixed container, not through
    # a guessed host port. Both root and the web service are trusted volume owners.
    info=ROOT.parent.lstat()
    owners={(0,0),(pwd.getpwnam('www-data').pw_uid,grp.getgrnam('www-data').gr_gid)}
    if not stat.S_ISDIR(info.st_mode) or (info.st_uid,info.st_gid) not in owners or stat.S_IMODE(info.st_mode)!=0o755:
        fail('PRODUCTION_VOLUME_OWNERSHIP_MISMATCH')
""")
 replace("PAYLOAD.get('release') != 'M085-R01'","PAYLOAD.get('release') != 'M085-P01'")
 start=source.index('def health():');end=source.index('\ndef preflight(',start)
 replace(source[start:end],'''def health():
    # Fixed localhost within the already-validated production container. No redirect.
    code = r\'''$ctx=stream_context_create(array('http'=>array('timeout'=>15,'follow_location'=>0)));
$body=@file_get_contents('http://127.0.0.1/',false,$ctx);
$status=isset($http_response_header[0])?$http_response_header[0]:'';
if(!preg_match('~^HTTP/\\S+ 200 ~',$status)||$body===false||strpos($body,'Dolibarr')===false||strpos($body,'type="password"')===false)exit(15);
echo 'M085_LOGIN_OK';\'''
    if php(code).strip()!=b'M085_LOGIN_OK':fail('LOGIN_HEALTH_FAILED')


def staging_candidate():
    # Promotion requires the exact eight candidate files already present on staging.
    root=Path('/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data/chinaplanetoperations')
    for name,item in PAYLOAD['files'].items():
        if digest(read(root/name))!=item['sha256']:fail('STAGING_CANDIDATE_MISMATCH:'+name)

''')
 replace('    environment(); compare(deployed); schema(); lint(); health()', '    environment(); compare(deployed); schema(); lint(); health()\n    if not deployed: staging_candidate()')
 replace("'production_accessed':False,'authenticated_acceptance'","'production_accessed':True,'authenticated_acceptance'")
 return source.encode(),payload

if __name__=='__main__':
 if len(sys.argv)!=2:raise SystemExit('Usage: build_m085_production_release.py output-directory')
 code,payload=build();out=Path(sys.argv[1]);out.mkdir(parents=True,exist_ok=True)
 path=out/'m085-production-release.py';path.write_bytes(code)
 manifest={'helper_sha256':sha(code),**{k:v for k,v in payload.items() if k!='files'},'files':{n:{k:v for k,v in f.items() if k!='data'} for n,f in payload['files'].items()}}
 (out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n');print(json.dumps({'helper':str(path),'sha256':sha(code),'runtime_commit':payload['commit']}))
