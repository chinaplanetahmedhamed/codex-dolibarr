# M056 — Completion Report

## Report control

- **Mission:** M056 — Shipment status email notification
- **Mission status received:** APPROVED
- **Report status:** DEVELOPER_REPORTED
- **Implementation owner:** Agent 007 Central
- **Branch:** `codex/m056-shipment-status-email`
- **Base commit:** `281cdcc955378fdb674fb21a46e129f529e8f5e1` (M055 live record)
- **Result commit:** `2646d0b3f1fb8b47c45325b2aedec7695a2ad73f` (M056 implementation plus preserved staging 200MB upload correction)
- **Environment used:** Local source and focused tests; canonical `backup-erp` staging approved
- **Production touched:** No

## Business outcome

Authorized staff can select an email notification while editing a shipment. After the shipment transaction commits, the system sends exactly for a real customer-visible status transition, using the entity-scoped customer's main email and Dolibarr's configured sender. Operational shipment truth is preserved if email delivery fails.

## Scope reconciliation

- **Completed:** Explicit edit-form option; permission, CSRF, entity and customer boundaries; real-status-change gate; server-owned recipient/sender; Arabic/Chinese/English content; customer-safe allowlist; post-commit send; Agenda history; focused tests and parity documentation.
- **Not completed:** Authenticated browser and synthetic Mailpit checks remain staging acceptance work.
- **Scope deviations:** None.
- **Staging baseline reconciliation:** Initial compare stopped before apply because staging `card.php` matched documented commit `851c231` rather than the older source baseline. The release candidate was corrected to retain its single 200MB staff-upload argument while adding M056; no staging runtime was changed during this reconciliation.

## Files changed

| Action | File | Purpose |
|---|---|---|
| Added | `custom/chinaplanetlogistics/class/ShipmentStatusEmailService.class.php` | Recipient lookup, localized safe message, Dolibarr mail send and Agenda history |
| Modified | `custom/chinaplanetlogistics/card.php` | Permission-gated option and post-commit send orchestration |
| Modified | `custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php` | Expose the server-proven committed status transition to the caller |
| Modified | `custom/chinaplanetlogistics/langs/en_US/chinaplanetlogistics.lang` | English UI/result messages |
| Modified | `custom/chinaplanetlogistics/langs/ar_SA/chinaplanetlogistics.lang` | Arabic UI/result messages |
| Added | `deploy/tests/test_m056_shipment_status_email_source.php` | Security and integration source contract |
| Added | `deploy/tests/test_m056_shipment_status_email_service.php` | Recipient, real language resource, send/Agenda/SMTP and retry behavior contract |
| Added | `deploy/tests/test_m056_shipment_update_commit.php` | Commit success/failure behavior contract |
| Added | `custom/chinaplanetlogistics/langs/zh_CN/chinaplanetlogistics.lang` | Chinese customer status labels |
| Added | `docs/MISSIONS/M056_SHIPMENT_STATUS_EMAIL_NOTIFICATION.md` | Approved scope and acceptance contract |
| Added | `docs/REPORTS/M056_COMPLETION_REPORT.md` | Delivery evidence and boundaries |
| Modified | `docs/CHANGELOG.md` | Local feature record |
| Modified | `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md` | Admin/portal/mobile parity impact |

## Database, accounting, configuration, and permissions

- **Database/schema/data:** No schema migration. A successful send creates a standard Dolibarr Agenda event; no business record is changed by local tests.
- **Accounting/multicurrency:** None.
- **Configuration/module state:** No change. Reuses `MAIN_MAIL_EMAIL_FROM` and existing SMTP settings.
- **Permissions/customer visibility:** Requires logistics write; when advanced permissions are enabled it also requires `expedition.shipping_advance.send`, matching Dolibarr core. External customers cannot invoke sending. Recipient lookup requires exact active entity/customer. Message content excludes internal notes, supplier/carrier identity, cost, margin and files.

## Tests and evidence

| Test | Expected | Actual | Result |
|---|---|---|---|
| PHP lint for all changed/added PHP | No syntax errors | All four PHP files passed | PASS |
| M056 source/security contract | All gates present | 21/21 checks passed | PASS |
| M056 service/message contract | Scoped recipient, real AR/ZH/EN resources, send/Agenda/SMTP/retry behavior | 9/9 checks passed | PASS |
| M056 update commit contract | Commit success exposes status; commit failure blocks send | 2/2 checks passed | PASS |
| Existing M055 focused regressions | Continue to pass | Contact service and 15-check source contract passed | PASS |
| Shipment lifecycle regressions | Existing statuses, timeline, collaboration, free-time and shell remain valid | M004, M011, M023, M024, free-time and unified-shell suites passed | PASS |
| Git whitespace check | No errors | Passed | PASS |
| Authenticated admin desktop/390px | Correct edit row and result states | Not run; staging deployment pending | PENDING_STAGING |
| Synthetic Mailpit send | One captured mail and Agenda event without customer delivery | Not run; staging deployment pending | PENDING_STAGING |

## Security and customer-isolation review

- **Entity and `fk_soc` enforcement:** Existing update validation is preserved; recipient query repeats the active `entity` plus shipment `fk_soc` boundary.
- **Internal cost/private data exposure:** Message is constructed only from reference, status transition, ETA, approved public reason and authenticated link.
- **Files/external links:** No attachments or public token. Link targets the existing authenticated shipment card.
- **Security reviewer result:** PASS after independent corrected re-review.

## Known risks, blockers, and follow-ups

- Customer third-party records without a valid main email receive no notification and show a staff warning.
- Agenda history is best-effort after a successful SMTP send; a history failure is visible but cannot retract the delivered mail.
- Retry is manual and bounded to the latest customer-visible status event. There is no automatic queue or delivery receipt.
- Staging acceptance must verify actual recipient selection, localized content, SMTP, Agenda history, authenticated link, desktop and 390px UI before any production proposal.

## Deployment boundary

- Local/staging actions performed: Local source implementation and focused tests complete; Ahmed approved canonical `backup-erp` staging deployment on 2026-08-14.
- Production actions: None.
- Production readiness is not approval to deploy.

## Rollback instructions

Restore the M056 application and language files to base commit `281cdcc955378fdb674fb21a46e129f529e8f5e1` through a reviewed release rollback. Remove only the M056 test/document files from source if abandoning the feature. No schema rollback exists. Keep any already-created Agenda/email history as an immutable communication record.

## Review record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping | Independent source review | PASS | 2026-08-14 | Corrected status, retry and permission flow verified |
| Customer Portal | Agent 007 parity review | PASS | 2026-08-14 | Existing committed status projection unchanged |
| Security | Independent source review | PASS | 2026-08-14 | Commit, CRLF header, permissions, scoped retry and privacy verified |
| QA | Independent local review | PASS | 2026-08-14 | M056 behavioral and shipment regression suites passed |
| Architect | Agent 007 Central | ARCHITECT_ACCEPTED | 2026-08-14 | Local mission accepted; separate staging gate required |
| Ahmed | Ahmed Hamed | APPROVED_IMPLEMENTATION | 2026-08-14 | “ok, do it” |

## Architect disposition

- **Decision:** ARCHITECT_ACCEPTED
- **Reason:** Approved local scope is complete, all corrected tests pass, and Shipping/Portal, Security and QA independent reviews pass.
- **Documentation updated:** Mission, completion report, changelog, parity record.
- **Final mission status:** DEVELOPER_REPORTED
