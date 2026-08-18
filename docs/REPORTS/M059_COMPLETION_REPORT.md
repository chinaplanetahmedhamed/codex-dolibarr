# M059 — Local Implementation Report

## Report control

- **Mission:** M059 — Internal Task Kanban Navigation and Configurable Work Stages
- **Report status:** LOCAL_IMPLEMENTATION_PASS — RELEASE ACCEPTANCE PENDING
- **Implementation owner:** Agent 007 Central — single code writer
- **Branch:** `codex/m058-secure-internal-work-kanban`
- **Base commit:** `85a4b24bad322db016caf6d8f38a85c276c6381d`
- **Implementation commit:** `4966ef0f282e5a848e9562d6646923258ac59cfe`
- **Environment:** Ahmed-approved local Dolibarr 23.0.3 runtime and an isolated socket-only MySQL 5.7 restore
- **Staging/production touched:** No

## Outcome

The internal-task board now opens directly from one **Team Tasks** Dolibarr menu item. Its default view follows the frozen precedence: administrator or `task_admin` opens **All Tasks**, a team manager opens **Team Tasks** even with zero grants, and an own-task worker opens **My Tasks**. Unauthorized or unknown view requests fall back without exposing hidden counts or filters.

Authorized managers can create a task from a prominent native dialog. A primary team and expected result are required; the optional assignee list is loaded only from active exact-entity members of the chosen team. Administrators can use a dedicated drag handle, while every role receives the same server-authorized keyboard/touch **Move to** dialog.

Administrators can add, rename, recolor, activate/deactivate and reorder work columns from **Manage Columns**. Protected workflow stages remain fixed, in-use stages cannot be deactivated, and waiting/review/done behavior continues through the M058 lifecycle service. No CP Kanban table, route or source file is reused.

### Local acceptance correction

Ahmed's first live Move-to attempt exposed `Unexpected token '<'` because the local Dolibarr shell publishes an empty `document.baseURI`. The browser's `form.action` property consequently resolved to an empty string, so the AJAX POST returned the board HTML instead of the JSON action route. The correction now resolves the literal form `action` against `window.location.href`, enforces same-origin, requests JSON explicitly and converts any non-JSON response to the localized bounded failure state. The same safe resolver covers AJAX file uploads. After the runtime file backup and local apply, the authenticated page resolved the move endpoint exactly to `/custom/chinaplanetoperations/tasks/action.php`; the dialog opened cleanly with no console errors and no business row was changed during verification.

A second local acceptance correction covers Dolibarr's standard entity-zero superadministrator and Trello-style administrator movement. The central active-user gate now accepts an entity-zero actor only when both the current Dolibarr actor and its active internal database row are administrators; entity-zero non-admin, wrong-entity and external actors remain denied. Administrators may now drag or use **Move to** from any incomplete stage directly to any active configured stage, including **Done**; worker and supervisor lifecycle restrictions remain unchanged. Isolated regressions prove the real entity-zero administrator predicate and direct `To Do → Done` completion (`progress=100`). The locally loaded board exposes Done as an allowed target and no business card was moved during verification.

## Schema, migration and lifecycle

- Added exact-entity `cpo_task_stage` rows with bigint primary key, immutable `(entity, code)`, localized labels, validated color, behavior, protected/system flags, position, active state and optimistic version.
- Added `cpo_task.fk_stage` and the exact board index.
- Seeded protected `todo`, `in_progress`, `waiting`, `review`, `done` and inactive `legacy_unknown` stages per initialized entity.
- Backfill is deterministic and idempotent under a migration lock. Pre-M059 terminal rows without prior-stage evidence use only `legacy_unknown`; new cancel/delete paths retain the real current stage.
- Stage administration and task movement use POST, CSRF, exact-entity authorization, row/config locks, compare-and-swap versions, atomic status/stage updates and bounded activity with immutable stage codes.

## Navigation and UI

- Module version is `2.6.0` with exactly one direct top Team Tasks menu row and a Manage Columns child.
- Corrected the menu condition to the expression form supported by Dolibarr's `verifCond`; authenticated `admin-agent` now sees `Team Tasks` as the selected current menu.
- The Kanban is generated from active configured stages; phone navigation says localized **Stage X of N** and retains logical order in RTL.
- New Task, Move to and required lifecycle inputs use native labeled dialogs with busy/error handling, focus restoration and 44px controls.
- Desktop, requested 390×844 override and 360px override passed without page-wide overflow; one column is shown on the phone board. The New Task dialog is full width, and Manage Columns also fits without horizontal overflow.
- Arabic, English and Chinese source/localization contracts pass. Full authenticated AR/ZH interaction acceptance remains a release gate.

## Backup and restore evidence

