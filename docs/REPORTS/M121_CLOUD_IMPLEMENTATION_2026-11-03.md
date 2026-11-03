# M121 — cloud implementation checkpoint, 2026-11-03
Owner: Ahmed Hamed. Coordinator: ChatGPT Work. Engineering: scoped Codex agents. Dot: monitoring/local shared handoff. Mission remains OPEN; this is source-only progress, not completion of the Master Instructions.

## Implemented candidates
| Scope | Candidate | Exact head | Result |
| --- | --- | --- | --- |
| Financial read model / P2-01 subset | [PR59](https://github.com/chinaplanetahmedhamed/codex-dolibarr/pull/59), codex/m121-financial-read-model, base main016893b | 6bdc868e7bfeb44c9ffe8b3ca5545300ab0df298 | Separate currencies in supplier/services/customer obligation/required-funding display; forward existing project filter to unpaid invoices. No ledger/posting/schema change. |
| Boards / P3-01, P3-02 subsets | [PR60](https://github.com/chinaplanetahmedhamed/codex-dolibarr/pull/60), codex/m121-boards-density-priority, base codex/m107-customer-project-workspaces113ddc6 | 422284e0b26d15ef866c1fb7a886d9d77425e9b3 | Compact/Comfortable per user/entity; labelled source priority bands; add existing t.priority to search/shared reads without changing permissions/entity filters. |

These isolated candidates do not advance the integrated phase gate P1-06. Main lacks the full Boards branch and the Mac has newer dirty CustomerFunds/SupplierWorkflow/ShipmentDocument services. Never blanket-copy these branches onto Mac or merge the full older M107 branch over newer work. Preserve original source/IDs/history.

## Actual verification
- Finance test-only commit1caf2ab reproduced the old mixed-currency KPI defect: [RED41629595728](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41629595728).
- Fixed financial rendering/project regression passed: [GREEN41629736490](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41629736490).
- Final financial head: three PHP8.3.6 syntax checks and all four related scripts passed: multicurrency, M028 portfolio mobile source contract, M029 core funds, M121 synthetic rendering/project regression. [Run41630027455](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41630027455).
- Boards baseline failed as expected on missing source-priority projection: [RED41630474774](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41630474774).
- Boards fixed PHP syntax, JS syntax, and synthetic DOM/read projection checks passed: [GREEN41630579659](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41630579659). Local Node fixture covers12 language/priority cases, human title/customer/reference, title/reference search, viewer menu absence, density persistence, shared rendering and no source writes.
- Independent cross-reviews found no blocking production-code issue. Diff whitespace checks passed.
- Financial test gaps retained: comparator fixture lacks non-shortfall/name-tie cases; fake DB guards invoice-scope predicates explicitly but does not prove every other query predicate.
- Boards fixture extracts selected functions, not whole-file startup or actual CSS rendering. Existing M095/M097 tests require a synthetic Dolibarr DB/config and were not run.
- Hosted source workflows have contents:read, no deployment step and no secret references. Checkout action is pinned; runner warned its Node20 action runtime is forced to Node24, non-blocking in observed successful runs.

## Runtime boundary and blockers
No application source was merged into main or deployed. No backup, restore, user creation, financial posting, authenticated ERP test, screenshot, server configuration change or production access occurred.
Cloud PHP is unavailable; apt package setup hit EPERM on setgroups/setuid and was not bypassed. PHP testing used supported hosted GitHub Actions instead.
Official Playwright Chromium installation failed with a truncated0MiB/non-ZIP download; installer exited1. No screenshots or visual pass are claimed.
backup-erp login previously showed24.0.1, not authenticated acceptance. The ERP connector exposes relative URLs but does not establish its origin. Do not write demo data through it before backup-only target proof.
The Mac VPS operations skill and current-state references remain local-only and must be read by the local executor before any operational command. No direct Mac/Dot/Codex-session control API exists in this session. The shared follow-up still lacks a new receipt as of this report.

## Local handoff — next actual work
Read docs/governance/DOT_MAC_HANDOFF.md on current main and append RECEIVED/IN_PROGRESS with branch/fullHEAD and accessible backup scope.
Read the mandatory Mac VPS skill/references. Preserve dirty/staged/untracked work. Snapshot canonical source and determine exact installed version, module/schema/grants and Docs target isolation.
Compare the two candidate diffs to the newer canonical files; reconcile only reviewed deltas and their tests/dependencies. Run full relevant local suites, startup/browser/mobile/RTL/role tests.
Verify backup-erp-only services/outbound isolation; create and verify the exact code/DB/config backup and rollback before applying. Record artifact/commit hashes and actual acceptance evidence.
Continue the existing29-task plan in order; do not mark P2/P3 complete from these source subsets. Update the existing tracker/manual after every tested function. Production remains prohibited.

## Shared records
[Progress/Tasks/Test Log](https://docs.google.com/spreadsheets/d/1QZMWYIHbuigCW_40XdIFC4ZSknVrYGVsW-IL2rjF8HQ/edit)
[Existing workflow manual](https://docs.google.com/document/d/1pxjs7J7qACZGfhj8XIEdEvw1A5Rfz06IvYphX9G5bEQ/edit)
Source results and runtime limits are recorded separately. No new tracker/manual was created. Routine backup-erp approval is already available; missing execution access is the blocker.
