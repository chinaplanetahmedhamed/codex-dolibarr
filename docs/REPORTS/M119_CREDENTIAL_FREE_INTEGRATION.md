# M119 — Credential-free integration checkpoint, 2026-11-01

Subsequent authorized milestone: [16 native HTTP cases pass](M119_NATIVE_HTTP_ACCEPTANCE.md), with direct native login303 corrected and the disposable runtime/accounts removed. This document retains the earlier credential-free run; full-native HTTP is no longer a local blocker, while target-version/engine/HTTPS and real persistence/accounting gates remain.

Outcome: **68 local scenarios pass**. The native-library workflow now has ten integrated acceptance cases using actual Dolibarr User/rights and expense-report methods, synthetic data and existing local PHP/SQLite only. This does not make the module ready for real expense entry or accounting.

## Changes

- `deploy/tests/fixtures/m119_native_identity.php` creates no password/hash, reset secret or API key by default. Its native source/schema loading and synthetic User/rights behavior remain intact. Currency/entity parameters allow a real fetched synthetic employee in a CNY entity as well as EGP. The existing credential branch requires explicit opt-in and was not executed in this checkpoint.
- `deploy/tests/test_m119_native_identity.php` defaults to credential-free execution. `--without-auth` is explicit; `--with-auth` selects the two historical password/session tests only when separately authorized. Six permission/controller cases run; two auth/session cases are skipped.
- `deploy/tests/test_m119_native_workflow.php` verifies ten complete library-level paths. The default identity fixture is checked for null password/hash/reset/API-key fields and no PHP session. A controller submission survives storage reopening and a fresh native User fetch; retry returns the same source and native draft, with one audit event. Changed amount/currency conflicts without duplication. Fresh rights revocation blocks retry. A different employee cannot read the first employee's draft and has a separately scoped submission key.
- CNY180.29 persists as an exact, unpaid native draft in synthetic entity2 with baseCNY and actual employee11. In baseEGP entity1, original CNY180.29, quote7.25000000 and estimateEGP1307.10 remain exact, while native creation is refused. Advances, returns, transfers and reimbursements cannot enter expense reports. Lost link evidence and changed native draft/paid state block automatic duplication. Lifecycle row changes are failure simulations in the disposable database, not approval/payment API calls.

No product/domain/money/store/writer logic or UI changed in this checkpoint. Synthetic grants and records exist only inside the test-owned temporary databases and are removed on process exit. No native runtime configuration, actual users/grants, original Finens data, ERP changes from other work, credentials or ledgers were accessed. No service, existing Colima profile, network/security setting, commit, push or deployment was changed.

## Current verification

Source root: isolated `office-expenses-erp`, branch `codex/m119-office-expenses-local`, base `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`.

Native source: `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1/htdocs`, whose version file declares24.0.1. Current host CLI PHP8.3.21; database fixture is SQLite, not MariaDB. Native SQLite constructor/result-count adaptations, copied-DDL syntax adjustment, inert hooks and per-call suppressed triggers remain test-only. No core source edit or full bootstrap occurs.

| Command from source root | Passed | Scope |
| --- | ---: | --- |
| `php deploy/tests/test_m119_office_expenses.php` | 16 | Exact money, intent, scopes, idempotency, persistence and rollback |
| `php deploy/tests/test_m119_employee_ui.php` | 15 | Controller/rendering and scoped form/filter behavior |
| `php deploy/tests/test_m119_native_drafts.php <native-htdocs>` | 13 | Actual native draft methods, safeguards and rollback |
| `php deploy/tests/test_m119_native_identity.php <native-htdocs> --without-auth` | 6 | Actual User/direct/group rights, validity/module gates, modeled token boundary |
| `php deploy/tests/test_m119_native_capabilities.php <native-htdocs>` | 5 | Observed foreign-field loss and illustrative tax arithmetic |
| `php deploy/tests/test_m119_native_provenance.php <native-htdocs>` | 3 | Exact evidence round-trip and rollback via native extrafields |
| `php deploy/tests/test_m119_native_workflow.php <native-htdocs>` | 10 | Credential-free integrated workflow described above |
| **Current total** | **68** | **Zero failures** |

The identity command without a flag was also verified to run six cases and skip the same two auth/session cases. Changed PHP syntax and staged/unstaged whitespace checks pass. Full output is preserved outside the repository at `../m119-credential-free-acceptance.json`.

Independent read-only code review found no material issue and confirmed the ten workflow and six credential-free identity cases. Hash checks confirm all five protected money/domain/bridge/store/writer files are unchanged from the prior checkpoint. Existing staged work remains intact; this checkpoint adds six further unstaged file changes and two new, untracked files. No Git history or index mutation was performed.

The previous 69-scenario milestone remains historical evidence. This run adds ten workflow cases and deliberately excludes two password/session cases and nine synthetic HTTP cases, hence68, not79. No new browser/UI acceptance was necessary because UI code is unchanged. All16 prepared full native HTTP cases remain **not_run** and are excluded from every passing count.

## Exact remaining path to real use

1. **Confirm the target environment.** Freshly verify intended Dolibarr/PHP/database versions, enabled expense module, entity base currencies, office assignments, expense categories and native rights in an authorized test context. Historical Dolibarr24.0.1/PHP8.2.7 (Oct27) and MariaDB10.6.27 (Oct2) receipts are not a freshly verified matching stack. No current production connection was attempted.
2. **Provide a separately owned complete test runtime.** Current source/PHP/SQLite capabilities cannot execute native login/main.inc against the intended engine. Existing Colima profiles remain stopped. Preparing an isolated runtime may require permission for a new service/VM/container start, missing versioned images/software, owned configuration/database/session directories, and temporary synthetic credentials. Those actions are prohibited in the current delegation and were not attempted. Use a new isolated environment rather than starting an old profile with unknown containers/mounts/port forwarding. Exact provisioning commands depend on the selected authorized runtime; there is no safe verified start command to run now.
3. **Run the16 native acceptance cases through unmodified bootstrap.** See `deploy/tests/fixtures/m119_native_acceptance_plan.json`: native login/logout, session invalidation/expiry, CSRF token rotation and multiple tabs, disabled/revoked access, identity/entity/office ownership, storage retries/failure, response headers and actual hosting-path bootstrap. Do not substitute the synthetic HTTP router or force a logged-in user. Credential creation, temporary fixture grants and service startup need explicit authorization for that disposable environment; no real permissions should be changed.
4. **Implement the runtime draft storage/factory and receipt path.** The employee entrypoint currently requires a trusted server-side service factory and fails503 when absent. The only supplied persistence adapters are test tools; never wire `LocalDraftStore` or `LocalNativeDraftWriter` into production. A reviewed native-backed draft/immutable link store, lifecycle and private receipt handling must be implemented/tested before staff can rely on saves. Reconcile this older isolated baseline with current source without overwriting existing work.
5. **Resolve accounting gates before financial integration.** Prove an approved native representation for foreign originals/quotes, tax/category/account treatment and rounding, then advance/return/reimbursement/transfer reconciliation including reuse of an existing cash movement. Native header/line insertion currently drops supplied foreign metadata despite schema columns. Do not enable financial posting based on these SQLite/library tests or create duplicate bank/journal entries. Deployment remains a separate unperformed step.

Current blocker state: **not attempted under the explicit task restrictions**, not an auto-review denial and not an uncertain action. Independent credential-free testing is complete. No request for production access or security changes is needed to review this checkpoint.
