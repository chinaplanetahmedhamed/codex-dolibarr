# M090 — Dedicated shipment document export API

Owner: Ahmed Hamed
Production target: `erp.visiontrading.biz`.

## Scope

- Expose staff-only ChinaPlanetLogistics shipment list, detail, and document upload routes.
- Deny external users even if they have a module read right. Keep entity-scoped shipment lookup and validate document filename, size, and content type.
- Keep customer publication disabled by default. Report partial publication failures accurately.
- Return safe API errors without exposing raw database errors.

## Release gates

- Package only the API entrypoint, API class, and module descriptor from a frozen commit. Preserve unrelated worktree changes.
- Verify the exact artifact on canonical `backup-erp` first, including authenticated staff and external-customer access tests and native document arrival.
- Production preflight and apply/verify require separate exact approvals, a checksum-pinned artifact, a scoped backup, and an automatic rollback path.
- The prior `CPL-API-20260925` helper and artifact are superseded and must not be applied.
