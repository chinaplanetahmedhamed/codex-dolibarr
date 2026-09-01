# M071 — Unified B2B quotation and Customer Center lifecycle

Status: APPROVED_FOR_LOCAL_IMPLEMENTATION
Approval: Ahmed Hamed, 2026-09-01 — “ok do it” following the M071 local-implementation gate.
Owner: Agent 007 (sole code-writing owner)
Contract: `cpo.catalog-order.v2`
Baseline: M070 Dolibarr/Mobile commit `7251ea78fd07e6de22302083dbf37702a3dd3d60`, React commit `95816fc178dc3547f40d760e459e0323c70780f7`, WordPress commit `fa34257fe9dfc276d85c16dae70b409224465c3c`.

## Business outcome

A visitor builds one multi-product quotation list, including products whose packing data is incomplete. Calculation-ready rows update the safe loading estimate and 3D drawing; incomplete rows remain in the request and are explicitly excluded from calculated CBM, weight, capacity, and placements. The complete immutable request reaches Dolibarr once as a review workflow, never as a validated order, confirmed quotation, or carrier booking.

## In scope

- Freeze and validate `request_type="catalog_order"`, top-level `schema_version=2`, unique request-scoped `lineId`, nullable unknown packing values, exact `missingFields`, `packingComplete`, `calculationReady`, source snapshots, and customer-safe per-piece price policy.
- Preserve duplicate product/variation rows as separate lines.
- Use a validated ISO 3166-1 alpha-2 country selector.
- Keep public, member-only, and request-only price visibility fail-closed; never infer a hidden amount.
- Recalculate authoritative product, packing, quantity, and customer-safe price snapshots server-side from WordPress.
- Submit only through React → secure Node API → Dolibarr. The React route must never create a WooCommerce order.
- Store an immutable Dolibarr intake plus entity-scoped working lines and an auditable, idempotent qualification workflow. Existing exact identities require controlled linking; ambiguous identities stop for staff review; a new Prospect/Lead and contact are created only through the reviewed lifecycle.
- Permit already activated Customer Center users to view only explicitly published, customer-safe requests and quotations owned by their `fk_soc`.
- Maintain Arabic, English, French, RTL/LTR, accessibility, and 360/390px/desktop behavior.

## Out of scope

- Automatic quotation validation, sales-order creation, carrier booking, portal password generation, invitation email, customer email, supplier data, costs, margins, internal notes, private documents, or automatic account activation.
- M072 release-controller work, server upload, helper execution, Staging or Production mutation, and real business-record acceptance.

## Security and ownership boundaries

- WordPress/WooCommerce owns catalog, variation, packing, and B2BKing price policy.
- React owns the public list and consent UI; Node signs the WordPress call, strips untrusted fields, rate-limits submission, and forwards the authoritative contract.
- Dolibarr owns leads, quotation requests, quotations, customers, documents, shipments, and authenticated Customer Center operations.
- Every ERP query and write is entity scoped; every Customer Center projection is additionally scoped to the authenticated customer.
- Unknown values remain `null`; zero is never substituted for unknown packing or pricing.

## Acceptance and stopping gates

- Identical contract fixtures cover complete, incomplete, mixed, duplicate, public-price, hidden-price, invalid-country, replay/conflict, consent, rate-limit, and upstream/ERP failures across the three repositories.
- Local lint, unit, UI, build, PHP/Bash syntax, source-contract, and relevant Dolibarr runtime tests pass.
- Frozen, checksum-identical Security and QA reviews both pass before local commits.
- Stop on any inferred packing/price, duplicate-row collapse, missing immutable snapshot, cross-entity projection, direct browser-to-Dolibarr call, WooCommerce order creation from the React route, unauthorized email, or unexplained M070 regression.
- Rollback is removal of the isolated M071 worktrees/branches only; permanent worktrees remain untouched.

No push, upload, deployment, helper action, database/configuration mutation, email, or real business record is approved.
