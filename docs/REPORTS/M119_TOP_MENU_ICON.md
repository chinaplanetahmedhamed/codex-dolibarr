# M119 native top-bar icon correction — 2026-11-02

Ahmed requested the Office Expenses module icon and link in Dolibarr's top bar. The existing backup-ERP native menu row211 already held the correct receipt icon, canonical URL, internal-user selector, entity boundary and approved staff permission expression. Direct authenticated module access returned200, but native menu administration evaluated Enabled as No and ordinary ERP headers omitted the icon.

## Cause and bounded correction

Official Dolibarr24.0.1 `dol_eval` rejects the old menu expression's raw `$conf->entity` variable. A native evaluator reproduction returned its explicit unsupported-variable syntax error, even though ordinary PHP can evaluate the expression. The earlier menu mock tests did not exercise the actual native evaluator. New regression tests first failed against the existing descriptor.

Only native menu row211's enabled field was corrected through the existing authenticated menu editor with a fresh CSRF token. Its value is now:

```php
isModEnabled('chinaplanetofficeexpenses') && !$user->socid && getDolEntity() == 1
```

The native editor's `alphanohtml` input filter stripped double quotes in the first submitted candidate; a fresh independent GET exposed this immediately. The corrected single-quoted expression survives the native filter and evaluator. There was no blind retry, duplicate menu, module disable/re-enable or direct SQL mutation. Before-state configuration is retained outside Git in the private evidence directory, file `2026-11-02-top-menu-before.json`; no token was saved. Fresh form readback compared every submitted non-token field, confirming only enabled changed. Native Enabled and Permissions now both evaluate Yes.

The URL, position93, `fa-receipt`, all-menu handler, entity/internal-only restrictions, original staff permission text and module identity remain intact. No grants, users, accounts, opening balances, financial records, private receipts, schema, core files, service settings or production environment were targeted.

[Module descriptor](../../custom/chinaplanetofficeexpenses/core/modules/modChinaPlanetOfficeExpenses.class.php) and [menu synchronizer](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeMenuSyncService.php) now use the same evaluator-safe expression for future registration. The synchronizer accepts only the two exact quote spellings of the existing approved staff permission expression, matching native registration normalization. It does not broaden rights or auto-adopt arbitrary menu changes. Source updates are separate from the native configuration fix; deployed module files were not replaced. Reinstalling the old descriptor could restore the broken condition, so future registration must use the corrected source.

## Verification

- New [native menu-condition regression](../../deploy/tests/test_m119_native_menu_conditions.php): old descriptor1pass/2fail, corrected descriptor4pass/0fail, including the actual native input filter, enabled/disabled module, internal/external and entity isolation, bootstrap administrator and activated staff read ceiling.
- [Menu synchronizer suite](../../deploy/tests/test_m119_native_menu_sync.php):20 assertions; exact row reuse, permission/duplicate/lookup failures and native quote normalization.
- Existing standalone module suite:8pass/0fail. Changed/new PHP files lint clean; source whitespace check passes.
- Authenticated fresh headers on Financial Center, native Banks and the expenses page include exactly the native icon anchor plus its text anchor. Icon class `mainmenu fa fa-receipt`, with an actual receipt glyph. No login-page200 was counted as acceptance.
- A real browser click on the Financial Center top-bar icon opened the canonical module page successfully; native authentication and the original module sidebar remain active.
- Current authenticated administrator was tested; no staff passwords or alternate-user sessions were created. Staff visibility ceiling is tested through the native evaluator, not asserted as a completed real staff login.
- Existing native-shell CSP/PWA warnings on the module remain unrelated; this change did not weaken CSP or modify core.

Admin ERP: native top-bar configuration fixed and authenticated click verified. Customer Center: its staff Financial Center header includes the same icon; customer access remains excluded. Separate Mobile App: not applicable, no new API.

Rollback: through native menu editor, restore only the original enabled value from the retained before-state after verifying the row/URL/permission preconditions. Retain the menu, module activation, permissions and all business data. A database restore or module reinstall is unnecessary. No server deployer or production action was used.

Branch: codex/m119-office-expenses-local. Authenticated AR/EN expenses and staff Financial Center DOM checks at effective desktop1280px and phone433px pass in all6 cases: receipt glyph present, icon visible and inside the viewport, no page overflow, native sidebar retained outside the module and hidden only inside it. Source commit and matching-engine CI follow below.

Source correction4830ceea162454d567be68b14f7d8e79e13d096f was committed and pushed. First check-only [Actions run41356423648](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41356423648) passed the new4 native-evaluator/filter cases,20 menu synchronization assertions, MariaDB and all other suites except one legacy employee UI assertion expecting the now-rejected literal `$conf->entity == 1`. That assertion now checks `getDolEntity() == 1`; its administrator/staff activation checks are retained, and the native evaluator additionally verifies entity/external/module denials. The employee UI suite passes18/18 locally. All20 local CLI suites pass after this test expectation correction; two historical authentication scenarios remain explicitly skipped. This CI failure did not represent a failed native menu mutation; fresh native Enabled/Permissions and real icon-click acceptance had already passed. No app release was applied by this check-only run.

Final check-only [Actions run41356754880](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41356754880) succeeded for exact source bcc5fc1be3447fd502482314e1cc165fce0e46cd on PHP8.2.32 / Dolibarr24.0.1 / MariaDB10.6.27. All CLI, native evaluator, HTTP, Linux filesystem, MariaDB, syntax and artifact-boundary checks passed; all deploy jobs were skipped. The resulting code package is retained by CI but was not applied to the server. Native top-bar visibility is already fixed through the independently verified configuration change. Fresh reload of the previously open staff Financial Center shows the icon; the active browser is left on the Arabic Egypt expense register with the icon visible. This final documentation commit does not alter runtime bytes.
