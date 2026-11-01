# Office expenses — local foundation

Administrator pilot preparation now supplies `OfficeExpensePilotConfiguration.php` and an optional native storage binding in `index.php`. Activation requires an explicitly provisioned root-owned private JSON configuration at the fixed server path, matching native identity/entity/currency/module state and ready pinned schemas/private receipt root. It grants no rights or automatic accounting posts. See `docs/REPORTS/M119_STAGING_ACTIONS_PREPARATION.md` for173 local cases, the unused tested BCMath image and pending real-MariaDB/Linux/transport deployment gates. Earlier unwired-factory statements below describe historical checkpoints; no pilot configuration is installed yet.

Current 2026-11-01 release status: see `docs/REPORTS/M119_STAGING_RELEASE_PREFLIGHT.md`. Native draft/receipt storage, independent pinned receipt migration and a strict native factory builder now exist, with166 passing synthetic CLI cases. They remain unwired and uninstalled. Ahmed approved a current-administrator-only Egypt/China pilot on `backup-erp`; its PHP8.2.32 runtime lacks required BCMath, so activation is stopped at preflight. No automatic financial posting is enabled. Older checkpoint descriptions below retain their historical limits and are superseded by that report where noted.

This additive foundation belongs to the existing ChinaPlanetOS operations module. It is not installed or activated. Its staff entrypoint uses native bootstrap/authentication and requires a trusted persistence factory; no production factory is provided, so an unconfigured runtime returns503. It does not run the Finens application or alter existing module permissions/schema. The original application remains a separate sanitized reference.

- `OfficeExpenseMoney.php`: configurable currency precision, exact decimal strings, explicit FX provenance and same-currency settlement.
- `OfficeExpenseDraftService.php`: internal native-right/office/entity/creator boundary and immutable movement drafts. Every posting attempt fails closed.
- `OfficeExpenseNativeDraftBridge.php`: reloads an owned immutable draft before handing an expense claim to an injected native writer; accepts no browser-supplied write payload.
- `OfficeExpenseNativeDraftStore.php`: unwired native-database operational draft adapter with atomic source/audit and integrity-checked retries. Its candidate `schema/` files are outside module auto-install SQL and were not installed in a runtime.
- `OfficeExpenseNativeConnection.php` and `OfficeExpenseNativeSchemaMigration.php`: shared uncertain-handle guard and explicit pinned draft-schema fresh-install/readiness/reversal adapters. The mysqli backend requires an existing native context; no installer, startup hook or runtime factory invokes it. Partial/unmarked/drifted schema is refused. See the native migration report for the matching-engine gate.
- `OfficeExpenseReceiptService.php`: owner/entity/office-scoped JPEG/PNG validation, private originals/normalized previews and audited read contracts. Optional `OfficeExpenseReceiptHttp.php`/`OfficeExpenseReceiptPanel.php` controller wiring supplies real-upload validation and private POST downloads; no runtime receipt factory is installed. `OfficeExpenseNativeReceiptStore.php` provides the native adapter and `OfficeExpenseNativeStorageFactory.php` a strict unwired context binder. `deploy/tools/office_expenses/LocalReceiptStore.php` remains synthetic-only.
- `deploy/tools/office_expenses/LocalDraftStore.php`: synthetic SQLite workbench only; atomic draft/audit persistence and scoped idempotency. This is not a native database adapter.
- `deploy/tools/office_expenses/generate_preview.php`: local static Arabic/English artifact with synthetic data and no submissions, credentials, network resources or live links.
- `deploy/tools/office_expenses/LocalNativeDraftWriter.php`: actual native expense-object persistence restricted to the disposable synthetic test harness; not a production writer.

Run from the repository root (PHP 7.4+ with BCMath and PDO SQLite):

```sh
php deploy/tests/test_m119_office_expenses.php
php deploy/tests/test_m119_native_drafts.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_employee_ui.php
php deploy/tests/test_m119_native_storage.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_native_migration.php /absolute/path/to/dolibarr/htdocs
php deploy/tests/test_m119_receipts.php
php deploy/tests/test_m119_receipt_ui.php
python3 deploy/tests/test_m119_employee_http.py
php deploy/tools/office_expenses/generate_preview.php /tmp/m119-office-preview
```

Open `ar.html` or `en.html` in the output directory. Preview estimates use explicit illustrative quotes and a synthetic USD entity base. They do not represent actual office/entity configuration or current exchange rates. Supported office currencies and base currency must come from trusted configuration, separately. Never infer entity base currency from an office's country.

Native local proof now covers same-base-currency EGP/CNY unpaid drafts, immutable links and rollback through actual Dolibarr 24.0.1 expense objects. Foreign-currency native persistence and nonzero/unknown tax remain blocked. See the workbench README for fixture adaptations and limits.

Next native stage: prove foreign-currency persistence against the target database engine; implement the production draft/receipt adapters and verify the changed entrypoint and file routes against native sessions. Obtain owner/accountant approval of accounts and settlement/FX treatment before financial writes. Do not reuse Finens login or a parallel cash ledger.

The employee UI/CSRF layer saves own operational drafts only and invokes no native financial writer or payment APIs. Historical disposable native-login acceptance passed16 cases. New receipt flow has credential-free controller/storage proof; actual multipart/binary HTTP, deployed authentication and production persistence remain pending.

See `docs/MISSIONS/M119_OFFICE_EXPENSES_LOCAL_FOUNDATION.md` for scope and accounting mappings that remain proposed.

Latest checkpoint: `docs/REPORTS/M119_RECEIPT_INTERFACE.md` records118 passing credential-free CLI scenarios and optional Arabic/English receipt UI/multipart/download wiring. Normal save uploads remain415; the missing runtime draft factory remains503 and receipt controls require a bound receipt factory. Defaults are JPEG/PNG,5MiB original,10MiB preview,12MP/10000px,10 receipts/25MiB combined per draft; existing PHP limits may reduce uploads. No automatic receipt deletion is provided. Real multipart/binary HTTP and new visual acceptance remain unverified: browser file-URL navigation was denied and no server was restarted. Production persistence/migration, policy and accounting gates remain unchanged.

Current release-readiness continuation: `docs/REPORTS/M119_RELEASE_READINESS.md` records125 safe CLI scenarios, including21 native operational-storage cases. The native draft adapter requires confirmed outer begin/commit/rollback and rejects reuse of an uncertain handle across store instances; recovery needs a fresh physical connection and exact scoped source/audit readback. No automatic migration/factory or financial integration is enabled. The report specifies versioned migration, role/tenant, classification, currency/tax and bank reconciliation prerequisites; original Finens UI and receipt wiring are unchanged.

Latest native migration continuation: `docs/REPORTS/M119_NATIVE_STORAGE_MIGRATION.md` records143 safe CLI cases, including22 storage and17 migration cases. Version/checksum registry and explicit fresh-install adapter now exist, but no existing database, server, module startup or runtime factory ran them. SQLite test shims are not matching-engine proof. Unknown transaction outcomes remain quarantined across draft/migration adapters; lock cleanup is bounded and unconfirmed release stops with a physical-disposal/server-verification requirement. Native receipt persistence, matching-database acceptance and accounting remain gated.
