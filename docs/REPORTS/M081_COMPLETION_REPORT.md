# M081 Completion Report — Customer portal clarity and traceability

- Date: 2026-09-14
- Environment: local Dolibarr 23.0.3 and synthetic UAT data
- Outcome: COMPLETED LOCALLY
- Release state: not pushed or deployed

## Delivered outcome

The Customer Center now gives Sarah Company a customer-safe, read-only commercial view backed by native Dolibarr records. Financial totals are grouped by original currency, quotations are visible without customer actions, published commercial documents appear from the main Documents page, operation reports use readable responsive cards, and project/order pages show only verified native commercial links.

Authenticated local acceptance confirmed:

- 5 visible non-draft Sarah quotations, including `PR2609-0019`;
- USD, CNY, and EGP invoice totals displayed separately with no implicit FX;
- 4 published commercial documents on the main Documents page;
- 20 readable operation-report cards;
- native project/order links to quotations, invoices, and customer-visible shipments;
- missing links shown as pending rather than invented.

## Security and customer visibility

- Customer identity is derived from the authenticated external user's `socid` and exact entity.
- A forged `socid` query does not broaden access.
- Foreign-customer and draft quotations are denied.
- The new quotation screens are read-only and expose no accept, reject, sign, send, pay, edit, upload, or POST workflow.
- Supplier identity, purchase cost, margin, private notes, bank details, staff tasks, and private files remain excluded.

## Data and configuration impact

- No native business record was created, edited, renamed, validated, paid, or deleted.
- No database schema, module state, permission, configuration, API, or mobile-app behavior changed.
- No email, external request, Git Push, Staging, or Production action occurred.

## Verification

- PHP syntax checks passed for all changed PHP files.
- M081 focused source/security contract passed.
- Customer documents, operation reports, multicurrency finance, shipment/customer flow, workspace, operations authorization, staff dashboard, commercial workflow, and M080 billing regressions passed.
- Authenticated customer portal scope integration passed against the local database.
- Desktop and 390px authenticated browser acceptance passed with no page-wide overflow.
- `git diff --check` passed.

Independent verdicts:

- Accounting: PASS.
- Customer Portal / Security / QA: PASS WITH CONDITIONS.

The condition is non-blocking: some synthetic records retain technical UAT names such as `UAT-SARAH-STAFF-*`, which a customer can see because those are the native record labels. Renaming business data was outside M081 and was not performed.

## Remaining separate work

- Mobile quotation and project/order journey parity remains pending a separately approved Mission.
- The closed-project/new-operation synthetic fixture conflict remains a data-quality item; M081 did not rewrite project or operation state.
- Customer-facing cleanup of synthetic UAT record labels, if desired, requires separately authorized data changes.

## Rollback

Revert the single local M081 source commit. No database or configuration rollback is required because this Mission made no runtime data or configuration changes.
