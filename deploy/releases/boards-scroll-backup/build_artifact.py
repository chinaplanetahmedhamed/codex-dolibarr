#!/usr/bin/env python3
"""Reproducible fixed application/fallback/support artifact, from immutable Git objects."""
import argparse
import gzip
import hashlib
import io
import json
from pathlib import Path
import re
import subprocess
import tarfile

HERE = Path(__file__).resolve().parent
REPO = HERE.parents[2]
SUPPORT = {'inventory.py':'install_inventory_preflight.py','inspect_native.php':'inspect_native.php',
           'native_install.php':'native_install.php','database_backup.php':'database_backup.php'}


def blob(commit, path):
    if re.fullmatch('[a-f0-9]{40}',commit) is None or path.startswith('/') or '..' in Path(path).parts:
        raise ValueError('Fixed immutable source required.')
    entry=subprocess.check_output(['git','ls-tree',commit,'--',path],cwd=REPO).decode().split()
    if len(entry)!=4 or entry[0]!='100644' or entry[1]!='blob' or entry[3]!=path:
        raise ValueError('Ordinary immutable source file required.')
    return subprocess.check_output(['git','show',commit+':'+path],cwd=REPO)


def build(support_commit, output):
    plan = json.loads(blob(support_commit,'deploy/releases/boards-scroll-backup/plan.json'))
    if plan['schema']!=1 or plan['mission']!='M107-C02' or len(plan['files'])!=45 or len(plan['protected'])!=35 or len(plan['fallback'])!=7:
        raise ValueError('Fixed Boards dependency scope differs.')
    files={}
    for item in plan['files']:
        path=item['path']
        if not path.startswith('chinaplanetoperations/') or 'runtime/'+path in files or '..' in Path(path).parts:
            raise ValueError('Invalid Boards runtime scope.')
        raw=blob(plan['application_commit'],'custom/'+path)
        if hashlib.sha256(raw).hexdigest()!=item['sha256'] or len(raw)!=item['size']:
            raise ValueError('Immutable application bytes differ.')
        files['runtime/'+path]=raw
    for path in plan['fallback']:
        if path not in {x['path'] for x in plan['files']}:
            raise ValueError('Fallback cannot expand runtime scope.')
        files['fallback/'+path]=blob(plan['fallback_commit'],'custom/'+path)
    for name, source in SUPPORT.items():
        files['support/'+name]=blob(support_commit,'deploy/releases/boards-scroll-backup/'+source)
    if len(files)!=56:
        raise ValueError('Incomplete package.')
    plain=io.BytesIO()
    with tarfile.open(fileobj=plain,mode='w',format=tarfile.USTAR_FORMAT) as archive:
        for name,raw in sorted(files.items()):
            if not 0<len(raw)<=2*1024*1024:
                raise ValueError('Member size outside bound.')
            item=tarfile.TarInfo(name);item.size=len(raw);item.mode=0o644;item.uid=item.gid=0;item.mtime=0
            archive.addfile(item,io.BytesIO(raw))
    compressed=io.BytesIO()
    with gzip.GzipFile(filename='',fileobj=compressed,mode='wb',mtime=0) as stream:
        stream.write(plain.getvalue())
    raw=compressed.getvalue();digest=hashlib.sha256(raw).hexdigest()
    manifest={'schema':1,'mission':'M107-C02','support_commit':support_commit,'plan':plan,'artifact_sha256':digest,
              'members':[{'path':p,'sha256':hashlib.sha256(v).hexdigest(),'size':len(v)} for p,v in sorted(files.items())]}
    meta=(json.dumps(manifest,sort_keys=True,separators=(',',':'))+'\n').encode()
    output.mkdir(parents=True,exist_ok=True)
    (output/('boards-artifact-'+digest+'.tar.gz')).write_bytes(raw)
    (output/('boards-manifest-'+digest+'.json')).write_bytes(meta)
    result={'artifact_sha256':digest,'manifest_sha256':hashlib.sha256(meta).hexdigest(),
            'application_commit':plan['application_commit'],'support_commit':support_commit,'members':56}
    (output/'package-pins.json').write_text(json.dumps(result,indent=2)+'\n')
    return result


if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--support-commit',required=True);parser.add_argument('--output',type=Path,required=True)
    args=parser.parse_args();print(json.dumps(build(args.support_commit,args.output)))
