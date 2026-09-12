# M077 — Connected finance and shipment foundation

- Owner: Ahmed Hamed
- Coordinator and writer: Agent 007
- Date: 2026-09-12
- Status: C02_LOCAL_ACCEPTANCE
- Approval: Ahmed explicitly requested connecting the accepted workspace to the main system, beginning with payments and shipment tracking.
- Current environment: source and local synthetic verification only. Production requires a separate immutable release and explicit approval.

## Objective

Replace the Phase 1 “not connected” boundary with reviewed, auditable links to native Dolibarr records. Payments and shipment tracking are first because supplier settlement, document release, CargoX, DHL, customer statements, and portal publication depend on them.

This Mission does not turn every prototype control on at once. A control becomes connected only after its native owner, authorization, persistence, audit, idempotency, failure behavior, and customer projection are verified.

## Durable integration inventory

| Capability | Current owner | State before M077 | M077 direction |
|---|---|---|---|
| Customers and contacts | Dolibarr Third Parties/Contacts | Connected | Preserve native records and entity ownership. |
| Products/services | Dolibarr Products/Services | Connected but not fully surfaced in the workspace | Reuse native products/services; never create a parallel catalog. |
| Customer quotations/orders/invoices/payments | Dolibarr native commercial/accounting objects | Partially connected | Treat validated native records as authoritative. |
| Customer funds held for later use | CP Customer Center ledger | Partial and unsafe for explicit reservation lifecycle | Add pending/confirmed receipt registration plus same-currency allocation lifecycle without duplicating native cash. |
| Supplier/company payment | Dolibarr supplier invoices/payments and CP customer-cost allocation | Partial | Link an allocation to an actual native supplier payment or posted CP debit; never infer payment from a task/status. |
| Operations/tasks | ChinaPlanet Operations | Connected | Preserve owner, next action, deadline, team authorization, and idempotency. |
| Shipment tracking | ChinaPlanet Logistics | Connected when manually created; partial from Operations | Create/link an auditable draft shipment from eligible operations without false booking/loading/sailing claims. |
| Milestones | ChinaPlanet Logistics event history | Connected manual provenance | Keep planned versus actual dates and source labels distinct; carrier API remains a later reviewed integration. |
| Operation documents | ChinaPlanet Operations versioned documents | Partial | Add explicit reviewer/deadline workflow later; receipt/opening never means approval. |
| CargoX/DHL | External systems | Missing | Draft/task/reference first; no submission, label purchase, or message without separate authorization. |
| Customer portal | Customer Center server-side projection | Partial | Publish only customer-owned, approved safe fields after positive/negative access tests. |
| Email/messages | Dolibarr mail + Operations drafts | Partial | Drafts are allowed; external send remains an explicit action and approval boundary. |

## Financial invariants

1. Dolibarr bank, customer payment, supplier invoice/payment, and accounting records remain authoritative.
2. A managed customer receipt starts `pending` and can become `confirmed` only through an explicit authorized action tied to validated native evidence. Historical receipts are never auto-confirmed.
   Eligible evidence is limited to a native payment applied to a Dolibarr deposit invoice (`facture.type=3`); an ordinary customer-invoice payment is already consumed and cannot become managed funds.
3. Confirmation never creates a second cash movement. The CP record is an allocation/control record referencing the native receipt.
4. One confirmed receipt may fund multiple operations, shipments, invoices, or services in the same original currency.
5. Allocation states are `reserved`, `settled`, or `reversed`. Reserved funds reduce unallocated funds but are not reported as spent. Settled funds require a real posted debit/native supplier payment.
6. Total active allocation plus any native Dolibarr use of the deposit credit cannot exceed the confirmed receipt. Evidence is revalidated on every reservation, and total settlement against one debit cannot exceed that debit.
   A database guard rejects new native use of a deposit credit while managed allocations from that source remain reserved or settled; staff must reverse the managed reservation first. This closes cross-connection races rather than relying on UI sequencing.
7. Original currencies remain separate. No FX is invented and no currencies are netted without an explicit paired conversion record, rate, date, and source.
8. Customer funds, company fees, supplier costs, and profit remain distinct. Supplier identities/costs stay private unless explicitly approved for the customer projection.
9. An unsettled reservation may be reversed with a reason. A settled attribution cannot release funds while its posted debit remains active; the native debit must be reversed first through a later reviewed correction flow.
10. Settlement evidence must be a posted debit bound to the same exact target, customer, project (when present), and currency; opening, adjustment, transfer, and FX records are not settlement evidence.
11. Every transition is entity/customer scoped, permission gated by the calling page, transaction protected, and append-only audited.

## Shipment invariants

