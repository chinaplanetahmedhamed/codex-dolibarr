# Synthetic office-expenses workbench

These tools are local development/test adapters, not production ERP services. They do not expose routes or install native schemas, modules, users or permissions.

Current credential-free release review:125 passing CLI scenarios, including21 native operational-draft storage cases. New tests inject begin/commit/rollback acknowledgement failures around real owned SQLite SQL; uncertain handles are refused across draft-store instances and recovery requires a fresh connection. See `docs/REPORTS/M119_RELEASE_READINESS.md` for exact migration/role/accounting gates. No service, credentials, real DB, financial posting or browser workaround is included in these tests.

Latest native migration checkpoint:143 passing CLI cases. `php deploy/tests/test_m119_native_migration.php <native-htdocs>` adds17 new-table-only migration cases; the storage suite has22. Its fixture invokes the production native backend through a CLI-only shim with real owned SQLite source/marker SQL and simulated mysqli metadata/advisory locks. ASCII BINARY, engine, auto-increment and datetime constraints are mapped explicitly; no matching MariaDB/MySQL proof is implied. It never connects to an existing DB or starts a service. Native receipt adapter/factory and real-engine acceptance remain pending; see `docs/REPORTS/M119_NATIVE_STORAGE_MIGRATION.md`.

Private-receipt workbench: `LocalReceiptStore.php` requires an owned marked private root outside the web root and SQLite metadata within it. It preserves original/normalized image files, scoped retry keys, quota/orphan accounting, checksum verification and access-start auditing. It must be used through `OfficeExpenseReceiptService`, not called from untrusted request parameters. It remains synthetic-only and no real runtime factory uses it. `php deploy/tests/test_m119_receipts.php` runs21 credential-free synthetic scenarios and removes its generated files. The native operational draft adapter has14 additional cases: `php deploy/tests/test_m119_native_storage.php <native-htdocs>`. Optional employee receipt UI wiring now has15 additional cases in `php deploy/tests/test_m119_receipt_ui.php`; the full safe CLI set is118. See `docs/REPORTS/M119_RECEIPT_INTERFACE.md` for defaults, wiring and exact transport/visual/migration gates, and the earlier storage report for integrity/failure details.

Separate explicitly authorized full-native HTTP acceptance has now passed16 cases on a disposable local24.0.1/PHP8.3.21/MySQL5.7.39 runtime, including direct-login303 and verified removal of all owned services/accounts/data. See `docs/REPORTS/M119_NATIVE_HTTP_ACCEPTANCE.md` for evidence, runner paths and remaining production-storage/target-parity/accounting gates. Do not infer authorization to start services or create accounts from these default credential-free tool commands.

From the source root:

```sh
php deploy/tests/test_m119_office_expenses.php
php deploy/tests/test_m119_employee_ui.php
php deploy/tests/test_m119_native_drafts.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_native_identity.php /absolute/path/to/dolibarr/htdocs --without-auth
php deploy/tests/test_m119_native_capabilities.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_native_provenance.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_native_workflow.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_native_storage.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_receipts.php
php deploy/tests/test_m119_receipt_ui.php
php deploy/tools/office_expenses/generate_preview.php /tmp/m119-office-preview
php deploy/tools/office_expenses/generate_preview.php /tmp/m119-office-boundaries --mobile-boundaries
php deploy/tools/office_expenses/generate_receipt_preview.php /tmp/m119-receipt-preview
```

The first test uses in-memory/temporary synthetic SQLite. The native test loads only library and schema files from the explicit native source directory: it never loads `main.inc.php`, `conf.php`, production credentials or business rows. It creates a randomly named private temporary directory, two native table definitions, a minimal synthetic category dictionary and a local draft-link table, then removes its generated databases.

`LocalNativeDraftWriter` is blocked unless the M119 synthetic bootstrap marker, SQLite driver, database-name prefix and owned temporary database path all match. It calls actual native `ExpenseReport::create`, `ExpenseReportLine::insert` and `CommonObject::update_price`. Native transaction semantics and expense calculation/persistence are exercised. External hooks are replaced by an inert fixture and triggers suppressed per call inside this synthetic harness only. No bank/payment/approval/journal APIs are called.

