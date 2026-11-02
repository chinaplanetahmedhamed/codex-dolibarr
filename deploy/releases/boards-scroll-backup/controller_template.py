#!/usr/bin/env python3
"""Fixed Boards-only backup release. Root session only; no persistent command grant."""
import fcntl
import gzip
import hashlib
import http.client
import io
import ipaddress
import json
import os
from pathlib import Path
import re
import selectors
import stat
import subprocess
import sys
import tarfile
import time

INBOX=Path('/home/codex-deploy/staging-inbox/boards-backup-9b11ede')
ROOT=Path('/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data')
STATE=Path('/root/boards-backup-release-20261103-retry1')
ARTIFACT_SHA='@ARTIFACT_SHA@'
MANIFEST_SHA='@MANIFEST_SHA@'
APPLICATION='7bb0686140da488a3d73ef4ae92d6a9cda686249'
SUPPORT_COMMIT='@SUPPORT_COMMIT@'
RUNTIME=()  # @RUNTIME@
PROTECTED=()  # @PROTECTED@
FALLBACK=()  # @FALLBACK@
SHARED_LOCKS=tuple(Path('/var/lib/chinaplanetos-backup-erp-deploy')/name/'lock'for name in ('m119-standalone-r01','m119-ui-r02','m119-sidebar-r03','m119-workspace-r04','m119-receipt-r05','m119-receipt-r06','m119-design-r07','m119-design-r08','m119-dropdown-r09','m119-entry-r10','m119-finance-r11','m119-menu-r12','m119-menu-r13'))
ENTRIES=tuple('chinaplanetoperations/tasks/'+p for p in ('boards.php','boards_api.php','card.php'))
FLAGS=('CPO_CUSTOMER_WORKSPACES_ENABLED','CPO_PROJECT_TASK_BRIDGE_ENABLED','CPO_WORKSPACE_GALLERY_ENABLED','CPO_BOARD_DELETE_ENABLED','CPO_BOARD_COMPLETION_ENABLED','CPO_BOARDS_SHORTCUT_ENABLED')
TABLES=('cpo_project_board','cpo_project_task','cpo_customer_workspace_meta','cpo_customer_workspace_pref','cpo_customer_workspace_appearance','cpo_board_deleted','cpo_board_completion')
ENV={'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'}
PAUSE_HTML=b'<?php http_response_code(503);header("Retry-After: 5");header("Cache-Control: no-store");header("Content-Type: text/html; charset=UTF-8");echo "Boards are updating. Please reload shortly.";'
PAUSE_API=b'<?php http_response_code(503);header("Retry-After: 5");header("Cache-Control: no-store");header("Content-Type: application/json");echo json_encode(["ok"=>false,"error"=>"Boards are updating. Please retry shortly."]);'


class Refused(RuntimeError): pass


def need(value,message):
    if not value: raise Refused(message)


def sha(raw): return hashlib.sha256(raw).hexdigest()


def open_directory(path):
    need(path.is_absolute() and '..' not in path.parts,'Absolute fixed directory required.')
    fd=os.open('/',os.O_RDONLY|os.O_DIRECTORY)
    try:
        for part in path.parts[1:]:
            child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
            os.close(fd);fd=child
        return fd
    except BaseException:
        os.close(fd);raise


def bounded_file(directory,name,maximum,owner=None,mode=None):
    need('/'not in name and name not in ('.','..'),'Fixed leaf required.')
    fd=os.open(name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=directory)
    with os.fdopen(fd,'rb')as f:
        s=os.fstat(f.fileno())
        need(stat.S_ISREG(s.st_mode) and s.st_nlink==1 and s.st_size<=maximum,'Unsafe ordinary file.')
        need(owner is None or s.st_uid==owner,'File owner changed.')
        need(mode is None or stat.S_IMODE(s.st_mode)==mode,'File mode changed.')
        raw=f.read(maximum+1);after=os.fstat(f.fileno())
        need(len(raw)==s.st_size and (s.st_mtime_ns,s.st_ctime_ns)==(after.st_mtime_ns,after.st_ctime_ns),'File changed during read.')
        return raw,s


