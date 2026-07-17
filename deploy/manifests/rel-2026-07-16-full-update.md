# REL-2026-07-16-FULL-UPDATE

Status: `READY_FOR_BACKUP_DEPLOYMENT`

Source branch: `develop/ui-0-visiontradingui`

Target: `backup-erp.visiontrading.biz` only. This release does not authorize a production deployment.

## Scope

- Update the China Planet Customer Center, including the unified employee dashboard, customer portal, finance, order, project, document, and operations entry points.
- Update China Planet Logistics and CPKanban integration and user experience.
- Update the Vision Trading UI and white-label login/dashboard presentation.
- Add the China Planet Shipping Prices module and its internal SOP integration.
- Add the China Planet Operations module as the internal operations center for SOPs, shipping bookings, reports, documents, email drafts, and internal tasks.
- Provide Arabic, English, and Simplified Chinese operation content where supported by the new modules.
- Add reviewed module reload, seed, import, normalization, and white-label deployment tools.

## Deployment allowlist

- `custom/chinaplanetcustomercenter/**`
- `custom/chinaplanetlogistics/**`
- `custom/cpkanban/**`
- `custom/visiontradingui/**`
- `custom/chinaplanetshippingprices/**`
- `custom/chinaplanetoperations/**`
- `deploy/migrations/rel_2026_07_12_custom_modules.php`
- `deploy/migrations/rel_2026_07_12_custom_modules.lib.php`
- `deploy/tools/**`
- This manifest.

The required relative symlinks under `chinaplanetcustomercenter` must remain relative and resolve only inside the deployed custom tree.

## Explicit exclusions

- Dolibarr core files and `conf.php`.
- Database dumps, document data, credentials, sessions, logs, caches, and backups.
- Development-only tests and demo customer data.
- Any production host, production container, production database, or production volume.

## Required pre-deployment backup

Before changing the backup ERP environment, create and checksum an off-runtime backup containing:

1. Its database.
2. Its complete current custom-module tree.
3. Its documents volume.
4. A configuration manifest with image versions, container/volume names, Dolibarr version, and enabled modules.

The deployment must stop if the backup cannot be read or checksummed.

## Verification

- PHP syntax, JavaScript syntax, Git whitespace, secret-signature, and archive-path checks.
- All repository integration and workflow regression tests.
- Standard Dolibarr module installation/upgrade without destructive module reinitialization.
- Admin, authorized internal employee, unauthorized internal employee, and external customer access checks.
- Direct-URL denial for internal SOP, operations, and administration endpoints.
- Customer isolation, dashboard, booking workflow, shipping-price search, SOP suggestion, task workflow, document generation, and multilingual smoke tests.
- Public HTTP health and recent critical PHP/Apache log check.

## Rollback

- Stop validation traffic to the backup environment.
- Restore the pre-deployment custom tree and database from the checksummed backup.
- Restore documents only if deployment validation changed them.
- Restart the isolated backup environment and repeat health, login, permission, and customer-isolation checks.
- Do not attempt a partial database rollback with ad-hoc destructive SQL.

## Release evidence

- Source commit, artifact name, SHA-256, backup ID, deployment time, and validation results are recorded after the immutable release artifact is created and deployed.
