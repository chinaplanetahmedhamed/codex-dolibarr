# M076 — Connected ChinaPlanet workspace Phase 1

- Owner: Ahmed Hamed
- Coordinator and writer: Agent 007
- Date: 2026-09-12
- Status: RELEASE_PREPARATION
- Approval: Ahmed explicitly approved execution after the M075 scope gap was explained: `ok, do it`.
- Current environment: local Dolibarr only. Staging and Production require later, separate approvals.

## Objective

Move the first accepted September 11 daily-work screens into authenticated Dolibarr using real native/customer and ChinaPlanet Operations records. The prototype remains a visual and workflow reference only; its browser-memory example data is never copied into Dolibarr.

## Fixed Phase 1 scope

1. A real manager landing view showing actionable operation summaries.
2. An employee `عملي اليوم` view using the existing permission-scoped internal task service.
3. Customer-first drill-down showing the real customer, related ChinaPlanet operations, native sales orders, and native projects.
4. A guided operation create/edit flow with customer-dependent project, order, and existing-shipment selectors.
5. Clear operation owner, next action, deadline, and wait reason.
6. Idempotent operation creation so a retry with the same request identity returns the existing operation instead of duplicating it.
7. Preserve the M075 shared navigation, branding, contrast, and sidebar behavior.

## Excluded

- Finance allocation, supplier payments, FX, commissions, accounting entries, and customer balances.
- CargoX, DHL, email/message sending, document publication, and external integrations.
- Customer portal permission or projection changes.
- New schema or module-state changes.
- DNS, proxy, React, WordPress, mobile, VPS, staging, or Production changes.
- Importing prototype people, companies, amounts, or example transactions.

Excluded functions must be absent or visibly marked unavailable; a simulation control must never look connected.

## Data and security rules

- Require authenticated internal ChinaPlanet Operations read rights for the workspace.
- Reuse `InternalTaskAuthorizationService` for employee task visibility; do not broaden task scope.
- Every customer, operation, project, order, and shipment lookup is entity-scoped.
- Project and order links must belong to the selected customer. Existing shipment links must match entity, customer, and project.
- Use existing Dolibarr and ChinaPlanet Operations objects/tables. No shadow ERP and no core edit.
- Do not create email, payment, document-publication, or external side effects.

## Local acceptance

- Manager view renders real actionable summaries and customer cards.
- `عملي اليوم` shows only the signed-in employee's authorized tasks with owner, deadline, next step, or wait reason.
- Customer detail shows its real related operations, native sales orders, and native projects.
- Guided operation create uses named contextual selectors rather than manual project/order IDs.
- Create one `LOCAL TEST -` operation linked to a local customer and an appropriate native order/project where available.
- Reload and customer drill-down show the same persisted operation.
- Replaying the same create request does not create a duplicate.
- Existing operation detail remains reachable and accurately shows customer, owner, next action, status, and tasks.
- Desktop and phone-width Arabic UI checks pass.
- Touched PHP files pass syntax checks, the focused M076 contract test passes, and `git diff --check` passes.

## Release boundary

This approval covers the bounded local implementation and local synthetic acceptance only. It does not authorize GitHub push, staging, Production, DNS, proxy, or business-data changes. Any later release must use an immutable reviewed artifact, backups, verification, rollback, and Ahmed's exact environment approval.

## Production authorization — 2026-09-12

Ahmed subsequently authorized publishing the exact reviewed M076 Phase 1 scope to canonical Production at `erp.visiontrading.biz`, including immutable artifact upload, restricted compare/preflight, scoped backups, apply, verify, status, and runtime acceptance. This does not authorize DNS/proxy changes, staging changes, schema migration, configuration repair, team-membership changes, synthetic records, or any deployment to `erp.chinaplanet.org`.

## Local acceptance evidence — 2026-09-12

- Local runtime: `http://127.0.0.1:8088/custom/chinaplanetoperations/workspace.php`.
- Existing local task prerequisites were repaired without schema change: ten existing `todo` tasks with a null stage were linked to the protected `todo` stage, and existing internal user `admin-agent` was added to the existing `managment` group. A broken local `cpkanban` runtime symlink was repointed to this worktree so the native user-group trigger could complete.
- Created exactly one labeled operation: ID `99`, ref `CP-OR-2026-000002`, title `LOCAL TEST - M076 Workspace acceptance 20260912`.
- Context links verified: customer `mofeed group` (`socid=73`), project `PJ2607-0006` (`id=87`), sales order `SO2607-0035` (`id=85`), owner `admin-agent` (`id=5`), team `managment` (`id=3`). No shipment or external action was created.
- Six standard order tasks were created and assigned to `admin-agent`; an authorization-scoped service check as that real local user returned exactly those six tasks in `view=my`.
- Retry proof: two replays with the original request identity both returned operation `99`; the database retained one matching operation and six tasks.
- Customer drill-down shows the operation once plus the linked native order and project. Operation detail remains reachable and shows the correct customer, owner, next action, status, and six tasks. Manager and customer views were checked in the authenticated desktop UI.
- Responsive CSS provides explicit `980px`, `700px`, and `430px` adaptations for summaries, action cards, task cards, customer panels, controls, and one-column phone layout. A true emulated phone viewport remains for independent visual review; no claim is made from CSS inspection alone.
- PASS: syntax checks for every touched PHP file, `test_m058_source_contract.php`, `test_m059_source_contract.php`, `test_m076_workspace_phase1.php`, and `git diff --check`.
