#!/usr/bin/env bash
set -Eeuo pipefail
test "${CI:-}" = true
test "${GITHUB_ACTIONS:-}" = true
native=/work/native-dolibarr-ci/htdocs
php -v
php -r 'if (PHP_VERSION !== "8.2.32") exit(1);'
failed_suites=0
check() {
    if "$@"; then return 0; fi
    failed_suites=$((failed_suites + 1))
    printf 'FAILED_SUITE %s\n' "$2"
}
check python3 -B deploy/tests/test_m119_workspace_deployer.py
check python3 -B deploy/tests/test_m119_receipt_deployer.py
check python3 -B deploy/tests/test_m119_receipt_acceptance_deployer.py
check python3 -B deploy/tests/test_m119_design_deployer.py
check python3 -B deploy/tests/test_m119_design_acceptance_deployer.py
check python3 -B deploy/tests/test_m119_dropdown_deployer.py
check python3 -B deploy/tests/test_m119_entry_deployer.py
check python3 -B deploy/tests/test_m119_finance_deployer.py
check python3 -B deploy/tests/test_m119_menu_deployer.py
check python3 -B deploy/tests/test_m119_modal_menu_deployer.py
check php deploy/tests/test_m119_finance_gate.php
check php deploy/tests/test_m119_financial_plan.php
check php deploy/tests/test_m119_workspace_documents.php
check php deploy/tests/test_m119_workspace_native.php "$native"
check php deploy/tests/test_m121_office_sqlite_decimal_cast.php "$native"
check php deploy/tests/test_m121_office_employee_summary.php
check php deploy/tests/test_m121_office_draft_workflow.php
check php deploy/tests/test_m121_office_request_progress.php
check php deploy/tests/test_m121_office_lifecycle.php "$native"
check php deploy/tests/test_m119_office_expenses.php
check php deploy/tests/test_m119_standalone_module.php "$native"
check php deploy/tests/test_m119_native_menu_conditions.php "$native"
check php deploy/tests/test_m119_staff_access.php "$native"
check php deploy/tests/test_m119_employee_ui.php
check php deploy/tests/test_m119_staff_ui_regression.php
check php deploy/tests/test_m119_native_menu_sync.php
check php deploy/tests/test_m119_native_drafts.php "$native"
check php deploy/tests/test_m119_native_identity.php "$native" --without-auth
check php deploy/tests/test_m119_native_capabilities.php "$native"
check php deploy/tests/test_m119_native_provenance.php "$native"
check php deploy/tests/test_m119_native_workflow.php "$native"
check php deploy/tests/test_m119_native_storage.php "$native"
check php deploy/tests/test_m119_receipts.php
check php deploy/tests/test_m119_receipt_ui.php
check php deploy/tests/test_m119_native_migration.php "$native"
check php deploy/tests/test_m119_native_receipts.php "$native"
check php deploy/tests/test_m119_pilot_configuration.php "$native"
check python3 deploy/tests/test_m119_employee_http.py
check python3 -B deploy/tests/test_m121_office_preview_access.py
check python3 -B deploy/tests/test_m119_ui_updater.py UiUpdaterTests.test_real_root_replacement_refuses_drift_and_symlink_and_restores
check php deploy/tests/test_m119_pilot_filesystem_ci.php
check php deploy/tests/test_m119_mariadb_ci.php "$native"
check php deploy/tests/test_m119_financial_mariadb_ci.php "$native"
printf 'FAILED_SUITES=%s\n' "$failed_suites"
test "$failed_suites" -eq 0
