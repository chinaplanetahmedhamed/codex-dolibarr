#!/usr/bin/env python3
"""Seal fixed root controller from immutable reviewed support commit and package pins."""
import argparse,hashlib,json,re,subprocess
from pathlib import Path
from build_artifact import blob

def render(commit,package,output):
 pins=json.loads((package/'package-pins.json').read_text())
 if pins['support_commit']!=commit:raise ValueError('Support commit differs.')
 plan=json.loads(blob(commit,'deploy/releases/boards-scroll-backup/plan.json'))
 source=blob(commit,'deploy/releases/boards-scroll-backup/controller_template.py').decode()
 for key,value in [('ARTIFACT_SHA',pins['artifact_sha256']),('MANIFEST_SHA',pins['manifest_sha256']),('SUPPORT_COMMIT',commit)]:source=source.replace('@'+key+'@',value)
 for key,paths in [('RUNTIME',[x['path']for x in plan['files']]),('PROTECTED',[x['path']for x in plan['protected']]),('FALLBACK',plan['fallback'])]:source=source.replace(key+'=()  # @'+key+'@',key+'='+repr(tuple(paths)))
 if re.search(r'@[A-Z_]+@',source):raise ValueError('Unsealed placeholder.')
 compile(source,'sealed_controller','exec');raw=source.encode();output.write_bytes(raw)
 result={**pins,'helper_sha256':hashlib.sha256(raw).hexdigest(),'helper_size':len(raw)}
 output.with_suffix('.pins.json').write_text(json.dumps(result,indent=2)+'\n');return result
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--support-commit',required=True);p.add_argument('--package',type=Path,required=True);p.add_argument('--output',type=Path,required=True);a=p.parse_args();print(json.dumps(render(a.support_commit,a.package,a.output)))
