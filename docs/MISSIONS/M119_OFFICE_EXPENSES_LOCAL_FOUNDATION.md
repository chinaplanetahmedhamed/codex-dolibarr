# M119 — Office expenses local foundation

Latest scope, 2026-11-01: Ahmed now requests push and deployment to `backup-erp.visiontrading.biz` first, with a pilot restricted to the current administrator and Egypt/China offices. This supersedes earlier local-only/no-push restrictions below for this release; production and financial posting remain excluded. The [current preflight](../REPORTS/M119_STAGING_RELEASE_PREFLIGHT.md) records166 passing synthetic CLI cases and the native receipt/owner-isolation continuation. Activation is blocked: freshly verified staging PHP8.2.32 lacks required BCMath. Runtime factory binding, actual MariaDB and transport acceptance remain pending. No staging files, schema, permissions or services were changed. Earlier checkpoint statements below are historical.

Status: READY_FOR_REVIEW (local foundation and synthetic native proof only). Scope authorized by Ahmed: pull the private reference, open a new VS Code window, and start the module locally. Ahmed confirms China Planet owns Finens and Moaz manages Egypt. The resumed task explicitly adds safe native integration with synthetic fixtures. This is approval of bounded local development, not financial posting, module activation, deployment, or access changes.

2026-11-01 update: Ahmed separately approved disposable local native login testing and the exact socket/loopback18849/synthetic-account operation. [All16 native HTTP cases now pass](../REPORTS/M119_NATIVE_HTTP_ACCEPTANCE.md), including a reproduced/fixed direct-login403→303 defect. Test-only services/accounts/configuration were removed and verified; real access, financial posting and deployment remain excluded. Historical future gates below are superseded only for this completed local HTTP acceptance.

Subsequent local storage scope: inspect and implement safe draft/attachment functionality using synthetic data, with no production, posting, persistent credentials, security/network changes, commit/push/deploy or restart of the disposed runtime. [The storage checkpoint](../REPORTS/M119_STORAGE_RECEIPTS.md) supplies an unwired native operational-draft adapter/inactive schema and scoped private image-receipt service/test adapter, with103 safe CLI scenarios passing. Existing task/operation document tables were not repurposed because their ownership/lifecycle do not match draft UUIDs. No native installation or HTTP/ECM/file-serving activation is included.

Latest authorized continuation connects that private service to the employee interface, using synthetic files only, configurable conservative formats/limits and no automatic receipt deletion. [The receipt-interface checkpoint](../REPORTS/M119_RECEIPT_INTERFACE.md) records118 passing safe CLI scenarios and optional native multipart/private POST-download wiring with the same employee/entity/office boundaries. Real runtime factories remain absent. No server, account or credential was created. Browser file-URL opening was denied before navigation, so new visual and actual multipart/binary HTTP acceptance remain pending. No workaround, production, accounting activation, commit, push or deployment occurred. Older future-scope statements below are historical only where this update explicitly supersedes them.

Current [release-readiness checkpoint](../REPORTS/M119_RELEASE_READINESS.md):125 safe CLI cases pass after7 new native operational transaction-failure/recovery tests. Unconfirmed begin/commit/rollback retires the handle across draft-store instances; fresh physical connection/readback is required, without deleting evidence or reporting uncertain success. Migration, roles, classification, multi-currency and reconciliation were reviewed against canonical accounting rules. No policy, financial adapter, runtime/permission/schema state, UI or browser/server behavior was activated; target-engine, transport, visual and business gates remain explicit.

Subsequent [native storage/migration checkpoint](../REPORTS/M119_NATIVE_STORAGE_MIGRATION.md):143 safe CLI cases pass, including22 storage and17 migration cases. Shared native connection guard, version/checksum registry and explicit owned fresh-install/readiness/reversal adapters are implemented locally. Existing databases, runtime factory/module initialization, servers and accounting remain untouched. Partial/unmarked/drifted or uncertain outcomes stop without destructive recovery; actual engine/lock/disconnect proof and native receipt persistence remain separate gates. Browser denial was not retried or bypassed.

## Design and approach

Preserve Finens' simple office experience while extending the existing ChinaPlanetOS operations module. Source remains in `codex-dolibarr`; the separate private Finens repository is an attributed, sanitized reference. Do not run its standalone authentication, treasury, seeding or SQLite application.

Compared approaches: an independent Finens ledger would require reconciliation with a second accounting system; directly wrapping native expense reports is simpler but does not yet prove foreign-currency persistence or advance settlement. Chosen local foundation: exact currency/domain rules, scoped immutable drafts, an isolated synthetic SQLite workbench and a static bilingual review screen. Native write adapter remains disabled. The choice follows the authorized staged approach; live accounting mappings remain subject to owner/accountant review.

## Existing architecture evidence

- Owning source: `custom/chinaplanetoperations`; PHP minimum 7.4, Dolibarr minimum 23. Existing native session/rights and entity boundaries must be reused later.
- Local PHP 8.3.21 with BCMath and PDO SQLite. Read-only native source inspected at local Dolibarr 24.0.1: `expensereport/class/expensereport.class.php` exposes currency fields, but `ExpenseReport::create()` does not insert those fields. Native payment object is `PaymentExpenseReport`. This does not establish live version or configuration.
- Live installed modules, entity base currency, office assignments, chart of accounts and schema state are intentionally unverified: no runtime configuration, credentials or ledgers accessed.
- Current ERP checkout has substantial unrelated changes. Separate worktree starts from `310deeaa70ced8d1a74aa84c802e2c705e54b2a1` on `codex/m119-office-expenses-local`; no existing code is replaced.

