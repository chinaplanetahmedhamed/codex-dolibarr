#!/usr/bin/env python3
"""Export only M089-C03-R1 affected ERP module code for release preflight."""
import hashlib, io, json, os, subprocess, sys, tarfile
from pathlib import Path

PATHS = ['custom/chinaplanetcustomercenter/lib/admin_shell.lib.php', 'custom/chinaplanetoperations/class/InternalTaskAgendaService.class.php', 'custom/chinaplanetoperations/class/InternalTaskAssetService.class.php', 'custom/chinaplanetoperations/class/InternalTaskAuthorizationService.class.php', 'custom/chinaplanetoperations/class/InternalTaskBoardBackground.class.php', 'custom/chinaplanetoperations/class/InternalTaskBoardPolicy.class.php', 'custom/chinaplanetoperations/class/InternalTaskBoardPresentation.class.php', 'custom/chinaplanetoperations/class/InternalTaskBoardsLegacyMigration.class.php', 'custom/chinaplanetoperations/class/InternalTaskBoardsMigration.class.php', 'custom/chinaplanetoperations/class/InternalTaskBoardsService.class.php', 'custom/chinaplanetoperations/class/InternalTaskCollaborationService.class.php', 'custom/chinaplanetoperations/class/InternalTaskDetailsService.class.php', 'custom/chinaplanetoperations/class/InternalTaskExperienceMigration.class.php', 'custom/chinaplanetoperations/class/InternalTaskFileValidator.class.php', 'custom/chinaplanetoperations/class/InternalTaskMigration.class.php', 'custom/chinaplanetoperations/class/InternalTaskPersonalBoard.class.php', 'custom/chinaplanetoperations/class/InternalTaskRichText.class.php', 'custom/chinaplanetoperations/class/InternalTaskSchedule.class.php', 'custom/chinaplanetoperations/class/InternalTaskService.class.php', 'custom/chinaplanetoperations/class/InternalTaskStageService.class.php', 'custom/chinaplanetoperations/class/actions_chinaplanetoperations.class.php', 'custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php', 'custom/chinaplanetoperations/css/boards.css', 'custom/chinaplanetoperations/css/card_board.css', 'custom/chinaplanetoperations/css/card_editor.css', 'custom/chinaplanetoperations/css/task_agenda.css', 'custom/chinaplanetoperations/css/task_experience.css', 'custom/chinaplanetoperations/js/boards.js', 'custom/chinaplanetoperations/js/card_board.js', 'custom/chinaplanetoperations/js/internal_tasks.js', 'custom/chinaplanetoperations/js/task_agenda.js', 'custom/chinaplanetoperations/js/task_experience.js', 'custom/chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang', 'custom/chinaplanetoperations/langs/en_US/chinaplanetoperations.lang', 'custom/chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang', 'custom/chinaplanetoperations/lib/main.lib.php', 'custom/chinaplanetoperations/lib/task_experience.lib.php', 'custom/chinaplanetoperations/sql/llx_cpo_boards.sql', 'custom/chinaplanetoperations/sql/llx_cpo_task_details.sql', 'custom/chinaplanetoperations/tasks/.htaccess', 'custom/chinaplanetoperations/tasks/action.php', 'custom/chinaplanetoperations/tasks/asset.php', 'custom/chinaplanetoperations/tasks/board.php', 'custom/chinaplanetoperations/tasks/board_background.php', 'custom/chinaplanetoperations/tasks/board_revision.php', 'custom/chinaplanetoperations/tasks/boards.php', 'custom/chinaplanetoperations/tasks/boards_api.php', 'custom/chinaplanetoperations/tasks/card.php', 'custom/chinaplanetoperations/tasks/new.php', 'custom/chinaplanetoperations/tasks/personal_action.php', 'custom/chinaplanetoperations/tasks/trash.php', 'custom/chinaplanetoperations/workspace.php']
ROOTS = {
    'staging': ('erp-staging-dolibarr-1', 'erp-staging_erp-staging_custom'),
    'production': ('vision_erp', 'vision-os_dolibarr_custom'),
}
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
    output = INBOX / ('m097-'+env+'-prestate.tar.gz')
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
