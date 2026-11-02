# M119 — Local office-expenses foundation and native draft proof

Outcome: READY_FOR_REVIEW at the bounded local-development scope. An employee draft interface is now implemented and tested with synthetic sessions. This is not production readiness or acceptance of native deployed authentication.

Latest checkpoint (2026-11-01): [private receipt interface](M119_RECEIPT_INTERFACE.md) records **118 passing safe CLI cases**, including15 new save→attach→list→scoped-download controller/rendering cases. Optional multipart/private-download wiring and Finens-styled Arabic/English controls now exist, with no runtime factory or schema activation. Actual new multipart/binary HTTP and visual acceptance remain pending: browser file-URL navigation was denied before opening and no workaround/server was used. No services or credentials were created. Earlier [16 native HTTP cases](M119_NATIVE_HTTP_ACCEPTANCE.md) remain historical login evidence and do not establish acceptance of the changed receipt entrypoint. Counts and pending statements below describe their historical milestones.

Subsequent [release-readiness review](M119_RELEASE_READINESS.md) raises the current safe CLI total to **125/0 failures** with7 native transaction uncertainty/recovery cases. The native operational store now refuses unconfirmed success and same-handle reuse after uncertain begin/commit/rollback. Migration, finance-role, original-currency/valuation and one-bank-event reconciliation prerequisites are documented; UI/runtime/schema/accounting gates remain unchanged and no server/browser workaround occurred. The118-case paragraph above describes the preceding receipt milestone.

Latest [native storage/migration milestone](M119_NATIVE_STORAGE_MIGRATION.md): **143/0 failures**, including22 native-storage and17 fresh-owned-database migration cases. Explicit pinned versioned install/readiness/reversal code and shared connection quarantine now exist; no existing database/runtime was migrated or activated. Test metadata/advisory locks are simulated around genuine owned SQLite/native transactions. Matching-engine and native receipt adapters remain pending. All prior totals above are historical checkpoints.

## Latest backup-ERP operational workspace — 2026-11-02

[All-sections activation evidence](M119_ALL_SECTIONS_ACTIVATION.md) supersedes the historical local-only deployment statements below: R04 is deployed on backup-ERP, all nine workspace sections and full users matrix active, two additive operational tables and four audited membership continuations verified. Native administrator HTTP CRUD/print/export, branch cash preservation, anonymous denial and72 AR/EN responsive DOM cases pass. All16 clearly marked synthetic records remain archived. Actual payment/FX/payroll posting, staff password login, physical phone taps and production are not accepted by this release.

## Receipt design correction — 2026-11-02

[Receipt design acceptance](M119_RECEIPT_DESIGN_CORRECTION.md) records current R06 backup-ERP application1be58db, exact source backup/apply/verify and independent verification,32 component cases plus full matching-engine Mission CI, native AR/EN branch print/denial checks,392px/1280px layouts, and one-page A4 PDF exports with three independently decoded local QR. No native finance/schema/role writes. Actual payment activation has been requested; approval ownership and statutory-posting scope remain pending in the [proposed activation contract](../M119_NATIVE_PAYMENT_ACTIVATION.md).

## Delivered

- Private Finens reference retrieved through the authenticated GitHub connector and reconstructed/verified against exact remote Git objects: `2955c7efca00dfac0edc30baa3124ec85944cc71`. The reference stays clean on `main`; original sensitive database/history were never imported.
- New VS Code window visibly titled `Office Expenses Local (Workspace)` contains the isolated ERP development folder and sanitized reference. Standard CLI access required sandbox escalation to reach the existing VS Code IPC service. Other windows were preserved.
- Development is isolated on `codex/m119-office-expenses-local`, base `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`. Existing source-checkout changes were not transferred or overwritten.
- Exact decimal-string amounts, configured currency exponents, quote direction/date/source and half-up estimates; original amounts remain immutable. Claims, advances, advance returns, transfers and reimbursements have separate draft intent classifications. Mixed-currency settlement is rejected.
- Scoped immutable drafts derive entity/creator from trusted internal native-right context, reject forged state/identity fields, require office scope and preserve source reference/date/purpose. Synthetic persistence is idempotent and draft/audit writes are atomic.
- Native bridge reloads the owned draft by ID before handing it to a writer. The only provided writer is explicitly restricted to disposable synthetic SQLite. Actual ExpenseReport/ExpenseReportLine creation and native total recalculation are exercised with no approvals, payments or accounting posting.
- Static Arabic/English preview uses synthetic data only, separates claim totals by currency, and shows the 1,000 − 700 − 300 advance example. It accepts no submissions and includes no live resources.

## Verification

`php deploy/tests/test_m119_office_expenses.php`: **16 scenarios pass**.

`php deploy/tests/test_m119_native_drafts.php /absolute/path/to/local/dolibarr-24.0.1/htdocs`: **13 scenarios pass**.

