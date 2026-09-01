# M061-C01 — Customer section email presentation correction

## Control

- **Parent:** M061 — Customer Center financial clarity, unified documents, and section emails
- **Type:** NARROW LOCAL CORRECTION
- **Status:** LOCAL_QA_PASS — AWAITING_AHMED_ACCEPTANCE
- **Owner:** Agent 007 Central
- **Approved by:** Ahmed Hamed on 2026-08-24 — “ok do it” after rejecting the financial email preview
- **Environment:** Local runtime only
- **Outbound communication:** No real customer email or WhatsApp message
- **Staging / production:** Not authorized and must not be accessed

## Verified defect

The M061 staff preview renders the plain-text message through `dol_escape_htmltag()` with its default newline behavior. Dolibarr therefore displays actual line breaks as visible `\\n` text. The same financial message is also too long and visually unstructured for a customer-facing statement.

## Approved correction scope

1. Keep every existing recipient, sender, permission, CSRF, customer/entity scope, calculation, Agenda, and failure boundary unchanged.
2. Generate a customer-safe HTML email plus a plain-text audit/fallback representation from the same allowlisted section data.
3. Use one consistent China Planet presentation for financial statement, invoice, project, and shipment summaries.
4. Financial email must lead with per-currency `Total invoiced → Paid or funded → Outstanding`, then an invoice table, then only the latest 12 transactions and a link to the complete portal statement.
5. Preview the exact HTML body that the mail transport will receive; do not show escaped newline tokens.
6. Keep all dynamic customer and business values HTML-escaped and keep private notes, supplier details, costs, margins, bank-private data, and arbitrary files out.
7. Build customer links from the configured application root when available, with a validated current-request fallback for local preview.
8. Verify through focused source/service tests and authenticated local browser acceptance. Do not submit the real send form.

## Out of scope

- Financial/accounting calculation changes, date/currency filtering, PDF generation, new attachments, schema, permissions, recipient editing, SMTP configuration, bulk/scheduled messages, mobile UI, staging, production, or real-customer delivery.

## Acceptance

- Preview contains formatted HTML with no visible `\\n` tokens.
- Captured fake send receives HTML mode and the same body shown in preview.
- Financial summary values and invoice rows remain sourced from the existing M061 calculation service.
- The email states when older transactions are omitted and links to the complete portal statement.
- Agenda stores the bounded plain-text representation, not raw HTML.
- Existing scope/isolation/send-failure tests remain green.
- Desktop, 390px, and 360px staff preview has no page-wide overflow.

## Rollback

Restore the M061-C01 source files to commit `d278e529ec257774c6f3594712169937decde1fd`. No database or business-data rollback is required.

## Local result — 2026-08-24

- The exact HTML shown in preview is the body passed to Dolibarr mail in explicit HTML mode.
- Financial preview shows the two original currencies independently, every validated invoice, and the latest 12 of 127 local transactions with a full-statement link.
- The staff preview passed desktop, 390px, and 360px checks with no page-wide overflow, no visible `\\n`, and no browser console error.
- Customer Portal, Security, UI/UX, Accounting-boundary, and QA reviews: `PASS` for this frozen correction scope.
- No send form was submitted; no email, Agenda row, database record, staging resource, or production resource was changed.
