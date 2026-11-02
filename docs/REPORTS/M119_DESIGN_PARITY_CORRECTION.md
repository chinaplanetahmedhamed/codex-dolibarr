# M119 original design correction — 2026-11-02

Ahmed requested a Back button, missing report charts, and comparison of all module surfaces with the original Finance SaaS. This is a bounded M119 interface correction; it does not authorize financial posting or production.

## Source comparison and implementation

The authenticated original reports, treasury, receipts, expenses, categories, employees, payroll and users surfaces and the sanitized original source were reviewed. Ordinary UI uses the original effective system sans font; Cairo remains local for charts and receipt print. Slate backgrounds/text, indigo controls, emerald cash, rose expenses, purple payroll, amber deficit, 16/24px radii, spacing and original desktop/phone grids are retained. Dolibarr's more specific native button selector was overriding the module font/background; the replacement is scoped to this module.

Back links preserve language and branch and return to the module dashboard. Reports now contain six-month grouped deposit/expense bars, a category doughnut, four selected-currency metrics and the monthly comparison table. Charts use authorized native request rows, retain exact decimal arithmetic, exclude archives and never pool currencies. Empty charts have explicit states. Native posted cash remains a separate table. CSV and print controls remain available. SVG and local fonts avoid external chart/CDN dependencies.

Dashboard, treasury cards, employee/payroll summaries and category proportions follow original components. Ordinary add/edit forms now use native dialogs, like the original. The previously verified three-copy receipt layout is preserved. The native staff permissions matrix remains intentionally richer than the original role selector. Advances and standalone branch settings have no exact original counterpart. Native branch/currency/draft controls are intentional differences, so pixel identity is not claimed.

## Evidence and release status

Local PHP workspace suite: 33 passed, zero failed, including exact chart totals, six months, currency/date validation, archive exclusion and own-worker totals. Synthetic AR/EN phone checks: all ten workspace views at392px, no document overflow or unlabeled inputs. Desktop rendered charts visually inspected. Native staging acceptance and deployment are pending at this checkpoint.

No schema, membership, cash balance, payment, accounting, FX or production change. Financial activation choices remain separate. Admin ERP affected; external Customer Center and native Mobile App are not applicable to this staff-only UI correction.

Rollback must restore only the exact changed source bytes from the prior R06 release, preserving all records and audit history.

## Final backup-ERP acceptance — 2026-11-02 07:18 UTC

Deployed and independently verified on the existing canonical backup-ERP runtime. R07 deployment [41403716621](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41403716621) installed the nine design files. R08 deployment [41404605071](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41404605071) completed four-file acceptance: treasury defaults to an explicit current-month statement, dates are editable, dashboard includes the original fourth net-movement card, and the native `#mainbody` action skin is overridden only inside this module. SVG labels remain readable at phone widths. Earlier pending deployment language above describes the local checkpoint only.

Final immutable application `afeb72b1c0e69233c59e99afb18609fd7e3053dc`, delivery `ca82237c6ba9c9276fc006e9f3d367d613f1e59a`; branch `codex/m119-office-expenses-local`. Full 59-file runtime closure plus support declaration, artifact `a1c993decfbf641072ce48a375c144fb1696356c87de5ee21f8e0b291647325d`, manifest `0cdd6664561890513112173d25d76848421874b7ea0e7fa585d18f038d594f25`. Installed five-action helper `ee54d35e9f992d78d3931c2820b1394c724799ac8dd8c2524c232cd760e74ffb`, root:root0755; existing sudoers unchanged. Private R08 state and exact four predecessor backups: `/var/lib/chinaplanetos-backup-erp-deploy/m119-design-r08`. R07 nine predecessor backups remain separately under `m119-design-r07`.

Runtime files changed across R07/R08: `index.php`, `employee.css`, `workspace.css`, `workspace.js`, and classes `OfficeExpenseEmployeePage`, `OfficeExpenseStaffPage`, `OfficeExpenseWorkspaceController`, `OfficeExpenseWorkspacePage`, `OfficeExpenseWorkspaceService`. Deployment helpers/tests are delivery-only. No schema, grants, account, opening balance, posting adapter, password, infrastructure or production change.

Acceptance evidence:

- Matching PHP8.2.32 / Dolibarr24.0.1 / MariaDB10.6.27 Mission CI: `FAILED_SUITES=0`; workspace suite33/0. Frozen helper-chain/closure, negative request, atomic replacement and rollback tests pass in isolated Linux CI.
- Authenticated native44 pages: ten workspace sections plus users, both branches and both languages. Each tested at actual392px and1280px:88 responsive cases, zero failures/overflow. The first broad pass encountered a browser `load` timeout on the final China settings route; the completed pass used DOMContentLoaded and all44 responses were200. This is DOM/browser evidence, not physical-device taps.
- Native final report headings: original system font,24px/900/#0f172a; chart headings16px/900/#1e293b; chart plots256px and panel radius24px. Final Back button #f1f5f9/#334155 and print #1e293b/white,12px radius, match intended original components. Inputs retain original font, slate text and12px corners. Both chart panels and twelve monthly marks present for each branch/language; permitted EGP/USD versus CNY/USD selections remain separate.
- Back navigation from China/English reports returns to China/English dashboard. Native treasury date controls default2026-11-01→2026-11-30; actual period fields are supplied by native queries rather than fabricated zero defaults. Dashboard has two currency rows with six white metrics and two green cash cards.
- Programmatic native dialog events open and close expense/category/employee/payroll/receipt editors. Invalid branch currency, invalid month and reversed period return422 without chart data; CSV returns200 with attachment headers. Local leap-year selector updates February2024 to29 days.
- Fresh native archived synthetic receipt paper captured without scripts/hidden native inputs and exported using current styles: one A4 page, three copies; all three QR independently decoded at300dpi to the same authenticated receipt target on exact backup-ERP host. Local English print regression remains covered; the previously verified long-note behavior is unchanged. Physical printer acceptance remains untested.
- Independent restricted R08 verify after deployment passed, retaining native role/money/schema baseline. Browser checks created no operational records.

The original does not model native branch permissions or separated original currencies. Those controls, explicit unposted labels, accessible empty states, safe SVG charts and the granular staff matrix are intentional differences; this is design/component parity, not a claim of pixel identity for every field or workflow. Customer Center and native Mobile App remain not applicable to this finance-only staff surface.

Rollback: use only the matching fixed R08 request with `/usr/local/sbin/chinaplanetos-backup-erp-deploy rollback` (delivery wrapper `deploy/releases/m119/run_design_acceptance_remote_action.sh`). It restores the four exact R07 predecessor files and retains records/audit. Do not invoke old release requests on the current helper or restore a database over subsequent records. Production was not accessed.
