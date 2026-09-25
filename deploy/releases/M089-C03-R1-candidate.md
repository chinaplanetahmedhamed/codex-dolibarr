# M089-C03-R1 — combined Kanban release candidate

Status at 2026-09-26 Asia/Shanghai: **local candidate only; staging and production unchanged**.

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
