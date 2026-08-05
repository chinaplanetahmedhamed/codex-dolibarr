# M043-R01 — Staging publication

## Mission control

- **Mission ID:** M043-R01
- **Type:** STAGING_RELEASE
- **Status:** BLOCKED_BEFORE_APPLY
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

## Current evidence — 2026-08-05

- `new.chinaplanet.org`: HTTP 200, Cloudflare, `noindex`.
- `backup-erp.visiontrading.biz`: HTTP 200.
- Canonical staging state command responds and records M014; the old legacy status wrapper has no running containers.
- Restricted account `codex-deploy` has no M043 helper command in `sudo -n -l`.
- React PR: `chinaplanetahmedhamed/Moaz#13`; staging gateway variables are prepared, but the dedicated API-key secret is intentionally absent until Dolibarr provisioning.
- No staging apply, React merge, React workflow dispatch, or production action has occurred for M043.

## Stop condition

M043-R01 remains blocked before apply until the server administrator installs the reviewed M043-only helper/sudoers surface and the shared public rate limit is verified. Do not substitute the legacy generic staging wrapper.
