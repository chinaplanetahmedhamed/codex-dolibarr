# M121 project funding semantic review

Decision: do not replace the ledger or automatically credit every project with global receipts. The tested negative project result follows the current invoice-credit allocation model. That model differs from the requested actual-cash view. No live bug, corrupted balance, deployed parity or authenticated acceptance is established.

## Evidence and corrected probe assumption

Canonical HEAD310deeaa70ced8d1a74aa84c802e2c705e54b2a1; the inspected financial.lib.php and CustomerFundsService files have no local tracked modifications. Exact working-source hashes are in manifest.json. No server/network action occurred.

The new probe loads the actual canonical PHP functions and executes their generated SELECT statements against synthetic SQLite in memory. Only SHOW TABLES is translated to sqlite_master. It does not hand functions canned rows. This respects f.paye=0, invoice type/status, joins, project/entity/customer filters and actual aggregate expressions. PDO SQLite is not MariaDB precision/locking/trigger or native authentication acceptance.

Passed36 assertions, plus existing M029 core-customer-funds and customer-finance multicurrency tests. Full SQL/result evidence is results.json. Probe fixture writes occur only in memory. No native mutation or managed reserve/settle/reverse command was executed; settled/reversed allocation states are explicitly seeded projection fixtures.

A fully paid invoice with paye=1 is excluded by the actual unpaid query. The former stub that returned such an invoice despite f.paye=0 was invalid evidence of unpaid debt. The new fully-paid fixture returns no unpaid rows and zero unpaid-invoice total.

## Current meanings and source identities

- financial.lib.php:319 native receipts are credits reconstructed from paiement + paiement_facture + facture, grouped by payment/invoice/currency. Their presentation identity is payment-ID/invoice-ID. Deposit type3 and direct standard-invoice payments both enter global receipts. Project statements intentionally omit these global receipts at line515.
- Lines368–447 debit societe_remise_except credit consumption into target invoices, keyed by remise row ID, and immediate direct invoice-payment allocations, keyed by payment/invoice. Project is the target customer invoice's fk_projet. These represent invoice funding consumption, not supplier cash payout.
- Lines476–509 add posted, customer-visible cpcc_financial_entry credits/debits by project/original currency. Native receipts are suppressed globally when ANY posted custom entry has matching entity/customer/source_type='payment'/source_id=payment-ID. This is whole-source suppression, not amount-based residual calculation. It does not itself verify the split's conserved total. A synthetic partial30k custom split suppresses the whole50k native receipt and projects30k; this demonstrates a reconciliation invariant to check, not evidence that such historical data exists. The current admin addentry flow explicitly blocks manual customer_receipt/invoice_allocation; the split case models possible legacy/source-linked rows, not a supported new split workflow.
- Lines512–577 sort these rows and derive per-currency available=credits−debits. received/spent omit FX legs; balances include them. Existing test_m029_core_customer_funds.php explicitly documents wallet=“native receipts less native allocations.” This is strong evidence of deliberate legacy semantics. It can also contain explicit operational debits/openings/adjustments, so it is not purely a native bank balance.
- CustomerFundsService: confirmed cpcc_customer_receipt references one native customer-payment identity, unique by entity/type/ID. Confirmation validates type3 deposit invoice-payment amounts/customer/currency. It creates no cash. Reconciliation computes native_consumed from credit rows linked to source deposit invoices, allocatable=confirmed−native_consumed, then available=allocatable−active reserved/settled managed allocations (floored at zero), with blocked coverage reported separately.
- cpcc_fund_allocation identifies receipt, customer, explicit target/project/operation/shipment, currency, amount, status, request identity and optional disbursement evidence. Reservations/settlements attribute existing cash; they do not become extra receipt/debit rows in financial.lib.php. settle(financial_entry) references an existing posted debit. settleWithSupplierPayment references existing native supplier payment/invoice/bank evidence, currently one invoice, matching operation/project/currency and bounded attribution total. It neither creates another payment nor inserts a legacy customer debit.
- OperationSupplierWorkflowService retains native supplier payment/bank IDs and an optional managed allocation ID. Without an explicit allocation, native company-funded/unattributed supplier spending must not be assumed customer-funded merely because project/customer context exists. This service can mark failed attribution for correction without recreating native cash.

