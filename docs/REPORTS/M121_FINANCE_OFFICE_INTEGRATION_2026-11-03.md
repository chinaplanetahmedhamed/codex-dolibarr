# M121 — R13 Office/customer-finance integration

Status: **R13 source reconciled; synthetic boundary checks PASS. Full finance PARTIAL.** Work coordinates; Codex root is sole writer of the existing isolated integration candidate; finance_review reviews read-only. Parent/Office owner verified the R13 baseline. No original Office, finance or canonical application files changed by this task, and no shared handoff/manual/tracker was edited concurrently.

## Verified source and preserved work

Office original `/Users/mac/Documents/Codex/2026-11-01/task/office-expenses-erp` is branch head53ac3ca9ed5eccbaf8c879379bcff19c6683b51b. All109 owning-module/compatibility files are byte-identical to app commit25866ad685e65c23dbeeca692c8719498047a97c and VS Code checkpointd438ae8. Independent file checks confirm this, not merely a matching branch name. The six existing M121 tracked UI/label deltas plus added RequestProgress renderer from `.worktrees/m121-office-evidence` remain byte-identical in the integration candidate. No application rewrite or extra Office implementation was needed.

Candidate `.worktrees/m121-finance-office-integration` remains detached at canonical310deeaa70ced8d1a74aa84c802e2c705e54b2a1. Earlier154-file scoped source closure is unchanged: selected finance service/library/view/test files plus the Office owning module, compatibility shims and tools. No whole historical customer module/branch was merged. Canonical and source worktrees were read-only. Source availability is not module installation/enablement. The previous source hold is resolved; prior provisional report/manifest/results/images are preserved in the artifact bundle as history.

## What changed and actual results

New test `deploy/tests/test_m121_finance_office_boundary.php` exercises actual CustomerFundsService SQL, Office DraftService/EmployeeController/LocalDraftStore, FinancialPlan and product renderers on one fresh shared synthetic SQLite database. Native bank/customer/supplier payment evidence is seeded, never posted by core financial commands.

**41 assertions pass on PHP8.2.0 and8.3.21.** Customer100→reservation40 (paid0/available60)→verified supplier200 attribution/retry (paid40/reserved0/available60). Office40USD stays a separate staff19 draft with native_record=null. Office save/retry and rejected injected source/changed intent/badCSRF/employee-post actions preserve every customer/native table. Customer supplier settlement preserves Office draft/audit. Unsupported Office allocation targets and wrong native supplier IDs fail with no source changes. Three Office originals40USD/700.10EGP/180.29CNY remain separate; no cross-currency total or customer debit is invented.

Pure Office plans distinguish expense/cash, owner funding and customer-funds liability. They register no customer receipt and execute no posting. A seeded unsupported Office-style base bank proof returns Unknown in CFS rather than certified/duplicated cash, then original proof is restored.

Full **R13 WorkspacePage** is now rendered in EN/AR as a supplied synthetic read-only sample, in addition to actual employee draft and customer funds views. It shows Approved · unpaid and no native posting action. This approved sample has a separate synthetic reference; it is not a saved workspace approval or promotion of the employee draft. Rendering leaves customer position and Office drafts unchanged.

Independent review found the first negative-test helper could accept an unrelated RuntimeException/PDOException. Corrected to exact expected class/message for all three guards, explicitly excluding PDOException. Three self-controls reject matching-message SQL errors and unrelated runtime failures and accept the exact intended guard. Final41-check runs include this correction; earlier33/38 results remain history.

Both PHP versions also pass28Office draft-workflow,10employee-summary,107request-progress and10financial-plan tests. Changed test lint, diff check and incremental patch reverse applicability PASS. Previously recorded154snapshot/1128managed finance assertions remain independent synthetic evidence. No full Office native-workspace migration/posting suite was invoked as an alternate route around the denied native-finance action.

## Visible result and limits

[Integration summary](http://127.0.0.1:18776/) and [full R13 workspace sample](http://127.0.0.1:18776/office-en.html) serve generated fixed HTML on loopback only. Actual R13 sidebar/top workspace styling and M121 progress guidance are retained; own CSS/fonts are embedded. Script and financial actions are absent, and navigation is view-only. This is a source-renderer sample, not an authenticated ERP or a working branch-management backend. The historical draft-only preview did not represent the full R13 workspace.

Chrome EN/AR desktop shows exact40USD, Approved · unpaid, disabled posting, and the synthetic/nonposting provenance notice. Current screenshot is the actual English full-workspace renderer. Requested390x844 viewport override remained1728x863 in this browser session, so full-workspace phone-width acceptance remains pending; no false390px claim. Override reset. Prior summary-only AR390 verification is earlier separate evidence, not current full-workspace acceptance.

Artifact bundle `outputs/M121/finance-office-integration-2026-11-03/` contains source-reconciliation.json, r13-verification.json, result.json, manifest.json, boundary-test.patch and current/historical screenshots. Source/patch remain local. The fixed viewer was restarted after the previous owned process ended; current loopback-only session45516 has no DB or financial backend.

## Remaining boundaries and blockers

- Historical R13 UI acceptance grants no authorization or proof of real-finance behavior. Office unallocated customer receipts lack native Paiement for CFS registration; invoice-linked bank proof and separate reversal identity require verified owner-to-owner handoffs. No automatic linking or extra cash source added.
- Office Treasury is whole-account cash, not company-ownership-adjusted funds. Never add it to CFS managed held/available or assume customer reservation prevents Office spend. Native funding/bank/source reconciliation remains pending.
- Native snapshot quarantine caller handling remains open: funding/project/portfolio may query after the handle is quarantined. No native readiness claimed or overlapping caller fix attempted here.
- `python3 deploy/tests/m121-native-finance/run.py` remains previously DENIED before execution, pending specific approval; no retry or alternate engine/core posting. No production/staging access, real posting, deployment, privilege change, migration/module enablement or backup/restore. RESTORE_PROOF=NO stays explicit.

Parent should reconcile this checkpoint into the shared manual/tracker: source hold resolved, R13/current deltas preserved,41checks plus Office regressions, native/full-finance/phone limits retained. This task made no concurrent shared-record writes. All phases remain open.

Final independent read-only review: negative-guard finding resolved; no remaining defect in this bounded test review, exact testSHA d1af1ea44f47d5abf5d7906500529bf87253642fcfb937cc03924b52feb04c59. No native engine/posting acceptance implied.
