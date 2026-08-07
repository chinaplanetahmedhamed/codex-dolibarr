# M045 — Public request quotation workspace

## Mission control

- **Mission ID:** M045
- **Type:** WORKFLOW / UI / QUOTATION / LOCAL_IMPLEMENTATION
- **Status:** DEVELOPER_REPORTED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — "ok, do it" in the current task
- **Approval date:** 2026-08-07
- **Targets:** canonical source and local verification only
- **Staging / production:** forbidden without a separate release approval
- **n8n:** excluded

## Objective

Turn a public catalog inquiry into a clear staff quotation workspace: show requested products in a responsive table with safe product images and packing data, map each line to a Dolibarr product, choose an approved Dolibarr selling price or enter a manual selling price, save notes, and create or update one standard Dolibarr draft commercial proposal (`Propal`) that staff can preview, validate, and send through Dolibarr's standard workflow.

## Approved scope

1. Preserve the original public-request JSON as intake evidence and render catalog lines as a structured responsive table.
2. Accept enriched non-financial product snapshots from the secure website gateway: WooCommerce IDs, SKU, title, option, image URL, cartons, pieces, volume, weight, and customer comment.
3. Resolve catalog lines to Dolibarr products server-side by controlled identifiers, with a staff product selector for unmatched or ambiguous lines.
4. Add an entity-scoped, idempotent request-to-proposal link and prevent duplicate proposal creation.
5. Create one standard Dolibarr draft proposal for a selected Third Party/contact, in one controlled currency.
6. Allow authorized staff to use the current Dolibarr selling price or a manual selling unit price, and save public/internal notes in the proposal.
7. Keep proposal preview, validation, PDF generation, and customer email in Dolibarr's standard proposal screen.
8. Add English, Arabic, and Chinese labels, desktop/mobile behavior, source tests, and a local runtime-safe test path.

## Restrictions

- No Dolibarr core edit, custom quotation ledger, supplier price, purchase cost, margin, or private commercial metadata may be exposed to the website or customer.
- Never assume a WooCommerce product ID equals a Dolibarr product row ID.
- Browser input is untrusted; mapping, permissions, entity ownership, currency, price, tax, totals, and proposal linkage are enforced server-side.
- The stored original request/snapshot evidence must not be rewritten when staff edits a proposal.
- No automatic proposal validation, email sending, invoice, payment, stock movement, order, shipment, or accounting posting.
- No production/staging access, deployment, proxy, DNS, Compose, Portainer, WordPress mutation, n8n, or real customer email in this mission.
- Existing unrelated worktree changes must remain untouched.

## Required reviews

- **UI/UX:** table readability, clear next action, responsive layout, empty/error states.
- **Security:** rights, CSRF, entity isolation, image URL safety, server-side mapping and price validation.
- **QA:** duplicate prevention, legacy requests, matched/unmatched products, manual/default prices, notes, and proposal link.
- **Accounting:** single proposal currency, selling-price-only boundary, VAT/rounding delegated to standard `Propal`, and no false posting state.

## Acceptance

- A catalog inquiry displays one readable row per requested product, including image/title/SKU/option and packing quantities when supplied.
- Legacy requests containing only product IDs remain readable and can be mapped manually.
- Unauthorized users cannot create or edit a proposal, and every write requires a valid CSRF token.
- Staff can select a customer/contact, map products, select a Dolibarr selling price or enter a non-negative manual selling price, and save public/private notes.
- The first save creates one draft `Propal`; later saves update that same draft and never create duplicates.
- All proposal totals, VAT, currency, PDF, validation, and email remain standard Dolibarr behavior.
- The page provides a clear link to preview/validate/send the proposal in the standard Dolibarr screen.
- PHP lint, focused source/runtime tests, React lint/tests/build, and responsive UI verification pass locally before any release proposal.

## Parity

- **Admin ERP:** affected; new request quotation workspace.
- **Public React:** affected only to enrich the secure inquiry payload with non-financial product snapshot fields.
- **Customer Portal:** unchanged; the customer receives standard Dolibarr proposal output after staff action.
- **Mobile App:** `NOT_APPLICABLE`; it does not own public inquiry intake or staff proposal preparation.
