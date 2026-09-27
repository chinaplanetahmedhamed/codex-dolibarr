# M096 — Website request archive and delete

Status: READY_FOR_REVIEW. Implemented and verified locally; NOT DEPLOYED.

Ahmed requested Archive and Delete on the Public website requests page. Added separate Active/Archived/Deleted views, row/detail actions, confirmation for recoverable deletion and Restore. Linked operation/quotation source requests are archive-only. Existing request-update permissions are reused; public submissions and native business objects are preserved.

Changes are in the public request service/page, quotation/contact inactive-state guards, scoped operations CSS and EN/AR/ZH language files. Three additive request-table fields store lifecycle state, optimistic version and audit history; an idempotent CLI migration and schema index support existing installations. No roles, grants, public customer visibility or financial records changed.

Verification: 41 lifecycle assertions, nine migration assertions, the existing real intake runtime and six relevant regression suites passed. Authenticated native browser covered archive/delete/restore with reload persistence, negative CSRF/GET, revoked permissions, read-only view and EN/AR desktop/phone layouts. PHP syntax/diff checks passed. The busy-lock failure path uses fault injection; actual dual-connection concurrency and Chinese visual checks are not claimed.

Evidence and release/rollback limits: [M096 verification](../evidence/M096-20260927/README.md). Exact implementation is on codex/m091-task-agenda over baseline 3535f5c. Production authorization, target preflight, scoped backups, deployment and live acceptance remain pending for this new feature.
