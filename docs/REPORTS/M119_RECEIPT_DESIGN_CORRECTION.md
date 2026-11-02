# M119 receipt register and A4 correction

Status: BACKUP_DEPLOYED; ADMINISTRATOR_READ_AND_PRINT_ACCEPTANCE_VERIFIED, checkpoint2026-11-02 06:23UTC. Actual payment activation remains a separate pending business/accounting decision; this correction does not enable financial posting.

The receipt register follows the observed original Finens reference: branch branding banner, three summary cards, date/search/status filters, nine-column register and receipt editor dialog. The voucher has three copies (customer, accounts, archive), cut lines, branch logo, permanent reference/date, original amount/currency and amount words, payer/purpose/notes, creator/signatures and local QR. The native configured staging URL retains an old alias; the receipt-only URL builder canonicalizes that exact trusted alias to backup-ERP, without changing global configuration or accepting Host headers. QR requires the existing authenticated branch/own access; no public receipt capability or third-party QR request.

Existing IDs, records, audit, permissions, own/branch isolation, original money and draft/archived semantics are retained. Branding projects only permitted branch identity/logo/contact, never native account IDs. Save-and-preview reuses CSRF/idempotency/revision checks. No schema, role, credential, financial balance or production application/database/container change. No new business/test records were created for this correction; the existing clearly synthetic archived receipts were reused read-only.

## Current release and files

Application source `1be58db8a0ccd6bd0608ed722c34f36d2ed82c08`; delivery `40386de`. [R06 release41398659676](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41398659676) passed the matching-engine Mission checks, exact three-file source backup/apply and separate verify. R05 initially deployed the six-file redesign; R06 corrects the QR alias and ordinary notes fitting found during native acceptance.

Artifact `f5805ea0ce8167336b14b3a481678625a4edd1cbd8d7605ca97e78c7bd05b58f`; manifest `30f66aff642bc40ffa709646866e60a19cb7e8b11174c629817485201433b6c8`. Complete59-file runtime dependency closure plus support declaration remains checked. Installed helper `76ce5fe08cb3081dc2eeb4ecec00e7b37dec5c668524855e5df541c31263f386`, root:root0755. Independent restricted helper verify after native browser acceptance returned verified, financial_posting=false, roles_changed=false, schema_changed=false.

- [Receipt controller](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceController.php): filters, scoped receipt state, save/print redirect, canonical trusted staging alias.
- [Workspace service](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceService.php): authorized receipt branding/creator projection.
- [Receipt rendering](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspacePage.php), [CSS](../../custom/chinaplanetofficeexpenses/workspace.css), [JavaScript](../../custom/chinaplanetofficeexpenses/workspace.js) and [native entrypoint](../../custom/chinaplanetofficeexpenses/workspace.php): original register/modal and three-copy A4 with local native TCPDF QR.
- [Native component cases](../../deploy/tests/test_m119_workspace_native.php), [R05 release tests](../../deploy/tests/test_m119_receipt_deployer.py) and [R06 release tests](../../deploy/tests/test_m119_receipt_acceptance_deployer.py): permission, closure, pinned-package and root filesystem/rollback boundaries.

## Verification

- All changed PHP lint, JavaScript syntax and source whitespace pass. Full Mission CI passed PHP8.2.32/Dolibarr24.0.1/MariaDB10.6.27, real isolated MariaDB/private-filesystem/root frozen-chain suites, FAILED_SUITES=0. Targeted workspace cases:32 passed,0 failed. [Checks-only run41397992043](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41397992043).
- Authenticated administrator AR/EN Egypt/China receipt print routes:200; three copies/two cut lines/three local QR each, exact canonical host/branch/record targets. Cross-branch direct receipt ID:403; invalid dates and unsupported paid-status filter:422; no receipt data returned.
- Native AR/EN392px phone layout and1280px desktop: no page overflow, original nine-column table scrolls within its region, zero unlabeled module controls. Actual loaded modal event handlers open/close the native dialog and preserve save-and-preview. Physical-device taps and staff password login are not claimed.
- Chrome exported fresh authenticated rendered HTML (scripts/hidden inputs removed only in the private test snapshot; app assets resolved to identical local source) to PDF. AR/EN native archived synthetic receipts, including notes, each produce one A4 page with three full copies. PDFKit visual inspection passes. Independent Apple Vision decoding at300dpi reads all three QR in each PDF and confirms the exact backup-ERP hostname. This is PDF/export evidence, not a physical printer test.
- Synthetic long notes intentionally expand to two pages; all300 test words and all three final markers remain present. No clipping or silent truncation. Source regex detector found no issues; parsed-page contrast detection is not claimed.

Admin ERP/responsive staff web: implemented and verified above. Customer Center: not applicable, no customer exposure. Native Mobile App: not applicable. Actual payments, statutory journals, FX and payroll settlement remain disabled pending the [native payment activation contract](../M119_NATIVE_PAYMENT_ACTIVATION.md).

## Rollback and incident

Root-private state/frozen R05 helper/exact three predecessor backups: `/var/lib/chinaplanetos-backup-erp-deploy/m119-receipt-r06`. The matching [fixed remote actions](../../deploy/releases/m119/run_receipt_acceptance_remote_action.sh) rollback restores those three R05 files, retaining all data and the other redesigned receipt files. Never restore an old database or send an earlier release request to the current fixed helper. Full withdrawal of the redesign would require a reviewed ordered R06 then R05 rollback, not an unpinned overwrite.

During R05 installation preparation, Termius typeText dropped a separator in an identity command and transiently changed the shared host kernel hostname to id. The installer stopped on its host guard before any state/application write. An exact-precondition restoration returned srv1011043; root screenshot and independent restricted SSH/os.uname verified restoration before retry. No persistent hostname file or service restart was performed. Subsequent bounded commands used paste, observation and a separate Return. This incident is also recorded in the authoritative VPS current-state record. No production application/container/database inspection or change occurred.
