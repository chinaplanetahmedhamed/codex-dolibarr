import importlib.util,tempfile,os,types,stat
from pathlib import Path
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('h',Path(__file__).resolve().parents[1]/'releases/m099/csrf_staging_fix.py');h=importlib.util.module_from_spec(spec);spec.loader.exec_module(h)
real_read=h.read_file;real_fstat=os.fstat
with tempfile.TemporaryDirectory() as tmp:
 root=Path(tmp)/'custom';root.mkdir();state=Path(tmp)/'state';name=h.NAME;p=root/name;p.parent.mkdir(parents=True);old=b'old js';p.write_bytes(old);p.chmod(0o644);dep=root/'dependency.php';dep.write_bytes(b'unchanged');dep.chmod(0o644)
 h.ROOT=root;h.STATE=state;h.EXPECTED={name:h.sha(old),'dependency.php':h.sha(dep.read_bytes())};h.target=lambda:None
 def fake_stat(s,uid=33,gid=33):return types.SimpleNamespace(st_mode=s.st_mode,st_uid=uid,st_gid=gid,st_ino=s.st_ino,st_mtime_ns=s.st_mtime_ns)
 def read(root,name):
  data,s=real_read(root,name);return data,fake_stat(s,0,0) if root==state else fake_stat(s)
 def reject(fn):
  try:fn()
  except (RuntimeError,FileExistsError,OSError):return
  raise AssertionError('unsafe operation accepted')
 with patch.object(h,'read_file',read),patch.object(h,'state_guard',lambda:None),patch.object(h.os,'fchown',lambda *a:None),patch.object(h.os,'fstat',side_effect=lambda fd:types.SimpleNamespace(**{k:getattr(real_fstat(fd),k) for k in ['st_mode','st_nlink','st_size','st_dev','st_ino','st_mtime_ns']},st_uid=0,st_gid=0)):
  h.main('preflight');h.main('apply');assert h.sha(p.read_bytes())==h.AFTER and dep.read_bytes()==b'unchanged'
  assert (state/'before.js').read_bytes()==old;h.main('verify')
  reject(lambda:h.main('apply'))
  dep.write_bytes(b'drift');reject(lambda:h.main('verify'));dep.write_bytes(b'unchanged')
  (state/'before.js').write_bytes(b'tampered');reject(lambda:h.main('rollback'));assert h.sha(p.read_bytes())==h.AFTER
  (state/'before.js').write_bytes(old);h.main('rollback');assert p.read_bytes()==old
  reject(lambda:h.atomic(b'new',{'uid':33,'gid':33,'mode':0o644},'unexpected'))
 symlink=root/'bad';symlink.symlink_to(dep);reject(lambda:real_read(root,'bad'))
print('M099_STAGING_HELPER_TESTS=PASS scenarios=8; production target absent')