def private_write(path,raw):
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb')as f:f.write(raw);f.flush();os.fsync(f.fileno())


def metadata_json(path,value):
    raw=(json.dumps(value,sort_keys=True)+'\n').encode();temporary=path.with_name(path.name+'.next')
    if path.exists():
        s=path.lstat();need(stat.S_ISREG(s.st_mode)and s.st_uid==0 and stat.S_IMODE(s.st_mode)==0o600,'Unsafe private state.')
    private_write(temporary,raw);os.replace(temporary,path)
    fd=open_directory(path.parent)
    try:os.fsync(fd)
    finally:os.close(fd)


class Tree:
    def __init__(self,root,allow):self.root=root;self.allow=set(allow);self.fd=open_directory(root)
    def close(self):os.close(self.fd)
    def parent(self,path):
        need(path in self.allow and not path.startswith('/')and '..'not in Path(path).parts,'Path outside pinned scope.')
        fd=os.dup(self.fd)
        try:
            for part in Path(path).parts[:-1]:
                child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd);os.close(fd);fd=child
            return fd,Path(path).name
        except BaseException:os.close(fd);raise
    def read(self,path):
        fd,name=self.parent(path)
        try:
            try:raw,s=bounded_file(fd,name,2*1024*1024)
            except FileNotFoundError:return None,None
            return raw,{'uid':s.st_uid,'gid':s.st_gid,'mode':format(stat.S_IMODE(s.st_mode),'04o'),'mtime_ns':s.st_mtime_ns}
        finally:os.close(fd)
    def replace(self,path,raw,metadata,expected):
        current,current_metadata=self.read(path);need((sha(current)if current is not None else None)in expected,'Source drift prevents replacement: '+path)
        if current is not None:need(all(current_metadata[k]==metadata[k]for k in ('uid','gid','mode')),'Metadata drift prevents replacement: '+path)
        fd,name=self.parent(path);temporary='.'+name+'.boards-release-next'
        created=None
        try:
            out=os.open(temporary,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600,dir_fd=fd)
            with os.fdopen(out,'wb')as f:
                created=os.fstat(f.fileno())
                if (os.geteuid(),os.getegid())!=(metadata['uid'],metadata['gid']):os.fchown(f.fileno(),metadata['uid'],metadata['gid'])
                os.fchmod(f.fileno(),int(metadata['mode'],8));f.write(raw);f.flush();os.fsync(f.fileno())
            os.replace(temporary,name,src_dir_fd=fd,dst_dir_fd=fd);os.fsync(fd)
        except BaseException:
            if created is not None:
                try:
                    current=os.stat(temporary,dir_fd=fd,follow_symlinks=False)
                    if (current.st_dev,current.st_ino)==(created.st_dev,created.st_ino):os.unlink(temporary,dir_fd=fd);os.fsync(fd)
                except FileNotFoundError:pass
            raise
        finally:os.close(fd)
    def remove_new(self,path,expected):
        current,_=self.read(path)
        if current is None:return
        need(sha(current)in expected,'Source drift prevents removal of own new code.')
        fd,name=self.parent(path)
        try:os.unlink(name,dir_fd=fd);os.fsync(fd)
        finally:os.close(fd)


