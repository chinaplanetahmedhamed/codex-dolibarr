# M119 dropdown and entry correction — native acceptance2026-11-02

## Outcome

Deployed to backup-ERP. The large unscaled operating-system select popup shown in Ahmed's screenshot is replaced by Dolibarr's already loaded Select2 component inside this module. No new frontend dependency, external CDN, core edit, permission change or financial posting was introduced.

The scoped original-style skin keeps system font14px, slate text, white list, indigo selection,12px field/popup corners, bounded scrolling and search on the staff picker. The actual native select/name/options remain authoritative for GET/POST. JavaScript enhancement is skipped if the native library is unavailable. Labels remain associated with the combobox. Dropdowns in receipt/edit dialogs attach inside the dialog top layer. Select2 selections dispatch one native change for existing form rules, then refresh dependent widgets through Select2-only listeners.

The default native top-bar entry now reaches the new workspace dashboard, preserving authorized branch/language. Explicit legacy views, draft/attachment links and filters remain on the legacy route. The Users Back link consequently returns to the new dashboard. Direct native-login default forwarding is updated in source; an actual fresh credential login was not exercised.

## Defect caught during acceptance

After R09, changing branch on the retained legacy form updated its real currency to CNY while its enhanced display still showed EGP. This was reproduced without submitting data. R10 refreshes dependent dropdown displays after synchronous native form rules. Final native tests confirm China displays CNY(RMB), Egypt displays EGP, and each popup correctly disables the other branch currency. No amount, account, quote or grant was changed by the test.

## Verification

- Local PHP syntax, JS syntax, diff check, employee18/0, workspace33/0 and staff UI regression passed.
- R09 matching-engine full Mission CI and fixed-package source preflight/backup/apply/verify: [41425052642](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41425052642), `FAILED_SUITES=0`.
- R10 full Mission CI and exact two-file preflight/backup/apply/separate verify: [41427006885](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41427006885), successful. Frozen-chain, strict package/request, inode replacement and rollback tests run in isolated Linux CI.
- Actual native AR/EN staff lists at1600px and392px: popup width equals control width (desktop374.8px, phone299.4px), stays inside viewport,14px list font,40px search field, zero document overflow.
- Actual desktop browser click opens the native enhanced staff list (`aria-expanded=true`). Native search `moaz` returns Moaz; Enter selects native ID5, actual FormData remains5, one native change fires, popup closes. The GET permissions page returns200 with nine section rows,31 existing checked grants and branch scope. No permission POST was submitted.
- Receipt currency dropdown renders inside its open native dialog at14px and stays inside viewport, with EGP/USD options. The same enhancement is loaded by users/workspace/legacy index entrypoints.
- Final native default entries for both branches and languages all resolve200 to workspace overview with exact requested branch/language. Explicit legacy expense route remains available with synchronized real/displayed currency and correct disabled options.
- Independent restricted R10 verification after native acceptance passed. Existing schema, four staff memberships and native money baseline are retained. Physical phone taps and staff credential login remain untested.

## Immutable release / rollback

Final application `56ac3d4187a0d7d11d897df7101222c64b706531`, delivery `a9e3456247850dc0657564bd57b5615a81f75fde`, branch `codex/m119-office-expenses-local`. Full59 runtime files plus support declaration. Artifact `8c22bf3e484207785a2cafa2f393d18a7d72a60cea82c33dca3b1180089a6600`; manifest `2e998a180d99b324dd6b60f1fc6097b26df682bcce523028373578b0db8c0fca`. Current five-action helper `e36452a4dc36d55657f742f86314c26acb8c47006d7d045b7b52148d520a2527`, root:root0755, existing sudoers unchanged. Private state/exact two predecessor backups: `/var/lib/chinaplanetos-backup-erp-deploy/m119-entry-r10`.

R09 application819dac01d1e63ecce106062017e3c7194eb1b766 installed `users.php`, `index.php`, `employee.css`, `workspace.js`; R10 replaces only `index.php` and `workspace.js`. R09 predecessor backups/frozen R08 helper remain in `m119-dropdown-r09`. Use only the matching fixed `run_entry_remote_action.sh rollback` request to the existing five-action helper; this restores the two R09 bytes and retains all data/audit. Do not send earlier release requests to the current helper. A clean rollback also restores the known R09 dependent-display defect; review that limitation before choosing it.

No financial adapter, GL module, chart, staff privilege, password, native balance, infrastructure or production change. The separate operational-readiness audit still applies: these corrections do not make unposted requests into actual payments or accounting entries. Admin ERP affected; Customer Center/native Mobile App not applicable.
