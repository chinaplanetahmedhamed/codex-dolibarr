# M094 — Task attachments and team members

Status: IN_PROGRESS. Ahmed requested 200 MB per internal task file, correction of JSON upload failures, and missing members in the Egypt Operation assignee picker. Prior direct-live release authorization continues for this bounded repair.

Diagnose runtime upload limits and native membership records first. Keep allowed file types, internal file access, per-task 25-file/500 MB quota and content safety checks. Correct assignment options and server validation consistently using existing native membership; do not grant new memberships or rights. No customer/public attachment policy changes.

Validate 200 MB boundary, multi-file persistence, readable upload failures, assignment positive/negative cases, desktop/mobile EN/AR, full task regression, immutable release with scoped backup and rollback, and authenticated live acceptance.

Release includes the prepared M093 controls/identity update in one artifact over the verified M092 baseline; M093 was not applied because its stale real-task smoke fixture stopped preflight. Use task-independent read-only permission checks. Native Apache overrides are limited to tasks/asset.php; no proxy, core, global PHP, rights, membership or schema changes.

## Release status — 2026-09-26

DEPLOYED; authenticated production browser acceptance remains pending stable ERP window control. M093/M094 shipped in M094; M095 shipped separately. See dated production-release evidence. Local authenticated workflows, runtime regression and release rollback tests passed.

## C01 - Standard Office metadata upload rejection, 2026-09-27

Ahmed reported 7621.xlsx rejected with HTTP 422 after M094. Continue the existing bounded live upload repair authorization. Reproduced a validation defect: standard OPC core-properties relationship is rejected because its namespace includes package. Permit only that exact standard metadata relation; retain external-link, macro, embedded-object, unsafe-path, DTD, decompression and size checks. Add allowlisted localized rejection messages without exposing arbitrary exceptions. No schema, permission, quota, record or infrastructure-policy change. Exact Moaz file and production error-log correlation remain unverified; root GUI session input currently unavailable.
