# M089-C03-R1 — combined Kanban release candidate

Status at 2026-09-26 Asia/Shanghai: **deployed on production and staging**. The candidate description below records the release preparation; the outcome is recorded at the end of this file.

Source branch: `codex/kanban-c03-touch-release`, code commit `ca07fb59e0532a1d89b24fefdeb2dc08a4f20d10`. Base hosted release: `M089-C02-R3` / `b793819793791ef4dc35ff93b1e8a70d44451e01` (both staging and production status checked on 2026-09-26).

## Scope

- M089-C03 personal task board and rich task experience, including additive `llx_cpo_task_details` schema and `login` hook registration.
- Shared Pointer Events drag handling for internal project, manager task, and personal task boards. Mouse card-body drag remains supported; touch and pen use the visible handle so ordinary scrolling and card taps remain available.
- Authenticated read-only board revisions and eight-second visible-tab checks to refresh other open board views after card changes. Reload waits while a dialog, text editor, or drag is active, and restores scroll position.

The release file set is the 28 paths under `custom/` in `git diff --name-only b793819..ca07fb5 -- custom`. The committed refresh test is `deploy/tests/test_kanban_refresh.js`. Do not include unrelated dirty Trello redesign edits from `codex-dolibarr-m089-own-tasks`.

## Verified locally

- PHP lint for every changed PHP file and Node syntax for every changed JavaScript file passed.
- `deploy/tests/test_kanban_refresh.js`, `deploy/tests/test_m089_kanban_access.php`, and `deploy/tests/test_m089_release.py` passed; `git diff --check` passed.
- The prior isolated M089-C03 implementation passed its local synthetic and browser checks before this combined change. Its database-backed test did not run against this candidate because the isolated test database/runtime is unavailable here.
- A physical Windows Chrome/Edge touch and pen pass has not occurred.

## Deployment gate

Do not use the frozen R3 deployment helper. It contains only the old file allowlist and no C03 schema migration. Before staging apply, compare all 28 target paths to their current hosted hashes, reconcile the existing hosted overlays (notably `operations.css` and the operations module descriptor), and build an immutable, checksum-pinned C03 artifact with its own fixed-target helper. Back up the exact code targets and full database, run the additive migration and hook registration, then verify authenticated worker/admin board behavior and rollback. Promote only the exact staging-accepted artifact to production, with a fresh production code/database backup and native acceptance. If any preflight, schema, authorization, or acceptance check fails, stop and restore the code; retain additive records and review hook context as described in `M089-C03-local-review.md`.

The available restricted `codex-deploy` sudo grants only M089-C02-R2/R3 helpers. No C03-scoped deployment helper is installed, so no staging or production apply was attempted.

## Deployment outcome — 2026-09-25 17:25 UTC

Ahmed explicitly requested the live push. A checksum-pinned 28-file artifact (`9dc75cfce6b05709f5d4325bcc30600aa82c6e104bf215c5d870e9de561383aa`) merged the existing production operations descriptor and CSS with C03. Both environment preflights passed their exact hosted hashes. The mission-specific deployer is `deploy/releases/m089-c03-r1/deploy.py`; manifest SHA-256 is `aeaf1736474196812c7a6798a855064e8c9cdce8c5dc72bae3288ecb4d511c7a`. The first staging apply stopped before changing code or database because MariaDB uses a different dump header; after correcting the header check, the full database backup, code apply, explicit additive migration and independent verification passed on staging. A synthetic staging task (`CP-TK-2026-000018`, task ID 117) was created through the authenticated UI; the saved card displayed its title, description and notes. The staged personal board and task composer rendered for an authenticated administrator.

The exact same 28-file artifact then passed production preflight, full database backup, apply and independent verify. Production code files, table columns/unique key and login hook were checked by the release path. Root-only rollback state and full database backups are `/root/chinaplanetos-m089-c03-r1-{staging,production}/`; backups were not exported or restored. Production browser reload displayed the new internal board controls, including add-task actions within columns. Publicly served pointer/refresh JavaScript hashes matched the released manifest. No production task or other business record was created or edited by this release.

Staging had nine additional UI/language overlays relative to production. After promotion, `deploy/releases/m089-c03-r1/stage_overlay.py` applied and verified a staged-only merge of those nine files on top of C03. Its payload SHA-256 is `fedf5bbc35d92d010a754ac102a9a77274353644e31286baa5022cc90baa751f`; it makes no schema or business-data changes. The prior R3 status helper is frozen and still reports the earlier R3 release; C03 deployment state is held in the root-only C03 release directories.

Validation limits: browser creation/persistence was verified on staging, but a two-tab automatic-refresh scenario was not completed end to end because browser control repeatedly changed windows during the test. The refresh polling and suppression behavior passed the local JavaScript test. Physical Windows Chrome/Edge touchscreen and pen handling remains for Ahmed's screen test. The 28-file production release has a code rollback path that preserves new task-detail records; if invoked, it restores the previous login-hook constant and does not restore the full database over later user writes.
