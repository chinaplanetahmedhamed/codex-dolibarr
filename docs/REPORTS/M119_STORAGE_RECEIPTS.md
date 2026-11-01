# M119 — Operational draft and private receipt storage, 2026-11-01

Historical storage checkpoint. [The subsequent receipt-interface report](M119_RECEIPT_INTERFACE.md) supersedes the absent-UI/HTTP-wiring statements below: optional own-draft receipt controls, multipart reader and private POST-download wiring now exist, with118 safe CLI cases passing. Real runtime activation, actual multipart/binary HTTP and new visual acceptance remain pending; no server was restarted.

Outcome: **103 safe local CLI scenarios pass**, including14 new native-draft storage/integration cases and21 private-receipt cases. No services, login environment, credentials, production connection, financial posting, schema installation, commit, push or deployment was performed. The existing native-login fix and Finens UI are unchanged.

## Gaps found and implemented scope

The employee page previously had only a synthetic `LocalDraftStore`, a required-but-absent runtime factory and an explicit415 rejection for file uploads. Existing operations/task document tables require their own foreign operation/task and authorization lifecycle. Linking office receipts to those objects would invent ownership. Native ECM metadata also needs a correctly authorized source object and file gateway; it is not itself an ownership check for a draft UUID.

Two independent ports are now implemented locally, with no activation:

| Deliverable | Behavior |
| --- | --- |
| `class/OfficeExpenseNativeDraftStore.php` | Existing draft-store contract through an injected native DoliDB handle, exact JSON strings, scoped retries, atomic source/creation audit and complete source/envelope/audit verification |
| `schema/001_drafts.sql`, `002_draft_audit.sql` | Candidate native operational staging tables/unique indexes, outside the module's auto-install `sql/` directory; no installer/factory enables them |
| `class/OfficeExpenseReceiptService.php` | Current native-right/user/entity/office authorization via `getOwn()` on every attach/list/read/audit/inspection; JPEG/PNG validation and normalized previews; no HTTP/upload path or native financial API |
| `deploy/tools/office_expenses/LocalReceiptStore.php` | Explicit synthetic-only private filesystem/SQLite adapter, immutable original/preview metadata, quotas, integrity checks, access-start audit and uncertain-transaction handling |
| `deploy/tests/test_m119_native_storage.php` |14 native-library/owned-SQLite scenarios, including actual native User rights and source→receipt access/revocation integration |
| `deploy/tests/test_m119_receipts.php` |21 persistent private-file scenarios with synthetic image bytes, isolation, retries, failure injection, quotas and cleanup |

Class/schema paths in the first four rows are under `custom/chinaplanetoperations/office_expenses/` unless otherwise qualified. A local `schema/.gitignore` exempts only these two authored DDL files from the repository's SQL-dump ignore rule, so the candidate definitions are visible for review without exposing other dumps. No existing table, financial object, application permission, office assignment or page/UI behavior was changed.

## Native operational draft persistence

The new adapter writes only `cpo_oe_draft` and its creation audit via the already supplied native connection/prefix. It neither connects to a database nor creates schema. UUID, native entity/creator, office, source date/text, original amount/currency, quote evidence and draft state round-trip exactly. JSON storage escapes Unicode, preserving the decoded original without depending on a connection's support for4-byte characters. Original money/rates remain decimal strings.

The idempotency key is unique per native entity/employee. SQL uses explicit ASCII binary collation for keys/UUIDs/offices so differently cased or numeric-leading-zero keys retain their identity on MySQL/MariaDB. SQLite tests remove only MySQL character-set/collation clauses and exercise its corresponding binary text comparison; actual target-engine DDL/collation/concurrency acceptance is still required.

Source and creation-audit inserts share an owned outer transaction. The store refuses caller transactions. Before success, it verifies the exact stored document, hash, entity/owner/office/UUID/status/timestamps and matching creation audit. Same-key/source retries return the existing source; changed intent conflicts. Missing or altered source/audit evidence blocks listing/read/retry. Native `ExpenseReport`, payments, bank and journal APIs are never called by this adapter. Its contract is operational draft persistence, not accounting recognition.

Tests prove persistence across a fresh database handle, original CNY180.29/quote7.25000000/EGP1307.10 estimate preservation, per-entity/employee key scope, physical uniqueness, rollback after failed/ignored audit, changed/scalar JSON/envelope detection, missing-schema fail-closed behavior and preservation of caller transactions. No claim of cryptographic authenticity against a database administrator is made by storing a hash beside its data.

## Private receipt behavior

Receipt IDs do not grant access. The service reloads the owned source through `OfficeExpenseDraftService::getOwn()` for every operation. Owner/entity/office come from that document, never receipt parameters. The receipt records a hash of the whole immutable source snapshot; changed draft evidence invalidates the association. The combined test uses actual native User/direct rights with the new persisted source, then confirms a freshly fetched user loses receipt access after a synthetic grant is revoked.

Accepted files are JPEG/PNG only. The service accepts bytes from a trusted caller, not a server path, URL or client-declared MIME. It checks safe UTF-8 names, extension/content agreement, compressed bytes and dimensions/pixels before GD decoding. Original bytes/hash remain exact, while a separately re-encoded preview drops metadata/appended content. Originals are stored as generated private `.original` blobs; neither originals nor previews are executed or served inline. Reads return an attachment/octet-stream descriptor and verified bytes, never an absolute path. There is no endpoint that accepts arbitrary paths or streams files publicly.

