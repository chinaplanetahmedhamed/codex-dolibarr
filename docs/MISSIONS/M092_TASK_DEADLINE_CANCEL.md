# M092 — Task deadline editing and cancellation

Requested by Ahmed during direct-live M091 acceptance on 2026-09-26: make the deadline editable in the task and add a cancel button that moves cancelled tasks to the archive. M091 Agenda is already deployed and verified; this follow-up reuses the same worktree and direct-live task context.

Put the existing deadline beside start date/time in the task card. Save the task deadline and task details together, validate their range and both optimistic versions, preserve unrelated fields and retain current instruction-edit permissions. Do not change an assigned task's manager-controlled fields implicitly.

Add a visible cancellation action with a reason and confirmation. Cancellation must archive atomically, remove the task from active work and Agenda, keep audit/history/files, and support authorized recovery. An optional scope question received no answer during implementation; the communicated conservative scope is self-created internal tasks only, with existing manager/archive rights retained. No hard deletion, new permission grants or schema changes.

Verify persisted schedule/cancellation/restoration, stale versions, invalid dates, rollback and positive/negative owner/team/entity cases; authenticated English/Arabic desktop/phone UI; exact live baseline, scoped backups, pinned release and native acceptance. Preserve all M091 corrections and unrelated deployed files.
