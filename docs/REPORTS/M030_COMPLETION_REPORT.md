# M030 Completion Report

## Outcome

The local ERP and customer portal now use generic **Invoices**, **Documents**, and **Reports** terminology. Export Documents remains intact but is hidden from navigation. Financial Center is the main Finance destination, with a prominent Finance Allocation Center action. Reports and every linked Shipping SOP management page now load the shared ChinaPlanetOS admin design for staff.

## Source context

- Mission: M030
- Branch: `integration/current-source`
- Base commit at implementation: `68ad0f4`
- Environment: local development only
- Deployment: none

## Files changed for M030

- `custom/chinaplanetcustomercenter/admin/financial.php`
- `custom/chinaplanetcustomercenter/admin/financial_portfolio.php`
- `custom/chinaplanetcustomercenter/admin/operations.php`
- `custom/chinaplanetcustomercenter/admin_dashboard.php`
- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php`
- `custom/chinaplanetcustomercenter/css/ux_fixes.css`
- `custom/chinaplanetcustomercenter/invoice.php`
- `custom/chinaplanetcustomercenter/invoices.php`
- `custom/chinaplanetcustomercenter/lib/admin_shell.lib.php`
- `custom/chinaplanetcustomercenter/lib/operations.lib.php`
- `custom/chinaplanetcustomercenter/operation_report.php`
- `custom/chinaplanetcustomercenter/operation_reports.php`
- `custom/chinaplanetcustomercenter/project.php`
- `custom/chinaplanetshippingprices/sop/index.php`
- `custom/chinaplanetshippingprices/sop/categories.php`
- `custom/chinaplanetshippingprices/sop/edit.php`
- `custom/chinaplanetshippingprices/sop/view.php`
- `deploy/tests/test_m030_generic_labels_navigation.php`
- `docs/MISSIONS/M030_GENERIC_PORTAL_LABELS_AND_NAVIGATION_CORRECTION.md`
- `docs/REPORTS/M030_COMPLETION_REPORT.md`
- `docs/CHANGELOG.md`

Pre-existing M029 edits in `financial.php`, `invoice.php`, `financial.lib.php`, and `mobile_api_data.lib.php` were preserved and not rolled back.

## Database, configuration, and permissions

- Database/schema changes: none.
- Accounting changes: none.
- Customer visibility: no expansion. The Documents count requires matching `entity`, `fk_soc`, active publication status, and `customer_visible=1`.
- Export Documents routes, code, and data remain available; only navigation/menu visibility changed.
- Orders & Support was not changed.

## Verification

- PHP lint: PASS for all modified PHP files.
- `deploy/tests/test_m029_core_customer_funds.php`: PASS.
- `deploy/tests/test_m030_generic_labels_navigation.php`: PASS.
- `deploy/tests/test_customer_operation_reports_security.php`: PASS.
- `deploy/tests/test_m010_customer_project_command_center.php`: PASS.
- Shared-shell route audit: PASS; all PHP pages using `cpcc_admin_shell_open()` now load the shared admin stylesheet directly or conditionally, excluding the shell definition itself.
- Authenticated local browser checks: PASS for customer dashboard cards/routes, project milestones, Invoices page, admin navigation, Financial Center landing/action, and Shipping SOP landing shell. The Reports missing-style defect was reproduced before correction.
- Reports and linked SOP subpage corrections were verified by PHP lint, focused source assertions, and the complete shared-shell asset audit; final visual refresh remains for Ahmed.
- Narrow-layout visual checks: PASS for Financial Center and Shipping SOP landing page.
- Browser console errors on checked pages: none.
- `deploy/tests/test_shipping_sop_integration.php`: not completed because the test process could not connect to the local database at `127.0.0.1:8889`. Source checks and rendered local page checks passed.

## Remaining risks and follow-up

- The hidden legacy Export Documents feature should receive its own future redesign/upgrade mission before being advertised again.
- Dolibarr menu metadata can require a module/menu refresh after a later deployment; the shared ChinaPlanetOS navigation already reflects the new state locally.
- Orders & Support remains intentionally unchanged for its planned major upgrade.

## Rollback

Revert only the M030 changes listed above. No database rollback is needed. Restoring the previous shared navigation and module descriptor re-exposes the legacy Export Documents menu and makes Finance Allocation Center the primary Finance route again.
