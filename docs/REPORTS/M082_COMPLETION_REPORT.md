# M082 Completion Report — Staff-first local completion

- Date: 2026-09-14
- Environment: local Dolibarr 23.0.3 plus a schema-only disposable database
- Outcome: COMPLETED LOCALLY
- Release state: not pushed or deployed; independent reviews required

## Delivered outcome

The China Planet OS operation screen now carries the staff workflow from a validated customer order into native purchasing and supplier settlement. Staff explicitly chooses the supplier, purchase costs, inherited VAT, original currency and exchange rate, validates the supplier order and invoice separately, and records partial or full payment against an open bank in the same currency.

The same screen now shows shipment and document readiness from stored evidence only. It cannot simulate booking, loading, departure, arrival, delivery, document approval, customer publication, CargoX, DHL, or email.

## Native and recovery controls

- Supplier orders, supplier invoices, supplier payments, and bank movements are native Dolibarr records.
- Supplier invoices are linked to their supplier orders and retain the same project, supplier, VAT, costs, currency, and explicit exchange rate.
- Unique request, object, and payment guards prevent duplicate creation.
- Partial and fully paid retries return the same native payment and bank evidence.
- Changed payload, wrong bank currency, overpayment, wrong customer/project/entity, unauthorized customer, and missing evidence fail closed.
- Optional customer-funds attribution settles only an existing reservation against the exact same-currency native supplier payment; it creates no additional cash movement.

## Isolated UAT evidence

The final disposable run passed with:

- 3 native quotations and 3 validated sales orders;
- 3 native customer invoices and 6 native customer receipts;
- 3 native supplier orders and 3 native supplier invoices;
- 6 native supplier payments and 12 total bank entries;
- USD, CNY, and EGP kept separate, with no automatic FX;
- one explicit USD managed-funds attribution;
- booking/loading and three reviewed document types projected from evidence;
- departure, arrival, and delivery left pending without evidence;
- customer write access blocked.

The clone contained schemas and synthetic fixtures only and was deleted after the run. UAT did not read, copy, or mutate real business rows.

## Local runtime and UI

- The additive `llx_cpo_supplier_action` table is installed and empty in the working local database.
- Source contracts, M077–M081 regressions, PHP syntax checks, and `git diff --check` passed.
- The authenticated operation page rendered the supplier and shipment/document panels on desktop.
- At 390px, the page width and document scroll width were both 390px.
- The current Sarah staff UAT account lacks supplier read permissions, and the UI correctly displays the permission requirement instead of the writer. No user permission was changed.

## Boundaries and next gate

No real customer, supplier, financial, shipment, or document record was created or changed. No external request, email, CargoX, DHL, automatic FX, overpayment, Push, Staging, or Production action occurred.

The next step is release preparation: freeze the local commit and obtain independent Accounting, Security, and QA reviews. Any Push or deployment requires a new exact approval for the named commit/artifact and environment.
