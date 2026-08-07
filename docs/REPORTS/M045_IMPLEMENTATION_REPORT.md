# M045 implementation report

## Verdict

`PASS_WITH_BLOCKER / SOURCE_IMPLEMENTED / STAGING_DEPLOYED / AUTHENTICATED_UI_VERIFIED / USD_DRAFT_PENDING`.

The public catalog request now has a responsive product quotation workspace designed to hand off to one standard Dolibarr draft `Propal`. Source, security, accounting-boundary, WordPress contract, React gateway, PHP syntax, and regression checks pass. The exact corrected runtime was deployed to canonical `backup-erp`; restricted compare, preflight, apply, schema checks, file verification, product sync, and authenticated desktop/390px acceptance passed. A USD draft was not created because staging exposes only EUR and has no approved USD conversion rate. Production, customer email, and real business records were not accessed or changed.

## Delivered behavior

- Replaces recursive ProductId/VariationId output with product rows containing image, title, option, SKU, cartons, pieces, CBM, weight, mapping, price source, selling unit price, and line note.
- Uses a Dolibarr product photo after mapping; a signed WordPress snapshot image is only allowed over HTTPS from China Planet or explicitly configured hosts.
- Adds visible Dolibarr product extra fields for WooCommerce product and variation IDs, while retaining exact SKU and staff-selection fallbacks.
- Creates one draft `Propal` per public request and uses a connection advisory lock plus an entity-scoped unique link to prevent duplicate creation.
- Requires the dedicated `publicrequest_quote` right and standard product/proposal rights; the new financial right receives no implicit user grants.
- Supports current Dolibarr/customer-level selling prices or a non-negative manual selling price.
- Uses total pieces as proposal quantity and fails closed when units/carton is missing; cartons are packing information only.
- Supports one controlled proposal currency with the standard Dolibarr rate, and converts foreign selling prices back to base currency using the proposal exchange rate.
- Keeps customer-visible, internal, and per-line notes separate. No purchase cost, supplier price, margin, or accounting posting is read or exposed.
- Hands preview, validation, PDF generation, and customer email to the standard Dolibarr proposal page.
- Preserves the immutable original website request and packing snapshot.

## Integration contract

- WordPress plugin `1.9.5` adds authoritative image, option, SKU, carton, unit, CBM, and weight fields to signed plan lines.
- The React server verifies that the signed lines exactly match the requested identifiers and carton quantities.
- The React server sends only the normalized non-financial line snapshot to Dolibarr and removes WooCommerce line prices from the stored gateway snapshot.
- Dolibarr never treats a WooCommerce ID as a Dolibarr row ID; mapping is server-side and ambiguity fails closed.

## Required reviews

### UI/UX

`PASS_STAGING`. The table has clear mapping/pricing actions, status, standard quotation handoff, legacy/unmapped states, and responsive card behavior. Authenticated desktop and `390×844` acceptance passed with the real product image and all row values visible without page-wide horizontal overflow.

### Security

`PASS`. CSRF and three-layer permissions are present; request, customer, contact, product, proposal, and currency are entity/server validated; image URLs are bounded; duplicate creation is locked; the original intake remains immutable; and no private cost or margin field is handled.

### Accounting

`PASS_WITH_RUNTIME_CONDITION`. The workflow creates a draft quotation only. Standard `Propal` owns VAT, totals, rounding, exchange rate, validation, PDF, and sending. Staging must verify the active currency rate and representative VAT/customer-price configurations before production.

### QA

`PASS_LOCAL_AND_STAGING / USD_DRAFT_PENDING`.

- M045 source/security contract: pass.
- M043 intake regression contract: pass.
- Modified Dolibarr PHP syntax: pass.
- React server tests: 45/45 pass.
- React lint and production build: pass.
- WordPress plugin PHP syntax, all contract tests, and worker smoke: pass.
- Git whitespace validation: pass.
- Local Dolibarr database runtime: unavailable at `127.0.0.1:8889`; no runtime-success claim made.
- Existing PHP 8.4 `fgetcsv()` deprecation notices in the WordPress spreadsheet reader remain outside M045 and did not fail tests.

## Staging acceptance gate

