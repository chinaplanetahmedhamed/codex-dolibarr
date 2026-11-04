# M121 v6 — dated consumer inventory and current-only presenter guard

Recorded 2026-11-04T03:21:29.732879+00:00. Codex is sole code writer in the existing integration worktree. Parent Work coordinates shared records/release. Frozen v5 commit 4b9af979df697de0794dbbc7877bbeab4df98c84, earlier archives and separate Docs website package remain preserved. **This is a narrow presentation reliability fix, not historical reconstruction or a claim that all runtime/extensions are guarded.** No remote/production access, deployment, schema/config/grant change or accounting/posting change occurred.

## Source inventory

Read-only PHP tokenization examined 287 regular PHP files below this candidate's custom/ tree, skipping symlinks.45 direct named calls to the selected financial readers/wrappers/presenter were recorded with path/line/argument expression and per-source SHA256 in consumer-inventory.json and consumer-calls.csv. Categories: 3 dated portfolio reader calls,1 portfolio current-only presenter option,10 internal diagnostic/current-adapter calls and31 current/undated calls. This is a dated static source inventory, not installed-plugin/dynamic/runtime completeness proof. Read-only independent search across custom PHP/JS/TS/templates/JSON found no additional financial dynamic dispatch.

| Consumer | Date/source behavior | Action |
|---|---|---|
| admin/financial_portfolio.php summary, unpaid, overdue |Only external named readers passing nonempty $asOf; v5 strict raw GET period classification gates all financial reads/rollups |Existing v5 guard retained |
| financial.lib.php internal summary/statement/receipt/allocation/credit/commitment helpers |Forward dates for diagnostic current-source filters; mutable lifecycle/credit links cannot certify past balances |Arithmetic/source API unchanged; historical totals remain unsupported |
| balance.php, funding.php, project.php |Current/default arguments; project charges/invoices are current adapters |No invented date support or new request semantics added |
| board.php, admin_dashboard.php, admin/managed_funds.php, lib/operations.lib.php |Current summaries/statement snippets/unpaid/commitments |No date parameter forwarded; no additional guard needed for an absent historical mode |
| lib/mobile_api_data.lib.php / frozen M017 API |Current/default financial statement and current credit SQL; updated_since filters listed transactions after retrieval |No balance-asof request supported; do not alter its envelope or mistake list synchronization for historical closing |
| ui.lib.php managed-funds presenter |Current-only display accepts optional asof; its !empty check let falsey malformed dates show current values |Fixed strict optional-date boundary, described below |
| orders.php date_from/date_to |Filters current order list by order date, not a financial closing-balance request |Not changed |
| OfficeExpenseNativeTreasury and Office workspace |Separately owned native-bank current as_of plus selected-period movement/entry filters; no CPCC cutoff reader |Not changed; no historical completeness claim or sum with managed customer funds |

Current/undated classification describes arguments, not independent acceptance of each consumer's financial source or a complete customer balance. Legacy invoice-credit history remains separate from managed cash. Lower-level asOf functions remain diagnostic and can still be misused by a future/installed caller that invents historical presentation; this inventory does not globally block them.

## Reproduced gap and correction

The existing managed-funds presenter hid nonempty past/future dates but used !empty(options[asof]). A RED test reproduced asof='0' rendering current 100/40/60 fixture values. The same route existed for 0, 0.0, false and empty arrays. The presenter now first extracts the optional value, permits only missing/null, empty string or exact application today, and otherwise returns its existing localized current-only notice before table/numeric rendering. It adds no dependency, query, date arithmetic, metadata exposure or new layout. Null/empty keep the same documented current-default contract as v5; a caller must not sanitize an invalid raw request into empty before passing it. The actual portfolio raw GET guard already prevents that.

## Verification

Actual source presenter tests cover 54 EN/AR date cases across customer/project scopes: malformed falsey values, past/future/invalid calendar/HTML/array inputs hide tables and100/40/60 numeric outputs; unsupported translations remain escaped. Current/today/null/empty output is byte-identical to omitted-date output. Existing 28 rendered-position cases retain null/partial/currency/privacy/project/scoped-link behavior. The RED log and final-regression.json are local evidence.

All 29 existing focused commands pass, including v5's 38 coverage assertions,20 actual-native GETPOST assertions,16 page render/access scenarios,110 diagnostic cutoff checks and retired-connection checks. UI PHP and changed test pass `php -l`. Read-only reviewer inspected the source/test and found no blocker; it did not rerun tests. No browser or authenticated target acceptance is claimed for this small presenter-only change. Native financial math/services were not modified and no new native posting/server run was needed. Counts overlap and are not a unique combined total.

## Boundaries and handoff

Full historical statement reconstruction still requires verified invoice versions/lifecycle, credit application/unapplication, native mutation coverage, historical identities/currencies and a trustworthy opening/coverage interval. No new ledger, inferred dates, fake zero, blanket complete flag or current-row backfill was added.

Frozen v5 and every previous candidate remain unchanged. This separate v6 source candidate contains only the small presenter correction plus tests/report/source manifest update; the later Docs website linkage remains a separate artifact, not incorporated here. Target MariaDB/PHP8.2/authenticated UI/CSRF/mobile/phone acceptance and the existing backup isolation/current backup/actual MariaDB restore/supported pinned M121 apply gates remain pending. Prior Docker denial was not retried/bypassed. Source rollback can restore the one presenter file while retaining all financial/audit/schema/document state; no DB restore.

Parent should consolidate CONSUMER_WORKFLOW.md and progress.csv into the shared tracker/manual. No concurrent external/shared-record write was attempted.
