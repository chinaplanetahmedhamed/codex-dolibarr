# M045-R03 — staging login-page acceptance correction

## Mission control

- **Mission ID:** M045-R03
- **Type:** STAGING_TEST_HARNESS_CORRECTION
- **Status:** COMPLETED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — the approved `backup-erp` deployment remains in progress
- **Approval date:** 2026-08-07
- **Production:** forbidden

## Trigger

GitHub run `31198458005` passed artifact verification, upload, compare, and preflight. Apply reached the live anonymous-route check, then failed and automatically rolled back successfully. Read-only post-rollback evidence showed `NOT_DEPLOYED`, all M045 files restored, `SCHEMA_PREEXISTED=0`, and canonical preflight PASS.

Dolibarr 23.0.3 returns its login form with HTTP `200` for an anonymous request to the staff page. The helper incorrectly accepted only `302`, `401`, or `403`.

## Authorized correction

1. Keep `302`, `401`, and `403` as accepted anonymous outcomes.
2. Accept HTTP `200` only when the response contains both the Dolibarr login form and its `actionlogin` field.
3. Reject every other HTTP `200` response so anonymous access to the actual staff workspace still fails closed.
4. Update and reinstall only the checksum-pinned M045 helper, then rerun M045-R01.

## Exclusions

- No runtime source, artifact, schema, permission, business record, production, WordPress, React, n8n, proxy, DNS, Compose, container, or volume change.

## Result — 2026-08-07

- Corrected helper SHA-256 `71be7469cddefb9d9b715e36653c4464df64a985a48168f2e4de6c4a4efd01db` was installed with the unchanged six-command staging-only sudo surface.
- M045-R01 run `31200353003` passed apply and verify; no rollback was required.
- The anonymous route was accepted only after the response matched the two Dolibarr login-form markers.
- `PRODUCTION_ACCESSED=NO`.
