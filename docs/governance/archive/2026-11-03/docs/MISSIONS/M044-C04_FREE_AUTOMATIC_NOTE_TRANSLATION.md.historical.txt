# M044-C04 — Free automatic shipping-note translation

## Mission control

- **Mission ID:** M044-C04
- **Type:** CORRECTION / EXTERNAL_INTEGRATION / APPLICATION_FILES
- **Status:** APPROVED / IN_PROGRESS
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-09
- **Target:** local development, then canonical `backup-erp.visiontrading.biz` after exact release approval
- **Production authority:** forbidden

## Problem

M044-C03 exposes safe note-review controls, but Batch 21 cannot translate its
Chinese carrier notes because the configured paid provider is absent. The
publication error also implies that staff must approve every translation even
though successful translations are designed to be accepted automatically.

Ahmed requires a temporary keyless/free provider, automatic acceptance of
validated English output, and optional staff editing only when a correction is
needed. Ahmed explicitly approved sending the carrier/source-note text alone to
the MyMemory REST API. Prices, customer data, costs, and other ERP data must not
leave Dolibarr.

## Approved scope

1. Use the official MyMemory `zh-CN|en` REST lookup as the default keyless
   provider when no private provider is configured.
2. Send only the normalized source-note text and language pair. Never send
   prices, customers, suppliers, workbook files, credentials, or ERP metadata.
3. Preserve the existing token, markup, language, response-size, timeout, TLS,
   redirect, DNS, and SSRF validation before automatic acceptance.
4. Automatically retry a bounded number of pending notes in a draft batch by
   CSRF-protected POST requests when an authorized staff member opens the batch.
5. Keep manual editing available as an optional correction. Remove wording that
   requires manual approval of every note.
6. Keep publication blocked only while a note remains untranslated or invalid.

## Restrictions

- No production access, deployment, configuration, secret, helper, workflow,
  container, Compose, proxy, DNS, database schema, module state, permissions,
  React, WordPress, n8n, document, customer record, accounting, or price
  publication change.
- Do not publish Batch 21 or any other batch during implementation or staging
  acceptance.
- MyMemory is a temporary best-effort provider with external usage limits. A
  quota or provider failure must stop the automatic loop safely and leave the
  note private/unpublished for a later retry or optional staff correction.
- Only authorized internal users with Import and View Cost rights may view,
  retry, or edit source-note translations.

## Acceptance

- Default translation requires no API key and sends only `q`, `langpair`, and
  `mt` to the fixed MyMemory HTTPS endpoint.
- Valid English output preserving all protected tokens is automatically stored
  as `approved` and customer-visible `public_notes`.
- Invalid, Chinese, markup-bearing, quota, oversized, or token-changing output
  is never published.
- Pending draft notes retry automatically in bounded POST batches; the loop
  stops when no progress is made.
- Staff may edit and save a correction without a separate approve action.
- Existing public projection, entity scope, permissions, CSRF, and publication
  guard tests pass.
- No batch is published and Production is not accessed or changed.

## Parity

- **Admin ERP:** affected; import and batch-review behavior only.
- **Customer Portal:** `NOT_APPLICABLE`; only already-approved public notes
  continue through the existing projection.
- **Mobile App:** `NOT_APPLICABLE`; API contract unchanged.
- **Public React:** `NOT_APPLICABLE`; unchanged.
