# M044 — Palestine shipping-country normalization and production release

## Mission control

- **Mission ID:** M044
- **Type:** DATA_CORRECTION / IMPORT_NORMALIZATION / PRODUCTION_RELEASE
- **Status:** APPROVED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed — requested replacing Israel with Palestine in Shipping Prices, enforcing it on future uploads, and publishing ERP and React live
- **Approval date:** 2026-08-06
- **Targets:** `backup-erp.visiontrading.biz`, `erp.visiontrading.biz`, `new.chinaplanet.org`, then `chinaplanet.org`
- **n8n:** excluded

## Objective

Represent Ashdod and Haifa shipping-price destinations as Palestine (`PS`) instead of Israel (`IL`), correct existing Shipping Prices module country scopes safely, keep the normalization automatic on future workbook uploads, and release the staging-accepted M043 public shipping gateway to the live ERP and React website.

## Approved scope

1. Change the built-in Shipping Price importer mapping for `ASHDOD` and `HAIFA` from `IL` to `PS`.
2. Force the staff/public Shipping Prices label for `PS` to the exact business label Palestine in English, Arabic, and Chinese.
3. Add a bounded, entity-scoped migration that updates only `IL` values in Shipping Prices rate, port mapping, rule, block, and SOP-revision country fields to `PS`.
4. Add source/runtime tests proving future uploads and existing records no longer expose `IL` in the Shipping Prices scope.
5. Apply the correction first to canonical `backup-erp`, with code and database backups, then verify the authenticated ERP and public route API.
6. Deploy the identical verified ERP artifact to `erp.visiontrading.biz` through a production-only restricted helper, with compare, preflight, backups, automatic failure rollback, verify, status, and an exact production confirmation gate.
7. Merge the M043 React shipping gateway into `main`, override `PS` to Palestine / فلسطين / Palestine, run quality checks, deploy the exact commit to `new.chinaplanet.org`, verify its health and live ERP integration, then dispatch the existing exact-SHA production workflow for `chinaplanet.org`.

## Restrictions

- No n8n, accounting, customer/supplier record, document, shipment, order, invoice, payment, proxy, DNS, Compose, Portainer topology, firewall, or unrelated module change.
- Do not rename Dolibarr core countries globally; the naming policy is limited to the Shipping Prices/public shipping experience.
- Do not delete prices, batches, rules, blocks, ports, SOPs, or requests.
- Data updates must be entity-scoped, counted before and after, transactional, backed up, and reversible only for the exact rows changed by this release.
- Production must receive the exact artifact/commit already verified on staging; stop on any environment, checksum, count, health, or integration mismatch.
- Production apply and React production dispatch require final exact artifact/SHA evidence and Ahmed's production order; this mission records his production intent but does not permit substituting a different artifact.

## Acceptance

- New workbook imports map Ashdod and Haifa to country code `PS`.
- Existing Shipping Prices `IL` country scopes become `PS` without changing other countries or deleting rows.
- ERP and public route options display Palestine rather than Israel for the affected routes.
- Public React country selection displays فلسطين / Palestine / Palestine for `PS` and retains the same POL/POD behavior.
- Source tests, PHP lint, migration tests, React lint/tests/build, staging runtime checks, authenticated browser checks, and public API checks pass.
- `backup-erp` is verified before production ERP apply.
- `new.chinaplanet.org` runs the exact commit before `chinaplanet.org` production dispatch.
- Exact backups and rollback locations are recorded for ERP production.

## Parity

- **Admin ERP:** affected; Shipping Prices filters and imported rate country scope.
- **Public React:** affected; destination-country label and live shipping gateway.
- **Customer Portal:** existing customer-safe Shipping Prices data contract remains.
- **Mobile App:** `NOT_APPLICABLE`; it does not own the public shipping-rate selector or importer.
