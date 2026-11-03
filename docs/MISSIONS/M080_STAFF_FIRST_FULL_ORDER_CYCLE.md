# M080 — Staff-first full order cycle

- Owner: Ahmed Hamed
- Coordinator: Agent 007
- Date: 2026-09-14
- Status: C01–C04 COMPLETED LOCALLY — release preparation and independent reviews required
- Environment: local synthetic only

## Five-stage checklist

1. **C01 — Customer invoice and receipt:** validated customer order → draft invoice → validated invoice → partial/full native bank receipt → read-only customer projection.
2. **C02 — Supplier purchasing and payment:** native supplier order/invoice/payment with exact project and managed-funds evidence; supplier identity/cost remain staff-only.
3. **C03 — Shipment and document follow-ups:** idempotent loading/document tasks and reviewed milestones; no CargoX, DHL, email, or external send.
4. **C04 — Full local UAT and bounded fixes:** synthetic USD, CNY/RMB, and EGP cycles; staff/customer permissions, reload, RTL/LTR, desktop/390px, and negative cases.
5. **R01 — Release preparation:** freeze commit/artifact, Accounting/Security/QA and affected-domain reviews, migration/rollback evidence, then separately approved Staging and Production gates.

M082 supplied the separately approved C02–C04 scope and completed it locally on 2026-09-14. R01 remains a separate release gate.

## C01 exact scope

### Staff flow

From the existing China Planet OS operation screen:

`validated customer order → create invoice draft → validate invoice → post partial/full customer receipt → verify bank evidence → show paid/remaining`

- Full standard invoice only in C01; no progress/milestone invoice.
- An order-linked deposit uses the existing M077 guided deposit workflow only when the native order/proposal payment term has an approved deposit percentage.
- A standalone advance before an order/proposal, an overpayment, or automatic FX is blocked.
- No invoice email/PDF send and no customer write action.

### Native objects and transitions

- `Commande::STATUS_VALIDATED` is the required source.
- `Facture::createFromOrder($order, $user)` creates one linked draft while explicitly preserving and rechecking the order header/line currency, rate, product, quantity, project, and VAT.
- Staff explicitly runs `Facture::validate()`; invoice state is `DRAFT (0) → VALIDATED (1)`.
- `Paiement::create($user, 1, $thirdparty)` records one positive amount against one validated standard invoice in C01.
- `Paiement::addPaymentToBank()` creates the same-currency bank evidence.
- Multiple sequential partial receipts are allowed; each amount is `> 0` and `<= current remaining`. Dolibarr closes the invoice only when the remaining amount reaches zero; C01 never calls `setPaid()` directly.
- Existing Customer Center invoice queries remain the read authority for total, paid, credit applied, remaining, and open/partial/paid state in original currency.

### Authorization

- All actions are staff-only: authenticated user, `empty($user->socid)`, active entity, operation customer/project/order ownership, POST, and CSRF.
- Invoice draft: `chinaplanetoperations.operation_write` plus native `facture.creer`.
- Invoice validation: the native validation rule, including `facture.invoice_advance.validate` when advanced permissions are enabled.
- Receipt/bank posting: `chinaplanetoperations.operation_write`, `chinaplanetcustomercenter.financial_write`, native `facture.paiement`, and bank read access.
- Admin bypass follows current Dolibarr conventions but never bypasses entity/customer/project/currency validation.

### Durable duplicate/recovery contract

- Add one entity-scoped action-marker table with request token, payload hash, operation/customer/project/order/invoice/payment/bank links, amount/currency, and status.
- Unique request/payment constraints plus operation/invoice named locks prevent duplicate guided actions.
- Marker states: `preparing → native_created → bank_posted → confirmed`; ambiguous or changed evidence becomes `correction_required`.
- Retry with the same payload returns or resumes the same native record. Changed payload fails closed.
- Once any native payment/bank evidence exists, retry never recreates cash. Resume re-locks and verifies marker, invoice, payment allocation, bank link, customer, project, amount, currency, date, method, and reference from server-owned records.

### Proposed files

New:

- `custom/chinaplanetoperations/class/OperationBillingWorkflowService.class.php`
- `custom/chinaplanetoperations/operations/billing_workflow.inc.php`
- `custom/chinaplanetoperations/operations/billing_workflow.view.php`
- `custom/chinaplanetoperations/sql/llx_cpo_billing_action.sql`
- `deploy/migrations/m080_billing_action_schema.php`
- `deploy/tests/test_m080_stage1_billing_workflow.php`
- `deploy/tests/test_m080_stage1_real_local_persistence.php`

Modified only as required:

- `custom/chinaplanetoperations/operations/view.php`
- `custom/chinaplanetoperations/css/operations.css`
- `custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php`

Existing M077 services are reused, not duplicated:

- `custom/chinaplanetcustomercenter/class/NativeAdvanceWorkflowService.class.php`
- `custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php`
- `custom/chinaplanetcustomercenter/lib/financial.lib.php`

