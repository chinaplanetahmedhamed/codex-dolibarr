> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M030 — Generic Portal Labels and Navigation Correction

## Mission control

- **Mission ID:** M030
- **Type:** CORRECTION
- **Status:** COMPLETED
- **Architect owner:** Agent 007
- **Implementation owner:** Agent 007
- **Required reviewers:** Customer Portal, UI/UX, Security, QA
- **Dependencies:** M029 local customer-funds integration
- **Target environment:** Local development
- **Production:** Forbidden
- **Ahmed approval:** Approved in chat with the exact bounded correction list
- **Approval date:** 2026-07-21

## Business objective

Make the current ERP and customer portal terminology generic enough for future invoices, documents, and reports; simplify the admin finance entry point; and make every linked Reports and Shipping SOP admin page use the approved ChinaPlanetOS layout.

## Current system context

- Customer pages still label all customer invoices as Vision service invoices.
- The dashboard counts and opens the legacy export-document area instead of the current customer-scoped shipment Documents page.
- Export Documents remains visible in the internal navigation although the feature is temporarily deferred.
- The Finance menu opens the allocation form instead of the frequently used financial portfolio.
- The Shipping SOP landing page and its linked category, editor, and detail pages open the shared admin shell; some linked pages do not load its stylesheet.
- Reports list and detail pages use the shared admin shell for staff but do not load its stylesheet.

## Scope

1. Rename visible invoice and report labels to generic bilingual labels.
2. Change the customer dashboard Documents card to the customer-scoped shipment Documents page and count active customer-visible shipment publications.
3. Update project lifecycle presentation to generic Invoice, Documents, and Operation finished wording without changing completion rules.
4. Hide Export Documents from internal menus without deleting its routes, code, or data.
5. Make Financial Center the main Finance destination and expose Finance Allocation Center as a prominent action inside it.
6. Load the shared admin design on the Shipping SOP landing, category, editor, and detail pages.
7. Load the shared admin design on the staff Reports list and detail pages while preserving the external customer design.

## Out of scope

- No redesign or deletion of the Export Documents feature.
- No Orders & Support changes.
- No accounting, payment, allocation, schema, permission, API, or mobile behavior changes.
- No Git push, backup-erp deployment, or production deployment.

## Components to inspect

- Customer Center board, invoices, reports, project view, lifecycle labels, portal and admin navigation.
- Customer-scoped shipment publication table and Documents page.
- Finance portfolio/allocation routes and shared CSS.
- Shipping Prices SOP shell asset loading across all linked SOP pages.
- Reports list/detail conditional admin asset loading.

## Architecture and data flow

Dolibarr and the existing custom modules remain authoritative. This correction changes presentation and routing only. Document counts are derived from active customer-visible shipment publications joined to the scoped customer shipments. Legacy commercial-document routes remain intact but are not advertised in the menus.

## Permissions and visibility

The authenticated external user's `fk_soc` remains authoritative. Existing `entity` and customer ownership checks are preserved. No supplier, margin, private-note, or internal-document visibility is added.

## Database, accounting, and configuration impact

- **Database:** None.
- **Accounting:** None.
- **Configuration/module state:** Menu visibility and landing URL only; no module activation change.

## Functional requirements

1. Customer-facing labels use Invoices, Reports, and Documents generically.
2. The customer Documents card opens `documents.php` and shows the customer-visible shipment publication count.
3. Project lifecycle shows Invoice issued, Documents complete, and Operation finished terminology.
4. Export Documents is absent from shared and Dolibarr module menus while its direct routes continue to exist.
5. Finance navigation opens Financial Center; Financial Center contains a major Finance Allocation Center action.
6. Shipping SOP landing, category, editor, and detail pages render inside the same styled admin shell as other ChinaPlanetOS pages.
7. Staff Reports list and detail pages render in the shared admin design; external customers retain the customer portal design.

## Success criteria

- Focused static checks and PHP lint pass.
- Local authenticated admin and customer pages render the new wording and routes.
- Customer document counts remain scoped by both active entity and customer ownership.
- No production or staging system is accessed.

## Technical restrictions

- Extend existing Dolibarr only; no core edits.
- No production access or action.
- Do not expose secrets, private files, supplier costs, internal margin/profit, or cross-customer data.
- Preserve M029 local changes and unrelated M028 artifacts.

## Risks and rollback concept

- **Risks:** stale menu cache after descriptor label changes; wording may imply broader document workflow while the legacy export section remains deferred.
- **Rollback concept:** revert the M030 file changes; no data rollback is required.

## Testing requirements

- PHP lint all changed PHP files.
- Focused source assertions for labels, URLs, hidden menu, document count scope, and SOP stylesheet.
- Local authenticated browser checks at desktop and narrow viewport for affected pages.
- `git diff --check`.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Domain | Agent 007 / Customer Portal | PASS | 2026-07-21 | Generic labels and customer Documents routing rendered locally |
| Security | Agent 007 | PASS | 2026-07-21 | Document count retains entity, customer, active, and customer-visible scope |
| QA | Agent 007 | PASS_WITH_CONDITIONS | 2026-07-21 | Lint, focused checks, route-wide shared-shell asset audit, and authenticated mismatch reproduction passed; final visual refresh remains for Ahmed, and the database-backed SOP test could not connect to local port 8889 |
| Architect | Agent 007 | ACCEPTED | 2026-07-21 | Bounded local correction accepted; no deployment authorized or performed |
| Ahmed | Ahmed | APPROVED | 2026-07-21 | Explicit “let's do small edits before push to live” request |

## Completion report contract

Create `docs/REPORTS/M030_COMPLETION_REPORT.md` with changed files, tests, visibility impact, local-only boundary, risks, and rollback.
