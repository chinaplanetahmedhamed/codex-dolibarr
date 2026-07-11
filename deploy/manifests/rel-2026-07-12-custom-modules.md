# REL-2026-07-12-CUSTOM-MODULES

Status: `BLOCKED`

Source branch: `develop/rel-2026-07-12-custom-modules`

Base production commit: `c1f8aa4`

## Scope

- Replace the production `chinaplanetlogistics` first-party source with the reviewed local version.
- Replace the production `cpkanban` source with the reviewed local version.
- Add `chinaplanetcustomercenter` as a new first-party custom module.
- Preserve production CPKanban font assets that remain referenced by CSS fallbacks.
- Deploy only the canonical runtime modules. Exclude duplicated `chinaplanetcustomercenter/cpkanban_src`, `chinaplanetlogistics_src`, and module test trees.
- Preserve the required runtime symlinks `chinaplanetcustomercenter/cpkanban -> ../cpkanban` and `chinaplanetcustomercenter/chinaplanetlogistics -> ../chinaplanetlogistics`.
- Do not include database data, documents, configuration, credentials, logs, sessions, caches, backups, demo data, or the legacy diagnostic/reset scripts excluded from the production baseline.

## Compatibility

- Dolibarr local and production: 23.0.3.
- Production PHP: 8.2.7; syntax gate executed with PHP 8.4.7.
- Production database reported: MariaDB 10.6.27.
- Local database target in test configuration: MySQL on 127.0.0.1:8889.

## Tests

- PHP lint: passed for all staged PHP files.
- JavaScript syntax: passed for all staged JavaScript files.
- Git whitespace check: passed.
- High-risk secret signature scan: passed.
- Customer route-guard matrix: passed.
- Customer shipment download path isolation: passed.
- Customer logout-confirmation markup: passed.
- Development router QUERY_STRING normalization: passed.
- UX-022 public activity actor masking: passed.
- UX-023 cover/database test: all eight checks passed against the approved local development database (`table`, MIME allowlist, size limit, external scope, realpath boundary, admin-only form, active cover file, and detail renderer).
- UX-026 hidden-column privacy regression: folded customer cards remain under the public `prog` column, hidden source metadata is absent from the external view-model/renderers, and internal column-label rendering is preserved.
- Forward migration disposable-schema test creates randomly prefixed production-shaped legacy tables in the DEVELOPMENT database, applies the migration twice, verifies required columns/index/table, requires the second run to perform zero actions, and drops the disposable tables in cleanup.

## Database/module state

- No production database migration was run during this Git preparation. The migration test changes only randomly prefixed disposable local tables and drops them during cleanup.
- No production module was enabled, disabled, or reinitialized.
- Forward migration artifact: `deploy/migrations/rel_2026_07_12_custom_modules.php`; it requires CLI plus explicit `--apply`, reads credentials only from `DOLIBARR_CONF_FILE`, adds no business rows, performs no destructive rollback, and is idempotent.
- Do not reinitialize CPKanban or ChinaPlanetLogistics to migrate existing tables. Apply the reviewed forward migration before deploying code that reads the new columns/table.
- ChinaPlanetCustomerCenter is absent from the captured production custom tree and must be treated as a new module installation requiring a separately reviewed activation step.

## Rollback

- File rollback source: production baseline commit `c1f8aa4` for the existing first-party modules.
- New-module rollback: disable ChinaPlanetCustomerCenter through the approved module procedure, then remove only its allowlisted deployed files after explicit approval.
- Database rollback must be defined after the staging migration test; no destructive SQL rollback is authorized at this stage.

## Remaining gates

- Validate the migration test against MariaDB 10.6 in disposable/staging infrastructure; the local automated run uses MySQL 5.7.39.
- Read-only live descriptor-file preflight found `500100` only in the expected CPKanban descriptor; no competing descriptor declaration was found for `500000` or `500200`. Database rights/module metadata collision checks remain required before CustomerCenter activation.
- Developer Agent final compatibility review against the production baseline diff.
- Customer Agent acceptance for all `READY_FOR_TEST` UX items.
- Production backup ID/checksum/restore-readability evidence.
- Final narrow deployment allowlist, downtime estimate, validation checklist, and Ahmed's separate final deployment approval.

## Deployment allowlist

- `custom/cpkanban/**` (canonical runtime; SQL files are reference/install inputs, not authorization to reinitialize live modules).
- `custom/chinaplanetlogistics/**` (canonical runtime; do not restore the removed diagnostic `add_user_to_project.php`).
- `custom/chinaplanetcustomercenter/**`, excluding `_src/**` and `tests/**`; preserve only the two required runtime symlinks listed above.
- `deploy/migrations/rel_2026_07_12_custom_modules.php` and `.lib.php` for the separately approved forward migration.
- This manifest.

Explicitly excluded: Dolibarr core, `conf.php`, databases/dumps, documents, credentials, logs, sessions, caches, backups, demo/test data, duplicated source trees, test scripts, and unreferenced alias symlinks.
