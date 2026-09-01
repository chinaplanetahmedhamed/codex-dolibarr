# M061 — Customer Center financial clarity, unified documents, and section emails

## Mission control

- **Mission ID:** M061
- **Type:** FEATURE / CUSTOMER PORTAL / ADMIN WORKFLOW / MOBILE PARITY
- **Status:** M061-C01 LOCAL_QA_PASS — AWAITING_AHMED ACCEPTANCE; MOBILE DEVICE PRESENTATION CHECK PENDING
- **Architect owner:** Agent 007 Central
- **Implementation owner:** Agent 007 Central
- **Required reviewers:** Accounting, Customer Portal, Security, QA, UI/UX, Shipping
- **Prepared:** 2026-08-24
- **Approved by:** Ahmed Hamed on 2026-08-24 for local implementation only
- **Evidence:** Read-only authenticated production review using the customer account nominated by Ahmed; no customer identifiers, credentials, private notes, document contents, or exact financial values are copied into this Mission.
- **Target for implementation:** Clean local worktree from the verified current `origin/main` only
- **Staging:** Not authorized by this Mission approval; requires a separate exact `backup-erp` approval after local acceptance
- **Production:** Forbidden unless Ahmed separately approves the exact staging-accepted release
- **Outbound communication during development/testing:** No real customer email or WhatsApp message

## Business objective

Make the Customer Center explain the customer's financial position in the normal business order — total invoiced, paid/funded, then outstanding — while preserving the existing wallet/funding view; make invoice details easy to discover; show only explicitly customer-visible payment comments; unify approved customer documents with project/shipment context; and let authorized staff preview and send customer-safe section summaries from Dolibarr with durable Agenda history.

## Verified current state

The following facts were verified read-only on the live Customer Center on 2026-08-24:

1. The financial statement leads with wallet balance and required-funding cards. It shows the unpaid-invoice amount but does not lead with gross invoice total, paid/funded total, and outstanding total.
2. The invoice list already calculates gross total, funded amount, and remaining amount per currency.
3. The invoice reference is already a functional link to an invoice-detail page. The detail page already shows service lines, quantities, unit prices, totals, applied credit, and linked payments. The remaining defect is discoverability, completeness of commercial totals, and consistent navigation rather than absence of a detail route.
4. Payment rows show date, type, invoice/payment reference, amount, allocation, and running balance. They do not project a customer-visible payment comment.
5. The external Documents page is shipment-centered. It does not present the approved commercial/export documents or a unified set of explicitly published customer/project/operations documents.
6. The project command center already shows customer-safe project status and its invoice totals. Project document visibility remains incomplete, and records without a verified project relation must not be assigned to a project by inference.
7. M056 already provides a restricted customer-safe shipment-status email path. M055 already provides a server-owned recipient/sender, preview/composer, Dolibarr mail, and Agenda-history pattern that can be reused.

## Repository precondition

The preserved canonical checkout inspected while preparing this Mission is not an implementation worktree: it contains unrelated user changes and its checked-out branch is behind the locally known `origin/main`. Do not switch, reset, clean, merge, or edit it.

Before implementation:

1. Refresh the remote reference read-only when authorized.
2. Verify the current default-branch SHA and next available Mission number.
3. Create a clean `codex/m061-customer-center-financial-documents-email` worktree from the verified current `origin/main`.
4. Re-read the root governance, Customer Center, accounting, document, email, and mobile-parity contracts.
5. Re-run source and local-runtime discovery before freezing migrations or file allowlists.

## Proposed functional scope

### 1. Financial statement hierarchy

Add a primary invoice summary for every original invoice currency:

- **Total invoiced / إجمالي الفواتير:** gross total of validated customer invoices only.
- **Paid or funded / المدفوع أو الممول:** direct validated payments plus correctly applied customer credit.
- **Outstanding / المتبقي:** `max(0, total invoiced - paid/funded)` using the existing normalized invoice calculation.

Rules:

- Never add different currencies together without an explicit reporting conversion.
- Never add a customer order and its generated invoice together.
- Validated but unbilled order/service commitments, when shown, use a separate card named **Approved unbilled commitments** and never alter invoice totals.
- Draft invoices remain hidden from external customers.
- The current wallet balance, supplier commitments, approved future services, unpaid invoices, and additional-funding calculation remain available as a separate secondary section.
- The financial statement and invoice list must use one shared calculation service so totals cannot diverge.

### 2. Invoice discoverability and complete safe detail

