# M121 financial semantics and mobile-test investigation — 2026-11-03

**Outcome: source findings reproduced, earlier misleading fixture corrected, and bounded correction proposals prepared. No application changes applied.** Connected Codex engineering coordinated independent historical and project-funding reviews. Existing local work and Git history are preserved. Staging isolation/runtime acceptance remains blocked by the previously documented restricted metadata permission.

## Source and method

Canonical branch `codex/m084-dashboard-repair`, HEAD `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`; financial library SHA256 `8b9eba67974982a04f636c05e964e9aab91be01d0a60ccbda0e1c946558b44f1`. Relevant owners remain Customer Center finance/funds and Operations supplier orchestration. No new ledger, posting or source identity was introduced.

PHP8.3.21 executed actual generated SELECT SQL against synthetic in-memory SQLite3.54.1 tables:24 historical cases and36 project/funds assertions. Historical cases were also executed with native DATE/DATETIME columns on an empty isolated local MySQL5.7.39 fixture under its existing strict SQL mode, including ONLY_FULL_GROUP_BY:24 cases characterized. No SELECT rewriting or fabricated result rows were used in the historical tests. The project adapter maps only SHOW TABLES to the SQLite catalog.

The native fixture used a fresh private data directory, no default configuration or existing database, and TCP disabled. Sandbox restrictions initially blocked local socket binding/connection; formal platform escalation approved the exact disposable fixture. It was shut down cleanly after tests; socket/PID files are absent and shutdown completion is recorded locally. No VPS route or denied Docker operation was retried during this source investigation. MySQL5.7 is not target MariaDB10.6; target native rights, decimal/locking/trigger behavior and authenticated acceptance remain pending.

Exact queries, synthetic rows, outputs, artifact hashes and proposed mobile test are in [source evidence](../evidence/m121-semantics-2026-11-03/manifest.json). Connection metadata is omitted from exported results; original local artifacts are retained.

## Corrected earlier inference

The earlier paid100 query double returned an invoice despite the generated `fk_statut=1 AND paye=0` condition. That was not a faithful native paid-invoice fixture. Actual SQL excludes native status2/paye1 invoices. The prior required-funding100 output must not be used as proof of a live paid-invoice debt. This report and its actual-query evidence supersede that particular canned-row observation; earlier records remain historical.

## Current project funding contract

The legacy financial library derives a customer-credit/wallet projection from native customer receipts minus invoice-credit consumption, plus classified visible custom entries. M029 explicitly documents that model. Invoice allocation is not an actual supplier cash payout; reservations also do not move cash.

| Synthetic scenario | Observed source result |
| --- | --- |
| Deposit50,000; invoice credit20,000 to project11 and10,000 to project22 | Global available20,000; project-only allocation activity −20,000/−10,000; no unpaid invoice rows when paid. |
| Add native supplier payout5,000 | Legacy projection unchanged; it does not read native supplier payments. |
| Reserve10,000+5,000 from remaining deposit coverage | Managed availability5,000 while legacy credit availability20,000. |
| Settle an attribution against an existing supplier payout | Managed coverage remains reserved/settled; the legacy statement does not gain a cash debit. |
| Reverse the10,000 reservation | Managed availability15,000; legacy credit20,000 unchanged. |
| Explicit complete custom receipt split30,000/20,000 | The source receipt counts once and projects receive those explicit credits. |
| Incomplete source-linked custom split30,000 against native receipt50,000 | Whole native receipt is suppressed, leaving30,000. This is a historical reconciliation invariant to check, not evidence such real records exist. |

Thus negative project allocation totals are not proof of negative actual cash or corrupted live data. Current inspected UI summary callers use all-customer scope; project.php shows transaction activity. Do not automatically add a global deposit to every project.

A separate function-contract inconsistency is reproduced with actual SQL: `cpcc_financial_summary(project11)` passes project0 into its unpaid-invoice helper and includes an unpaid8,000 invoice in project22; direct unpaid(project11) excludes it. The bounded project-argument correction already prepared with PR59 addresses this helper scope, preserves all-customer behavior and does not implement actual cash.

Native payment/source IDs, explicit funding attribution and split conservation must be reconciled before projecting M121 actual customer cash. Company-funded or unattributed supplier payments must not be assumed customer-funded. Do not sum native, CPCC and M116 representations or create adjustment postings just to align displays. [Detailed project review](../evidence/m121-semantics-2026-11-03/project-funding-review.md).

## Historical As-of findings

All amounts below are synthetic USD; invoice100 issued before the selected cutoff, with no unrelated commitments.

