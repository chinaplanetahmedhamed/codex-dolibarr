# M119 — Company Users loading and missing-menu correction

Date: 2026-11-02. Target: canonical backup-erp only. Status: DEPLOYED_RUNTIME_VERIFIED. Final Actions41346537136 attempt2 and independent restricted verification passed.

Ahmed reported a persistent Loading overlay and missing full options compared with the original design preview. The existing deployed administrative session reproduced the failure: native `#dol-block-ui` had HTML `style="display: none;"` but computed `display:flex`, `z-index:9999`, even after `document.readyState=complete`. The custom Company Users fallback CSP blocked style attributes. Native Dolibarr 24.0.1 declares the overlay `display:flex` in its theme and relies on a style attribute to hide it. This is a rendering/CSP compatibility failure, not a pending staff query.

The second finding is a delivery mismatch: the local original-style preview retained all ten menu destinations, while the deployed native staff page had only two links. The full design was not removed from source. Several financial sections remain static previews with no implemented accounting lifecycle. Earlier deployment acceptance did not establish full visual parity or check whether the loader intercepted interaction.

## Change

Application source: `e59efa9a33e5c21b5bbd82e37caf6c1670713480`. Exactly five runtime files differ from the recorded standalone package:

- `custom/chinaplanetofficeexpenses/users.php`: allow native style attributes independently of nonce-protected scripts, permit the same-origin PWA manifest, version staff/shared CSS by content hash. Existing native/forced CSP remains authoritative.
- `custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffPage.php`: original grouped menu structure, mobile menu that works without JavaScript, branch-preserving links, full eight-section permission overview, explicit unavailable editing/deletion and unfinished sections; existing escaped values, CSRF/revision fields and supported read/create POST contract retained.
- `custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php`: reuse the same navigation, so the menu does not disappear when opening implemented expense/advance/dashboard views. Company Users still depends on the server-computed management capability.
- `custom/chinaplanetofficeexpenses/employee.css` and `staff.css`: original palette, spacing, rounded panels, responsive navigation and horizontally scrollable permission matrix.

The static preview was regenerated with its full original menu, current activation notice and explicit links to actual Egypt/China assignment management. It remains review-only; saving is on the authenticated native page. No sample preview balances or payroll/report data were imported into ERP.

## Release and rollback

46-entry dependency-closed archive: `c1ae3bb30a849834165e4810beedadc69cb3112d486c897da874abf177a96ec3`.
Manifest: `6d6640ef6aa79ff9dfed05d13776f0666b6162d053034465c03990548f3036de`.
Fixed five-action updater: `388b8d24178307b790f178aec8c4fadc62a9ce68f993adbfc840f8f7da8a4757`.
Installer: `dedd2fad3372976bd355a1531b877dba69fdf51c4bda44496fa04efda702bdb4`.
Private state: `/var/lib/chinaplanetos-backup-erp-deploy/m119-ui-r02`.

The exact updater was installed through the existing verified root session after bounded checksum validation; independent restricted SSH readback matched its hash. Existing sudoers and five commands are unchanged. Subsequent release actions use restricted SSH/GitHub. The updater requires the recorded original module/parent inodes, exact previous full package and unchanged native staff/branch baseline, and refuses replay or additional changed files. Before replacement it retains five root-private exact code backups; replacements use held directories and checked source inodes. No schema, account, password, grant, branch revision, service, proxy, production or posting change is included. Existing native verification reads append normal private-receipt read audit events.

Use `run_ui_remote_action.sh rollback` with the fixed R02 request to restore only these five original files after identity/hash checks. It does not restore an old database, erase history or undo staff activation. Original R01 state and frozen helper are retained; do not invoke the historical R01 requests against the now-installed R02 helper.

## Verification

- Loading regression failed before the fix, then passed. Sixteen AR/EN UI/security assertions pass, including escaped names, supported POST fields, no submitted unfinished grants, full menu and mobile navigation.
- Local employee UI: 18 passed. Native synthetic staff suite: 21 passed. Changed PHP syntax and `git diff --check`: pass.
- First CI run 41343132600 failed only because the package test was incorrectly placed in the PHP container, which has no Git. The actual root replacement/precondition/symlink test and other PHP/MariaDB suites passed. Test routing was corrected: Git/package tests on the runner, root filesystem proof inside the matching Linux container.
- [Corrected full check 41343385016](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41343385016): success. PHP8.2.32, native24.0.1, MariaDB10.6.27, all Mission suites, package/updater tests and real root filesystem test pass.
- [Deployment 41344416142](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41344416142): success. Independent restricted verification also passed. Authenticated administrator DOM confirmed `readyState=complete`, native loader computed `display:none`, visible enabled filter/save controls, all menu groups and the eight-section permission overview, correct Egypt members and no overflow at an effective1280px. The filter button passed hit testing. A browser click attempt timed out on actionability while the shared tab reported `visibilityState=hidden`; this is not claimed as a successful click or assignment mutation.

Admin ERP: affected. Customer Center: not exposed. Native mobile app: not exposed. Responsive staff web page: authenticated AR/EN effective1280/433px checks passed as recorded below. Actual four staff password/login acceptance and browser successful assignment/revoke remain separate; no staff assignment will be changed merely to test this visual correction. Financial update/delete, treasury, receipts/vouchers, reports, categories and payroll workflows remain unavailable in the deployed module; their preview navigation must not be presented as completed integration.


## Approved R03 follow-up: remove the duplicate native sidebar

