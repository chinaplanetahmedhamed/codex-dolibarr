# M061 — Customer portal trust and operational consistency

## Mission control

- **Mission ID:** M061
- **Type:** IMPLEMENTATION
- **Status:** LOCAL_IMPLEMENTATION_COMPLETE_RUNTIME_ACCEPTANCE_PENDING
- **Architect owner:** Agent 007 Central
- **Implementation owner:** Agent 007 Central
- **Required reviewers:** Customer Portal, Shipping, Accounting, Mobile, UI/UX, Security, QA
- **Baseline:** `38493b775889f77015db174fb3e5c79d2b493669` (tree-identical to fetched `origin/main` `95402e7f22665c9efd61d648f1e545094aaf47c9`)
- **Target environment:** Local source and isolated tests completed; authenticated local/staging runtime acceptance pending
- **Production:** Not authorized. M061-R01 compare/preflight/apply/verify/rollback requires a separate exact approval.
- **Ahmed approval:** APPROVED — “PLEASE IMPLEMENT THIS PLAN”
- **Approval date:** 2026-08-19

## Business objective

Make the authenticated customer project, shipment and invoice views reflect auditable Dolibarr operational truth. Remove misleading project completion, normalize unambiguous shipment facts, and make invoice/date empty states explicit without exposing internal data or adding new commercial features.

## Approved scope

1. Derive customer project progress, status, current step, next step and timeline from the existing eight evidence-backed lifecycle stages. CP Kanban progress remains staff-only.
2. Require the final lifecycle stage and Dolibarr project closure before customer-visible `100% / Completed`.
3. Use the same lifecycle projection in Customer Center web and the existing mobile API shape.
4. Show a clear customer-safe empty state when no shipment is linked to a project; never infer or create a link.
5. Normalize shipment status forward from actual dates: ATD requires at least On Vessel; ATA requires at least Arrived and takes precedence.
6. Reject future actual dates and ATA earlier than ATD. Never regress status or invent dates.
7. Normalize a container count below one to one when a container number exists.
8. Preserve transactional shipment audit behavior and explicit, post-commit email opt-in.
9. Display standard Dolibarr invoice due date/overdue state, a safe no-lines state, and unambiguous `YYYY-MM-DD` portal dates.
10. Add source-only M061-R01 repair tooling and contract tests. The tool must be inert until separately installed and approved.

## Out of scope

- Payment actions, invoice PDF downloads, WhatsApp, action center, new document categories or schema.
- Automatic project/shipment linking, invoice-line creation, financial posting or accounting recalculation.
- Customer notification from the bulk repair.
- Staging installation, deployment, database repair or production access.
- Dolibarr core edits, module-state changes or permission expansion.

## Required behavior

### Project lifecycle projection

- Lifecycle keys remain `confirmed`, `funded`, `deposit`, `supplier_paid`, `service_invoice`, `shipment`, `documents`, `closed`.
- Progress is the rounded percentage of completed lifecycle stages.
- `closed` requires the existing operational completion rules and Dolibarr project status `2`.
- The first incomplete stage is current; the next incomplete stage is next; other incomplete stages are planned. Independently proven later stages may remain completed.
- An active project cannot be customer-visible as completed even if an internal Kanban card is at 100%.

### Shipment normalization

- Canonical status order is `0,1,8,9,2,3,4,5,6,7`.
- ATD advances only statuses earlier than `2` to `2`.
- ATA advances only statuses earlier than `3` to `3` and wins when both actual dates exist.
- Later statuses are preserved. Clearing a date does not roll status backward.
- Actual dates later than the active entity's current day and ATA earlier than ATD are rejected before persistence.
- A non-empty container number with `nb_containers < 1` persists as `1`.
- Normal staff edits use the existing audit event transaction. Email remains impossible unless the existing customer-visible and send-email checkboxes are both explicitly selected.

### M061-R01 repair contract

- Commands: `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback`, `status`.
- `dry-run` reports exact affected row IDs/counts and excludes ambiguous rows.
- `apply` requires the recorded dry-run identity, unique code/database backups and an explicit request token; it is transactional and idempotent.
- Repair changes only the three unambiguous rules above, records internal `customer_visible=0` correction events, and sends no email.
- `verify` proves resulting rows, event scope and unchanged ambiguous rows; `rollback` restores only the exact backed-up repair set.

## Security and visibility

- Every customer read remains bound to active `entity` and authenticated `user->socid` / object `fk_soc`.
- URL `socid`, object IDs and download tokens never widen scope.
- Supplier identity/cost, margin, internal notes, staff-only files, raw Kanban progress and internal correction details remain private.
- No new public or anonymous route is introduced.

## Public interface impact

- Existing mobile response shapes and public opaque IDs remain unchanged.
- `progress_percent`, project `status`, current/next step and timeline semantics change from customer-visible Kanban position to operational lifecycle evidence.
- Shipment status values may advance after a valid staff save or an approved M061-R01 repair; no new status codes are added.

## Required verification

- PHP lint for every changed PHP file and `git diff --check`.
- Focused lifecycle, web/mobile parity, shipment normalization/audit/email, invoice/date and repair-helper tests.
- Regressions: M010, M011, M017, M020, M024, M031–M033, M054 and M056.
- Authenticated customer A/B isolation and forged `socid`, object ID and download-token denial.
- Arabic/English desktop and 390px customer views plus affected mobile tests.

## Completion and release boundary

Developer completion requires a report under `docs/REPORTS/`, changelog and portal parity updates, frozen file/test evidence and explicit unresolved runtime limitations. M061 local completion does not authorize Git publication, staging, helper installation, repair apply or production deployment. M061-R01 remains a separate release approval.
