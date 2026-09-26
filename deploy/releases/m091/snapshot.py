#!/usr/bin/env python3
"""Export only M091 affected ERP module code for release preflight."""
import hashlib, io, json, os, subprocess, sys, tarfile
from pathlib import Path

PATHS = ['custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php', 'custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php', 'custom/chinaplanetoperations/class/InternalTaskSchedule.class.php', 'custom/chinaplanetoperations/class/InternalTaskService.class.php', 'custom/chinaplanetoperations/class/actions_chinaplanetoperations.class.php', 'custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php', 'custom/chinaplanetoperations/css/task_agenda.css', 'custom/chinaplanetoperations/css/task_experience.css', 'custom/chinaplanetoperations/js/task_agenda.js', 'custom/chinaplanetoperations/js/task_experience.js', 'custom/chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang', 'custom/chinaplanetoperations/langs/en_US/chinaplanetoperations.lang', 'custom/chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang', 'custom/chinaplanetoperations/lib/task_experience.lib.php', 'custom/chinaplanetoperations/workspace.php']
ROOTS = {'production': ('vision_erp','vision-os_dolibarr_custom')}
INBOX = Path('/home/codex-deploy/staging-inbox')

def main():
    if len(sys.argv) != 2 or sys.argv[1] not in ROOTS: raise SystemExit('environment required')
    env = sys.argv[1]
    container, volume = ROOTS[env]
    info = json.loads(subprocess.check_output(['docker', 'inspect', container]))[0]
    assert info['State']['Running'], 'container not running'
    mounts = [m for m in info['Mounts'] if m['Destination'] == '/var/www/html/custom']
    assert len(mounts) == 1 and mounts[0]['Name'] == volume and mounts[0]['RW'], 'custom mount mismatch'
    root = Path(mounts[0]['Source'])
    assert root.resolve() == Path('/var/lib/docker/volumes') / volume / '_data', 'unexpected volume source'
    assert INBOX.is_dir() and not INBOX.is_symlink(), 'inbox mismatch'
    output = INBOX / ('m091-'+env+'-prestate.tar.gz')
    inventory = {}
    fd = os.open(output, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    try:
        with os.fdopen(fd, 'wb') as target:
            with tarfile.open(fileobj=target, mode='w:gz') as archive:
                for name in PATHS:
                    rel = name.removeprefix('custom/')
                    assert rel != name and '..' not in Path(rel).parts
                    path = root / rel
                    if not path.exists():
                        inventory[rel] = None
                        continue
                    assert path.is_file() and not path.is_symlink() and path.stat().st_size < 5_000_000
                    data = path.read_bytes()
                    inventory[rel] = hashlib.sha256(data).hexdigest()
                    member = tarfile.TarInfo(rel)
                    member.size, member.mode = len(data), 0o600
                    archive.addfile(member, io.BytesIO(data))
                data = json.dumps({'environment': env, 'files': inventory}, sort_keys=True).encode()
                member = tarfile.TarInfo('inventory.json')
                member.size, member.mode = len(data), 0o600
                archive.addfile(member, io.BytesIO(data))
            target.flush()
            os.fchown(target.fileno(), 1002, 1002)
            os.fsync(target.fileno())
    except BaseException:
        output.unlink(missing_ok=True)
        raise
    print(json.dumps({'environment': env, 'files': len(inventory), 'present': sum(v is not None for v in inventory.values()), 'artifact': output.name, 'sha256': hashlib.sha256(output.read_bytes()).hexdigest()}))

if __name__ == '__main__': main()
