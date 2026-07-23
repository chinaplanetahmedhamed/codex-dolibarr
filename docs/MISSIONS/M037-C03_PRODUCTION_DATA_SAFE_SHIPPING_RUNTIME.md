# M037-C03 — Production-data-safe Shipping Runtime Acceptance

## Mission control

- **Mission ID:** M037-C03
- **Parent:** M037
- **Status:** DEVELOPER_REPORTED
- **Approver:** Ahmed Hamed
- **Approval date:** 2026-07-24
- **Approval wording:** “ok do it”
- **Environment:** Local development only during implementation and verification

## Objective

Correct the M036 shipping-orders runtime acceptance failure exposed by real production data without weakening entity, ownership, customer-visibility, rollback, or deployment safeguards.

## Confirmed context

- The exact M037 production apply stopped at `BUCKET_DELTA_total_current_0`.
- Automatic code/database rollback passed and the production portal returned HTTP 200 afterward.
- The shipping lifecycle SQL evaluates `data_json` with `JSON_EXTRACT` without first validating legacy JSON values.
- `shippingOrderStats()` deliberately returns zeroed statistics when its SQL query fails, which makes one malformed legacy JSON value appear as a zero fixture delta.

## Approved implementation

1. Make shipping lifecycle JSON reads tolerate malformed or empty legacy JSON by substituting an empty JSON object before extraction.
2. Extend the transactional M036 runtime test with a malformed legacy `service` record and prove:
   - baseline statistics still execute;
   - the malformed record is safely classified as active;
   - the seven existing lifecycle fixtures retain their exact expected delta;
   - entity and customer ownership isolation remain unchanged;
   - rollback removes every test fixture.
3. Run local PHP lint, focused source tests, and the transactional runtime test.
4. Produce a clean, checksum-pinned correction artifact and restricted helper for later review.

## Restrictions

- No production or `backup-erp` access in this mission step.
- No schema or customer-data mutation outside the rolled-back local test transaction.
- No change to lifecycle meanings, permission rules, customer projections, finance, documents, portal UI, or mobile UI.
- A new exact deployment approval is required before the correction artifact may be uploaded or applied.

## Affected files

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `deploy/tests/test_m036_shipping_orders_runtime.php`
- M037 release/helper metadata needed to pin the new commit and artifact
- this mission, completion report, changelog, and parity log

## Success criteria

- Malformed legacy JSON no longer causes zeroed shipping statistics.
- Existing lifecycle totals remain `7 / 4 / 1 / 1 / 1`.
- All focused tests pass locally.
- Artifact contents are allowlisted and checksum-pinned.
- Production remains untouched until separate approval.

## Developer result

- PHP lint: `PASS`.
- Focused source contract: `PASS`.
- Transactional local runtime: `M036_RUNTIME=PASS total_delta=7 active=4 confirmed=1 closed=1 cancelled=1 legacy_json=SAFE ownership=ISOLATED foreign_entity=EXCLUDED rollback=CLEAN`.
- No `backup-erp` or production access occurred during implementation or verification.
- Release packaging and restricted-helper pins are recorded in the release commit that follows this runtime correction.
