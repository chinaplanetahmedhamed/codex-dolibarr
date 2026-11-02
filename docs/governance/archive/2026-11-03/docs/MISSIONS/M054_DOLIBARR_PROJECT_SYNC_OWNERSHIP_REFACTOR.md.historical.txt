# M054 — Dolibarr project-to-Kanban sync ownership refactor

## Mission control

- **Mission ID:** M054
- **Type:** REFACTORING
- **Status:** DEVELOPER_REPORTED
- **Owner:** Agent 007
- **Target:** Local source and GitHub review only
- **Production/staging/database/configuration/business data/React:** Forbidden
- **Ahmed approval:** Ahmed accepted the next bounded Dolibarr refactoring step with “ok, do it” after PR #33 review/merge and continuation were proposed.
- **Approval date:** 2026-08-13

## Objective

Move the project-to-Kanban synchronization implementation from Customer Center into the CP Kanban module that owns the card and column behavior, while preserving the legacy include path as a compatibility wrapper and keeping all runtime behavior unchanged.

## Verified context

- Source baseline: `origin/main` merge `b9751709423e826e4a3b5c9de9171f981424c9a0`.
- Dolibarr source/runtime version: 23.0.3; local PHP: 8.3.21.
- The local database was unavailable during startup, so enabled-module state is **Unknown** and no database-dependent test is authorized or claimed.
- Existing source shows Customer Center triggers/pages and CP Kanban board loading `chinaplanetcustomercenter/lib/projectsync.lib.php`, although the implementation writes CP Kanban cards and columns.

## Scope

1. Move the implementation to `custom/cpkanban/lib/projectsync.lib.php` without changing function behavior.
2. Keep `custom/chinaplanetcustomercenter/lib/projectsync.lib.php` as a thin compatibility include.
3. Update known first-party call sites to use the CP Kanban-owned path.
4. Add source characterization coverage for ownership, compatibility, call sites, and unchanged function contracts.
5. Run PHP lint, focused source regressions, and `git diff --check`.
6. Create the M054 completion report and update the source changelog.

## Out of scope

- Database/schema, module enablement, configuration, permissions, routes, UI, navigation, customer-visible behavior, business data, or runtime deployment.
- Renaming the historical `cpcc_project_*` functions or changing their SQL/transaction behavior.
- Extracting Customer Center portal/admin-shell infrastructure or removing every remaining module dependency.
- React, WordPress, staging, backup ERP, or production.

## Acceptance criteria

- The full project-sync implementation has one canonical owner under `custom/cpkanban/lib/`.
- The legacy Customer Center include path remains functional through a thin `require_once` wrapper.
- CP Kanban no longer loads project-sync implementation from Customer Center.
- Customer Center project page and trigger load the canonical CP Kanban path explicitly.
- Project/card mapping, customer ownership, entity predicates, status/column mapping, and create/update behavior are textually unchanged by the move.
- Admin ERP parity: behavior unchanged.
- Customer Portal parity: behavior and projections unchanged.
- Mobile parity: not applicable; no API or customer contract change.

## Rollback

Revert the M054 source commit. No database, configuration, business-data, staging, or production rollback is required.
