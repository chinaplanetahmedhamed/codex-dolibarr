# M090 — Dedicated shipment document export API

Owner: Ahmed Hamed
Production target: `erp.visiontrading.biz`.

## Scope

- Expose staff-only ChinaPlanetLogistics shipment list, detail, and document upload routes.
- Deny external users even if they have a module read right. Keep entity-scoped shipment lookup and validate document filename, size, and content type.
- Keep customer publication disabled by default. Report partial publication failures accurately.
- Return safe API errors without exposing raw database errors.
- Use the `/chinaplanetlogisticsapi` endpoint and `ChinaplanetlogisticsApi` REST class. Dolibarr 23 strips the `api` suffix to find the existing module file, then registers a class derived from the full endpoint name. PHP treats `Chinaplanetlogistics` and the existing `ChinaPlanetLogistics` shipment model as the same class; the suffix keeps them distinct. Staging runtime acceptance must confirm this route.

## Release gates

- Package only the API entrypoint, API class, and module descriptor from a frozen commit. Preserve unrelated worktree changes.
- Verify the exact artifact on canonical `backup-erp` first, including authenticated staff and external-customer access tests and native document arrival.
- Production preflight and apply/verify require separate exact approvals, a checksum-pinned artifact, a scoped backup, and an automatic rollback path.
- The prior `CPL-API-20260925` helper and artifact are superseded and must not be applied.
- Antigravity's earlier helper partially applied that superseded artifact to production and its verification failed with HTTP 500. The new route returned an empty 500 while an existing API returned a structured 405; PHP lint passed and no fatal marker appeared in the bounded container logs. Do not treat that partial production state as accepted or use the old helper for rollback.
- The first staging preflight stopped before changing ERP files because the custom volume root is `www-data:www-data` (`33:33`, mode `0755`), while the helper expected root ownership. A read-only root metadata check confirmed the live owner and mode. The revised helper pinned that observed metadata.
- Ahmed approved the three-file artifact from commit `0de9653`, SHA-256 `a3406d9fdfecd74ace4225d9d00fd3365af7767cb5ff86e3d625334e8f5be8e5`, and revised helper SHA-256 `93c879676ab547c0aad8e5fd348ac9e86d5930b0a60063c4b563911dac238ad2` on canonical `backup-erp` only. Preflight passed. Apply failed the unauthenticated API HTTP check and automatically restored the exact baseline: both new API files absent, descriptor hash `023fc24d067a160226860c63e6dea5e7488e935ffa5b75d4452b6963fbfa5a`. Helper state is `ROLLED_BACK_AFTER_FAILURE`; baseline API status is 501. The class/model name collision is the source-evidenced cause, not yet confirmed by a live failure marker. The next candidate and helper hashes require new exact staging approval. Authenticated staff/customer and native document-arrival checks remain separate acceptance gates.
- The corrected three-file candidate is frozen at ERP commit `abbc485`, artifact SHA-256 `4a1e25ceeb54f7bd3d7e7961ec649e9ce81173acb47396303891f100b3367374`. The staging helper now checks `/chinaplanetlogisticsapi/shipments`; its SHA-256 is `429853b5ab8e9917d2c3ff150cf4f99f38d8965d75f76d6751988c631e31486b`. Document-app commit `04f3d24` uses the same route. Neither candidate has been deployed to production or docstest.
- Ahmed approved the corrected artifact and helper for canonical `backup-erp` staging. Remote checksums matched; preflight, apply, and separate verify all passed. Staging backup: `/root/chinaplanetos-m090-staging/backup-20260924T194456Z`. The helper verified file hashes, PHP lint, ERP health, and an unauthenticated 401/403 API boundary. This is technical smoke only: staging Dolibarr 24.0.1 currently shows a login screen, so authenticated staff/customer isolation and native document arrival remain unverified. No production ERP or docstest application change was made.