Default **synthetic test policy**, not approved company policy:5MiB original,10MiB normalized output,12million pixels,10000px per dimension,10 receipts and25MiB combined original+preview bytes per draft. Trusted constructors can set bounded policies. Filenames currently permit one extension and reject hidden/path/control/bidi/double-extension names. PDF/SVG/Office/archive/executable files are rejected; no regex-based PDF safety claim is introduced.

The local adapter requires a synthetic marker, private existing root outside/separate from the web root, and a SQLite database directly inside that owned root. It never uses a native ERP database or standard public document directory. Generated names and exclusive file creation prevent overwrite. Every path traversal rechecks the root, parents and symlinks, including replacement of a previously valid root. Blob reads verify expected size and SHA256; changed, missing or symlinked files fail closed and are not silently repaired during retry.

SQLite writes use PDO-owned transactions and acquire the writer lock before quota reads. This preserves PDO8.3 commit/rollback bookkeeping; manual `BEGIN IMMEDIATE` was found unsuitable and is not used. Quotas count both originals/previews and unresolved private orphan files, including their logical IDs. Inspection reports counts/bytes/orphans without returning paths or automatically deleting data.

Receipt metadata, creation audit and access-start events are checked on persistence. Access-audit readback verifies its inserted ID, scope, event, digest and timestamp before any bytes are returned. The event means **access started**, not successful delivery to the employee. Insert failure or silently deleted/altered audit evidence blocks access. Failed/false commit never reports upload success or releases bytes. Confirmed rollback removes only files created by the failed attempt. Lost commit response or unconfirmed rollback retains private evidence and requires verified retry/reconciliation; caller transactions remain untouched. A crash can still leave private orphan files because SQL and filesystem commits are separate. Such files remain nonpublic, consume quota and are never automatically swept.

## Verification and review

Current commands from source root; `<native-htdocs>` was `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1/htdocs`:

| Suite | Passed |
| --- | ---: |
| `php deploy/tests/test_m119_office_expenses.php` |16 |
| `php deploy/tests/test_m119_employee_ui.php` |15 |
| `php deploy/tests/test_m119_native_drafts.php <native-htdocs>` |13 |
| `php deploy/tests/test_m119_native_identity.php <native-htdocs> --without-auth` |6 |
| `php deploy/tests/test_m119_native_capabilities.php <native-htdocs>` |5 |
| `php deploy/tests/test_m119_native_provenance.php <native-htdocs>` |3 |
| `php deploy/tests/test_m119_native_workflow.php <native-htdocs>` |10 |
| `php deploy/tests/test_m119_native_storage.php <native-htdocs>` |14 |
| `php deploy/tests/test_m119_receipts.php` |21 |
| **Total** |**103,0 failures** |

Five new PHP files pass syntax checks; staged/unstaged whitespace checks pass. Existing native SQLite constructor/result-count adaptations remain documented library-test limitations. No MySQL/MariaDB daemon, native HTTP runner, password/session tests or synthetic HTTP server was started. The previous16 native HTTP cases remain historical acceptance of the unchanged entrypoint/test-factory boundary; they were not rerun and do not establish new native-store/receipt HTTP acceptance.

Independent read-only review covered both adapters, schema, service and tests. A found access-audit readback gap was reproduced RED with post-insert delete/hash-drift triggers, then fixed GREEN. Additional failure tests reproduced/fixed scalar source handling, root replacement, false/uncertain commits and unconfirmed rollback. All existing money/domain/bridge/local-draft/native-writer file hashes and the login entrypoint hash remain unchanged. Admin/Customer Center/mobile exposure and original Finens visuals are unchanged; there is no new UI to screenshot in this checkpoint.

Full output and post-run checks: `/Users/mac/Documents/Codex/2026-11-01/task/m119-storage-receipt-verification.json`. Synthetic test directories/databases/images are removed at process exit. Port18849 remains absent; m073/shipping-docs remainStopped. Existing Git index/work is preserved; new implementation/schema/tests are untracked and no commit/push occurred.

## Decisions and exact remaining gates

1. Verify/apply a reviewed versioned migration and wire a trusted runtime factory against the selected native release/engine, with actual office assignments/base currencies and readiness checks. The SQL files are inactive candidates; new persistence has only owned SQLite/native-API proof. Target-engine locking, unique-key races, encoding and commit-failure behavior remain unverified. No production migration is authorized here.
2. Implement a native/private receipt adapter, source-object/ECM mapping and authorized multipart/download routes. Current `index.php` still rejects files415 and has no receipt picker. Do not reuse `LocalReceiptStore` in a real runtime or add a local-path upload fallback. Native multipart/CSRF/permission/file-serving acceptance needs a freshly authorized disposable environment; the old approved environment was not restarted.
3. Confirm accepted receipt formats/limits, retention of originals and orphan reconciliation/cleanup policy; decide PDF support through an appropriate parser/scanner/native document workflow. A normalized preview is not a scan or rewrite of original evidence. Do not enable broad file types merely to satisfy an extension check.
4. Confirm finance-review visibility/lifecycle beyond employee-owned immutable drafts, then test it explicitly. No manager-wide receipt access, mutation/delete/replacement API or administrator override was invented.
5. Foreign-currency native representation, tax/category/accounts, advances/returns/transfers/reimbursements and bank/journal reconciliation still require their separate accounting decisions/tests. This storage work enables none of those financial writes or deployment steps.

No action was denied or left uncertain by the platform in this checkpoint. Remaining environment/HTTP/migration actions were not attempted under the explicit current restrictions. Rollback is removal/revert of these additive unwired files; no real runtime/schema/data exists to undo.
