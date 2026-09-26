# M094 — Task attachments and team members

Status: IN_PROGRESS. Ahmed requested 200 MB per internal task file, correction of JSON upload failures, and missing members in the Egypt Operation assignee picker. Prior direct-live release authorization continues for this bounded repair.

Diagnose runtime upload limits and native membership records first. Keep allowed file types, internal file access, per-task 25-file/500 MB quota and content safety checks. Correct assignment options and server validation consistently using existing native membership; do not grant new memberships or rights. No customer/public attachment policy changes.

Validate 200 MB boundary, multi-file persistence, readable upload failures, assignment positive/negative cases, desktop/mobile EN/AR, full task regression, immutable release with scoped backup and rollback, and authenticated live acceptance.

Release includes the prepared M093 controls/identity update in one artifact over the verified M092 baseline; M093 was not applied because its stale real-task smoke fixture stopped preflight. Use task-independent read-only permission checks. Native Apache overrides are limited to tasks/asset.php; no proxy, core, global PHP, rights, membership or schema changes.
