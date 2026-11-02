# M053 — Dolibarr module dependency map and first cycle removal

## Mission control

- **Mission ID:** M053
- **Type:** REFACTORING
- **Status:** APPROVED
- **Owner:** Agent 007
- **Target:** Local source only
- **Production/staging/database:** Forbidden
- **Ahmed approval:** “Approve merging Dolibarr PR #32 into main, creating a clean M053 branch/worktree from the updated origin/main, and starting the local-only module dependency mapping and first cycle-removal refactor. No database, configuration, business-data, staging, React, or production changes.”
- **Approval date:** 2026-08-13

## Objective

Establish an explicit dependency map for the six ChinaPlanetOS custom modules and remove the first hard service-level dependency from Shipping Prices back into Operations without changing booking behavior, schema, permissions, customer visibility, pricing, or transaction ownership.

## Scope

1. Record current descriptor and runtime PHP dependencies between custom modules.
2. Add a source characterization test for the Shipping Prices ↔ Operations service cycle.
3. Replace `CustomerShippingBookingService`'s direct construction of `OperationsService` with an injected, narrow operation gateway contract.
4. Implement that gateway in the Operations module and compose it at the existing customer-booking entry point.
5. Update the existing M035 local integration/source tests to retain behavior and security checks.
6. Run PHP lint, focused source tests, relevant M035/M043 tests that are safe locally, and `git diff --check`.

## Out of scope

- Database/schema, module enablement, configuration, permissions, customer or business data.
- UI redesign, public API changes, accounting, pricing, shipment logic, documents, mobile behavior, React, WordPress, staging, or production.
- Moving routes between modules or removing every historical dependency in one change.
- Worktree/branch deletion or deployment-tool consolidation.

## Acceptance criteria

- `CustomerShippingBookingService` no longer imports, constructs, or names `OperationsService`.
- A narrow interface owned by Shipping Prices defines the required operation-writing behavior.
- An Operations-owned adapter implements the interface using the unchanged `OperationsService` methods.
- The existing customer-booking route injects the adapter explicitly.
- Booking transaction, idempotency, ownership, immutable commercial snapshot, customer-safe projection, and accounting non-mutation checks remain intact.
- The dependency map labels the remaining edges and follow-up cycles without claiming full decoupling.
- Admin ERP parity: internal implementation only; behavior unchanged.
- Customer Portal parity: behavior unchanged and covered by existing M035 contracts.
- Mobile parity: not applicable; no API or customer projection change.

## Rollback

Revert the M053 source commit. No data, configuration, schema, or runtime rollback is required because this Mission is local-only and behavior-preserving.
