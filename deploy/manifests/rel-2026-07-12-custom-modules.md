# REL-2026-07-12-CUSTOM-MODULES

Status: `STAGING_VALIDATED`

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
- Forged customer card denial regression: ownership lookup and HTTP 403/content-type headers complete before `llxHeader()` or modal output, preventing headers-sent warnings and local-path disclosure.
- Customer acceptance: UX-026 and UX-027 are VERIFIED locally; owned Alpha board/modal/full-page show only the approved public status, while forged Beta IDs fail closed without warnings, paths, links, or record metadata.
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

## Remaining production gates

- Read-only live descriptor-file preflight found `500100` only in the expected CPKanban descriptor; no competing descriptor declaration was found for `500000` or `500200`. Database rights/module metadata collision checks remain required before CustomerCenter activation.
- Production backup ID/checksum/restore-readability evidence.
- Final narrow deployment allowlist, downtime estimate, validation checklist, and Ahmed's separate final deployment approval.

## Deployment allowlist

- `custom/cpkanban/**` (canonical runtime; SQL files are reference/install inputs, not authorization to reinitialize live modules).
- `custom/chinaplanetlogistics/**` (canonical runtime; do not restore the removed diagnostic `add_user_to_project.php`).
- `custom/chinaplanetcustomercenter/**`, excluding `_src/**` and `tests/**`; preserve only the two required runtime symlinks listed above.
- `deploy/migrations/rel_2026_07_12_custom_modules.php` and `.lib.php` for the separately approved forward migration.
- This manifest.

Explicitly excluded: Dolibarr core, `conf.php`, databases/dumps, documents, credentials, logs, sessions, caches, backups, demo/test data, duplicated source trees, test scripts, and unreferenced alias symlinks.

## Staging artifact

- Validated source commit: `e52c35e` (superseded only by this evidence-only manifest commit).
- Validated artifact: `rel-2026-07-12-custom-modules-e52c35e.tar.gz`.
- SHA-256: `378b336ebbc2e9b5131eaeb96c80228b7dc4611f7be8829738351b0eaf105078`.
- Location: `/home/codex-deploy/staging-inbox/`; checksum, archive safety, migration, installation, activation, restart, isolation, and rollback gates passed in staging.

## Final staging validation

- Staging was isolated from production networks, volumes, database, documents, configuration, proxy, and DNS.
- Dolibarr executing code and upgraded database marker: 23.0.3; MariaDB: 10.6.27; PHP: 8.2.7.
- Migration validation passed on MariaDB 10.6: first run 10 actions, second run 0 actions, cleanup remaining 0.
- Standard Dolibarr activation passed for ChinaPlanetCustomerCenter, eCommerceNG, and Kontava; activation constants, hooks, and triggers survived container restart. WhatsApp and Oblyon remained disabled.
- Behavioral project-trigger test created exactly one Alpha Kanban card on project creation, updated it without duplication, and left Beta cards unchanged.
- Staging restart and HTTP health check passed; no new critical Apache/PHP log errors were reported.
- Customer isolation and forged-ID denial passed. Local Customer Agent browser acceptance supplies the actual modal/history/focus, protected-cover, logout-confirmation, UX-026, and UX-027 evidence for the identical release code.
- Required relative symlinks resolve within the staging custom tree.
- Pre-upgrade, post-upgrade, and post-test snapshots were checksummed; rollback from 23.0.3 to the pre-upgrade state and restoration to 23.0.3 both passed health checks.

`STAGING_VALIDATED` authorizes preparation of the release candidate only. It is not production deployment approval. Production still requires a fresh encrypted backup, exact live diff/allowlist, rollback plan, final validation plan, and Ahmed's separate approval after reviewing them.
