#!/usr/bin/env python3
"""Reproduce the pinned artifact from committed sources, without filesystem metadata."""
import gzip, hashlib, io, json, subprocess, sys, tarfile
from pathlib import Path
root=Path(__file__).resolve().parents[3]
release=Path(__file__).resolve().parent
out=Path(sys.argv[1]);out.mkdir(parents=True,exist_ok=True)
raw=(release/'manifest.json').read_bytes();manifest=json.loads(raw)
buf=io.BytesIO()
with gzip.GzipFile(fileobj=buf,mode='wb',mtime=0) as gz:
    with tarfile.open(fileobj=gz,mode='w',format=tarfile.USTAR_FORMAT) as tar:
        for name,digest in sorted(manifest['files'].items()):
            data=subprocess.check_output(['git','show',manifest['source_commit']+':custom/'+name],cwd=root)
            assert hashlib.sha256(data).hexdigest()==digest
            member=tarfile.TarInfo(name);member.size=len(data);member.mode=0o644
            tar.addfile(member,io.BytesIO(data))
archive=buf.getvalue();assert hashlib.sha256(archive).hexdigest()==manifest['payload_sha256']
(out/'m095-payload.tar.gz').write_bytes(archive)
(out/'m095-manifest.json').write_bytes(raw)
(out/'m095-deploy.py').write_bytes((release/'deploy.py').read_bytes())
print('M095_ARTIFACT_REPRODUCED='+manifest['payload_sha256'])