- Preserve the existing scoped `invoice.php` route.
- Make each invoice row/card visibly actionable with a bilingual **View details / عرض التفاصيل** control.
- The invoice reference and action must be keyboard accessible; mobile uses cards or a bounded horizontal table without page-wide overflow.
- Show customer-safe invoice metadata: reference, project, linked customer order when verified, issue/due dates, status, currency, subtotal excluding tax, discount when applicable, tax, gross total, direct payments, applied credit, funded total, and remaining amount.
- Show every non-credit invoice line with customer description, quantity, unit, unit price, tax rate when applicable, and line total.
- Render line breaks as line breaks; do not expose stored escape sequences as visible `\\n` text.
- Show linked payments with date, safe payment reference, amount, currency, and the explicit public payment comment from Scope 3.
- Financial-statement invoice references should link to the same scoped detail route.
- Never show supplier identity, supplier price/cost, margin, internal description, internal note, bank-private detail, draft invoice, or another customer's invoice.

### 3. Explicit customer-visible payment comments

Use Dolibarr's standard payment extra-field extension point first:

- Add one module-managed payment extra field named `cpcc_public_note` (multiline text) only through an idempotent absent-or-exact migration.
- Label it clearly for staff as **Customer-visible payment comment / تعليق الدفعة الظاهر للعميل**.
- Keep Dolibarr payment `note_private`/legacy `note` private. It must never be used as a fallback and must never enter the portal/mobile/email DTO.
- Staff can intentionally add or edit the public comment through the authorized payment/admin workflow.
- Existing private notes are not migrated or published automatically. Any historical publication requires a staff member to review and copy safe text into the public field in a separately authorized data operation.
- The public comment appears on matching receipt/allocation rows, invoice-linked payments, section email/PDF output, and the mobile customer contract.
- Empty public comments render no placeholder and do not change accounting.

### 4. Unified, explicitly published Customer Documents Center

Create one customer-safe document read model and one normalized card/list UI. Include only records that pass entity, authenticated `fk_soc`, current-version, approval, and publication checks.

Approved sources:

1. Customer-visible `cpcc_export_document` commercial/export documents with valid customer and optional project/shipment links.
2. Current `cpo_document` records whose visibility is `customer_after_approval` and whose review status is `approved` or `final_received`, joined through a customer-owned operation.
3. Existing shipment documents, customer uploads, and gallery files already published through the shipment customer-visibility contract.
4. Core Dolibarr third-party/project documents only after an authorized staff member explicitly publishes the exact existing file through a new Customer Center publication registry. Merely existing under a third-party, project, invoice, order, ECM, or filesystem directory never makes a file customer-visible.

Publication registry requirements:

- Additive table with `entity`, `fk_soc`, optional `fk_project`, optional `fk_shipment`, source object type/id, normalized relative document token/path, customer-safe title/type, publication status, creator/reviewer, timestamps, and indexes.
- Do not store an unrestricted absolute path or client-supplied download URL.
- Publishing and unpublishing require an internal permission, CSRF, verified source-object ownership, and a review step.
- Download re-resolves the source server-side, requires the same entity/customer/publication checks, verifies the real path remains inside its exact allowed document root, and returns safe headers.

Customer UI requirements:

- Filters: All documents, project, shipment, document type, and date.
- Every document shows its verified related project and shipment, or **Not linked / غير مرتبط**. Never infer a relationship from a filename, date, or customer alone.
- Project detail shows the same approved documents filtered by that project.
- Empty states distinguish no published documents from no results for the current filter.
- Preserve Arabic/English and RTL/LTR behavior.

### 5. Staff-only section email workflow

Add a reusable Customer Center section-email service and bilingual preview/composer for authorized internal staff. External customer users remain read-only and receive no send button.

Phase-one send locations:

1. Financial statement — detailed statement for selected currency/date range/as-of date, including invoice summary, transaction rows, and public payment comments.
2. Invoice detail — customer-safe invoice summary with the current validated invoice PDF when a safe generated PDF exists.
3. Project detail — customer-safe project status, next step, related invoice summary, shipment summary, and approved document links.
4. Shipment detail — reuse/extend the M056 recipient, sender, permission, customer-safe projection, retry, and Agenda patterns; do not create a second conflicting shipment-status sender.

Email controls:

