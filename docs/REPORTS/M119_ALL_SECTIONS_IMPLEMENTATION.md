# M119 — Remaining branch workspace sections

Date: 2026-11-02. Branch: `codex/m119-office-expenses-local`.
Status: operational implementation tested locally; financial activation and backup-ERP rollout remain pending. This is not a deployed-finance acceptance report.

## Implemented

The original Finens grouped right sidebar, white rounded cards, green treasury hero, table proportions, AR/EN text direction, and request-local removal of the native Dolibarr sidebar remain the UI basis. A native-authenticated `workspace.php` route provides the following:

| Section | Available operational behavior |
|---|---|
| Categories | Branch category create/edit, enable/disable, archive/restore |
| Employees | Directory of active native staff assigned to this branch; optional job/contact/base-salary/hire-date profile; create/edit/archive/restore without creating/deleting login accounts |
| Payroll | One employee/month document per branch; exact base, bonus, gross, ordinary deduction, loan repayment and net; edit/archive/restore and printable draft |
| Treasury | Persistent deposit requests with source, purpose, original money and date; clearly unknown cash balance until a native account is configured |
| Receipt vouchers | Persistent payer/contact/payment-method/purpose document, stable reference, edit/archive/restore, printable draft with office letterhead |
| Expenses / advances | New versioned operational requests, category and advance references, archive/restore; earlier immutable drafts and private-attachment links retained |
| Reports | Date/type/status filters, totals by original currency and request type, own/branch restrictions, original and newer drafts included once each, formula-safe UTF-8 CSV, browser print/PDF |
| Office settings | Versioned name/contact/currency; small PNG/JPEG logo validated and re-encoded as PNG, displayed on letterheads; remove current logo without erasing audit history |
| Company users | Exact section/action checkboxes for read/create/update/archive where supported; reports read-only; native identity/capability ceilings, no financial approval capability or automatic new grants |

Delete grants mean reversible archive/restore, not physical deletion. Existing posted accounting records are not edited. The new documents never claim to be bank movements, paid salaries, accepted expense reports, or an independent ledger.

Branch categories/letterhead are shared metadata only for explicitly permitted readers; their mutation requires branch visibility. Personal payroll/profile records follow the subject under own-record grants. Native identities, memberships, capabilities and validity are refreshed for operations; unsupported/forged fields, cross-branch IDs, stale revisions and unauthorised actions fail closed.

## Persistence and release boundary

New owned tables: `llx_cpo_oe_workspace` and `llx_cpo_oe_workspace_audit` (native prefix respected), separate manifested schema version `m119_office_expenses_workspace_v1`. The existing base/draft/receipt/access manifests are unchanged. Installation is explicit, checksum-pinned, locked and refuses partial/drifted/unmarked schemas. Operational writes own the native transaction, compare revisions and persist full before/after audit. Create retries compare entity/actor/key/intent; commit uncertainty never reports a confirmed save.

`CPO_OE_WORKSPACE_ENABLED` defaults off and also requires staff activation and exact schema readiness. Deploying source alone does not activate sections or grant existing staff additional access. Bootstrap admin can configure new grants after activation. Moaz, Rina, Omar and Shan Shan's current memberships are not modified by this change.

The extended package has a complete separate dependency closure; historical R01/R02/R03 packages remain byte-reproducible. Historical pinned server helpers deliberately cannot deploy this larger/schema-bearing package. A new reviewed backup-ERP release must back up the database/files, install only the additive workspace schema, enable the workspace flag, and accept authenticated native GET/POST/print/export and branch denials. Do not use the old three-file sidebar updater for this feature release.

## Financial decision still required

Fresh authenticated backup-ERP discovery found only account 1, `M088QA-EUR`; it is unrelated QA data and has not been changed or bound. Egypt/EGP and China/CNY native cash accounts and the approval policy remain unconfirmed. Ahmed was asked whether branch managers, the overall administrator, or draft-only operation should govern cash activity. No answer has been received at this checkpoint.

Consequently nonzero cash-account binding and native payments remain disabled. Native salary creation exposes a single native amount; gross salary versus loan reduction, foreign currency, expense/advance accounting and receipt/deposit deduplication still need the approved native adapter and real workflow tests. A receipt and deposit request cannot be assumed to be separate income. No production, cash, ledger, payroll payment, FX rate or underlying account change has occurred.

## Verification

- Local PHP 8.4.7 against official native Dolibarr24.0.1 library/schema source; temporary synthetic SQLite is a test fixture only.
- 13 typed-document/logo/exact-payroll tests and 17 synthetic native persistence/policy/controller/rendering tests pass. Coverage includes idempotency, stale updates, archive/restore, atomic audit failure rollback, uncertain commits/fresh readback, native subject validation, own/cross-branch denials, revocation, CSRF, forged verbs/identity fields, CSV formula injection and AR/EN rendering.
- Full applicable local Mission suites pass: foundation, standalone module, staff access, employee UI, staff UI regression, menu sync, native drafts/identity/capabilities/provenance/workflow/storage/migration/receipts, receipt UI, pilot configuration. Two credential-dependent identity scenarios remain explicitly skipped.
- Synthetic HTTP fixture: 14 scenarios pass, including default-deny anonymous/unactivated access to the new route. This does not establish activated native web-session acceptance.
- Historical release/helper/package tests pass; actual root-owned replacement is reserved for the Linux CI check. Extended package closure/reproducibility and schema hashes pass.
- Browser: 9 new sections × AR/EN × desktop/compact widths (36 rendered cases), observed effective CSS widths1778 and602. No document overflow, duplicate IDs, unlabelled controls or invalid money input types; native sidebar is absent in these local fixtures. Smaller phone follow-up and exact PHP8.2.32/MariaDB10.6.27 CI are recorded below once complete.
- Changed PHP syntax, JavaScript syntax and `git diff --check` pass.

## Parity, limits and rollback

Admin ERP: implemented operational surface, awaiting native runtime activation/acceptance. Customer Center: not applicable/no exposure. Native mobile app: not implemented; responsive web layouts are tested separately.

No destructive migration or public logo upload is introduced. Logo copies stay in versioned internal documents/audit. Reports fail rather than silently truncate above10,000 matching documents; the UI register will require pagination before that scale. Earlier immutable rows remain read-only. PDFs use browser Print/Save PDF; no automatic outbound sharing or email is added.

Rollback: disable only the workspace feature flag, restore the exact prior code package after verifying the release backup, and retain the new schema, operational documents and audit. Do not drop tables or restore an older database over new submissions. Financial posting remains off throughout.
