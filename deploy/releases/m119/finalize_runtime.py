#!/usr/bin/env python3
"""Finalize ONLY the known fully created M119 app left by interrupted Compose."""
import json
import os

ID='ed2c8761e65a0f9a7747eec302823e4e4dec9e2c769fd01db7b2fd6872bd3fed'
NAME='/ddfff104da7e_erp-staging-dolibarr-1'


def main():
    if os.geteuid()!=0 or os.uname().nodename!='srv1011043' or 'verified_acceptance' not in globals():raise RuntimeError('Verified fixed-host acceptance context required.')
    a=verified_acceptance
    app=json.loads(a['run'](['inspect',ID]))[0]
    if app['Id']!=ID or app['Name']!=NAME or app['State']['Status']!='created' or app['State']['Running'] or not app['State'].get('StartedAt','').startswith('0001-'):raise RuntimeError('Known incomplete app transition changed; no replay.')
    a['validate'](app,a['read']('application-inspect.json'),a['read']('runtime-ready.json'),running=False)
    if a['run'](['ps','-a','--filter','name=^/erp-staging-dolibarr-1$','--format','{{.ID}}']).strip():raise RuntimeError('Canonical application name is already present; no overwrite.')
    path=a['ROOT']/'runtime-finalizing.json'
    fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as f:
        f.write(json.dumps({'schema':1,'mission':'M119','container_id':ID,'prior_name':NAME,'image':a['IMAGE'],'action':'finalize_existing_created_app_only'}).encode());f.flush();os.fsync(f.fileno())
    a['run'](['rename',ID,a['APP']])
    renamed=json.loads(a['run'](['inspect',ID]))[0]
    if renamed['Id']!=ID or renamed['Name']!='/'+a['APP'] or renamed['State']['Status']!='created' or renamed['State']['Running'] or not renamed['State'].get('StartedAt','').startswith('0001-'):raise RuntimeError('Renamed application identity/state differs; no start.')
    a['validate'](renamed,a['read']('application-inspect.json'),a['read']('runtime-ready.json'),running=False)
    if a['run'](['start',ID]).decode().strip()!=ID:raise RuntimeError('Known application start is unconfirmed; inspect before retry.')
    a['main']()


if __name__=='__main__':
    try:main()
    except Exception as e:
        print(json.dumps({'result':'M119_RUNTIME_FINALIZATION_STOP','reason':str(e) if isinstance(e,RuntimeError) else 'Fixed app finalization stopped; inspect current state before any retry.'}),flush=True)
        raise SystemExit(1)
