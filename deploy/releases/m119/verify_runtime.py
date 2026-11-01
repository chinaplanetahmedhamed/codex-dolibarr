#!/usr/bin/env python3
"""Fixed read-only acceptance of the app-only M119 runtime replacement."""
import datetime
import json
import os
from pathlib import Path
import stat
import subprocess
import urllib.request

APP='erp-staging-dolibarr-1'
IMAGE='sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9'
ROOT=Path('/root/backup-erp-code-backups/m119-8b14b9e9c609')
VOLUME='erp-staging_m119_office_expenses_private'


def run(args,data=None):
    r=subprocess.run(['docker','--host','unix:///var/run/docker.sock']+args,input=data,capture_output=True,timeout=30,cwd='/',env={'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'})
    if r.returncode:raise RuntimeError('A fixed runtime acceptance command failed; details withheld.')
    return r.stdout


def read(name):
    p=ROOT/name;s=p.lstat()
    if not stat.S_ISREG(s.st_mode) or s.st_uid!=0 or s.st_nlink!=1 or s.st_mode&0o777!=0o600 or p.resolve()!=p:raise RuntimeError('Private runtime evidence provenance differs.')
    return json.loads(p.read_bytes())


def validate(app,before,ready,running=True):
    if app['Id']==before['Id'] or app['Image']!=IMAGE or (running and not app['State']['Running']):raise RuntimeError('The prepared staging runtime has not replaced the original application.')
    if ready['final_image']!=IMAGE:raise RuntimeError('Runtime preparation image differs.')
    def environment(config):
        values=config.get('Env') or []
        out=dict(item.split('=',1) for item in values)
        if len(out)!=len(values):raise RuntimeError('Duplicate environment keys are ambiguous.')
        return out
    if environment(before['Config'])!=environment(app['Config']):raise RuntimeError('Existing environment key/value mapping changed; values withheld.')
    skip={'Image','Hostname','Labels','Env'}
    differences=sorted(k for k in set(before['Config'])|set(app['Config']) if k not in skip and before['Config'].get(k)!=app['Config'].get(k))
    allowed_labels={'com.docker.compose.config-hash','com.docker.compose.image','com.docker.compose.version'}
    label_diff=sorted(k for k in set(before['Config']['Labels'])|set(app['Config']['Labels']) if k not in allowed_labels and before['Config']['Labels'].get(k)!=app['Config']['Labels'].get(k))
    def same_host(k):
        a,b=before['HostConfig'].get(k),app['HostConfig'].get(k)
        if k in ('Dns','DnsOptions','DnsSearch') and a in (None,[]) and b in (None,[]):return True
        if k=='OomKillDisable' and (a is None or a is False) and (b is None or b is False):return True
        return a==b
    host_diff=sorted(k for k in set(before['HostConfig'])|set(app['HostConfig']) if k!='Binds' and not same_host(k))
    if differences or label_diff or host_diff:raise RuntimeError('Unapproved configuration key differences: '+json.dumps({'application':differences,'labels':label_diff,'host':host_diff}))
    old_binds=before['HostConfig'].get('Binds') or []
    new_binds=app['HostConfig'].get('Binds') or []
    if sorted(new_binds)!=sorted(old_binds+[VOLUME+':/var/lib/chinaplanetos-office-expenses:rw']):raise RuntimeError('Only the approved new private-volume bind is allowed.')
    mounts={m['Destination']:m for m in app['Mounts']}
    if set(mounts)!={'/var/www/html/custom','/var/www/documents','/var/lib/chinaplanetos-office-expenses'}:raise RuntimeError('Runtime mount set differs.')
    for m in before['Mounts']:
        if mounts.get(m['Destination'])!=m:raise RuntimeError('An existing staging data mount changed.')
    private=mounts['/var/lib/chinaplanetos-office-expenses']
    if private.get('Type')!='volume' or private.get('Name')!=VOLUME or private.get('Source')!='/var/lib/docker/volumes/'+VOLUME+'/_data' or private.get('RW') is not True:raise RuntimeError('Private durable mount provenance differs.')
    if app['HostConfig']['PortBindings']!={'80/tcp':[{'HostIp':'127.0.0.1','HostPort':'18088'}]} or set(app['NetworkSettings']['Networks'])!=set(before['NetworkSettings']['Networks']):raise RuntimeError('Existing staging exposure/network membership differs.')
    if running and app['NetworkSettings']['Ports']!={'80/tcp':[{'HostIp':'127.0.0.1','HostPort':'18088'}]}:raise RuntimeError('Actual running loopback exposure differs.')
    for name,old in before['NetworkSettings']['Networks'].items():
        current=app['NetworkSettings']['Networks'][name]
        if old.get('IPAMConfig')!=current.get('IPAMConfig') or (running and old.get('NetworkID')!=current.get('NetworkID')):raise RuntimeError('Existing network identity/address policy changed.')
        required=set(old.get('Aliases')or[])-{before['Id'],before['Id'][:12]}
        if not required.issubset(set(current.get('Aliases')or[])):raise RuntimeError('An existing staging service/network alias was removed.')
    db=json.loads(run(['inspect','erp-staging-mariadb-1']))[0];mail=json.loads(run(['inspect','erp-staging-mailpit-1']))[0]
    for container,identity,image in ((db,'b00e094f86f9fbe8cb62e47e54146ef46baaf7c69eb25091dd176b60bf04f79d','sha256:daacc2f260f8ec999daa5e03a017a23a7e6fa3fb982aaf26e8b72f24daf03bc9'),(mail,'92ab1e651f4b6892d67f0fb83a6f75a185f29ca8c74883bf8904fe71a6a7d295','sha256:81370195cd4a0eab9604d17c2617a7525b0486f9365555253b6c5376c6350f1a')):
        started=datetime.datetime.fromisoformat(container['State']['StartedAt'].replace('Z','+00:00'))
        if container['Id']!=identity or container['Image']!=image or not container['State']['Running'] or started>=datetime.datetime.fromisoformat(ready['time']):raise RuntimeError('An existing database/mail service was replaced or restarted.')


