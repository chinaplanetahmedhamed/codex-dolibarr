# M121 financial source reconciliation review — 2026-11-03

PR59 is appropriate for a scoped semantic port, not whole-file replacement. This is a prepared temporary fixture and diff only. Canonical source, dirty work and Git refs were preserved. P1 runtime/source-parity and session/scope ownership gates remain blocked. No server access or runtime acceptance was performed.

## Pins and ownership

Canonical: `codex/m084-dashboard-repair`, HEAD `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`. Root owns shared canonical scope; finance_review performed bounded source review. GitHub connector verified PR59 open/draft head `6bdc868e7bfeb44c9ffe8b3ca5545300ab0df298`, base `016893bbc6382f050e819a8a1984efb2aafde7a1`. No current release authority is inferred from historic CI or reviews.

## Findings and safe integration

1. Preserve newest local financial library. PR's older full file removes `$includeSupplierIdentity=false` controls in `cpcc_financial_commitment_rows()`/`cpcc_financial_project_charges()`, returning supplier labels to caller projections unconditionally. Current `funding.php`/`project.php` explicitly opt in only for internal users. Full replacement would regress customer privacy.
2. PR's older library also removes `cpcc_managed_funds_schema_ready()`, called by `admin/managed_funds.php:15`, causing a missing-function failure.
3. Port only the summary's unpaid-invoice project argument into the newest library. Port portfolio by-currency rendering and shortfall/name/ID sorting while retaining local English UI behavior. Preserve all other helpers and services. No ledger, posting, schema or arithmetic rewrite is proposed.
4. PR synthetic harness must copy local `ui.lib.php` alongside the financial library. The reviewed temporary candidate does so. Fixtures now shuffle Beta/Alpha and add Gamma without shortfall, strengthening sorting coverage.
5. Canonical finance files match the inspected Office Expenses checkout. Boards/M116 source variants have older Customer Center files and lack CustomerFundsService/OperationSupplierWorkflowService. They must not replace canonical finance implementations wholesale.

## Verification actually executed

PHP 8.3.21 CLI. Three PHP lints passed: candidate financial library, portfolio and adapted M121 test. Adapted M121, existing multicurrency and M029 source tests passed. Privacy opt-in and managed-funds helper probes passed. The unchanged canonical page fails the adapted M121 currency assertion as a negative control (exit255); the fixture candidate was restored and M121 passed again.

M028-C04 fails on both baseline and candidate: `M028-C04 FAIL: totals mobile labels`. It asserts older bilingual labels, while preserved canonical internal UI uses English. This is an existing source-test expectation mismatch; no functional mobile acceptance is claimed. Exact commands/results and full baseline/candidate SHA256s are in manifest.json.

## Existing finance semantics requiring a separate contract

Run `php /tmp/m121-finance-reviewed-candidate/deploy/tests/test_m121_review_probes.php`. It loads the adapted synthetic test, then uses M121PaidProjectDb query doubles: project11/customer7/entity2; invoice100 USD dated2026-10-01; cutoff2026-11-03; no commitments/custom entries. For ordinary payment fixtures the native allocation query returns the paid amount. Project statements skip native receipt credits for projectid>0.

| Case | Available | Unpaid invoices | Required funding |
| --- | ---: | ---: | ---: |
| Paid25 on invoice100 | -25 | 75 | 100 |
| Paid100 on invoice100 | -100 | 0 | 100 |
| Payment25 occurs after historical cutoff | 0 | 75 | 75 |

The last fixture excludes the later allocation but returns current paid25 in the unpaid-invoice aggregate, matching source SQL: invoice date is filtered but the payment aggregate lacks `ppay.datep` cutoff; current `f.paye` also affects historical reconstruction. These observations reproduce preexisting source semantics, not new PR regressions, actual SQL execution or approved accounting results. Do not rewrite arithmetic until the project cash/funding and historical-as-of contract is settled with the owner/accountant.

## Remaining acceptance

Equal-name/ID sorting, reversed/funded/deficit boundaries, actual historical payment/credit queries, cross-entity/customer direct routes, authenticated finance/nonfinance/external ACLs and desktop/phone AR/EN rendering remain pending. Runtime schema/parity and staging isolation must be verified before authenticated backup-ERP acceptance. No production permission or acceptance is implied.

## Deliverables

- reviewed-integration.patch: only two reviewed production-file deltas and the new adapted M121 test, against canonical baseline; not applied.
- manifest.json: source pins, baseline/candidate SHA256s, exact command results and limits.
- deploy/tests/test_m121_review_probes.php: source-only characterization/privacy probes; excluded from implementation patch.
- Existing fixtures and adapted tests in this temporary directory support reproduction; none is a deployment artifact.
