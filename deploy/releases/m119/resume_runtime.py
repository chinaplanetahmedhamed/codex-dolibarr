#!/usr/bin/env python3
"""One bounded continuation after the known commit-ID-format guard, no replay."""
import gzip
import json
import os
import stat

SNAPSHOT = 'sha256:9d364c8e1103a87062dbdce864584c1f16f68ad4480d5534430ccb222356cd73'


def main():
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043' or 'verified_preparer' not in globals() or 'verified_probe' not in globals():
        raise RuntimeError('Verified fixed-host continuation context is required.')
    p=verified_preparer
    if verified_probe['main']() not in (None,0):raise RuntimeError('Current staging preflight failed.')
    root=p['BACKUP'];p['safe_root_directory'](root)
    def private(name,limit=1048576):
        path=root/name;s=path.lstat()
        if not stat.S_ISREG(s.st_mode) or s.st_uid!=0 or s.st_nlink!=1 or s.st_mode&0o777!=0o600 or s.st_size>limit or path.resolve()!=path:
            raise RuntimeError('Prior private preparation evidence differs.')
        fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW)
        with os.fdopen(fd,'rb') as f:
            opened=os.fstat(f.fileno())
            if (opened.st_dev,opened.st_ino)!=(s.st_dev,s.st_ino):raise RuntimeError('Preparation evidence changed while opening.')
            data=f.read(limit+1)
        if len(data)!=s.st_size:raise RuntimeError('Preparation evidence size changed.')
        return data
    phase=json.loads(private('preparation.json'))
    if phase['phase']!='backup_started' or phase['source']!=p['SOURCE'] or phase['application_id']!=p['APP_ID'] or phase['base_image']!=p['BASE'] or phase['candidate']!=p['CANDIDATE']:
        raise RuntimeError('Known incomplete preparation identity differs.')
    before=json.loads(private('application-inspect.json'))
    current=p['inspect'](p['APP'])
    if current['Id']!=p['APP_ID'] or current['Image']!=p['BASE'] or not current['State']['Running'] or current['Config']!=before['Config'] or current['Mounts']!=before['Mounts']:
        raise RuntimeError('Original application changed; continuation refused.')
    snapshot=json.loads(p['run'](['docker','image','inspect',p['SNAPSHOT_TAG']]))[0]
    if snapshot['Id']!=SNAPSHOT or snapshot['Config'].get('Env')!=current['Config'].get('Env') or snapshot['Config'].get('Labels')!=current['Config'].get('Labels'):
        raise RuntimeError('Known preserved-rootfs snapshot changed.')
    if json.loads(p['run'](['docker','image','inspect',p['CANDIDATE_TAG']]))[0]['Id']!=p['CANDIDATE']:
        raise RuntimeError('Reviewed dependency image changed.')
    if (root/'build').exists() or (root/'runtime-ready.json').exists() or p['run'](['docker','image','ls','--filter','reference='+p['FINAL_TAG'],'--format','{{.ID}}']).strip() or p['run'](['docker','volume','ls','--filter','name=^'+p['VOLUME']+'$','--format','{{.Name}}']).strip():
        raise RuntimeError('Later preparation resources already exist; no replay or adoption.')
    config_hash=p['native_readonly']("echo hash_file('sha256','/var/www/html/conf/conf.php');")
    if config_hash!=phase['native_configuration_sha256']:raise RuntimeError('Original native configuration changed.')
    original=private('portainer-stack2-original.yml',65536)
    if p['archive_file']('management-portainer-1','/data/compose/2/docker-compose.yml')!=original:raise RuntimeError('Authoritative staging definition changed.')
    dump=root/'database-before.sql.gz'
    if dump.stat().st_size>2*1024**3:raise RuntimeError('Private database backup exceeds reserved capacity.')
    s=dump.lstat()
    if not stat.S_ISREG(s.st_mode) or s.st_uid!=0 or s.st_mode&0o777!=0o600 or s.st_nlink!=1 or dump.resolve()!=dump:raise RuntimeError('Private database backup provenance differs.')
    count,tail=0,b''
    with gzip.open(dump,'rb') as f:
        while True:
            chunk=f.read(65536)
            if not chunk:break
            count+=len(chunk);tail=(tail+chunk)[-8192:]
            if count>2*1024**3:raise RuntimeError('Verified dump capacity exceeded.')
    if count<1024 or b'Dump completed' not in tail:raise RuntimeError('Prior private database backup is incomplete.')
    p['finish_preparation'](before,phase,config_hash,p['hashlib'].sha256(original).hexdigest(),p['hash_file'](dump),count,SNAPSHOT)


if __name__=='__main__':
    try:main()
    except Exception as error:
        print(json.dumps({'status':'M119_RUNTIME_CONTINUATION_STOP','error_class':type(error).__name__,'reason':str(error) if isinstance(error,RuntimeError) else 'Private continuation stopped; preserve existing state.'}),flush=True)
        raise SystemExit(1)
