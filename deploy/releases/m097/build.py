#!/usr/bin/env python3
"""Build only committed M097 runtime plus byte-exact production dependencies."""
from pathlib import Path
import tarfile,json,hashlib,io,subprocess,sys
root=Path(__file__).resolve().parents[3]
assert len(sys.argv)==3,'source commit and private artifact directory required'
commit,out=sys.argv[1],Path(sys.argv[2]);snap={};data={}
for env in ['staging','production']:
 with tarfile.open(out/('m097-'+env+'-prestate.tar.gz')) as t:
  snap[env]=json.load(t.extractfile('inventory.json'))['files']
  if env=='production':data={n:t.extractfile(n).read() for n,h in snap[env].items() if h}
changed=[n.removeprefix('custom/') for n in json.loads(subprocess.check_output(['git','show',commit+':deploy/releases/m097/runtime-files.json'],cwd=root))['changed']]
payload={n:subprocess.check_output(['git','show',commit+':custom/'+n],cwd=root) for n in changed}
for n,h in snap['production'].items():
 if n not in payload and snap['staging'][n]!=h and h:payload[n]=data[n]
prereq={env:{n:h for n,h in snap[env].items() if n not in payload} for env in snap}
p=out/'m097-payload.tar.gz'
with tarfile.open(p,'w:gz') as t:
 for n,b in sorted(payload.items()):
  m=tarfile.TarInfo(n);m.size=len(b);m.mode=0o644;t.addfile(m,io.BytesIO(b))
sha=lambda b:hashlib.sha256(b).hexdigest()
manifest={'release':'M097-R1','source_commit':commit,'payload_sha256':sha(p.read_bytes()),'files':{n:sha(b) for n,b in sorted(payload.items())},'before':{env:{n:snap[env][n] for n in payload} for env in snap},'prerequisites':prereq,'core_version':'24.0.1'}
b=(json.dumps(manifest,sort_keys=True,indent=2)+'\n').encode();(out/'m097-manifest.json').write_bytes(b);(root/'deploy/releases/m097/manifest.json').write_bytes(b)
print(json.dumps({'files':len(payload),'manifest_sha':sha(b),'payload_sha':sha(p.read_bytes())}))
