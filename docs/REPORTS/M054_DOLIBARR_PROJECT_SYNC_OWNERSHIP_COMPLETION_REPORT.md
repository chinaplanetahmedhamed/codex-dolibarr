# M054 — Project-to-Kanban sync ownership completion report

## Context and outcome

- **Mission:** M054
- **Branch:** `codex/m054-dolibarr-composition-boundary`
- **Baseline:** `origin/main` at `b9751709423e826e4a3b5c9de9171f981424c9a0`
- **Status:** `DEVELOPER_REPORTED`
- **Outcome:** The project-to-Kanban synchronization implementation now has one canonical owner under CP Kanban. The historical Customer Center path remains a compatibility include, so behavior and existing third-party includes are preserved.

## Files

### Added

- `custom/cpkanban/lib/projectsync.lib.php` — canonical implementation.
- `deploy/tests/test_m054_project_sync_ownership.php` — source ownership and compatibility characterization.
- `docs/MISSIONS/M054_DOLIBARR_PROJECT_SYNC_OWNERSHIP_REFACTOR.md` — approved scope.
- `docs/REPORTS/M054_DOLIBARR_PROJECT_SYNC_OWNERSHIP_COMPLETION_REPORT.md` — this report.

### Modified

- `custom/chinaplanetcustomercenter/lib/projectsync.lib.php` — compatibility include only.
- `custom/chinaplanetcustomercenter/projects.php` — canonical CP Kanban include.
- `custom/chinaplanetcustomercenter/core/triggers/interface_99_modChinaPlanetCustomerCenter_CustomerProjectSync.class.php` — canonical CP Kanban include.
- `custom/cpkanban/board.php` — loads its local canonical implementation.
- `docs/CHANGELOG.md` — M054 entry.
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md` — parity assessment.

### Removed

- None. The legacy include path remains available.

## Impact

- **Database/schema:** None.
- **Configuration/module state:** None.
- **Business data/documents:** None.
- **Permissions/security/customer visibility:** Unchanged. Existing entity/customer predicates and the external read-only project behavior remain in the moved implementation and existing pages.
- **Routes/UI/API:** Unchanged.
- **Admin ERP:** Internal ownership refactor only; behavior unchanged.
- **Customer Portal:** Projection, navigation and write restrictions unchanged.
- **Mobile App:** `NOT_APPLICABLE`; no mobile API or screen contract changed.
- **Staging/production/React:** Not accessed or changed.

## Verification

Passed:

- PHP lint for every changed/added PHP file.
- `deploy/tests/test_m054_project_sync_ownership.php`.
- `deploy/tests/test_cpkanban_denial_headers.php`.
- `deploy/tests/test_unified_admin_shell.php`.
- `deploy/tests/test_m035_customer_shipping_booking_source.php`.
- `deploy/tests/test_m053_module_dependency_cycle.php`.
- `git diff --check`.

Not run:

- Database-dependent/runtime project-card synchronization. The local Dolibarr 23.0.3 runtime was found, but its local database connection was unavailable. No database repair or configuration change was authorized.

Known baseline issue, not caused by M054:

- `deploy/tests/test_m010_customer_project_command_center.php` reaches its unrelated portal CSS assertion and fails because it expects the historical exact token `@media(max-width:900px)`. M054 does not modify `portal.css`; the failure is present in the `origin/main` source shape and all earlier M010 ownership/security assertions pass before that point. Fixing the stale CSS assertion is outside this ownership-only Mission.

## Risks and follow-up

- Legacy external code may still include the Customer Center path; the compatibility include intentionally protects it.
- Historical `cpcc_project_*` function names remain unchanged to avoid a broad API rename.
- A separate Mission may update remaining module boundaries or repair stale historical test contracts after explicit scope approval.

## Rollback

Revert the M054 Git commit. Because this is a local source-only ownership move with a retained compatibility path, no database, configuration, module, business-data, document or runtime rollback is required.