- Require internal Customer Center/email rights plus section-specific read rights and CSRF.
- Resolve the sender from validated Dolibarr mail configuration.
- The browser may post only a contact ID; the server verifies the contact is active, has a valid email, belongs to the same entity/customer, and is eligible for the selected section. No arbitrary posted recipient address.
- Preview is required before send and has no side effect.
- Subject/body are prefilled from the same allowlisted DTO used by the portal; staff may edit only bounded customer-safe text.
- Financial statement output is generated from one frozen server-side snapshot. If a PDF is attached, the HTML body and PDF totals must share the same snapshot identifier.
- Attach only the validated invoice PDF or newly generated statement PDF. Do not attach arbitrary project/shipment/customer files in this Mission.
- Successful sends create an Agenda event linked to the customer and the relevant invoice/project/shipment where Dolibarr supports it.
- SMTP or Agenda failure never changes an invoice, payment, project, shipment, document, or financial calculation. Show a bounded warning and preserve audit evidence without raw transport errors.
- Explicit retries are separate POST actions. No automatic, scheduled, bulk, WhatsApp, or background sends.

## Admin, portal, and mobile parity

### Admin ERP

- Payment entry/edit exposes the explicit public payment comment and preserves the private note.
- Documents admin can publish/unpublish eligible linked files with visible customer/project/shipment scope.
- Customer financial, invoice, project, and shipment sections expose preview/send only to authorized staff.

### Customer Portal

- Receives the new financial hierarchy, clear invoice-detail action, public payment comments, unified documents/filters, and project-context labels.
- Remains read-only for financial, invoice, document-publication, and email actions.

### Mobile App/API

- Add the same invoice summary fields per currency.
- Add a scoped invoice-detail endpoint/screen with lines, totals, linked payments, and public payment comments.
- Extend transaction DTOs only with `public_note`; never add private payment notes.
- Unify document list/detail DTOs with verified project/shipment identifiers and filters, using opaque public IDs and authenticated downloads.
- Do not add staff email actions to the customer app.
- Update `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md` with exact API/UI evidence.

## Permissions and data protection

- Derive every external customer scope from the authenticated user (`user->socid`/server context), never a posted/query customer selector.
- Enforce `entity` and `fk_soc` on invoice, payment, project, shipment, operation, document metadata, file download, PDF, preview, recipient, send, and Agenda history.
- Customer A must receive 404/denial for Customer B's invoice, payment note, document, project, shipment, PDF, email preview, or download token.
- Use standard Dolibarr rights, CSRF, validation, escaping, language files, and mail/Agenda services.
- No supplier identity/cost, margin/profit, internal note, bank-private data, staff-only comment/file, credentials, raw SQL error, absolute path, or internal ID enters customer HTML, API, email, PDF, logs, or documentation.

## Database, accounting, and configuration impact

- **Accounting:** Presentation/read-model change only. No posting, reconciliation, invoice/payment mutation, exchange-rate change, or ledger rewrite.
- **Schema:** One absent-or-exact payment extra field and one additive customer-document publication table are proposed. Freeze their exact definitions after local schema discovery and reviewer approval.
- **Configuration:** Reuse existing Dolibarr SMTP and sender settings. No mailbox, collector, DNS, API key, or external-email-service configuration change.
- **Business data:** Local and staging tests use synthetic customers/records only. No production backfill or public-note/document publication is authorized by implementation approval.

## Proposed affected areas

- `custom/chinaplanetcustomercenter/balance.php`
- `custom/chinaplanetcustomercenter/invoices.php`
- `custom/chinaplanetcustomercenter/invoice.php`
- `custom/chinaplanetcustomercenter/documents.php`
- `custom/chinaplanetcustomercenter/project.php` and/or the current CP Kanban customer project projection owner
- `custom/chinaplanetcustomercenter/lib/financial.lib.php`
- New customer-document read/publication library and new section-email service
- Customer Center module descriptor, SQL, permissions, language, CSS and focused JavaScript
- Existing M056 shipment email integration points only where required for a non-conflicting summary action
- `custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php` and route contract
- Mobile app API types, repositories, finance/invoice/document/project screens and tests
- Focused deploy tests, changelog, parity record, Mission completion report, and release manifest later

No Dolibarr core file may be modified.

## Acceptance criteria

### Financial correctness

- For each currency, displayed `total invoiced = funded + outstanding` within the existing rounding tolerance.
- Direct payments plus applied credits are counted once; receipt/allocation ledger pairs do not double-count funded invoice value.
- Order and invoice values are never double-counted.
- Wallet/funding values remain unchanged by the new invoice-summary presentation.
- Different currencies are never silently combined.

### Invoice and payment UX

- A customer can discover and open an owned validated invoice from the list and financial statement using mouse, touch, and keyboard.
- Invoice lines and subtotal/tax/discount/gross/funded/outstanding reconcile to the underlying validated invoice.
- Only explicit `cpcc_public_note` text appears; private payment notes are absent from HTML, API, email, PDF, logs, and negative tests.

