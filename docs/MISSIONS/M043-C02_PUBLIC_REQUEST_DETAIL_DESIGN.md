> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M043-C02 — Professional public-request detail design

## Mission control

- **Mission ID:** M043-C02
- **Type:** UI_CORRECTION / DATA_PRESENTATION / STAGING
- **Status:** COMPLETED
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

## Completion evidence

- Runtime commit: `87d64018a89e08db4d86551ad046e9d2bebe01fc`.
- Staging release: `m043-c02-staging`; clean 26-file artifact SHA-256: `3ab56995901ed5f81c5e301b0a2fcf7ba60948ff78778675a23ab23473b4eb8c`.
- GitHub Actions run `31059447845`: source contracts, artifact verification, restricted SSH upload, staging preflight, backup/apply, and verify all passed; rollback was not required.
- Canonical `backup-erp` state: `DEPLOYED`, release `M043-C02`, runtime commit and artifact checksum matched the approved pins.
- Pre-apply code backup: `/root/backup-erp-code-backups/m043-before-87d6401-20260806T002212Z.tar.gz`.
- Pre-apply database backup: `/root/backup-erp-db-backups/m043-before-87d6401-20260806T002212Z.sql`.
- Authenticated request `id=14` acceptance passed on desktop and at `390x844`: no raw `<pre>`, no literal `\\n`, no page-wide horizontal overflow, and all structured contact, request, commercial, pricing, validity, inclusion, exclusion, warning, and carrier-space sections rendered.
- Existing status, customer, promotion, and anonymization controls remained present; no real business-record action was executed during acceptance.
- The temporary Termius helper snippet was removed after successful deployment.
- Production and n8n were not accessed or changed.
