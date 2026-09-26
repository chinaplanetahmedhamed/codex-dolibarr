# M091 — Scheduled My Tasks in Agenda

Deployed directly to production and verified on 2026-09-26 after Ahmed’s explicit live-release instruction. Final code source: `6e5dd5604d089cbb6e7390e684bf2bd604a55ab3`; branch `codex/m091-task-agenda`. Production preflight, backup, apply, corrections and independent verify passed. Salma’s authenticated month/day calendars show her existing scheduled task with the correct deadline and To do status; its link opens her task card with the new start-time field.

My Tasks now accepts an optional start time alongside the existing start date. Scheduled tasks appear in native Agenda month, week, day and per-user calendars with their current operational status. Clicking a calendar task opens its authorized task card. My day includes an Agenda shortcut. Updates, reassignment, completion and archive changes are reflected on the next read without synchronization jobs or duplicate event records.

The integration uses Dolibarr calendar hooks and reads the authoritative task records through existing task and Agenda rights. No core files, customer/mobile surfaces, business permissions or database schema changed. Start time is an additive JSON key in existing task details. Deadline/start edits share a task-row lock, validate ordering and keep optimistic versions. Stored deadline values are unchanged. Calendar projection preserves the wall-clock dates/times entered and shown on the task card, including when the server and user timezones differ.

## Verification

47 integration assertions and 145 existing task runtime assertions passed. Local English/Arabic desktop and phone browser acceptance covered form creation, save/reload, status change, calendar navigation, task links and rescheduling. Installer idempotency and rollback preserved task/details/native-event data. Twenty-seven release integrity/recovery tests passed across the base release and two one-file corrections. Live acceptance exposed and corrected a MariaDB collation difference and a server/user timezone mismatch; both candidates passed read-only production checks before deployment. Syntax and diff checks passed. Full evidence and the two pre-existing legacy-test limitations are recorded in [evidence](../evidence/M091-20260926/README.md).

## Installation requirements and limits

Deploy the complete changed file set over the C03 task-experience baseline (including its existing `cpo_task_details` table), then run `deploy/migrations/m091_task_agenda.php apply` with the target `DOL_DOCUMENT_ROOT` and `CPO_ENTITY`. Newly activated modules register the Agenda context through the module descriptor. Existing Agenda permissions are required and are not granted by this change.

The feature projects tasks into the native calendar views. Native event List/export/API remains a list of ActionComm records; no duplicate task copies are introduced there. Per-user working-day filters remain native. Personal board column names do not change operational task status. Unscheduled, archived, cancelled and deleted work is excluded; done work respects the native completed/incomplete filters. Existing scheduled tasks appear without a backfill.

Rollback: run the pinned C02 rollback, C01 rollback, then base production rollback in that order. The base helper removes the new hook and restores prior code. Retain task data and the harmless additive `start_time` JSON values; never restore the database over newer business writes. Production backups/state are retained privately in `/root/chinaplanetos-m091-production/`. See the [dated release evidence](../evidence/M091-20260926/production-release.md).

Local preview: http://127.0.0.1:8101/comm/action/index.php?mode=show_day&year=2026&month=9&day=27&search_filtert=2&lang=ar_SA
