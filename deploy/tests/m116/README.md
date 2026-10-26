# M116 isolated test installation and acceptance

This branch is a development trial, not a live release. Base: `113ddc6855915061d14acc7ab61ea33fd31742f9`. PHP implementation targets the module's existing PHP 7.4+/Dolibarr 23+ boundary; executed tests used PHP 8.3.21. BCMath, mbstring and ZipArchive are required. The fixed base is USD; original currencies default to USD,CNY,EGP and can be configured through `CPO_ACCOUNT_CURRENCIES`. No native payment/bank/invoice objects are written or imported. No portal access.

## Automated tests

From repository root: `deploy/tests/m116/run.sh`.

Tests execute the real account classes, existing customer/project SQL scoping and task authorizer against private SQLite fixtures. The native Project lookup is a synthetic API stand-in. `FOR UPDATE` is removed by the test-only adapter; `BEGIN IMMEDIATE` serializes fixture writers. The two-process tests prove one competing post conflicts and identical commands replay once in SQLite. They do **not** certify MySQL row-lock semantics or real Dolibarr login/CSRF/module registration.

The audit-scope regression rejects inaccessible former project allocations even when current allocations are fully visible. The audit screen displays the latest 200 events and does not weaken historical scope checks.

The browser test uses a deliberately synthetic localhost harness, not a replacement ERP bootstrap. Start a fresh temporary DB (choose a new unique filename per run):

```
M116_UI_FIXTURE=1 M116_FIXTURE_DB=/tmp/m116-example-unique.sqlite php -S 127.0.0.1:8765 deploy/tests/m116/router.php
```

Negative HTTP checks can be run against the same saved synthetic fixture with `M116_HTTP_FIXTURE=1 M116_FIXTURE_DB=/tmp/m116-example-unique.sqlite python3 deploy/tests/m116/http-negative.py`.

In another terminal, with Playwright installed outside source or in a private test dependency directory:

```
M116_PLAYWRIGHT=/absolute/path/to/playwright M116_BROWSER_OUTPUT=/tmp/m116-browser-output node deploy/tests/m116/browser.cjs
```

The harness binds localhost, stores only synthetic data and tests the real account PHP endpoints/UI. It simulates the native bootstrap/token rather than testing Dolibarr session security. Never install the harness into Dolibarr or expose it beyond localhost. Screenshot capture is optional via `M116_CAPTURE=1`; functional tests do not depend on screenshots. Physical phone/native WebView acceptance is outstanding. The test currently uses installed macOS Chrome; adjust its executable path for another test host.

## Dedicated native test installation (not executed here)

A separate test administrator must provision a throwaway Dolibarr/MySQL database named `m116_test_<suffix>` and the module's normal dependencies. Do not reuse the shared M097 database or any live/staging customer data. Check the actual core version and enabled module state first. Copy the isolated module source into that test installation only.

`custom/chinaplanetoperations/sql/llx_cpo_account.sql` contains five additive tables. For an already initialized module, the guarded CLI installer can install these into the dedicated DB:

```
M116_ISOLATED_TEST=1 M116_EXPECTED_DATABASE=m116_test_example php deploy/tools/m116_install_trial.php /absolute/test/htdocs
```

It rejects a missing environment guard, non-CLI use, unexpected DB naming, or actual/expected database mismatch. DDL is not transactional; on a partial install stop and inspect the dedicated test DB. The installer does not grant rights, enable the feature or refresh the module's rights metadata. Standard module initialization also loads SQL from the module SQL directory; use one installation route, not both. Native setup must preserve module dependencies/settings and verify the added rights metadata is registered. No existing accounts/rights were modified by this task.

`CPO_ACCOUNT_STATEMENTS_ENABLED` gates all endpoints and navigation; its absent/default state is disabled. Rights definitions 500580–500584 are `account_read`, `account_edit`, `account_post`, `account_reallocate`, `account_export`, default-off. Finance grants and local native setup were not performed under this task's no-grants restriction. Use a separately authorized native test identity with the required rights for acceptance; do not widen a real user's access as a workaround.

Native positive/negative tests still required: rights/entity/customer/project/card scopes; private project aggregation; live/moved/deleted cards; POST/CSRF/token expiry; schema prefix/install compatibility; original-currency and USD rounding; browser retry after dropped connection; two actual MySQL sessions racing post/reallocate; native account/Boards/card navigation and screen/XLSX/print totals; Arabic/English phone; feature-off behavior and pre-existing Board regressions.

## Operational behavior

Only posted manual receipts/spends contribute to balances. Drafts remain separately visible. Original amount and manual USD-per-original-unit FX/date/source are frozen at post. The original amount is split into positive allocation magnitudes that conserve both original and base totals. Last-line rounding residual is explicit; too-small allocations whose residual would go negative must be combined. Reallocation creates a new audited batch within the same customer, without creating a cash movement. Posted monetary content never changes; correction is a dated opposite-direction reversal plus a new draft. Reversal copies its historical allocation batch. Reversed/reversal records cannot be reallocated or reversed again.

Historical customer/project attribution does not follow a moved card. A deleted operational card retains its ledger under finance + native customer/project rights, without returning current task fields; new allocations require a live readable card and consistent native bridge. The complete customer statement fails if any included project is inaccessible. Partial project/card views return only visible allocations, mask full source amounts and disable whole-entry mutation controls. The server also checks every old allocation before edits/reallocation, so a partial view cannot overwrite hidden lines.

Custom columns are text-only, stable numeric IDs, max 12 per customer, values max 500 chars. User text is escaped in HTML and emitted as XLSX string cells (never formulas). Values exceeding Excel's 15 significant numeric digits are preserved as exact decimal text. Exports include posted rows, original/rate metadata, running balances, custom fields and allocation breakdown; drafts excluded. PDF means the authorized Print / Save PDF view with print CSS, not a server-generated native PDF attachment.

## Rollback

Disable the feature flag in the dedicated test environment, restore the four modified existing integration files from the base and remove the added runtime files only after preserving any test statement/audit data. Do not drop tables, restore an old whole database or rewrite native accounting. New data tables remain for recovery. Turning off the feature removes navigation and rejects endpoints without touching Boards, attachments, grants or financial rows. For the source checkout alone, abandon the isolated trial branch; original owner checkout is untouched. No production rollback action is authorized.
