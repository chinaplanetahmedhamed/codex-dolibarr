#!/usr/bin/env python3
"""Fixed backup-ERP source snapshot. No files, configuration, database or service writes.

Run only through an already authorized administrative session after the shared
backup deployment queue is idle. stdout contains hashes and selected metadata, never source/configuration
contents or runtime records. This is not a deployment helper or a substitute for CI gates.
"""
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import stat
import subprocess
import sys

APP = 'erp-staging-dolibarr-1'
DB = 'erp-staging-mariadb-1'
MAIL = 'erp-staging-mailpit-1'
ROOT = Path('/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data')
FILES = ('chinaplanetcustomercenter/css/admin_dashboard.css', 'chinaplanetcustomercenter/js/kanban_pointer.js', 'chinaplanetcustomercenter/lib/admin_shell.lib.php', 'chinaplanetcustomercenter/lib/portal.lib.php', 'chinaplanetcustomercenter/lib/ui.lib.php', 'chinaplanetlogistics/class/ShipmentDocumentMigration.class.php', 'chinaplanetlogistics/class/ShipmentDocumentService.class.php', 'chinaplanetoperations/class/InternalTaskAgendaService.class.php', 'chinaplanetoperations/class/InternalTaskAssetService.class.php', 'chinaplanetoperations/class/InternalTaskAuthorizationService.class.php', 'chinaplanetoperations/class/InternalTaskBoardBackground.class.php', 'chinaplanetoperations/class/InternalTaskBoardCounts.class.php', 'chinaplanetoperations/class/InternalTaskBoardLifecycle.class.php', 'chinaplanetoperations/class/InternalTaskBoardLifecycleMigration.class.php', 'chinaplanetoperations/class/InternalTaskBoardPolicy.class.php', 'chinaplanetoperations/class/InternalTaskBoardPresentation.class.php', 'chinaplanetoperations/class/InternalTaskBoardsService.class.php', 'chinaplanetoperations/class/InternalTaskBoardsShortcutMigration.class.php', 'chinaplanetoperations/class/InternalTaskCollaborationService.class.php', 'chinaplanetoperations/class/InternalTaskCompletionMigration.class.php', 'chinaplanetoperations/class/InternalTaskCompletionService.class.php', 'chinaplanetoperations/class/InternalTaskContextService.class.php', 'chinaplanetoperations/class/InternalTaskDetailsService.class.php', 'chinaplanetoperations/class/InternalTaskFileValidator.class.php', 'chinaplanetoperations/class/InternalTaskMigration.class.php', 'chinaplanetoperations/class/InternalTaskPersonalBoard.class.php', 'chinaplanetoperations/class/InternalTaskProjectDeadline.class.php', 'chinaplanetoperations/class/InternalTaskProjectTaskMigration.class.php', 'chinaplanetoperations/class/InternalTaskProjectTaskService.class.php', 'chinaplanetoperations/class/InternalTaskRichText.class.php', 'chinaplanetoperations/class/InternalTaskSchedule.class.php', 'chinaplanetoperations/class/InternalTaskService.class.php', 'chinaplanetoperations/class/InternalTaskStageService.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceAppearance.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceAppearanceMigration.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceGalleryMigration.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceGalleryService.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceMigration.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceService.class.php', 'chinaplanetoperations/class/OperationsSopService.class.php', 'chinaplanetoperations/class/actions_chinaplanetoperations.class.php', 'chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php', 'chinaplanetoperations/css/boards.css', 'chinaplanetoperations/css/card_board.css', 'chinaplanetoperations/css/card_editor.css', 'chinaplanetoperations/css/card_operation.css', 'chinaplanetoperations/css/operations.css', 'chinaplanetoperations/css/task_experience.css', 'chinaplanetoperations/js/boards.js', 'chinaplanetoperations/js/boards_geometry.js', 'chinaplanetoperations/js/boards_live_runtime.js', 'chinaplanetoperations/js/boards_sounds.js', 'chinaplanetoperations/js/card_board.js', 'chinaplanetoperations/js/card_operation.js', 'chinaplanetoperations/js/internal_tasks.js', 'chinaplanetoperations/js/task_experience.js', 'chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang', 'chinaplanetoperations/langs/en_US/chinaplanetoperations.lang', 'chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang', 'chinaplanetoperations/lib/main.lib.php', 'chinaplanetoperations/lib/task_experience.lib.php', 'chinaplanetoperations/sql/llx_cpo_board_completion.sql', 'chinaplanetoperations/sql/llx_cpo_board_deleted.sql', 'chinaplanetoperations/sql/llx_cpo_customer_workspace_appearance.sql', 'chinaplanetoperations/sql/llx_cpo_customer_workspace_meta.sql', 'chinaplanetoperations/sql/llx_cpo_customer_workspace_pref.sql', 'chinaplanetoperations/sql/llx_cpo_project_board.sql', 'chinaplanetoperations/sql/llx_cpo_project_task.sql', 'chinaplanetoperations/tasks/action.php', 'chinaplanetoperations/tasks/asset.php', 'chinaplanetoperations/tasks/board_background.php', 'chinaplanetoperations/tasks/boards.php', 'chinaplanetoperations/tasks/boards_api.php', 'chinaplanetoperations/tasks/card.php', 'chinaplanetoperations/tasks/collaboration_action.php', 'chinaplanetoperations/tasks/context_api.php', 'chinaplanetoperations/tasks/inbox.php', 'chinaplanetoperations/tasks/new.php', 'chinaplanetoperations/tasks/personal_action.php', 'chinaplanetoperations/tasks/workspace_background.php')
OPTIONAL = ('chinaplanetoperations/class/InternalTaskBoardCounts.class.php', 'chinaplanetoperations/class/InternalTaskBoardLifecycle.class.php', 'chinaplanetoperations/class/InternalTaskBoardLifecycleMigration.class.php', 'chinaplanetoperations/class/InternalTaskBoardsShortcutMigration.class.php', 'chinaplanetoperations/class/InternalTaskCompletionMigration.class.php', 'chinaplanetoperations/class/InternalTaskCompletionService.class.php', 'chinaplanetoperations/class/InternalTaskProjectDeadline.class.php', 'chinaplanetoperations/class/InternalTaskProjectTaskMigration.class.php', 'chinaplanetoperations/class/InternalTaskProjectTaskService.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceAppearance.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceAppearanceMigration.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceGalleryMigration.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceGalleryService.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceMigration.class.php', 'chinaplanetoperations/class/InternalTaskWorkspaceService.class.php', 'chinaplanetoperations/js/boards_geometry.js', 'chinaplanetoperations/js/boards_live_runtime.js', 'chinaplanetoperations/js/boards_sounds.js', 'chinaplanetoperations/sql/llx_cpo_board_completion.sql', 'chinaplanetoperations/sql/llx_cpo_board_deleted.sql', 'chinaplanetoperations/sql/llx_cpo_customer_workspace_appearance.sql', 'chinaplanetoperations/sql/llx_cpo_customer_workspace_meta.sql', 'chinaplanetoperations/sql/llx_cpo_customer_workspace_pref.sql', 'chinaplanetoperations/sql/llx_cpo_project_board.sql', 'chinaplanetoperations/sql/llx_cpo_project_task.sql', 'chinaplanetoperations/tasks/workspace_background.php')
ENV = {'PATH': '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'}


