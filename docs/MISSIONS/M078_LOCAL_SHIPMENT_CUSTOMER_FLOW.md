# M078 — Local shipment and customer flow

- Owner: Ahmed Hamed
- Coordinator and writer: Agent 007
- Date: 2026-09-13
- Status: LOCAL_IMPLEMENTATION
- Environment: local only

## Objective

Make the Operations register readable and complete the shortest native workflow for adding a customer and tracking that customer's shipment in Shipping Center.

## Approved scope

- Correct the low-contrast Operations table text, reference links, and service labels in the dark admin shell.
- Add a permission-gated action from a new Operation to Dolibarr's native customer form.
- Return from native customer creation to the Operation form with the new customer selected.
- Add permission-gated Operations shortcuts to Shipping Center and native shipment creation.
- Create one clearly local test customer named `Sarah Company` and one local shipment at status `Loaded & Going to Export Customs`.
- Keep missing container, route, bill-of-lading, carrier, and ETA facts visibly pending; do not invent them.

## Invariants

1. The customer is a native Dolibarr Third Party, not a parallel custom customer record.
2. Every shipment created by the Operations shipment service is a real `chinaplanetlogistics` record and therefore appears in Shipping Center.
3. A shipment selected on an Operation must belong to the same customer and, when selected, the same project.
4. Customer creation is available only to users with native Third Party creation rights.
5. Shipping Center actions are visible only to users with the matching logistics read/write rights.
6. This local test creates no email, customer publication, CargoX/DHL action, external request, or real business data.
7. New manual shipments are private unless staff explicitly select customer-portal visibility. Shipment visibility and individual public-update publication are separate explicit choices and are editable with logistics-write permission.

## Acceptance

- The previously unreadable Operations row is legible in the dark shell.
- The native Add Customer action returns to the new Operation form and auto-selects the created customer.
- `Sarah Company` exists once locally as a customer.
- Its shipment exists once locally in Shipping Center at loaded/export-customs status and remains private.
- Focused source-contract, syntax, existing shipment-link regression, visual desktop, and narrow-width checks pass.

## Hard boundaries

- No push, staging, production, DNS, proxy, email, or other external write.
- No real customer or shipment facts are invented.
- No change to Dolibarr core files or parallel customer/shipment tables.
