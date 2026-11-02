# M058 — Local Implementation Report

## Report control

- **Mission:** M058 — Secure Internal Work Kanban Phase 1
- **Report status:** LOCAL_SOURCE_IMPLEMENTATION_PASS — RELEASE ACCEPTANCE PENDING
- **Implementation owner:** Agent 007 Central — single code writer
- **Branch:** `codex/m058-secure-internal-work-kanban`
- **Base commit:** `0351be8c571c494ab367cf12afd519cb3c71d575`
- **Result commits:** initial implementation `214292e`; local-access compatibility correction recorded by the final handoff; no push or merge
- **Environment:** clean local worktree, isolated restored MySQL 5.7 database, and Ahmed-approved local Dolibarr 23.0.3 runtime
- **Staging/production touched:** No

## Outcome

M058 now provides a Trello-like internal Dolibarr work board without reusing customer-project CP Kanban. Workers are limited to their current assigned tasks. Supervisors require an explicit active grant for an exact team. Administrators operate only in the active entity. Tasks support controlled workflow, progress, waiting/follow-up, detail/history, assignment, archive lifecycle and internal images/files with versions and cover behavior.

On 2026-08-15 Ahmed separately approved running M058 locally. The runtime/database/file-output prestate was backed up, module `2.5.0` was activated additively, all 13 existing task rows were preserved, and the `admin-agent` authenticated browser session opened the board successfully. Live acceptance exposed and corrected a Dolibarr compatibility edge: internal accounts may use `fk_soc=0`, while the loaded User object may expose `entity=0`; the authorization layer now treats those object values as unset and still proves the exact active entity and internal status from the database row.

## Base, preflight, backup and restore rehearsal

- Clean worktree: `/private/tmp/m058-secure-internal-work-kanban` from accepted M056 tip `0351be8c...`, containing deployed runtime ancestor `606b54c...`.
- Permanent diverged/dirty checkout was not switched, reset or edited.
- Dolibarr core inspected: `23.0.3`.
- Source module baseline was `2.4.0`; local runtime module was initially stale `2.2.0`. It was later backed up and updated to `2.5.0` only after Ahmed's separate local-run approval.
- Rights `500516`–`500522` were unused before implementation; no new right was auto-granted.
- Prestate had 13 `cpo_task` rows, 19 activities, no `cpo_task_asset`, and all legacy tasks lacked explicit M058 team classification.
- Full database dump: `/private/tmp/m058-preflight-oERvMT/local-database-before.sql`, 11,883,355 bytes, mode `0600`, SHA-256 `cb5b9a62faacb9976ba2237b2ff9da5b5223dcc8b8a1b3a07bab540c9a0be58a`.
- Runtime module backup SHA-256: `9c83be50fa7028caee530a64250a97119d6cd20c723d62c84f0215745d44d1aa`.
- Module output backup SHA-256: `e3f7ee4888f364da5b61859e7abd609f666d8e286d9bed4efa2fcf6033209da6`.
- The dump restored successfully to an isolated socket-only MySQL instance as `dolibarr_m058`: 429 tables and the original 13 task rows. M058 migration ran twice there and preserved the 13 rows.
- The original local database and runtime were not migrated or modified.

## Delivered architecture and permissions

`cpo_task → InternalTaskAuthorizationService → InternalTaskService → board/card/action/asset/team routes`

- New rights: `task_read_own`, `task_update_own`, `task_upload_own`, `task_manage_team`, `task_archive`, `task_delete`, and `task_admin` (`500516`–`500522`). All default disabled for non-admin users.
- Worker scope requires active internal account, own-read right, current assignment and current membership in the exact primary team.
- Supervisor scope requires `task_manage_team` and an explicit active `cpo_task_team_manager` grant; same-group membership alone is insufficient.
- Dolibarr admin or `task_admin` allows active-entity administration, but high-risk file, archive and delete actions still require their separate action right.
- Every task list/count/search/fetch/history/mutation and asset stream rechecks current scope. Unauthorized/missing objects fail without revealing metadata.
- Legacy `OperationsService` task entry points delegate to the new service. Generated operation tasks without a verified team are unassigned/null-team Unclassified tasks; team membership is never inferred.

