# M108–M115 local candidate — 2026-10-24

The approved M108–M115 candidate has passed the final local native-project browser acceptance. It is not yet deployed. Earlier M107 remains the current production release. Production core stays 24.0.1; existing local core is 24.0.2. Source: isolated release-work/source, branch codex/m107-customer-project-workspaces. Ahmed explicitly authorized publication conditional on verification and no permission changes.

## Implemented

- New project-board cards create one native Dolibarr Task using the existing card reference; stable unique mapping and failure rollback prevent duplicates/orphans. Generic boards retain their own behavior. No existing-card backfill.
- New cards inherit customer/project and a single valid readable shipment. Multiple shipments require choice; deliberate changes are validated. Old empty links derive context read-only. Explicit project change moves the SAME card and native task to the destination board's first active list, preserving task identity, assignments and access grants. Both boards/native projects must be editable. Native time, task structure, contacts, documents and dependent records block implicit transfer for reviewed handling.
- Customer gallery shares General board tiles, favorites, responsive shell and controls; priority is shared, stars/order private. Filters, keyboard move buttons, CAS and ownership/entity checks remain server enforced.
- Board removal is recoverable through Deleted boards. Original project/task records, board identities, memberships, comments and assets are retained. Project mapping stays attached to preserve authorization scope.
- Independent board Done/reopen; task Done/reopen synchronizes the existing native link. Board completion never bulk-completes tasks or closes a native project; remaining open tasks are explicit. Counts use a single server policy: unarchived canonical tasks/placements in active lists; canceled/deleted excluded. Archived boards retain their independent completion state. Customer totals use scoped aggregates, independent of the 200-project display bound.
- Native project deadline uses ERP calendar-day semantics: before today Overdue, today/+1/+2 Urgent, exactly +3 not urgent; completed native projects suppress urgency, missing date explicit. Separate from shared manual priority.
- Native left menu gets one localized, permission-checked Boards shortcut without module reinitialization or rights writes.
- Customer container color/image settings project onto the root customer card. Project cards reuse actual board color/image. Green tabs replaced by General board neutral/navy/blue palette. Color contrast adapts; images use overlays and private raster delivery.

## Evidence

Real native model suites on BOTH official 24.0.1 and local 24.0.2: M108 26 assertions, M109/M110/M115 41 assertions, M111/M112/M114 42 assertions per version. Legacy M097 58, M099 shared documents 78, M107 SQLite 28, native sidebar 8, completion fault injection 6, renderer/link-filter tests and syntax/diff checks pass. Assertions cover idempotence, original-record preservation, permission failures, cross-entity/customer mismatch, transfer/native-ID preservation, archive-aware totals and >200 projects, Done/reopen, date boundaries and rollback.

A real two-connection concurrency test required >0.5 seconds overlap and verified completion preserved a concurrently changed native title, private note, duration and workload. The first test exposed REPEATABLE READ snapshot behavior even after a row lock; the final implementation writes ONLY native status/progress under lock and invokes TASK_MODIFY, without rewriting notes/time/extras/dates or invoking native auto-close. Six fault cases verify cleanup and original error preservation.

Independent source review/recheck confirmed its four findings fixed: row lock/narrow completion, consistent archive counts, totals beyond 200 and failed-commit cleanup. Its review was not integration acceptance and preceded the latest M115 appearance addition.

Authenticated local UI: shared priority, favorites, filtering, order save/reload, Arabic navigation, persisted board Done/reopen, deletion/cancel/restore, inherited card references save, 44px controls and mobile no horizontal page overflow at CSS 390px. Native sidebar appears on the native project route and goes to Boards, with no duplicate. The initial native UI permission coverage gap was subsequently resolved by the human preparing existing account 1; the agent did not grant or change any rights.

M115 color was saved through the real authenticated form; root and inner RGB matched. Later the user selected a local image; its persisted metadata, same root/inner image URL and reload were verified. The automated synthetic PNG filechooser failed before its subsequent Save step; no retry or bypass occurred. Do not claim automated upload success. Screenshots containing the user's selected photo are internal only and excluded from delivery.

## Final native browser acceptance and release state

