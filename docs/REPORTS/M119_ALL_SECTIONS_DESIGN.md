# M119 — Complete the original office workspace

Status: local implementation authorized by Ahmed's request to complete every remaining section. Financial activation decision requested separately; it is not inferred from showing a menu.

## Existing decisions and approach

Keep the original Finens visual language, native Dolibarr24.0.1/PHP8.2+, one company/entity, Egypt/EGP and China/CNY branch boundaries and the module-only sidebar. Native staff identity remains authoritative. Existing immutable operational drafts, private attachments and audit are preserved. No standalone SQLite application or independent cash ledger is introduced.

A second ledger would duplicate accounting; linking users to unrelated native pages would lose the original branch workspace. Extend the existing custom module with branch-scoped operational documents and reuse native accounting objects for actual cash/payment activity. This is the existing integration direction, not a new hosting/framework decision.

## Section outcomes

| Section | Working behavior |
|---|---|
| Dashboard | Real scoped counts/totals; separate drafts from recorded cash |
| Expenses / advances | Existing immutable records preserved; new workspace drafts support versioned edits and reversible archival |
| Categories | Create, rename, disable and restore branch categories; retain labels on prior documents |
| Reports | Date/kind/status filters, separate totals by original currency, pending versus native-recorded activity and escaped CSV export |
| Employees | Existing native users assigned to this branch, optional branch employment/payroll profile; no independent login/account creation |
| Payroll | Employee/month uniqueness; original currency; base + bonus = gross, separate ordinary deduction and loan repayment, net = gross - deductions; auditable drafts, no inferred cash payment |
| Treasury | Actual native mapped cash account and statement; separate deposit requests; an unmapped balance is unknown, never fabricated zero |
| Cash receipt vouchers | Persistent, scoped request/reference, payer/payment reason/method/date/currency and printable draft/issued state; one native movement per approved voucher |
| Settings | Branch identity/contact/default operational currency, normalized private letterhead logo and pending native account binding; currency/entity/type validation before binding |
| Users | Separate per-section CRUD grants and record scope; no global administrator promotion; archive/revoke distinct from deleting native users |

## Security and persistence

All actions derive actor/entity from the authenticated native context. Branch selection is checked against current memberships on every request. Workers keep own-record access; branch managers' grants are capped by their current delegation scope. Existing native draft/read/manage rights remain capability ceilings. Additional sections default denied for existing staff until assigned; the bootstrap administrator can configure them. Do not silently grant payroll visibility or financial approval to all workers.

New operational document and revision-audit tables are additive, separately manifested and explicitly installed. Branch categories and letterhead settings are shared metadata for staff explicitly granted read access; editing them requires branch visibility. Each record has entity, office, kind, owner, UUID, original request key/hash, status, revision, dates and validated JSON. Writes own their transaction, recheck authorization, require expected revision and insert audit atomically. Create retry keys never reuse changed intent. Archive/restore retains all records and audit; posted/approved financial documents cannot be edited or archived. Conflicting writes and uncertain commit outcomes fail without reporting success or erasing evidence.

Cash/payment posting is a separate native adapter boundary with request-id deduplication and native-ID readback. It must not activate without confirmed branch-account mappings, approval policy, currency handling and accountant treatment of payroll/employee loans. Only account ID/currency/type/entity are configured; never copy bank credentials or unrelated QA account data.

## Fresh observations and unresolved financial inputs

Authenticated backup-erp bank-list discovery found only native account1 labelled M088QA-EUR. It is unrelated QA data and will not be rebound or changed. No suitable Egypt/China cash account is yet confirmed. Ahmed has been asked whether to use separate branch cash accounts and branch-manager approval, administrator approval, or draft-only operations. Native salary creation stores a single native amount; original foreign salary/gross/net/loan treatment must be preserved explicitly and verified before payment integration. No live accounting changes are authorized by this document alone.

## Accounting and release

Operational drafts have no double-entry impact. Actual deposits, receipt payments, expense reimbursement and payroll must use native bank/payment/salary objects and preserve original currency, FX quote/date/source and proper gross versus loan reduction. Customer receipts are not automatically income. No public/customer/mobile app exposure is added. Test synthetic persistence, idempotency, stale writes, rollback, cross-branch/own-record denials, CSV/HTML injection and AR/EN desktop/phone forms. A backup-erp release requires the full dependency-closed code/schema package, backup, unchanged original records and authenticated workflows; production is excluded. Code rollback retains new schema/data/audit.

## Execution sequence

- [x] Add typed document validation and exact payroll arithmetic; test malformed and cross-currency input.
- [x] Add manifested operational persistence, optimistic revisions, idempotency and revision audit; test native transactions and actual MariaDB.
- [x] Extend section permission handling without granting existing memberships new rights; test delegation and revocation.
- [x] Wire native employee reads, settings/logo, category forms and scoped reports/CSV. Account binding is held for the financial decision.
- [x] Wire deposit/voucher/payroll draft creation, updates, reversible archival and print routes with the original styling.
- [ ] Implement native financial transition only after the financial inputs above are resolved and reviewed.
- [ ] Validate all routes and desktop/phone AR/EN; prepare the complete staging release and runtime acceptance.
