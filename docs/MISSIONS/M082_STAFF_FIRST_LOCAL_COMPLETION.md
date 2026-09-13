# M082 — Staff-first local completion

- Owner: Ahmed Hamed
- Coordinator and writer: Agent 007
- Date: 2026-09-14
- Status: COMPLETED LOCALLY — RELEASE REVIEW REQUIRED
- Environment: isolated local Dolibarr and disposable synthetic data only

## Objective

Finish the smallest safe staff-first operational cycle from the China Planet OS screens while keeping native Dolibarr records authoritative and the customer portal read-only.

## Current baseline

- Customer → quotation → acceptance → validated sales order: implemented locally.
- Validated sales order → customer invoice → partial/full customer receipt with native bank evidence: implemented locally.
- Linked shipment and customer-safe tracking/documents: implemented locally.
- Customer quotation, order, invoice, payment, shipment, document, and report visibility: implemented and authenticated locally.

## C02 — Supplier purchase and payment

From the existing operation screen, staff can:

1. select one active native Dolibarr supplier;
2. create one supplier-order draft linked to the same project and operation;
3. enter explicit purchase quantity, unit cost, VAT, supplier reference, currency, and expected delivery date;
4. validate the supplier order separately;
5. create and validate one native supplier invoice linked to that supplier order;
6. record partial or full native supplier payment to an open same-currency bank account;
7. link the exact native supplier payment to an existing same-currency customer-funds allocation when staff explicitly selects it;
8. show supplier ordered, invoiced, paid, and remaining values to staff only.

No supplier is inferred from free text. No purchase price, VAT, currency, customer-funds allocation, margin, or FX is inferred. Overpayment and mismatched customer/project/supplier/currency evidence fail closed.

## C03 — Shipment and document follow-up

- Reuse the existing linked shipment record and Shipment Center rather than duplicate it.
- Show one staff checklist for booking, loading, departure, arrival, delivery, Commercial Invoice, Packing List, BL, and customer publication.
- Each completed item must be backed by the existing shipment milestone or current reviewed document evidence.
- Missing evidence remains pending; no booking, loading, sailing, arrival, delivery, CargoX, DHL, email, or customer publication is simulated.
- Provide direct staff links to complete the required shipment or document record in the existing China Planet OS screens.

## C04 — Full isolated local UAT

- Clone the local runtime/database into an isolated disposable environment before native financial writes.
- Run complete synthetic USD, CNY/RMB, and EGP cycles through the custom staff screens and verify reload persistence.
- Verify native proposal, sales order, customer invoice/payment/bank entry, supplier order/invoice/payment/bank entry, shipment, reviewed documents, staff tasks, and customer read-only projections.
- Verify duplicate submission, retry/recovery, wrong currency, overpayment, cross-customer/project/entity, unauthorized customer, draft/cancelled source, and missing evidence fail closed.
- Verify Arabic/English, RTL/LTR, desktop and 390px behavior.
- Delete the disposable UAT clone after preserving non-sensitive evidence. Do not mutate the working local business database.

## Files expected

New, if required:

- a staff-only supplier workflow service, controller include, and view;
- one additive action-marker schema and migration for idempotency/recovery;
- focused source, disposable persistence, permission, and full-cycle tests;
- completion report and parity/changelog updates.

Modified only as required:

- `custom/chinaplanetoperations/operations/view.php`;
- `custom/chinaplanetoperations/css/operations.css`;
- the China Planet Operations module descriptor;
- the existing project lifecycle projection so supplier completion is based on native verified evidence.

## Hard boundaries

- Synthetic/disposable records only; no real customer, supplier, financial, shipment, or document record.
- No automatic FX, overpayment, standalone advance, guessed supplier/cost/VAT/currency, progress invoice, credit note, or accounting-policy invention.
- No customer write action, email, external send, CargoX, DHL, API call, Push, Staging, or Production.
- No Dolibarr core edit and no deletion of existing business data.
- M081 customer isolation remains mandatory; supplier identity, cost, margin, bank data, and internal evidence never enter the customer portal.

## Release boundary

After local completion, freeze one commit and obtain independent Accounting, Security, and QA reviews. Staging and Production remain separate named approvals with immutable artifacts, backup, preflight, rollback, and authenticated acceptance.

## Exact approval required

> أوافق على تنفيذ M082 محلياً فقط وفق `M082_STAFF_FIRST_LOCAL_COMPLETION.md`: إنشاء واعتماد طلب وفاتورة مورد أصلية، تسجيل دفعة مورد جزئية/كاملة بقيد بنكي أصلي وربط اختياري صريح بأموال العميل بنفس العملة، متابعة الشحنة والمستندات من الأدلة الحالية، ثم UAT كامل داخل نسخة Dolibarr وقاعدة بيانات معزولة وقابلة للحذف باستخدام بيانات صناعية فقط. لا بيانات حقيقية، لا FX تلقائي، لا overpayment، لا إرسال خارجي، ولا Push أو Staging أو Production.

Approval recorded from Ahmed on 2026-09-14.

## Local completion evidence

- The operation screen now contains a staff-only supplier purchase and payment path plus an evidence-only shipment/document checklist.
- Native supplier order, supplier invoice, supplier payment, and bank records are authoritative; the action-marker table prevents duplicate creation and supports evidence-based recovery.
- The exact empty additive marker schema was installed locally. It contains no business row.
- A schema-only disposable database completed three connected USD, CNY, and EGP cycles: native quotation, validated sales order, customer invoice, 40/60 customer receipts, supplier order, supplier invoice, 40/60 supplier payments, and native bank entries.
- One USD cycle also attributed an explicitly selected same-currency customer-funds reservation to the exact native supplier payment without recreating cash.
- Retry after partial and full payment returned the same native evidence. Wrong-currency banks, overpayment, changed retry payload, and customer writes failed closed.
- Synthetic shipment booking/loading and reviewed Commercial Invoice, Packing List, and BL evidence projected as complete; departure, arrival, and delivery stayed pending when evidence was absent.
- The disposable database was deleted after every test run. No real business row, external message, Push, Staging, or Production action occurred.
- The authenticated local operation screen rendered the new supplier and follow-up panels at desktop and 390px with no page-wide overflow. The tested staff account correctly showed the supplier-permission gate rather than exposing an unauthorized writer.

Release preparation remains separately gated by a frozen commit and independent Accounting, Security, and QA reviews.