## Schema and task behavior

- Additive `cpo_task` columns: primary team, waiting reason/party/follow-up, last actor, archive date and optimistic `version`.
- New exact-entity supervisor-grant and task-asset tables plus own/team board indexes.
- Workflow: `todo → in_progress ↔ waiting → review → done`; scoped managers may return review, cancel, archive/restore, and qualified admins may soft-delete archived done/cancelled tasks.
- Worker progress is limited to integer `0–99` in in-progress/waiting. Done sets `100` server-side.
- Every task/file mutation uses POST, Dolibarr CSRF enforcement, task-row locking, `entity + rowid + version` compare-and-swap and atomic bounded activity. Manual task creation also uses a durable request hash, named lock and payload fingerprint so replay changes state once and a mismatched replay is rejected.

## Internal files and images

- Allowed images: JPEG/PNG/WebP, 10 MB, maximum 10,000 × 10,000 and 25 million pixels; decoded and re-encoded to strip metadata.
- Allowed documents: bounded PDF, DOCX/XLSX with OOXML entry/size/ratio/path/symlink/macro validation, and UTF-8 CSV/TXT.
- Rejected classes include SVG/HTML/PHP/executables, legacy/macro Office, unrelated archives, double/hidden extensions, MIME mismatch, active PDF markers, traversal/control/bidi names, ZIP bombs, symlinks, zero-byte and oversize files.
- One file per request, 25 current logical assets and 500 MB active bytes per task; private `0700` directories, `0600` random files, quarantine and atomic same-volume activation.
- Workers access current active versions only. Scoped managers can explicitly open version history. Deleted-task assets cannot stream. M058 performs no physical task/asset deletion.

## UI and parity

- Role-specific My/Team/All/Unassigned/Unclassified/Archive/Grant views.
- Arabic, English and Chinese resources; derived RTL/LTR; `<bdi>` for mixed task/user/file data.
- Five logical workflow columns on desktop; at 390px/360px one full-width stage with localized position and previous/next 44px controls, without reversing logical DOM order.
- Card detail includes instructions, team/assignee/deadline, progress/waiting controls, role-safe transitions, assets, current cover, replace/version behavior and bounded activity. Board links open a native accessible desktop drawer with a direct-page fallback and full-screen phone treatment; lifecycle actions use native confirmation dialogs.
- Customer Portal and Mobile App are explicitly unchanged/Not Applicable. No customer/public/mobile route or DTO includes internal tasks or assets.

## Verification

| Verification | Result |
|---|---|
| PHP lint for all `chinaplanetoperations` PHP | PASS |
| `git diff --check` | PASS |
| M058 source/security/route contract | PASS |
| Isolated runtime migration rerun and 13-row preservation | PASS |
| Worker A/B/entity-0/inactive/membership isolation and malformed cross-entity projection denial | PASS |
| Same-group denial, explicit/revoked/replayed supervisor grant | PASS |
| Full workflow, forbidden lifecycle matrix, reason retention and stale-write rejection | PASS |
| Durable create replay once and changed-payload rejection | PASS |
| Active/archive/deleted alternate operation list/activity isolation | PASS |
| Generated Unclassified and external-user denial | PASS |
| Adversarial PNG/MIME/SVG/bidi/NUL/OOXML root/comment/embedded executable/macro/PDF encoded/compressed/fake validation | PASS |
| Transaction failure and storage permission/symlink failure contract | PASS |
| Interrupted migration index repair and unique-index drift rejection | PASS |
| Arabic/English/Chinese and 390px/360px UI source contract | PASS |
| M036/M045/M054/M055/unified-shell focused regressions | PASS |
| Authenticated `admin-agent` desktop board access | PASS — role tabs and empty My Tasks state rendered after the internal-user compatibility correction |
| Full authenticated desktop/390px/360px AR/EN/ZH browser matrix | PENDING — remains a pre-release gate |

