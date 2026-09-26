# M091 — My Tasks in native Agenda

Status: Local implementation and acceptance complete. Ahmed explicitly authorized direct production release on 2026-09-26: ‘ok . push to live , live directly’. This includes production preflight, scoped backup, pinned deployment, authenticated verification and rollback if needed. Staging is outside this release scope.

Show scheduled internal tasks in the existing Agenda month, week, day and per-user calendars, including current operational status and a task-card link. Extend the existing start date with an optional start time. Reuse task deadlines, ownership, entity scope and task authorization. Dates without a time retain their calendar-day meaning. A deadline alone appears as a deadline marker. Unscheduled, archived, cancelled and deleted tasks are omitted; completed work remains available through Agenda status filters.

Use the native `getCalendarEvents` and `eventOptions` hooks, projecting the authoritative tasks on each read. Do not create duplicate ActionComm records or expose task content through native event exports/list APIs. No core changes, outbound messages, new permissions or schema changes. Register the Agenda hook explicitly for existing installations, preserving other contexts.

Source: isolated `codex/m091-task-agenda` checkout from `cd84e48`, preserving existing dirty task-board worktrees. Changes cover the task details editor/service, deadline validation, calendar projection/hooks, localized labels and scoped installation/tests. Customer Center/mobile are not affected.

Acceptance: persisted create/edit/status/reassignment, no duplicates, date/time and range validation, English/Arabic desktop/phone, native calendar navigation and task links, own-task/team/entity/external/disabled-user negative cases, stale writes and rollback. Rollback removes the Agenda hook context and restores the prior code; task records and additive JSON start times are retained.
