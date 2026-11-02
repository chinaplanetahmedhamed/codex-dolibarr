> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M045-R01 — backup-erp staging acceptance

## Mission control

- **Mission ID:** M045-R01
- **Type:** STAGING_RELEASE
- **Status:** IN_PROGRESS — DEPLOYED / AUTHENTICATED_UI_ACCEPTANCE_PENDING
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — "ok push to backup-erp for testing"
- **Approval date:** 2026-08-07
- **Target:** canonical `backup-erp.visiontrading.biz` only
- **Production:** forbidden
- **n8n / WordPress / React:** excluded from this release

## Objective

Publish the exact M045 Dolibarr quotation-workspace commit to canonical `backup-erp`, apply only its additive schema, and verify the staff request page before any production decision.

## Authorized scope

1. Deploy only the nine M045 files below `custom/chinaplanetoperations` from runtime commit `8d919fd9f2164bca6cfd859e89df338e985c486a`.
2. Add `fk_propal`, its entity-scoped unique index, quotation-right definition `500515`, and the two Product Extra Fields `cp_woo_product_id` and `cp_woo_variation_id`.
3. Back up affected staging code and the staging database before mutation.
4. Use a checksum-pinned M045-only root helper through the restricted `codex-deploy` account.
5. Run candidate lint/source checks, post-deploy schema checks, file-hash verification, and staging HTTP health.

## Forbidden scope

- Production ERP, production database, production containers, production volumes, production URLs, or production helper commands.
- Legacy `/opt/codex-dolibarr-staging` and `codex_staging_*` resources.
- Customer email, proposal validation, business-record creation, accounting, WordPress, React, n8n, proxy, DNS, Compose, image, container, document, or module-state changes.
- Automatic grant of the new quotation right to staff groups.

## Rollback

- An apply failure restores the exact code backup and the pre-apply staging database backup automatically.
- A later manual rollback restores the code backup and retains the additive schema dormant, avoiding deletion of staging records created after release.
- Production remains untouched in every path.

## Acceptance

- Restricted `compare`, `preflight`, `apply`, and `verify` pass against `erp-staging` only.
- The runtime files match the pinned artifact hashes and every PHP file lints inside the staging container.
- `M045_SCHEMA_CHECK=PASS`, the standard ERP root remains healthy, and the quotation page does not expose an anonymous write path.
- The final report records artifact/backup hashes, rollback paths, and `PRODUCTION_ACCESSED=NO`.

## Deployment evidence — 2026-08-07

- GitHub Actions run `31200353003`: `PASS`.
- Restricted compare, preflight, apply, and independent verify: `PASS`.
- Runtime commit: `8d919fd9f2164bca6cfd859e89df338e985c486a`.
- Artifact SHA-256: `2503f666d86c9422207295e4c21fa000bd725cd7060eb79ec86881652f6846cd`.
- State: `DEPLOYED`; schema was absent before apply and the post-apply schema check passed.
- Standard ERP root: HTTP `200`; anonymous request route returned the Dolibarr login form and no staff workspace.
- Authenticated desktop and 390px staff-page visual acceptance remains pending because no `backup-erp` staff session was available. Local-only test credentials were not reused on staging.
- Production: `PRODUCTION_ACCESSED=NO`.
