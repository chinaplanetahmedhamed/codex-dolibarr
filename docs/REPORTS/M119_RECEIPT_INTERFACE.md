# M119 — Private receipt interface, 2026-11-01

The employee interface now supports **save draft → select own draft → attach receipt → list → download original/preview** through the existing private receipt service. **118 credential-free CLI scenarios pass, zero failures**, including15 new receipt-interface cases. This is local code and synthetic-flow acceptance; no real runtime factory, schema migration or accounting writer was enabled.

## Delivered behavior

- `OfficeExpenseEmployeeController` accepts an optional receipt service bound to the **identical authorized draft-service instance**. It checks current native user/entity/office ownership before reading uploaded bytes, and the receipt service checks the owned immutable source again for attach/list/read. CSRF and native stale-token handling precede upload processing. Forbidden/not-found responses clear private draft/receipt state.
- After an operational draft is saved, the register exposes an own-draft receipt link. The Arabic/English panel shows original money, a native multipart picker, retained retry key, attachment list and separate original/preview download buttons. Receipt errors preserve source context, including advances, without opening the expense dialog. Successful uploads use303 POST/Redirect/GET. Same-key retries return one receipt; changed bytes/name conflict.
- `OfficeExpenseHttpUploadSource` accepts only a real PHP `is_uploaded_file()` result with scalar single-file metadata, valid filename, matching actual size and bounded read. It never accepts an arbitrary local path or URL, trusts no client MIME/full-path value and contains no synthetic fallback. The injectable synthetic reader exists only in the CLI test fixture, which the entrypoint never loads.
- Downloads require POST, CSRF, owned draft ID, bound receipt ID and an allowed variant. The verified bytes pass through `OfficeExpenseReceiptHttpResponse` as attachment/octet-stream with private/no-store, nosniff, length and safely encoded UTF-8 filename headers. No public image URL, inline original, path parameter, delete or replacement endpoint was introduced. The access audit records access started, not confirmed delivery.
- `index.php` optionally obtains the service from trusted `cpoOfficeExpenseReceiptFactory($db,$user,$conf,$draftService)` bootstrap code and passes `$_FILES` to the controller. No such runtime factory is installed. Without it, receipt controls are hidden and receipt actions fail closed. The required draft factory is also absent, so an unconfigured runtime remains503. A normal draft-save upload remains415: save first, attach separately.
- Receipt-specific CSS extends the existing Finens white/indigo rounded panels and logical RTL/mobile layout. The pre-existing CSS is byte-equivalent after excluding only newly added receipt selector lines; existing JavaScript is unchanged. The new mobile controls use44px minimum targets. These are source/markup checks, not new measured browser-layout evidence.

Implementation is under `custom/chinaplanetoperations/office_expenses/`: new `class/OfficeExpenseReceiptHttp.php` and `class/OfficeExpenseReceiptPanel.php`, plus controller/page/service/entrypoint/CSS changes. `LocalReceiptStore.php` remains synthetic-only; typed quota/conflict exceptions provide safe distinct interface errors. The money/domain/bridge/local-draft/native-writer files remain hash-identical to their prior checkpoint.

## Conservative configurable defaults

| Setting | Current default / constraint |
| --- | --- |
| Formats | JPEG and PNG; trusted configuration may restrict to a nonempty subset of these formats. Existing verified receipts remain readable after tightening new-upload formats. |
| Original size |5MiB; effective picker/server cap is additionally bounded by existing PHP upload/post INI limits, reserving64KiB of POST envelope space. Disabled/invalid upload capability disables new uploads while preserving existing downloads. No INI setting is changed. |
| Normalized image size |10MiB |
| Dimensions |12million pixels total,10000px maximum per dimension; checked before GD decoding |
| Draft quota |10 receipts and25MiB combined originals/previews, including unresolved private orphan files |
| Retention |No automatic receipt deletion, replacement or orphan sweep. Original bytes/hash remain exact; a separately re-encoded preview strips metadata/appended content. |

These are bounded development defaults, not approved company retention/file policy. PDF/SVG/documents/archives/executables remain unsupported. Constructor limits are trusted server configuration, never form fields. The synthetic adapter's private0700 root/0600 blobs, integrity checks, scope/audit verification and uncertain-commit preservation remain as documented in [the storage report](M119_STORAGE_RECEIPTS.md). Confirmed rollback removes only a failed attempt's own uncommitted files; test shutdown removes only owned synthetic fixtures. Neither is a policy for deleting saved receipts.

## Verification evidence

Run from the isolated source root with PHP8.3.21 and the read-only local24.0.1 native library source:

