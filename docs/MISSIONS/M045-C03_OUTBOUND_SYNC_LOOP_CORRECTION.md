# M045-C03 — prevent outbound product sync loops

## Mission control

- **Mission ID:** M045-C03
- **Type:** PRODUCTION_CORRECTION / STAGING_FIRST
- **Status:** DEPLOYED
- **Approved by:** Ahmed Hamed — “ok, do it, fastest way”
- **Approval date:** 2026-08-07
- **Targets:** `backup-erp.visiontrading.biz` first, then `erp.visiontrading.biz` under M045-P01

## Failure evidence

- Production run `31213109770` reached apply after all earlier gates passed.
- Updating acceptance product `105789` fired an existing WooCommerce connector and attempted an outbound update to the `chinaplanet` remote site.
- The connector returned `Wrong format of API query response - Error: Syntax error`.
- Automatic code and database rollback passed.

## Correction

The acceptance sync is an authoritative CMS-to-ERP import. Product create, update, and price-update operations therefore use Dolibarr's supported `notrigger` argument. This prevents a feedback loop to WooCommerce while keeping all transaction, validation, allowlist, price, and packing safeguards intact.

## Release pins

- Runtime commit: `bd553a72eadeaa0437105060ea7cdafc8f11ecdd`
- Artifact: `m045c03-bd553a7.tar.gz`
- Artifact SHA-256: `2b01b0b4bc31c0f1e8ee80110f953ddb3b6b0c16e5e99d5de5e19b0b8e1b073f`
- Staging GitHub run: `31214187404` — PASS
- Production GitHub run: `31214265559` — PASS

## Acceptance

- [x] Official Dolibarr 23.0.3 method signatures confirm the `notrigger` parameter for product create, update, and price update.
- [x] Source and restricted-helper contract tests pass.
- [x] The exact artifact passes staging compare, preflight, apply, and verify.
- [x] Production creates protected code/database backups before mutation.
- [x] The exact artifact passes production apply and independent verify.
- [x] The authenticated live quotation workspace renders without fatal errors.
