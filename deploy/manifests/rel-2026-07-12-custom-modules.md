# REL-2026-07-12-CUSTOM-MODULES

Status: `BLOCKED`

Source branch: `develop/rel-2026-07-12-custom-modules`

Base production commit: `c1f8aa4`

## Scope

- Replace the production `chinaplanetlogistics` first-party source with the reviewed local version.
- Replace the production `cpkanban` source with the reviewed local version.
- Add `chinaplanetcustomercenter` as a new first-party custom module.
- Preserve production CPKanban font assets that remain referenced by CSS fallbacks.
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

## Database/module state

- No production or local database migration was run during this Git preparation.
- No production module was enabled, disabled, or reinitialized.
- CPKanban adds `custom/cpkanban/sql/llx_cpkanban_asset.sql`; migration/activation behavior must be validated against a disposable/staging database before approval.
- ChinaPlanetCustomerCenter is absent from the captured production custom tree and must be treated as a new module installation requiring a separately reviewed activation step.

## Rollback

- File rollback source: production baseline commit `c1f8aa4` for the existing first-party modules.
- New-module rollback: disable ChinaPlanetCustomerCenter through the approved module procedure, then remove only its allowlisted deployed files after explicit approval.
- Database rollback must be defined after the staging migration test; no destructive SQL rollback is authorized at this stage.

## Remaining gates

- Complete the remaining module schema/activation regression review on the approved development database.
- Developer Agent final compatibility review against the production baseline diff.
- Customer Agent acceptance for all `READY_FOR_TEST` UX items.
- Production backup ID/checksum/restore-readability evidence.
- Final narrow deployment allowlist, downtime estimate, validation checklist, and Ahmed's separate final deployment approval.
