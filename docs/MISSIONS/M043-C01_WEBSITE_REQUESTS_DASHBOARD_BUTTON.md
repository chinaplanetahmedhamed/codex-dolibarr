# M043-C01 — Website requests dashboard button

## Mission control

- **Mission ID:** M043-C01
- **Type:** UI_CORRECTION / STAGING
- **Status:** COMPLETED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — requested a dashboard button for Website Requests
- **Approval date:** 2026-08-06
- **Target:** local source and canonical `backup-erp` staging only
- **Production:** forbidden
- **n8n:** excluded

## Objective

Make the existing staff-only Public Website Requests queue discoverable from the main Admin Dashboard without adding another top-level Dolibarr module icon.

## Approved scope

1. Add `Website Requests / طلبات الموقع` to the shared Admin Dashboard navigation.
2. Add the same destination as a visible Quick Action on the dashboard.
3. Preserve the existing `publicrequest_read` destination permission and admin-only dashboard boundary.
4. Verify desktop and 390px dashboard rendering and the real navigation outcome on `backup-erp`.

## Restrictions

- No database, schema, permission, API, customer-portal, mobile, accounting, React, n8n, proxy, DNS, Compose, production, or business-record change.
- Do not add a second Dolibarr top-level icon.
- Deploy only a checksum-pinned allowlist with backup, verification, and rollback.

## Acceptance

- The Admin Dashboard exposes a clearly labeled Website Requests button in both the shared navigation and Quick Actions.
- Both links open `/custom/chinaplanetoperations/operations/public_requests.php`.
- Existing request list/detail behavior and permissions remain unchanged.
- Desktop and 390px pages have no horizontal overflow.
- Production is not accessed or changed.

## Completion evidence — 2026-08-06

- Source commit: `334c46c4316c8e75c1535e75f772a44e24cb80a7`.
- Restricted helper pin: `37b8e26`.
- Staging artifact: `m043-334c46c.tar.gz`, SHA-256 `8dd4e625190d2c33de98e9e902bc6f34589b4b8d731f4833462a9a8b2a30224e`.
- Canonical `backup-erp` preflight, apply, and verify: `PASS` for release `M043-C01`.
- Backup before apply:
  - code: `/root/backup-erp-code-backups/m043-before-334c46c-20260805T225143Z.tar.gz`, SHA-256 `b6a61d7c4e0a4fd71f732660c011fc3c7fb8ac992c83f713e6698901523e9aff`;
  - database: `/root/backup-erp-db-backups/m043-before-334c46c-20260805T225143Z.sql`, SHA-256 `c3ae2ba3873e2fa5f5a415bf319787582534379e4171e16b56801dc222e5c128`.
- Authenticated desktop acceptance: shared navigation and Quick Actions both expose `Website Requests / طلبات الموقع`; the link opens the queue and request detail `id=14`.
- Authenticated 390×844 acceptance: responsive menu opens and exposes the same Website Requests destination without material horizontal overflow.
- Existing request permissions and data behavior were unchanged; no customer portal, mobile app, API, schema, n8n, or production change was made.