## Observed synthetic projections

| Scenario | Actual source result |
|---|---|
| Deposit50k; invoice credit consumption20k project11,10k project22; both target invoices paid | Global available20k; project11 −20k, project22 −10k; unpaid invoices0 |
| Fully paid direct customer invoice10k | Global receipt10k−allocation10k=0; project-only result−10k; unpaid0 |
| Native supplier payout5k added | Legacy statement unchanged; no native supplier-payment table is queried |
| After deposit consumption30k, managed reservations10k+5k | Managed available5k; legacy available20k |
| Reservation5k becomes settled against native supplier payout | Both projections stay as above; actual payout attribution is absent from legacy statement |
| Reverse unsettled reservation10k | Managed available15k; legacy20k unchanged |
| Invalidate native receipt status | Managed evidence invalid/available0; remaining native credit rows still influence legacy statement; correction/reversal needs native/source reconciliation |
| Explicit complete legacy split30k/20k source-linked to same50k receipt | Global50k once, project30k/20k; not proof a supported project-split command exists |
| Explicit customer debit5k; paired USD100 out/CNY700 in | USD balance decreases5100; RMB balance700; FX excluded from received/spent; no currency netting |
| Native CNY700 receipt, base100 | RMB700, no USD credit; foreign customer/entity receipts excluded |

The current project.php displays project transactions as debit/credit rows, not this synthetic project “available” summary. Canonical UI calls to cpcc_financial_summary currently use all-customer scope. Thus the negative helper output does not establish an observed negative live project cash widget.

## Separately reproduced project-scope inconsistency

financial.lib.php:546 passes project0 to cpcc_financial_unpaid_invoices even when summary(project11) was requested. An actual unpaid8k invoice in project22 appears in summary(project11); direct unpaid(project11) correctly excludes it. This is a bounded function-contract inconsistency. Current inspected canonical UI summary callers do not pass a project ID, so no current UI impact is claimed.

Bounded proposed repair, if approved in an implementation task: pass (int)$projectid instead of0 at that call; retain project0 behavior and all existing ledger semantics. Add actual-query tests for two projects, paid/unpaid/type/customer/entity/currency, comparing all-customer unchanged and project-only unpaid isolation. This does not repair negative project allocation balances or implement actual cash. No code patch was applied here.

## Concrete alternatives for owner decision

1. Preserve the existing invoice-credit view, name its global balance as unused customer credit and show project allocations as consumption/activity. Keep cash, reservation and obligation fields separate. Avoid presenting allocation-only project totals as standalone cash balances.
2. For M121 actual cash, extend an existing service with a separate read projection over agreed native source identities plus classified historical custom exceptions. Count each actual receipt once and each explicitly customer-funded actual payout once. Invoice credit consumption changes invoice funding; reservations change earmarked/unallocated amounts; neither is an actual cash payout. Company/unattributed spending remains visible to supplier/company views and does not automatically reduce customer cash. Project displays need explicit receipt/project funding attribution and split conservation; merely filtering native receipt invoice.fk_projet cannot represent one deposit split across projects.
3. Keep uncertain historic sources in a reconciliation exception queue. Validate whole-payment custom replacement coverage, duplicate candidates, explicit funder/payee/source relations and supplier-attribution evidence before deciding migration. Do not sum native/CPCC/M116 independent representations or synthesize posting entries to make projections match.

ACCOUNTING_RULES requires native accounting/bank authority, original-currency/source preservation, unallocated vs allocated distinctions, immutable paired FX evidence and business-owner/accountant review before changing accounting behavior. The source uses floating-point projection and default0.005 thresholds; the tests establish present semantics, not a newly approved precision or FX/reversal policy. Historical as-of views also use current payment/paid status and source aggregates; historical reconstruction needs separate acceptance.

Remaining gates: agreed cash vs invoice-credit labels/contracts, approved project funding semantics, classified source links and reversals, verified isolated schema/native rights, authenticated synthetic persistence/failure/concurrency tests and current mounted-code parity. No canonical source, refs, worktrees, ledger, database, flag, grant, server or job changed.