The native test runs real native create/line/price methods against empty copied native table definitions and synthetic dictionaries. It proves EGP and CNY claims in separately configured synthetic entities, retry idempotency, scoped identity/module enforcement, unknown tax/foreign-currency/non-claim rejection, rollback after line/link/readback failures, conflicting mappings, altered native dates/amounts/taxes, silently ignored link completion, preservation of caller transactions and large original amounts. It loads neither runtime config nor business rows and deletes its temporary databases.

Native SQLite source required a fixture subclass for missing constructor SQL helpers and SELECT result counting, plus removal of MySQL timestamp auto-update syntax from copied DDL. External hooks are inert and triggers suppressed per call in tests. Native/core files and runtime configuration were not edited. This does not prove MySQL/MariaDB or deployed-hook behavior.

All added PHP files are checked with `php -l`; staged additions are checked with `git diff --cached --check`. The mechanical UI detector returned no findings. Static previews were inspected in Arabic/English at desktop 1920 CSS px and the browser's actual phone width of 433 CSS px (390 was requested from the override, but 433 was observed). No horizontal page overflow was observed. Language navigation works. Independent visual review found no blocking visual defect in the four screenshots; this is not authenticated functional acceptance.

## Independent code review and corrections

Two initial findings were reproduced and fixed RED→GREEN: configurable three/four-decimal preview totals, and whitespace-normalized same-account transfers. Native review found two further issues, both reproduced and fixed RED→GREEN: persisted line date/tax invariants, and silent registry-update failure. Final native verification reloads complete link evidence before commit and rejects drift in native dates, taxes, local-tax totals, amounts, state, category, source/purpose, ownership and detail count.

## Decisions and limits

- Source belongs in existing `codex-dolibarr`; the private Finens repository remains an attributed reference. Ahmed confirmed China Planet's ownership in the task conversation.
- Native proof supports only claims matching the synthetic entity base currency, with explicit zero-tax treatment. CNY-in-EGP and other foreign native cases are blocked despite having a domain FX estimate. Do not silently convert a claim into a native report until the target behavior/accounting policy is reviewed.
- Native examples use real library source locally observed as 24.0.1; installed target version, enabled modules, actual base currency, office/user assignments, expense category/tax configuration and schema remain unverified.
- The preview is read-only and the settlement panel is a fixed explanatory example; settlement arithmetic is separately covered by domain tests. Receipts/attachments, approval screens, native login/CSRF routes, production writer, advances/payment/FX accounting and migrations remain future work.
- Customer Center and mobile app exposure: not applicable in this local stage. An additive staff entrypoint now exists but is not deployed; its native persistence factory is deliberately absent and an unconfigured runtime returns503. No bank records, real ledgers, access grants, real credentials, original-source changes, pushes or deployments.
- Existing ERP worktree is older than current uncommitted operating work. Reconcile this additive foundation with the selected release base before any native installation.

## Rollback and next stage

Rollback is removal/revert of the additive local files. No native runtime state or real records exist to undo. Keep this local branch and reference workspace for review; do not activate the synthetic writer in a runtime.

Next: choose an authorized disposable native test runtime matching the intended target engine/version, verify module/entity/office/category/tax configuration, wire the employee interface to a production draft port and receipt storage, prove native session behavior and foreign-currency persistence, and review accounting mappings before payments, settlements or journal writes.

## Resumed employee-interface milestone

The staff entrypoint uses ordinary native `main.inc.php`, `CSRFCHECK_WITH_TOKEN`, `currentToken()`/`newToken()` and a trusted service factory bound to the identical native user object/entity. No production factory, activation or permission is supplied. The injected service owns office/currency options. Employees can submit claims, received advances and advance returns; bank transfers, payments, approvals and native writer actions are not exposed.

The interface saves immutable own drafts, preserves original currency and FX provenance, retains values and submission key after validation/storage failure, and redirects after successful submission. Same-key retries do not duplicate records; conflicting intent returns409. Guest/external/revoked-right contexts, forged offices/identity, IDOR and missing/expired CSRF are rejected. Responses use no-store caching and same-origin-only form/style CSP; database failures disclose no internal details. File uploads are explicitly rejected pending receipt-storage work.

Additional verification: `php deploy/tests/test_m119_employee_ui.php` — **12 controller scenarios pass**; `python3 deploy/tests/test_m119_employee_http.py` — **9 HTTP scenarios pass** with isolated cookie sessions and synthetic storage. Combined M119 total: **50 scenarios passing** (16 domain/store +13 native +12 controller +9 HTTP). The HTTP fixture models native expired-token sanitization (`unset($_POST)` plus `InvalidToken`), reproduces the review finding and verifies the fixed403 response without warnings. Actual native login/session acceptance remains pending; the test fixture does not establish it.

