# M082 Completion Report — Staff-first local completion

- Date: 2026-09-14
- Environment: local Dolibarr 23.0.3 plus a schema-only disposable database
- Outcome: CORRECTED LOCALLY AFTER INITIAL REVIEW
- Release state: not pushed or deployed; renewed independent reviews required

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

The next step is renewed independent Accounting, Security, and QA review on the frozen accounting-corrected commit, followed by release preparation. Any Push or deployment requires a new exact approval for the named commit/artifact and environment.

## Accounting correction evidence

The first Accounting review found four release blockers. The corrected implementation now serializes all supplier payments for the same invoice, rereads the balance under lock, completes a pending allocation on retry without recreating cash, validates the exact bank line and `payment_supplier` link, and fully reconciles customer-receipt coverage including native deposit-credit consumption.

The disposable UAT added adversarial cases for two distinct payment tokens, a simulated crash after native cash posting, changed bank amount/link evidence, changed receipt evidence, and pre-existing native credit consumption. Every invalid case was blocked; recovery restored the same native payment and bank identifiers.

## Initial-review findings and correction

The first independent Staff and Customer reviews returned `FAIL` for two release-blocking issues. Both were corrected locally without changing permissions or business data:

- Supplier payment no longer checks the nonexistent `fournisseur.facture.payer` path. It now matches the native Dolibarr 23.0.3 supplier-payment create gate (`fournisseur.facture.creer` or its native alias) and additionally requires bank read. Supplier-order and supplier-invoice validation buttons and services follow `MAIN_USE_ADVANCED_PERMS` and the corresponding native advanced validation rights.
- The external shipment card is now strictly read-only. Customer document confirmation/comment/upload, container receipt, and original-document receipt forms are absent, and every external POST is rejected before legacy action routing. Historical collaboration events remain viewable and internal staff file actions are unchanged.

The corrected disposable UAT ran the complete native USD/CNY/EGP commercial, customer-payment, supplier-payment, bank, and shipment-evidence cycle as a non-admin staff actor. Negative bank, external-customer, and advanced-validation permission cases also passed; all counts and idempotency/security results remained unchanged. Eleven related source regressions passed after updating one stale M020 title assertion to the current M081 Documents page marker.
