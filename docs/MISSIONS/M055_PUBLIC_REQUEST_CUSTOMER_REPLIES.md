> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M055 — Reply to website customers from Dolibarr

## Mission control

- **Mission ID:** M055
- **Type:** FEATURE / ADMIN_WORKFLOW
- **Status:** LIVE_VALIDATED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — “link this to the website coming request ... send email directly ... phone number can open new window for whatsapp”
- **Approval date:** 2026-08-13
- **Staging approval:** Ahmed Hamed — “Deploy M055 to backup-erp staging” — 2026-08-13
- **Staging acceptance:** Ahmed Hamed — “ok it working now” — 2026-08-14
- **Production approval:** Ahmed Hamed — “need to push to live” — 2026-08-14
- **Production target:** `erp.visiontrading.biz` / `vision_erp`, exact staging-accepted six-file runtime only

## Objective

Let authorized staff contact the person behind a public website request without copying contact details into another tool: compose and send email from the request screen through Dolibarr, or open a new WhatsApp conversation from the stored phone number.

## Approved scope

1. Add email and WhatsApp actions to the existing website-request detail screen.
2. Keep the email recipient server-derived from the selected request and the sender server-derived from Dolibarr mail settings.
3. Require both website-request update and operations email rights, plus the existing CSRF token, before sending.
4. Record outgoing email in the Dolibarr Agenda; when a quotation exists, use the standard proposal tracker so replies can be associated with it.
5. Open WhatsApp in a separate protected browser tab using a validated international number and a customer-safe draft containing the durable request reference. Never auto-send.
6. Preserve Arabic, English, Chinese, RTL/LTR, desktop and phone behavior.
7. Add focused source and phone-normalization tests, changelog, parity and completion reporting.

## Restrictions

- No React, Node, WordPress, Customer Portal, native Mobile App, schema, mailbox, email-collector, credential, customer-data, quotation-state, order, invoice, payment or accounting change.
- No attachment sending in this first increment.
- No WhatsApp Business API, Meta credential or automated WhatsApp send.
- Staging deployment is authorized only through the checksum-pinned, fail-closed M055 staging helper against canonical `erp-staging`.
- Production deployment is authorized only for the exact staging-accepted artifact SHA-256 `983541e2a3f4f0200ba1aeab891efc68226dfe0a34c1f9672b0f4e036a6c3a68`, six-file runtime allowlist, production code backup, compare, preflight, apply, verify and rollback if validation fails.
- No production database, configuration, mailbox, documents, Compose, proxy, DNS, customer records or other runtime files may change.

## Acceptance

- [x] Authorized staff can open an in-Dolibarr email composer from a website request.
- [x] Customer email cannot be replaced by a posted recipient field.
- [x] SMTP sending uses Dolibarr's standard mail class and configured sender.
- [x] Outgoing mail creates an Agenda event and proposal tracking is used when available.
- [x] A valid stored phone opens `wa.me` in a new protected browser tab; invalid numbers do not create a link.
- [x] Arabic, English and Chinese labels and responsive source rules are present.
- [x] Focused PHP syntax, source/security and phone-normalization tests pass locally.
- [x] Checksum-pinned deployment, candidate tests, live file verification, HTTPS health and anonymous-login boundary passed on canonical staging.
- [x] Ahmed accepted the working staging behavior on 2026-08-14.
- [x] The exact staging-accepted six-file runtime was deployed to production after compare and preflight passed; independent verify and status report `DEPLOYED`.
- [x] Authenticated production desktop and 390px acceptance passed for the request list, email/WhatsApp actions and email composer, with no page-wide mobile overflow.
- [ ] A real production send/reply is intentionally left to Ahmed's actual-work check; deployment validation did not send to a customer.
