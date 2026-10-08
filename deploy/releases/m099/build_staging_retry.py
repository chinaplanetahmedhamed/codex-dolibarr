#!/usr/bin/env python3
"""Build immutable M099-R2 against the already verified M099-R1 staging+CSRF baseline."""
from pathlib import Path
import hashlib,io,json,subprocess,sys,tarfile
root=Path(__file__).resolve().parents[3]
if len(sys.argv)!=4:raise SystemExit('source commit, applied staging manifest, private output directory required')
commit=subprocess.check_output(['git','rev-parse',sys.argv[1]+'^{commit}'],cwd=root,text=True).strip()
prior=Path(sys.argv[2]);out=Path(sys.argv[3]);out.mkdir(parents=True,exist_ok=True)
def git(path):return subprocess.check_output(['git','show',commit+':'+path],cwd=root)
sha=lambda b:hashlib.sha256(b).hexdigest()
old=json.loads(prior.read_bytes());assert old['release']=='M099-R1' and set(old['before'])=={'staging'}
assert old['source_commit'].startswith('11323b3')
names=json.loads(git('deploy/releases/m099/runtime-files.json'))['changed'];assert len(names)==20 and set(names)==set(old['files'])
before=dict(old['files']);assert before['chinaplanetoperations/js/card_operation.js']=='5522e74a434976ccd931937220b01e8a197fe3e1c383423dde1d0b8b8f61c0c1'
before['chinaplanetoperations/js/card_operation.js']='39809d546fbadc761c3beb30c77b76e5b6560de38dbad8ac575e512b7bdda6e2'
payload={n:git('custom/'+n) for n in names}
p=out/'m099-payload.tar.gz'
with tarfile.open(p,'w:gz') as t:
 for n,b in sorted(payload.items()):
  i=tarfile.TarInfo(n);i.size=len(b);i.mode=0o644;i.mtime=0;t.addfile(i,io.BytesIO(b))
m={'release':'M099-R2','source_commit':commit,'baseline_manifest_sha256':sha(prior.read_bytes()),'core_version':'24.0.1','files':{n:sha(b) for n,b in payload.items()},'payload_sha256':sha(p.read_bytes()),'before':{'staging':before},'prerequisites':old['prerequisites']}
b=(json.dumps(m,sort_keys=True,indent=2)+'\n').encode();(out/'m099-manifest.json').write_bytes(b)
h=git('deploy/releases/m099/deploy.py.in').decode().replace('__MANIFEST_SHA__',sha(b)).replace('__PAYLOAD_SHA__',m['payload_sha256'])
h=h.replace("RELEASE = 'M099-R1'", "RELEASE = 'M099-R2'").replace("INBOX = Path('/home/codex-deploy/staging-inbox')", "INBOX = Path('/home/codex-deploy/staging-inbox/m099-staging-r2')").replace('chinaplanetos-m099-r1-', 'chinaplanetos-m099-r2-').replace('m099-r1-', 'm099-r2-')
h=h.replace("check(config(web)['ui'] in ('','0'),'FEATURE_ALREADY_ACTIVE')", "check(config(web)=={'ui':'1','storage':'1'},'STAGING_FEATURE_STATE_DRIFT')")
compile(h,'m099-deploy.py','exec');(out/'m099-deploy.py').write_text(h)
e={'release':'M099-R2','source_commit':commit,'manifest_sha256':sha(b),'payload_sha256':m['payload_sha256'],'helper_sha256':sha(h.encode()),'target':'staging only'}
(out/'artifact-evidence.json').write_text(json.dumps(e,indent=2)+'\n');print(json.dumps(e))
