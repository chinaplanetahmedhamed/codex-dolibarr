# M036 — Shipping Orders Admin Integration and Dashboard Metrics

## Mission control

- **Mission ID:** M036
- **Type:** IMPLEMENTATION
- **Status:** COMPLETED
- **Architect owner:** Agent 007 — ChinaPlanetOS Central Architect & Operations Agent
- **Implementation owner:** Agent 007 — approved Developer/Tester
- **Required reviewers:** Shipping Operations, Security, UI/UX, QA
- **Dependencies:** M035 customer actual shipping prices and direct booking
- **Target environment:** Local development only
- **Production:** Forbidden
- **Ahmed approval:** Approved in the active task with `ok do it`
- **Approval date:** 2026-07-23

## Business objective

Give staff one reliable place to receive and operate customer shipping bookings, show each booking under the related customer's Customer Center, and show actionable shipping-order totals on the main admin dashboard without mixing shipping bookings with native Dolibarr sales orders.

## Current system context

- Verified source: authenticated customer direct booking creates exactly one `cpo_operation` with `service_type='shipping_booking'`, the session-derived `fk_soc`, and an immutable accepted customer-rate snapshot.
- Verified schema: `cpo_operation` is the operational source of truth and already supports `fk_soc`, `fk_commande`, `fk_shipment`, stage, owner, next action, customer visibility, entity, and active status.
- Verified source: `chinaplanetsp_booking` links the customer, accepted rate, and operation with entity-scoped uniqueness/idempotency.
- Verified admin behavior: Operations can filter `service_type` and a single stage, while Shipping Center currently launches Tracking, Shipments, and Shipping Prices only.
- Verified Customer Center behavior: the selected-customer view currently counts native `commande` orders and customer-visible Operations reports but has no separate shipping-order summary.
- Verified main-dashboard behavior: native Sales Orders and shipment tracking have separate metrics; direct shipping bookings do not yet have a dedicated metric group.

## Scope

1. Add an internal, entity-scoped Shipping Orders list backed by existing `cpo_operation` rows of type `shipping_booking`.
2. Add route, accepted price, customer, lifecycle bucket, dates, next action, and linked shipment information using safe existing fields/snapshots.
3. Add mutually exclusive dashboard buckets: Active, Confirmed/SO Received, Closed, and Cancelled, plus Total Current.
4. Add Shipping Orders as a launch destination in Shipping Center.
5. Add an internal selected-customer Shipping Orders card and recent-order list in Customer Center, scoped by `entity + fk_soc`.
6. Add a Shipping Orders KPI section to the main admin dashboard with links to filtered results.
7. Preserve the existing authenticated customer booking history and customer-safe status projection.
8. Add focused source/runtime/security/responsive tests and update living documentation and portal/mobile parity.

## Out of scope

- Creating, validating, invoicing, or posting a native Dolibarr sales order automatically.
- Changing customer price calculation, rate publication, accepted-price snapshot, or booking submission behavior from M035.
- Changing Operations generic stage vocabulary or adding a database migration/index without measured need.
- Mobile API or native mobile implementation; record exact follow-up as `PENDING`.
- Notifications, email, WhatsApp, carrier integrations, accounting postings, staging, Git push, or production deployment.

## Components to inspect

