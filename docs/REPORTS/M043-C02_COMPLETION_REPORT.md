# M043-C02 completion report

## Verdict

`PASS / COMPLETED / STAGING_VERIFIED` on canonical `backup-erp` only. The Public Website Requests detail page now presents historical request and commercial evidence as responsive operational cards instead of raw or double-encoded JSON. Production and n8n were not accessed or changed.

## Delivered behavior

- Safely decodes legacy double-encoded request and commercial JSON on read without rewriting stored evidence.
- Presents contact, request, cargo, POL/POD, carrier, container, pricing, validity, inclusions, exclusions, warnings, and carrier-space status with professional labels and spacing.
- Keeps technical schema, fingerprint, and terms-hash fields out of the normal staff UI while preserving them in storage.
- Uses the shared Customer Center admin shell and responsive navigation.
- Preserves permission, entity, CSRF, status, customer-selection, promotion, and anonymization behavior.
- Adds matching English, Arabic, and Chinese labels.

## Verification

- PHP source contracts and artifact verification: `PASS`.
- GitHub Actions run: `31059447845` — all required stages passed; rollback was not invoked.
- Release: `m043-c02-staging`.
- Runtime commit: `87d64018a89e08db4d86551ad046e9d2bebe01fc`.
- Artifact: 26 allowlisted files, SHA-256 `3ab56995901ed5f81c5e301b0a2fcf7ba60948ff78778675a23ab23473b4eb8c`, with no AppleDouble metadata members.
- Authenticated browser acceptance on request `id=14`: desktop and `390x844` passed; raw `<pre>` count `0`, literal escaped-newline leakage `false`, and page overflow `false`.
- The full mobile page showed the structured request cards, route, total `6,868.00 USD`, validity, included/excluded items, warnings, and explicit unconfirmed-carrier-space notice.
- The local MariaDB rerun remained unavailable at `127.0.0.1:8889`; the staging runtime test passed inside the restricted deployment flow.

## Deployment evidence and rollback

- Staging state: release `M043-C02`, status `DEPLOYED`, deployed at `2026-08-06T00:22:17Z`.
- Code backup: `/root/backup-erp-code-backups/m043-before-87d6401-20260806T002212Z.tar.gz`.
- Database backup: `/root/backup-erp-db-backups/m043-before-87d6401-20260806T002212Z.sql`.
- Schema pre-existed and no schema mutation was required by this correction.
- The GitHub environment SSH secret was replaced with the approved working deployment key; no secret value was printed or committed.
- The temporary Termius helper snippet was deleted after verification.

## Boundaries

- No production, proxy, DNS, Compose, WordPress, React, mobile, accounting, customer-portal, n8n, or business-record mutation occurred.
- No status change, Third Party link, promotion, or anonymization action was executed against request `id=14`.
