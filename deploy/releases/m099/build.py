#!/usr/bin/env python3
"""Build committed M099 runtime, baseline and checksum-pinned staging-only helper."""
from pathlib import Path
import hashlib,io,json,subprocess,sys,tarfile
root=Path(__file__).resolve().parents[3]
if len(sys.argv)!=4:raise SystemExit('source commit, baseline commit and private output directory required')
commit,baseline,out=sys.argv[1],sys.argv[2],Path(sys.argv[3]);out.mkdir(parents=True,exist_ok=True)
def git(path,rev=commit):return subprocess.check_output(['git','show',rev+':'+path],cwd=root)
sha=lambda b:hashlib.sha256(b).hexdigest()
names=json.loads(git('deploy/releases/m099/runtime-files.json'))['changed']
assert len(names)==len(set(names)) and all(not n.startswith('/') and '..' not in Path(n).parts for n in names)
payload={n:git('custom/'+n) for n in names};before={}
for n in names:
 r=subprocess.run(['git','show',baseline+':custom/'+n],cwd=root,stdout=subprocess.PIPE,stderr=subprocess.DEVNULL)
 before[n]=sha(r.stdout) if r.returncode==0 else None
# Pin existing unchanged direct dependencies. Drift stops apply; never overwrite them.
deps=['chinaplanetoperations/class/InternalTaskService.class.php','chinaplanetoperations/class/InternalTaskAuthorizationService.class.php','chinaplanetoperations/class/InternalTaskDetailsService.class.php','chinaplanetoperations/class/InternalTaskCollaborationService.class.php','chinaplanetoperations/class/InternalTaskBoardsMigration.class.php','chinaplanetoperations/class/InternalTaskBoardPolicy.class.php','chinaplanetlogistics/card.php','chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php','chinaplanetcustomercenter/lib/mobile_api_data.lib.php']
# Verified staging retains M079's stricter mobile customer-visibility filters.
# This dependency is never deployed; pin its exact committed bytes instead.
dependency_revisions={'chinaplanetcustomercenter/lib/mobile_api_data.lib.php':'077bbe04ff69ad443efa7dddcff9d6c05292665a'}
prereq={n:sha(git('custom/'+n,dependency_revisions.get(n,baseline))) for n in deps}
p=out/'m099-payload.tar.gz'
with tarfile.open(p,'w:gz') as t:
 for n,b in sorted(payload.items()):
  info=tarfile.TarInfo(n);info.size=len(b);info.mode=0o644;info.mtime=0;t.addfile(info,io.BytesIO(b))
manifest={'release':'M099-R1','source_commit':commit,'baseline_commit':baseline,'core_version':'24.0.1','files':{n:sha(b) for n,b in payload.items()},'payload_sha256':sha(p.read_bytes()),'before':{'staging':before},'prerequisites':{'staging':prereq}}
b=(json.dumps(manifest,sort_keys=True,indent=2)+'\n').encode();(out/'m099-manifest.json').write_bytes(b)
helper=git('deploy/releases/m099/deploy.py.in').decode().replace('__MANIFEST_SHA__',sha(b)).replace('__PAYLOAD_SHA__',manifest['payload_sha256'])
h=out/'m099-deploy.py';h.write_text(helper);h.chmod(0o644)
result={'release':'M099-R1','source_commit':commit,'baseline_commit':baseline,'files':len(payload),'manifest_sha256':sha(b),'payload_sha256':manifest['payload_sha256'],'helper_sha256':sha(h.read_bytes()),'target':'staging only'}
(out/'artifact-evidence.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result))
