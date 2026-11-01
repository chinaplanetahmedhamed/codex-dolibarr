#!/usr/bin/env bash
set -Eeuo pipefail
test "${CI:-}" = true
test "${GITHUB_ACTIONS:-}" = true
native=/work/native-dolibarr-ci/htdocs
php -v
php -r 'if (PHP_VERSION !== "8.2.32") exit(1);'
php deploy/tests/test_m119_office_expenses.php
php deploy/tests/test_m119_employee_ui.php
php deploy/tests/test_m119_native_drafts.php "$native"
php deploy/tests/test_m119_native_identity.php "$native" --without-auth
php deploy/tests/test_m119_native_capabilities.php "$native"
php deploy/tests/test_m119_native_provenance.php "$native"
php deploy/tests/test_m119_native_workflow.php "$native"
php deploy/tests/test_m119_native_storage.php "$native"
php deploy/tests/test_m119_receipts.php
php deploy/tests/test_m119_receipt_ui.php
php deploy/tests/test_m119_native_migration.php "$native"
php deploy/tests/test_m119_native_receipts.php "$native"
php deploy/tests/test_m119_pilot_configuration.php "$native"
python3 deploy/tests/test_m119_employee_http.py
php deploy/tests/test_m119_pilot_filesystem_ci.php
php deploy/tests/test_m119_mariadb_ci.php "$native"
