# M119-C01 — Native branch cash and general-ledger activation

Status: IN_PROGRESS — approved objective and branch authority. Financial code is deployed/verified on backup-ERP; accounting mappings and live activation acceptance remain gated.
Owner: Agent007, one writer. Requester: Ahmed Hamed,2026-11-02.
Environment: local development and established backup-ERP trial only. Production not authorized.

## Approved user decisions

- One native Dolibarr company/entity; Egypt and China are module branches.
- Moaz approves Egypt; Rina approves China. Ahmed and the global administrator cover both. Identify Ahmed's exact native account before granting anything; current directory has no separate ahmed identity.
- A completed operation must produce the real native branch cash movement and native double-entry bookkeeping together. Cash-only bookkeeping is not sufficient.
- Existing original-currency branch accounts remain authoritative. No new cash accounts, duplicate opening balances, inferred revenue or implicit FX.

## Pre-implementation accounting review

The current module is draft-only; native double-entry accounting is disabled. Company settings show HK/USD. The proposed debit/credit classifications and unresolved mappings are in `docs/M119_NATIVE_PAYMENT_ACTIVATION.md`; owner/accountant review is required before enabling accounting or writing GL entries. No guessed account numbers, journals, historical exchange rates, employee loan openings or tax treatment.

## Required behavior

Draft → submitted → approved → paid/received, with rejected and linked reversal outcomes. Separate create, submit, approve, post and reverse rights; CRUD grants do not silently become approval rights. Recheck native identity, branch/own scope, source revision, account currency/open state and accounting-period validity on every transition. Branch managers must not gain global cross-branch accounting access merely to use the scoped module.

Use standard ExpenseReport/PaymentExpenseReport, native bank Account, customer/invoice payments or explicitly unallocated funds, native Salary/PaymentSalary and BookKeeping objects as applicable. Keep entity/branch/original currency/source evidence and native IDs linked. Store exact base valuation/provenance when required by USD books. Preserve gross salary expense, net payment and employee-loan reduction separately.

One atomic native transaction binds source claim, native payment/bank line, balanced journal group and append-only audit. Unique operation keys, locks and fresh readback prevent duplicate or uncertain payment replays. Posted data is immutable; correcting it creates an authorized linked reversal. No direct native-table writes as a substitute for object services.

## Related readiness gaps

Connect private evidence uploads to new workspace expense records, retain native ownership checks on download, and scope navigation to permitted sections. Keep existing records untouched and require explicit counterparty/account mapping before converting a historical draft into a financial operation.

## Acceptance and rollback

Matching PHP8.2.32/Dolibarr24.0.1/MariaDB10.6.27 tests must cover branch/own/role/CSRF/revision denials, concurrency/idempotency, lost commit response, currencies/valuation, balanced native GL, one linked bank movement, loan/gross-net allocation, reversal, closed periods/accounts and attachment isolation. Require complete target DB/source backup and explicit bounded synthetic posting details before native write acceptance. Rollback disables new actions and preserves records/audit/native money; never restore an old DB over subsequent business transactions.

Admin ERP only; Customer Center and native Mobile App excluded. Completion requires actual native finance reconciliation and staff login/workflow acceptance, not HTTP200 or a rendered button.

Implementation and R11 deployment evidence: [native finance report](../REPORTS/M119_NATIVE_FINANCE_IMPLEMENTATION.md). Actual business activation, native acceptance posting and staff credential-login remain pending; this Mission is not completed.
