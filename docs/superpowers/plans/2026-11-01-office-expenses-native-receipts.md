# Native receipts and ERP storage binding plan

Goal: implement native receipt metadata/audit and private blobs through existing native context, preserving immutable source ownership, exact evidence and conservative no-auto-delete defaults. Explicit local continuation; no existing database migrations, server, preview workaround, credentials/access changes, posting, commit/push/deploy.

Design: retain the immutable draft migration/manifest unchanged. Add a separately versioned receipt stage with an explicit pinned dependency on the ready draft stage, two owned receipt tables, scoped retry/reference/audit uniqueness and the existing shared transaction guard/installer lock. The receipt store uses the identical native DB/source adapter, locks the source row before quota/retry checks, preserves private originals/previews and audits byte access before release. Only confirmed rollback before any commit attempt may clean up that attempt's own blobs. Unknown commits retain evidence and require fresh connection/reconciliation.

Native storage binding is a server-only, unwired factory builder for the current actual native DB/User/conf and trusted office/currency/private-root configuration; it checks schema readiness and grants no permissions. No runtime globals, routes or factories are registered. The employee interface retains its real HTTP upload reader; synthetic upload sources remain test-only.

- [ ] TDD receipt migration dependency/independent markers, original draft artifact immutability and exact reruns; preserve17 migration cases.
- [ ] Implement inactive receipt DDL/manifest and metadata/audit/file store with strict source/entity/owner/office association, byte/hash/path/name validation and bounded quotas.
- [ ] TDD native User→draft→receipt→audited read/retry and controller flow; denial for cross-user/entity/office/revocation, source/schema/metadata/file/audit drift and unsafe roots.
- [ ] TDD confirmed/false/lost commit, failed rollback, no bytes on unconfirmed access audit, source locking, orphan quota and no automatic deletion/replacement.
- [ ] Implement/test the unwired native factory binding, preserving exact context and rejecting missing readiness/private configuration. Native accounting writers stay absent.
- [ ] Independent scoped review, bounded credential-free regression/lint/whitespace/cleanup and evidence. Real-engine locks/races/pooling/disconnect and multipart/binary/visual/HTTPS/ECM/backup acceptance remain explicit gates.
