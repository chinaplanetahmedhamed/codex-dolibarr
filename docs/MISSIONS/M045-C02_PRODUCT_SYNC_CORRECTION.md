# M045-C02 — preserve existing product extra fields during sync

## Mission control

- **Mission ID:** M045-C02
- **Type:** PRODUCTION_CORRECTION / STAGING_FIRST
- **Status:** COMPLETED — superseded by M045-C03 for production
- **Approved by:** Ahmed Hamed — “ok, do it, fastest way”
- **Approval date:** 2026-08-07
- **Targets:** `backup-erp.visiontrading.biz` first, then `erp.visiontrading.biz` under M045-P01

## Failure evidence

- Production compare and preflight passed in GitHub run `31211913874`.
- Product synchronization failed with `PRODUCT_UPDATE_FAILED`.
- The restricted helper reported `M045_PRODUCTION_AUTO_ROLLBACK=PASS`; no partial production release remains.

## Correction

Load all existing Dolibarr product extra fields before changing only the allowlisted ChinaPlanet mapping and packing fields. This preserves unrelated required production metadata and adds bounded Dolibarr error details if an update still fails.

## Gates

1. Exact corrected artifact must pass source/helper tests and restricted staging compare, preflight, apply, and verify.
2. Staging and production helpers must accept only the corrected artifact hash and their fixed environments.
3. Production retains full code/database backup and automatic rollback.
4. No FX configuration, bulk sync, customer record, quotation, email, order, invoice, stock, or accounting mutation is authorized.

## Release pins

- Runtime commit: `1e6d58a917402db927b56a9e4b5af33bba7d04d1`
- Artifact: `m045c02-1e6d58a.tar.gz`
- Artifact SHA-256: `20637c0e9cc4f3b48827d5a5cb5f27853bfda058e857b043e7c04e194667f1cc`

## Outcome

- Staging GitHub run `31213011273` passed compare, preflight, apply, and verify.
- Production run `31213109770` confirmed the extra-field preservation fix, then exposed a separate outbound WooCommerce trigger failure.
- Automatic rollback passed. M045-C03 resolved the outbound sync loop and became the final production artifact.