| Case | Unpaid remaining | Allocation debit | Finding |
| --- | ---: | ---: | --- |
| No payment |100|0|Baseline. |
| Payment25 after cutoff |75|0|Unpaid aggregate includes future payment; dated allocation excludes it. |
| Fully paid/closed after cutoff, current status2/paye1 |0|0|Current flags erase the earlier open obligation. |
| Payment25 at cutoff-day midnight, native DATETIME |75|25|Date-only upper bound includes midnight. |
| Payment25 at cutoff-day noon, native DATETIME |75|0|Same upper bound excludes later events that day. |
| Direct credit25 created after cutoff |100|25|Future credit allocation is included from current target links. |

Further actual-query controls cover later validation/cancellation, invoice date, other project/customer/entity and payment-validation settings. An intentionally inconsistent cross-entity payment link changes the unpaid aggregate while the allocation query excludes it; this is an integrity-hardening gap, not a demonstrated native-workflow exploit or live disclosure.

Credit creation date is not application date. Native discount links lack an application timestamp; a credit created earlier but applied later cannot be reconstructed from datec alone. Direct payment credits and invoice-line discounts also have different net/gross treatment, so a single arithmetic rewrite would be unsafe. Current lifecycle fields do not establish arbitrary historical state across reopening, edits or revalidation.

The exact-midnight result previously obtained from SQLite TEXT comparison was a dialect limitation; native MySQL includes the midnight allocation. Both engines confirm the noon and later-payment inconsistencies. [Historical review](../evidence/m121-semantics-2026-11-03/historical-review.md), [bounded proposal](../evidence/m121-semantics-2026-11-03/historical-proposal.md).

Before implementing historical behavior, decide whether As of means a historical position at the end of a business day or today's open invoices issued before that date. If historical position is intended, define timezone/effective date, closure/write-off/cancellation, reversal and credit-application evidence. A narrow payment slice can use one exclusive next-day event bound plus entity scope consistently; it cannot claim complete reconstruction without lifecycle/application history. No timestamps are invented and no arithmetic or historical records were changed.

## Older mobile-label test

Git commit `b442f0f85be47a755edb538e15339e0e517cd2bf` changed portfolio headers and matching data-label text from bilingual strings to English. The older M028-C04 test still asserts exact bilingual literals. The unchanged canonical test reproduces `M028-C04 FAIL: totals mobile labels`; no mobile-layout regression is established by that string failure.

A **test-only proposal**, unapplied, checks that both responsive tables have a nonempty label matching each column header, one value container per data cell, correct empty-row colspan and the existing mobile CSS/card/breakpoint/wrapping contract. It retains detection of missing labels instead of accepting any arbitrary English substring.

Executed validation: PHP lint PASS; current English template, actual historical bilingual template and a Unicode parity fixture PASS. Eight negative controls fail as expected: missing/empty/wrong label, empty header, missing value container, wrong empty-row colspan, missing responsive class and disabled CSS label content. Patch applicability check passes against unchanged canonical test.

This is static template/DOM validation, not rendered CSS geometry or an Arabic translation acceptance. No product labels, language policy or application file changed. [Proposed test patch](../evidence/m121-semantics-2026-11-03/mobile-test-proposal.patch), [results](../evidence/m121-semantics-2026-11-03/mobile-test-results.json).

## Proposed next changes and remaining gate

1. Review/apply the test-only mobile-contract repair in the owning source scope. Keep AR/EN rendered/localization acceptance separate.
2. Retain PR59's bounded project-argument correction and currency display changes, preserving newer privacy/helpers. Add the actual-query two-project coverage before integration.
3. Agree cash versus invoice-credit/funding and historical semantics; implement a narrowly tested payment-boundary/entity slice only after that decision. Credit/lifecycle reconstruction remains separate work, with unresolved history explicit.
4. Obtain staging-only container/project/custom-volume ownership metadata through an existing authorized administrative read path. A human operator action is prepared locally; Codex did not use another route to bypass the Docker denial. It reads only fixed staging metadata, stops on a mismatch and requires no new standing permissions.
5. Verify target isolation, mounted dependencies/schema/grants and backup/recovery before application integration or authenticated synthetic acceptance.

The operator document is deliberately excluded from Git because it contains infrastructure identifiers. Its syntax and fixed metadata-only shape were checked; the command was **not executed**. The supported user action is to verify the existing authorized administrative session, run the prepared bounded read, keep details local and return only PASS/STOP. If that session lacks permission too, stop and report; do not alter socket, groups or sudoers.

No canonical application/test changes, financial postings, migrations, access expansion, staging deployment or production access occurred. All application phase gates remain open.
