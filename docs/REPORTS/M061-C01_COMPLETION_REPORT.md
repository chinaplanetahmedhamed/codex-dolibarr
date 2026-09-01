# M061-C01 — Customer section email presentation correction — Completion report

## Control

- Parent: M061
- Approval: Ahmed Hamed — “ok do it”, 2026-08-24
- Branch: `codex/m061-customer-center-financial-documents-email`
- Baseline: `d278e529ec257774c6f3594712169937decde1fd`
- Environment: local Dolibarr 23.0.3 only
- Result: `LOCAL_QA_PASS — AWAITING_AHMED_ACCEPTANCE`
- Outbound messages: none
- Staging/production: not accessed or changed

## Business outcome

The Customer Center now previews and sends a structured China Planet HTML message instead of a long plain-text wall. Financial statements lead with the exact M061 per-currency invoice totals, show validated invoice rows, limit the email to the latest 12 transactions, explain the truncation, and link to the full authenticated statement.

The same bounded HTML presentation is used for invoice, project, and shipment section summaries. A plain-text representation remains available for Agenda history and mail-client fallback/audit use.

## Changed files

- `custom/chinaplanetcustomercenter/class/CustomerSectionEmailService.class.php`
- `custom/chinaplanetcustomercenter/section_email.php`
- `custom/chinaplanetcustomercenter/css/customer_dashboard.css`
- `deploy/tests/test_m061_customer_section_email_service.php`
- `deploy/tests/test_m061_customer_section_email_source.php`
- `docs/MISSIONS/M061-C01_SECTION_EMAIL_PRESENTATION_CORRECTION.md`
- `docs/MISSIONS/M061_CUSTOMER_CENTER_FINANCIAL_DOCUMENTS_AND_SECTION_EMAILS.md`
- `docs/REPORTS/M061_COMPLETION_REPORT.md`
- `docs/REPORTS/M061-C01_COMPLETION_REPORT.md`
- `docs/CHANGELOG.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`

## Data, configuration, permissions, and visibility

- Database/schema/business records: unchanged.
- Accounting calculations and currency handling: unchanged.
- SMTP/mailbox/sender/recipient configuration: unchanged.
- Permissions, CSRF, entity/customer scope, and staff-only send boundary: unchanged.
- Customer-safe projection: unchanged except presentation and the approved latest-12 email bound.
- Agenda: a real send would store the plain-text representation; no local send or Agenda row was created.
- Mobile: `NOT_APPLICABLE`; no API, DTO, repository, route, or mobile screen changed.

## Verification

- PHP lint for changed PHP and focused test files: `PASS`.
- `git diff --check`: `PASS`.
- M061 financial, documents, section-email source, captured-mail, permission, recipient, HTML-mode, exact-preview, Agenda-text, SMTP-failure, and isolation contracts: `PASS`.
- M029, M020-C02, M031, M032, M055, and M056 focused regressions: `PASS`.
- Local runtime/schema/isolation rollback test: `PASS`; temporary public/private payment-note markers were rolled back and original values restored.
- Authenticated staff browser: financial HTML preview contains no visible `\\n`; per-currency totals, invoices, latest 12 of 127 transactions, full-statement link, recipient, subject, and confirm button rendered.
- Responsive browser: desktop, 390px, and 360px had `pageScrollWidth = pageClientWidth`; the preview frame also had no overflow. Console errors: zero.
- Send button: deliberately not submitted.

## Backup and rollback

The three pre-correction runtime files were copied to `/private/tmp/m061-c01-runtime-backup.I97FBq` before activation. Their SHA-256 values are:

- `CustomerSectionEmailService.class.php`: `7308b8a859c8aa35c0c6c7951267a921923cd3485ea309bb928290bcb0c1e0ab`
- `section_email.php`: `593b480bdee0b715e7c17bc048d95be0328efb6c10a2814ab44d563b1c2355b5`
- `customer_dashboard.css`: `53d816108a48afe3dc415328975ef9c29380e11c2464072edaa482bbeeca940b`

Rollback restores only those three files. No database rollback is required.

## Review verdicts

- Customer Portal: `PASS` — recipient/customer scope and external no-send boundary unchanged.
- Security: `PASS` — dynamic HTML values escaped; no private note, supplier, cost, margin, arbitrary attachment, or posted recipient added.
- Accounting: `PASS` — existing M061 normalized totals reused without calculation or posting changes.
- UI/UX: `PASS` — branded hierarchy, bounded transaction list, responsive preview, and exact-send notice verified.
- QA: `PASS` — source, captured-service, regression, runtime, responsive, and console checks passed.
- Architect: `PASS_WITH_AHMED_ACCEPTANCE_PENDING`.

Production was not accessed or changed.
