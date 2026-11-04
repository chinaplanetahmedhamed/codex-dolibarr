# M089-C03 — Personal Kanban, pointer input, and board refresh

Owner approval: Ahmed Hamed requested the C03 personal board combined with touch/pen drag, automatic updates across open Kanban screens, and a production push.

Scope: the 28-file `custom/` payload at `deploy/releases/m089-c03-r1/manifest.json`, based on R3. It adds personal task columns/details and the additive `llx_cpo_task_details` table; registers the operations `login` hook; uses Pointer Events for mouse, touch and pen drag; and polls authenticated board revisions every eight seconds while visible. Ordinary touch scrolling and card clicks remain available. Customer visibility, core Dolibarr code, unrelated modules and production task records are outside this release.

Release: the same SHA-256-pinned 28-file payload was accepted on staging and promoted to production. Both deployments passed exact-file preflight, full database backup, migration and independent verify. Staging's prior language/UI changes were reconciled as a separate nine-file staging-only overlay after promotion. The staging UI saved synthetic task 117 with description and notes. A live authenticated board reload displayed the upgraded controls; public pointer and refresh JavaScript matched the artifact hashes. Local PHP/JS syntax, Kanban access/revision tests, and `git diff --check` passed.

Limits: automatic refresh was unit-tested but a two-tab hosted acceptance was not completed. Mouse interactions and the physical Windows Chrome/Edge touchscreen and stylus paths require Ahmed's screen test. The old R3 status helper does not describe this release; use the C03 root-only state and verification record. The complete artifact hashes, rollback paths and test detail are in `deploy/releases/M089-C03-R1-candidate.md`.

Rollback: restore only the 28 allowlisted code files and previous login-hook context from the C03 state; retain additive task-detail records and user preferences. A full database backup exists for disaster recovery, not for normal post-deployment code rollback.
