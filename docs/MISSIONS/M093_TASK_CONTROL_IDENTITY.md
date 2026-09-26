# M093 — Task controls and responsibility

Status: IN_PROGRESS. Ahmed explicitly requested rename, edit information, delete/full controls, and Created by / Assigned to on cards, continuing the authorized direct-live task update.

Confirmed permission scope: “Only self-created tasks; managers control assigned work.” Staff control only their self-created standalone tasks still assigned to them in an active team; managers retain their authorized team scope. No account rights or team grants are changed. Customers, inactive/foreign-entity users and other staff remain denied.

Expose Rename, Edit, Cancel and Delete on the task card. Delete moves to Trash with reason and recoverable prior status/archive state; retain files, rich details, comments/checklists and audit. Never hard-delete business records. Preserve manager assignment controls. Show Created by and Assigned to on full cards, board cards and My day; names are entity-scoped with safe missing-user fallback.

Existing custom task module is authoritative (Dolibarr 23.0.3); extend it without core or schema changes. Cover optimistic concurrency, failure rollback, own/team/foreign authorization, persistence/reload, EN/AR desktop/mobile UI, exact production baseline, backup, immutable artifact, rollback and authenticated acceptance.

## Release status — 2026-09-26

DEPLOYED; authenticated production browser acceptance remains pending stable ERP window control. M093/M094 shipped in M094; M095 shipped separately. See dated production-release evidence. Local authenticated workflows, runtime regression and release rollback tests passed.
