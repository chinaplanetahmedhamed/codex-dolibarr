# M043-R01 — Staging publication

## Mission control

- **Mission ID:** M043-R01
- **Type:** STAGING_RELEASE
- **Status:** DOLIBARR_STAGING_VERIFIED / REACT_STAGING_PENDING
- **Owner:** Ahmed Hamed
- **Target:** canonical `backup-erp` Dolibarr staging and `https://new.chinaplanet.org`
- **Production:** forbidden; `https://chinaplanet.org` is recorded only as the future React production domain
- **n8n:** excluded
- **Approval:** Ahmed approved publication after identifying `new.chinaplanet.org` as React staging. This approval does not include production.
- **Approval date:** 2026-08-05

## Objective

Publish the reviewed M043 public website intake and FCL rate/booking flow to staging, prove the real React-to-Dolibarr outcome, and preserve a complete rollback path before any later production decision.

## Authorized scope

1. Deploy only the M043 Dolibarr runtime files, additive intake schema, permission definitions, and staging-only server configuration to canonical `backup-erp`.
2. Create a dedicated staging API user with only `website_rate_search` and `website_intake_create`.
3. Store `CHINAPLANET_PUBLIC_RATE_TOKEN_KEY`, `CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY`, and the API key only in server-side secret/configuration surfaces.
4. Deploy the corresponding React/Node commit to `new.chinaplanet.org` through the existing GitHub Actions and Portainer staging workflow.
5. Verify rate options, exact-rate search, 12-month reference trend, idempotent booking submission, Dolibarr staff queue visibility, and synthetic-data cleanup.

## Forbidden scope

- Any production workflow, stack, domain, database, container, volume, proxy, DNS, secret, or API user.
- `chinaplanet.org` deployment or cutover.
- Legacy `/opt/codex-dolibarr-staging` or `codex_staging_*` resources.
- n8n, WooCommerce order creation, confirmed carrier space, Sales Orders, invoices, payments, accounting entries, stock movements, or customer-data copies.
- Administrator or broadly privileged Dolibarr API keys.

## Exact staging identities

- ERP domain: `backup-erp.visiontrading.biz`
- Compose project: `erp-staging`
- ERP container: `erp-staging-dolibarr-1`
- DB container: `erp-staging-mariadb-1`
- custom volume: `erp-staging_erp-staging_custom`
- local ERP port: `127.0.0.1:18088`
- React domain: `new.chinaplanet.org`
- React Portainer stack ID: `6`, endpoint ID: `3`

## Required release gates

1. A checksum-pinned, M043-only staging helper and exact sudoers command allowlist must be installed by the server administrator.
2. Preflight must reject every noncanonical or production identity and verify code/database backups before mutation.
3. The helper must install the additive table and rights with exact pre-state capture and automatic rollback.
4. A shared or reverse-proxy/edge limiter must be verified before public traffic is accepted. The Node in-memory limiter alone is not release acceptance.
5. GitHub staging must contain the dedicated `DOLIBARR_API_KEY`; no `VITE_*` secret is permitted.
6. Dolibarr must be deployed and verified before merging/deploying the React PR.

## Current evidence — 2026-08-06

- `new.chinaplanet.org`: HTTP 200, Cloudflare, `noindex`.
- `backup-erp.visiontrading.biz`: HTTP 200.
- Canonical staging state command responds and records M014; the old legacy status wrapper has no running containers.
- The checksum-pinned M043 helper and exact five-command sudoers surface are installed for restricted account `codex-deploy`.
- Dolibarr artifact `m043-334c46c.tar.gz` (SHA-256 `8dd4e625190d2c33de98e9e902bc6f34589b4b8d731f4833462a9a8b2a30224e`) passed restricted preflight, apply, and verify on canonical `backup-erp`.
- Authenticated staff acceptance passed for the website request queue, request `id=14`, the Admin Dashboard button, and the 390px responsive navigation.
- GitHub Environment `backup-erp` now contains the two restricted SSH secrets and the three M043 gateway secrets. Their values were not printed or committed.
- GitHub prerelease `m043-c01-staging` stores the exact approved artifact, and `.github/workflows/deploy-backup-erp.yml` is pinned to M043-C01 with manual confirmation, staging-only environment, backup/apply/verify, and failure rollback.
- React PR remains `chinaplanetahmedhamed/Moaz#13`; React staging merge/dispatch and end-to-end public acceptance remain pending.
- No production action has occurred for M043.

## Stop condition

The Dolibarr staging gate is complete. M043-R01 remains open until the shared public rate limit is verified and the React PR is deployed and accepted on `new.chinaplanet.org`. Production remains forbidden and requires a separate mission and approval.
