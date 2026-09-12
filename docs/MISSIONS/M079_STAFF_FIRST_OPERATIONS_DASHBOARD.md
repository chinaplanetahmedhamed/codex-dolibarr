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

## Acceptance

- An authorized staff user can start each supported work type from either dashboard in one click.
- The selected work type is retained in the guided Operation form.
- Unauthorized users do not receive operation-creation actions.
- Desktop and phone layouts remain usable.
- PHP syntax, focused source checks, authenticated rendering, and `git diff --check` pass.

## Deferred workflow slices

- Create/validate customer quotations and convert them to orders from the custom operation screen.
- Create invoices, record customer receipts, supplier orders/payments, documents, and shipment milestones from the operation workspace.
- Full click-through acceptance for each order-cycle type.

## Hard boundaries

- Local source and local runtime only.
- No customer-portal write actions, financial posting, email, external provider call, staging, Production, or push.
- No schema or Dolibarr core change.
