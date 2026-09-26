# M095 local authenticated UI — 2026-09-26

Dolibarr 23.0.3 synthetic local runtime, administrator m089_manager, browser UI.

- All Tasks renders task rows with title/team/status/progress, creator, assignee, deadline and file count; no Kanban columns.
- Dashboard selector chooses M089 worker. Saved six-column layout appears, including قائمة سلمى الخاصة and جاهز للمراجعة عندي, with its colors and persisted card placement. Layout controls and drag handles are absent for the viewing manager.
- Search for M094 upload acceptance keeps assignee_id=2, employee heading and all six columns; one matching card remains.
- My Tasks returns manager own five default columns and editable My columns control; employee settings are unaffected.
- English desktop, Arabic desktop and actual 390x844 Arabic list / English staff dashboard visually inspected. Arabic list has document width 390px with no horizontal overflow. Viewport override reset.
- Local synthetic manager password fixture restored after sign-in. No production credential changed.
- Runtime suite separately verifies authorized supervisor reads, cross-team exclusions, revoked grants, external/foreign/inactive denial and persistence without preference writes.

Production browser acceptance is pending deployment.
