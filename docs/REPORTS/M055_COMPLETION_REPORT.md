# M055 implementation report

## Verdict

`STAGING_ACCEPTED_BY_AHMED / PRODUCTION_DEPLOYMENT_APPROVED / PRODUCTION_PREFLIGHT_IN_PROGRESS`.

The Website Requests detail screen now has an in-Dolibarr email reply composer and a WhatsApp action. The customer email and phone remain server-owned request data; the browser cannot post a replacement recipient, WhatsApp never auto-sends, and no credential is present in source.

## Delivered behavior

- `Reply by email` opens a responsive composer inside the selected request and shows the configured sender plus fixed request recipient.
- Sending requires `publicrequest_update` and `email_write` in addition to the existing authenticated staff and CSRF boundaries.
- Dolibarr sends through `CMailFile`, validates subject/body limits, and logs the outgoing message as an `AC_EMAIL` Agenda event.
- If the request has a linked proposal, the standard `pro<ID>` tracker and proposal event link are used, allowing Email Collector to associate a later reply with the quotation.
- Before quotation conversion, the outgoing event remains linked by the durable website-request element/reference; normal Email Collector processing still records incoming mail in Dolibarr.
- `Open WhatsApp` builds a `wa.me` URL only for an 8–15 digit international number, normalizes common China/Arab-market local prefixes from the stored country code, opens a protected new tab, and prepares a locale-aware draft containing the request reference.
- Missing or invalid email/phone values show an unavailable state instead of an unsafe link.

## Security and data handling

- Recipient is reread from the entity-scoped durable request during POST; there is no posted recipient field.
- Sender comes from `MAIN_MAIL_EMAIL_FROM`; SMTP username, password and server details are not handled by this feature.
- Subject line breaks are removed, fields are length bounded, email validity is checked server-side, errors are logged without message bodies or credentials, and external-tab isolation uses `noopener noreferrer`.
- WhatsApp is user-initiated and does not call a Meta/WhatsApp API or claim delivery.

## Verification

- PHP syntax: `PASS` for the service, page and both focused tests.
- M055 source/security contract: `PASS` (15 checks).
- Phone normalization and WhatsApp URL contract: `PASS` (9 checks).
- `git diff --check`: `PASS`.
- Legacy M045 quotation source regression: `PASS`.
- Database-backed multilingual Operations suite: `NOT_RUN`; the configured local MariaDB runtime was unavailable, and no database start or mutation was authorized or inferred.
- Canonical staging compare and preflight: `PASS`; all six target files matched the pinned M045 baseline or approved absence before apply, and candidate lint/contract tests passed.
- Canonical staging apply and independent verify: `PASS` for runtime commit `991011e8db9a95f0fa98ce5d518d09fca551f4bc` and artifact SHA-256 `983541e2a3f4f0200ba1aeab891efc68226dfe0a34c1f9672b0f4e036a6c3a68`.
- Code backup: `m055-before-991011e-20260813T204755Z.tar.gz`, SHA-256 `f8b5338b89279f8ab45a04333b60fc2a0d66a1337a3e92a6b594a110fe530b57`; prestate SHA-256 `d6d0dd01a116a834e37bd5dd04a1f0ea0463f9551065186c457289044357bcce`.
- HTTPS and anonymous-login boundary: `PASS`; the staging homepage and Website Requests route returned healthy login responses.
- Authenticated browser desktop and 390px: `PENDING_STAGING_LOGIN`; the available browser session was not authenticated to `backup-erp`.
- Real SMTP/IMAP send/reply through the new UI: `PENDING_AUTHENTICATED_SYNTHETIC_ACCEPTANCE`; no customer message or business record was created.
- Database changed: `NO`. Production accessed: `NO`.

## Surface parity

- Admin ERP: `DEPLOYED_AND_AUTOMATED_VERIFIED_ON_BACKUP_ERP_STAGING`.
- Customer Portal: `NOT_APPLICABLE`; no route, projection or UI changed.
- Native Mobile App: `NOT_APPLICABLE`; no API or screen changed.
- Public React/Node/WordPress: `NOT_APPLICABLE`; the existing request contract already supplies email, phone and country code.

## Boundaries and next gate

Ahmed confirmed staging is working and separately approved pushing M055 live on 2026-08-14. Production scope is frozen to the exact staging-accepted artifact and six runtime files, with code-only backup, fail-closed compare/preflight/apply/verify and automatic rollback. No database, configuration, mailbox or business-data change is authorized.
