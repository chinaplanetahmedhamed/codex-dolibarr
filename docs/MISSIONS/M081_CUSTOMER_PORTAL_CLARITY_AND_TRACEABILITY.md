# M081 — Customer portal clarity and traceability

- Owner: Ahmed Hamed
- Coordinator and writer: Agent 007
- Date: 2026-09-14
- Status: COMPLETED LOCALLY
- Environment: local source and synthetic UAT only

## Objective

Correct the customer-visible gaps found while Sarah Company reviewed the local Customer Center, without changing native business records or allowing customer writes.

## Approved scope

1. Replace the mixed-currency financial report with per-currency totals using the existing Customer Center financial projection.
2. Add read-only customer quotation list/detail pages for validated or later native Dolibarr proposals, scoped by authenticated customer and entity.
3. Show quotation count and navigation from the customer dashboard and portal navigation.
4. Make published operation-report cards readable on desktop and phone.
5. Show existing published commercial documents on the main customer Documents page.
6. Add customer-safe traceability between project, quotation, order, invoice, shipment, documents, and reports where native links exist.
7. Add focused source and authenticated positive/negative access tests.

## Invariants

- Dolibarr native proposals, orders, invoices, projects, and links remain authoritative.
- External users are scoped from the authenticated `user->socid`; URL `socid` never broadens access.
- Draft proposals are hidden from customers.
- The portal is read-only: no accept, reject, sign, send, pay, edit, or upload action is added.
- Supplier identity, purchase cost, margin, private notes, bank details, staff tasks, and private documents remain hidden.
- Currency values are never added across different currencies and are displayed in original currency.
- Missing links or shipment facts are displayed as pending, not invented.

## Files

New:

- `custom/chinaplanetcustomercenter/quotations.php`
- `custom/chinaplanetcustomercenter/quotation.php`
- `custom/chinaplanetcustomercenter/lib/commercial_trace.lib.php`
- `custom/chinaplanetcustomercenter/css/quotations.css`
- `deploy/tests/test_m081_customer_portal_clarity.php`
- `docs/REPORTS/M081_COMPLETION_REPORT.md`

Modified as needed:

- `custom/chinaplanetlogistics/reports.php`
- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/documents.php`
- `custom/chinaplanetcustomercenter/operation_reports.php`
- `custom/chinaplanetcustomercenter/order.php`
- `custom/chinaplanetcustomercenter/project.php`
- `custom/chinaplanetcustomercenter/lib/portal.lib.php`
- relevant Customer Center CSS only

## Acceptance

- Sarah sees each currency in a separate financial block and each invoice in its original currency.
- Sarah sees her non-draft proposals, including `PR2609-0019`, and cannot open another customer's proposal or a draft.
- Published commercial documents appear from the main Documents button.
- Reports render as separated readable cards at desktop and 390px.
- Project/order/quotation pages show only verified native links and safe pending states.
- Authenticated Sarah positive checks and cross-customer direct-route negative checks pass after fresh reload.
- PHP syntax, focused source tests, affected regressions, and `git diff --check` pass.

## Hard boundaries

- No business-data correction, invoice/payment posting, customer action, email, CargoX, DHL, external call, Push, Staging, or Production.
- No Dolibarr core edit, schema change, module-state change, or permission expansion.
- The conflicting closed-project/new-operation fixture is reported separately; this Mission does not rewrite project or operation state.

## Approval

Ahmed approved implementation in this task on 2026-09-14 with: `ok do it`.

## Completion evidence

- Customer Portal runtime acceptance: PASS on authenticated desktop and 390px views.
- Security isolation: PASS for authenticated-customer ownership, forged `socid`, and foreign quotation direct access.
- Accounting review: PASS; USD, CNY, and EGP remain separate and no automatic FX or mixed total is shown.
- Customer/Security/QA review: PASS WITH CONDITIONS; the only non-blocking condition is that synthetic UAT record names remain visible as their native names.
- Sarah Company sees 5 non-draft quotations, 4 published commercial documents, 20 readable operation-report cards, and native project/order commercial links without fabricated relationships.
- PHP syntax, focused M081 checks, affected regressions, authenticated DB integration, and `git diff --check` passed.
- No business data, schema, module state, permissions, Push, Staging, or Production changed.