A real browser submitted `700.10 EGP` with a synthetic receipt reference, received the saved-draft confirmation and displayed the immutable original amount. Arabic/English desktop/phone captures are stored outside source in `m119-employee-evidence/`; the actual phone viewport was433 CSS px with no horizontal page overflow. The local review server is loopback-only. Its session bootstrap uses one-use synthetic fixture tickets outside the document root, not native credentials or an application login replacement.

Remaining integration limits: no production draft writer/configuration, deployed native auth acceptance, receipt attachments, approvals, native foreign-currency persistence, nonzero taxes, settlement/payment/reconciliation workflows or deployment. Previously staged work is retained on the isolated branch.

## Native identity and capability checkpoint

Added **7 passing scenarios** using actual native password checking, fetched `User` objects, direct/group rights, fresh-request revocation, validity dates, PHP session persistence and an owned unpaid native draft. Disabled or expired/future-valid users now fail the draft service and synthetic writer. Added **5 passing observational probes**: the tested native header/line create paths discard supplied multicurrency metadata; simple sample VAT-inclusive calculations and persistence work, including one small-amount rounding case. These observations do not enable foreign-currency or nonzero-tax adapters.

All **62 M119 scenarios pass**. Complete native `main.inc.php` HTTP/session lifecycle and matching-engine/runtime acceptance remain pending. See [native identity/capability evidence and precise gates](M119_NATIVE_IDENTITY_CAPABILITIES.md) for commands, source hashes, fixture boundaries and remaining work. No production state, real credentials/rights/records, deployment or financial posting was touched.

## Native provenance and representation checkpoint

Added **3 passing native provenance storage scenarios**, bringing the verified M119 total to **65**. Actual native extrafield APIs preserve exact UTF-8 source/decimal quote data; header evidence failure rolls back header creation; detail evidence requires explicit native save/load calls. This does not enable foreign-currency accounting or establish immutable metadata. Financial writer gates remain unchanged.

The source version file declares24.0.1. A complete disposable runtime was not available: no MySQL/MariaDB executables on PATH, no local3306 listener, default and `colima-m073` Docker sockets absent, and both Colima profiles stopped. Native full-bootstrap HTTP/session acceptance is pending; no denied or uncertain action exists. No daemon/VM/service was started and no real configuration was read.

[Native representation and reconciliation design](M119_NATIVE_REPRESENTATION_DESIGN.md) documents a candidate native base-currency representation linked to original evidence, mutable-extrafield safeguards, rounding discrepancies, tax/account bindings, payment/bank duplication risks, synthetic calculations and exact decisions still needed from the owner/accountant. It cites official documentation and local native source; it is not an approved accounting policy.

## Historical release receipts and acceptance preparation

Parent research reports Dolibarr24.0.1/PHP8.2.7 on Oct27 and an older separate MariaDB10.6.27 receipt on Oct2. None is fresh parity verification. The older README23.0.3 target and M11624.0.2/MySQL5.7 are not matching evidence. Local PHP is8.3.21, so the SQLite/library results are not target-PHP/engine acceptance.

[Native acceptance preparation](M119_NATIVE_ACCEPTANCE_PREPARATION.md) records the source-derived token/session/logout behavior and a 16-case inert acceptance plan, all **not run**. One additional native-helper/controller token distinction scenario passes; total verified M119 scenarios is now **66**, excluding the 16 planned cases. No services/installations/security settings were changed. Matching runtime, fresh version/engine confirmation, full native HTTP execution and accounting-policy gates remain pending.

## Original-design restoration

Ahmed rejected the earlier visual departure from Finens. The local interface now restores the reference sidebar/navbar, colors, actual typography, icon set, rounded panels, filter/table register, quick-entry dialog, mobile drawer and bottom navigation. Static previews use the same renderer. Backend financial/domain/storage gates remain intact; available actions create own drafts only. Two new filter/navigation scenarios bring the verified total to **68**. A review-found400 rendering warning was reproduced and fixed before preserving this checkpoint.

[Visual restoration report](M119_FINENS_VISUAL_RESTORATION.md) records source authority, matched Arabic/English desktop/mobile screenshots, real browser synthetic save/error/search checks and limitations. Original references are static source-template reconstructions; the original app was not run. Mobile tables retain horizontal scrolling. Full native session acceptance, category/tax/FX accounting, real balances and deployment remain pending.

## Mobile money readability follow-up

The previous clipped-money limitation is resolved at the verified320/375/433px phone widths. The original amount/currency column remains fully readable through both scroll ends; other fields retain the Finens horizontal table with localized controls. Maximum mixed-currency fixtures, expanded quote evidence, empty states, resizing and desktop behavior are checked. CSS/JS content hashes prevent stale cached assets hiding the fix. **69 regression scenarios pass**, with protected accounting/domain/store files unchanged.

[Mobile verification and remaining gates](M119_MOBILE_MONEY_READABILITY.md) records the evidence and scope. Full native runtime acceptance and financial adapters remain pending; existing Colima profiles stayed stopped. No security/network settings, real records, commit, push or deployment changed.
