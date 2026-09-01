# M071 — Unified B2B Quotation and Customer Center Lifecycle

## Report control

- **Status:** LOCAL_COMPLETE_SECURITY_QA_PASS
- **Owner:** Agent 007 Central, sole code-writing owner
- **Scope:** Local implementation only
- **Dolibarr base:** `7251ea78fd07e6de22302083dbf37702a3dd3d60`
- **React base:** `95816fc178dc3547f40d760e459e0323c70780f7`
- **WordPress base:** `fa34257fe9dfc276d85c16dae70b409224465c3c`
- **Contract:** `cpo.catalog-order.v2`
- **Shared fixture SHA-256:** `35f3b88a33027c95a292f01bab9b5467d92fa6ac385ef23dda82fba0ed0be9cc`
- **Shared invalid-fixture SHA-256:** `cc0abd96c274970a51a7dda2000d88596b97fec751cd9791ce7ebf5d9c557a2b`
- **Security/QA reviewed React tree:** `89e3054c187e93f2a9817b3d27c59eb9d37a511e`
- **Security/QA reviewed WordPress tree:** `33ec2bf9a792610b18300d24ffe9e27610731b61`
- **Security/QA reviewed Dolibarr source tree before this verdict-only report update:** `a7a1eacebb9569ba4cd1c4e1b4401f790540a735`
- **Server, Staging, Production, email, and real business records:** NONE

## Outcome

The local candidate implements one multi-product quotation request. Duplicate product/variation rows stay distinct through request-scoped `lineId`. Complete rows contribute to piece, CBM, weight, capacity, and 3D totals. Incomplete rows stay visible and are submitted with exact missing fields, while unknown numbers remain `null` and those rows never enter the loading calculation.

WordPress remains the catalog, packing, and price-policy authority. React submits through the secure Node gateway. Dolibarr stores the immutable intake and owns staff-reviewed identity qualification, draft quotation preparation, publication, and authenticated Customer Center visibility. No WooCommerce order, validated Dolibarr order, confirmed carrier booking, account, password, or email is created automatically.

## Security and ownership evidence

- Node accepts only strict integer product/variation/carton/line identifiers from the browser, re-fetches WordPress authority without redirects, verifies the final origin, verifies exact readiness and hidden-price invariants, strips non-allowlisted plan fields, excludes incomplete lines from every placement, keys rate limits from the socket unless proxy trust is explicit, and submits one frozen contract to Dolibarr.
- The legacy `/products` drawer freezes its line identifiers, consent timestamp, payload, and idempotency key across a lost-response retry. A changed cart or customer creates a new frozen request.
- WordPress exposes no headless request/order write endpoint. Signed planning rejects hidden/unpublished products and variations; only an anonymous Woo/B2BKing-rendered amount marker can expose a public price. Login/request-only text and member-hidden values remain request-only and are never inferred.
- Dolibarr enforces entity-scoped intake and request-unique idempotency, a frozen ISO alpha-2 allowlist plus Dolibarr `c_country.code` activation check, consent version/freshness, checked begin/commit/rollback results, checked advisory-lock release, and audited staff-controlled identity/publication transitions.
- Exact or ambiguous existing identity evidence blocks new Prospect/Lead creation. Company and contact email/phone use complete exact matching without a first-100-row cutoff. New Prospect/Lead and contact creation requires the explicit staff permission and action.
- Customer Center derives `fk_soc` from the authenticated external session and requires explicit request publication. Proposal visibility additionally requires a linked, approved proposal owned by the same entity/customer, and renders only customer-safe product/line labels and totals. `propaldet.description` and staff line notes are deliberately excluded. Public projection contains no costs, margins, supplier data, private notes, credentials, or raw request row IDs.
- M071 defines permissions disabled by default and grants none. The additive migration exposes only `check` and `apply`, detects rights collisions and partial table schemas, and was not executed.

## Language, direction, mobile, and accessibility

The React B2B flow and Customer Center request view provide Arabic, English, and French, Arabic RTL and English/French LTR, localized accessible names, keyboard focus styles, an ISO country selector, privacy consent, and responsive source rules for 360/390px and desktop. Incomplete-line notices explain that packing and price will be reviewed. Touch controls reach 44px on the narrow layout.

The native mobile app is intentionally unchanged. Its parity log marks quotation-request visibility as pending a future opaque-ID Customer Center API contract. Authenticated browser, real device, database, and real request acceptance remain M073 gates.

## Automated verification

- React: lint PASS; secure server tests PASS (67); UI/helper tests PASS (69), including frozen retry idempotency; production build PASS.
- WordPress: all six PHP suites PASS; worker smoke PASS; PHP syntax PASS. The REST contract suite has 29 assertions.
- Dolibarr: M071 source/fixture contract PASS; country contract accepts `EG`, rejects `ZZ`, and pins alpha-2 to `c_country.code`; M043/M045 intake and quotation regressions PASS; M054 CP Kanban ownership PASS; M062 Alexandria grouping PASS; M070 composite and unit-projection regressions PASS; all affected PHP files and the migration pass syntax.
- Shared positive and invalid fixtures: each is identical byte-for-byte in React, WordPress, and Dolibarr at the SHA-256 values above. Negative cases freeze rejection of fractional PCS/CTN, incomplete-line placements, and unknown country `ZZ`.
- Formatting: `git diff --check` must remain PASS in all three repositories at freeze.
- Database-backed and authenticated runtime tests were not run because this approval forbids business records and no isolated M073 runtime was approved.

## Runtime and rollback gate

This result is local source only. M073 must later prove a mixed complete/incomplete request reaches isolated Dolibarr exactly once, Customer Center isolation/publication, staff-side identity and draft quotation behavior, Arabic/English/French presentation, 360/390px and desktop rendering, and absence of email/order/booking side effects.

Before any release, M072 must package the complete manifest and additive migration in the unified checksum-pinned controller. Code rollback restores the preceding release; the additive schema is retained unless a separately rehearsed data rollback is approved.

## Review record

| Role | Result |
|---|---|
| Security | PASS on all three frozen trees; local source only |
| QA | PASS on the same frozen trees; runtime acceptance remains M073 |
| First and second independent reviews | CORRECTION_REQUIRED; all findings corrected and regression-covered before this replacement freeze |
| Agent 007 | LOCAL IMPLEMENTATION COMPLETE; Security and QA PASS |
| Ahmed | Local implementation only approved |

No push, upload, helper execution, database/configuration change, Staging/Production mutation, email, or real business record occurred.