All synthetic users, groups, task rows, grants and activity rows created by the isolated runtime test were removed in its cleanup. No real employee/customer value was read into test output.

## Independent local review

- Internal operations review: **PASS** — workflow, reason retention, forbidden lifecycle matrix, and active/archive/deleted operation-stream isolation.
- Security source review: **PASS** — exact entity boundaries, durable payload-bound replay control, exact readiness, storage compensation, and adversarial PDF/OOXML checks have no remaining source blocker.
- UI source review: **PASS** — role views, shell-free drawer, same-origin/source synchronization, terminal delete handling, dialogs, stale retention, upload states, localization, RTL and phone source contracts.
- These PASS verdicts accept the local source implementation only. They do not waive the authenticated browser, real web-SAPI upload, concurrency, or release gates below.

## Changed-file scope

- Classes/services: `custom/chinaplanetoperations/class/InternalTaskAssetService.class.php`, `InternalTaskAuthorizationService.class.php`, `InternalTaskMigration.class.php`, `InternalTaskService.class.php`, and modified `OperationsService.class.php`.
- Descriptor/library: `custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php` and `custom/chinaplanetoperations/lib/main.lib.php`.
- Routes: `custom/chinaplanetoperations/index.php`, `operations/index.php`, `operations/view.php`, `tasks/board.php`, `tasks/card.php`, `tasks/action.php`, `tasks/asset.php`, and `tasks/team_admin.php`.
- Schema: `custom/chinaplanetoperations/sql/llx_cpo_task.sql`, `llx_cpo_task_asset.sql`, and `llx_cpo_task_team_manager.sql`.
- UI: `custom/chinaplanetoperations/css/operations.css`, `js/internal_tasks.js`, and AR/EN/ZH `langs/*/chinaplanetoperations.lang`.
- Tests: `deploy/tests/test_m058_source_contract.php`, `test_m058_runtime.php`, `test_m058_asset_validation.php`, `test_m058_failure_contract.php`, and `test_m058_ui_contract.php`.
- Documentation: `docs/CHANGELOG.md`, `docs/REPORTS/M058_COMPLETION_REPORT.md`, and `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`.
- No CP Kanban, Customer Center implementation, mobile API/app implementation, Logistics, Orders, Finance, core Dolibarr, deployment helper or release workflow file changed.

## Risks and remaining acceptance

- Authenticated desktop board access was verified with `admin-agent`; the full role/language/responsive/interaction browser matrix remains pending.
- Actual uploaded-file HTTP orchestration was not exercised through a real web SAPI; validators, headers, route gates, AJAX busy/retry source contracts and isolated service contracts passed.
- The full every-DDL-step interruption/full-restore matrix, concurrent cover/replace/upload quota matrix, injected failure at every file/DB boundary, and authenticated desktop/390×844/360 AR/EN/ZH browser matrix remain mandatory pre-release acceptance. They require a separately approved parity-correct synthetic runtime.
- Before any release, use a separately approved parity-correct synthetic runtime, grant only the required rights, and run desktop/390px/360px AR/EN/ZH role and upload acceptance.

## Rollback

- Code rollback: revert the eventual local M058 commit; no push/merge exists.
- Normal local rollback: stop using M058 code and retain inert additive schema/right definitions for review.
- Exact-prestate database rollback: restore the recorded full SQL dump and validate table/task/right counts and representative hashes.
- File rollback: preserve task bytes/metadata; disable routes/code. Do not physically delete evidence automatically.

## Environment boundary

The approved local Dolibarr runtime and local database were backed up and activated for M058. No staging, production, VPS, Git push, merge, customer message, external integration, accounting record or customer-visible data change was performed.