def run(args):
    p = subprocess.run(args, cwd='/', env=ENV, capture_output=True, timeout=20)
    if p.returncode or len(p.stdout) > 131072:
        raise RuntimeError('Fixed read-only runtime command failed; details withheld.')
    return p.stdout


def selected(name):
    # Deliberately exclude environment, command, logs and other containers.
    fields = '{"name":{{json .Name}},"running":{{json .State.Running}},"image":{{json .Image}},"project":{{json (index .Config.Labels "com.docker.compose.project")}},"service":{{json (index .Config.Labels "com.docker.compose.service")}},"ports":{{json .NetworkSettings.Ports}},"mounts":{{json .Mounts}}}'
    item = json.loads(run(['docker', '--host', 'unix:///var/run/docker.sock', 'inspect', '--format', fields, name]))
    # Docker builds this array from a map; order is not a resource identity.
    item['mounts'] = sorted(item['mounts'], key=lambda m: m['Destination'])
    return item


def validate_resources(app, database, mail):
    for item, name, service in ((app, APP, 'dolibarr'), (database, DB, 'mariadb'), (mail, MAIL, 'mailpit')):
        if item['name'] != '/' + name or item['running'] is not True or item['project'] != 'erp-staging' or item['service'] != service:
            raise RuntimeError('Canonical backup resource identity differs.')
    if app['ports'] != {'80/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18088'}]}:
        raise RuntimeError('Canonical backup HTTP binding differs.')
    expected_mail = {'1025/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '11025'}],
                     '8025/tcp': [{'HostIp': '127.0.0.1', 'HostPort': '18025'}]}
    published_mail = {key: value for key, value in mail['ports'].items() if value}
    if any(database['ports'].values()) or published_mail != expected_mail:
        raise RuntimeError('Canonical backup database/mail exposure differs.')
    mounts = {m['Destination']: m for m in app['mounts']}
    for target, volume in (('/var/www/html/custom', 'erp-staging_erp-staging_custom'), ('/var/www/documents', 'erp-staging_erp-staging_docs')):
        m = mounts.get(target, {})
        if m.get('Type') != 'volume' or m.get('Name') != volume or m.get('Source') != '/var/lib/docker/volumes/' + volume + '/_data' or m.get('RW') is not True:
            raise RuntimeError('Canonical backup mount differs.')


