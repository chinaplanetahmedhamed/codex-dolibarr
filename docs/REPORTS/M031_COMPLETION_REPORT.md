# M031 Completion Report

## Outcome

The local dashboard Documents card totals customer-visible document records. The external Documents page now focuses on shipment documents, customer uploads, and gallery files; the legacy Commercial Documents block is temporarily hidden there without deleting its data or its admin-only view. Phone-width search controls and shipment document cards were tightened for a clear single-column layout.

## Source and environment

- Branch: `integration/current-source`
- Environment: local development only
- Deployment: none

## Files changed

- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/documents.php`
- `custom/chinaplanetcustomercenter/css/documents.css`
- `custom/chinaplanetcustomercenter/lib/portal.lib.php`
- `deploy/tests/test_m030_generic_labels_navigation.php`
- `deploy/tests/test_m031_customer_document_count.php`
- `docs/MISSIONS/M031_CUSTOMER_DOCUMENT_COUNT_CORRECTION.md`
- `docs/REPORTS/M031_COMPLETION_REPORT.md`
- `docs/CHANGELOG.md`

## Data, accounting, and visibility impact

- Database/schema/configuration: none.
- Accounting: none.
- Customer visibility: reduced on this page. The external portal no longer renders the legacy Commercial Documents block; shipment publications remain restricted by current entity, authenticated `fk_soc`, active status, and `customer_visible=1`.
- Staff/admin access to the Commercial Documents block is preserved for later work.
- The external portal retains a generic Documents title plus admin-only upload and file-management controls.
- Shipment collaboration events accept both Dolibarr `id` objects and database `rowid` records, preventing customer Documents-page warnings without changing visibility.

## Verification

- PHP lint for changed PHP: PASS.
- M031 focused source checks: PASS.
- M030 regression checks: PASS after moving its document-scope assertions to the shared helper.
- Customer operation-report security regression checks: PASS.
- M010 customer project command-center security and UI regression checks: PASS.
- Authenticated local browser check for China Gate (`socid=10`): PASS; the dashboard and Documents page both show 8 published customer documents.
- Authenticated local layout check: PASS; the 8 commercial documents render as compact individual cards showing only document name, type, and related shipment.
- Customer Documents-page collaboration-event identifier regression check: PASS.
- External Documents phone layout check: PASS after correction; responsive search and single-column shipment cards fit the phone viewport, document information retains a readable content column, and download actions occupy their own full-width row without horizontal page overflow.
- `git diff --check`: PASS.

## Rollback

Revert the M031 files listed above. No data rollback is required.
