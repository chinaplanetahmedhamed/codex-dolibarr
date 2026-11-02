#!/usr/bin/env python3
import hashlib,importlib.util,json,sys,tarfile
from pathlib import Path
from build_artifact import build
from render_helper import render
HERE=Path(__file__).resolve().parent
SUPPORT='a6e23d171adece639117361c88d44eb6022d3c15'
EXPECTED={'artifact_sha256':'42226e7921ea56a61ab383e632ac5556111aa4bf453d35009c832f78a0b17d3a','manifest_sha256':'73ad577e2d9ad9aa5efcaaf61f8675a5d24c663f0475fb2e996efda111dcb420','helper_sha256':'9932eb68d292924dc77b172212d75c91520b80a573d13c926c829c8dfe09fcc0'}
out=Path(sys.argv[1]);pins=build(SUPPORT,out);pins=render(SUPPORT,out,out/'boards-backup-controller.py')
if any(pins[k]!=v for k,v in EXPECTED.items()):raise RuntimeError('Reproduced package differs.')
spec=importlib.util.spec_from_file_location('sealed',out/'boards-backup-controller.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
archive=out/('boards-artifact-'+pins['artifact_sha256']+'.tar.gz');manifest=out/('boards-manifest-'+pins['artifact_sha256']+'.json')
plan,files=module.unpack(json.loads(manifest.read_bytes()),archive.read_bytes())
for name,raw in files.items():
 if name.startswith('runtime/'):
  target=out/'lint'/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(raw)
print(json.dumps({'result':'IMMUTABLE_PACKAGE_VERIFIED',**pins},sort_keys=True))
