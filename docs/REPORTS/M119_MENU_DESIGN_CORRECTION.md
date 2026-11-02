# M119 menu and notice correction — 2026-11-03

Status: COMPLETED for this bounded UI correction on backup-ERP. Ahmed explicitly requested removal of the repeated notice and a complete menu/dropdown design check. Financial activation remains a separate task.

## Result

Removed the repeated page-level draft/payment explainer from the six workspace section layouts. Action-specific payment-disabled, opening-reconciliation and confirmation messages remain where they explain an actual state. User-entered notes are unchanged.

Fixed the underlying dropdown sizing problems throughout the module:

- Dolibarr desktop CSS forced a300px minimum with `!important` onto a235px employee picker, pushing the popup outside its dialog. A module-scoped override retains the real field width and existing Select2 component.
- Select2 measured available viewport space instead of the smaller modal scrollport. Modal lists now choose above/below, bound their scrollable results to the dialog and viewport, and align with the visible control.
- Stable modal scrollbar space prevents controls changing width while a popup opens. Search and window resizing recheck the fit. Closed widgets cannot reposition another active picker.

The existing font, palette,12px control/list corners, focus/selection states, native select names/values and change-event bridge are retained. No new dependency, CDN, core edit or replacement dropdown framework.

## Application files

Relative to financial R11, only these UI files changed:

- [employee.css](../../custom/chinaplanetofficeexpenses/employee.css)
- [workspace.js](../../custom/chinaplanetofficeexpenses/workspace.js)
- [OfficeExpenseWorkspacePage.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspacePage.php)

The reusable read-only acceptance runner is [m119_menu_browser_audit.js](../../deploy/tests/m119_menu_browser_audit.js). It never submits a form or changes native select values during the matrix audit.

## Verified acceptance

Actual deployed R13, authenticated administrator session, no browser stylesheet/script interception:

| Coverage | Result |
| --- | --- |
|11 sections × Egypt/China × Arabic/English × desktop/phone |88 cases passed |
| Opened filter/modal/staff dropdowns |240 passed |
| Mobile navigation menus |44 opened with11 authorized links |
| Effective viewport widths |392px and1280px |
| Popup width/alignment, dialog/viewport clipping,14px font and RTL/LTR | No failures |
| Document overflow and wrong branch/language navigation | None |
| Repeated page-level notices |0 |

Sections: dashboard, treasury, receipts, expenses, advances, categories, reports, employees, payroll, settings and company users. Setup controls hidden by current accounting/module state were not activated merely to test styling. Physical phone interaction is not claimed.

A separate client-side search and synthetic keyboard-event check matched `rina`, selected native ID6, retained FormData staff_id6, dispatched one native change and closed the popup. No permissions or other form was submitted. Physical keyboard activation is not certified by that synthetic event. The shared page was restored to Arabic/China Employees at the original2000×1000 browser viewport, showing the correctly aligned employee dropdown. Popup232.13px/control232.14px and identical left edge were read back from the deployed DOM.

Original reference employees-page styles were inspected read-only: system sans family, slate palette and12px corners. Existing module control typography and styling remain intact.

## Checks and immutable releases

Local PHP lint for the changed page, JS syntax, workspace41/0, employee18/0, receipt15/0, staff UI regression and diff checks passed.

- R12 application `78efe9a5fca48d26602f0b6060d1b5a9bdf81990`; delivery `6fb3e36d00bfebdf07b73823f6e572e7cdebdec5`. [CI41482672184](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41482672184) passed the full matching-engine suites and four scoped deployer tests. Separate restricted preflight/apply/verify passed. The wider audit then identified the additional vertical cases, addressed by R13.
- Final R13 application `25866ad685e65c23dbeeca692c8719498047a97c`; delivery `a0b1c6faa181b59c5fdb408bc99a2e9dd988b71a`. [CI41487133820](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41487133820) passed full PHP8.2.32/Dolibarr24.0.1/MariaDB10.6.27 checks, including the frozen-chain R13 tests and unchanged native financial suites; FAILED_SUITES=0. Separate restricted R13 preflight/apply/verify all returned verified.

Final complete closure:69 runtime files plus declaration. Artifact `d57aa2ef03b0e3640f4b2a9d020f39057a54b081e52f88d1794304d5ebed8e4d`; manifest `39bca9d49270c18bb3e6708f1cabc561bb01c29e68dd78086f780b2a5a465cbe`. Installed fixed five-action helper `de3b8f431ccdc5f6729a1df5b497b279e58cd0603b0ab819b97a01871382472d`, root:root0755, independently checked over restricted SSH. Existing sudoers unchanged.

Two direct artifact transfers were closed by the server. The exact package and manifest were transferred through the existing verified VPS Termius SFTP profile instead. Their regular-file/root-owner/mode/link-count and both pinned hashes were checked before restoring the existing uploader ownership1002:1002/0600 on those two files only. Restricted SSH independently rehashed both and verified the owner before installation. No SSH/security/network configuration was changed.

## Data, scope and rollback

No schema, native financial backend, financial configuration, account, balance, membership, password or permission change. Browser acceptance was read-only. Admin ERP affected; Customer Center and native Mobile App not applicable. Production was not accessed or changed.

Private R12 state and two source backups remain at `/var/lib/chinaplanetos-backup-erp-deploy/m119-menu-r12`; R13 state and its exact two predecessor backups are at `/var/lib/chinaplanetos-backup-erp-deploy/m119-menu-r13/before-0` and `before-1`. Independent verify checks those retained bytes. Only matching [run_modal_menu_remote_action.sh](../../deploy/releases/m119/run_modal_menu_remote_action.sh) requests are valid for the installed helper.

R13 rollback restores only R12 employee.css/workspace.js. It retains the compatible financial parser, removed repeated notice and all data/audit/evidence; it also restores the predecessor's known vertical popup defect. Never use historical R10/R03 helpers or restore an old database over later financial records.
