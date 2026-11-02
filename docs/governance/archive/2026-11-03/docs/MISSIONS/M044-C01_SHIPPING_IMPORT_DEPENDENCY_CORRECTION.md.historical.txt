# M044-C01 — Shipping Prices import dependency correction

## Mission control

- **Mission ID:** M044-C01
- **Type:** CORRECTION / RELEASE_PACKAGING
- **Status:** COMPLETED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — approved the diagnosed correction and staging-first test
- **Approval date:** 2026-08-07
- **Initial target:** `backup-erp.visiontrading.biz` only
- **Production authority:** None; a separate exact approval is required after staging acceptance

## Objective

Restore the authenticated Shipping Prices import page after M044 deployed a
`ShippingPriceImporter.class.php` that requires
`ShippingNoteTranslationService.class.php` while omitting that required service
from the runtime artifact.

## Approved scope

1. Package the existing, source-matched translation service from the current
   canonical repository.
2. Require the existing staging importer and import page to match the exact
   M044 hashes before applying the correction.
3. Add only the missing service file to canonical `backup-erp` through a
   checksum-pinned, staging-only helper.
4. Back up the previous file state, lint the PHP, load the complete importer
   dependency closure, and verify ERP health after apply.
5. Verify the authenticated import page at desktop and 390px before considering
   a separately approved production release.

## Restrictions

- No database, schema, module-state, configuration, document, business record,
  customer, quotation, shipping rate, provider secret, proxy, DNS, Compose,
  container, port, WordPress, React, n8n, or production change.
- Do not overwrite the importer or import page. Preflight must stop if either
  prerequisite hash differs from the M044 runtime.
- Do not use the legacy `codex-staging-*` wrappers or the retired duplicate
  staging stack.
- Production remains forbidden until Ahmed approves the exact staging-accepted
  artifact.

## Runtime allowlist

- `custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php`

## Acceptance

- Local PHP lint and dependency-contract test pass.
- Artifact contents and SHA-256 match the pinned release.
- Canonical staging identity, container, volume, ports, and target hashes pass.
- Apply creates a rollback backup and changes only the one allowlisted file.
- Runtime dependency test and ERP HTTP health pass.
- Authenticated `/custom/chinaplanetshippingprices/admin/import.php` loads
  without HTTP 500 on desktop and at 390px.
- Production is not accessed or changed.

## Parity

- **Admin ERP:** affected; authenticated import page only.
- **Customer Portal:** `NOT_APPLICABLE`; no customer-facing contract changes.
- **Mobile App:** `NOT_APPLICABLE`; no mobile contract changes.

## Completion evidence — 2026-08-08

- GitHub PRs `#12` and `#13` merged to `main`.
- Runtime commit: `dc6387f41611f2bec24c5a96eb8aacc252f019ce`.
- Artifact SHA-256: `946479da0e9072ff83e2145ac5a82e75e2109459e262e449ef7375a53446a847`.
- First workflow run `31221565605` stopped read-only at
  `IMPORT_PAGE_PREREQUISITE_DRIFT`; no apply occurred.
- Corrected workflow run `31221891055` passed compare, preflight, apply, and
  verify on canonical `backup-erp`.
- Independent restricted status is `DEPLOYED` and independent verify passed.
- Authenticated desktop acceptance passed. At `390×844`, the page title,
  workbook control, draft action, and batch history were present with document
  width `390px` and scroll width `390px`; no fatal error or horizontal overflow.
- Production was not accessed or changed.
