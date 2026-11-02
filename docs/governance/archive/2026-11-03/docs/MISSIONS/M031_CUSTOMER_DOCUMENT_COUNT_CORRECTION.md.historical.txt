# M031 — Customer Document Count Correction

## Mission control

- **Mission ID:** M031
- **Type:** CORRECTION
- **Status:** COMPLETED
- **Architect / implementation owner:** Agent 007
- **Target environment:** Local development only
- **Production:** Forbidden
- **Ahmed approval:** Explicit request that the Documents card show the number of documents for the selected customer
- **Approval date:** 2026-07-21

## Objective

Make the customer dashboard Documents card count all customer-visible documents and expose those records in the combined Documents page.

## Scope and rules

1. Count active customer-visible shipment publications belonging to the selected/authenticated customer and active entity.
2. Count active customer-visible commercial/export documents belonging to the same customer and entity.
3. Show customer-visible commercial documents inside the main Documents page above shipment files.
4. Do not change document content, publication state, permissions, schema, accounting, Git, staging, or production.

## Success criteria

- Dashboard uses one shared, role-aware document counter.
- No internal or unpublished document count is disclosed to external customers.
- PHP lint, focused scope assertions, prior M030 assertions, and whitespace checks pass.

## Rollback

Revert the M031 helper, restore the previous dashboard publication-count block, and remove the focused M031 test and documentation. No database rollback is required.
