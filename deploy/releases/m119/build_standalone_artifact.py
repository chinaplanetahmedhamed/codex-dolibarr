#!/usr/bin/env python3
"""Complete standalone M119 code package; no server installer or activation is included."""
import argparse
import importlib.util
import json
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


def build(commit, output, repo=None):
    spec = importlib.util.spec_from_file_location('m119_package_format', Path(__file__).with_name('build_artifact.py'))
    package = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(package)
    return package.build(commit, output, repo, runtime_files=RUNTIME_FILES, source_files=RUNTIME_FILES,
                         release_metadata={'package_kind': 'standalone-code-only', 'staff_activation': False,
                                           'installer_included': False})


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--commit', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    result = build(args.commit, Path(args.output))
    print(json.dumps({'artifact': result['artifact'], 'manifest': result['manifest_path'],
                      'sha256': result['manifest']['artifact_sha256'], 'commit': args.commit,
                      'files': len(result['manifest']['files'])}))
