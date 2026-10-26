# M116 isolated trial implementation and verification

Status: READY_FOR_REVIEW — implemented trial; native Dolibarr/MySQL installation and acceptance remain outstanding. No production release, push, merge, permission grant or customer portal change.

Approval and policy: `docs/MISSIONS/M116_CUSTOMER_ACCOUNT_STATEMENTS_TRIAL.md`. Plan: `docs/superpowers/plans/2026-10-26-customer-account-statements.md`. Installation, tests and rollback: `deploy/tests/m116/README.md`.

## Ownership and implementation

Owner thread `01a16d4d-b009-71d0-8231-07beb60c6039` was read without messaging or forwarding approval. It was idle and its release checkout clean. Work uses a separate local clone/branch `codex/customer-account-statements-trial` from clean Boards commit `113ddc6855915061d14acc7ab61ea33fd31742f9`; the dirty main development checkout was preserved. No setup script, shared runtime or native DB was changed.

Five new additive tables store canonical entries, versioned allocation lines, idempotent command results, append-only audit and custom text column definitions. BCMath performs bounded exact decimal conversion/half-up rounding. Customer, project and card projections aggregate each allocation once, preserve unallocated funds and original-currency subtotals, exclude drafts, and distinguish funds from obligations/revenue. Native payments/invoices/banks remain authoritative and adapters are deferred.

Added finance-specific rights definitions default-off. Existing customer/project SQL ACLs and task authorizer supplement finance rights; board visibility alone cannot read or mutate accounts. Native bridge consistency is checked for new card allocations. Historical posted attribution survives card movement and operational deletion. All mutations use customer row locks, version checks, atomic audit/command storage and idempotent replay. No rights were granted.

The user did not select a gallery variant. Trial UI adopts the requested paired statement on desktop and compact contextual entry on phone with visible focus, native dialogs, Arabic/English labels and no page overflow at 390px. Customer workspace, project board and card navigation link to reauthorized statement endpoints. XLSX is actual Open XML, text-safe and precision-aware. PDF is explicitly browser Print / Save PDF using the authorized projection, not a native server-generated PDF document.

## Verification evidence

- Exact math: 20 assertions.
- Service persistence/security/idempotency: 29 assertions.
- Hardening: 11 assertions covering partial amount masking, hidden-allocation mutation denial, cross-entity scope, card movement, deleted-card statements/reversal and stale native-task project mapping.
- Concurrency: two processes racing post -> one success/one conflict; two identical saves -> one canonical draft. SQLite serialization only, not certified MySQL row locks.
- Failure injection: failed audit rolls back (service test); failed begin writes nothing (separate test).
- Export: real XLSX ZIP/Open XML, scope values, user formula-like text, high-precision fallback.
- Existing M107 SQL/persistence regression: 28 assertions. Existing real Boards DOM renderer harness passed.
- PHP lint: all 12 changed/added implementation PHP files passed; both JS syntax checks and git diff check passed.
- Synthetic localhost PHP/SQLite browser: customer/project/card contexts, CNY conversion, draft create/edit/post, custom text values, XLSX download, print PDF, mobile entry at 390px, Arabic RTL, same-customer reallocation, reversal roll-up and dropped-success-response idempotent retry. Browser fixture is not native authenticated Dolibarr acceptance.
- Representative desktop, phone and Arabic captures visually inspected. A later optional screenshot call timed out; functional checks reuse the reviewed layouts with capture optional. No Computer Use denial was bypassed.
- Native M109/M110 suite was attempted without its required `M108_QA_CONFIG` and exited 2 (not executed); no shared credentials/runtime were used. Native suites, MySQL schema execution and actual core/session/CSRF acceptance are outstanding.
- CLI installer without its isolation guard refused with exit 2, as intended. No native DDL was run.

## Independent review and decisions

A fresh reviewer found five important defects across the initial and resumed reviews. Each received a failing regression then fix: hidden full-source amount in partial views; uncertain save identity overwritten by another action; deleted cards blocking retained financial history; Excel numeric precision truncation; historical audit snapshots disclosing inaccessible former project allocations. Final tests cover all five. Further hardening added failed-begin protection and native task mapping validation.

Rulings: (1) use existing clean Boards release as base, not unrelated dirty canonical checkout; cost: later integration must rebase/review newer owner changes. (2) Retain historical ledger for deleted cards under finance+native project/customer rights without returning current task fields; cost: must confirm native finance-role policy in acceptance. (3) Browser Print/Save PDF fulfills this trial's PDF action explicitly; native PDF attachment generation remains future work. (4) Native tests require a dedicated M116 DB and an authorized existing test identity; no shared environment reuse or permission grant workaround.

Deferred: native payment adapter, portal exposure, true currency-conversion transfers, statutory accounting mapping, formula custom columns, automatic native PDF attachments and physical-device acceptance. No production readiness claim.

## Resumed local-only completion

The executor reconnected. Saved source and the prior successful PDF render were checked before any retry. Both rendered pages were inspected: all four posted fixture rows and their allocation metadata are present without clipped content. The three interrupted negative HTTP checks subsequently passed against the synthetic localhost harness (GET mutation 405, invalid fixture token 403, malformed payload 422), with unchanged fixture entry count. These do not certify native Dolibarr CSRF.

A second independent review found the historical audit scope issue. `audit-scope.php` reproduced it before the fix; the corrected audit view authorizes every returned historical allocation snapshot and fails before serialization if any scope is inaccessible. It displays the latest 200 authorized events; the database retains the full history. The complete local suite was rerun green after this fix.

Read-only configured-resource investigation found existing local Dolibarr 24.0.2 / MySQL 5.7.39 on 127.0.0.1:8889, database `m097_qa_37feec439b`. Both existing QA configuration pointers address that same shared M097 QA DB. A read-only metadata transaction found no accessible `m116_test_%` databases and no account-entry table in M097. No schema, records, configuration, credentials or grants changed. The M097 runtime remains untouched.

Native acceptance is blocked, not denied or pending: no already configured dedicated M116 installation was found in the inspected local resources. Required next authorization is provisioning a dedicated local `m116_test_<suffix>` database/runtime using the existing local connection where possible. Credential creation or finance permission grants are not assumed; if the chosen native test identity lacks rights, their assignment requires separate explicit authorization. Native schema/locking/CSRF/UI acceptance remains outstanding.

The continuation expressly excludes the prior rejected external-upload operation. No source/report upload, cloud sharing or external delivery package was attempted during this continuation. All files and evidence remain on the Mac. Local commit only; no push, merge or deployment.
