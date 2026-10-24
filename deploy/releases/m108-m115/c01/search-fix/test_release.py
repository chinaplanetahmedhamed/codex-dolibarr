import importlib.util,json,os,shutil,tempfile
from pathlib import Path
here=Path(__file__).resolve().parent
def load(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
f=load('searchfix',here/'release.py');c=load('prior',here.parent/'release.py');b=load('base',here.parent.parent/'release.py')
with tempfile.TemporaryDirectory(prefix='m115-searchfix-') as td:
 td=Path(td).resolve();root=td/'custom';root.mkdir();inbox=td/'inbox';inbox.mkdir();state=td/'state';state.mkdir()
 class Fake:
  ROOT=root;INBOX=inbox
  @staticmethod
  def guarded(p):assert not p.is_symlink() and p.resolve()==p;return p
 b.m=Fake();c.r=b;f.c=c;f.STATE=state
 shutil.copy(here.parent.parent/b.ARTIFACT,inbox/b.ARTIFACT);shutil.copy(here.parent/c.ARTIFACT,inbox/c.ARTIFACT);shutil.copy(here/f.INBOX_FILE,inbox/f.INBOX_FILE)
 main,blobs=b.payload();prior,overlay=c.payload();blobs.update(overlay)
 for name,blob in blobs.items():p=root/name.removeprefix('custom/');p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(blob)
 def check(m,after=False):
  assert after
  for x in m['files']:assert b.sha((root/x['path'].removeprefix('custom/')).read_bytes())==x['sha256'],'Source drift'
 b.check_files=check;f.check();n=1;blob=f.data();n+=1;p=f.target();(state/'before.js').write_bytes(p.read_bytes());(state/'metadata.json').write_text(json.dumps({'uid':os.getuid(),'gid':os.getgid(),'mode':0o644}));p.write_bytes(blob);f.check(True);n+=1
 p.write_bytes(b'human edit')
 try:f.rollback();raise RuntimeError('Missed human edit')
 except RuntimeError as e:assert 'Human source drift' in str(e);n+=1
 p.write_bytes(blob);out=f.rollback();assert out['database_restored'] is False and out['business_writes_retained'];n+=1;f.check();n+=1
 (inbox/f.INBOX_FILE).write_bytes(blob+b'bad')
 try:f.data();raise RuntimeError('Missed bad payload')
 except RuntimeError as e:assert 'Payload drift' in str(e);n+=1
 print(json.dumps({'result':'SEARCHFIX_ISOLATED_OVERLAY_ROLLBACK_PASS','assertions':n,'database_or_production_execution':False}))
