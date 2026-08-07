# M045-C01 staging report

## Verdict

`PASS_WITH_BLOCKER / PRODUCT_AND_UI_STAGING_VERIFIED / USD_DRAFT_NOT_CREATED`.

The controlled CMS product sync, secure React intake, product mapping, real image, piece-based packing equation, and responsive staff workspace passed on canonical staging. A USD draft quotation was deliberately not created because backup ERP exposes only EUR and has no approved USD conversion rate. Writing `0.34 USD` as `0.34 EUR` would corrupt the commercial meaning.

## Release identity

- Branch: `codex/m045-public-request-quotation`.
- Dolibarr runtime commit: `28ce3ee50886dc70e680717dcfc6f36161b6a8d1`.
- Delivery tooling head: `129d4f1`.
- Artifact: `m045c01-28ce3ee.tar.gz`.
- Artifact SHA-256: `c413922cebb16645d1dfdd59da8d027cea7d49e838639b4302ac4d872e2ee794`.
- Installed restricted helper SHA-256: `51c7b1e14ad931b23f7f413f7f468e2db9fe6b347a2c00b689d035cfdb2cdbe2`.
- React staging runtime: `e8b253d5cc8e3ce766d2da43dc55d2cd67b0637d`.

## Real product and synthetic request

- CMS/WooCommerce product: `105789` / `WP-9688B`, Valve Fitting WP-9688B.
- Authoritative CMS selling price: `0.34 USD / piece`.
- Packing: `100 pieces/carton`, `50 × 40 × 38 cm`, `0.076 m³/carton`, `11 kg` net and `12 kg` gross.
- Synthetic request: `WEB-20260807-182858-81C9DA`, staging row `id=32`.
- Requested quantity: `2 cartons × 100 pieces/carton = 200 pieces`.
- Request totals: `0.152 m³` and `24.00 kg` gross.
- Synthetic contact data uses the reserved `example.invalid` domain and is clearly marked not to validate or email.

## Verification

- Local PHP source/security contract: `PASS`.
- Staging helper contract and deterministic 33-member artifact: `PASS`.
- Restricted compare, preflight, apply, and independent verify: `PASS`.
- Idempotent product mapping and packing-field check: `PASS`.
- Authenticated desktop: product image, `WP-9688B`, mapping, piece equation, price source, per-piece label, and note controls visible.
- Authenticated `390×844`: `PASS`; image and all product-row values visible, responsive rows use content height, and page-wide horizontal overflow is false.
- Original request snapshot remained immutable.

## Final backup and rollback evidence

- Code backup: `/root/backup-erp-code-backups/m045-before-28ce3ee-20260807T184616Z.tar.gz`.
- Code backup SHA-256: `f1a2aa2e79610226de40f16e3f0fb3f87832f65607d8f357132c895eec831280`.
- Database backup: `/root/backup-erp-db-backups/m045-before-28ce3ee-20260807T184616Z.sql`.
- Database backup SHA-256: `7cb2b8214b0d6bf627d280ccb37294701206ca2fcd0e0c1105a8047f3097df60`.
- Rollback was not invoked because apply and verify passed.

## Currency blocker

- Backup ERP currency selector exposes only `EUR`.
- No active approved USD conversion rate is available.
- Product identifiers and packing synchronized, but the Dolibarr base price remains unchanged/zero rather than receiving a false currency value.
- No quotation draft was created. Completion of the draft-price acceptance requires a separately approved USD currency/rate configuration, followed by a synthetic draft-only retest.

## Boundaries

- `cms.chinaplanet.org` was read-only; WooSync remained disabled and no bulk sync ran.
- No real customer data, proposal validation, PDF, email, order, invoice, stock movement, accounting entry, proxy, DNS, Compose, or production resource was accessed or changed.
- Customer Portal and native Mobile App are `NOT_APPLICABLE`; the affected surfaces are the public React intake and staff-only Dolibarr workspace.
- `PRODUCTION_ACCESSED=NO`.
