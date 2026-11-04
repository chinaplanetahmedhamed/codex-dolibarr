# M121 linked shipment attention — local integration, 2026-11-04

Status: implemented and tested locally; authenticated backup acceptance and deployment remain pending. No remote or production access was performed for this increment.

## Source and ownership

Reconciled only the ten runtime and five test/preview files in the delta of Boards commit `81e7bc8dae2bb50f39f805554b20b30bb25fc0fd` against its parent, onto integrated v6 `ce3e86d2527b9a16701b8a0c2010f3f898f815d3`. The branch, older tree, owner screenshots and historic verification logs were not merged wholesale. Work stays in the existing finance/Office integration worktree; the dirty canonical repository and other owners' worktrees are preserved. Ops EN/AR labels were appended separately because their tails also contain the integrated saved-payment recovery labels. No shared transaction, Office, Documents recovery or financial history implementation was replaced.

## Result

The assigned-work dashboard can show shipments needing attention within the actor's existing authorized task projection. Existing owner checks revalidate shipment/entity/customer/project access. The projection keeps native shipment status and actual arrival unchanged; a past ETA with no ATA is explicitly unconfirmed. Existing active lifecycle states govern ETA/free-time signals. Terminal/unknown states are not newly classified as active. Shipment and task links identify the exact existing records. No financial posting, lifecycle mutation, schema, module activation or grants were added.

Checks cover at most 500 assigned task rows and 50 unique linked shipment source reads. This is an assigned-task subset, not an exhaustive shipment inventory. Read failures and exhausted bounds produce partial coverage; unreadable sources disclose no shipment details. Missing internal/Logistics rights or disabled dependencies keep the panel unavailable.

## Reproduced issue and correction

Independent review found the donor handled the 50-source cap but did not propagate exhaustion of the upstream `LIMIT 500`. If only task 501 links to an overdue shipment, the omitted source could leave an empty result marked complete, allowing a false all-clear.

The service now materializes the existing selector once and counts its raw rows before deduplication. At 500 rows, an available attention projection is conservatively incomplete, including an exactly-full result whose completeness cannot be proved. SQL order, scope and 500-row limit remain unchanged; there is no extra/sentinel query. Both translations and fallback copy disclose the task-row and shipment limits. Existing JS suppresses the empty/all-clear message for incomplete results.

The new test loads the exact Boards class body in an isolated namespace, removing only dependency `require_once` statements. Auth, reference and source adapters are explicitly synthetic. The production selector executes against SQLite with 499, 500 and 501 tasks, plus foreign-entity, other-assignee and unreadable records. It failed at 500 before the correction; all 19 assertions pass afterward. At 501 the sole late-linked shipment is actually omitted by SQL, while partial coverage remains true. This is a local boundary test, not native authentication evidence.

## Verification

Evidence: `outputs/M121/shipment-attention-integration-2026-11-04/` in this worktree.

- `commands.json`: 42 distinct checks, 45 successful command executions, including applicable owner regressions, changed PHP syntax checks, actual JS dashboard renderer, documents/context/navigation checks, managed-funds presentation and whitespace verification. Repeated JS checks follow the final fallback-copy change.
- Shipment owner/projection: 27 assertions cover access-before-read, exact source scoping, status/date boundaries, no ETA-as-ATA inference, deduplication, denied/failed sources, permissions, disabled dependencies and 50-source partial coverage. The task-cap test adds 19 assertions.
- Independent read-only review accepted the cap correction and preserved saved-payment labels. It did not rerun tests or claim native runtime acceptance.
- Browser: actual source JS/CSS and source owner fixture through the existing loopback adapter at port 18780. EN desktop 1728×919 has document scroll width 1728. AR/RTL phone 390×844 has document scroll width 390; the 340px dashboard list has no horizontal overflow and its existing 360px inner scroll exposes both shipments. Long mixed-language task titles wrap; dates remain isolated left-to-right. This is synthetic chrome/data, not an authenticated ERP session.
- `ui-observations.json`, `shipment-attention-ar-phone.jpg` (SHA256 `91eb700a2b13d3607f674b25e9dcedba87645174968dec08ea20970d249440c5`) and `progress.csv` record steps, roles, expected/observed results and limits. The earlier assembly screenshot was restored and its hash checked after capture. Temporary browser viewport override was reset and verified at 1728×919.
- `preserved-candidates.json` verifies original frozen v4/v5/v6 archive hashes unchanged. A new v7 archive is built from exact Git objects; it does not modify those archives.

## Remaining gates

No target MariaDB, installed module/rights, native top-bar/theme, application-timezone boundaries, authenticated desktop/mobile, real storage or target performance claim is made. No new native finance/MySQL journey was needed or run for this read-only additive panel; earlier evidence retains its original environment and build limits. Full historical financial reconstruction is still unsupported; v5/v6 guards remain intact. The separately saved website draft-linkage package is not silently included.

The prior staging Docker/access denial remains in force. Current backup isolation, a fresh consistent backup, actual disposable matching-MariaDB restore proof, a reviewed supported M121 apply/rollback path and authenticated target acceptance are still required before backup deployment. Old metadata/integrity PASS and local synthetic recovery do not establish these. Do not invoke the Office-only installed helper for M121, bypass access controls or restore an old financial DB over subsequent records. Parent Work owns consolidation into the shared tracker/manual/handoff to avoid concurrent writes.
