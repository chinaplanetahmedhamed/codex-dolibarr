# M108 — Native project task on project-board card creation

Status: APPROVED / IN_PROGRESS. Authorization: Ahmed explicitly requested on2026-10-24 that a new card inside a project board also be recorded as a task in its original native Dolibarr project; screenshots identify board12 and native project15 as the concrete example. No assumption that their numeric IDs match.

Scope: atomic creation of an existing internal CPO card/task and one native Dolibarr project Task using the native Task model, with a stable entity-scoped mapping and idempotent retries. Generic boards retain current behavior. Respect native project creation/write rights and original board/customer/entity permissions. No stored rights/credentials grants, retroactive conversion of existing cards, bidirectional status/description/time synchronization, copying private comments/files, or unrelated UI changes. Preserve the pending local M107 design edits.

Implementation/testing approved locally. New feature rollout requires the applicable explicit release authorization and fresh exact-target/source/schema preflight; prior UX push rejection is not authorization for new external publishing. No Git push or live writes in this implementation step.

Additive migration and flag must remain explicit and reversible. Verify native24.0.1 and local24.0.2, real native task listing, duplicates, failures/rollback, native/customer/entity permissions, generic compatibility and unchanged original records/security. Never create production test records.
