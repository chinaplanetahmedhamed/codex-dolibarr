# Customer account statements implementation plan

Goal: implement M116 as an isolated manual operational ledger in the existing Boards module.
Architecture: immutable posted entry headers, versioned conserving allocation batches and append-only audit/command records. Existing native customer/project/task ACLs supplement finance-specific rights. Scoped view projection shared by UI and exports.
Tech stack: PHP 7.4+, Dolibarr 23/24 APIs, DoliDB MySQL, BCMath, ZipArchive; vanilla JS/CSS. SQLite adapter is test-only.
Spec: docs/MISSIONS/M116_CUSTOMER_ACCOUNT_STATEMENTS_TRIAL.md

Global constraints: no live deployment, native accounting writes, customer portal exposure, payment adapters, permission grants, push or merge. Use USD base and configured original currencies. Preserve existing Boards behavior.

Review focus: hidden project totals; historical card movement; concurrent save/reallocation; allocation penny residuals; idempotency key payload mismatch.

1. Exact amount math + schema: write failing arithmetic/conservation tests; implement BCMath with half-up cents and explicit residual; create test-only installation schema/migration. Test zero/negative/oversize/exponent/precision input.
2. Policy and transactional service: failing persistence tests for draft/edit/post/reversal, allocations and command replay. Implement native scope validation plus finance rights, row locks, version checks, idempotency and audit. Read projection with totals/original-currency subtotals and no draft inclusion. Test wrong customer/project/card, external actor, missing finance permission, stale version and rollback.
3. UI/exports: shared scoped projection, customer/project/task context, add/edit/post/reverse/reallocate/custom fields, paired received/spent view, mobile dialog. True XLSX values and print/Save PDF use same rows and totals. Wire context links into Boards/card without changing existing mutations. Test forms, scoped export totals/text escaping, print layouts.
4. Verification: PHP lint, source/regression suites appropriate to module, synthetic DB tests, representative browser desktop/mobile only where permitted; independent whole-branch review, fix serious findings. Document installation/rollback and native QA limitations. No automatic production acceptance claim.

Execution: native in this session, already authorized by delegation. Record progress in M116 report; no extra implementation approval requested.
