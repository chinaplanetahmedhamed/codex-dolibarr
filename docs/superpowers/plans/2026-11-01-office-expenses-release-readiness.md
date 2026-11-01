# Office Expenses release-readiness review implementation plan

**Goal:** Review safe-release prerequisites and fix unconfirmed native operational-draft transactions without activating a runtime or financial writer.

**Architecture:** Keep native DoliDB transaction methods and immutable source/audit storage. Fail closed if begin/commit/rollback acknowledgement or transaction depth is inconsistent; retire that adapter and require a fresh connection/readback. Confirmed rollback permits scoped retry. Do not infer durable success by reading through an uncertain connection.

**Scope:** Explicit delegated local continuation; preserve existing work. PHP7.4-compatible source; execution PHP8.3.21/read-only Dolibarr24.0.1 libraries and owned SQLite fixtures. No server/browser workaround, production, posting, credentials/access changes, commit/push/deploy or destructive migration. Accounting review uses the canonical `docs/ACCOUNTING_RULES.md`; no accounting policy is changed.

**Files:** Modify `OfficeExpenseNativeDraftStore.php` and its existing test; add CLI-only transaction-fault fixture. Update current Mission/readmes/release-readiness report. Backup affected source outside the repository before editing. Existing Finens UI, money logic and native source stay unchanged.

## Task1 — Native transaction uncertainty

- [x] Back up both affected files and inspect native DoliDB begin/commit/rollback. Native rollback resets its counter even when SQL rollback fails.
- [x] Write seven failure/recovery cases with real source/audit SQL and injected transaction acknowledgement faults. Observe RED:15 pass/5 fail after correcting an independent SQLite reader lock, then20 pass/1 fail for same-handle reuse.
- [x] Require acknowledged begin at depth1 and commit at depth0. On failure, accept only one acknowledged owned outer rollback at depth0; otherwise retire the handle across draft-store instances for every read/write, without deleting data or retrying through it.
- [x] Verify GREEN21/0, including fresh-connection recovery of a durably committed source and exactly one audit, no success for uncommitted rows, bounded failed rollback, preserved caller transaction and healthy same-key retry after confirmed rollback.
- [x] Run the bounded credential-free Office Expenses suites:125/0. Three scoped PHP files pass independent lint. Final whitespace/cleanup checks are recorded with evidence. No prohibited HTTP/session/server suite was run.

## Task2 — Release prerequisites and accounting review

- [x] Document schema version/preflight/partial-state/engine/unique-key race/backup/data-preserving rollback prerequisites; no installer or destructive down migration.
- [x] Map employee/entity/office/native-right boundaries and explicit finance role/approval gaps, preserving staff-only data.
- [x] Reconcile classifications, immutable original currencies, estimate-versus-approved valuation, native foreign-field loss, tax/FX/fees and one bank representation per cash movement against canonical rules.
- [x] Record exact remaining runtime/visual/transport gates and decisions with pass/fail evidence and synthetic fixture cleanup. Independent read-only review found no material issue within the injected-handle contract; process-local/object-based and engine/runtime limitations remain documented. No release readiness is claimed from CLI tests alone.
