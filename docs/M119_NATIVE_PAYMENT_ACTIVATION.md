# Native payment and financial posting activation

Status: PROPOSED; required approval-role and accounting-level decisions are pending. Ahmed requested activation on2026-11-02. No financial adapter is enabled by this document or the receipt-design release.

## Workflow to implement after the decisions

Draft → submitted → approved → paid/received, with rejection and auditable reversal. Approval and payment are separate actions; CRUD permission alone does not grant either. The approver is either the assigned branch manager within that branch or the native administrator, according to Ahmed's pending choice. Recheck current native identity/entity, branch membership, capability and record revision at each transition. Workers may submit and view their own permitted requests. Posted documents become immutable; corrections use linked reversals rather than edit/delete/archive.

| Operation | Native source and proposed accounting effect |
| --- | --- |
| Staff expense reimbursement | Native validated ExpenseReport and PaymentExpenseReport; bank line linked through native addPaymentToBank. Debit approved expense/payable, credit the exact branch cash account. Prevent double-expensing on payment. |
| Customer receipt | Map a native customer and invoice/payment where applicable; otherwise explicitly unallocated customer funds. Debit branch cash, credit customer liability/receivable as appropriate. Never infer revenue from payer text. |
| Branch funding | Native bank movement with identified source: owner funding, reimbursement or transfer. Debit cash and credit the approved source account. Opening balances are not reposted. |
| Staff advance and return | Native bank movement plus immutable allocation to the staff advance; advance is an asset, return reduces that asset. Expense settlement and cash return are distinct events. |
| Salary | Native Salary/PaymentSalary integration after employee-loan mapping. Preserve gross expense, deduction/loan allocation and net cash separately. |

Native Dolibarr24.0.1 source was inspected: Account::addline, PaymentExpenseReport::create/addPaymentToBank and PaymentSalary::create/addPaymentToBank. This is source evidence only. It does not establish current module enablement, a reviewed chart or a verified live posting transaction. Native amount conversions and multicurrency support must be tested against the installed engine, rather than assuming a foreign-currency bank account supplies base-currency valuation.

## Invariants and acceptance

Use the existing exact Egypt EGP/USD and China CNY/USD cash mappings, validated by entity/reference/type/currency/open state. Store original amount/currency/date, source record and revision, counterparty, approver/payer, native document/payment/bank IDs and allocation. No implicit FX. If statutory posting is requested, require approved account/journal/valuation mappings first; a native bank line alone is not a statutory journal.

One native transaction must bind the posting claim, source status, native payment/bank movement and append-only audit. A unique source/revision/operation key prevents replay; lock and re-read the source and selected account before mutation. A lost commit response is an uncertain result: quarantine the request and reconcile exact native references before retry. Do not add a parallel cash balance or write native accounting tables directly.

Migration is additive and separately pinned. Start in isolated MariaDB/native objects with synthetic funds: authorized payment; cross-branch/own-scope/capability/CSRF/revision denials; retry/concurrent submission; partial failure and lost commit response; currency and closed-account mismatch; actual native payment-bank linkage; statement/reconciliation totals; reversal; no duplicate openings. Back up the complete target database and source before an expressly bounded backup-ERP acceptance transaction. Any actual acceptance amount/source/account must be specified before posting; no arbitrary business payment is implied.

Admin ERP is the affected surface. Customer Center and native Mobile App remain excluded; receipt QR continues requiring authenticated branch access. Rollback disables new posting actions while retaining posted native entries and audit; it must not restore an old database over real money movements or silently delete/reverse transactions.
