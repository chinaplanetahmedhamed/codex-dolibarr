# M037-C04 — MariaDB-safe shipping classification

Status: DEVELOPER_REPORTED  
Approver: Ahmed Hamed  
Approval date: 2026-07-24  
Approval wording: “ok do it, and check”

Developer result: `PASS` on 2026-07-24; awaiting QA and Architect acceptance.

## Objective

Correct the rolled-back M037 production failure by removing lifecycle classification of shipping orders from MariaDB JSON SQL expressions and performing the same allowlisted classification in PHP.

## Scope

- Change `OperationsService::shippingOrderStats()` and `searchShippingOrders()` only as required to classify decoded service JSON in PHP.
- Treat malformed legacy JSON as an empty service payload and an active order, without failing or zeroing all statistics.
- Preserve entity, customer, booking-ownership, and shipment-ownership filters.
- Verify exact active, confirmed, closed, and cancelled counts with transactional local fixtures.
- Update tests, changelog, parity log, and completion report.

## Restrictions

- Local implementation and testing only.
- No production or `backup-erp` access under this approval.
- No schema change, no data mutation outside transactional test fixtures, and no customer-visible contract change.
- Production deployment requires a separate exact approval for the corrected pinned artifact.

## Success criteria

- No lifecycle SQL uses `JSON_EXTRACT`, `JSON_VALID`, or another database JSON parser.
- Malformed legacy JSON does not fail the shipping-order query.
- Runtime fixture reports the exact bucket counts and a clean rollback.
- PHP syntax, source contract, release checks, and diff checks pass.

## Affected files

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `deploy/tests/test_m036_shipping_orders_source.php`
- `docs/CHANGELOG.md`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`
- `docs/REPORTS/M037-C04_COMPLETION_REPORT.md`
