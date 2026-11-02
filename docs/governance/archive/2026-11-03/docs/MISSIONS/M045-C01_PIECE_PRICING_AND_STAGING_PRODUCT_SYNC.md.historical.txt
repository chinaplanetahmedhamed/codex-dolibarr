# M045-C01 — Piece pricing and controlled staging product sync

## Mission control

- **Mission ID:** M045-C01
- **Type:** CORRECTION / STAGING_INTEGRATION
- **Status:** STAGING_VERIFIED_WITH_CURRENCY_BLOCKER
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — current task: correct piece pricing, sync CMS products to backup ERP, and test through new.chinaplanet.org
- **Approval date:** 2026-08-07
- **Targets:** `cms.chinaplanet.org` read-only, `new.chinaplanet.org`, and canonical `backup-erp.visiontrading.biz`
- **Production ERP / production website:** forbidden

## Objective

Make every quotation line use pieces as the commercial quantity and unit-price basis, while preserving cartons as packing information. Verify the secure catalog request against the CMS, show product imagery, and prove the workflow with one real CMS product and a synthetic staging customer.

## Approved scope

1. Require a positive pieces-per-carton value before a catalog line can become a quotation line.
2. Display `cartons × pieces/carton = total pieces` and label selling price as price per piece.
3. Use the secure B2B CMS verification path for the public React quote cart.
4. Synchronize the single acceptance product `WP-9688B` / WooCommerce product `105789` into backup ERP, idempotently, including its piece price and packing fields.
5. Create one synthetic staging inquiry through `new.chinaplanet.org` and verify it in backup ERP without validating or emailing the quotation.

## Approved exception — 2026-08-07

Ahmed selected the safe manual-price fallback after staging proved that backup ERP has no active USD conversion rate:

- Do not configure or invent an FX rate as part of this mission.
- Synchronize the CMS/WooCommerce identifier and all packing fields for `WP-9688B` even when the CMS currency cannot be converted safely into the Dolibarr base currency.
- When no safe conversion exists, preserve an existing Dolibarr base price; for a newly created product use zero rather than writing `0.34 USD` as if it were the base currency.
- Use the verified CMS selling price `0.34 USD / piece` only as a manually entered price in the synthetic staging quotation draft.
- In this acceptance run, “including its piece price” means verifying the authoritative CMS price and the manually entered USD piece price; it does not authorize an incorrect base-currency price write.

## Restrictions

- The CMS is read-only and WooSync remains disabled.
- No bulk sync is authorized by this acceptance run.
- No real customer PII, customer email, proposal validation, order, invoice, stock movement, accounting, production deployment, DNS, or proxy changes.
- Missing packing data must fail closed; cartons must never be substituted for pieces.
- The original public request snapshot remains immutable.

## Acceptance

- [x] The React cart obtains packing from the CMS server path and rejects a product whose pieces/carton is unknown.
- [x] The ERP workspace shows a real product image and the explicit carton/piece equation.
- [ ] A USD quotation draft uses total pieces as `qty` and a selling price per piece. Backup ERP currently exposes only EUR and has no approved USD conversion rate, so no incorrect draft was created.
- [x] The synced staging product carries CMS/Woo identifiers and all available packing fields; the authoritative CMS piece price is verified as `0.34 USD`, with base-price synchronization intentionally skipped because conversion is unavailable.
- [x] Local tests, staging deployment checks, and authenticated desktop plus 390px verification pass.

## Staging result — 2026-08-07

- React staging accepted the synthetic request `WEB-20260807-182858-81C9DA` for WooCommerce product `105789` / `WP-9688B`.
- Request `id=32` contains `2 cartons × 100 pieces/carton = 200 pieces`, `0.152 m³`, and `24.00 kg`.
- The mapped Dolibarr product and real CMS image render on desktop and at `390×844`; the final phone layout has no page-wide horizontal overflow.
- Runtime commit `28ce3ee50886dc70e680717dcfc6f36161b6a8d1` and artifact SHA-256 `c413922cebb16645d1dfdd59da8d027cea7d49e838639b4302ac4d872e2ee794` passed restricted compare, preflight, apply, and verify on canonical `backup-erp`.
- The quotation currency selector contains only `EUR`. No draft, validation, PDF, email, order, invoice, stock, accounting, or production mutation was performed.
