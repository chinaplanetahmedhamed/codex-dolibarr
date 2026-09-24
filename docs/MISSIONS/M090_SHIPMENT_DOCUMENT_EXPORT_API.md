# M090 — Dedicated shipment document export API

Owner: Ahmed Hamed
Production target: `erp.visiontrading.biz`.

## Scope

- Expose staff-only ChinaPlanetLogistics shipment list, detail, and document upload routes.
- Deny external users even if they have a module read right. Keep entity-scoped shipment lookup and validate document filename, size, and content type.
- Keep customer publication disabled by default. Report partial publication failures accurately.
- Return safe API errors without exposing raw database errors.
- Name the REST class `Chinaplanetlogistics`: the Dolibarr 23 direct dispatcher registers only the class derived from the endpoint name. The earlier `ChinaplanetlogisticsApi` class was present when production returned an empty HTTP 500; runtime acceptance must confirm the correction.

## Release gates

- Package only the API entrypoint, API class, and module descriptor from a frozen commit. Preserve unrelated worktree changes.
- Verify the exact artifact on canonical `backup-erp` first, including authenticated staff and external-customer access tests and native document arrival.
- Production preflight and apply/verify require separate exact approvals, a checksum-pinned artifact, a scoped backup, and an automatic rollback path.
- The prior `CPL-API-20260925` helper and artifact are superseded and must not be applied.
- Antigravity's earlier helper partially applied that superseded artifact to production and its verification failed with HTTP 500. The new route returned an empty 500 while an existing API returned a structured 405; PHP lint passed and no fatal marker appeared in the bounded container logs. Do not treat that partial production state as accepted or use the old helper for rollback.
- The staging candidate is the exact three-file API artifact from commit `0de9653`, SHA-256 `a3406d9fdfecd74ace4225d9d00fd3365af7767cb5ff86e3d625334e8f5be8e5`. The Mission-specific `m090_staging_api_release.py` targets only canonical `backup-erp`, checks the artifact and mounted custom volume, backs up each existing target, applies files atomically, checks PHP syntax and an unauthenticated 401/403 API boundary, and restores the prestate automatically on failure. Authenticated staff/customer and native document-arrival checks remain separate acceptance gates.