def unpack(manifest,compressed):
    need(len(compressed)<=4*1024*1024 and sha(compressed)==ARTIFACT_SHA,'Pinned archive differs.')
    need(manifest['schema']==1 and manifest['mission']=='M107-C02' and manifest['support_commit']==SUPPORT_COMMIT,'Manifest identity differs.')
    plan=manifest['plan']
    need(plan['application_commit']==APPLICATION and set(x['path']for x in plan['files'])==set(RUNTIME)and len(plan['files'])==45,'Runtime allowlist differs.')
    need(set(x['path']for x in plan['protected'])==set(PROTECTED)and len(plan['protected'])==35,'Protected dependency list differs.')
    need(len(plan['fallback'])==7 and set(plan['fallback'])==set(FALLBACK),'Compatible fallback scope differs.')
    expected=set('runtime/'+p for p in RUNTIME)|set('fallback/'+p for p in plan['fallback'])|{'support/inventory.py','support/inspect_native.php','support/native_install.php','support/database_backup.php'}
    members={m['path']:m for m in manifest['members']}
    need(set(members)==expected and len(manifest['members'])==56,'Archive member allowlist differs.')
    with gzip.GzipFile(fileobj=io.BytesIO(compressed))as f:plain=f.read(8*1024*1024+1)
    need(len(plain)<=8*1024*1024,'Expanded archive exceeds bound.')
    result={}
    with tarfile.open(fileobj=io.BytesIO(plain),mode='r:')as archive:
        for entry in archive:
            need(entry.name in expected and entry.name not in result and entry.type==tarfile.REGTYPE and entry.mode==0o644 and entry.uid==0 and entry.gid==0 and not entry.linkname and not entry.pax_headers and not entry.issparse(),'Unsafe archive entry.')
            item=members[entry.name];need(0<entry.size<=2*1024*1024 and entry.size==item['size'],'Archive member size differs.')
            raw=archive.extractfile(entry).read(entry.size+1);need(sha(raw)==item['sha256']and len(raw)==entry.size,'Archive member hash differs.');result[entry.name]=raw
        need(not any(plain[archive.offset:]),'Unexpected archive trailer.')
    need(set(result)==expected,'Archive dependency closure differs.')
    for item in plan['files']:need(sha(result['runtime/'+item['path']])==item['sha256'],'Runtime source hash differs.')
    return plan,result


def backup_command(user_hash):
    need(re.fullmatch('[a-f0-9]{64}',user_hash)is not None,'Invalid existing account hash.')
    return ("set -eu\n[ \"${MARIADB_DATABASE:-${MYSQL_DATABASE:-}}\" = dolibarr_db ]\nu=\"${MARIADB_USER:-${MYSQL_USER:-}}\"\np=\"${MARIADB_PASSWORD:-${MYSQL_PASSWORD:-}}\"\n[ -n \"$u\" ] && [ -n \"$p\" ]\nh=$(printf %s \"$u\" | sha256sum)\n[ \"${h%% *}\" = "+user_hash+" ]\nexport MYSQL_PWD=\"$p\"\nexec mariadb-dump --protocol=tcp --host=127.0.0.1 --port=3306 --user=\"$u\" --single-transaction --skip-lock-tables --quick --hex-blob --no-tablespaces --routines --events --triggers --default-character-set=utf8mb4 dolibarr_db\n").encode()


