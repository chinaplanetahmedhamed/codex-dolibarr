> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M044-C02 — Staging Shipping Prices schema correction

## Mission control

- **Mission ID:** M044-C02
- **Type:** CORRECTION / DATABASE_MIGRATION
- **Status:** APPROVED / BUILDING
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-08
- **Target:** canonical `backup-erp.visiontrading.biz` only
- **Production authority:** forbidden

## Problem

The authenticated Shipping Prices page loads after M044-C01, but a real
workbook upload fails before a draft is created with:

`Unknown column 'source_notes_raw' in 'INSERT INTO'`

The deployed importer writes the additive M041 shipping-note translation
columns. The staging rate table does not currently expose at least the first
required column.

## Approved scope

1. Inspect the exact M041 shipping-note schema state on canonical staging.
2. Take a complete MariaDB backup and an exact schema prestate snapshot.
3. Apply the existing additive, idempotent M041 migration to add only missing
   shipping-note columns and its entity/batch/status index.
4. Verify exact column metadata, index order, staging identity, ERP health, and
   importer dependency hashes.
5. Upload the user-provided price workbook once and verify that a reviewable
   draft is created; do not publish or activate any rates.

## Restrictions

- Staging only. No production host, container, database, volume, helper,
  workflow job, environment, secret, URL, or identifier is permitted.
- No application runtime file, module state, configuration, permissions,
  proxy, DNS, Compose, container, port, React, WordPress, n8n, customer portal,
  mobile app, quotation, order, invoice, stock, or accounting change.
- No rate publication or activation. The workbook acceptance stops at draft.
- Apply must stop before mutation unless the canonical staging identity,
  artifact SHA, migration SHA, importer SHA, service SHA, table identity,
  schema prestate, database backup, and free-space checks pass.
- Rollback must use the recorded prestate. If new-column data prevents safe
  column removal, rollback must restore the checksum-pinned database backup.

## Schema allowlist

Table: `llx_chinaplanetsp_rate` using the active Dolibarr prefix.

- `source_notes_raw`
- `source_notes`
- `notes_en`
- `translation_status`
- `translation_source_hash`
- `translation_provider`
- `translation_model`
- `translation_error`
- `fk_user_translation_review`
- `date_translation_review`
- index `idx_chinaplanetsp_rate_translation`

## Acceptance

- Source contract and migration tests pass.
- Deterministic artifact SHA matches the pinned release.
- Restricted compare and preflight report staging only and no mutation.
- Apply records a non-empty MariaDB backup and exact SHA before migration.
- Exact post-apply schema check and independent verify pass.
- Authenticated workbook upload creates a draft and shows its review page.
- No publication, activation, production access, or unrelated change occurs.

## Parity

- **Admin ERP:** affected; staging Shipping Prices import only.
- **Customer Portal:** `NOT_APPLICABLE`; unchanged.
- **Mobile App:** `NOT_APPLICABLE`; unchanged.
- **Public React:** `NOT_APPLICABLE`; unchanged.
