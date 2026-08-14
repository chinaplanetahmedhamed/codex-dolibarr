# M056 — Completion Report

## Report control

- **Mission:** M056 — Shipment status email notification
- **Mission status received:** APPROVED
- **Report status:** PRODUCTION_HELPER_INSTALLED_AWAITING_COMPARE
- **Implementation owner:** Agent 007 Central
- **Branch:** `codex/m056-shipment-status-email`
- **Base commit:** `281cdcc955378fdb674fb21a46e129f529e8f5e1` (M055 live record)
- **Result commit:** `606b54c852230b0674b0745084e107487cf3161d` (M056 implementation plus dependency-closed preserved staging 200MB upload correction)
- **Environment used:** Local source/focused tests, canonical `backup-erp` staging, production artifact upload, and restricted helper installation/verification
- **Production touched:** Deployment tooling only; ERP runtime not changed

## Business outcome

Authorized staff can select an email notification while editing a shipment. After the shipment transaction commits, the system sends exactly for a real customer-visible status transition, using the entity-scoped customer's main email and Dolibarr's configured sender. Operational shipment truth is preserved if email delivery fails.

## Scope reconciliation

- **Completed:** Explicit edit-form option; permission, CSRF, entity and customer boundaries; real-status-change gate; server-owned recipient/sender; Arabic/Chinese/English content; customer-safe allowlist; post-commit send; Agenda history; focused tests and parity documentation.
- **Not completed:** Production compare, preflight, apply, verify, and Ahmed's actual-work email acceptance.
- **Scope deviations:** Ahmed declined a staging SMTP configuration correction after the synthetic send failed safely and directed M056 to production for actual-work testing.
- **Staging baseline reconciliation:** Initial compare stopped before apply because staging `card.php` matched documented commit `851c231` rather than the older source baseline. The release candidate now retains the full two-file 200MB staff-upload dependency (`card.php` plus `portal.lib.php`) while adding M056, with a focused dependency test; no staging runtime was changed during this reconciliation.

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
| Added | `deploy/tools/chinaplanetos_production_m056_shipment_status_email.sh` | Checksum-pinned production compare/preflight/apply/verify/rollback/status helper |
| Added | `deploy/tools/install_production_m056_helper.sh` | Checksum-locked root installer for the restricted helper and sudoers rule |
| Added | `deploy/config/codex-deploy-production-m056.sudoers` | Six exact production helper commands for `codex-deploy` |
| Added | `deploy/tests/test_m056_production_helper.sh` | Production scope, pin, rollback, and durability contract |

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
| Dependency-closed staging package | Exact 8 runtime + 4 support files | Artifact `19943d703d...` rebuilt byte-for-byte; Security and QA PASS | PASS |
| Staging compare/preflight/apply/verify | Exact baseline, backup, hashes, candidate/live checks | All passed for runtime `606b54c`; state `DEPLOYED` | PASS |
| HTTPS and anonymous shipment boundary | Healthy ERP and authenticated-only card | HTTP 200 and login boundary PASS | PASS |
| Authenticated admin desktop/390px | Correct edit row and result states | Checkbox and Update action present; no horizontal overflow at `1728px` or `390px` | PASS |
| Synthetic staging send | Safe committed transition and deterministic mail/Agenda result | Status committed; PHP-mail transport failed; retry shown; Mailpit `0`; no email Agenda row | FAIL_SAFE_CONFIGURATION_BLOCK |
| Production artifact rebuild | Byte-identical exact staging artifact | SHA-256 `19943d703d...`; `cmp` pass; 27 archive members | PASS |
| Production helper contract | Production-only target, six commands, baseline pins, durability and rollback | Local contract plus independent Security and QA reviews passed | PASS |
| Production helper installation | Exact root-owned helper and six fixed commands; runtime remains untouched | Helper SHA-256 `6798f0d8...`, `root:root 0755`, six-command sudo policy, status `NOT_DEPLOYED` | PASS |

## Security and customer-isolation review

- **Entity and `fk_soc` enforcement:** Existing update validation is preserved; recipient query repeats the active `entity` plus shipment `fk_soc` boundary.
- **Internal cost/private data exposure:** Message is constructed only from reference, status transition, ETA, approved public reason and authenticated link.
- **Files/external links:** No attachments or public token. Link targets the existing authenticated shipment card.
- **Security reviewer result:** PASS after independent corrected re-review.

## Known risks, blockers, and follow-ups

- Customer third-party records without a valid main email receive no notification and show a staff warning.
- Agenda history is best-effort after a successful SMTP send; a history failure is visible but cannot retract the delivered mail.
- Retry is manual and bounded to the latest customer-visible status event. There is no automatic queue or delivery receipt.
- Staging did not prove successful SMTP/Agenda/link delivery because its Dolibarr mail method is PHP mail rather than internal Mailpit SMTP. Ahmed explicitly chose production actual-work acceptance instead of changing staging configuration.

## Deployment boundary

- Local/staging actions performed: Deployed exact dependency-closed eight-file runtime to canonical `backup-erp`; compare, preflight, backup, apply, verify, status, HTTPS and anonymous-login boundary passed.
- Production actions: Uploaded the exact artifact and checksum-pinned helper tooling, installed only `/usr/local/sbin/chinaplanetos-production-m056-shipment-status-email` plus its six-command sudo policy, and independently verified the helper checksum, metadata, permissions and `NOT_DEPLOYED` state.
- Production ERP runtime, database, configuration, mailbox, documents, module state, Compose, proxy, DNS, ports and business records remain unchanged. Compare/preflight and apply remain separate gates.

## Rollback instructions

Run the restricted helper command `/usr/local/sbin/chinaplanetos-backup-erp-m056-shipment-status-email rollback`. It restores the exact prestate from `/root/backup-erp-code-backups/m056-before-606b54c-20260813T233252Z.tar.gz` (SHA-256 `74c918efce926b7d38b2b471d44d33b806e3079f598d5fe8f3df327e87337112`) using prestate SHA-256 `f303f6339a2e5e7c5fa3a50aaf11df38676cddee6926d46ff1e5a696b450f8aa`. No schema rollback exists. Keep any already-created Agenda/email history as an immutable communication record.

## Review record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping | Independent source review | PASS | 2026-08-14 | Corrected status, retry and permission flow verified |
| Customer Portal | Agent 007 parity review | PASS | 2026-08-14 | Existing committed status projection unchanged |
| Security | Independent source and production-helper review | PASS | 2026-08-14 | Production target, allowlist, hashes, durability, rollback and fixed sudo surface verified |
| QA | Independent runtime and production-package review | PASS | 2026-08-14 | Artifact byte identity, baselines, hashes, lint, tests, state and rollback passed |
| Architect | Agent 007 Central | ARCHITECT_ACCEPTED | 2026-08-14 | Local mission accepted; separate staging gate required |
| Ahmed | Ahmed Hamed | APPROVED_IMPLEMENTATION | 2026-08-14 | “ok, do it” |

## Architect disposition

- **Decision:** PRODUCTION_HELPER_INSTALLED_AWAITING_COMPARE
- **Reason:** Ahmed explicitly confirmed the exact production helper upload/install. Independent verification matched the reviewed helper checksum and six-command policy, while status remained `NOT_DEPLOYED`.
- **Documentation updated:** Mission, completion report, changelog, parity record.
- **Final mission status:** PRODUCTION_HELPER_INSTALLED_AWAITING_COMPARE; compare/preflight remains the next read-only gate and apply remains separate
