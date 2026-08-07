# M045-P01 — production ERP release

## Mission control

- **Mission ID:** M045-P01
- **Type:** PRODUCTION_RELEASE
- **Status:** DEPLOYED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — “Approve adding the restricted M045 production sudo rule and deploying to erp.visiontrading.biz”
- **Approval date:** 2026-08-07
- **Target:** `erp.visiontrading.biz` only
- **Staging prerequisite:** exact runtime commit and artifact must verify on canonical `backup-erp.visiontrading.biz`

## Objective

Release the staging-accepted M045 public-request quotation workspace to production ERP so website inquiries render as structured product rows with images, carton/piece packing details, per-piece selling prices, Dolibarr product mapping, notes, and draft quotation creation.

## Authorized scope

1. Deploy only the nine M045 runtime files below `custom/chinaplanetoperations` from the checksum-pinned runtime commit.
2. Apply only the additive M045 quotation schema from the checksum-pinned artifact.
3. Synchronize the single acceptance product `WP-9688B` / WooCommerce `105789` idempotently, preserving any existing Dolibarr base price when no approved USD conversion rate exists.
4. Add one restricted production helper and six exact `sudo` commands for `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
5. Create full affected-code and database backups before mutation and automatically restore both if apply-time verification fails.
6. Deploy through the GitHub `production` environment only after the identical staging artifact verifies healthy.

## Restrictions

- No invented or newly configured FX rate.
- No bulk CMS product sync, WooSync enablement, customer creation, synthetic production inquiry, quotation validation, email, order, invoice, stock movement, or accounting mutation.
- No changes to DNS, proxy, Compose definitions, container images, WordPress, React, n8n, or unrelated Dolibarr modules.
- The production helper is fixed to `vision_erp`, `vision_db`, `vision-os_dolibarr_custom`, and `erp.visiontrading.biz`; staging identifiers are forbidden.

## Release pins

- Runtime commit: `bd553a72eadeaa0437105060ea7cdafc8f11ecdd`
- Artifact: `m045c03-bd553a7.tar.gz`
- Artifact SHA-256: `2b01b0b4bc31c0f1e8ee80110f953ddb3b6b0c16e5e99d5de5e19b0b8e1b073f`
- Runtime manifest SHA-256: `21c948877b57b9c3851a374db61df68ea39886174bb05170862c0a958da52608`
- Support manifest SHA-256: `5831f1e4107e25dc6e3dce9f16c3ec871999565dcf6add84e6810e414d6549e7`

## Acceptance

- [x] Local source, helper, installer, and workflow contract tests pass.
- [x] The identical staging artifact verifies before production preflight.
- [x] Production compare and preflight verify the exact container, database, volume, artifact, source checks, schema prestate, and live authentication boundary.
- [x] Production apply reports protected code/database backup paths and hashes.
- [x] Independent production verify passes exact runtime hashes, schema, mapped acceptance product, ERP root, and anonymous-login boundary.
- [x] `erp.visiontrading.biz` remains healthy and no quotation/customer/email/business transaction was created by deployment.

## Rollback

- Apply-time failure restores both affected runtime files and the complete pre-apply database automatically.
- Manual rollback restores the exact recorded code and database backups and retains a checksum-addressed reapply archive.

## First production attempt — rolled back

- GitHub run `31211913874` passed staging verification, production compare, and production preflight.
- Apply stopped at the acceptance-product update and the helper reported `M045_PRODUCTION_AUTO_ROLLBACK=PASS`.
- M045-C02 corrects preservation of existing production product extra fields and must pass staging before this mission is retried.

## Second production attempt — rolled back

- GitHub run `31213109770` passed staging verification, production compare, and production preflight.
- Apply exposed an existing outbound WooCommerce trigger that attempted to send the imported product back to the `chinaplanet` remote site.
- The remote response was invalid and the helper reported `M045_PRODUCTION_AUTO_ROLLBACK=PASS`.
- M045-C03 makes the authoritative CMS-to-ERP import trigger-free, preventing the outbound sync loop.

## Successful release

- M045-C03 staging run `31214187404` passed compare, preflight, apply, and independent verify on `backup-erp`.
- Production GitHub run `31214265559` deployed the identical artifact and passed independent production verification.
- Code backup: `/root/chinaplanetos-production-code-backups/m045-before-bd553a7-20260807T200505Z.tar.gz`
- Code backup SHA-256: `714e6321949c45b256fabf9a030d1acfd111c2614cb3ba0e754b67cec2d855f5`
- Database backup: `/root/chinaplanetos-production-db-backups/m045-before-bd553a7-20260807T200505Z.sql`
- Database backup SHA-256: `1eb78e017e779c18855fa65f54bc23c503f0b2b65095eee5db444d1df7e1d149`
- External ERP root returned HTTP 200; anonymous access returned the Dolibarr login form.
- Authenticated runtime inspection confirmed the quotation workspace, structured rows, product mapping, per-piece price controls, notes, and draft-quotation action without a fatal error.
