# Office expenses local foundation implementation plan

> For agentic workers: execute inline with superpowers:executing-plans; preserve the source checkout and use the isolated worktree.

**Goal:** Build a safe, testable local draft foundation for the simple Egypt/China office-expenses experience.

**Architecture:** Add isolated domain classes under the existing operations module. Keep synthetic persistence and preview tools under `deploy/tools/office_expenses`; no native writes or public routes.

**Tech stack:** PHP 7.4-compatible syntax, BCMath, PDO SQLite for synthetic workbench only, static HTML/CSS preview.

**Spec:** `docs/MISSIONS/M119_OFFICE_EXPENSES_LOCAL_FOUNDATION.md`.

## Global constraints

- Synthetic data only; no production, credentials, native schema/state or access changes.
- Decimal strings only; configurable exponents; preserve originals and quote provenance.
- Dolibarr is accounting source of truth; all posting disabled.
- Source and reference histories remain separate; changes stay local.

## Review focus

- Malformed, excessive-precision or float money is rejected, including boundary amounts and FX half-up ties.
- Branch currency must not be mistaken for entity base currency; missing/ambiguous quotes fail.
- External/admin and cross-office/cross-entity forgery must fail before persistence.
- Retry keys must neither duplicate records nor silently accept changed intent; audit failure rolls back.
- Mixed-currency settlement is rejected; reimbursement/advances never add expense twice.

### Task 1: Exact amount, conversion and settlement rules

Files: `custom/chinaplanetoperations/office_expenses/class/OfficeExpenseMoney.php`; `deploy/tests/test_m119_office_expenses.php`.
Interfaces: `OfficeExpenseMoney::amount($value, $currency): string`, `convert($value, $currency, $base, array $quote): array`, `settlement(array $advance, array $claims, array $returns): array`.

- [x] Write failing tests for EGP/CNY/USD precision, additional configured JPY, float/negative/overflow rejection, quote provenance/direction/rounding and 1000−700−300 settlement.
- [x] Run `php deploy/tests/test_m119_office_expenses.php`; verify missing implementation failure.
- [x] Implement exact BCMath rules with explicit validation.
- [x] Run the same tests; require all pass.

### Task 2: Scoped immutable drafts and synthetic persistence

Files: `office_expenses/class/OfficeExpenseDraftService.php`, `deploy/tools/office_expenses/LocalDraftStore.php`, same tests.
Interfaces: service `submit(array $payload, $key): array`; local store `save($entity, $owner, $key, array $draft): array`, `listOwn($entity, $owner): array`; service `post(array $draft): void` always rejects.

- [x] Add failing tests for internal native rights, office scoping, forbidden context fields, date/source requirements, all five kinds, scoped retries/conflicts, persistence after reopen, atomic audit rollback and disabled posting.
- [x] Implement narrow service and SQLite-only workbench adapter; no native adapter or route.
- [x] Run full M119 test file and PHP syntax checks.

### Task 3: Bilingual synthetic preview and handoff

Files: `deploy/tools/office_expenses/generate_preview.php`, `preview.css`, `README.md`, M119 report.

- [x] Generate static Arabic/English screens using real domain output and clearly labelled synthetic records; no live endpoints or external resources.
- [x] Verify both languages on desktop and phone, escaping, readable per-currency amounts and local-only posting notice.
- [x] Run M119 tests, changed PHP syntax checks and `git diff --check`; review all changes.
- [x] Open multi-folder workspace in a new VS Code window; report paths, evidence and pending native integration.

### Task 4: Resumed local native draft proof

Authorized by the resumed local task; no production activation or accounting posting.

Files: `office_expenses/class/OfficeExpenseNativeDraftBridge.php`, `deploy/tools/office_expenses/LocalNativeDraftWriter.php`, `deploy/tests/test_m119_native_drafts.php`, `deploy/tests/fixtures/m119_native_bootstrap.php`; extend scoped draft lookup.

- [x] Write and observe missing native bridge failure.
- [x] Reload immutable owned draft IDs; accept expense claims only; preserve native-right/entity/office boundary.
- [x] Execute actual native ExpenseReport/Line creation and price recalculation in disposable synthetic SQLite; refuse foreign currency and unknown tax treatment.
- [x] Verify atomically idempotent native links, native/marker rollback, readback/date/amount mismatch, conflict, owner/module/entity and nested-transaction cases.
- [x] Record native SQLite fixture adaptations without editing core or reading runtime configuration.
- [x] Run complete M119 checks, independent review and preserve local checkpoint.

### Task 5: Employee draft interface milestone

- [x] Add the native-bootstrap staff entrypoint and trusted same-user/entity service factory contract; no production factory or permission activation.
- [x] Implement own-draft form/list with explicit currency, advance/return distinctions, exact quote provenance, CSRF, scoped reads and retry protection.
- [x] Verify 12 controller and 9 HTTP scenarios with separate synthetic sessions; reproduce and fix native stale-token POST sanitization.
- [x] Submit a synthetic draft in a real browser and capture Arabic/English desktop/phone layouts; source currency amount remains700.10 EGP.
- [x] Retain original 29 passing domain/native scenarios; total50. Preserve isolated staged work and report integration limits.

### Task 6: Native identity and capability evidence

- [x] Test actual native credential/User/right methods and PHP session persistence against disposable identity tables; record full-bootstrap proof boundary.
- [x] Reproduce and reject disabled/expired/future-valid native identities in the domain service and synthetic writer.
- [x] Observe native multicurrency field loss and sample VAT-inclusive persistence without enabling either adapter; total62 passing scenarios.

### Task 7: Runtime readiness and native provenance representation

- [x] Inspect local source version and available engine/runtime without reading real config or contacting production.
- [ ] Full native main.inc HTTP acceptance: blocked by absent local MySQL/MariaDB and unavailable Docker daemon (socket absent, Colima stopped); release-engine match also unverified.
- [x] Verify native extrafield APIs can round-trip exact source/quote metadata in owned synthetic tables, and document line/atomicity boundaries.
- [x] Write proposed native representation, tax/reconciliation invariants, synthetic worked examples and owner/accountant decisions still required; keep writer gates unchanged.
- [x] Run all65 scenarios, 20 PHP syntax checks and independent review; preserve staged milestone with the complete-runtime gate still pending.

### Task 8: Historical release evidence and native acceptance preparation

- [x] Incorporate parent-supplied historical receipts without asserting current parity; explicitly exclude older README/M116 environments.
- [x] Verify local PHP8.3.21 differs from receipt8.2.7; inspect native CSRF/session/logout source without executing bootstrap.
- [x] Add actual native token-helper/controller distinction coverage; identity suite8 scenarios, combined66.
- [x] Prepare16 native HTTP cases as inert JSON, all not_run; preserve source-derived expectations and full-runtime/approval gates.
