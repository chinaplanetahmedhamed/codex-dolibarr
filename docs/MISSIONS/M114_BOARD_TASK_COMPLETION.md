# M114 — Persisted Done/reopen and accurate counts

Status: APPROVED by explicit Ahmed relay.

Independent board completion table with disabled-by-default flag; never closes projects or bulk-completes tasks. Show remaining open-task warning. Task Done/reopen uses existing canonical status, 100/0 progress and native mapped Task CLOSED/VALIDATED with native TASK_MODIFY triggers. Row locks and narrow native UPDATE avoid REPEATABLE READ lost updates and never rewrite notes, time, extras, dates or financial fields.

Counts share one server policy: unarchived canonical tasks and placements in active lists; canceled/deleted excluded; archived boards retain independent Done/open state, deleted boards excluded. Customer aggregates are independent of the bounded 200-project display. Completed tasks stay accessible; canonical archived tasks appear under archive rather than active lanes.

UI: retain General boards palette and shared gallery, shared priority, private stars/order, accessible controls, Arabic/English, responsive. Latest Ahmed color correction removes green tab strip and uses gallery-neutral surface with navy/blue active tab.
