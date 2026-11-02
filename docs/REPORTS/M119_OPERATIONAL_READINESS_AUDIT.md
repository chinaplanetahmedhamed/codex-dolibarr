# M119 operational readiness audit — 2026-11-02

## Verdict

The backup-ERP module is usable for branch-scoped operational draft capture, editing, archival, printing and request reporting. It is **not yet a complete cash-payment / double-entry accounting workflow**. Opening a page or having a working Save button does not establish that money has been received/paid or posted to the general ledger. No production acceptance is claimed.

Ahmed confirmed: Moaz approves Egypt, Rina approves China, with Ahmed and the global administrator covering both; real native cash movements **and** general-ledger entries are required. The exact Ahmed identity and approved chart/account/journal mappings remain unresolved at this checkpoint.

## Current authenticated checks

All ten workspace views plus Company users returned200 with the module shell for both Egypt and China:22 authenticated loads, no Pending/aria-disabled menu placeholders for the administrator. This turn additionally inspected form contracts, actual native module administration, staff directory and company settings. Existing financial records and grants were not altered by these reads.

| Section | Operational today | Gap before full financial use |
| --- | --- | --- |
| Dashboard | Branch-separated cash readout, request summaries and navigation | Draft activity is not paid activity. |
| Treasury | Reads actual native cash accounts by original currency; period filter and statements | New funding requests save as drafts; no receive/pay posting action. |
| Receipts | Draft voucher CRUD/archive/restore, search, three-copy A4 and authenticated QR | Payer is free text; no native customer/invoice allocation or receipt-to-bank/GL posting. |
| Expenses | Draft CRUD, category selection and legacy draft access | No submit/approve/pay states; new workspace form has a document-reference text field but no receipt-file upload. |
| Advances / returns | Separate linked draft requests | No actual staff advance asset balance, cash settlement or cumulative posted-return control. |
| Categories | Create/edit/archive/restore, branch scope | Account-number mapping for each expense category is not implemented. |
| Reports | Request charts, six-month table, currency separation, CSV/print and separate actual-cash table | Request graphs are not a statutory general ledger or paid-expense report. |
| Employees | Native branch staff directory and salary-profile drafts | Native identity/password management remains in Dolibarr; profiles do not themselves create payroll payments. |
| Payroll | Gross, deductions, loan repayment and net draft calculation; month filtering and print | No native salary/payment/GL posting or real loan-balance reduction. |
| Settings | Office identity/logo and safe fixed native cash mappings | No approved chart, journal, tax/deduction/FX or opening-GL mapping setup. |
| Company users | Native staff assignment/revocation and per-branch CRUD/own-vs-branch grants | Approval/payment roles do not exist yet. Staff password login remains unverified. Directory also includes existing QA/service identities; do not assign them accidentally. |

The shared menu renderer currently receives workspace readiness, not per-section read scopes. Restricted staff can therefore see some links that correctly fail closed with403. This is a navigation usability gap; it does not prove a cross-branch data leak. Direct-route scope checks must remain authoritative.

## Confirmed blockers

1. `OfficeExpenseWorkspaceController` accepts only save/archive/restore; no submit/approve/pay/receive/reverse adapter exists. `OfficeExpenseDraftService::postFinancially()` deliberately throws. This cannot be fixed by enabling a cosmetic button.
2. Native accounting setup routes returned an Access denied body even with HTTP200. Native module administration exposes `modAccounting` with the enable (`action=set`) action: double-entry accounting is currently disabled. No enable action was followed.
3. Native company settings show Hong Kong (HK), base currency USD. These are observed values, not an accounting-policy recommendation. Original branch currencies remain Egypt EGP/USD and China CNY/USD. Approved base-currency valuation/rate provenance and account/journal IDs are needed before GL posting.
4. Native users currently include admin, Moaz, Rina, Omar, Shan Shan and existing QA/service users; no separate ahmed account was found. A question is pending whether admin is Ahmed or a separate identity is required. No account/password was created or changed.
5. File attachments exist on the legacy draft route, not the new workspace expense records. Receipt-voucher printing and attached purchase-receipt evidence are different functions.
6. The localhost18846 page is a static synthetic design preview. Its PHP links can return404; it is not the authenticated operational runtime. Use backup-ERP for the current trial.

## Required sequence

Finish the bounded dropdown correction; confirm Ahmed identity; approve native accounting setup and the concrete account/journal/FX mapping in the companion activation contract; implement guarded state transitions and native cash+GL adapters with idempotency/reversal; connect private workspace evidence uploads; hide unauthorized navigation; then perform bounded matching-engine and native user workflow acceptance. Never replay cash opening balances when adding their GL representation, and never turn stored draft vouchers into actual receipts automatically.

Safe actions today: capture and review clearly marked unposted requests, configure categories/office identity and staff assignments, print drafts, inspect native cash and export request reports. Do not use the module as proof of actual collection, reimbursement, advance settlement, salary payment or completed accounting posting yet.

## Evidence limits

Fresh local suites: workspace33/0, employee UI18/0 and staff UI regression pass. Earlier R04 authenticated persistence/CSRF/revision/archive/restore acceptance and R08 native responsive/PDF checks remain dated prior evidence; they are not a new real payment. No statutory transaction, new financial balance, module activation, staff password login or production action was performed by this audit.

Fresh authenticated persistence check during this audit: created one clearly named `M119 SYNTHETIC READINESS AUDIT` expense category in Egypt, verified a fresh readback, updated it with expected revision1→2, then archived it and verified the Restore action. Retained archived category reference `6697354861c2ce8f4d74c8d16cefd960`; audit history preserved. No payment, bank movement, GL entry, salary, account or grant was changed. This is a real shared document-store CRUD check, not proof of financial posting.
