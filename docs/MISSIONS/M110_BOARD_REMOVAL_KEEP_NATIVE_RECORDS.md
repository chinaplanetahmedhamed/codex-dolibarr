# M110 — Remove boards while retaining native records

APPROVED / IN_PROGRESS. Ahmed requested an option to delete a board while keeping original Dolibarr projects and tasks unchanged. Implement recoverable board removal plus manager restore using the original board ID.

Only a CPO board tombstone and board version change. Do not delete native projet/projet_task, internal tasks, list/card placements, members, comments, attachments or project-board associations. Preserve associations so removed-board cards never gain legacy fallback access. Hide removed boards from gallery/direct routes/counts and board-derived grants; existing explicit card grants remain scoped by native customer/project permissions. Restore requires original manager/edit rights and native project visibility. No account/native access grants, permanent purge, backfill or live record deletion in tests. Local implementation/testing only; no external push/deployment added.
