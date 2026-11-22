# Supplier workspace picker — 2026-11-23 Asia/Shanghai

Owner Ahmed Hamed; implementation Codex. Branch `codex/supplier-workspace-picker`. Scope is the existing Operations supplier activation picker in `tasks/boards.php`, `tasks/boards_api.php`, `SupplierWorkspaceService`, existing Boards JS/CSS and EN/AR translations. No new module, permission, workspace registry or business rule.

Cause: `supplierOptions` always queried page 1 (51 rows including lookahead), and the UI used an unbounded native select with no continuation. The page argument now reaches the existing scoped search; the API returns 50 results plus page/has_more. The supplier dialog uses an inline scrolling listbox, searchable combobox, explicit selection, Load more, loading/empty/error/retry and keyboard Arrow/Enter navigation. New searches cancel old requests, clear stale selection and do not submit on Enter. Chinese IME composition is preserved. Names are inserted as text; long names wrap. Existing customer picker and workspace activation validation remain unchanged. Already-enabled suppliers remain in the workspace gallery rather than in the activation list.

Verified locally:

- A synthetic SQLite test using the real service SQL reproduced the second-page failure before the fix, then passed 14 checks: 122 eligible supplier IDs across three pages without duplicates, end of results, literal wildcard/Unicode search, other-company/non-supplier exclusions, external/inactive/non-admin/foreign actor denial and unchanged enabled gallery.
- Existing General Board regression: 72 permission/persistence checks pass. PHP lint on changed PHP and the new test, JS syntax and diff check pass.
- Actual current Boards JS/CSS in a synthetic loopback browser fixture: 50 -> 100 -> 123 results, no executable markup in supplier labels, search for the last supplier, Arrow/Enter selection, explicit enabled submit state, empty result, failure/retry and stale-selection clearing. No activation POST was submitted.
- English desktop dark/light and Arabic 390x844: bounded scroll area, wrapping long multilingual names, selected supplier display and visible footer; no horizontal dialog overflow. Phone dialog bounds x16..374, y89.9..754.1, result region 240px, footer bottom737.1. Native Chrome/customer records were not used as browser fixtures.
- Guarded seven-file local overlay: every predecessor matched Git HEAD before copy. Originals and hashes retained privately at `/Users/mac/.codex/supplier-picker-20261123/`. Local native Dolibarr on port18121 authenticated through its standard lab login; nine translated labels, page1/page2 metadata, distinct IDs and end-of-list verified. The native fixture contains two not-yet-enabled suppliers; multi-page volume coverage uses the synthetic test/browser fixture. An initial test expectation of 50 native results was corrected after this read-only inventory. No native record or membership was changed.

State: source/local runtime tested; GitHub publication recorded in the PR. This supplier-picker change has not been deployed to backup-ERP or production. The earlier M141 staff release remains separate. No schema, configuration, user profile, permission, financial/business record or service changes.

Ahmed's GitHub sync rule is recorded in repository AGENTS.md: completed verified code changes must be committed and pushed, and live/backup deployments must match published source. This rule grants no deployment approval on its own.


## Authorized remote release — 2026-11-23 Asia/Shanghai

Ahmed requested “push to them as well”, authorizing this exact tested seven-file code overlay on live and backup, with preflight, scoped backups, apply, independent verification and code-only rollback. PR #64 was merged first; both releases are built from GitHub main `91d7337e54a3367dc171342fce71c0059e66aa02`.

Both exact containers, images, custom volumes, ports, proxy/config hashes, twelve scoped source predecessors/protected paths and file owner/modes were checked. Each target passed 14 native MySQL tests using session-temporary synthetic tables, PHP lint, complete native supplier ID comparison over all pages, external-actor denial, retained General Board staff scope, anonymous login requirement and HTTPS JS/CSS byte hashes. Recovery archives were verified before seven atomic file writes; independent verification then repeated checks. Live has 1,642 eligible supplier IDs across33 pages. Backup has one isolated eligible supplier; its user/business data was not copied.

Initial preflight stopped before source mutation because the test harness placed `?>` before a strict PHP declaration. Sanitized diagnostics confirmed strict declaration ordering; controller r2 evaluates the same candidate body without its opening PHP tag, preserving strict mode. The app artifact did not change. All guards were rerun successfully. No production rollback was required.

Authenticated Chrome on live rendered the bounded supplier list and its 50 ->100 continuation. Native browser control briefly reported a changed page; fresh state was read before proceeding. No supplier was created or workspace activated during this check. Search/keyboard and EN/AR phone visual cases are the separately recorded synthetic checks above. [Exact release receipts](supplier-picker-release-2026-11-23.json) and the frozen controller `deploy/tools/supplier_picker_release_20261123.py` record checksums and backups. Recovery command is `/root/supplier-picker-release-r2.py backup rollback` or `live rollback` through Python; subsequent source drift is refused. No database, schema, config, profile, native right, actual membership or service change/restart.


## New supplier shortcut — requested 2026-11-23

Ahmed asked for a visible New supplier option like New customer during the active supplier-picker release. The existing native creation link is promoted from the low-contrast dialog footer to a clear button beside the search label. It remains conditional on `can_create_supplier`, uses the standard native `societe/card.php?action=create&type=f` form, and supplies a fixed Boards return path. The sidebar New supplier action is retained. No automatic workspace activation, new third-party service or permission grant is introduced.

Local native authenticated GET confirms the Supplier checkbox is preselected and the hidden Boards return path is retained. Initial test expected an old `fournisseur` select; current runtime uses a `supplier` checkbox, which was inspected and asserted. No supplier creation was submitted. Synthetic current-asset EN desktop and AR390px review confirms a visible purple/white 44px creation button and a fitting dialog. Existing 14 supplier regression checks, JS syntax and diff check pass. Remote application of this two-file follow-up is tracked below.


### New supplier shortcut release outcome

PR #65 merged to GitHub main `0c3e8277c35418171e0ab709fff6fa18187467e8` before release. The two-file JS/CSS artifact was applied to backup, independently verified, then applied and independently verified on live. Both match source and Mac hashes; all ten protected source paths, runtime/config/proxy identity and ownership were unchanged. Each passed native14 temporary-table checks, full eligible-ID paging comparison (live1,642/33pages; backup1/1page), external denial, General Board staff scope and HTTPS JS/CSS hashes. [Exact checksums/backups](supplier-create-release-2026-11-23.json) and the frozen `deploy/tools/supplier_create_release_20261123.py` support two-file rollback refusing later drift.

The standard native creation form GET and EN/AR button presentation were verified locally. Post-release Chrome review timed out and reset computer control; live clicking through the new button to the form is not claimed as observed. The previous picker50→100 signed-in review was observed before this follow-up. No supplier or workspace was created during tests. No database/config/profile/rights/business changes or restart. GitHub main contains the implementation and release evidence. Earlier seven-file recovery must only be used after reversing this newer two-file layer, because both reject later source edits.
