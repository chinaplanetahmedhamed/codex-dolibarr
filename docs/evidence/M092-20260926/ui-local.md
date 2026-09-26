# M092 local acceptance — 2026-09-26

Authenticated synthetic m089_worker on Dolibarr 23.0.3 at localhost:8101.
Task 15 is synthetic local data, not a production record.

- Moved start from September 24 to September 29 at 09:15 and deadline from September 28 at 16:00 to September 29 at 17:30 in one Save. Card deadline updated; manual reload retained all three values.
- Cancel task opens explanation, required reason, Keep task and Cancel and archive. Keep task dismisses without changing status.
- Confirmed local cancellation: Cancelled header, dates read-only, files/checklists/notes/history preserved, two audit events with supplied reason.
- Back to tasks opens own Archive; task visible with Cancelled badge. Opening its card modal and restoring with reason returned To Do and preserved deadline.
- Arabic mobile 390x844: cancellation dialog and three date/time inputs fit viewport; controls legible and usable. English desktop and mobile dialogs checked visually; Arabic desktop labels and dialog also verified.
- Service suites independently verify active-list/Agenda exclusion, own-Archive scope, restore, stale versions and write-failure rollback.
- Local synthetic cover image URL is unavailable in this harness; no asset changes in M092.
