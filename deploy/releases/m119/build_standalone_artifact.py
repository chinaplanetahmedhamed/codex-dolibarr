#!/usr/bin/env python3
"""Complete standalone M119 code package; no server installer or activation is included."""
import argparse
import importlib.util
import json
import re
from pathlib import Path

MODULE = 'custom/chinaplanetofficeexpenses/'
RUNTIME_FILES = tuple(MODULE + p for p in (
    'index.php', 'users.php', 'employee.css', 'employee.js', 'staff.css',
    'core/modules/modChinaPlanetOfficeExpenses.class.php',
    'langs/ar_SA/chinaplanetofficeexpenses.lang',
    'langs/en_US/chinaplanetofficeexpenses.lang',
    'langs/zh_CN/chinaplanetofficeexpenses.lang',
    'assets/Cairo-OFL.txt', 'assets/Cairo.ttf', 'assets/Font-Awesome-LICENSE.txt',
    'assets/fa-solid-900.woff2', 'assets/fontawesome-solid.css', 'assets/fontawesome.css',
    'class/OfficeExpenseDraftService.php', 'class/OfficeExpenseEmployeeController.php',
    'class/OfficeExpenseEmployeePage.php', 'class/OfficeExpenseMoney.php',
    'class/OfficeExpenseModuleAccess.php', 'class/OfficeExpenseNativeMenuSyncService.php',
    'class/OfficeExpenseNativeConnection.php', 'class/OfficeExpenseNativeDraftStore.php',
    'class/OfficeExpenseNativeReceiptStore.php', 'class/OfficeExpenseNativeSchemaMigration.php',
    'class/OfficeExpenseNativeStorageFactory.php', 'class/OfficeExpensePilotConfiguration.php',
    'class/OfficeExpenseReceiptHttp.php', 'class/OfficeExpenseReceiptPanel.php',
    'class/OfficeExpenseReceiptService.php', 'class/OfficeExpenseStaffAccess.php',
    'class/OfficeExpenseNativeAccessStore.php', 'class/OfficeExpenseStaffRuntime.php',
    'class/OfficeExpenseStaffController.php', 'class/OfficeExpenseStaffPage.php',
    'schema/001_drafts.sql', 'schema/002_draft_audit.sql', 'schema/003_schema_state.sql',
    'schema/manifest.json', 'schema/receipts/001_receipts.sql',
    'schema/receipts/002_receipt_audit.sql', 'schema/receipts/manifest.json',
    'schema/access/001_membership.sql', 'schema/access/002_access_audit.sql',
    'schema/access/manifest.json',
))


# Preserve the R01/R02/R03 immutable contract. The extended workspace has its own closure.
WORKSPACE_RUNTIME_FILES = RUNTIME_FILES + tuple(MODULE + p for p in (
    'workspace.php', 'workspace.css', 'workspace.js',
    'class/OfficeExpenseWorkspaceLogo.php', 'class/OfficeExpenseWorkspaceDocument.php',
    'class/OfficeExpenseWorkspaceStore.php', 'class/OfficeExpenseWorkspaceService.php',
    'class/OfficeExpenseWorkspaceController.php', 'class/OfficeExpenseWorkspacePage.php',
    'class/OfficeExpenseWorkspaceRuntime.php', 'schema/workspace/001_documents.sql',
    'schema/workspace/002_audit.sql', 'schema/workspace/manifest.json',
))


def build(commit, output, repo=None):
    spec = importlib.util.spec_from_file_location('m119_package_format', Path(__file__).with_name('build_artifact.py'))
    package = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(package)
    repo = Path(repo or Path(__file__).resolve().parents[3])
    if not isinstance(commit, str) or re.fullmatch('[a-f0-9]{40}', commit) is None:
        raise ValueError('Use an immutable full Git commit.')
    extended = bool(package.git(repo, 'ls-tree', commit, '--', MODULE + 'class/OfficeExpenseWorkspaceRuntime.php').strip())
    files = WORKSPACE_RUNTIME_FILES if extended else RUNTIME_FILES
    metadata = {'package_kind': 'standalone-code-only', 'staff_activation': False, 'installer_included': False}
    if extended:
        metadata['workspace_activation'] = False
    return package.build(commit, output, repo, runtime_files=files, source_files=files, release_metadata=metadata)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    result = build(args.commit, Path(args.output))
    print(json.dumps({'artifact': result['artifact'], 'manifest': result['manifest_path'],
                      'sha256': result['manifest']['artifact_sha256'], 'commit': args.commit,
                      'files': len(result['manifest']['files'])}))