After the human prepared the existing local test account, account 1 had native project read/create rights. The agent made no account/credential/permission changes. A new full local backup was taken before creating two isolated synthetic customers/projects (prefix M116-BROWSER-b54816d1).

Using the actual authenticated local 24.0.2 HTTP browser session, project board 177 created card 637 / CP-TK-2026-000057 and native task 134. The native project task list displayed that same reference/title. Mark Done produced native Closed/100%; Reopen produced native Validated/0%. Changing the customer/project links moved the SAME card and native task to project 1930 / board 178 under the second customer. Reloaded Link ERP selected the second customer/project, and the native task page displayed project B with the same task reference. The two boards intentionally used different teams; task assignment and access were not expanded. Mark board Done preserved its open task and native project, displayed the open-task warning, survived reload, and could be reopened. Read-only DB verification confirmed the single mapping/placement and unchanged open project states.

Evidence: task workspace workspace-gallery/native-browser-acceptance/{persisted-state.json,cleanup.json,screenshots/}. Full private backup SHA256 d4eae1dceefd88c2fa3cf190de3e35e75def915b16257784ed925b87174ace73. Only owned synthetic records were cleaned transactionally, then all 25 saved original table hashes matched, including user/account rights and the human-selected image/settings. App 8107 and DB 8889 remain running; private 8117 remains stopped.

A small browser-observed localization defect (task_reopened activity key) was corrected in EN/AR/ZH. No other runtime source changed after the dual-version integration gate. Final syntax/diff and localization checks are required at the publication pin.

Earlier review-only commit/push was rejected before execution. Later explicit human push-live authorization supersedes that scope limitation; production publication remains conditional on final runtime preflight. The offered Termius window currently displays root@srv1011043 and the expected address, but supported input did not yield a fresh hostname/id result; one coordinate attempt reported no window available. No deployment command was submitted. Await the human bringing the existing session to the foreground; do not bypass the restricted codex-deploy boundary. There are no new production writes.

Rollback must retain the six new additive tables and all user business writes. Restore pinned pre-release code and prior feature flag/hook values, never restore an old production database over new writes. No module reinitialization, native rights change, production core upgrade or cpkanban retirement is included.

## cpkanban recommendation

Retain cpkanban. Customer Center project-status triggers, mobile stage queries, customer-visible assets/reviews/publication and customer project/shipment/document projections still depend on its own records and private files. New Boards can become the internal team interface, but they are not a replacement for these portal/mobile dependencies. Current cpkanban comparison is source-only; fresh live counts/hash parity and a separately approved retirement/migration decision are still needed. Nothing was disabled, deleted or migrated.

Relevant skills: Dolibarr operator/developer, Impeccable audit/craft-floor and web-design-guidelines. Regex detector reported only the inherited Arial choice; it is preserved for incumbent consistency. Rendered checks were performed separately; detector output is not a full accessibility certificate. Customer Center/Mobile behavior is unchanged; no portal publication or new customer access is introduced.

## Final delivery / reconciliation addendum

Later read-only metadata confirms customer 119 color #749ce7/version 1 and customer 202 image/version 2. Root/inner image URLs were equal and still present after reload. The selected image includes a person and is excluded from all Library deliverables. A clean screenshot of customer 119's color header and its differently colored project card is included instead.

Final native project/task/CPO task/placement/list/member/details and user/user-rights/group-rights hashes match the original baseline. Original native customer business columns also match the pre-feature backup; later differences are limited to tms (100/119) and fk_user_modif (100). Customer 100's workspace toggle was enabled during the user's review. These later user-side settings/metadata are preserved, not reset.

Library upload was initially rejected before execution. After Ahmed explicitly selected uploading the screenshots and bundle to his Library, the bounded retry succeeded: desktop libfile_5752a803e4108191a176ca44e69806f7, mobile libfile_9b6f44eacf488191b4d2ddb88c156762, candidate ZIP libfile_e79f6b0f8fa481918cc5deee59071305 (all version 0). These are the earlier candidate delivery; current release evidence remains local until final delivery update. No private DB, credentials or user-selected photograph is included.
