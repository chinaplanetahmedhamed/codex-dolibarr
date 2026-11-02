# Native payment and financial posting activation

Status: CODE DEPLOYED AND VERIFIED ON BACKUP-ERP; reviewed business accounting configuration and live posting acceptance remain pending. Ahmed confirmed manager approval and native cash plus GL. Historical proposal wording below is retained as context; the confirmed decisions and current checkpoint take precedence.

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

## Decisions confirmed — 2026-11-02

Ahmed selected branch-manager approval (Moaz/Egypt and Rina/China), plus Ahmed and the global administrator, and requires **native cash movements and general-ledger posting together**. These two choices supersede the unresolved-choice wording at the top of this historical proposal. Implementation/activation is not complete. See Mission `M119-C01_NATIVE_CASH_AND_GL_ACTIVATION.md`.

The authenticated audit found double-entry accounting disabled (`modAccounting` enable action present; accounting setup routes deny access), company country Hong Kong, base currency USD. No separate ahmed identity was found in the native user list. Two precise questions remain pending: whether admin is Ahmed, and whether an approved chart exists or a proposed mapping should be reviewed first.

### Proposed mapping for owner/accountant review

No account numbers or journals below have been selected or activated.

| Operation | Debit classification | Credit classification | Required mapping |
| --- | --- | --- | --- |
| Approve staff expense | Approved expense category | Staff expense payable | Category GL account, staff subledger and recognition journal |
| Pay approved expense | Staff expense payable | Exact branch cash account | Cash GL account/journal and native expense-payment link; do not recognize expense twice |
| Receive invoiced customer funds | Exact branch cash account | Customer receivable | Native customer/invoice/payment and allocation |
| Receive unallocated customer funds | Exact branch cash account | Unallocated customer funds liability | Native customer plus liability/subledger; payer text alone is insufficient |
| Owner funding | Exact branch cash account | Owner/shareholder current account | Identified owner/source; never sales revenue |
| Staff advance | Staff advance/loan asset | Exact branch cash account | Native staff and original-currency advance allocation |
| Cash advance return | Exact branch cash account | Staff advance/loan asset | Linked outstanding posted advance; not a second expense |
| Expense settlement against advance | Approved expense category | Staff advance/loan asset | Evidence, expense classification and cumulative allocation; no additional cash movement |
| Salary recognition | Gross salary expense | Net salary payable plus classified deductions/loan offsets | Gross/base/bonus, actual loan receivable and separately classified deductions |
| Salary payment | Net salary payable | Exact branch cash account | Native Salary/PaymentSalary and payroll journal |
| GL opening representation | Mapped cash GL accounts | Approved opening-equity/control account | Reviewed original opening amounts/date and historical USD valuations; never add another native bank opening |

For non-USD money, retain the original amount and require an explicit approved USD valuation, rate date and source. Do not silently change the company base currency or infer realized/unrealized FX policy. Native closed periods, tax/deduction classification and starting staff-loan balances must be resolved where applicable. No real or synthetic money was posted by preparing this proposal.

## M119-C01 implementation checkpoint — 2026-11-02

Local implementation is in progress; backup-ERP remains on R10. New default-disabled financial configuration, explicit submission/approval/post/reverse authority, exact original/base posting plans, native money/GL bridge, private evidence, and compatible immutable source/audit handling are implemented for review. The source does not enable accounting or guess native mappings. Existing openings and older drafts are preserved.

Application a6d1893b46db0cd9349533fdd15461d93ad32f9a passed the full matching-engine check in Actions 41468853234 (FAILED_SUITES=0): 41 workspace tests, 10 financial-plan tests, and 20 actual native finance cases on PHP8.2.32/Dolibarr24.0.1/MariaDB10.6.27, in addition to the existing full module suites. The native suite covers actual ExpenseReport/Salary/customer payments, bank linkage, balanced BookKeeping, opening GL without duplicate cash, historical loan FX, reversal, closed periods, audit rollback, lost-commit recovery, manager isolation and worker denials. Test fixtures now use genuine Conf/Translate/HookManager objects; invoice PDF auto-update is explicitly disabled only in the disposable CI configuration. These are isolated synthetic tests and do not establish live business acceptance. Native banking/GL activation, opening-GL reconciliation, approved accounting mappings, Ahmed's exact identity and authenticated branch staff acceptance remain open. Do not mark this Mission completed or the module ready for real financial use from this checkpoint.

Runtime rollback for the financial release must disable actions while keeping the compatible parser, immutable financial states, evidence and native records. R10's older source-store/parser cannot be restored over financial state. Customer Center/Mobile App parity: not applicable, finance remains internal native ERP only.


### Release preparation checkpoint

R11 prepares a complete 69-file runtime plus release declaration. The business configuration remains disabled; deploying code does not enable native accounting, choose a chart, assign financial roles, or post cash/GL. The pinned application package is d648078b62e491082bdf7869f1a7a0a283306e44ac29d38fe27be5587983b1d9; manifest c5b2f2b27ab6235febdb0b8d294f7b3b546e24e20212864865d0b02ed660d369. The five-action finance updater verifies the existing R10 closure, backs up the full database and exact source, installs a paused compatible closure, verifies unchanged native records, and only then permits reviewed business setup. Rollback pauses the release, drains financial work and retains all compatible files and financial history. It never restores the older R10 parser or an old database.

The finance report CSV contains original and base amounts, posted cash, historical FX provenance, native bank reference and detailed balanced journal pieces. It follows the existing branch/own-report permissions and protects exported text from spreadsheet formula interpretation.

Expanded reversal and release verification: Actions 41472562164 succeeded with 22 native finance cases and 11 release-helper cases, including native payroll and customer-invoice payment reversals. Corrected helper installation passed actual backup-ERP preflight; application apply remains separately pending at this checkpoint.

Final R11 checkpoint: complete69-file application a6d1893 was deployed after full database/source backup and passed separate restricted verification. Both branches retain disabled financial business settings; native records were unchanged. The complete deployment/evidence/rollback boundary is recorded in [M119 native finance report](REPORTS/M119_NATIVE_FINANCE_IMPLEMENTATION.md). Authenticated UI verification awaits native sign-in.
