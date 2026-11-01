# M119 actual branch treasuries — 2026-11-02

Ahmed explicitly supplied the four opening cash amounts for actual branch use. The authenticated **backup-ERP** administrator created four separate native Cash accounts in the same entity using the ordinary Dolibarr account form. Each account has its own currency and one native SOLD initial-balance entry dated 2026-11-02. Independent native account-card and statement reads verified the approved amounts, dates, cash types and currencies. A second statement read confirmed exactly one opening per account. Production was not accessed.

Exact amounts, native account/line IDs and private readback evidence are retained outside Git in the private evidence directory recorded by the Mission. This report and synthetic tests deliberately omit real financial amounts.

| Branch | Currency | Stable native reference |
|---|---|---|
| Egypt | EGP | CPO-EG-EGP |
| Egypt | USD | CPO-EG-USD |
| China | CNY (RMB) | CPO-CN-CNY |
| China | USD | CPO-CN-USD |

Native account creation uses `Account::create` and its standard initial-balance line, without suppressing native triggers. Accountancy codes remain blank; no chart accounts, FX rates or statutory journal posting were invented. These are original-currency opening cash balances, not revenue. Company base currency remains unchanged. No payment, payroll, loan, transfer or customer-receipt posting was performed. Existing unrelated QA account was not targeted; no independent before/after balance fingerprint was captured for that account.

## Source integration

[Native treasury reader](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeTreasury.php) resolves only the server-defined branch/currency references within the trusted entity. Missing, duplicate, closed, non-cash, case-mismatched or wrong-currency mappings fail closed. Native references are not assumed to be database-unique. User-supplied account IDs cannot redirect a read. Queries return only safe cash metadata and authorized statement fields; native bank identifiers, private comments and credentials are excluded.

Branch treasury read permission is required for full cash balances/statements. Own-record treasury access returns no branch balance or entries. Fresh native identity, module capability and membership checks precede and follow the read. Native bank lines are scoped through their account because the native bank table has no entity column. Each account total and its latest 100 entries come from one SQL statement; an explicit older-entry flag preserves the distinction between a partial statement and the full balance. SQL decimal-to-text conversion preserves signed original-currency values, including eight-decimal native amounts. No cross-currency sum, guessed conversion or independent cash ledger exists.

[Workspace service](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceService.php), [controller](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceController.php), [page](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspacePage.php) and [styles](../../custom/chinaplanetofficeexpenses/workspace.css) display two separate original-styled treasury cards and native statement tables. Opening lines explicitly say opening balance, not revenue. Settings shows safe per-currency mappings. Its legacy zero account selection means automatic mapping, not unlinking; nonzero metadata accepts only the validated primary native cash account. Settings cannot activate payments or redirect USD to the primary-currency account.

[Branch currency contract](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffAccess.php) and [document validation](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspaceDocument.php) permit primary currency plus USD in operational drafts for both branches. Settings keeps the original primary currency. This does not update the historical private pilot currency configuration or establish a native FX/payment adapter.

## Verification and release boundary

Local PHP 8.4.7 / official Dolibarr 24.0.1 source: all 19 scoped CLI suites pass, including 14 workspace document and 24 workspace/native access cases. Two historical credential/session scenarios remain explicitly skipped. Four-role CRUD/payroll ownership cases, cash metadata rejection, own/branch/entity denial, immediate rights revocation, statement limits, original-currency balances and AR/EN rendering pass with synthetic values. Fourteen synthetic HTTP scenarios and both standalone package tests pass. Every changed/new PHP file passes syntax checks. The HTTP script is executable directly; unittest discovery does not apply to it.

Matching PHP 8.2.32 / MariaDB 10.6.27 check-only [Actions run 41354595823](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41354595823) succeeded for exact runtime source b61b8c691282be81888876d28c667f1a1d3ba0e7. It includes the native cash reader on copied official native table definitions, signed totals, separate branches/entities, duplicate-reference rejection, SOLD distinction and eight-decimal wire values. All deployment jobs were skipped; this is CI evidence, not a deployment.

Fresh local synthetic browser DOM observations cover Treasury and Settings in Arabic and English at effective 1778, 602 and 355 CSS-pixel widths: 12 cases, no document overflow, duplicate IDs or unlabelled controls; treasury renders two distinct currency cards. Tables scroll internally. These are synthetic snapshots with test balances, not actual native sessions or phone pixel acceptance. Native opening-account acceptance is separate from source-only integration tests.

The existing deployed R03 module has **not** been replaced by this continuation. Expanded workspace schema/feature activation, new branch grants, native web acceptance and real financial posting remain pending. The old R03 helper is not a compatible deployer for the larger dependency closure. The new reader is included only in the expanded workspace package; historical R01/R02/R03 manifests remain unchanged. No core/schema/configuration or new staff rights were changed by this read-only source integration.

Admin ERP: actual native cash accounts/openings verified; expanded workspace source tested but undeployed. Customer Center: not applicable, no exposure. Separate Mobile App: not applicable, no new API; responsive web verification is a separate evidence class.

Rollback: disable/revert the expanded source package through a compatible pinned release. Retain approved native accounts/openings, all existing documents, receipts, grants and audit history. Do not restore an older database backup over these new financial records or silently delete/repost an opening. Any later opening correction must use a separately reviewed native accounting correction.

Branch: codex/m119-office-expenses-local. Runtime source commit: b61b8c691282be81888876d28c667f1a1d3ba0e7, pushed and verified by the check-only run above. This documentation follow-up does not alter runtime bytes.
