# M096 — Archive and delete website requests

Status: READY_FOR_REVIEW. Ahmed explicitly requested Archive and Delete in Public website requests on 2026-09-27. This authorizes bounded implementation and local verification. Production release for this new request-management scope is not yet authorized.

Use existing internal publicrequest_read and publicrequest_update permissions. Add Active, Archived and Deleted views, per-request actions, deletion confirmation and Restore. Delete is recoverable and preserves intake evidence, deduplication keys and history. Requests linked to an operation or quotation may be archived but cannot be deleted. Restore returns to Active, preserving the business status and links. No bulk or permanent deletion, public API changes, permission grants or changes to linked records.

Add lifecycle state, version and audit history to the existing request table through an idempotent additive migration. Default existing records to Active. Serialize transitions with quotation creation and row locks, reject stale versions, preserve original status and immutable snapshots. Archived/deleted requests cannot be processed until restored. UI is translated EN/AR/ZH and usable on desktop/phone.

Verification: native-user positive/negative access, entity isolation, old/new request transitions and restore, stale submissions, linked-record protection, deduplication, transaction rollback, POST/CSRF enforcement, local authenticated persistence and rendered desktop/phone EN/AR. Rollback code only, retaining lifecycle/audit columns and all business data; prior code would show hidden requests again, so use a forward correction if preserving those views is required. Customer Center/mobile/public website are not affected; this is internal ERP intake administration.

Local implementation and native browser acceptance passed. See `docs/REPORTS/M096_COMPLETION_REPORT.md` and `docs/evidence/M096-20260927/README.md`. Production unchanged; release approval pending for this new scope.
