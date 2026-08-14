# M056 — Shipment status email notification

## Mission control

- **Mission ID:** M056
- **Type:** IMPLEMENTATION
- **Status:** PRODUCTION_PREFLIGHT_PASS_AWAITING_APPLY
- **Architect owner:** Agent 007 Central
- **Implementation owner:** Agent 007 Central
- **Required reviewers:** Shipping, Customer Portal, Security, QA
- **Dependencies:** M011 customer-safe shipment events; M055 configured Dolibarr outbound email
- **Target environment:** Local development, canonical `backup-erp` staging, and exact approved production release
- **Production:** Explicitly approved by Ahmed for M056; helper review/install and fail-closed gates remain required before apply
- **Ahmed approval:** APPROVED — “ok, do it”
- **Approval date:** 2026-08-14
- **Staging approval:** Ahmed Hamed — “Deploy M056 to backup-erp staging” — 2026-08-14
- **Production approval:** Ahmed Hamed — “no, publish to live erp, i will test there” — 2026-08-14

## Business objective

Allow authorized logistics staff to notify the shipment customer by email when, and only when, the saved shipment status actually changes and the staff member explicitly selects the notification option.

## Current system context

- `ChinaPlanetLogistics::update()` already compares the stored and submitted status and records an entity/customer-scoped `status_changed` event.
- The shipment edit form already separates internal notes from customer notes and includes an explicit customer-visible publication checkbox.
- M055 verified outbound Dolibarr email through the configured company sender and Agenda history.
- There is no existing shipment-status email sender.

## Scope

1. Add an explicit **Send email notification** checkbox to the existing shipment edit form.
2. Send only after a successful committed status change and only when the update is customer-visible.
3. Resolve the recipient server-side from the shipment third party in the active entity; do not accept a posted recipient.
4. Use the third party's default language for Arabic, Chinese, or English customer-safe content.
5. Include shipment reference, old/new customer-safe status, ETA when present, public update reason, and an authenticated shipment link.
6. Exclude internal notes and commercially private fields.
7. Record successful sends in Dolibarr Agenda against the customer and shipment.
8. Preserve the saved shipment status when email sending or Agenda history fails; show a bounded staff warning and permit a separately submitted retry of the latest customer-visible status event without rewriting shipment data.
9. Add source/contract tests and update the completion report, changelog, and portal/mobile parity record.

## Out of scope

- Production configuration, database, documents, module state, Compose, proxy, DNS, or mailbox changes.
- Automatic email for ETA-only, ATD-only, ATA-only, document, gallery, or transshipment updates.
- WhatsApp automation, inbound email collector changes, attachments, bulk notifications, automatic queues, or schema changes.
- Customer portal or mobile UI changes; their existing timeline continues to consume the same committed status event.

## Architecture and data flow

`shipment edit POST → permission/CSRF checks → checked transactional shipment update/event commit → status-change proof returned by object → server-side customer/language lookup → Dolibarr CMailFile send → Agenda event → staff result message`

If SMTP fails, an authorized staff POST can resend the latest exact entity/customer-scoped, customer-visible `status_changed` event. The retry does not edit the shipment or create another shipment event.

Email failure is intentionally outside the shipment transaction so an SMTP outage cannot roll back operational truth. Standard Dolibarr mail and Agenda services are reused; no core file or database schema is changed.

## Permissions and visibility

- Internal staff only; external customers remain read-only.
- Requires ChinaPlanet Logistics write. When Dolibarr advanced permissions are enabled it also requires the standard shipment-send right; administrators remain authorized.
- Entity and `fk_soc` are verified in the existing update boundary and again during server-side recipient lookup.
- CSRF remains enforced by the shipment card.
- Internal notes, supplier data, costs, margins, source identities, and private files are not read into the email body.

## Database, accounting, and configuration impact

- **Database:** No schema or business-data migration. Successful emails create a standard Agenda row.
- **Accounting:** None.
- **Configuration/module state:** Reuses `MAIN_MAIL_EMAIL_FROM` and the already configured Dolibarr SMTP transport.

## Success criteria

- A permitted staff save with a real status change, customer-visible publication, and checked notification sends one customer-safe email after commit.
- An unchanged status, unchecked publication, unchecked notification, missing/invalid recipient, or missing permission sends no email.
- The browser cannot replace the recipient or sender.
- Email failure does not undo the status or audit event and is visible to staff.
- Successful email is recorded against the same entity/customer/shipment in Agenda.
- PHP lint and focused M056 contract tests pass.

## Risks and rollback concept

