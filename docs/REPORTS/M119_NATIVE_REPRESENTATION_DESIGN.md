# M119 — Native representation and reconciliation design

Status: proposed, non-posting design. Native storage probes are verified locally; accounting policy and complete runtime acceptance are pending. This document grants no activation or posting permission.

## Runtime readiness and exact blocker

The examined source's `version.inc.php` declares **24.0.1**. File hashes for native auth/expense source are recorded in `M119_NATIVE_IDENTITY_CAPABILITIES.md`. The intended ChinaPlanetOS release and database engine have not been established from production configuration, which was not read.

Local checks found PHP with BCMath, mysqli/PDO MySQL and SQLite extensions, but no `mysql`, `mariadb`, `mysqld` or `mariadbd` command on PATH. Docker's selected `default` context points to the local Unix socket. Explicit read-only `docker --host unix:///var/run/docker.sock version --format '{{.Server.Version}}'` failed because `/var/run/docker.sock` does not exist. `colima status` reports stopped. No daemon, VM, host service, existing container or network setting was started or modified.

The only other listed Docker context is `colima-m073`; its `/Users/mac/.colima/m073/docker.sock` is also absent and `colima --profile m073 status` reports stopped. No listener was found on local TCP3306. These checks establish that no ready runtime was found through the available local routes; they do not identify the intended deployment engine or claim to inventory every host installation.

Therefore complete native `main.inc.php` HTTP acceptance is **pending**, not denied and not attempted against a real installation. The SQLite library fixture is not a substitute: its documented driver workarounds and partial schema cannot establish matching-engine/runtime acceptance. A disposable runtime with its own empty database, synthetic configuration/users, loopback HTTP, isolated session directory and blocked outbound mail/hooks is required. The acceptance suite must test real login, session ID rotation, native current/new-token behavior, stale CSRF, expiry/logout, disabled/expired users, fresh-right revocation, session invalidation timestamp and entity switching. Preserve native security checks; no forced user, auth/CSRF bypass or production config import is acceptable evidence.

## Native-supported provenance storage

