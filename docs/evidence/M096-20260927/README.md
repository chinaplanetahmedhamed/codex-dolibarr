# M096 request archive and delete verification — 2026-09-27

Status: LOCAL IMPLEMENTATION VERIFIED; production unchanged. Current source baseline before this work: 3535f5c on codex/m091-task-agenda. The new request-management scope needs release authorization before production preflight/migration/deployment.

## Behavior

Active, Archived and Deleted folders have per-request Archive, Delete and Restore controls. Delete requires a confirmation naming the exact reference and contact, preserves data and can be restored to Active. Archive/restore retain business status, immutable source payloads, customer/operation/quotation links and deduplication keys. A repeated website submission cannot resurrect deleted work. Linked operations or quotations prevent deletion; those requests can still be archived. Archived/deleted records remain readable to authorized internal staff but must be restored before status edits, quotation creation, promotion or contacting the customer through this request page. No permanent or bulk deletion.

Existing native publicrequest_read plus publicrequest_update permissions authorize management. Server-side actor, active-user, external-user and entity checks complement the page gate. POST and native CSRF protection are mandatory. Transitions share the existing quotation lock, lock the request row, require the displayed lifecycle version, and commit the state plus actor/time/history together. Unknown lifecycle errors return a bounded translated message. No permission definitions/grants or public integration changes are shipped.

## Verified

- 41 actual-service assertions in runtime.txt: native admin/staff allowed; read-only, external, inactive and other-entity identities denied; archive/delete/restore; source/status preservation; deduplication; linked-record protection; inactive request processing blocked; stale version rejection; database failure rollback; busy-lock rejection (fault injection).
- Nine migration assertions in migration.txt: a pre-existing request created with the original schema receives Active/version 1 without changing its qualified status or payload. Repeat migration is inert. Prior-code queries remain compatible; schema drift is rejected. The added index uses its own folder name, preserving a pre-existing unrelated qualification index in the QA database.
- Existing M043 intake runtime passed: quote and shipping intake, commercial snapshots, retry/conflict behavior and historical JSON decoding. Six relevant source/service regression suites passed, including quotation and customer-contact boundaries. The M055 source assertion was updated to require the additional active-request check while retaining the existing update-right check.
- Changed PHP syntax and git diff checks passed.
- Native authenticated browser at 127.0.0.1:8101, Dolibarr 23.0.3, synthetic QA worker: without read permission the page denied access; with temporary native read/update rights, archived a synthetic request, reloaded Archived, deleted after confirmation, reloaded Deleted, restored, and observed it back in Active. Database reread confirmed version 4 and exactly archive/delete/restore audit entries.
- Missing token rejected by main.inc.php; forged token canceled by native CSRF handling; GET mutation rejected. After revoking update permission, submitting an already-open valid form denied access, and fresh read-only navigation showed no management buttons. None changed the fixture state/history.
- EN/AR desktop and 390px phone layouts visually checked. Corrected shell CSS collisions affecting new folder labels and confirmation contrast, native fixed cell heights on phone, and language propagation through folder/action links. Phone rows show labeled fields and reachable actions. Chinese strings supplied; native Chinese visual acceptance not performed.
- ui-cleanup.txt records removal of only synthetic UI records, temporary local grants/definitions, QA constant and negative-test HTML. Production and actual business records were untouched.

A real two-connection lock-contention run could not open a second connection in the local PHP/MySQL setup (greeting failure); no server settings were changed. Busy-lock handling is covered by explicit fault injection, while successful transitions and row locking use the real database. Do not describe this as an end-to-end concurrent quotation race test.

## Release and rollback

Deploy the dependency-complete source files with the additive migration m096_public_request_lifecycle.php after scoped code/database backup and verified preflight. Its CLI requires M096_DOL_DOCUMENT_ROOT explicitly and accepts check/apply only. Three columns and one index are additive; no existing rows are deleted or reclassified during migration. Validate actual live schema, artifact hashes, native staff access and archive/restore on an approved synthetic request after deployment. No release has occurred in this task.

Preserve lifecycle columns/history during code rollback and never restore the whole database over subsequent business activity. Old code can still read the rows but does not filter archived/deleted requests; rolling back would make those requests visible in the old list. Prefer a forward correction if hiding them must be maintained. Customer Center, mobile client and public website behavior are unchanged; the affected interface is internal ERP request administration.
