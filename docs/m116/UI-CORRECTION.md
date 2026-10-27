# M116 simple cash account book — isolated UI correction

Approved correction: five display styles, normal Dolibarr top navigation, customer/project card Balance and Due. Owner clarified Due is funds needed to cover recorded spending: max(0, -cash balance), not invoices, overdue items or commitments. This is the same manual cash book in every presentation: what customer paid, what was paid for customer, and balance. No automatic native payment integration or customer portal.

Branch codex/m116-statement-presentations starts at abfed8b. Existing statement-dev and the separate realtime/touch trial are unchanged. Boards owner explicitly approved editing only this worktree's boards.js and requested a small patch for a single-writer later merge. Implementation began as an isolated correction. The later explicit conditional production approval was fulfilled; see docs/REPORTS/M116_UI_RELEASE_COMPLETION.md for the deployed outcome.

## Behavior

- Display selector: Ledger, Statement, Spending groups, Activity feed, Workspace. Preference stores only a view name locally by entity/user; amounts remain server-authorized.
- Shared entry controls, custom text columns, original amounts, locked USD-per-original FX, allocation details. Spending groups show recorded spending net of reversal corrections, with partial group details scoped to the group and whole-entry actions suppressed for partial allocations.
- Native llxHeader/llxFooter wrap an account fragment. Account CSS is scoped under #cpo-account. Binary XLSX dispatch precedes the native shell. Print stays standalone and opens record metadata; canonical paired layout is used for print regardless of screen view.
- New CustomerAccountSummary delegates canonical statement() for finance, native customer/project/card and entity checks. Base USD and each original-currency position remain distinct. Customer includes unallocated funds; project uses direct/card allocation lines once. Due is exact integer cents, no floating point.
- Card summaries are loaded separately from the board payload. Unauthorized/unavailable scopes have no totals and render no financial placeholder. Late responses cannot update detached cards. Requests contain up to20 scopes; duplicate scopes are cached per server request. One canonical projection per unique scope is a bounded correctness-first implementation; large-gallery load measurement remains a nonblocking follow-up.
- Existing draft/post/reversal/idempotency logic and schema are unchanged. No migration, rights registration/grants or credentials are added.

## API / single-writer Boards integration

GET tasks/account_summary.php?scopes=1:0:0,1:10:0

Each item is customer:project:task, strict decimal IDs, up to20, maximum700 characters. Numeric transport preserves native Dolibarr input protection (JSON in GET was rejected during native acceptance). Response is private,no-store. Finance read is checked before scope parsing/read. Each response item has scope, available, and totals only when authorized. No finance values are added to boards_api gallery/workspace JSON or hidden HTML. Keep all dependency files together; boards.js alone cannot supply summaries.

Integration calls accountCardSummary(tile, customer[,project]) for both enhanced and legacy customer/project galleries. No CSS/boards.php/boards_api edits. Realtime changes must be merged by one writer, then combined regression/UAT repeated; do not replace a newer boards.js wholesale.

## Verification and limits

- Full deploy/tests/m116/run.sh passes with old Mission regressions, exact arithmetic, canonical scope/rights, exports, SQLite concurrency/idempotency and workspace regression suites. New summary19 assertions cover positive/zero/negative balance, currencies, reversals, card/project conservation, unallocated funds, invalid/bounded scopes and denied payloads.
- Renderer cases cover all five modes, escaping, exact split grouping, spending reversal netting, receipt reversals excluded from spending, shared/group-scoped details.
- Card harness covers denied/success responses, exact money and currency separation, mismatched scopes, late detached DOM, 41-card batching and disabled feature.
- Native HTTP acceptance10 checks passed on the existing synthetic m116_test_account_stmt_20261026 database: normal finance login, native top menu and one account fragment, customer/project/card routes, finance summary/no-store, real XLSX with Due, standalone print, no-finance statement and summary403/no totals. Credentials kept private.
- Actual browser fixture: all five views inspected at1280px desktop and390px phone, no page overflow (wide ledger scrolls inside its own region); mobile create/save succeeded in each view. Synthetic receipt300USD and spending500CNY at locked0.14USD/CNY posted correctly to230USD base balance. Arabic390px fragment is RTL with no overflow. Print route shows original amounts, FX, running balance, Due and allocation content.
- Fixture menu is explicitly a synthetic shell, not native menu visual proof. Native menu and customer/project card pixels were subsequently verified through the existing synthetic native identity; all five native phone views passed. Realtime/touch remains excluded from this release. Actual print dialog/PDF save was not exposed by IAB. A Chrome fixture Print click timed out and left that QA tab blank; the created tab was closed without affecting other tabs. A separate one-page PDF from captured native rendered DOM was generated with WeasyPrint70, exact amount/FX/allocation text checked, and pixels reviewed after print-contrast correction; OS dialog/save itself remains unverified. No claim of physical touch or exact live PHP8.2.7/Dolibarr24.0.1 acceptance (local native24.0.2/PHP8.3.21).
- Independent review found no critical/important defect in the initial reviewed diff; its detail-parity and test gaps were corrected and are covered above. Final fix review found no important issue and one minor grouped rounding-detail residual issue; selected allocation residuals are now summed and a red/green split-project regression covers it.

## Recovery / rollout boundary

All changes are code only. Revert this isolated commit to restore prior presentation; no database restoration/migration is involved. Before any later release, obtain the current production source and owner freeze, merge only this scoped correction with newer Boards work, back up affected code, rerun native/combined tests and request the separately required deployment authorization. Never restore an old database for this UI correction.

Admin ERP: implemented. Customer Center / Mobile App: not exposed; no customer portal change. Responsive internal ERP mobile: fixture verified; native pixels pending as above.