1. A draft shipment may be created only from an entity-scoped operation with a real customer and a shipping service type.
2. The operation’s customer, project, and order links are copied only after ownership validation.
3. The draft stores entered origin/destination as planning data and starts at status `0`; it does not claim booking, loading, departure, arrival, CargoX, originals, DHL, or customer notification.
   Operation-created drafts also start `customer_visible=0` and remain absent from every external portal/mobile shipment and shipment-document projection until a later explicit publication workflow is reviewed.
4. Creation and linking are one transaction and idempotent: retry returns the already linked shipment.
5. Later milestone updates retain manual/API provenance and planned/actual separation.
6. Loading completion follow-ups will be idempotent: one collection follow-up and one export-document draft review per shipment. The collection deadline convention must come from configuration or the approved existing calendar convention.

## Delivery phases

### M077-C01 — Finance and shipment foundation

- Add receipt/allocation/audit schema and a server-side service enforcing the financial invariants.
- Add an operation-to-draft-shipment service with entity/customer/project/order validation and no external side effects.
- Add focused contract tests and synthetic reconciliation examples for two shipments plus one standalone service.

### M077-C02 — Staff workflow

- Add permission/CSRF-protected staff screens for pending receipt, confirmation, reservation, and settlement.
- Add the explicit “Create linked draft shipment” action to eligible Operations pages.
- Show per-currency confirmed, pending, reserved, settled, unallocated, due-now, and future-due values without cross-currency totals.

### M077-C03 — Supplier/order/document follow-ups

- Reuse native supplier Third Parties, products/services, supplier orders, invoices, and payments.
- Implement idempotent loading-completion tasks and document draft review.
- Keep CargoX, originals release, DHL, and external sending as independent reviewed actions.

### M077-C04 — Customer-safe projection

- Publish only approved customer-owned shipment, document, and statement fields.
- Add authenticated positive/negative ownership tests and representative Arabic/English desktop/phone acceptance.

## Local acceptance

- Additive schema uses an exclusive migration lock, applies twice without duplicate business rows, preserves existing data, and never drops partially created tables on an uncertain DDL failure.
- One labeled synthetic pending receipt cannot affect available funds.
- Explicit confirmation references validated native evidence and does not duplicate the native payment.
- Two synthetic shipments and one standalone service can reserve one same-currency receipt without exceeding it; retry is idempotent.
- Reservation is not presented as spending; settlement requires a validated actual debit; reversal restores the correct available amount with audit history retained.
- An eligible synthetic shipping operation creates exactly one status-0 linked shipment; retry returns the same shipment.
- Mismatched entity/customer/project/order/evidence, ordinary-invoice payment evidence, invalidated payment evidence, native deposit-credit consumption, mixed currency, unrelated debit, excessive allocation/settlement, changed retry payload, settled reversal, and unauthorized customer projection fail closed.
- Relevant PHP syntax checks, focused tests, migration replay/rollback checks, and `git diff --check` pass.

## Hard boundaries

- No Production or staging mutation, no DNS/proxy change, and no React/WordPress change under this local approval.
- No Production data and no unlabeled historical-money confirmation.
- No email, CargoX submission, DHL purchase, customer publication, or other external write.
- No fixed employee assignment and no staff membership changes beyond separately approved prerequisites.
- The accepted September 11 prototype is design authority only; its sample people, companies, amounts, and transactions are never imported.

## Review and release gates

Because this Mission changes financial allocation and cross-module persistence, Accounting, Security, and QA must review frozen source and migration evidence before any release artifact is prepared. Production compare/preflight and Production apply/verify remain separate approval gates.

## C01/C02 local acceptance evidence — 2026-09-12

- Source contract and legacy finance/workspace regression tests pass.
- A real MariaDB test used a random disposable schema-only database. It copied table definitions but no customer/business rows, applied each new table definition twice, then persisted one labeled receipt, three allocations, two settlements, one reservation reversal, and one linked shipment draft. It verified idempotent retries, exact customer/project/order ownership, status `0`, baseline milestone `inquiry`, private provenance, allocation ceiling, and reconciliation. The disposable database was dropped and its absence verified.
- The existing local Dolibarr database was backed up before the additive browser-acceptance migration. Full dump: `/var/folders/9v/6t5wqy7x6yn4q4x7x27_bh3h0000gn/T/m077-local-schema-20260912T152459Z-010c5ee2/dolibarr-before-m077.sql`, SHA-256 `ef47c9e5f7f17dfc660a85869e43883c71ef9d0278ee8ab41f5a3a82afeee2a3`. Prestate recorded all three M077 tables absent; absence-list SHA-256 `4e0ca5a0e2107ea1a14803a15ad0462e5044b1bb8cb7074c107baa5f307f7341`.
- The three M077 tables were added empty to the local database. Replay succeeded and post-apply row counts remained zero.
- Authenticated local admin browser acceptance loaded `Managed Customer Funds`, selected the existing labeled local customer `LOCAL TEST - Connected MVP 20260911`, and rendered the four guided actions plus empty receipt/allocation tables. A read-only operation check rendered the new linked-draft action for `CP-SB-2026-000001`, showed its explicit no-booking/no-loading/no-sailing warning, and did not activate it. Database verification retained `fk_shipment=0` and zero rows in all three M077 tables.
- This acceptance proves the local foundation and UI rendering only. No real financial record, shipment, message, CargoX/DHL action, or external write was created. Phone-width acceptance and independent Accounting/Security/QA review remain open before release.

