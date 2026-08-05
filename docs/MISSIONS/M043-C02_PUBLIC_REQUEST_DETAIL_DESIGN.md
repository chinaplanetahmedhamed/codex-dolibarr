# M043-C02 — Professional public-request detail design

## Mission control

- **Mission ID:** M043-C02
- **Type:** UI_CORRECTION / DATA_PRESENTATION / STAGING
- **Status:** IN_PROGRESS
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — requested redesign of raw Request Details and Commercial Snapshot JSON
- **Approval date:** 2026-08-06
- **Target:** canonical source, local verification, and `backup-erp` staging only
- **Production:** forbidden
- **n8n:** excluded

## Objective

Replace raw/double-encoded JSON on the staff-only Public Website Requests page with a professional, readable operational summary while preserving the stored evidence and all existing permissions and actions.

## Approved scope

1. Decode historical double-encoded request/snapshot JSON safely when reading existing records.
2. Render contact, request, cargo, route, equipment, price, validity, components, terms, and carrier-space state as structured cards and lists.
3. Hide technical-only fields such as schema version, fingerprint, and terms hash from the normal UI without deleting them from storage.
4. Use the shared Customer Center admin shell and responsive navigation on this staff page.
5. Add English, Arabic, and Chinese labels and verify desktop plus 390px behavior.

## Restrictions

- No database, schema, API, permission, customer-portal, mobile-app, accounting, React, WordPress, n8n, production, proxy, DNS, Compose, or business-record change.
- Do not mutate, rewrite, or delete stored request/snapshot JSON.
- Preserve status update, customer selection, promotion, anonymization, entity scope, and CSRF behavior.
- Any staging deployment must use a new checksum-pinned allowlist, backups, verification, and rollback.

## Acceptance

- Request `id=14` no longer shows raw JSON or literal `\\n` sequences.
- Request Details and Commercial Snapshot are readable cards with clear labels and professional spacing.
- The total, unit price, quantity, currency, route, container, carrier, validity, inclusions, exclusions, warnings, and non-confirmed-space state are understandable when present.
- Missing and legacy data fail safely without PHP warnings or raw technical leakage.
- Existing actions and permission checks remain unchanged.
- Desktop and 390px authenticated browser acceptance pass on `backup-erp`.
- Production is not accessed or changed.
