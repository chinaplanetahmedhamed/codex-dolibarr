# M045-R01 staging report

## Verdict

`DEPLOYED / AUTOMATED_ACCEPTANCE_PASS / AUTHENTICATED_UI_ACCEPTANCE_PENDING`.

The exact M045 runtime is active on canonical `backup-erp`. Restricted compare, preflight, apply, schema verification, file-hash verification, ERP health, and anonymous-access isolation passed. No rollback was required. A dedicated authenticated staging staff session was unavailable, so desktop and 390px visual/interaction acceptance is recorded as pending rather than inferred.

## Release identity

- Mission: `M045-R01`, with corrections `M045-R02` and `M045-R03`.
- Branch: `codex/m045-public-request-quotation`.
- Runtime commit: `8d919fd9f2164bca6cfd859e89df338e985c486a`.
- Delivery tooling head: `feedd99ca59d9d5c7a191112e7f5cb9e8dc7e909`.
- GitHub Actions run: `31200353003`.
- Artifact: `m045-8d919fd.tar.gz`.
- Artifact SHA-256: `2503f666d86c9422207295e4c21fa000bd725cd7060eb79ec86881652f6846cd`.
- Installed helper SHA-256: `71be7469cddefb9d9b715e36653c4464df64a985a48168f2e4de6c4a4efd01db`.

## Environment and resource allowlist

- Host account: restricted `codex-deploy`.
- Target: canonical `backup-erp.visiontrading.biz` only.
- Compose project: `erp-staging`.
- ERP container: `erp-staging-dolibarr-1`.
- Database container: `erp-staging-mariadb-1`.
- Custom volume: `erp-staging_erp-staging_custom`.
- Runtime allowlist: the nine files in `deploy/releases/m045-runtime-files.txt`.
- Helper command surface: exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
- Production identifiers and resources: forbidden and not accessed.

## Results

- Helper installation SHA and `root:root 0755`: `PASS`.
- Restricted six-command sudo surface: `PASS`.
- Initial state: `NOT_DEPLOYED`, `SCHEMA_PREEXISTED=0`.
- Artifact verification and metadata-free archive validation: `PASS`.
- Compare: `PASS`; one new runtime file and eight expected updates.
- Preflight: `PASS`.
- Apply: `PASS`.
- Schema check: `PASS`.
- Workflow verify: `PASS`.
- Independent post-run status: `DEPLOYED`.
- Independent post-run verify: `PASS`.
- ERP root: HTTP `200`.
- Anonymous staff route: Dolibarr login form only; no anonymous write path.
- Rollback: not executed because apply and verify passed.

## Files deployed

1. `custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php`
2. `custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php`
3. `custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php`
4. `custom/chinaplanetoperations/css/operations.css`
5. `custom/chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang`
6. `custom/chinaplanetoperations/langs/en_US/chinaplanetoperations.lang`
7. `custom/chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang`
8. `custom/chinaplanetoperations/operations/public_requests.php`
9. `custom/chinaplanetoperations/sql/llx_cpo_public_request.sql`

No core Dolibarr file, document, configuration, proxy, DNS, Compose, image, port, volume definition, WordPress, React, n8n, customer portal, or mobile file changed in this release.

## Database and permission impact

- Added nullable `fk_propal` to the public-request table.
- Added unique index `uk_cpo_public_request_propal` on `(entity, fk_propal)`.
- Added right definition `500515 / publicrequest_quote` with `bydefault=0`; no staff or group grant was applied.
- Added entity-scoped Product Extra Fields `cp_woo_product_id` and `cp_woo_variation_id`.
- No proposal, customer, contact, product, invoice, payment, accounting entry, email, stock movement, or other business record was created or modified.

## Backups and rollback

- Code backup: `/root/backup-erp-code-backups/m045-before-8d919fd-20260807T170155Z.tar.gz`.
- Code backup SHA-256: `b258c049dcaea3838358a64cb07305fbcc8162858aab9fe9ae00e13fc7cbc30a`.
- Absent-file manifest: `/root/backup-erp-code-backups/m045-absent-before-8d919fd-20260807T170155Z.txt`.
- Absent-file manifest SHA-256: `282ce78ca01bce1a47c50d3feae42312a3b8cd46ef25227a7ed5be22ef60f707`.
- Database backup: `/root/backup-erp-db-backups/m045-before-8d919fd-20260807T170155Z.sql`.
- Database backup SHA-256: `dfc2009202aadb407d1b406c91cf0ce98d16f4d81ae8f422ce0207a91a8e8683`.
- Approved staging rollback command: `sudo -n /usr/local/sbin/chinaplanetos-backup-erp-m045-deploy rollback`.
- Manual rollback restores the prior code and retains the additive schema dormant, matching M045-R01. It must not be run without Ahmed's exact staging rollback approval.

## Remaining acceptance and risk

- Log in with a dedicated `backup-erp` staff test account and explicitly grant only the M045 quotation right required for the test role.
- Open an existing or synthetic request and verify desktop and 390px rows/cards, image fallback, product mapping, Dolibarr/manual selling price, public/private/line notes, totals, currency behavior, and standard proposal link.
- Prove that repeated saves retain one proposal ID if synthetic creation is authorized.
- Keep validation, real email, real customer data, supplier price/cost/margin, accounting, and production outside this acceptance.

## Parity

- Admin ERP: backend/runtime deployed and verified; authenticated desktop/390px UI pending.
- Customer Portal: `NOT_APPLICABLE`, unchanged.
- Mobile App: `NOT_APPLICABLE`, unchanged.
- Public React: excluded from this release and unchanged.

## Production boundary

`PRODUCTION_ACCESSED=NO`. Production ERP, database, containers, volumes, routes, documents, and helpers were not accessed or changed.
