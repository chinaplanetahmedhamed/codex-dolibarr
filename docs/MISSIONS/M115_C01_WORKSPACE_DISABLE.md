# M115-C01 — shared workspace disable and palette finish

Status: APPROVED by Ahmed’s direct request to add disable/hide customer workspace and explicit selection “للفريق كله، مع إعادة التفعيل”. Continues the existing feature-only live publication workflow; no customer is disabled as part of deployment.

Only administrators who can already enable a workspace can disable the shared container. The option is in the customer card menu and explains that existing project-board links remain available to authorized staff. Native customer/project/task data, memberships, comments, files, favorites, priorities, appearance and board IDs remain intact. Re-enable with the existing Enable selector, now searchable. Native customer extra-field updates touch only cpo_board_workspace. No schema, flag or rights migration.

Also finish the approved General-board color consistency: neutral breadcrumb shell/blue focus, navy default for newly created project boards, existing chosen colors/images untouched.

Verification: 23 rollback-based native preservation/permission cases on both 24.0.1 and 24.0.2; affected 41-case gallery suite on both. Real local authenticated browser cancel, disable, full reload, searchable re-enable, same board/card/native task preservation. Original 25-table hashes restored after scoped fixture cleanup. Rendered local breadcrumb computed rgb(22,26,29). No claim of 390px local coverage from the 872px capture; final production responsive check remains required.