| Suite | Passed |
| --- | ---: |
| Domain/money/draft store |16 |
| Existing employee interface |15 |
| Native draft library |13 |
| Native identity `--without-auth` |6 |
| Native currency/tax capability observations |5 |
| Native provenance |3 |
| Credential-free native workflow |10 |
| Native operational storage |14 |
| Private receipt storage |21 |
| New `php deploy/tests/test_m119_receipt_ui.php` |15 |
| **Total** |**118, zero failures** |

The15 new cases cover draft→attachment→scoped audited download, exact original money, retry/conflict, CSRF before byte reads, cross-employee/entity/office and disabled-user denial, forbidden actions/fields, size/quota/invalid files, advance error context, missing/mismatched services, receipt/source/path/variant tampering, Arabic/English escaping and language context, zero effective upload limit, configurable PNG-only uploads with older JPEG download, rejection of ordinary local files by the real HTTP reader, and header injection/UTF-8 encoding. The advance error-context regression was reproduced RED and fixed GREEN. Independent read-only code review found a PHP7.4 empty-unit parsing compatibility edge; it was corrected. PHP7.4 itself was not available for execution.

All10 changed/new PHP implementation/fixture/test files pass `php -l`. Full commands, suite output and limitations are recorded in `/Users/mac/Documents/Codex/2026-11-01/task/m119-receipt-interface-verification.json`. Native-library tests retain the documented SQLite constructor/result-count adaptations; they do not establish target MariaDB/MySQL behavior.

Post-run checks confirm no owned receipt/native-receipt/UI temporary directories remain, no18849 listener exists, and both existing Colima profiles remainStopped. All five protected money/domain/bridge/local-draft/native-writer hashes match their previous checkpoint. Staged and unstaged whitespace checks pass; existing index/work was preserved. Details are in `m119-receipt-interface-post-checks.json` alongside the main evidence file.

`php deploy/tools/office_expenses/generate_receipt_preview.php <absolute-output>` generates10 self-contained Arabic/English saved/empty/invalid/overview/advance HTML pages using synthetic files. All submissions/file controls are disabled; no credentials, database or private blobs are included. Current output is `/Users/mac/Documents/Codex/2026-11-01/task/m119-receipt-ui-preview/`. Manifest hashes, embedded JavaScript syntax, unique IDs, label/ARIA references, local navigation, disabled controls and preserved original CSS/JS pass offline checks. See `offline-checks.json` and `markup-checks.json` there.

## Exact blocked and unverified actions

**Browser action denied, not pending or uncertain:** opening Chrome at `file:///Users/mac/Documents/Codex/2026-11-01/task/m119-receipt-ui-preview/en.html#receipts` was rejected before navigation because browser URL policy permits only HTTP/HTTPS. No tab/navigation outcome was acquired and no workaround, alternate browser/renderer or server was attempted. Consequently there are **no verified new receipt desktop/mobile screenshots or browser upload/download results**. Offline structure/source checks above do not substitute for visual acceptance.

Actual multipart parsing, native full-bootstrap CSRF with files, binary HTTP headers/content/caching, concurrent requests and fresh-session rights revocation for these new routes remain **not attempted**. This task explicitly prohibited restarting a test server. The earlier16 native HTTP cases passed before this receipt wiring and their disposable environment was removed; they are historical login evidence, not acceptance of the changed receipt entrypoint. No accounts, services, credentials or production records were created/accessed in this checkpoint. No financial posting, commit, push or deployment occurred.

## Remaining gates and decisions

1. Review/apply the operational-draft migration and supply a trusted native draft/receipt adapter and private storage/source-object/ECM mapping for the selected ERP release. The provided receipt adapter is SQLite synthetic-only. Verify actual database engine locking, races, encoding, permissions and failure recovery before activation.
2. Use a separately authorized disposable loopback environment for real native multipart/binary/session acceptance, plus an allowed browser viewing route for Arabic/English desktop/mobile inspection. Do not reuse the removed test environment or treat its earlier approval as permission to restart it.
3. Confirm receipt size/formats, deployment PHP/GD memory capacity, retention/export and manually reviewed orphan-reconciliation policy. Decide whether PDF support is needed and which parser/scanner/document workflow would support it. Do not automatically delete saved receipts to recover quota.
4. Define/test finance-review visibility and lifecycle; current scope remains the owning employee with existing native rights and office/entity boundaries. No manager-wide grant or administrator override is implied.
5. Prove native foreign-currency representation, tax/accounts and advance/transfer/reimbursement/bank reconciliation before enabling any financial writer. This receipt work changes no accounting amount, ledger, settlement or posting gate.

Work remains isolated and uncommitted. Rollback is review/revert of these additive local source changes; there is no activated runtime or real receipt data to undo.