- `custom/chinaplanetshippingprices/class/CustomerShippingBookingService.class.php`
- `custom/chinaplanetshippingprices/customer_booking.php`
- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `custom/chinaplanetoperations/operations/index.php` and operation view/edit routes
- `custom/chinaplanetoperations/sql/llx_cpo_operation.sql`
- `custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_booking.sql`
- `custom/chinaplanetlogistics/index.php`
- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/admin_dashboard.php`
- related module rights, shared admin shell, CSS, language files, and deployment tests

## Architecture and data flow

`Customer direct booking → chinaplanetsp_booking → cpo_operation(service_type=shipping_booking, fk_soc) → internal Shipping Orders view → optional linked shipment → completed/closed`

- `cpo_operation` remains the authoritative mutable operational record.
- `chinaplanetsp_booking` remains the immutable customer acceptance and price evidence.
- The new admin list and dashboards are projections only; they do not duplicate or mutate bookings.
- A future native sales order, if business policy requires it, must be created by an explicit separately approved action and linked through `fk_commande`.
- One internal row equals one current-entity, active `cpo_operation` where `service_type='shipping_booking'`; a linked `chinaplanetsp_booking` may join at most once through its unique `entity + fk_operation` relation.
- For M035-originated rows, accepted route, equipment, quantity, total, currency, and acceptance date come only from `chinaplanetsp_booking.snapshot_json`; mutable Operations service data must not reconstruct the accepted price.
- Manual/legacy Shipping Booking Operations remain visible with source `Manual / Legacy` and blank `Not captured` accepted-price fields.
- Lifecycle buckets use one exhaustive shared predicate:
  - `confirmed`: `stage='published'`, `booking_status IN ('accepted','confirmed')`, and at least one `shipping_order_no` or `carrier_booking_no` exists;
  - `active`: every other non-terminal record, including a `published` row with incomplete confirmation evidence;
  - `closed`: `completed`;
  - `cancelled`: `cancelled`;
  - `total_current`: all active records (`status=1`) of this service type.
- Dashboard buckets are mutually exclusive. Total Current includes all four buckets. The word `Succeeded` is not used because carrier confirmation is not operational completion.
- The UI distinguishes `CP-SB-…` ChinaPlanet Booking Request, Carrier Shipping Order/SO, and the optional related native Dolibarr Sales Order.
- A linked M035 booking must match the operation on `entity`, `fk_soc`, and `fk_operation`. A mismatch is an internal integrity exception: the internal register flags it, hides the accepted snapshot, and Customer Center excludes it rather than attributing evidence to a different customer.

## Permissions and visibility

- Internal Shipping Orders routes call `cpo_require($user, 'read')` before any query or rendering; write actions continue to require `cpo_can($user, 'operation_write')` and existing CSRF behavior.
- Shipping Center shows the launcher only to users with Operations read. Selected Customer Center queries/rendering require internal user, Operations read, and a validated `socid`; the shared external branch receives no M036 data or links.
- All list, count, and detail queries enforce the current `entity`.
- Customer Center selected-customer projections enforce both current `entity` and the internal selected `fk_soc`; external users cannot use an arbitrary customer selector.
- Customer-facing history continues deriving customer ownership from authenticated `user->socid` and never trusts a URL customer ID.
- Customer-visible output must not expose forwarder identity, supplier/internal costs, margin/profit, internal notes, staff/private files, batch/source identifiers, or internal workflow metadata.
- The internal list uses a dedicated field allowlist; it never renders or serializes raw `input_json`/`snapshot_json`, fingerprints/evidence/hashes, party payloads, forwarder, internal notes, costs/margin/source metadata, or private files.
- Admin projections may show operational fields already authorized to internal Operations users but must not introduce broader permissions or default grants.

## Database, accounting, and configuration impact

- **Database:** No schema or data migration expected. Existing rows/tables are read and existing operation links remain authoritative.
- **Accounting:** None. Shipping-order status/counts do not create orders, invoices, payments, revenue, or ledger entries.
- **Configuration/module state:** None. No module activation/deactivation or permission expansion.

## Functional requirements

1. Shipping Center contains a responsive `Shipping Orders / طلبات الشحن` destination.
2. The Shipping Orders list defaults to all current-entity, `status=1` shipping bookings and supports allowlisted lifecycle-bucket, validated customer, and escaped text filtering.
3. Each row clearly shows ChinaPlanet booking request reference, source, customer, accepted route, equipment/quantity, accepted total/currency, Carrier SO/booking number, lifecycle status, created/acceptance date, next action, and linked shipment when present. Manual/legacy rows show `Not captured` rather than fabricated evidence.
4. Total Current, Active, Confirmed/SO Received, Closed, and Cancelled counts use one shared exhaustive bucket definition and link to matching filtered results.
5. The main admin dashboard shows the same five counts in a separate Shipping Orders section; native Sales Order KPIs remain unchanged.
6. A selected customer's Customer Center shows a Shipping Orders card and recent list filtered by exact `entity + fk_soc`.
7. Empty states are explicit and bilingual; links and labels remain Arabic/English and direction-safe.
8. Existing customer booking submission, booking history, native order metrics, shipment tracking, and financial views remain unchanged.
9. Dashboard customization adds the new widget key to the server allowlist and preserves save/reload/reset behavior for both existing and new layouts.

## Success criteria

- One submitted M035 booking appears once in the internal Shipping Orders view and under the correct customer's Customer Center.
- Another customer cannot be included by forged `socid`, direct route, or cross-entity query behavior.
- Dashboard counts reconcile exactly with the filtered Shipping Orders list for every bucket.
- Confirmed/SO Received requires `published` plus explicit accepted/confirmed booking state plus a Carrier SO/booking number; incomplete `published` remains Active with an internal warning. `completed` is Closed and `cancelled` only Cancelled.
- A synthetic ownership mismatch is flagged internally, never exposes its accepted snapshot under another customer, and is excluded from the selected-customer Customer Center projection.
- No customer-facing response contains restricted internal/commercial fields.
- PHP lint, focused source tests, local runtime checks, and relevant regression tests pass.
- Admin dashboard, Shipping Center, Shipping Orders, and selected Customer Center pass at 1440, 1024, 768, 390, and 320 CSS pixels without material horizontal overflow; Arabic RTL and English/LTR direction-sensitive data remain readable.

## Technical restrictions

- Extend existing Dolibarr custom modules; no standalone application or core edit.
- No production, staging, Git push, schema mutation, data deletion, module-state change, or accounting action.
- Preserve current dirty-worktree changes and make narrowly reviewable edits only.
- Do not expose secrets, private files, supplier costs, internal margin/profit, or cross-customer data.
- Do not expand scope; return material ambiguity to the Architect.

## Risks and rollback concept

- **Risks:** misleading KPI semantics, duplicated records, cross-customer leakage, exposing immutable price evidence incorrectly, slow broad queries, and responsive overflow from dense admin rows.
- **Rollback concept:** revert only M036 application/documentation/test edits. No schema or data rollback is expected because this mission adds read-only projections and navigation over existing records.

## Testing requirements

- PHP lint for every changed PHP file.
- Focused source contract test for route permission, entity/fk_soc scope, bucket definitions, safe fields, and links.
- Local runtime test against synthetic/current local rows that reconciles bucket counts and verifies customer isolation without modifying business data.
- Regression tests for M035 booking, unified admin shell, customer portal isolation, and relevant existing dashboards.
- Browser verification at the required widths in Arabic RTL and English/LTR where a language control exists, including semantic cards at narrow widths, 44px controls, overflow, wrapping, focus, 200% text zoom, empty states, and real local data.
- Authorization coverage: admin; internal user with Operations read; internal Customer Center user without Operations read; external Customers A/B; anonymous; forged customer; foreign entity; mismatched booking/operation and shipment/operation ownership; invalid bucket/text; duplicate-join resistance; prohibited-payload scan.
- Dashboard layout save/reload/reset covers a new user and a saved older layout that lacks the M036 widget.
- `git diff --check` and review of only the M036 diff against pre-existing dirty changes.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping Operations | m030_shipping_pricing_review | PASS | 2026-07-23 | Shared source-of-truth, immutable evidence, distinct identifiers, exhaustive buckets, ownership integrity, runtime reconciliation and M035 accounting regression verified |
| Security | m030_security_review | PASS | 2026-07-23 | Authorization-before-query, exact entity/customer ownership, safe allowlist, mismatch suppression, external direct-route denial and isolation evidence verified |
| UI/UX | m030_ui_qa_review | PASS | 2026-07-23 | Filtered KPI reconciliation, semantic responsive cards, warnings, bidi, 320–1440px layouts and actual Chrome 200% zoom verified without overflow |
| QA | m030_ui_qa_review | PASS | 2026-07-23 | Seven-row browser fixture, lifecycle/ownership coverage, save/reload/reset, cleanup `rows=0`, layout restore `backup=no`, regressions and diff check verified |
| Architect | Agent 007 | APPROVED | 2026-07-23 | Conditions accepted; scope frozen for local implementation |
| Ahmed | Ahmed Hamed | APPROVED | 2026-07-23 | `ok do it` after exact M036 recommendation |
| Architect acceptance | Agent 007 | ARCHITECT_ACCEPTED | 2026-07-23 | Required reviewers PASS; canonical source matches local runtime; local scope complete with no staging, production, or Git publication |

## Completion report contract

The implementation owner must create `docs/REPORTS/M036_COMPLETION_REPORT.md` from the official report template. The report must reconcile the approved scope, every changed file, data/config/permission impact, tests, isolation/security, responsive evidence, risks, deployment boundary, rollback, and reviewer results.
