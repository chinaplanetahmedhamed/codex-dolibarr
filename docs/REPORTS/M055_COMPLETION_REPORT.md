# M055 implementation report

## Verdict

`DEVELOPER_REPORTED / LOCAL_SOURCE_AND_FOCUSED_TESTS_PASS / STAGING_ACCEPTANCE_PENDING`.

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
- Authenticated browser desktop and 390px: `PENDING_STAGING_APPROVAL`; source contains responsive stacking rules but no runtime acceptance is inferred.
- Real SMTP/IMAP send/reply through the new UI: `PENDING_STAGING_APPROVAL`; existing mailbox configuration is not changed by this source mission.

## Surface parity

- Admin ERP: `IMPLEMENTED_AND_SOURCE_VERIFIED_LOCAL`.
- Customer Portal: `NOT_APPLICABLE`; no route, projection or UI changed.
- Native Mobile App: `NOT_APPLICABLE`; no API or screen changed.
- Public React/Node/WordPress: `NOT_APPLICABLE`; the existing request contract already supplies email, phone and country code.

## Boundaries and next gate

No database, module state, configuration, mailbox, business record, staging or production environment was changed. The next permitted step is a separately approved staging release and authenticated acceptance using a synthetic customer address/number. Production remains forbidden until staging is accepted and Ahmed gives exact production approval.