## C01/C02 correction evidence — 2026-09-12

- The first independent review returned `CORRECTION_REQUIRED`: managed-funds evidence was too broad, native deposit-credit use was not reconciled, invoice currency and settlement target were not bound, operation-created drafts could enter customer projections, migration failure cleanup could remove newly created tables, and the test matrix/UI totals were incomplete.
- The corrected implementation restricts confirmation to payments on native deposit invoices, revalidates evidence and subtracts consumed native credit at reservation time, enforces invoice currency, and binds settlement to the exact target/customer/project/currency with a settlement ceiling.
- Native payment, deposit-invoice, and credit rows are locked during reserve/settle reconciliation, and guarded insert/update triggers reject later direct native credit use while managed allocations remain active. The staff page now uses the same authoritative reconciliation, displays native consumption and blocked/invalid evidence, and offers only covered reservations for settlement.
- Staff can explicitly bind a real posted expense to an operation, shipment, project, or customer invoice; unbound general expenses remain valid ledger entries but cannot settle a managed allocation.
- Shipment records now have an explicit publication flag. Existing shipments remain visible by default; operation-created status-0 drafts are private, and external list/card/dashboard/project/document/gallery/mobile projections require visibility.
- Commercial/export-document list, detail, print-source, and mobile paths also require the linked shipment to be visible; a customer-visible document cannot reveal a private draft shipment.
- The local migration helper now takes an exclusive named lock and preserves all tables after uncertain partial DDL instead of attempting destructive cleanup.
- The disposable real-MariaDB test now covers deposit-only eligibility, invalidated evidence, authoritative native-credit availability, the exact stale-snapshot/two-connection allocation race, all-settled-first coverage regardless of row order, currency mismatch, changed request payload, unrelated settlement evidence, settlement ceiling, settled-reversal rejection, two independent private draft shipments, and private commercial/export-document list/detail denial. Static regressions and authenticated local admin rendering passed; no action form was submitted and persistent M077 business tables remain empty.
- Guard acceptance rejects a same-name no-op trigger, safely repairs definitions only when no active allocation exists, blocks removal while allocations remain active, permits metadata-only corrections, and blocks linked-credit source or accounting-amount changes. Reserve and settle fail closed unless the exact reviewed trigger bodies verify while the shared lifecycle lock is held.
- The local database has the two M077 native-credit guards installed; their exact definitions are verified rather than trusting trigger names. The three M077 business tables remain empty; no receipt, allocation, shipment, document, or other business row was created in the persistent database.

### Native-credit guard operator contract

- Scope: linking a customer deposit credit to an invoice, changing its source, or changing its accounting/currency amounts is blocked when the same deposit source has a confirmed managed receipt with a `reserved` or `settled` allocation. Ordinary invoices, payments, new unconsumed deposit credits, supplier credits, metadata-only edits, and unlink corrections are not blocked.
- Operator message: `Managed funds are active; correct or release the managed allocation before using this deposit credit`. Staff must return to Managed Customer Funds and reverse an unsettled reservation with a reason before retrying native allocation. A settled attribution is not reusable money and requires the reviewed debit-correction flow before later release.
- Lock order: reserve/settle hold the shared guard-lifecycle lock, then lock native payment, deposit invoice, and credit rows before managed receipt/allocation rows. The deterministic two-connection test commits native use immediately after the initial receipt hint and proves the later locking read rejects the stale allocation decision.
- Disable/rollback: init, repair, removal, reserve, and settle share the lifecycle lock. Removal and repair fail closed if the active-allocation query fails or any reserved/settled allocation exists. With none active, removal drops only the two exact verified triggers before normal module removal; table/data rollback remains independent and is never automatic.

## Remaining integration checklist after C01/C02

- This deposit-invoice-backed workflow is the safe first slice for customer advances held on account; it is not the whole payments program. Staff still need a reviewed guided workflow to create the native advance/deposit invoice, post the bank receipt, and then confirm it here without navigating raw accounting screens.
- Native supplier third parties, supplier orders/invoices/payments, and their exact allocation evidence remain C03.
- Loading completion, export-document draft review, original-document release, CargoX submission, DHL purchase, and external email/message sending remain separate idempotent actions with their own approval and audit rules.
- Customer shipment/document/statement publication remains C04 and requires authenticated positive/negative ownership checks plus Arabic/English desktop/phone acceptance.
