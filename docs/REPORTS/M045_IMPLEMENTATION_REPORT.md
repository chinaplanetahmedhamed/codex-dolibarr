# M045 implementation report

## Verdict

`PASS / SOURCE_IMPLEMENTED / READY_FOR_STAGING_ACCEPTANCE`.

The public catalog request now has a responsive product quotation workspace backed by one standard Dolibarr draft `Propal`. Source, security, accounting-boundary, WordPress contract, React gateway, PHP syntax, and regression checks pass locally. Authenticated Dolibarr runtime and browser acceptance remain intentionally pending because the local MariaDB configured at `127.0.0.1:8889` is unavailable. No staging, production, customer email, or real business record was accessed or changed.

## Delivered behavior

- Replaces recursive ProductId/VariationId output with product rows containing image, title, option, SKU, cartons, pieces, CBM, weight, mapping, price source, selling unit price, and line note.
- Uses a Dolibarr product photo after mapping; a signed WordPress snapshot image is only allowed over HTTPS from China Planet or explicitly configured hosts.
- Adds visible Dolibarr product extra fields for WooCommerce product and variation IDs, while retaining exact SKU and staff-selection fallbacks.
- Creates one draft `Propal` per public request and uses a connection advisory lock plus an entity-scoped unique link to prevent duplicate creation.
- Requires the dedicated `publicrequest_quote` right and standard product/proposal rights; the new financial right receives no implicit user grants.
- Supports current Dolibarr/customer-level selling prices or a non-negative manual selling price.
- Uses total pieces as proposal quantity when units/carton is known; otherwise the quoted unit is one carton.
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

`PASS_SOURCE / RUNTIME_PENDING`. The table has clear mapping/pricing actions, status, standard quotation handoff, legacy/unmapped states, and responsive card behavior at the `700px` and `430px` breakpoints. Authenticated desktop and 390px browser evidence is required on staging before release.

### Security

`PASS`. CSRF and three-layer permissions are present; request, customer, contact, product, proposal, and currency are entity/server validated; image URLs are bounded; duplicate creation is locked; the original intake remains immutable; and no private cost or margin field is handled.

### Accounting

`PASS_WITH_RUNTIME_CONDITION`. The workflow creates a draft quotation only. Standard `Propal` owns VAT, totals, rounding, exchange rate, validation, PDF, and sending. Staging must verify the active currency rate and representative VAT/customer-price configurations before production.

### QA

`PASS_LOCAL / DB_RUNTIME_PENDING`.

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

## Boundaries

- No staging or production deployment occurred.
- No proposal was validated, signed, accepted, refused, closed, invoiced, paid, stocked, or posted to accounting.
- No email was sent.
- No unrelated Dolibarr worktree changes were touched.
