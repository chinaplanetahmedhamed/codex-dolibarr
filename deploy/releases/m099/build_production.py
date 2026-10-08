#!/usr/bin/env python3
"""Build M099 production from exact observed source hashes, preserving live overlays."""
from pathlib import Path
import hashlib,io,json,subprocess,sys,tarfile
root=Path(__file__).resolve().parents[3]
if len(sys.argv)!=4:raise SystemExit('source commit, private snapshot, private output directory required')
commit=subprocess.check_output(['git','rev-parse',sys.argv[1]+'^{commit}'],cwd=root,text=True).strip()
snapshot,out=Path(sys.argv[2]),Path(sys.argv[3]);out.mkdir(parents=True,exist_ok=True)
def git(path):return subprocess.check_output(['git','show',commit+':'+path],cwd=root)
sha=lambda b:hashlib.sha256(b).hexdigest()
observed=json.loads(snapshot.read_text())
assert observed['environment']=='production' and observed['result']=='READ_ONLY_SNAPSHOT'
assert observed['web_image']=='sha256:db5c663a52c24e6cf66a19b18d4632dec212e280751780178d9b5162e3afafee'
assert observed['web_project'] in ('',None) and observed['db_project']=='vision-os'
assert observed['custom_volume']=='vision-os_dolibarr_custom'
names=json.loads(git('deploy/releases/m099/runtime-files.json'))['changed']
assert len(names)==20 and len(set(names))==20
payload={n:git('custom/'+n) for n in names}
for n in ['chinaplanetcustomercenter/documents.php','chinaplanetcustomercenter/lib/portal.lib.php']:
 payload[n]=git('deploy/releases/m099/production-overlays/'+n)
for n in names:
 info=observed['files'][n]
 if info:assert info['uid'] in (0,33) and info['gid'] in (0,33) and info['mode']==0o644,n
before={n:observed['files'][n]['sha256'] if observed['files'][n] else None for n in names}
# Only real direct dependencies: staging's unrelated presentation adapters are not imported.
deps=[n for n in observed['files'] if n not in names and observed['files'][n]]
prereq={n:observed['files'][n]['sha256'] for n in deps}
p=out/'m099-payload.tar.gz'
with tarfile.open(p,'w:gz') as t:
 for n,b in sorted(payload.items()):
  i=tarfile.TarInfo(n);i.size=len(b);i.mode=0o644;i.mtime=0;t.addfile(i,io.BytesIO(b))
manifest={'release':'M099-R2','source_commit':commit,'core_version':'24.0.1','snapshot_sha256':sha(snapshot.read_bytes()),'files':{n:sha(b) for n,b in payload.items()},'payload_sha256':sha(p.read_bytes()),'before':{'production':before},'prerequisites':{'production':prereq}}
b=(json.dumps(manifest,sort_keys=True,indent=2)+'\n').encode();(out/'m099-manifest.json').write_bytes(b)
h=git('deploy/releases/m099/deploy.py.in').decode().replace('__MANIFEST_SHA__',sha(b)).replace('__PAYLOAD_SHA__',manifest['payload_sha256'])
h=h.replace("INBOX = Path('/home/codex-deploy/staging-inbox')", "INBOX = Path('/home/codex-deploy/staging-inbox/m099-production')")
h=h.replace("'staging': ('erp-staging-dolibarr-1', 'erp-staging-mariadb-1', 'erp-staging_erp-staging_custom', 'erp-staging'),", "'production': ('vision_erp', 'vision_db', 'vision-os_dolibarr_custom', 'vision-os'),")
h=h.replace("check(info['Config']['Labels'].get('com.docker.compose.project') == project, 'COMPOSE_IDENTITY_MISMATCH')", "check((info['Config'].get('Labels') or {}).get('com.docker.compose.project') in ('',None) and info['Image']=='sha256:db5c663a52c24e6cf66a19b18d4632dec212e280751780178d9b5162e3afafee', 'PRODUCTION_IMAGE_OR_LABEL_MISMATCH')")
h=h.replace("RELEASE = 'M099-R1'", "RELEASE = 'M099-R2'").replace('chinaplanetos-m099-r1-', 'chinaplanetos-m099-r2-').replace('m099-r1-', 'm099-r2-').replace('/staging-inbox/m099-production', '/staging-inbox/m099-production-r2')
h=h.replace('Host: backup-erp.visiontrading.biz','Host: erp.visiontrading.biz')
compile(h,'m099-production-deploy.py','exec');(out/'m099-production-deploy.py').write_text(h)
result={'release':'M099-R2','target':'production only','source_commit':commit,'files':len(names),'dependencies':len(deps),'manifest_sha256':sha(b),'payload_sha256':manifest['payload_sha256'],'helper_sha256':sha(h.encode())}
(out/'artifact-evidence.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result))
