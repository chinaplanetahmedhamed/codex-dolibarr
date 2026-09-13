# M079 — Staff-first operations dashboard

- Owner: Ahmed Hamed
- Coordinator and writer: Agent 007
- Date: 2026-09-13
- Status: IN_PROGRESS
- Approval: Ahmed instructed Agent 007 to focus first on the company dashboard and keep the customer portal view-only for now.
- Environment: local only

## Objective

Make the China Planet OS dashboard the staff starting point for daily work. Dolibarr remains the native backend, but staff start common workflows from simple guided China Planet OS screens.

## Approved first slice

- Put the most important work-start actions at the top of the Admin Dashboard and Operations Workspace.
- Cover customer order, sourcing, purchasing, quality control, shipping booking, and customs/documents.
- Route every action to the existing permission-scoped guided Operation form, not a Dolibarr core creation screen.
- Keep manager oversight and worker task views separate.
- Keep Customer Center view-only; do not add customer request/order creation.

## Approved second slice

- Start the native commercial cycle from eligible customer operations: order, sourcing, and purchasing.
- Keep five explicit staff decisions: save quotation draft, validate quotation, record customer acceptance, create the sales order, and validate the order.
- Use mapped sellable products, native VAT, configured currency rates, operation customer/project ownership, CSRF, native permissions, and a per-operation replay lock.
- Require a staff-entered customer acceptance evidence reference before marking a quotation accepted.
- Lock operation customer/project/order context after its quotation workflow starts.
- Show validated orders in the read-only Customer Center, while hiding draft and canceled orders from external users and the mobile API.
- Do not send quotations, email customers, create invoices, record payments, create supplier orders, or mutate shipments.

## Acceptance

- An authorized staff user can start each supported work type from either dashboard in one click.
- The selected work type is retained in the guided Operation form.
- Unauthorized users do not receive operation-creation actions.
- Desktop and phone layouts remain usable.
- PHP syntax, focused source checks, authenticated rendering, and `git diff --check` pass.
- The commercial flow creates one linked native quotation and one linked native order without duplication.
- A quotation/order with a mismatched entity, customer, or project fails closed.
- Customer users cannot open operation screens and cannot see draft/canceled customer orders.

## Deferred workflow slices

- Create invoices, record customer receipts, supplier orders/payments, documents, and shipment milestones from the operation workspace.
- Full click-through acceptance for each order-cycle type.

## Local acceptance evidence — second slice

- Completed through the China Planet OS staff operation screen on 2026-09-13; no Dolibarr core creation screen was used.
- Operation `CP-OR-2026-000006` (`rowid=109`) belongs to Sarah Company (`socid=76`) and project `M078-SARAH-SYNTH-ALPHA` (`project=89`).
- The guided five-step flow created and validated native quotation `PR2609-0018`, recorded customer acceptance, then created and validated native sales order `SO2609-0040` (`rowid=92`).
- The persisted order contains one mapped product line, quantity `2`, and total `200.00 USD`; the staff screen retained the accepted quotation and validated order after reload.
- Sarah's authenticated Customer Center session showed `SO2609-0040` and its line/totals as read-only. Direct access by Sarah to operation `109` was denied.
- A staff attempt to change the linked project after quotation start was rejected with `Customer and project are locked after the quotation workflow starts.` The original customer/project remained unchanged.
- Focused PHP syntax/source checks, related dashboard/shipment checks, and `git diff --check` passed locally.
- Slice result: `COMPLETE_LOCAL`. M079 remains `IN_PROGRESS` because the deferred financial, supplier, document, and shipment workflow slices are not included.

## Hard boundaries

- Local source and local runtime only.
- No customer-portal write actions, financial posting, email, external provider call, staging, Production, or push.
- No schema or Dolibarr core change.
