# M121 bounded cutoff correction — prepared source, 2026-11-03

**Outcome: local source correction and regression tests are prepared and reviewed; nothing was applied to backup-erp or production.** The scope is payment/credit-creation cutoff consistency, with existing financial arithmetic and current invoice lifecycle retained. This is not a full historical accounting implementation.

## Changed source

| File | Change |
| --- | --- |
| custom/chinaplanetcustomercenter/lib/financial.lib.php | One strict calendar cutoff helper; consistent next-day exclusive limits; payment and credit source scope; dated charge allocations; project forwarding. |
| deploy/tests/test_m121_financial_cutoff.php | New actual-query regression suite with110 checks. |
| deploy/tests/test_m028_c04_financial_portfolio_mobile.php | Reviewed repair of stale bilingual-literal assertions, strengthened table-role/column and active CSS checks. |

Base branch is codex/m084-dashboard-repair at `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`; changes remain in the canonical working tree. HEAD and index are preserved. Exact original source bytes were archived and matched pre-edit hashes. No whole-tree overlay, new module/ledger, schema migration, source relocation, native posting, credential/grant change or application release commit occurred.

[Three-file review patch](../evidence/m121-cutoff-correction-2026-11-03/source-correction.patch), SHA256 `4138432fccbfa5b84b92f475d0b40c5488cd9ea625ce5ee8ce4b3897888ff5ae`; [source and evidence manifest](../evidence/m121-cutoff-correction-2026-11-03/manifest.json). This is a review patch, not a deployable release artifact. Earlier PR59/PR60 candidates are not merged; future integration must reconcile against this newer library rather than replay an older whole file.

## Corrected behavior

- A valid named day uses `event_date < next_day`. Noon and23:59:59 are included; next midnight and future payments are excluded consistently from native receipts, allocations and unpaid payment aggregation.
- Strict calendar, leap/year rollover and representable bounds are checked. Invalid nonempty dates fail closed at outer queries; malformed internal column identifiers are rejected. The helper performs calendar arithmetic without converting stored timestamps or choosing a new business timezone.
- Unpaid payment aggregation retains native payment-validation policy and adds the current entity filter. Credit aggregation accepts explicit entity/customer scope and creation-date cutoff. Unknown credit creation dates remain in undated/current mode and are excluded from an explicit cutoff.
- Native credit allocations use both target-invoice date and credit creation date. Legitimate manual credits need no source invoice. Direct and invoice-line-only links are covered; the no-argument credit SQL builder remains executable for the existing mobile consumer.
- Charge payments use the same cutoff and entity/customer relationship in the LEFT JOIN, retaining unpaid charges. Custom entries and charge/invoice dates use the same date rule.
- Project summaries pass their requested project into the existing unpaid-invoice helper.
- Supplier privacy, managed-funds helpers, original currencies, current status/paye eligibility and gross/credit formulas are preserved. No payment or adjustment is created to alter a displayed balance.

## Verification and independent review

The new regression failed before implementation. Independent review then found a terminal-newline date that caused a parser failure instead of rejecting the input. LF/CRLF regressions reproduced that failure before absolute-end anchors and parser/roundtrip guards fixed it. Final independent review found no remaining blocking issue in the three-file scope.

| Check | Actual result |
| --- | --- |
| Changed PHP files, PHP8.3.21 and PHP8.2.0 syntax | PASS, all3 files. |
| Actual generated financial SQL, SQLite memory, PHP8.3.21 | PASS,110 checks. |
| Same suite, PHP8.2.0 | PASS,110 checks. |
| Actual generated SQL with native DATE/DATETIME, isolated local MySQL5.7.39 | PASS,110 checks;133 generated queries; strict SQL mode unchanged. |
| Mobile source test | PASS on PHP8.3.21 and8.2.0. |
| Mobile fixtures |3 valid English/bilingual/Unicode templates pass;12 negative controls fail as expected. |
| M029 / existing multicurrency tests | PASS on both PHP versions. |
| M017 security and data tests | PASS. |
| Scoped whitespace and reverse patch applicability | PASS. |

The native runner required explicit private parameters, verified the exact isolated instance and disabled networking, checked its database was empty before DDL and restricted later resets to fixed synthetic tables. It used no existing database/configuration. Initial sandbox socket access was blocked; formal approval for the empty local fixture then permitted the same guarded command. The instance was cleanly shut down and its socket/PID files disappeared. This did not retry or bypass the remote Docker permission denial.

[Native verification](../evidence/m121-cutoff-correction-2026-11-03/native-verification.md), [native generated queries](../evidence/m121-cutoff-correction-2026-11-03/native-generated-queries.json), [test output](../evidence/m121-cutoff-correction-2026-11-03/native-test-output.txt), [mobile mutation evidence](../evidence/m121-cutoff-correction-2026-11-03/mobile-mutations.json).

The mobile repair reflects current markup without restoring removed bilingual product strings. It verifies nonempty header/label parity, exact table roles with6/8 columns, value containers and empty-row spans. It checks active mobile CSS outside comments and rejects detached label selectors or removed funding columns. Its CSS parser is intentionally a static heuristic; it does not prove cascade specificity, nested media behavior, rendered geometry, Arabic localization or physical-phone acceptance.

## Existing failures retained

- M017 mobile contract: fails its frozen OpenAPI byte-hash assertion. The exact untouched test and OpenAPI bytes reproduce the failure in a HEAD-only selected fixture; no API route/specification was changed.
- M077 connected foundation: fails the missing Arabic draft-source phrase assertion. The test and Operations view match their pre-task hashes; the failure is outside this correction. No shipping behavior is declared correct or changed by that source-string check.

These failures were recorded rather than changing unrelated contracts or pre-existing work to make the suite green. No full-suite or authenticated workflow pass is claimed.

## Essential business decision and known limits

The patch fixes event inclusion, not historical state reconstruction. A credit created earlier but applied later still lacks an application timestamp. Current paid/closed/reopened/validated states and current invoice net totals cannot establish every earlier position. Excluding a future line credit does not restore an invoice net amount modified today. Current custom-entry statuses and native-source suppression also retain history limitations.

The owner/accountant must choose the intended report contract before full historical acceptance:

1. **Current open-invoice view with dated evidence:** retain current lifecycle eligibility and describe it explicitly as today's open invoices issued through the selected day. Payment/creation cutoffs are bounded as in this patch; it is not a reconstructed past balance.
2. **Historical position at the end of a business day:** define timezone/effective dates, closure/write-off/reopening, reversals, historical invoice net and actual credit-application events. Use verified existing audit/history or an explicit unresolved-history queue; do not invent timestamps, erase records or create a duplicate ledger.

Actual customer cash and unused invoice credit remain separate concepts. This correction does not silently turn the existing wallet into a cash book.

## Remaining operational gate

The staging metadata OS-permission denial remains. No alternate privileged route was used. A bounded, guarded operator read is prepared locally for an already authorized administrator; it is not executed by Codex and contains no new standing-access request. Target isolation, mounted source/schema/grants, backups/recovery, target MariaDB and authenticated role/privacy/persistence/UI acceptance remain pending. Local MySQL5.7.39 is not target MariaDB10.6.

All integrated M121 phase gates remain open. The corrected mobile source test supersedes its earlier failed source assertion only; the old failure and all earlier evidence remain in history. Sanitized evidence belongs in the existing tracker/manual. No production-resource access or backup/prod apply occurred.
