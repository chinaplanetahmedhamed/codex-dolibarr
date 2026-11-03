# M121 supplier obligations and payment safeguards — 2026-11-03

Status: tested bounded local source corrections, not a deployment or full M121 acceptance. Work assigns; Codex coordinates exclusive file writers and independent review. Production remains prohibited. All original dirty work, HEAD and index are preserved. A brief unavailable execution context interrupted work; after restoration root read back the final payment-service/test hashes and unchanged HEAD/empty index before continuing evidence work.

## Confirmed operator backup outcome and next safe steps

Ahmed reported `M121_STAGING_BACKUP_INTEGRITY=PASS RESTORE_PROOF=NO` for the previously prepared fixed human read. This establishes integrity of the identified OLD archive and saved source originals only. Its earlier preparation/pending entries remain historical. Raw infrastructure output remains private. No fresh backup, database import, recovery rehearsal, permission grant, deployment or production access occurred.

Existing owner consent already covers backup-ERP backup/recovery verification and testing; no routine permission request is needed. The remaining gates are technical: current mounted source/schema/roles and anonymization, full integration/outbound/Docs isolation, a fresh consistent scoped baseline, and a validated isolated scratch-resource identity/access/execution path. No reviewed existing restricted wrapper establishes those facts: legacy commands target another stack, the installed fixed helper is release-specific with lock/state side effects, and old recovery scripts can replace active data. None was invoked. The earlier restricted Docker denial was not retried or bypassed.

Next permitted progression is to reconcile current target ownership/parity and anonymized acceptance scope through a supported authorized read path, pin a release-specific allowlist and its compatible current predecessors, then verify a fresh scoped backup and rehearse recovery on independently isolated scratch resources. There is no validated command for that rehearsal yet. Never restore the dated dump over subsequent financial/business records or use the active database as a recovery test. Source work below does not close these gates.

## Managed receipt transaction failures

Actual service fault injection showed `createPendingReceipt()` ignored database begin/commit failures. A failed begin followed by an audit failure could leave an autocommitted receipt without its event. A failed commit could return a successful receipt ID before persistence was confirmed.

The correction checks the begin result and both commit paths. A failed begin stops before writes. An unconfirmed commit throws; the existing cleanup attempts rollback and releases the request lock. If the underlying commit already persisted but its acknowledgement was lost, durable rows are retained and retrying the SAME identity reconciles the existing receipt rather than duplicating it. No deletion, compensating financial entry or blanket nested-transaction rule was added. Other service/caller transaction ownership is not certified by this slice.

The new transaction regression executes actual service SQL on synthetic SQLite:27 assertions pass on PHP8.2.0 and8.3.21, with initial RED and all3 removed-check mutations RED. Cases cover identical/conflicting retry, failed begin, commit failure before/after persistence, existing-row retry commit failure, audit failure and control cleanup. Advisory locks are fixture seams and SQLite strips terminal FOR UPDATE; these tests alone are not concurrency evidence.

A separate fresh private no-network MySQL5.7.39 fixture exercised actual request locks with two PHP workers. Both native sessions were observed waiting on the held request lock before release. Three cases passed: identical key/body gives one receipt/event/ID; conflicting body gives one winner plus payload rejection; distinct keys give independent receipts/events. Conservation and lock release were checked. The owned engine stopped and its socket/PID disappeared.

That native run tested pending RECEIPT REGISTRATION, not native cash posting, allocations, target MariaDB10.6, the native Dolibarr driver or nested workflow ownership. Its full service build preceded the alias-only edits; the tested receipt method remains byte-identical. Exact build/method hashes are in the manifest.

## Supplier currency identity

The existing Customer Center normalizer stores Chinese-yuan allocations as RMB while native supplier/bank records can retain CNY. Actual queries reproduced the picker hiding and settlement rejecting the same original currency.

The correction uses the existing canonical comparison key at the allocation picker/assertion and supplier attribution boundary. It retains entity/customer/project/exact-operation/status checks, handles returned RMB/CNY allocation aliases and rejects unrelated/invalid currency keys. Raw native currency, original/base amount selection, bank/payment codes, rates, payload identity and stored money remain unchanged. It performs no conversion or cross-currency sum.

The alias regression passes59 actual-SQL SQLite assertions plus syntax on PHP8.2/8.3. It covers both allocation/native alias directions, canonical receipts, retained allocation-code compatibility, USD unaffected, EUR rejection, original40 versus base8, amount ceilings, retry/event identity and entity/customer/project/operation/funder/status boundaries. Native cash objects are bootstrap stubs and control guard health is an explicit assumption. The base currency in these fixtures is USD. Legacy RAW CNY receipt reconciliation remains unchanged; this is not universal currency reconciliation or full payment acceptance.

## Supplier lifecycle evidence

The project lifecycle projection previously trusted a confirmed invoice marker even when its native invoice moved to a different project/supplier; the supplier owner rejected the same mismatch. A paid stale reference could therefore still display supplier fully paid.

The corrected read projection keeps confirmed markers visible and validates the native standard invoice, entity, supplier/project and its linked historical supplier order. Missing, contradictory or unreadable evidence makes the paid-complete flag false, including mixed valid/invalid markers and legacy-paid fallback cases. No eligible guided marker retains the existing legacy-only behavior. Mutation-only active/latest-order conditions are not imposed on historical read evidence. Only booleans leave this query; no supplier identity/cost disclosure, posting, ledger, new table or money sum is introduced.

The lifecycle regression passes33 actual-query SQLite cases on PHP8.2/8.3, lints and6 baseline/mutation negative controls. It covers paid/partial/unpaid, invoice/order/context drift, missing native evidence/order links, foreign scope, mixed markers, query failure, legacy fallback and boolean/privacy output. Independent review found no material introduced defect. The same33 cases also pass with unmodified generated SELECT/SHOW TABLES statements on a fresh empty private no-network MySQL5.7.39 fixture, using checksum-pinned source and actual custom-table DDL with minimal native read-model tables. The owned server stopped and its socket/PID disappeared. This verifies query compatibility, not full native schema, target MariaDB, command/authentication or historical acceptance.

## Unresolved source contracts and acceptance

A direct service probe could attribute a same-project payment from another operation. The sole canonical caller validates the exact selected operation and passes its own persisted payment marker, so no current external bypass or concurrent exploit was demonstrated. The lower-level exact-source binding contract, especially unmarked historical payments, remains to be defined; no guessed operation identity or blanket historical rejection was added.

The existing guided-native override of manual supplier commitments is retained. Ambiguous legacy/manual/native links must be reconciled explicitly; no independent ledgers were added together. A unified supplier statement, complete trustworthy historical statements, target authenticated roles/persistence/failure behavior, and full native cash concurrency remain OPEN. Existing baseline contract/label failures remain recorded separately.

All3 bounded slices received independent source review. Source patches remain LOCAL for review; the earlier exact three-file publication approval is separate. No new application/test code is published by this report. [Sanitized evidence manifest](../evidence/m121-supplier-payment-2026-11-03/manifest.json) records exact source/test identities and actual results. Shared tracker/manual retain actual outcomes and limits; the final write/readback is recorded in the manifest. No screenshot or runtime pass is invented.
