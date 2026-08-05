# M043-C01 — Website requests dashboard button

## Mission control

- **Mission ID:** M043-C01
- **Type:** UI_CORRECTION / STAGING
- **Status:** IN_PROGRESS
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
