import ast,os,tempfile,tarfile,hashlib,json,subprocess
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'tools/m142_supplier_links_release_20261123.py';tree=ast.parse(p.read_text());defs=[n for n in tree.body if isinstance(n,ast.FunctionDef) and n.name in ['write_file','restore','owned_relations','native']]
ns={'json':json,'os':os,'Path':Path,'tarfile':tarfile,'sha':lambda b:hashlib.sha256(b).hexdigest()};exec(compile(ast.Module(body=defs,type_ignores=[]),'release-test','exec'),ns)
programs=[]
ns.update({'TARGETS':{'backup':{'host':'backup-erp.visiontrading.biz'}},'runphp':lambda n,c:(programs.append(c) or b'0')})
ns['owned_relations']('backup')
for code in programs:subprocess.run(['php','-l'],input=code,check=True,stdout=subprocess.DEVNULL)
ns['runphp']=lambda n,c:(programs.append(c) or b'{}')
ns['native']('backup',True);subprocess.run(['php','-l'],input=programs[-1],check=True,stdout=subprocess.DEVNULL)
count=2
with tempfile.TemporaryDirectory(prefix='m142-recovery-') as d:
 root=Path(d);old=b'original source';new=b'released source';a=root/'old.php';b=root/'new.php';unrelated=root/'private.dat';unrelated.write_bytes(b'preserved')
 a.write_bytes(old);meta={'mode':0o600,'uid':os.getuid(),'gid':os.getgid()};a.chmod(0o600);archive=root/'backup.tgz'
 with tarfile.open(archive,'w:gz') as t:t.add(a,arcname='old.php')
 ns.update({'baseline':{'backup':{'custom_root':d,'files':{'old.php':meta,'new.php':{'absent':True}}}},'artifact':{'files':{'old.php':{'sha256':ns['sha'](new),'predecessor':ns['sha'](old)},'new.php':{'sha256':ns['sha'](new),'predecessor':None}}},'owned_relations':lambda n:0,'identity':lambda *a:None})
 a.write_bytes(new);b.write_bytes(new);ns['restore']('backup',archive,['old.php','new.php']);assert a.read_bytes()==old and not b.exists() and unrelated.read_bytes()==b'preserved';count+=1
 a.write_bytes(new);b.write_bytes(b'later edit')
 try:ns['restore']('backup',archive,['old.php','new.php']);raise AssertionError('Accepted drift')
 except RuntimeError:pass
 assert b.read_bytes()==b'later edit' and a.read_bytes()==new;count+=1
 ns['owned_relations']=lambda n:1
 try:ns['restore']('backup',archive,['old.php','new.php']);raise AssertionError('Accepted data-bearing rollback')
 except RuntimeError:pass
 assert a.read_bytes()==new;count+=1
print('PASS M142 controller:',count,'recovery/embedded-PHP checks')
