# M119 — Native operational storage and migration adapters, 2026-11-01

**Outcome:**143 credential-free CLI scenarios pass, zero failures. Native operational draft persistence now shares one uncertain-connection guard with a new explicit schema migration adapter. The adapter supplies read-only planning/readiness inspection, a pinned fresh-install path, exact rerun detection, partial/drift refusal and a data-preserving reversal plan. It is **not activated**, called by module initialization, or connected to a runtime factory.

Only new process-owned temporary databases and synthetic identities/files were used. No migration ran against an existing database, including the shared MAMP instance; no server, credential, access/security setting, browser attempt, financial posting, commit, push or deployment occurred. The original Finens interface and receipt HTTP code are unchanged.

## Implemented adapters

| File / API | Responsibility |
| --- | --- |
| `class/OfficeExpenseNativeConnection.php` | Injected native-handle guard, outer transaction ownership, acknowledged begin/commit/rollback and process-local/object-based quarantine shared across adapters |
| `class/OfficeExpenseNativeDraftStore.php` | Existing entity/creator/office-scoped immutable operational draft/audit port; delegates transaction safety to the shared guard; no financial/native expense write or schema install |
| `class/OfficeExpenseNativeSchemaMigration.php` | `plan()`, `assertReady()`, explicit `install($reviewedManifestHash)` and `reversalPlan()`; validates artifact bytes and complete owned-schema descriptors |
| `OfficeExpenseMysqliSchemaBackend` in that same file | Existing Dolibarr mysqli handle only; selected-database verification, information_schema inspection, advisory migration lock, explicit DDL and confirmed version-marker DML |
| `schema/003_schema_state.sql` | Inactive `cpo_oe_schema` version/checksum/status/date registry candidate |
| `schema/manifest.json` | Ordered immutable SQL checksums for registry, original draft table and original creation-audit table |

Paths above are relative to `custom/chinaplanetoperations/office_expenses/`. Original001/002 SQL bytes are unchanged. The local SQL ignore exception now exposes only the three authored candidate files, not other SQL dumps. Existing source changes and staged work are retained.

The runtime-facing adapters create no connection and read no real configuration or credentials. Their constructors do not run migrations. No installer route, CLI installer, admin menu, global factory or module-init hook was added. The native backend uses only the supplied native mysqli handle and prefix; SQLite support resides exclusively in the test fixture. A future operator entrypoint must separately enforce native administrator authorization and CSRF if web-based. The reviewed manifest hash pins an artifact; **it is not an access credential or permission grant**.

## Migration behavior and reversibility

Version: `m119_office_expenses_drafts_v1`. `plan()` validates the manifest and SQL checksums before inspecting the database, returns the seven planned CREATE statements and does not execute DDL/DML. Only the three known owned table names, reviewed column forms and unique-index definitions are accepted. Full inspection compares engine, ordered columns/types/nullability/auto-increment/defaults, explicit ASCII binary collations, unique-index names/order/full-column coverage and index kind. Integer display-width differences are normalized; unsupported differences fail closed.

`install($reviewedManifestHash)` is a trusted server-only method, not a user-facing install flow. It rejects a stale hash before locking or SQL execution. The backend verifies selected `DATABASE()` equals its declared native context, refuses caller transactions, and uses one prefix/database-scoped advisory lock. It refuses inheriting an already-owned/reentrant migration lock. The state is inspected again **inside** that lock.

| Observed state | Action |
| --- | --- |
| No owned tables | Explicit fresh install may proceed: create registry/index, commit `installing`, create source/audit tables/indexes, verify exact schema, commit/read back `ready`, verify final readiness |
| Exact tables and matching ready version/hash | Read-only rerun; no new DDL, source or marker rows |
| Existing source/audit tables with no verified registry | `unmarked`; stop without adopting or overwriting them |
| Matching installing marker / interrupted install | `partial`; stop for fresh-connection inspection/manual recovery; no automatic resume |
| Changed/missing columns/indexes/engine/collations, unknown version/hash/status | `drift`; stop, no ALTER/DROP/repair |

MySQL/MariaDB DDL is **not** wrapped in a transaction or assumed reversible. On failed/uncertain DDL, partial schema/evidence is retained and the handle is retired. Unknown begin/commit/rollback or ready-marker acknowledgement never reports success. A fresh physical connection must inspect the actual outcome; if the ready marker was durably committed before its response was lost, fresh readback proves installed state without duplicating tables/markers/sources.

Independent review found an advisory-lock lifecycle defect: a quarantined business-query guard initially prevented lock release. Shared two-connection lock tests reproduced it RED. The correction permits only a private bounded `RELEASE_LOCK` cleanup query on that same handle, even when business queries are retired; quarantine is never cleared. The original operation failure is preserved. Failure to confirm release throws an explicit error, retires the handle and requires **physical connection disposal and server lock-owner verification before retry**. The adapter does not open another connection, silently reset state or claim a persistent/pooled connection was physically closed. Real server lock/disconnect/pooling behavior remains an acceptance gate.

`reversalPlan()` returns **no destructive SQL**. Code rollback disables entrypoint factories/writes while preserving source/audit/registry and receipt metadata/blobs. Partial state requires reviewed recovery; do not drop tables, erase evidence or silently mark ready. A consistent backup/restore of source/audit/receipt metadata/blobs and authenticated restored-data verification is still required before deployment.

