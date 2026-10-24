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

## M115-C01 — deployed and verified 25 October, China time

Ahmed's continuation “go on” approved the pending bounded live publication. Termius recovered after human window focus; fresh srv1011043/root identity and address31.97.158.178 correlated. Five-file C01 source63723ec applied at20:47:13Z; independent verify20:47:48Z. Full DB/code recovery `/root/chinaplanetos-m115-c01-20261025`, DB SHA256 `da9dc7afe19511a6600fc466f5a20a0708b593f95221406ce3177eb86cedec0b`. All native and workspace records/rights/config hashes preserved. Zero actual customers disabled by deployment or acceptance.

Team-wide recoverable disable is available to administrators through each customer card menu. Restore through Enable customer workspace. Appearance, favorites, priorities, project/board/task records and memberships remain intact; existing authorized board deep links remain accessible. Hiding does not revoke access. Neutral breadcrumb and navy defaults affect the requested shell/new project boards; existing chosen colors remain.

23 disable cases plus41 gallery cases passed on each24.0.1/24.0.2. Isolated authenticated browser cancel/disable/reload/search/reenable and original-record cleanup passed. Seven release recovery assertions passed. Live Arabic desktop872 and phone390, English phone390 confirmation/cancel pass; both gallery and board scrollWidth390. Native project12 breadcrumb computed rgb(22,26,29).

Live acceptance found customer-option search mistakenly sent POST instead of GET. Narrow JavaScript correction f9e4abadd78f5e1a2db1b5b76b313925f1cc0d4f was tested red then green using the real input handler/transport, existing renderer and actual local search. No permission change was needed. One-file checksum-pinned follow-up applied20:57:57Z, independent verify20:58:36Z checked all36 final files plus preserved dependencies. Scoped recovery `/root/chinaplanetos-m115-c01-searchfix-20261025`; restore search correction first, then C01, then main release if needed. Each rollback preserves business writes, never restores stale DB.

Final authenticated production search Manzur returned exactly its matching option, selection then Cancel made no save. Actual production disable/reenable was deliberately not performed on real customers; those persistence, preservation and negative permission cases are proven in isolated tests. Final runtime source is a composite ed9b316 + five C01 files63723ec + boards.js f9e4aba. Final source hashes, code-only helpers and sanitized receipts live in `deploy/releases/m108-m115/c01/`.

No production Dolibarr core, account/security grant, cpkanban, native projects/tasks, credentials or unrelated feature changed during C01 or its correction. Local QA services were left in their existing running state; no service/DB startup or permissions writes were needed during this continuation.