- **Risks:** Wrong or missing master-data email, duplicate operator saves, SMTP failure, unsafe content leakage.
- **Controls:** Server-owned recipient, actual status-change proof, explicit checkbox, post-commit send, customer-safe field allowlist, Agenda record.
- **Rollback:** Restore the M056 application files to the M055 commit. Existing shipment and Agenda history remain intact; no schema rollback is needed.

## Staging deployment gate

- Deploy only the exact eight-file dependency-closed runtime allowlist in `deploy/releases/m056-runtime-files.txt` to canonical `erp-staging` / `backup-erp.visiontrading.biz`.
- Use a checksum-pinned, fail-closed M056 helper with compare, preflight, apply, verify, rollback and status commands.
- Create and verify a code backup before apply. Do not change the database, Dolibarr configuration, module state, documents, Compose, proxy, DNS, ports or customer business records.
- Use Mailpit only for any later synthetic delivery acceptance. Do not send a staging notification to a real customer.
- The authenticated edit row passed desktop and `390px` browser inspection with no horizontal overflow.
- A synthetic staging status transition from Arrived Port to Under Customs committed correctly, while the email failed safely because staging Dolibarr uses PHP mail instead of Mailpit SMTP. Mailpit remained at zero messages, no shipment-email Agenda row was created, and the bounded retry action appeared.
- Ahmed declined the staging mail-configuration correction and separately approved the exact M056 production release, retaining the real-customer send as his production acceptance step.
- Staging preflight detected that `card.php` already contained the exact documented `851c231` 200MB staff-upload correction. M056 preserves the complete two-file dependency (`card.php` and `portal.lib.php`) instead of reverting it.
- Deployed runtime commit `606b54c852230b0674b0745084e107487cf3161d`; artifact SHA-256 `19943d703ddfc0866aa79ffb003a58e82780b824162d9ac08e872c46bad54592`.
- Compare, candidate tests, preflight, backup, apply, independent verify, status, HTTPS health and anonymous-login boundary passed. Database/configuration/business records were unchanged and production was not accessed.

## Production deployment gate

- Reuse the byte-identical staging artifact for runtime commit `606b54c852230b0674b0745084e107487cf3161d`, SHA-256 `19943d703ddfc0866aa79ffb003a58e82780b824162d9ac08e872c46bad54592`, with the same exact eight runtime and four support files.
- Compare production only against the recorded M055 source baseline before apply; stop on any unexpected absence or SHA drift.
- Use `/usr/local/sbin/chinaplanetos-production-m056-shipment-status-email` with only compare, preflight, apply, verify, rollback, and status commands.
- Require an exact code backup, prestate metadata, disk-space checks, fsync, atomic replacement, candidate/live tests, HTTP/login-boundary checks, durable state, and automatic rollback/reapply protection.
- Do not change the database, Dolibarr configuration, module state, documents, mailbox, Compose, proxy, DNS, ports, or business records during deployment.
- Ahmed confirmed upload and installation of the production helper on `31.97.158.178`. The exact artifact and tooling were uploaded with matching server-side SHA-256 values. The installed helper is `root:root 0755`, SHA-256 `6798f0d893b6a6b2683832f96962cb19cfc5565825c1bc2e058b7efeab531a16`, and the restricted account exposes only the reviewed compare/preflight/apply/verify/rollback/status commands for this helper.
- Independent post-install status is `M056_PRODUCTION_STATUS=NOT_DEPLOYED`. No compare, preflight, apply, verify, rollback, runtime mutation, database change, or customer email was performed under the helper-install approval.
- Ahmed separately confirmed the production compare and preflight gate. The restricted helper matched all eight target files to the recorded M055 production baseline, validated the exact artifact/runtime commit and candidate checks, and returned `M056_PRODUCTION_COMPARE=PASS` followed by `M056_PRODUCTION_PREFLIGHT=PASS` with `DATABASE_CHANGED=NO`.
- Independent status after preflight remains `M056_PRODUCTION_STATUS=NOT_DEPLOYED`. Apply, verify and rollback did not run; production runtime and data remain unchanged pending a separate final apply approval.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping | Independent source review | PASS | 2026-08-14 | Corrected status, retry and permission flow verified |
| Customer Portal | Agent 007 parity review | PASS | 2026-08-14 | Existing committed status projection unchanged |
| Security | Independent source and production-helper review | PASS | 2026-08-14 | Runtime privacy plus production scope, pins, durability, rollback and six-command sudo surface verified |
| QA | Independent runtime and production-package review | PASS | 2026-08-14 | Byte-identical artifact, all baselines, hashes, lint, contracts and rollback/state behavior passed |
| Architect | Agent 007 Central | APPROVED | 2026-08-14 | Narrow no-schema local mission |
| Ahmed | Ahmed Hamed | APPROVED | 2026-08-14 | “ok, do it” |