Ahmed then explicitly requested removing the main ChinaPlanetOS/Dolibarr sidebar inside Office Expenses. Native24.0.1 supports `$conf->dol_hide_leftmenu=1` before `llxHeader`. Both module entry points now set this request-local flag. No query/session flag, persisted preference or native core modification is used. The native top bar and authentication are preserved. The staff register's first column header remains visible at phone widths; this column contains names, unlike the expense row-number column.

Application source: `26ce07fdef53765121532837dbaf7dcc2f6057cf`; three changed runtime files: `index.php`, `users.php`, `staff.css`. Full46-entry artifact `87c828b1ef26187384e705fa184ef5bc9567106c0f19d5c8a17b702efabc1830`, manifest `0518d3464781345ee10a8fab960ed5b54ccb5bc1de6468c72d690da83dbbc69b`. Fixed helper `7fb963f8db83af868471afcfa7d7c5b97677904b963bbf0365404aed2bfc0b63`; installer `c2cb6fabe9831297f8e5a00422c167417547f418dad129bc19b58262fd918b71`. State `/var/lib/chinaplanetos-backup-erp-deploy/m119-sidebar-r03`.

R03 reuses the checksum-pinned R02 replacement/backup/rollback engine in a separate module instance. Its predecessor verification keeps the exact R02 five-file contract and active inode; its new contract permits exactly these three changed files. Native role/receipt/schema checks remain in the preserved R01 worker. Root-private backups contain the exact three R02 predecessors. `run_sidebar_remote_action.sh rollback` restores R02 UI only and verifies it, retaining all data and staff activation. Do not invoke earlier R01/R02 requests against the new R03 helper. No additional privilege is granted.

Twenty UI regression assertions, both changed PHP lints, two package/chain-specific tests and local whitespace checks pass. [R03 full check41345525004](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41345525004) passed all checks. [Initial deployment41345667084](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41345667084) applied the three files successfully but final verify/rollback stopped on the recorded release-identity defect described next; do not report this run as successful.


### R03 release-identity defect and bounded correction

The reused engine had R03 state/path/changed-file constants but retained R02 `SOURCE`/`ARTIFACT` globals for `apply()`. The actual runtime was the correct R03 artifact, but its active state was labelled with the R02 source/archive. Subsequent verification and rollback refused that mismatch before further writes. A new regression assertion reproduced the exact wrong source; setting all release globals made the test pass. Final helper SHA256 is `b51706fff210aba0022502aff7b74b698ed6a6f11b5e0286bd9b5f1a3aaa1674` (superseding the initial7fb963f helper).

The exact observed wrong state SHA256 was `07ceee7362757bb9a7f3d6dd45e2401ccdb8f0f67b666ab32e88199e28b6f454`, deployment ID `59efdf84a2ef46929e017393a360c886`, with its R02 predecessor ID retained. The pinned `repair_sidebar_identity.py` SHA256 `ce655c7b397fd7041f4c081edc469cb9882de81e30156ed80ca16360fe204882` refused any other state/helper, verified the complete actual R03 tree, all three R02 code backups, recorded inodes and native branch baseline, then retained the original bytes as `state-before-global-fix.json` and `helper-before-global-fix.py`. It appended a corrected active state/audit and retained `identity-correction-acceptance.json`; previous audit events were not erased. Result: `RECORDED_IDENTITY_CORRECTED_AND_VERIFIED`. No application reapply, account/schema/role change, database restore, privilege expansion or service operation occurred. First launcher attempt had a syntax error before executing the payload; the shorter checksum-verified launcher succeeded.

Application source remains26ce07f and artifact87c828b unchanged. Delivery correction35a4271 includes the identity regression and a verification-only Actions choice. [Verification41346537136](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41346537136) first stopped at the expected helper hash while reconciliation was still completing; only its failed verification job was rerun after independent restricted readback matchedb51706f. No completed test suite or apply action was replayed.

### Authenticated acceptance

- Fresh native Company Users page: native `#id-left` absent; native top bar retained; exactly one module sidebar; Arabic `dir=rtl`; module menu right edge equals the module layout right edge1236px. Loader computed `display:none`; effective desktop1280px, document scroll width1280px; all four module stylesheets loaded.
- Authenticated China user page200: exactly `rina`, `shanshan`; native sidebar absent. Expense register200: module rendered, native sidebar absent. Ordinary native user list200 after those module requests: native sidebar present and no module fragment. No login HTML substituted for these pages.
- Effective phone433px AR and EN: document scroll width433px, native sidebar absent, mobile menu visible, permission matrix scrolls inside its own container, staff first header visible and loader hidden. English China page retains the correct two staff. Browser restored to Arabic Egypt/Moaz page afterward.
- Rendered screenshot inspected: one original-style dark sidebar on the right, native top bar above and no duplicate Dolibarr side menu. Browser automation's hidden-tab click limitation remains separate from these rendering/route checks; no successful staff Save/revoke or four-password login is claimed.

Current rollback: fixed R03 helper with the pinned `run_sidebar_remote_action.sh rollback` restores only the three recorded R02 files, preserves all data and verifies the prior runtime. A live rollback was not exercised because the desired R03 UI is active. Customer Center/mobile app exposure and financial posting remain excluded.


Final source branch: `codex/m119-office-expenses-local`. Application commit26ce07f, initial deliveryb3cfdd9, corrected delivery35a4271. GitHub verification41346537136 attempt2 completed success; previous matching-engine test result carried forward, only the failed verification job rerun. Independent restricted verify returned `result=verified`, `roles_changed=false`, `schema_changed=false`, `financial_posting=false`. No production access or change.
