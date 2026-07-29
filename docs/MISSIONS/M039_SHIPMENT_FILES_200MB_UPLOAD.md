# M039 — Shipment Files 200MB Upload Correction

## Mission control

- **Mission ID:** M039
- **Type:** CORRECTION
- **Status:** IN_PROGRESS
- **Architect and implementation owner:** Agent 007
- **Required reviewers:** Shipping, Customer Portal, Security, QA, Architect
- **Dependencies:** M008, M020-C03, M024
- **Targets:** local source, canonical `backup-erp`, then `erp.visiontrading.biz`
- **Production authority:** Ahmed's direct 2026-07-29 request to correct the failed Shipment Files upload from 2MB to 200MB

## Objective

Allow authorized internal staff to upload shipment files up to 200 MiB without changing Dolibarr's global upload setting or the existing lower customer application cap.

## Verified context

- The failed production route is `custom/chinaplanetlogistics/card.php`.
- M008's `200M|210M|1024M|900|900` PHP override is limited to Shipping Prices and does not cover Shipment Files.
- Shipment upload validation already enforces authenticated write rights, POST, CSRF, actual and declared size, filename/extension, MIME, uploaded-file provenance, path containment, and private-by-default storage.
- Dolibarr is 23.0.3; ChinaPlanetLogistics source descriptor is 1.4.0 and ChinaPlanetCustomerCenter is 2.3.2.
- The local database runtime was unavailable during preflight; no local database or module state was changed.

## Approved scope

1. Add the proven PHP resource values under `custom/chinaplanetlogistics/` only.
2. Add an exact `200 * 1024 * 1024` internal staff limit and pass it only from the authorized staff shipment-card action.
3. Preserve the existing customer `MAIN_UPLOAD_DOC`/fallback cap.
4. Return a safe, specific message for PHP size rejection.
5. Add exact-boundary, one-byte-over, customer-cap, MIME, security-regression, configuration, staging, and production tests.
6. Use checksum-pinned, fail-closed staging and production helpers with backups and automatic rollback.

## Restrictions

- No global `MAIN_UPLOAD_DOC` change.
- No unrelated ERP upload route or customer upload entitlement change.
- No database/schema/data, accounting, documents, module state, Compose, image, DNS, firewall, proxy, port, or volume change.
- No production document inspection and no business file in automated acceptance.
- Production changes are restricted to the exact accepted M039 allowlist.

## Acceptance

- Effective shipment-route values are `200M|210M|1024M|900|900`.
- Staff size/MIME validation accepts an allowed file at exactly 200 MiB and rejects one byte more.
- Customer uploads do not inherit the staff allowance.
- Existing M003, M020, and M024 permission/ownership/publication behavior remains intact.
- Canonical `backup-erp` passes before the exact accepted artifact is applied to production.
- Every target has a hashed pre-deploy backup and fixed rollback.

## Approval record

| Role | Result | Date | Evidence/condition |
|---|---|---|---|
| Architect | APPROVED | 2026-07-29 | Narrow urgent correction |
| Ahmed | APPROVED | 2026-07-29 | Direct production request: 2MB to 200MB |
| Shipping | PENDING | — | Shipment workflow acceptance |
| Customer Portal | PENDING | — | Existing customer cap/publication behavior |
| Security | PENDING | — | Upload and authorization regression |
| QA | PENDING | — | Local, staging, and production evidence |

