# M095 — Staff dashboards and all-task list

Status: IN_PROGRESS. Ahmed explicitly requested that filtering by staff opens that employee's own dashboard, columns and settings; each user has their own dashboard; managers/supervisors can view staff dashboards; All Tasks must be a task list rather than personal columns. Continue the authorized direct-live update.

Personal column names, colors, ordering and card placement belong to the selected employee. Viewing another employee is read-only for personal layout; task actions continue under the signed-in viewer's existing task authorization. Managers see only their granted teams' tasks, even if an employee has tasks in another team. Staff cannot view another employee's board. All/Team views without a selected staff member use a responsive task list, preserving filters. Do not grant rights or memberships and do not impersonate another user for writes.

Verify cross-user identity, manager scope, revoked grant, direct URL/revision denial, persistent distinct layouts, independent mutations, list/filter behavior, desktop/mobile EN/AR and release/rollback. No schema, public/customer/mobile API changes.

## Release status — 2026-09-26

DEPLOYED; authenticated production browser acceptance remains pending stable ERP window control. M093/M094 shipped in M094; M095 shipped separately. See dated production-release evidence. Local authenticated workflows, runtime regression and release rollback tests passed.