- Full pre-activation database dump: `/private/tmp/m059-local-activate-qTi4Rm/local-database-before.sql`, 11,917,804 bytes, SHA-256 `89fcb77d47331431d15596270f61c127fa3c9f037cb5e04fa44a71e944a72d85`.
- Runtime module backup SHA-256: `6fd0c40d5e3acc4a9a9b96fef6b7fee354723ec8e5cb87a33f529058d9282953`.
- Module data backup SHA-256: `52dd883602242046b017dc4ea7efb3ee5e6fb83778feb61ce1b911792d1426f1`.
- The dump restored successfully into isolated socket-only MySQL database `dolibarr_m059`: 431 tables, 14 tasks, 21 activities and 339 rights before M059 migration.
- Local and restored databases finish with the same 14 task rows and task-stage hash `0b7f43e0b51d3c79e5ab617cc7f894e1f4de2fe62bc38b3a3d6d92e69efd0eb8`.
- CP Kanban totals remained 12 columns and 44 cards across entities in both prestate and local runtime.

## Verification

| Verification | Result |
|---|---|
| PHP lint for module and focused tests | PASS |
| JavaScript syntax and `git diff --check` | PASS |
| M059 source/security/schema contract | PASS |
| M059 UI/localization/responsive contract | PASS |
| Isolated M059 migration, role-default, stage CRUD/move/retention runtime | PASS |
| M058 source, UI, adversarial asset and failure contracts | PASS |
| Isolated M058 authorization/workflow/archive/delete runtime regression | PASS |
| M036/M045/M054/M055/unified-shell/shipping-shell regressions | PASS |
| Authenticated menu/current state, board, New Task and Manage Columns | PASS — `admin-agent` |
| Authenticated desktop/390/360 English responsive behavior | PASS |
| Empty-`baseURI` AJAX move/upload endpoint regression | PASS — exact same-origin action URL and safe non-JSON handling |
| Entity-zero Dolibarr superadministrator authorization regression | PASS — verified dual actor/database administrator predicate |
| Administrator direct move to Done | PASS — target exposed and server sets completion to 100% |
| Full authenticated AR/EN/ZH role and fault matrix | PENDING — mandatory before release |

No synthetic test row remains in the isolated database after cleanup. The active local database contains 14 tasks, 21 activities, six seeded stages, zero null stage references and zero wrong-entity stage references.

## Isolation and remaining gates

- No file under `custom/cpkanban/`, `custom/chinaplanetcustomercenter/` or `mobile/` changed.
- CP Kanban project/card data and project sync remain unchanged.
- Customer Portal, public APIs, mobile app, staging and production were not changed.
- Before release, run the full authenticated AR/EN/ZH worker/supervisor/admin matrix, concurrent stage/task mutation tests, every-step migration/fault restore matrix, and actual business-user acceptance in a separately approved parity-correct environment.

## Changed files

- Services: `custom/chinaplanetoperations/class/InternalTaskAuthorizationService.class.php`, `InternalTaskMigration.class.php`, `InternalTaskService.class.php`, and new `InternalTaskStageService.class.php`.
- Descriptor/schema: `custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php`, `sql/llx_cpo_task.sql`, and new `sql/llx_cpo_task_stage.sql`.
- Routes: `custom/chinaplanetoperations/tasks/action.php`, `board.php`, `card.php`, and new `stage_action.php` and `stages.php`.
- UI/localization: `custom/chinaplanetoperations/css/operations.css`, `js/internal_tasks.js`, and `langs/ar_SA`, `langs/en_US`, `langs/zh_CN` `chinaplanetoperations.lang` files.
- Tests: `deploy/tests/test_m058_runtime.php`, `test_m058_source_contract.php`, and new `test_m059_runtime.php`, `test_m059_source_contract.php`, `test_m059_ui_contract.php`.
- Documentation: `docs/CHANGELOG.md` and `docs/REPORTS/M059_COMPLETION_REPORT.md`. The canonical M059 Mission status was updated outside this source worktree to `DEVELOPER_REPORTED`.

## Rollback

Restore the verified local database, runtime module and data backups above. Normal code rollback must first disable `fk_stage`-dependent menu/routes/readiness, then leave the additive schema inert without deleting task, stage, activity or file evidence.

For the local acceptance correction only, restore `/private/tmp/m059-c01-qEWyc8/internal_tasks.js.before` (SHA-256 `986859b240ec0efe9d694b5ac6fd46b7e72cb08b305054de4b6913fcdb57ab95`) to the runtime JavaScript path.

For the administrator-access/direct-Done correction only, restore `/private/tmp/m059-admin-access-tipQxF/InternalTaskAuthorizationService.class.php.before` (SHA-256 `32d4eafd4a2a581d0b0a6a1323fac6802ef8066594f9e07e125631b4480acf3e`) and `/private/tmp/m059-admin-access-tipQxF/InternalTaskService.class.php.before` (SHA-256 `cb7bb3fa2ab68df0d46d3d4601b7737fbe04d93ec3cd6149fcea92cee533befc`) to their runtime class paths.
