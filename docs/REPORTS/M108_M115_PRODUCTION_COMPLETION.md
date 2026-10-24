# M108–M115 production completion and separate M115-C01 status

M108–M115 is deployed and independently verified on `srv1011043/vision_erp` at `https://erp.visiontrading.biz/custom/chinaplanetoperations/tasks/boards.php`. Runtime source `ed9b316de228da9aa7569278808266d2c409e7b5`, 36 allowlisted files, artifact SHA256 `a2739eca3d3155d4f56fc4d475eb6847e30a3945e7326c13b2b8da06ccb62183`.

Actual apply receipt: 2026-10-24T18:44:10Z (25 October 02:44 China time). Independent source/schema/native-core verification: 18:45:07Z. Six additive Boards linkage/preference/completion tables and five feature flags; existing sourcing hooks and translations reconciled with live source. Production core remains 24.0.1; local M097 is 24.0.2. No production core/image/security/permissions upgrade, credentials, cpkanban removal or synthetic business data.

Private full DB/scoped code backup: `/root/chinaplanetos-m108-m115-20261025`; database SHA256 `83053044269cc506914d6cf7b38e36b5df5f7cf5651f307d5e2513c1045bf72b`. Protected native/customer/project/task/Boards/access data hashes preserved. Rollback is scoped code/feature flag/hook restoration, retaining additive schema and subsequent business writes; never restore the old dump over newer data. Later overlays must be rolled back first.

## Acceptance

- Native bridge, gallery and completion suites passed on both 24.0.1 and 24.0.2 (26 + 41 + 42 assertions per version), with earlier legacy/regression, permission, failure/rollback, concurrency, archived-count and >200-project cases.
- Authenticated isolated native browser: card/native-task creation, original native project task list, Done/Closed100, reopen/Validated0, same-ID project/card transfer, board Done warning without closing native project, cleanup preserving original 25 table hashes.
- Actual authenticated production reads: customer/project hierarchy, board12/native project15, native project deadline, open/done counters; card CP-TK-2026-000075 defaults customer35/project15 after options load; canceled without saving. Exactly one native project-page Boards shortcut, click-through accepted. Mobile board had width=scrollWidth=390. No live customer/card business edit was used as a test.
- Canonical task state, not a list name, defines completion; historical cards in a list named Done were not silently rewritten.
- Actual production gallery was read again after executor recovery on 25 October, showing General-style navy tiles, priorities/stars/counts and hierarchy.

Receipts: `deploy/releases/m108-m115/production-{preflight,apply,independent-verify}.json`. Detailed local authenticated acceptance and screenshots remain in the task workspace `workspace-gallery/native-browser-acceptance` and `workspace-gallery/production-release`.

## Separate follow-up M115-C01 — not deployed

Five-file source `63723ec30d73b7cbeab15c441d9af21c9abf2c82` implements team-wide recoverable workspace disable plus neutral breadcrumb/navy new-board defaults. Native records, memberships, appearance, favorites and priorities persist; authorized existing board deep links remain usable. Hiding is not access revocation. No customer is disabled by deployment, and no schema/config/rights migration is needed.

23 disable assertions plus affected 41 gallery assertions passed on each core version; authenticated local cancel/disable/full reload/search/reenable and preservation cleanup passed. Seven isolated release/rollback assertions reran after recovery. Final production responsive acceptance is still pending for this follow-up.

Server preflight passed 2026-10-24T19:12:34Z. An earlier apply was rejected by automatic approval review before execution for missing separate publication approval. Ahmed has now said “go on” in response to that outstanding scoped publication step. After Mac recovery, restricted SSH receipt still shows only preflight; Termius native control currently returns `noWindowsAvailable`, so no apply has been sent this turn and no source/schema migration is in flight. Restore visible authorized session control, verify host/account and outcome, then perform fresh preflight/apply/independent verification. Do not claim C01 live until receipts and actual UI checks confirm it.
