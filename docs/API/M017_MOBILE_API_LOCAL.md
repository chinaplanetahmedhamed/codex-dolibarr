# M017 customer mobile API — local development

## Boundary

This API is an additive, read-only customer projection for the China Planet mobile app. Its only writes are device-session token issuance, rotation, revocation, opaque-ID allocation, and rate-limit counters.

- Local route base: `/api/index.php/chinaplanetcustomercenter/mobile/v1`
- Frozen contract: `docs/API/mobile-v1.openapi.json`
- Authentication: extension-owned short-lived Bearer access token plus rotating refresh token
- Customer scope: derived from the active external Dolibarr user and its current `fk_soc`; the API does not accept an entity or customer selector
- Data boundary: customer-visible records only; no supplier identity, supplier cost, margin, private notes, bank metadata, staff data, credentials, or filesystem paths

## Local prerequisites

1. Dolibarr 23.0.3 with the existing China Planet custom modules enabled.
2. Run the CLI-only additive schema migration from the repository root:

   ```text
   php deploy/migrations/m017_customer_mobile_api_schema.php check
   php deploy/migrations/m017_customer_mobile_api_schema.php apply
   php deploy/migrations/m017_customer_mobile_api_schema.php check
   ```

3. Configure a local-only random secret of at least 32 characters as `CHINAPLANETCUSTOMERCENTER_MOBILE_TOKEN_PEPPER`. Never commit or transmit it to the app. Production and backup-erp secrets require separate environment-specific authorization.
4. Keep `MAIN_API_DEBUG` disabled. Login and refresh fail closed while API debug logging is enabled to prevent credential or token leakage.

The API does not run DDL from a web request. If its additive tables are absent, it returns a generic request-ID-bearing server error.

## Protocol rules

- Login and refresh accept JSON only and reject unknown or caller-supplied scope fields.
- Protected routes accept only `Authorization: Bearer …`; Dolibarr master API keys are not accepted by this extension.
- List endpoints use opaque encrypted cursors bound to resource, entity, customer, filters, and page size.
- Every object ID is opaque and scoped to its customer and entity.
- Timestamps use UTC ISO-8601; money uses decimal strings and ISO currency codes; balances remain separated by currency.
- Customer files are streamed only after a fresh ownership/visibility check, with `private, no-store`, range validation, path confinement, and no public filesystem URL.
- Error bodies use the frozen bilingual `ErrorEnvelope` and include a non-secret `request_id`.

## Environment boundary

M017 authorizes local implementation and testing only. It does not authorize backup-erp, production, VPS, proxy/rewrite changes, push notifications, APK distribution, or store release.