def source(rel, root=ROOT):
    if rel not in FILES:
        raise RuntimeError('Source path is outside the fixed allowlist.')
    # Walk using directory descriptors; never follow a substituted parent/file.
    fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY)
    try:
        for part in root.parts[1:] + tuple(rel.split('/')[:-1]):
            child = os.open(part, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd)
            fd = child
        try:
            leaf = os.open(rel.split('/')[-1], os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=fd)
        except FileNotFoundError:
            if rel in OPTIONAL:
                return None
            raise RuntimeError('An existing Boards source file is missing.') from None
        try:
            s = os.fstat(leaf)
            if not stat.S_ISREG(s.st_mode) or s.st_nlink != 1 or s.st_size > 2 * 1024 * 1024:
                raise RuntimeError('Source must be one bounded ordinary file.')
            raw = b''
            while len(raw) <= 2 * 1024 * 1024:
                block = os.read(leaf, 65536)
                if not block:
                    break
                raw += block
            after = os.fstat(leaf)
            if len(raw) != s.st_size or (s.st_mtime_ns, s.st_ctime_ns) != (after.st_mtime_ns, after.st_ctime_ns):
                raise RuntimeError('Source changed during snapshot.')
            return {'sha256': hashlib.sha256(raw).hexdigest(), 'size': len(raw), 'uid': s.st_uid,
                    'gid': s.st_gid, 'mode': format(stat.S_IMODE(s.st_mode), '04o'),
                    'inode': s.st_ino, 'mtime_ns': s.st_mtime_ns}
        finally:
            os.close(leaf)
    finally:
        os.close(fd)


def main():
    if len(sys.argv) != 1 or os.geteuid() != 0 or os.uname().nodename != 'srv1011043':
        raise RuntimeError('No arguments; authorized administrative session on srv1011043 required.')
    before = [selected(n) for n in (APP, DB, MAIL)]
    validate_resources(*before)
    files = {rel: source(rel) for rel in FILES}
    again = {rel: source(rel) for rel in FILES}
    after = [selected(n) for n in (APP, DB, MAIL)]
    validate_resources(*after)
    if files != again or before != after:
        raise RuntimeError('Backup source/runtime changed; snapshot is not stable.')
    print(json.dumps({'schema': 1, 'result': 'BACKUP_BOARDS_READONLY_SNAPSHOT',
                      'timestamp': datetime.now(timezone.utc).isoformat(), 'host': 'srv1011043',
                      'resources': before, 'files': files,
                      'writes': False, 'deployment_authorized_by_snapshot': False}))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print(json.dumps({'result': 'BLOCKED', 'reason': str(error) if isinstance(error, RuntimeError) else type(error).__name__, 'writes': False}), file=sys.stderr)
        sys.exit(1)
