# M083 — Staff-first Production release

- Owner: Ahmed Hamed
- Coordinator and sole writer: Agent 007
- Runtime commit: `ecf151802ca8396c51214d758c6150cf1db49a9d`
- Target: `https://erp.visiontrading.biz` Production only
- Status: PREPARING IMMUTABLE RELEASE — NO PRODUCTION ACTION AUTHORIZED

## Business outcome

Publish the staff-first China Planet OS screens so Ahmed can start entering real orders through the simplified Operations workspace while Dolibarr remains the native source of truth.

The release carries the completed M076–M082 local work:

- manager and staff workspace with readable navigation and responsive Arabic/English UI;
- customer creation and customer-linked operations;
- quotation → acceptance → validated customer order;
- customer invoice → partial/full receipt → native bank evidence;
- supplier order → supplier invoice → partial/full supplier payment → native bank evidence;
- explicit same-currency customer-funds attribution without creating extra cash or FX;
- linked shipment tracking and evidence-based document/milestone follow-up;
- read-only customer portal for quotations, orders, invoices, payments, shipments, documents, and per-currency reports.

## Financial and security controls

- Native Dolibarr commercial, payment, bank, shipment, project, and document records remain authoritative.
- Original currency and inherited VAT are preserved; there is no automatic FX or overpayment.
- Supplier payments are serialized per supplier invoice and the remaining balance is reread under lock.
- Retry/recovery validates exact payment, invoice, bank amount/sign/date/main-currency amount, and `payment_supplier` link before reuse.
- Customer-funds attribution locks and reconciles the original receipt, native deposit-credit consumption, and total active coverage.
- Customer access derives from the authenticated external user and stays read-only. Supplier identity, cost, margin, bank details, and internal notes remain staff-only.
- No email, CargoX, DHL, public API write, or external send is introduced.

## Exact release scope

- 57 runtime files listed in `deploy/releases/m083-runtime-files.txt`.
- Six additive marker/ledger tables and four exact managed-funds database triggers.
- No Dolibarr core file, document volume, Compose definition, container image, proxy, DNS, firewall, or public React website change.
- No automatic permission grant. Existing Admin remains usable; staff permissions must be granted separately and deliberately.

## Production gates

1. Freeze and independently review the immutable artifact, helper, installer, sudoers, schema migration, baseline, and rollback behavior.
2. Separately approve Git Push/upload and remote checksum verification.
3. Separately approve checksum-locked root helper/sudoers installation.
4. Separately approve restricted Production `compare` and `preflight`; both are read-only and must prove the exact M075 baseline and absent M083 schema.
5. Separately approve `apply`, conditional `verify`, `status`, and authenticated staff/customer acceptance.

`apply` must create a complete code backup, absent-file manifest, and full database dump before any mutation. A failed apply restores code and drops only empty newly created schema. It never automatically restores a full Production database. Rollback is permitted only while all six M083 tables remain empty; after real business use, recovery requires a new data-preserving plan.

## Acceptance

- Exact runtime hashes and schema/trigger definitions match the frozen release.
- Production login and the three custom route boundaries remain healthy.
- Authenticated Admin can complete one clearly labelled disposable acceptance cycle without email or external send, then those disposable records are retained or removed only by a separately approved business-data action.
- Authenticated external customer can see only their own read-only information.
- Production backup paths and hashes, release state, and rollback limitations are reported.

## Current boundary

No Push, upload, root installation, Production compare/preflight/apply/verify, permission change, or real business record is authorized by this preparation document.