### Synthetic acceptance

- Disposable/local-only native records cover USD, CNY/RMB, and EGP with matching bank accounts.
- Invoice retry creates exactly one order-linked invoice and preserves customer, project, lines, VAT, totals, original currency, and exchange rate after reload.
- Two partial receipts (for example 40% then 60%) show correct paid/remaining; the invoice closes only after the final receipt.
- Double submit, concurrent guided requests, changed payload, overpayment, wrong bank currency, cross-customer/project/entity, draft/canceled source, and unauthorized customer fail closed.
- Injected failures before payment, after payment creation, and after bank posting either roll back or resume the exact server-owned evidence without duplicate cash.
- Customer Center shows only the customer's validated invoice and safe paid/remaining values; draft invoice, bank, internal notes, supplier/cost/margin, and another customer's records remain inaccessible.
- PHP syntax, focused source checks, migration replay/empty rollback, disposable MariaDB lifecycle, authenticated staff/customer desktop and 390px checks, and `git diff --check` pass before review.

## Known policy unknowns — excluded from C01

- Accounting/VAT treatment for money received before any approved proposal/order.
- Treatment of customer overpayment or credit not tied to a validated invoice/deposit.
- Automatic cross-currency settlement and realized/unrealized FX.
- Progress/milestone invoice rules.

These require an Accounting decision and Ahmed approval; C01 must not infer them.

## C01 hard boundaries

- Local source and synthetic/disposable data only.
- No real financial/business records, supplier records/payments, shipment mutation, email, CargoX, DHL, external call, customer write action, Push, Staging, or Production.
- No Dolibarr core edit.
- Accounting, Security, and QA review the frozen C01 evidence before C02 or release preparation.

## Exact approval required

> أوافق على تنفيذ M080-C01 محلياً فقط كما هو محدد في `M080_STAFF_FIRST_FULL_ORDER_CYCLE.md`: فاتورة عميل أصلية من طلب بيع معتمد، اعتماد منفصل، دفعات جزئية/كاملة مع قيد بنكي أصلي، marker إضافي لمنع التكرار والاستعادة الآمنة، والعملات الأصلية وVAT الموروث من الطلب، واختبارات صناعية/disposable فقط. لا دفعة مستقلة أو overpayment أو FX تلقائي أو بيانات حقيقية أو Push أو Staging أو Production، وبوابة العميل تبقى للقراءة فقط.

Approval recorded from Ahmed on 2026-09-14. C02–C04 and R01 remain on the delivery plan and are not authorized by this approval.

## C01 local implementation evidence — 2026-09-14

- Added the staff-first invoice draft, separate validation, partial/full receipt, paid/remaining, and persisted recovery controls to the operation screen.
- Native writers are limited to `Facture::createFromOrder()`, `Facture::validate()`, `Paiement::create()`, and `Paiement::addPaymentToBank()`; the workflow never calls `setPaid()` and has no send action.
- Added the entity-scoped `cpo_billing_action` marker with unique invoice/request/payment guards and `preparing`, `native_created`, `bank_posted`, `confirmed`, and `correction_required` states.
- The additive local schema applied and replayed successfully. The marker table remained empty; no local invoice, payment, bank entry, email, external call, or business record was created.
- Source-contract tests passed. A separate disposable MariaDB database passed schema replay, invoice/request/payment duplicate guards, 40% + 60% marker persistence, original CNY retention, and durable correction state, then was deleted.
- The existing M077 disposable persistence suite passed its native-payment, bank-evidence, rollback, resume, deletion, concurrency, and managed-funds guards.
- Existing M076/M079 workflow source tests and authenticated customer ownership integration passed.
- A proposed full native USD/CNY/EGP transaction test against the current local business database was rejected before file creation or execution because native numbering/PDF side effects are not guaranteed to roll back. Full native C01 acceptance therefore remains pending a fully isolated Dolibarr database/runtime clone; it must not be run against the working local database.

### Parity

- Admin ERP / China Planet OS operation screen: implemented locally.
- Customer Center: read-only existing invoice/payment projection retained; no customer write action added.
- Mobile App: not applicable to C01 and unchanged.

### Rollback

- Source rollback: revert the C01 commit after it is created.
- Local schema rollback: `rollback-empty` is permitted only while `llx_cpo_billing_action` contains zero rows. Once markers exist, retain the table and correct through a reviewed migration.

## M082 C02–C04 completion — 2026-09-14

- Added the native supplier order, invoice, partial/full payment, same-currency bank, optional managed-funds attribution, and durable recovery path.
- Added evidence-only shipment and reviewed-document follow-up to the operation screen.
- Completed schema-only disposable native UAT in USD, CNY, and EGP from quotation through customer collection and supplier settlement; the disposable database was deleted and the working business database was not mutated by UAT.
- R01 remains unapproved. No Push, Staging, or Production action occurred.