1. Back up the staging ERP code and database.
2. Apply and check `m045_public_request_quotation_schema.php` before loading the new page.
3. Explicitly grant `publicrequest_quote` only to the test quotation role.
4. Install WordPress plugin `1.9.5`, then deploy the React gateway branch.
5. Submit a synthetic catalog inquiry with a simple product and a variation.
6. Verify desktop and 390px rows, images, automatic SKU/external-ID mapping, and manual fallback.
7. Create a draft with Dolibarr price, update it with a manual price, and prove the same proposal ID remains.
8. Verify VAT, base/foreign totals, public/private notes, product images in the enabled PDF model, and the standard preview/send screen.
9. Send only to Mailpit or another staging sink; do not send to a real customer.
10. Confirm no supplier cost, purchase price, or margin is present in the page, API payload, PDF, or email.

## Staging deployment result — 2026-08-07

- Environment: canonical `backup-erp.visiontrading.biz` only.
- GitHub Actions: run `31200353003`, successful in 36 seconds.
- Runtime commit: `8d919fd9f2164bca6cfd859e89df338e985c486a`.
- Delivery tooling head: `feedd99ca59d9d5c7a191112e7f5cb9e8dc7e909`.
- Artifact: `m045-8d919fd.tar.gz`; SHA-256 `2503f666d86c9422207295e4c21fa000bd725cd7060eb79ec86881652f6846cd`.
- Restricted helper: SHA-256 `71be7469cddefb9d9b715e36653c4464df64a985a48168f2e4de6c4a4efd01db`; exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` are allowed.
- Compare, preflight, apply, workflow verify, and independent post-run verify: `PASS`.
- Runtime state: `DEPLOYED`; all nine runtime files match the release hashes.
- Database: added nullable `fk_propal`, the unique `(entity, fk_propal)` index, right definition `500515` with no default grant, and Product Extra Fields `cp_woo_product_id` and `cp_woo_variation_id`. No business record was created.
- Code backup: `/root/backup-erp-code-backups/m045-before-8d919fd-20260807T170155Z.tar.gz`; SHA-256 `b258c049dcaea3838358a64cb07305fbcc8162858aab9fe9ae00e13fc7cbc30a`.
- Pre-existing-absence manifest: `/root/backup-erp-code-backups/m045-absent-before-8d919fd-20260807T170155Z.txt`; SHA-256 `282ce78ca01bce1a47c50d3feae42312a3b8cd46ef25227a7ed5be22ef60f707`.
- Database backup: `/root/backup-erp-db-backups/m045-before-8d919fd-20260807T170155Z.sql`; SHA-256 `dfc2009202aadb407d1b406c91cf0ce98d16f4d81ae8f422ce0207a91a8e8683`.
- HTTP: public ERP root `200`; anonymous request route produced the Dolibarr login form, so no anonymous staff workspace or write path was exposed.
- Rollback: no rollback occurred. If Ahmed approves a later staging rollback, the fixed command is `sudo -n /usr/local/sbin/chinaplanetos-backup-erp-m045-deploy rollback`; it restores the prior code and leaves the additive schema dormant as specified by M045-R01.
- Production: `PRODUCTION_ACCESSED=NO`.

### M045-C01 authenticated acceptance — 2026-08-07

- Synthetic request `WEB-20260807-182858-81C9DA` / `id=32` passed authenticated desktop and `390×844` verification.
- The mapped real product `WP-9688B`, CMS image, `2 cartons × 100 pieces/carton = 200 pieces`, `0.152 m³`, `24.00 kg`, price-source selector, per-piece label, and notes are visible.
- The final corrected runtime is `28ce3ee50886dc70e680717dcfc6f36161b6a8d1`; artifact SHA-256 is `c413922cebb16645d1dfdd59da8d027cea7d49e838639b4302ac4d872e2ee794`.
- Remaining acceptance is limited to creating a synthetic USD draft after USD and an approved rate are separately configured. Staging currently exposes only EUR, so no incorrect `0.34 EUR` draft was created.

## Boundaries

- Staging deployment occurred only on canonical `backup-erp`; production was not accessed or changed.
- No proposal was validated, signed, accepted, refused, closed, invoiced, paid, stocked, or posted to accounting.
- No email was sent.
- No unrelated Dolibarr worktree changes were touched.
