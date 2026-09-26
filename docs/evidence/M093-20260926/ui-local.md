# M093 authenticated local acceptance

2026-09-26, Dolibarr 23.0.3 at localhost:8101, synthetic staff `m089_worker`.

- Task 15: top Rename task opens focused title dialog; saving persisted new title. Edit task opened the existing editor and saved revised description/priority. Reload retained changes.
- Full card, personal board and My day display separate Created by and Assigned to labels and names.
- Delete task explains recoverable deletion and requires reason. Confirmation navigates to Trash; manual reload retains the deleted task with responsibility labels and Restore task action. Restore with confirmation returned to original To Do state with deadline, notes, file/checklist/comment content retained.
- Repeated deletion inside the board iframe: successful mutation closed the modal and refreshed the board; the deleted task link disappeared. Trash link opened the record and recovery returned it to the card.
- Synthetic manager-assigned task 173 showed Created by M089 manager / Assigned to M089 worker. Staff saw progress/work actions, with no Rename/Edit/Delete/Cancel controls, matching Ahmed's explicit scope choice.
- EN and AR desktop/mobile (390x844) controls and responsibility labels verified. Rename/delete dialogs fit the mobile viewport, restore confirmation labels localized. Desktop Arabic layout places responsibility fields separately and wraps the top action group.
- Temporary viewport override reset. Synthetic cover URL is unavailable in this harness (pre-existing); attachment record/content counters remain intact.

Service tests separately cover old creation dates, exact restore state, optimistic conflicts, post-write audit failure rollback, manager/grant revocation, isolation, creator fallbacks, active-list/Agenda exclusion and no bypass of manager cancellation.