def main():
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise RuntimeError('Fixed host identity differs.')
    before=read('application-inspect.json');ready=read('runtime-ready.json')
    app=json.loads(run(['inspect',APP]))[0]
    validate(app,before,ready)
    php="require '/var/www/html/version.inc.php';$p='/var/lib/chinaplanetos-office-expenses';if(DOL_VERSION!=='24.0.1'||PHP_VERSION!=='8.2.32'||!extension_loaded('bcmath')||!function_exists('imagejpeg')||bcadd('0.1','0.2',2)!=='0.30'||!is_readable($p.'/pilot.json')||!is_writable($p.'/receipts')||hash_file('sha256','/var/www/html/conf/conf.php')!=='"+ready['native_configuration_sha256']+"')exit(2);echo 'M119_RUNTIME_BINDING_PASS';"
    if run(['exec','--user','33:33',APP,'php','-r',php])!=b'M119_RUNTIME_BINDING_PASS':raise RuntimeError('Current UID33 native configuration/capability/private-storage binding differs.')
    req=urllib.request.Request('http://127.0.0.1:18088/',headers={'Host':'backup-erp.visiontrading.biz'})
    class LocalOnly(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args):return None
    try:
        response=urllib.request.build_opener(urllib.request.ProxyHandler({}),LocalOnly()).open(req,timeout=10);status=response.status;response.close()
    except urllib.error.HTTPError as e:status=e.code
    if status not in (200,301,302,303):raise RuntimeError('Existing local ERP health differs.')
    print(json.dumps({'result':'M119_RUNTIME_ACCEPTANCE_PASS','application_id':app['Id'],'image':app['Image'],'native_configuration_preserved':True,'existing_host_config_preserved':True,'existing_data_mounts_preserved':True,'networks_and_loopback_ports_preserved':True,'database_mail_not_restarted_or_replaced':True,'uid33_capabilities_private_storage':'PASS','local_http_status':status,'schema_installed':False,'financial_posting':False}),flush=True)


if __name__=='__main__':
    try:main()
    except Exception as error:
        print(json.dumps({'status':'M119_RUNTIME_ACCEPTANCE_STOP','reason':str(error) if isinstance(error,RuntimeError) else 'Read-only acceptance failed; do not activate the module.'}),flush=True)
        raise SystemExit(1)