### Documents

- All four approved document sources appear only when their exact publication/approval requirements pass.
- Project/type/shipment/date filtering returns the correct subset and shows verified relationship labels.
- Draft, superseded, internal, forwarder-only, rejected, unreviewed, unregistered core files, and foreign-customer files never appear.
- Direct URL, public-ID, source-ID, filename, encoded path, symlink, and download-token tampering fail closed.

### Email

- Authorized staff can preview and explicitly send each phase-one section email.
- External customers and unauthorized staff see no send action and cannot call the POST endpoint.
- Posted recipient/customer/object tampering fails; sender and eligible recipient are server-owned.
- Preview sends nothing. One confirmed POST sends one email and records one appropriate Agenda event.
- SMTP/Agenda failure does not roll back or modify business records and exposes no raw error.
- No automated or real-customer send occurs in local/staging acceptance.

### Isolation, compatibility, and presentation

- Customer A/B isolation passes for every new HTML/API/download/preview/send path.
- Arabic and English, RTL and LTR, empty/loading/error/success states pass.
- Admin and portal pass desktop plus real `390x844` and `360x844` browser verification without page-wide overflow; table/card behavior remains readable.
- Mobile app unit/integration tests and a real phone/simulator presentation check pass.
- Existing M029 finance, M031 documents, M032 responsive, M055 email, M056 shipment-email, customer-scope, mobile download, and current regression suites remain green.

## Required test plan

1. PHP lint for every changed PHP file and `git diff --check`.
2. Focused pure financial calculation tests for totals, credits, currencies, and no double-counting.
3. Local MySQL/MariaDB migration readiness and idempotency tests for the payment extra field and publication registry.
4. Authenticated customer-scope integration tests with two synthetic customers and foreign-object tampering.
5. Document publication/source/status/version/path/download negative matrix.
6. Email source/permission/CSRF/recipient/body/Agenda/failure/retry tests using a fake or captured local transport only.
7. Authenticated browser acceptance for staff and customer roles at desktop, 390px, and 360px in Arabic and English.
8. Mobile API contract, repository, authenticated-download, screen, RTL/LTR, and cache tests.
9. Required existing Customer Center, Accounting, Document, M055, M056, and mobile regression suites.

## Out of scope

- Changing invoice/payment accounting, posting transactions, reconciliation, currency valuation, or supplier accounting.
- Automatically publishing existing private payment notes or existing filesystem/ECM documents.
- Email campaigns, bulk statements, scheduled statements, queues, inbound mail collection, WhatsApp/SMS, or customer self-send.
- Editing SMTP/mailbox/DNS configuration.
- Sending arbitrary attachments or unauthenticated/public document links.
- Redesigning the full Customer Center, project lifecycle, shipment lifecycle, or CP Kanban.
- React/Node/WordPress changes.
- Staging or production deployment, production data backfill, or real-customer email acceptance.

## Risks and controls

- **Accounting confusion:** one normalized invoice calculation and explicit separation of invoice totals from wallet/funding and unbilled commitments.
- **Private-note exposure:** dedicated public field with no private fallback and negative projection tests.
- **Document leakage:** explicit publication registry, approved/current allowlists, ownership joins, safe roots, and authenticated download checks.
- **Wrong recipient/content:** server-owned sender/customer/contact, section DTO allowlists, preview, rights, CSRF, and Agenda history.
- **Duplicate/conflicting shipment emails:** reuse M056 services and events rather than creating a second status sender.
- **Scope expansion:** phase-one email sections and four document sources are frozen; anything else returns for Architect/Ahmed approval.

## Rollback concept

- Restore only the M061 application files to the accepted pre-M061 commit and disable M061 routes/actions.
- Keep additive payment-extra-field/publication-table data inert; do not delete customer or Agenda history during rollback.
- Existing invoice/payment/project/shipment/document data remains authoritative and unchanged.
- Any staging release later requires checksum-pinned files, full database and code backups, readiness verification, fail-closed apply/verify, and an exercised rollback before production can be considered.

## Approval gate

Ahmed approval must explicitly cover:

1. The financial definitions and separation from wallet/funding/unbilled commitments.
2. The new explicit customer-visible payment field with no automatic historical-note publication.
3. The additive document-publication registry and explicit-publication rule.
4. Staff-only preview/send for financial statement, invoice, project, and shipment, with no customer self-send or automated/bulk email.
5. Related Customer Portal and Mobile parity work.
6. Local implementation only; staging and production remain separate approvals.

After approval, status becomes `APPROVED`, then `IN_PROGRESS` only in a verified clean worktree.
