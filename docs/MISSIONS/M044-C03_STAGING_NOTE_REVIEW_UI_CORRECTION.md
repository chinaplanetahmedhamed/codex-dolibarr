# M044-C03 — Staging shipping-note review UI correction

## Mission control

- **Mission ID:** M044-C03
- **Type:** CORRECTION / APPLICATION_FILES
- **Status:** APPROVED / BUILDING
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-09
- **Target:** canonical `backup-erp.visiontrading.biz` only
- **Production authority:** forbidden until separate staging acceptance and approval

## Problem

Batch 21 correctly blocks publication while imported carrier/source notes remain
unreviewed, but its deployed review page does not expose the note-review controls.
The earlier runtime artifact included the publication gate dependencies without
including the page, review service, and CSS that render and process those
controls.

## Approved scope

Deploy exactly these existing, reviewed application files to canonical staging:

1. `custom/chinaplanetshippingprices/admin/batch.php`
2. `custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php`
3. `custom/chinaplanetshippingprices/css/shippingprices.css`

Before replacement, record and back up each affected file. Verify hashes,
ownership, mode, PHP lint, source contracts, authenticated Batch 21 rendering,
and responsive note-review controls.

## Restrictions

- Staging only. Production hosts, containers, volumes, URLs, helpers, workflows,
  secrets, identifiers, and resources are forbidden.
- Do not publish Batch 21 or any other batch.
- Do not approve, save, reset, or otherwise mutate any imported carrier/source
  note during acceptance.
- No database, schema, configuration, module-state, permissions, proxy, DNS,
  Compose, container, port, React, WordPress, n8n, document, customer record,
  quotation, order, invoice, stock, or accounting change.
- The deploy helper must target only `erp-staging-dolibarr-1` and
  `erp-staging_erp-staging_custom`, use an exact three-file allowlist, create a
  root-owned pre-deploy backup, and roll back automatically on failed checks.

## Acceptance

- Source contract, PHP lint, helper contract, and deterministic artifact checks
  pass from a clean commit.
- Restricted compare and preflight identify canonical staging only.
- Apply records a non-empty, checksum-pinned backup of the prior file state.
- Verify confirms the exact three deployed hashes, `www-data:www-data` ownership,
  mode `0644`, ERP health, and no database change.
- Authenticated Batch 21 shows the carrier-note review summary and per-source-row
  review controls, including the English translation field and save action.
- The Publish control remains unavailable while pending notes exist.
- Desktop and 390px-wide responsive views are usable.
- No note is approved/saved/reset, no batch is published, and production is not
  accessed or changed.

## Parity

- **Admin ERP:** affected; staging Shipping Prices Batch 21 only.
- **Customer Portal:** `NOT_APPLICABLE`; unchanged.
- **Mobile App:** `NOT_APPLICABLE`; unchanged.
- **Public React:** `NOT_APPLICABLE`; unchanged.
