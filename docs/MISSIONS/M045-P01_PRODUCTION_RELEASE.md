# M045-P01 — production ERP release

## Mission control

- **Mission ID:** M045-P01
- **Type:** PRODUCTION_RELEASE
- **Status:** APPROVED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — “Approve adding the restricted M045 production sudo rule and deploying to erp.visiontrading.biz”
- **Approval date:** 2026-08-07
- **Target:** `erp.visiontrading.biz` only
- **Staging prerequisite:** exact runtime commit and artifact must verify on canonical `backup-erp.visiontrading.biz`

## Objective

Release the staging-accepted M045 public-request quotation workspace to production ERP so website inquiries render as structured product rows with images, carton/piece packing details, per-piece selling prices, Dolibarr product mapping, notes, and draft quotation creation.

## Authorized scope

1. Deploy only the nine M045 runtime files below `custom/chinaplanetoperations` from runtime commit `28ce3ee50886dc70e680717dcfc6f36161b6a8d1`.
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

- Runtime commit: `28ce3ee50886dc70e680717dcfc6f36161b6a8d1`
- Artifact: `m045c01-28ce3ee.tar.gz`
- Artifact SHA-256: `c413922cebb16645d1dfdd59da8d027cea7d49e838639b4302ac4d872e2ee794`
- Runtime manifest SHA-256: `21c948877b57b9c3851a374db61df68ea39886174bb05170862c0a958da52608`
- Support manifest SHA-256: `5831f1e4107e25dc6e3dce9f16c3ec871999565dcf6add84e6810e414d6549e7`

## Acceptance

- [ ] Local source, helper, installer, and workflow contract tests pass.
- [ ] The identical staging artifact verifies before production preflight.
- [ ] Production compare and preflight verify the exact container, database, volume, artifact, source checks, schema prestate, and live authentication boundary.
- [ ] Production apply reports protected code/database backup paths and hashes.
- [ ] Independent production verify passes exact runtime hashes, schema, mapped acceptance product, ERP root, and anonymous-login boundary.
- [ ] `erp.visiontrading.biz` remains healthy and no quotation/customer/email/business transaction is created by deployment.

## Rollback

- Apply-time failure restores both affected runtime files and the complete pre-apply database automatically.
- Manual rollback restores the exact recorded code and database backups and retains a checksum-addressed reapply archive.

## First production attempt — rolled back

- GitHub run `31211913874` passed staging verification, production compare, and production preflight.
- Apply stopped at the acceptance-product update and the helper reported `M045_PRODUCTION_AUTO_ROLLBACK=PASS`.
- M045-C02 corrects preservation of existing production product extra fields and must pass staging before this mission is retried.