## Rules and interfaces

- Every draft retains kind, office, original amount/currency, date, source reference, purpose, creator and entity. Amount/rate inputs must be decimal strings; no binary floats, scientific notation, silent rounding of original money or currency netting.
- EGP, CNY and USD are initial synthetic configuration, not inferred live base currencies. Currency exponents are configurable. RMB is displayed as CNY, the ISO code. Entity base currency is supplied separately from office reporting currency.
- An optional quote is base-currency units per one original-currency unit; preserve rate, date, source, rounded estimate and rounding policy. Converted values are review estimates and never overwrite originals or imply posting.
- Kinds: expense claim, employee advance, advance return, transfer, reimbursement. Only an approved claim may later recognize expense. Advances/returns settle employee balances; transfers move funds; reimbursement settles a claim. None posts in this phase.
- Settlement helper accepts same-currency amounts only. Example: EGP 1,000 advance minus EGP 700 claims minus EGP 300 returned = zero outstanding, expense EGP 700.
- Draft submissions derive entity/creator from trusted context. Native `expensereport.creer` and internal identity are required, with explicit office scope. External users, guests, cross-office requests and forged context fields are rejected. These checks use synthetic user fixtures; no real permissions are granted.
- Idempotency is scoped to entity/creator/key; same payload returns the same immutable draft, different payload conflicts. Draft and audit insert are atomic. Local SQLite is exclusively synthetic test/workbench storage, not an ERP ledger or production adapter.
- No activation, live migration, real accounts, login, uploads, approval, posting, payment, bank entries, background sync, customer/mobile exposure or deployment.

## Synthetic native stage

`OfficeExpenseNativeDraftBridge` reloads owned immutable drafts through `getOwn`; the only writer provided is an isolated test tool. `LocalNativeDraftWriter` requires the M119 synthetic marker, native SQLite type, generated database name and owned temporary path. It creates actual native ExpenseReport/Line objects in copied empty native table definitions. It creates no native bank, payment, approval or accounting journal records and exposes no endpoint.

Native proof is deliberately limited to claims matching entity base currency with explicitly verified zero tax. EGP/entity 1 and CNY/entity 2 have separate synthetic acceptance cases. A CNY claim in an EGP entity is rejected before writes despite having an FX estimate. Additional currencies/FX quotes are preserved by the domain foundation, but this does not establish native foreign-currency persistence.

Native creation and immutable link evidence are atomic and idempotent. Retries check current native ownership, entity, draft/unpaid state, date, source/purpose, category, detail count and decimal amounts. Conflicting evidence blocks continuation. Readback failure, native line failure and link failure roll back the entire native draft. No caller-owned transaction is absorbed.

Native source is local 24.0.1, not asserted to be the installed target. SQLite fixture constructor/row-count adaptations and no-op hooks/per-call trigger suppression are documented in `deploy/tools/office_expenses/README.md`. They do not modify native source or runtime settings. MySQL/MariaDB and installed hook acceptance remain pending.

## Employee draft interface milestone

The resumed local task authorizes an authenticated employee interface using existing native identity/rights. `index.php` now uses the ordinary native bootstrap and mandatory token protection, but requires a server-injected factory bound to the current native user/entity. No production factory or settings are installed. Synthetic HTTP acceptance uses an isolated loopback fixture with separate sessions, never actual accounts, credentials or configuration. This does not prove deployed native login behavior.

Employees can submit and view their own claim/advance/return drafts. Unknown/forged fields, office changes outside scope, other users'/entities' IDs, bad tokens and posting actions are denied. Native token rejection that clears POST is explicitly handled. Source amounts and quote provenance remain exact; draft retry keys and POST/Redirect/GET prevent duplicate submissions. Financial/native posting remains disabled. Receipt uploads, production storage and native foreign-currency/tax/reconciliation acceptance remain pending.

## Accounting impact and future gate

Current double-entry impact: none. Drafts are not expenses, account balances or posted events. Proposed later mappings: approved business claim → native expense report (tax and currency persistence proven); advance/return → approved employee receivable/current-account treatment; transfer → one linked native transfer group preserving both legs; reimbursement → native expense-report payment linked to existing claim/bank movement. Never post the same cash movement through two paths. Actual account selection, taxes, FX fees, realized/unrealized FX and currency-conversion settlement require accountant review and native integration tests first.

## Verification, migration and rollback

Use synthetic fixtures only. Test exact arithmetic, rate direction/date/source/rounding, precision rejection, currency isolation, native-right negative cases, scoped idempotency, persistence/reopen, audit rollback and disabled posting. Verify Arabic/English desktop/phone preview and HTML escaping. Run changed PHP syntax checks and `git diff --check`.

No data migration occurs. Finens database and source uploads stay excluded. Rollback is removal/revert of the additive local foundation; there are no native records or schema changes to undo. Admin ERP write integration remains pending; Customer Center and mobile are not exposed.

## Completion boundary

This phase is complete only as a local foundation, not as a usable accounting module. Follow-up: inspect exact native target version/configuration in authorized test environment; implement native draft adapter with tax/currency persistence and idempotent links; test authenticated native lifecycle and approvals; then review advances, settlements and payments before enabling writes.