## Synthetic verification and integration

`php deploy/tests/test_m119_native_migration.php <native-htdocs>` — **17 scenarios pass**:

- Fresh read-only plan, explicit hash-pinned install, one completion marker and exact no-op rerun preserving existing synthetic sources.
- Stale artifact hash and changed SQL bytes rejected before database work.
- Existing unmarked/partial tables, missing unique index, extra column, wrong marker hash/status and incompatible schema refused without automatic overwrite/repair.
- Shared simulated server lock contention and locked recheck, inherited same-connection lock refusal, lost acquisition response, unconfirmed release visible to a second connection, and interrupted DDL.
- Lost durable ready-marker response recovered through a fresh connection with no new DDL; caller-uncommitted readiness and selected-database mismatch rejected.
- Reversal plan preserves data and executes no down SQL; caller transactions and unsupported native drivers refused.
- Integrated native User/source: original180.29 CNY with explicit synthetic7.25 estimate1307.10 EGP survives migration/rerun, attaches/downloads a synthetic receipt through the existing private test adapter, and rejects a different native entity/user. Native expense-report count stays zero. This does **not** implement a native receipt store or establish foreign-currency accounting.

The existing native-storage suite now has **22 passing cases**, adding shared guard quarantine to the prior21. Initial shared-guard RED21/1 became GREEN22/0. Migration RED initially reported the missing adapter; the strengthened lock fixture reproduced11 pass/4 fail and the database-context test16 pass/1 fail before corrections. All11 bounded credential-free suites were rerun after the final implementation:

| Suite | Passed |
| --- | ---: |
| Domain/money |16 |
| Employee interface |15 |
| Native draft library |13 |
| Native identity without authentication |6 |
| Native capability observations |5 |
| Native provenance |3 |
| Credential-free native workflow |10 |
| Native operational storage |22 |
| Private receipts |21 |
| Receipt interface |15 |
| Native migration |17 |
| **Total** |**143, zero failures** |

Six affected PHP files pass syntax checks. Full results, RED/GREEN, hashes, cleanup and review evidence are stored outside source at `/Users/mac/Documents/Codex/2026-11-01/task/m119-native-migration-verification.json`. Scoped backups are in `m119-native-migration-before/`. Synthetic fixtures remove their own databases/images/schema-copy directories at process exit. No existing database or test server is used.

Final independent read-only review found no material remaining issue after the lock corrections and independently reran17 migration/22 storage cases and4 scoped PHP lints. Original money/domain/bridge/local-draft/synthetic-writer and preceding receipt interface/route hashes remain unchanged. Staged/unstaged whitespace checks pass; no owned native/receipt/UI temporary roots or18849 listener remain and both Colima profiles remainStopped.

Test environment: PHP8.3.21 and read-only Dolibarr24.0.1 library source. The migration fixture uses genuine newly created owned SQLite DDL/DML and actual native DoliDB transactions, but **simulates** mysqli `information_schema`, selected database, connection IDs and server advisory locks. It maps reviewed ASCII identity to SQLite BINARY and maps auto-increment/engine syntax while preserving datetime NOT NULL; native SQLite's own DDL converter would weaken those constraints. These adaptations are explicit test-only behavior, not native MySQL/MariaDB or PHP7.4 execution proof.

## Remaining matching-database and release prerequisites

1. In an approved disposable environment matching the actual selected native/PHP/MariaDB/MySQL versions, verify metadata normalization/defaults/collations/indexes, server privileges, named-lock ownership/reentrancy/release, DDL implicit commits, interrupted installs, simultaneous installers, unique-key races and real commit/rollback/disconnect/persistent-pool failures. No such environment was started here. Existing host databases are excluded.
2. Confirm/review artifact version/checksums and the actual release base. Implement an authorized operator/factory integration that calls `assertReady()` and fails closed; do not wire `install()` into employee requests or module startup. Existing runtime draft factory is absent, so an unconfigured employee endpoint remains503. No physical schema installation is claimed.
3. Implement the **native private receipt metadata/blob adapter and reviewed source-object/ECM association**. `LocalReceiptStore` remains synthetic-only; this milestone deliberately supplies draft-schema migration rather than inventing a production receipt ownership/lifecycle mapping. Receipt policy, storage location/protection, backup/restore and orphan reconciliation still require configuration/decisions. No auto-delete policy is introduced.
4. Repeat actual native multipart/binary HTTP/session/permission/entity-switch and allowed bilingual desktop/mobile acceptance for the changed receipt routes, plus HTTPS/reverse-proxy/session policy. No server/browser was used and the prior file-preview denial was not retried/bypassed.
5. Financial posting remains disabled. Native foreign-currency representation, approved valuation/tax/category/accounts, finance roles, advances/transfers/returns/reimbursements and one native bank representation per cash movement retain the separate accounting/reconciliation gates in [the readiness report](M119_RELEASE_READINESS.md).

No platform action was denied, is awaiting approval or has an uncertain external execution outcome in this checkpoint. Injected uncertain outcomes belong only to isolated tests and were cleaned up; the earlier browser denial remains respected. Remaining engine/runtime/production actions were not attempted.
