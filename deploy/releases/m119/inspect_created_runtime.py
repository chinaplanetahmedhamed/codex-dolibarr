#!/usr/bin/env python3
"""Selected metadata only for the single created M119 app left by Compose."""
import json
import os
import subprocess
from pathlib import Path

if os.geteuid()!=0 or os.uname().nodename!='srv1011043':raise SystemExit(1)
root=Path('/root/backup-erp-code-backups/m119-8b14b9e9c609')
before=json.loads((root/'application-inspect.json').read_bytes())
def inspect(ref):
    return json.loads(subprocess.check_output(['docker','--host','unix:///var/run/docker.sock','inspect',ref],env={'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'},cwd='/',stderr=subprocess.DEVNULL))[0]
app=inspect('ed2c8761e65a')
def changed(a,b):return sorted(k for k in set(a)|set(b) if a.get(k)!=b.get(k))
print(json.dumps({'name':app['Name'],'id':app['Id'],'image':app['Image'],'state':app['State']['Status'],'configuration_changed_keys':changed(before['Config'],app['Config']),'host_changed_keys':changed(before['HostConfig'],app['HostConfig']),'label_changed_keys':changed(before['Config']['Labels'],app['Config']['Labels']),'volume_paths_before':sorted((before['Config'].get('Volumes')or{})),'volume_paths_now':sorted((app['Config'].get('Volumes')or{})),'bindings':app['HostConfig']['Binds'],'port_bindings':app['HostConfig']['PortBindings'],'networks':sorted(app['NetworkSettings']['Networks']),'database_id':inspect('erp-staging-mariadb-1')['Id'],'mail_id':inspect('erp-staging-mailpit-1')['Id']}))
