#!/usr/bin/env python3
"""Fixed backup-only read transaction; writes only a non-secret fingerprint receipt."""
import contextlib
import hashlib
import io
import ipaddress
import json
import os
import re
import stat
import subprocess
import sys

INBOX = '/home/codex-deploy/staging-inbox/boards-backup-9b11ede'
IMAGE = 'sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9'
PINS = {'install_inventory_preflight-v2.py': ('ff9b7b869a4b7e0bc19adb8cff4b9ee86d4b888ea9899af28c54ed7a92ce9386',12484),
        'inspect_native-v2.php': ('10d80f839d79b32d43ab5e907227e851b7251a6c05ea2a2aa88f3ba4415cea44',4948)}


def read(directory, name):
    pin, size = PINS[name]
    fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=directory)
    with os.fdopen(fd, 'rb') as f:
        s = os.fstat(f.fileno())
        if not stat.S_ISREG(s.st_mode) or s.st_uid != 1002 or s.st_nlink != 1 or s.st_mode & 0o022 or s.st_size != size:
            raise RuntimeError('Pinned inspection file metadata differs.')
        data = f.read(size + 1)
        after = os.fstat(f.fileno())
        if (s.st_size,s.st_mtime_ns,s.st_ctime_ns) != (after.st_size,after.st_mtime_ns,after.st_ctime_ns) or hashlib.sha256(data).hexdigest() != pin:
            raise RuntimeError('Pinned inspection file identity differs.')
        return data


def main():
    if len(sys.argv) != 1 or os.geteuid() != 0 or os.uname().nodename != 'srv1011043':
        raise RuntimeError('Fixed authorized backup administrative session required.')
    directory = os.open('/',os.O_RDONLY | os.O_DIRECTORY)
    try:
        for part in INBOX.split('/')[1:]:
            child = os.open(part,os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW,dir_fd=directory)
            os.close(directory)
            directory = child
        s = os.fstat(directory)
        if s.st_uid != 1002 or stat.S_IMODE(s.st_mode) != 0o700:
            raise RuntimeError('Private fixed inspection inbox differs.')
        ctx = {'__name__':'boards_inventory'}
        exec(compile(read(directory,'install_inventory_preflight-v2.py'),'pinned_inventory','exec'),ctx)
        before = [ctx['selected'](n) for n in (ctx['APP'],ctx['DB'],ctx['MAIL'])]
        ctx['validate_resources'](*before)
        if before[0]['image'] != IMAGE:
            raise RuntimeError('Inspected backup image changed.')
        addresses = ctx['run'](['docker','--host','unix:///var/run/docker.sock','inspect','--format','{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}',ctx['DB']]).decode().split()
        if len(addresses)!=1 or ipaddress.ip_address(addresses[0]).version!=4:
            raise RuntimeError('Canonical backup database network identity differs.')
        result = subprocess.run(['docker','--host','unix:///var/run/docker.sock','exec','--env','CPO_BOARDS_EXPECTED_DB_IP='+addresses[0],'-i',ctx['APP'],'php'],input=read(directory,'inspect_native-v2.php'),capture_output=True,timeout=90,cwd='/',env=ctx['ENV'])
        if result.returncode or len(result.stdout)>131072:
            match = re.search(rb'BOARDS_NATIVE_READONLY_FAILED phase=([a-z_0-9]+) code=([0-9]+)',result.stderr)
            detail = (' phase='+match[1].decode()+' code='+match[2].decode()) if match else '; details withheld'
            raise RuntimeError('Read-only native inspection failed'+detail)
        data = json.loads(result.stdout)
        after = [ctx['selected'](n) for n in (ctx['APP'],ctx['DB'],ctx['MAIL'])]
        ctx['validate_resources'](*after)
        if before != after or data.get('result') != 'BOARDS_NATIVE_READONLY':
            raise RuntimeError('Backup runtime changed during inspection.')
        raw = (json.dumps(data,sort_keys=True)+'\n').encode()
        fd = os.open('native-before.json',os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW,0o600,dir_fd=directory)
        with os.fdopen(fd,'wb') as output:
            os.fchown(output.fileno(),1002,1002)
            output.write(raw)
            output.flush()
            os.fsync(output.fileno())
        os.fsync(directory)
        print('BOARDS_NATIVE_INSPECTION_PASS',data['dolibarr'],data['php'],hashlib.sha256(raw).hexdigest())
        print('READ_TRANSACTION_ONLY; fingerprint metadata receipt; no application/config/grant writes')
    finally:
        os.close(directory)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(str(error) if isinstance(error,RuntimeError) else type(error).__name__,file=sys.stderr)
        sys.exit(1)