Official documentation describes [expense workflows](https://www.dolibarr.org/presentation-expense-reports.php) and [extrafields on Expense Report objects](https://wiki.dolibarr.org/index.php/Extrafields). The [Multicurrency documentation](https://wiki.dolibarr.org/index.php/Module_Multi-currency) lists commercial documents; it does not establish expense-report multicurrency support. For this source version, the local probes are the narrower deciding evidence: header create and detail insert discard supplied native foreign-currency fields.

`test_m119_native_provenance.php` verifies **3 scenarios** using native `ExtraFields::addExtraField`, header `create`/`fetch`, and inherited detail `insertExtraFields`/`fetch_optionals`:

- A native text extrafield round-trips UTF-8 receipt text, original `180.29 CNY`, decimal-string quote `7.25000000`, quote date/source and estimate `1307.10 EGP` byte-for-byte. The report is an empty unpaid draft, not an accounting conversion.
- Failure to insert header provenance rolls back header creation. This establishes one failure path in synthetic SQLite, not full production atomicity.
- Detail `insert` does **not** automatically save `array_options`. An explicit inherited `insertExtraFields` call and explicit `fetch_optionals` are required. Header/line/evidence/link writes therefore need one verified outer transaction in any future adapter.

Fields and synthetic dictionary tables were created only in process-owned temporary databases and removed at shutdown. No installation or migration was supplied. Native core is unchanged; the fixture adds only a no-op translation method needed by the native API.

## Proposed representation for review

Separate the immutable source, accounting valuation, and cash settlement. A draft's current conversion is an estimate. It must not become an approved valuation merely because a quote exists.

| Layer | Proposed data and rule |
| --- | --- |
| Immutable module evidence | Entity, office, beneficiary/creator, draft UUID, receipt hash/reference, incurred date, original decimal amount/currency, original tax components, classification. Preserve all revisions and the original document. |
| Approved valuation snapshot | Original-to-entity-base quote direction, exact rate, effective date, provenance, approver/time, policy version, base net/tax/gross, recoverability and rounding decisions. Keep the employee estimate separately. |
| Native expense draft | Native base-currency values only after the valuation policy and category/tax mapping are approved and tested. Link exactly one immutable source revision to one native report/line. Do not populate ignored `multicurrency_*` columns by raw SQL. |
| Native provenance extrafields | A versioned text snapshot and source/link identifier for review/display, written through native APIs. Decimal amounts/rates are strings, not native price/float extrafields. Hash and compare against immutable module evidence before every state transition/retry. |
| Settlement evidence | Native payment/allocation/bank IDs, payment currency/amount, base carrying/settlement values, fees and separately reviewed FX difference. A reimbursement never creates the expense again. |

An extrafield is mutable native data, not an immutable ledger or an access boundary. Its presence alone proves neither authenticity, scope nor approval. Native edits, missing/unknown fields, mismatching snapshots and incomplete links must block continuation. Mark provenance empty-on-clone and prevent duplicate source links in the module registry; clone/update/delete lifecycle and transaction/race handling remain untested gates. Prefer one receipt/source currency and valuation policy per linked line; do not combine incompatible rate/tax contexts in a header-only blob. Respect entity/office/native-user checks when displaying metadata.

This base-currency native representation with linked originals is a **candidate**, not accepted native foreign-currency accounting. It needs owner/accountant approval and full tests for native recalculation, journals, reports, export and reconciliation. If native workflows cannot preserve the approved valuation and tax treatment, keep the claim blocked or evaluate a native-supported extension; do not silently approximate.

## Synthetic valuation and reconciliation examples

All amounts/rates below are invented arithmetic examples, not country tax rules or posting instructions. Existing `OfficeExpenseMoney` uses exact decimal strings and preserves quotes. Native probes show that sample tax-inclusive arithmetic can work, not that input tax is recoverable.

| Synthetic case | Expected review result |
| --- | --- |
| Receipt 180.29 CNY, illustrative quote 7.25 EGP/CNY | Estimate 1307.10 EGP; retain original 180.29 and full dated quote. No native write follows automatically. |
| Receipt gross 114.00 CNY = net 100.00 + sample tax 14.00; quote 7.25 EGP/CNY | Converted arithmetic: net 725.00, tax 101.50, gross 826.50 EGP. Recoverability/account choices remain unapproved. |
| Small receipt gross 0.06 CNY = net 0.05 + tax 0.01; quote 1.50 EGP/CNY | Gross rounds to 0.09; separately rounded components are 0.08 + 0.02 = 0.10. The 0.01 discrepancy is explicit and blocks mapping until an approved rounding/tax policy resolves it. Never silently alter tax. |
| Advance 1000 EGP; approved claims 700 EGP; unused cash returned 300 EGP | Employee balance zero; expense 700 once. Advance/return are balance movements; reimbursement is zero. This single-currency illustration cannot establish cross-currency settlement. |
| Payment already has a native bank line | Verify and reuse a supported native allocation/link route, or stop. Do not call a second bank-creation route for the same cash movement. |

Reconciliation must compare immutable original components, approved valuation, native readback, employee balance and cash evidence as separate equalities. Require `net + tax + local taxes = gross` per currency/policy, header equals linked detail totals, approved base totals equal native totals, source link uniqueness, and no duplicated payment/bank allocation. Rounding residuals, partial settlements, reversals, FX and fees need explicit evidence and mappings. A balanced sum alone is not proof of correct classification or valid tax.

## Source findings that constrain accounting

Read-only local source: `accountancy/journal/expensereportsjournal.php` reads native line net/tax/gross, account binding, beneficiary account and entity. It supports date-selection and tax-dispatch settings. Those settings, category bindings and tax accounts must be reproduced in the disposable target; no defaults are inferred from an office country. Do not write a second journal from this module.

`PaymentExpenseReport::create` inserts a payment with `fk_bank=0`; assigning an existing bank ID before calling it is not proven reuse. `addPaymentToBank` calls the bank account's `addline` then links the resulting bank row. Calling it after a bank movement already exists can duplicate cash. No payment/bank methods were executed; the supported existing-movement allocation route must be identified and tested before any settlement adapter.

The current writer remains restricted to base-currency claims with explicitly verified zero tax. It rejects foreign currency, unknown/nonzero tax, advances, transfers, returns and reimbursements. No proposed representation changes those gates.

## Decisions still required

1. Intended release/database engine and legal entity base currency per company; office-to-entity ownership and allowed staff scope. A China office does not itself imply a CNY entity base currency.
2. Recognition/valuation date, permitted quote sources, rate approval/freeze/correction rules and rounding method. Do not treat an employee-entered estimate or latest internet rate as approved policy.
3. Expense categories/accounts and receipt requirements; taxable/exempt/zero-rated classification, recoverability and local taxes by relevant legal entity/document. No EGP/CNY country tax rate is inferred.
4. Employee advance/current-account and reimbursement workflows, partial allocations, actual bank currency, FX/fees, reversals and existing-bank-line reuse. Require tests showing one cash event has one native bank representation.
5. Native API lifecycle tests for provenance, cloned/edited documents, concurrency, reconciliation and financial export, followed by explicit approval before activation.
