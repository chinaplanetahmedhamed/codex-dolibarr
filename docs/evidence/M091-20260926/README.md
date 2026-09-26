# M091 local acceptance — 2026-09-26

Source branch: `codex/m091-task-agenda`, base `cd84e48`. Runtime: isolated synthetic Dolibarr 23.0.3, localhost port 8101, MySQL 5.7. No staging/production access or changes.

## Results

- New task-calendar integration: 43 assertions passed. Includes persisted schedule edits, live status/progress projection, reassignment, own/team/entity/customer/inactive-user isolation, broad Agenda-rights isolation, team grant revocation, native filters, multi-day clipping, date-only timezone handling, invalid dates/ranges, stale edits and transaction rollback.
- Existing task runtime suites: M058, M059, M060, M089 own tasks and M089 personal board passed (145 assertions total). Runtime harnesses were adapted only to the synthetic M091 database/config/path. The M058/M059/M060 suites used a separate synthetic regression clone.
- PHP syntax, JavaScript syntax and `git diff --check`: passed.
- Existing source/UI contracts passed except `test_m059_ui_contract.php`: three stale expectations for the pre-C03 composer/drag UI fail identically on untouched base `cd84e48`. No attempt was made to rewrite those unrelated expectations.
- The broad legacy operations-workflow script is not an acceptance gate for this change: it hardcodes an obsolete runtime path; pointing a temporary copy at QA reaches an existing missing-primary-team fixture failure. OperationsService is unchanged.

## Authenticated browser acceptance

Synthetic staff account used the actual Add task form to create task 111 (`Agenda test — shipping follow-up`), initially 2026-09-27 09:15–15:30. The saved task appeared in the native month, week and day calendars. Opening its Agenda link reached the authorized card. Starting work through the native confirmation changed the persisted status to In Progress and immediately changed the next calendar load. Editing start time to 10:15 persisted after reload and appeared as 10:15–15:30 in Agenda.

Per-user view respects native working-day settings (default Monday–Friday). With `end_d=7`, the Sunday task appeared in its time slots. Clicking an overlapping cell opened that day with individual task links, avoiding the native event-list route. English and Arabic day calendars, Arabic month calendar and task schedule editor were checked at 390×844; document width remained 390. Desktop calendar/form acceptance used 1440×1000. Arabic date/time ranges are isolated left-to-right. Temporary viewport override was reset.

## Installation / rollback evidence

The explicit hook installer was checked, applied twice, rolled back, checked disabled, reapplied and checked enabled. Task rows, details payloads and native ActionComm rows retained this same before/after SHA-256:

`ae02a995fda044b3985a3238b80379e0daac63696eb0080ef2178ea41e278e93`

Only `MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS` is changed by installation; unrelated contexts are retained. No table/column/index migration, task backfill, outbound messages or duplicate ActionComm records.
