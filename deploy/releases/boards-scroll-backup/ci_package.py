#!/usr/bin/env python3
import hashlib,importlib.util,json,sys,tarfile
from pathlib import Path
from build_artifact import build
from render_helper import render
HERE=Path(__file__).resolve().parent
SUPPORT='d756ca79906b19802dd620b948588b41effb19b3'
EXPECTED={'artifact_sha256':'75fc5e2bccf13ecf266ada483b9d5b51e3dc31cd371d84c0a206f7655dbad8c1','manifest_sha256':'3c290e79bf566a4e865aa57ab629aca75d1241d9302fa350f52aea0e4107a059','helper_sha256':'4258fd94dfd2cab04e235a98c0ba805bdfa95c547fc20603fc9ca1642d376bdc'}
out=Path(sys.argv[1]);pins=build(SUPPORT,out);pins=render(SUPPORT,out,out/'boards-backup-controller.py')
if any(pins[k]!=v for k,v in EXPECTED.items()):raise RuntimeError('Reproduced package differs.')
spec=importlib.util.spec_from_file_location('sealed',out/'boards-backup-controller.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
archive=out/('boards-artifact-'+pins['artifact_sha256']+'.tar.gz');manifest=out/('boards-manifest-'+pins['artifact_sha256']+'.json')
plan,files=module.unpack(json.loads(manifest.read_bytes()),archive.read_bytes())
for name,raw in files.items():
 if name.startswith('runtime/'):
  target=out/'lint'/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(raw)
print(json.dumps({'result':'IMMUTABLE_PACKAGE_VERIFIED',**pins},sort_keys=True))