Local 24.0.1's SQLite driver requires two test-only adaptations: its constructor references absent dbIF/dbMONTH helper methods, and `num_rows` assumes a PDO queryString property on SQLite3Result. The bootstrap subclass supplies its own fresh SQLite connection and counts/rewinds actual SELECT rows. Native ERP/core source is untouched. The fixture also removes MySQL's timestamp `ON UPDATE` syntax from copied table definitions. This evidence is not MariaDB/MySQL or installed-runtime acceptance.

Supported native proof: zero-tax claims matching the synthetic entity base currency (EGP and CNY tested separately). Foreign-currency native drafts, unknown tax treatment, advances, transfers, returns and reimbursements are rejected. The native writer is not wired into the ERP and cannot be used as a production adapter. Native readback is checked before commit and on retry: entity, owner, unpaid draft state, dates, source/purpose, category, line count and decimal totals. Marker and native writes share one outer transaction.

The static preview now reuses the Finens-restored employee renderer for Arabic/English expenses, overview and advances pages, with EGP as an explicitly synthetic base and separate original EGP/CNY amounts. Dialogs can be inspected, but save/filter submission is disabled. It is not a live expense-submission UI. Open generated `ar.html` or `en.html`; optional HTTP serving must be limited to localhost. The older read-only `OfficeExpensePreview` remains solely as a tested arithmetic/formatting helper, not the generated visual authority.

The identity fixture defaults to credential-free synthetic identities: password, password hash, reset secret and API key columns remain null. The identity suite defaults to six native User/permission/controller cases without creating a PHP session; `--without-auth` explicitly selects this same safe mode. The two historical password/session cases require `--with-auth` and the fixture's explicit credential opt-in; do not run that mode where creating test credentials is not authorized. Neither mode executes real `main.inc.php`; the separate historical native HTTP runner is described above and has not been rerun for new receipt routes. Its connection-local `GREATEST` helper supports native user fetch on the SQLite fixture.

The receipt preview generator emits10 self-contained Arabic/English saved/empty/invalid/overview/advance pages with embedded local assets and disabled submissions. Its synthetic upload reader is a CLI-only fixture, never a real-upload fallback. The runtime reader requires `is_uploaded_file()`. Receipt original/preview downloads are POST-only, authorized and audited; they are attachment/octet-stream responses, not public image URLs. No receipt deletion/automatic orphan cleanup exists. Browser file-URL preview was denied in this checkpoint; no alternate renderer or server was used. These generated files and structural checks do not establish visual or multipart/binary HTTP acceptance.

The credential-free workflow suite joins actual native User rights, controller, durable local draft storage and the synthetic native writer. Ten scenarios cover reopen/retry idempotency with one audit/native draft, changed-intent conflicts, fresh rights revocation, employee isolation, CNY base-entity persistence, original CNY/FX preservation in an EGP entity with native creation blocked, non-expense movement gates, orphan-link duplication prevention and native lifecycle drift. It opens no sessions, server or real configuration. See `docs/REPORTS/M119_CREDENTIAL_FREE_INTEGRATION.md` for the current 68-scenario run and remaining real-runtime gates.

The capability suite observes native header/line multicurrency field loss and sample VAT-inclusive arithmetic/persistence without enabling either feature in the adapter. Sample rates are not approved country tax mappings. See `docs/REPORTS/M119_NATIVE_IDENTITY_CAPABILITIES.md` for evidence, limitations and gates.

The provenance suite creates header/detail text extrafields only in the owned temporary database, through native APIs. It proves exact source/quote metadata round-trip, one header-evidence rollback path and the need to explicitly save/load detail extrafields. It neither enables conversion nor establishes immutable/approved native accounting. `docs/REPORTS/M119_NATIVE_REPRESENTATION_DESIGN.md` records the runtime blocker and proposed reconciliation design.

`--mobile-boundaries` generates separate synthetic maximum/minimum/long/mixed-currency pages plus Arabic/English empty pages. It creates no persistent database or native records. Mobile money stays visible while other columns scroll; local styles/scripts carry content hashes to avoid stale preview assets. See `docs/REPORTS/M119_MOBILE_MONEY_READABILITY.md` for the measured viewport/scroll evidence.
