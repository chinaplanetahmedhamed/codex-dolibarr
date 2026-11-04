# M089-C03 — Personal task experience

Local implementation and acceptance completed on 2026-09-24. Branch: `codex/m089-personal-board`, based on the deployed R3 delivery `e043540f0ef2335efba65fce06edce31e934a711`. Hosted staging and production have not been changed by this mission. R3 release artifacts remain unchanged.

## User-visible behavior

- An internal worker with own-task access lands on My day after a normal Dolibarr login. Managers retain their existing landing policy. My day shows only authorized assigned work and links to Add task and My Tasks.
- Today, task board and task details use a flat layout for employees and administrators. Other business screens retain their existing layout.
- Personal boards support add, rename, color and reorder of columns, drag/drop of tasks (including within a list), and an accessible Move to dialog. Column names, colors, order and card positions belong to the authenticated user and entity. Existing columns are retained, up to 30 columns.
- Board lists are 292px wide on desktop, with bounded height and their own vertical scrolling. At phone widths they stack vertically; the page does not require horizontal scrolling. Text cards keep their width and compact title; image covers have a fixed preview height.
- The task composer includes description, priority, deadline, start date, labels, notes, typed custom fields (text, number, date, checkbox), initial checklist, initial comment and multiple images/files. A task can be created directly in a selected personal column. Images or colors can be covers.
- Base task, extra metadata, checklist, comment and selected-column placement are transactional and idempotent. Attachments upload sequentially using the existing authenticated asset service. A partial upload leaves a link to the saved task for continuation instead of silently duplicating it.
- Existing task lifecycle, comments, checklists, file limits and secure previews remain in use. Workers edit instructions and extra metadata only on eligible tasks they created; assigned work retains its existing progress/comment/file/checklist permissions.

## Boundaries and data

Personal columns are an organizational view, not operational stages. Moving to any personal column does not approve, complete, reassign or change the operational status of a task. Each card continues to show its actual status.

New table: `llx_cpo_task_details`, unique `(entity,fk_task)`, JSON metadata and optimistic version. Native `llx_user_param` stores the personal board under `CPO_PERSONAL_BOARD_V1`; writes serialize through the authenticated user's row and reject stale versions. No core tables are altered.

The explicit `InternalTaskExperienceMigration::apply(entity)` installs and checks the additive table and appends the standard `login` hook context without replacing other contexts. The module descriptor also declares this hook for normal module installation. No migration runs automatically on a page read or save. The login hook changes the in-memory landing page only, without changing saved user preferences.

No new user permissions, credentials, customer visibility or external integrations. A pre-existing page bug was fixed: a reader without the separate task-files permission can open authorized task details, while attachments and image previews stay hidden/denied.

## Verification

Runtime: local Dolibarr 23.0.3 / PHP 8.4.7, isolated synthetic M089 database on loopback. The local PHP development router supplies an empty QUERY_STRING as Apache does; no core file was modified.

Automated checks: 353 passing assertions across M089 personal boards (31), prior own-task behavior (30), Kanban access (10), M058 runtime (39), M060 runtime (28), M058 source/UI/assets/failure contracts (109), and M060 source/UI contracts (106). PHP syntax passed for every changed PHP file (14); both JavaScript files passed syntax checks; `git diff --check` passed.

The personal-board suite covers field persistence, duplicate and changed replay, selected-column creation, stale updates, own/other-user/admin column isolation, insertion before untouched cards, preservation of operational status, manager-owned instructions, forged task access, external/inactive/cross-entity actors, invalid HTML/CSS, and full rollback when a child save fails.

Authenticated browser acceptance:

- Worker login lands on My day; second worker also gets his own My day.
- Created local task 15 with Arabic description, deadline/start date, label, numeric field, notes, checklist, comment and an uploaded synthetic PNG cover. Reloaded and edited notes; completed a checklist item (1/3), with board counters retained.
- Renamed/recolored a personal column, added/reordered another, dragged task 1 to Waiting, and reloaded to confirm persistence. Moved task 15 through the phone Move to dialog to personal column 6 and confirmed persistence.
- Created local task 41 directly in column 6, with a color cover and an image attachment that is not the cover. The colored header and attachment were verified on the saved card.
- Second worker sees empty default columns, with no colleague tasks or personal names. Direct access to task 15 is denied. The manager's board retains its native columns and task 15 remains in the operational To Do stage, regardless of the worker's personal placement.
- Desktop and 393x852 phone views inspected, including Arabic RTL board/drawer and English My day/composer. Measured document width is 393px with no page overflow. Lists have vertical scroll (460px client height versus more than 1300px content in the populated phone list).
- Shared dark-shell color inheritance and the old 460px mobile hero flex-basis were corrected during visual testing. Details without file permission were separately verified as readable after the fix.

Client-side CSRF handling delegates to the same native Dolibarr `CSRFCHECK_WITH_TOKEN` mechanism as the existing task endpoints; a forged-token browser submission was not independently performed in this local acceptance.

## Release and rollback

This is ready for local review, not a claim of hosted acceptance. A new pinned staging release must include the shared shell, operations descriptor/login hook, new services/table installer, task routes, both JS files, CSS, renderer and translations. Existing stage overlays must be retained. Do not reuse the frozen R3 11-file helper.

Before hosted apply: backup affected code and the full database, verify exact target versions/state, run the additive installer, register the login hook, then deploy the dependency-complete code and repeat authenticated worker/admin acceptance. Promote the exact staging-tested artifact only after release approval. This mission's new schema/behavior is not covered by the old R3 production approval.

Code rollback must also restore the previous hook-context constant if the hook class is removed. Retain the additive metadata table and user preferences; they are ignored by old code and must not be deleted. Existing task/collaboration/file data stays valid.

The existing 500-task board query cap remains. File constraints remain those of M058 (25 files / 500MB per task, 10MB images/text and 50MB PDF/Office). Rich metadata is limited to 20 labels and 30 custom fields per task. This is the requested task experience, not a claim of every Trello feature.

ERP desktop/mobile parity: implemented and locally verified. Customer portal and native mobile app: not changed or newly exposed. No live employee/customer records were modified.
