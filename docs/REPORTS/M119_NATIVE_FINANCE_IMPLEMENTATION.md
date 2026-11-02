# M119-C01 native finance implementation

Status: CODE DEPLOYED AND VERIFIED ON BACKUP-ERP; live accounting activation and acceptance remain pending. Date: 2026-11-02. Owner: Agent007. Branch: `codex/m119-office-expenses-local`.

## Scope delivered in source

The existing internal Office Expenses workspace now supports explicit submit, approve, reject, post and reverse actions for expense reimbursement, funding, customer receipt, staff advance/return/settlement and gross/net payroll. Original native cash balances remain authoritative. Existing SOLD openings can receive reviewed GL representation without adding another bank movement.

The bridge uses native ExpenseReport, PaymentExpenseReport, Salary, PaymentSalary, Paiement, Account and BookKeeping objects in the same source/audit transaction. Original currency, explicit historical USD valuation, loan carrying value, realized FX and rounding remain separate. Balanced exact journal previews are bound to approval. Current identity, branch/record scope, source/config/evidence revisions and native state are rechecked before execution. Lost commit acknowledgement requires fresh physical readback and idempotent recovery.

Private evidence is now attached to workspace requests with scoped access, MIME/size/hash checks and audited downloads. Staff recipients may read manager-prepared records assigned to them without acquiring editing or financial authority. Financial execution authority is explicit and separate from CRUD. There are no global bank/GL grants added to branch managers.

Reports show actual posting and reversal dates, separate original currencies, chart totals, native movement/journal references and a protected detailed CSV. Existing original design, RTL controls, receipt print layout and branch-preserving navigation remain in place.

## Source and data boundaries

Application commit: `a6d1893b46db0cd9349533fdd15461d93ad32f9a`.

- Financial plan/settings/service/native bridge and evidence: [module classes](../../custom/chinaplanetofficeexpenses/class/).
- Native form/action route: [finance.php](../../custom/chinaplanetofficeexpenses/finance.php).
- Source/report route: [workspace.php](../../custom/chinaplanetofficeexpenses/workspace.php).
- Release gate: [finance-bridge.json](../../custom/chinaplanetofficeexpenses/finance-bridge.json).
- Matching-engine tests: [native finance suite](../../deploy/tests/test_m119_financial_mariadb_ci.php).
- Release implementation: [finance updater](../../deploy/releases/m119/finance_update_helper.py), [read-only native verifier](../../deploy/releases/m119/finance_native_verify.php), [installer](../../deploy/releases/m119/install_finance_helper.py).

No new schema migration. Financial settings, source metadata, immutable states and evidence metadata reuse existing workspace/audit tables. Actual payments write native financial objects only when separately configured and explicitly executed. No native accounting/module activation, chart creation, financial role expansion, historical conversion, user creation/password reset or business posting was performed in source preparation.

Admin ERP is affected. Customer Center and native Mobile App: not applicable; finance stays internal. No public customer access or unauthenticated evidence access is introduced.

## Verification

[Actions 41468853234](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41468853234) succeeded for the exact application. [Actions 41472562164](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41472562164) then passed the final release helper and the expanded native reversal suite on delivery commit `b76679d`; all suites, syntax and package checks passed with `FAILED_SUITES=0`.

- 41 workspace/native-storage cases and 10 exact financial-plan cases.
- 22 native finance cases against disposable official-schema PHP8.2.32/Dolibarr24.0.1/MariaDB10.6.27, including actual cash/GL, payroll, invoice allocation, FX, reversal, failure atomicity, lost commit and explicit manager/worker authority.
- Existing access, private-filesystem, document, report, receipt, native identity/provenance and storage regression suites passed.
- R11 helper tests: 11 passed in matching Linux CI, including the actual root-owned frozen chain, installer preparation and partial-install repair. Local macOS runs pass 8 and skip the 3 Linux-root-only cases.
- Local AR/EN desktop/phone finance preview checked earlier; synthetic fixtures are separate from authenticated runtime acceptance.

## Release and rollback

Complete package: 69 runtime files plus declaration. Artifact SHA256: `d648078b62e491082bdf7869f1a7a0a283306e44ac29d38fe27be5587983b1d9`. Manifest SHA256: `c5b2f2b27ab6235febdb0b8d294f7b3b546e24e20212864865d0b02ed660d369`.

Restricted SSH read on 2026-11-02 confirms expected host/UID and existing R10 root:root0755 helper SHA256 `e36452a4dc36d55657f742f86314c26acb8c47006d7d045b7b52148d520a2527`. This checks helper identity, not newly deployed finance behavior. The corrected R11 helper is installed and its exact root:root0755 hash independently verified as `48fdc68f0e07f2a9fa3c5875d13170527551f8b390b76bcbb0fca11533bd183f`. Separate restricted preflight, apply and verify succeeded. The exact 69-file runtime is deployed; both branches retain disabled business settings.

R11 requires the exact existing R10 files, complete DB and source backups, pinned package, root-owned atomic file changes and read-only native fingerprints. Recovery retains the complete compatible parser and replaces the release gate with canonical false JSON, then waits for module financial work to drain. All business settings, audit, evidence, cash and GL records remain. Do not use R10 rollback or restore an old DB after financial data exists.

## Remaining business acceptance

The exact native Ahmed identity, approved chart and account/journal mappings, gross expense tax treatment, historical non-USD opening valuations and any starting staff loans remain unresolved. The company was last observed as HK/USD with double-entry accounting disabled. Do not guess these values or claim readiness for live payments.

After reviewed setup and an explicitly bounded acceptance transaction: verify native bank/payment/GL reconciliation, staff logins, branch/own visibility and failure/reversal workflow in the authenticated backup runtime. Physical phone/printer behavior remains unverified. Production is outside the approved scope and has not been accessed or changed.


## Verified deployment readback

Deployment `3a0fe3abfcbc464bbcbc3d4021d318d8` is active at `/var/lib/chinaplanetos-backup-erp-deploy/m119-finance-r11`. Full database backup `database-before.sql.gz` was independently rehashed: `17593e3c29f6d8240c3b3d6686ed12d8308ff1fb8fcc7db6dd56e0b5a6bd7071`. Exact predecessor files are retained as private `source-before-*` backups. The native financial/access/source fingerprint remained `2ba388ff7a6c478a962fa8829ffaaa2a911b4958a4885fd30d4c8edfe0e27e01` across apply.

The first installer preflight stopped before application mutation because native schema readiness requires no open transaction. The corrected verifier performs schema readiness before its read-only snapshot. The original worker was preserved and the installer resumed only its exact two-file, application-untouched preparation; no audit/state was erased. Final helper/installer checks and separate native verification passed.

Authenticated browser acceptance is still blocked by the expired native session. Ahmed was asked to sign in in the shared browser; no password was requested, extracted or reset. The current evidence establishes deployed source, read-only native invariants, health and isolated synthetic finance behavior. It does not establish approved business mappings, a live native financial acceptance transaction, or staff credential-login acceptance.
