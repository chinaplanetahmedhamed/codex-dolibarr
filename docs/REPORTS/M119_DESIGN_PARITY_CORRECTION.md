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