class Release:
    def __init__(self):
        self.inbox=open_directory(INBOX);s=os.fstat(self.inbox);need(s.st_uid==1002 and stat.S_IMODE(s.st_mode)==0o700,'Private fixed inbox differs.')
        raw,_=bounded_file(self.inbox,'boards-manifest-'+ARTIFACT_SHA+'.json',131072,1002,0o600)
        need(sha(raw)==MANIFEST_SHA,'Pinned manifest checksum differs.')
        archive,_=bounded_file(self.inbox,'boards-artifact-'+ARTIFACT_SHA+'.tar.gz',4*1024*1024,1002,0o600)
        self.manifest=json.loads(raw);self.plan,self.files=unpack(self.manifest,archive)
        self.ctx={'__name__':'pinned_inventory'};exec(compile(self.files['support/inventory.py'],'pinned_inventory','exec'),self.ctx)
        self.tree=Tree(ROOT,RUNTIME+PROTECTED)
    def close(self):self.tree.close();os.close(self.inbox)
    def resources(self):
        values=[self.ctx['selected'](n)for n in (self.ctx['APP'],self.ctx['DB'],self.ctx['MAIL'])];self.ctx['validate_resources'](*values)
        need({v['name'].lstrip('/'):v['image']for v in values}==self.plan['target_images'],'Pinned backup runtime images differ.')
        for path,pin in self.plan['core_pins'].items():
            need(path in ('version.inc.php','projet/class/task.class.php','projet/class/project.class.php'),'Unexpected core read.')
            output=self.ctx['run'](['docker','--host','unix:///var/run/docker.sock','exec',self.ctx['APP'],'sha256sum','/var/www/html/'+path])
            need(output.decode().split()[0]==pin,'Native core compatibility differs.')
        ips=self.ctx['run'](['docker','--host','unix:///var/run/docker.sock','inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}',self.ctx['DB']]).decode().split()
        need(len(ips)==1 and ipaddress.ip_address(ips[0]).version==4,'Canonical backup DB network differs.');self.ip=ips[0]
    def php_args(self,action=None):
        args=['docker','--host','unix:///var/run/docker.sock','exec','--env','CPO_BOARDS_EXPECTED_DB_IP='+self.ip]
        if action:args+=['--env','CPO_BOARDS_INSTALL_ACTION='+action]
        return args+['-i',self.ctx['APP'],'php']
    def native(self,action=None):
        worker='support/native_install.php'if action else'support/inspect_native.php'
        r=subprocess.run(self.php_args(action),input=self.files[worker],capture_output=True,timeout=120,cwd='/',env=ENV)
        need(r.returncode==0 and len(r.stdout)<=131072,'Scoped native worker failed; details withheld.')
        value=json.loads(r.stdout)
        if not action:need(value['dolibarr']=='24.0.1'and value['php']=='8.2.32'and value['database_engine'].startswith('10.6.27-'),'Native backup runtime version differs.')
        return value
    def check_sources(self,after=False,fallback=False):
        for item in self.plan['files']:
            path=item['path'];raw,metadata=self.tree.read(path)
            expected=item['sha256']if after else item['before_sha256']
            if fallback and path in self.plan['fallback']:expected=sha(self.files['fallback/'+path])
            need((sha(raw)if raw is not None else None)==expected,'Source drift: '+path)
            if raw is not None:need(all(metadata[k]==item[k]for k in ('uid','gid','mode')),'Source ownership/mode drift: '+path)
        for item in self.plan['protected']:
            raw,metadata=self.tree.read(item['path']);need((sha(raw)if raw is not None else None)==item['sha256'],'Preserved dependency drift: '+item['path'])
            if raw is not None:need(all(metadata[k]==item[k]for k in ('uid','gid','mode')),'Protected metadata drift.')
    def lease(self):
        try:os.stat('reconciliation-hold.json',dir_fd=self.inbox,follow_symlinks=False)
        except FileNotFoundError:pass
        else:raise Refused('Cancelled or failed workflow hold requires reconciliation before apply.')
        raw,_=bounded_file(self.inbox,'workflow-lease.json',4096,1002,0o600);lease=json.loads(raw)
        need(set(lease)=={'mission','artifact_sha256','application_commit','workflow_commit','run_id','expires'}and lease['mission']=='M107-C02'and lease['artifact_sha256']==ARTIFACT_SHA and lease['application_commit']==APPLICATION,'Scoped workflow lease differs.')
        need(re.fullmatch('[a-f0-9]{40}',lease['workflow_commit'])is not None and type(lease['run_id'])is int and lease['run_id']>0 and time.time()+300<lease['expires']<time.time()+1800,'Workflow lease expired or invalid.')
        return lease
    def health(self):
        connection=http.client.HTTPConnection('127.0.0.1',18088,timeout=15)
        try:
            connection.request('GET','/custom/chinaplanetoperations/tasks/boards.php',headers={'Host':'backup-erp.visiontrading.biz'})
            response=connection.getresponse();body=response.read(1024*1024+1)
            need(response.status==200 and len(body)<=1024*1024 and b'name="username"'in body and b'name="password"'in body,'Anonymous native login wall failed; no authenticated acceptance implied.')
        finally:connection.close()
    def preflight(self):
        need(not STATE.exists(),'Existing release state: inspect status, never replay apply.')
        self.resources();self.check_sources();before=self.native()
        need(all(before['schema'].get(t)is None for t in TABLES),'Existing or partial feature schema requires reconciliation.')
        need(not before['workspace_option_types']and 'cpo_board_workspace'not in before['schema']['societe_extrafields']['columns'],'Existing workspace option requires reconciliation.')
        need(all(k not in before['flags']for k in FLAGS),'Feature flags already exist; do not overwrite unknown state.')
        need(before['flags'].get('CPO_BOARDS_ENABLED')=='1'and before['flags'].get('CPO_SHIPMENT_CARDS_ENABLED')=='1'and before['flags'].get('MAIN_MODULE_CHINAPLANETOPERATIONS')=='1','Existing Boards prerequisites differ.')
        need(before['flags'].get('MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS')=='login:agenda'and before['flags'].get('CPO_ACCOUNT_STATEMENTS_ENABLED','0')=='0','Shared feature/configuration state requires reconciliation.')
        self.lint();self.health();return before
    def lint(self):
        for path,raw in self.files.items():
            if path.endswith('.php'):
                r=subprocess.run(['docker','--host','unix:///var/run/docker.sock','exec','-i',self.ctx['APP'],'php','-l','/dev/stdin'],input=raw,capture_output=True,timeout=20,cwd='/',env=ENV)
                need(r.returncode==0,'Target PHP lint failed: '+path)
    def save_state(self,value):metadata_json(STATE/'state.json',value)
    def read_state(self):
        if not STATE.exists():return None
        fd=open_directory(STATE);s=os.fstat(fd)
        need(s.st_uid==0 and stat.S_IMODE(s.st_mode)==0o700,'Unsafe private release state.')
        try:raw,_=bounded_file(fd,'state.json',131072,0,0o600);return json.loads(raw)
        finally:os.close(fd)
    def backup(self,before,lease):
        need(not STATE.exists(),'Private backup already exists; no replay.')
        need(os.stat(STATE.parent).st_uid==0,'Unsafe private backup parent.')
        STATE.mkdir(mode=0o700)
        value={'schema':1,'phase':'backing_up','application_commit':APPLICATION,'artifact_sha256':ARTIFACT_SHA,'lease':lease,'before_native':before,'originals':{}}
        self.save_state(value)
        for index,item in enumerate(self.plan['files']):
            raw,metadata=self.tree.read(item['path']);need((sha(raw)if raw is not None else None)==item['before_sha256'],'Source changed before backup.')
            if raw is not None:need(all(metadata[k]==item[k]for k in ('uid','gid','mode')),'Source metadata changed before backup.')
            name='source-before-'+str(index).zfill(3)
            value['originals'][item['path']]={'name':name,'sha256':sha(raw)if raw is not None else None,'metadata':metadata}
            if raw is not None:private_write(STATE/name,raw)
        self.save_state(value)
        # Stream SQL only through private pipes into a root-only compressed backup.
        need(os.statvfs(STATE).f_bavail*os.statvfs(STATE).f_frsize>3*1024**3,'Insufficient private backup capacity.')
        admission=subprocess.run(self.php_args(),input=self.files['support/database_backup.php'],capture_output=True,timeout=20,cwd='/',env=ENV)
        need(admission.returncode==0 and len(admission.stdout)<1024,'Read-only backup admission failed; application untouched.')
        admitted=json.loads(admission.stdout);need(type(admitted['tables'])is int and admitted['tables']>0 and re.fullmatch('[a-f0-9]{64}',admitted['user_sha256'])is not None,'Invalid backup admission metadata.')
        script=backup_command(admitted['user_sha256'])
        process=subprocess.Popen(['docker','--host','unix:///var/run/docker.sock','exec','-i',self.ctx['DB'],'sh'],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,cwd='/',env=ENV)
        total=0
        try:
            process.stdin.write(script);process.stdin.close()
            output=os.open(STATE/'database-before.sql.gz',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
            with os.fdopen(output,'wb')as private:
                with gzip.GzipFile(filename='',fileobj=private,mode='wb',mtime=0)as compressed:
                    deadline=time.monotonic()+120;error=bytearray();streams=selectors.DefaultSelector()
                    for pipe in (process.stdout,process.stderr):os.set_blocking(pipe.fileno(),False);streams.register(pipe,selectors.EVENT_READ)
                    try:
                        while streams.get_map():
                            remaining=deadline-time.monotonic();need(remaining>0,'Private backup stream deadline exceeded; application untouched.')
                            for key,_ in streams.select(min(remaining,1)):
                                block=os.read(key.fileobj.fileno(),65536)
                                if not block:streams.unregister(key.fileobj);continue
                                if key.fileobj is process.stdout:
                                    total+=len(block);need(total<=2*1024**3,'Private SQL backup exceeds bound.');compressed.write(block)
                                else:
                                    error.extend(block);need(len(error)<=65536,'Private backup diagnostics exceeded bound.')
                    finally:streams.close()
                private.flush();os.fsync(private.fileno())
            code=process.wait(timeout=max(1,deadline-time.monotonic()))
            need(code==0 and total>1024,'Private database backup failed; application untouched.')
            tail=b'';creates=0
            with gzip.open(STATE/'database-before.sql.gz','rb')as checked:
                for line in checked:
                    creates+=line.startswith(b'CREATE TABLE ');tail=(tail+line)[-8192:]
            need(creates>=admitted['tables'] and b'Dump completed on'in tail,'SQL backup structure/completion/CRC validation failed.')
        except BaseException:
            if process.poll()is None:
                process.terminate()
                try:process.wait(timeout=5)
                except subprocess.TimeoutExpired:process.kill();process.wait(timeout=5)
            raise
        checksum=hashlib.sha256()
        with (STATE/'database-before.sql.gz').open('rb')as saved:
            for block in iter(lambda:saved.read(65536),b''):checksum.update(block)
        value.update(phase='prepared',database_backup_sha256=checksum.hexdigest(),database_uncompressed_bytes=total)
        self.save_state(value);return value
    def schema_ready(self,value):
        need(all(value['schema'].get(t)and value['schema'][t]['engine']=='InnoDB'for t in TABLES),'Additive transactional schema not ready.')
        need(value['workspace_option_types']==['boolean']and 'cpo_board_workspace'in value['schema']['societe_extrafields']['columns'],'Customer workspace option not ready.')
    def verify_native(self,value,before=None):
        self.schema_ready(value)
        need(all(value['flags'].get(k)=='1'for k in FLAGS),'Scoped feature flags not active.')
        need(value['flags'].get('MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS')=='login:agenda:leftblock','Existing hook contexts not retained.')
        if before is not None:need(before['protected']==value['protected'],'Protected data/access changed during installation; retain all writes.')
    def apply(self):
        lease=self.lease();before=self.preflight();value=self.backup(before,lease);self.lease()
        value['phase']='applying';self.save_state(value);exposed=False
        try:
            # Three brief maintenance responses prevent mixed caller/dependency versions.
            for path in ENTRIES:
                item=next(x for x in self.plan['files']if x['path']==path);pause=PAUSE_API if path.endswith('boards_api.php')else PAUSE_HTML
                self.tree.replace(path,pause,item,{item['before_sha256']})
            def order(item):
                p=item['path'];return (0 if p.endswith('.sql')or item['before_sha256']is None else 1 if '/class/'in p else 2 if '/js/'in p or '/css/'in p else 3,p)
            for item in sorted(self.plan['files'],key=order):
                path=item['path']
                if path in ENTRIES:continue
                self.tree.replace(path,self.files['runtime/'+path],item,{item['before_sha256']})
            self.native('migrate');schema=self.native();self.schema_ready(schema)
            need(before['protected']==schema['protected'],'Native records/access changed during migration; retain writes.')
            self.native('enable');ready=self.native();self.verify_native(ready,before)
            value['phase']='exposing';self.save_state(value);exposed=True
            for path in ENTRIES:
                item=next(x for x in self.plan['files']if x['path']==path);pause=PAUSE_API if path.endswith('boards_api.php')else PAUSE_HTML
                self.tree.replace(path,self.files['runtime/'+path],item,{sha(pause)})
            self.resources();self.check_sources(after=True);self.lint();self.health()
            value.update(phase='applied',verified_native=ready,completed_at=int(time.time()));self.save_state(value)
            return value
        except BaseException as error:
            value['failure']=type(error).__name__
            try:
                if exposed:
                    value['phase']='failed_retained_reconciliation_required';self.save_state(value)
                else:self.restore_before_exposure(value)
            except BaseException as rollback_error:
                value.update(phase='failed_retained_reconciliation_required',rollback_failure=type(rollback_error).__name__)
                self.save_state(value)
            raise
    def restore_before_exposure(self,value):
        current=self.native()
        empty=all(current['schema'].get(t)is None or current['schema'][t].get('rows')==0 for t in TABLES)
        unchanged=current['protected']==value['before_native']['protected']
        if not empty or not unchanged:
            value['phase']='failed_retained_reconciliation_required';self.save_state(value)
            raise Refused('Concurrent/new feature records detected. Compatible code retained; no data restore.')
        # Refuse any unrecognized human edits before changing settings or restoring code.
        for item in self.plan['files']:
            raw,_=self.tree.read(item['path']);allowed={item['before_sha256'],item['sha256']}
            if item['path']in ENTRIES:allowed.add(sha(PAUSE_API if item['path'].endswith('boards_api.php')else PAUSE_HTML))
            need((sha(raw)if raw is not None else None)in allowed,'Concurrent source edit prevents automatic restoration.')
        self.native('restore_before_exposure')
        # Keep entry gates until old callers are coherent; remove new dependencies last.
        ordered=[x for x in self.plan['files']if x['path']not in ENTRIES and x['before_sha256']is not None]+[x for x in self.plan['files']if x['path']not in ENTRIES and x['before_sha256']is None]+[x for x in self.plan['files']if x['path']in ENTRIES]
        for item in ordered:
            path=item['path'];original=value['originals'][path];raw,_=self.tree.read(path);digest=sha(raw)if raw is not None else None
            if digest==original['sha256']:continue
            if original['sha256']is None:self.tree.remove_new(path,{item['sha256']})
            else:
                original_bytes=(STATE/original['name']).read_bytes();need(sha(original_bytes)==original['sha256'],'Original source backup differs.')
                self.tree.replace(path,original_bytes,original['metadata'],{digest})
        self.resources();self.check_sources();self.health()
        value['phase']='failed_restored_before_exposure';self.save_state(value)
    def verify(self):
        value=self.read_state();need(value and value['phase']in ('applied','ui_fallback'),'No completed Boards installation to verify.')
        self.resources();self.check_sources(after=True,fallback=value['phase']=='ui_fallback');self.verify_native(self.native());self.health();return value
    def rollback_ui(self):
        value=self.verify();need(value['phase']=='applied','Compatible UI fallback is already applied.')
        before=self.native();originals={}
        for index,path in enumerate(self.plan['fallback']):
            raw,metadata=self.tree.read(path);item=next(x for x in self.plan['files']if x['path']==path)
            need(raw is not None and sha(raw)==item['sha256']and all(metadata[k]==item[k]for k in ('uid','gid','mode')),'Concurrent UI edit before fallback; retain it.')
            backup=STATE/('ui-before-'+str(index))
            if backup.exists():
                fd=open_directory(STATE)
                try:saved,_=bounded_file(fd,backup.name,2*1024*1024,0,0o600);need(saved==raw,'Previous UI backup differs; reconciliation required.')
                finally:os.close(fd)
            else:private_write(backup,raw)
            originals[path]=(raw,metadata)
        value['phase']='ui_fallback_applying';self.save_state(value)
        try:
            for path in self.plan['fallback']:
                raw,metadata=originals[path];self.tree.replace(path,self.files['fallback/'+path],metadata,{sha(raw)})
            self.check_sources(after=True,fallback=True);after=self.native();need(before==after,'UI rollback native state changed; retain all data.')
            self.health();value['phase']='ui_fallback';self.save_state(value);return value
        except BaseException as error:
            value['ui_fallback_failure']=type(error).__name__
            try:
                for path in self.plan['fallback']:
                    raw,metadata=originals[path]
                    current,_=self.tree.read(path)
                    if current!=raw:self.tree.replace(path,raw,metadata,{sha(self.files['fallback/'+path])})
                self.check_sources(after=True);self.health();value['phase']='applied'
            except BaseException as restore_error:
                value.update(phase='failed_retained_reconciliation_required',ui_restore_failure=type(restore_error).__name__)
            self.save_state(value);raise
    def receipt(self,value):
        result={'mission':'M107-C02','phase':value['phase'],'application_commit':APPLICATION,'artifact_sha256':ARTIFACT_SHA,'manifest_sha256':MANIFEST_SHA,'run_id':value['lease']['run_id'],'workflow_commit':value['lease']['workflow_commit'],'database_backup_sha256':value.get('database_backup_sha256'),'backup_directory':str(STATE),'runtime_files':45,'protected_dependencies':35,'core':'24.0.1','php':'8.2.32','permissions_changed':False,'existing_business_records_changed':False,'authenticated_ui_acceptance':'pending'}
        temporary='deployment-receipt.next';raw=(json.dumps(result,sort_keys=True)+'\n').encode()
        out=os.open(temporary,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600,dir_fd=self.inbox)
        with os.fdopen(out,'wb')as f:os.fchown(f.fileno(),1002,1002);f.write(raw);f.flush();os.fsync(f.fileno())
        os.replace(temporary,'deployment-receipt.json',src_dir_fd=self.inbox,dst_dir_fd=self.inbox);os.fsync(self.inbox)
        return result


def main():
    need(len(sys.argv)==2 and sys.argv[1]in ('preflight','apply','verify','rollback_ui','status'),'Exactly one fixed Boards action required.')
    need(os.geteuid()==0 and os.uname().nodename=='srv1011043','Authorized fixed administrative backup host required.')
    need(re.fullmatch('[a-f0-9]{64}',ARTIFACT_SHA)is not None and re.fullmatch('[a-f0-9]{64}',MANIFEST_SHA)is not None,'Unsealed controller cannot execute.')
    os.umask(0o077);action=sys.argv[1];release=Release()
    try:
        if action=='preflight':release.preflight();result={'phase':'preflight_pass','application_commit':APPLICATION,'writes':False}
        elif action=='status':
            value=release.read_state();result={'phase':value['phase']if value else'not_started','application_commit':APPLICATION}
        else:
            if action!='apply':
                need(STATE.exists(),'No private release state.');s=STATE.lstat();need(stat.S_ISDIR(s.st_mode)and s.st_uid==0 and stat.S_IMODE(s.st_mode)==0o700,'Unsafe private release state.')
            # GitHub serializes shared backup deployments; this lock prevents own replays.
            shared_locks=[]
            for path in SHARED_LOCKS:
                parent=open_directory(path.parent)
                try:fd=os.open(path.name,os.O_RDWR|os.O_NOFOLLOW,dir_fd=parent)
                finally:os.close(parent)
                shared_locks.append(fd);m=os.fstat(fd)
                need(stat.S_ISREG(m.st_mode)and m.st_uid==0 and m.st_nlink==1 and stat.S_IMODE(m.st_mode)==0o600,'Established backup operation lock differs.')
                fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
            lock=os.open(STATE.with_suffix('.lock'),os.O_WRONLY|os.O_CREAT|os.O_NOFOLLOW,0o600)
            try:
                s=os.fstat(lock);need(stat.S_ISREG(s.st_mode)and s.st_uid==0 and s.st_nlink==1 and stat.S_IMODE(s.st_mode)==0o600,'Unsafe Boards operation lock.')
                fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
                value=getattr(release,action)();result=release.receipt(value)
            finally:
                os.close(lock)
                for fd in reversed(shared_locks):os.close(fd)
        print(json.dumps(result,sort_keys=True))
    finally:release.close()


if __name__=='__main__':
    try:main()
    except Exception as error:
        print(json.dumps({'result':'BOARDS_RELEASE_REFUSED','reason':str(error)if isinstance(error,Refused)else type(error).__name__}),file=sys.stderr);sys.exit(1)
