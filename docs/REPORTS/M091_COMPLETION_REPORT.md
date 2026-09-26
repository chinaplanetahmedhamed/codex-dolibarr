# M091 — Scheduled My Tasks in Agenda

Implemented and verified locally on 2026-09-26. Not deployed. Branch: `codex/m091-task-agenda`, based on `cd84e48`; changes remain available for review in this worktree.

My Tasks now accepts an optional start time alongside the existing start date. Scheduled tasks appear in native Agenda month, week, day and per-user calendars with their current operational status. Clicking a calendar task opens its authorized task card. My day includes an Agenda shortcut. Updates, reassignment, completion and archive changes are reflected on the next read without synchronization jobs or duplicate event records.

The integration uses Dolibarr calendar hooks and reads the authoritative task records through existing task and Agenda rights. No core files, customer/mobile surfaces, business permissions or database schema changed. Start time is an additive JSON key in existing task details. Deadline/start edits share a task-row lock, validate ordering and keep optimistic versions. Existing deadline storage/timezone conventions are preserved; Agenda formats timed events using the native user timezone.

## Verification

43 integration assertions and 145 existing task runtime assertions passed. English/Arabic desktop and phone browser acceptance covered form creation, save/reload, status change, calendar navigation, task links and rescheduling. Installer idempotency and rollback preserved task/details/native-event data. Syntax and diff checks passed. Full evidence and the two pre-existing legacy-test limitations are recorded in [evidence](../evidence/M091-20260926/README.md).

## Installation requirements and limits

Deploy the complete changed file set over the C03 task-experience baseline (including its existing `cpo_task_details` table), then run `deploy/migrations/m091_task_agenda.php apply` with the target `DOL_DOCUMENT_ROOT` and `CPO_ENTITY`. Newly activated modules register the Agenda context through the module descriptor. Existing Agenda permissions are required and are not granted by this change.

The feature projects tasks into the native calendar views. Native event List/export/API remains a list of ActionComm records; no duplicate task copies are introduced there. Per-user working-day filters remain native. Personal board column names do not change operational task status. Unscheduled, archived, cancelled and deleted work is excluded; done work respects the native completed/incomplete filters. Existing scheduled tasks appear without a backfill.

Rollback: remove the Agenda hook using the installer `rollback` mode and restore the previous code artifact. Retain task data and the harmless additive `start_time` JSON values. Production preflight, immutable release packaging, backup and live acceptance remain part of a separately requested deployment.

Local preview: http://127.0.0.1:8101/comm/action/index.php?mode=show_day&year=2026&month=9&day=27&search_filtert=2&lang=ar_SA
