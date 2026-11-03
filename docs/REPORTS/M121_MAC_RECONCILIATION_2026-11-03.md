# M121 Mac source reconciliation — 2026-11-03

Status: **Source reconciliation prepared and tested; integrated implementation and backup-erp acceptance BLOCKED.** Owner: Ahmed Hamed. PM assignment: ChatGPT Work. Connected Mac Codex root coordinates bounded finance/Boards reviews and a separate shared-records writer. Production access and changes remain prohibited.

## Execution and preservation

Task accepted by connected Mac Codex session `01a1a143-b1ab-765b-aea0-a13373b3b00c`. Shell verified user `mac`, host `MacdeMacBook-Pro.local`, canonical `/Users/mac/Documents/dev/chinaplanet-OS/codex-dolibarr`, branch `codex/m084-dashboard-repair`, HEAD `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`. Existing tracked/deleted/untracked work and index retained. No pull, branch switch, reset, clean, merge, application source replacement or Git history change. Source candidates exist as review fixtures and patches; they were not applied to canonical source or deployed.

Read current remote handoff, owner staging authorization, cloud checkpoint, full requirements/review/master plan, local M121 audit/plan, shared/repository governance and required VPS references. Management and installed skill current-state copies match SHA256 `2a8a4a8d555d6403bd5ef8878f68b9ef03bd1a7f77c52fe6ed892adf93e02614`. Dated state documents are evidence only; no current runtime parity is inferred.

A process name/start-time inventory confirmed existing Codex/Termius processes. Termius window `vps` was inspected without sending a command. Computer-use refused Codex application inspection for safety, leaving other task ownership unverified. No second desktop engineering writer was opened. Root alone writes the assigned M121 documents; finance and Boards reviewers changed only selected temporary fixtures.

## Source findings and prepared changes

| Candidate | Exact head | Reviewed result |
| --- | --- | --- |
| [PR59](https://github.com/chinaplanetahmedhamed/codex-dolibarr/pull/59) | `6bdc868e7bfeb44c9ffe8b3ca5545300ab0df298` | Prepared semantic port of currency display/sorting and the summary project argument onto newest canonical financial library and portfolio. |
| [PR60](https://github.com/chinaplanetahmedhamed/codex-dolibarr/pull/60) | `422284e0b26d15ef866c1fb7a886d9d77425e9b3` | Prepared five-file delta on newer inspected Boards owner `0fb1dcc89d33760cca3a1b42c83979ba2fe89e77`, preserving M116 finance summaries/gates/links. |

PR59's whole older financial library would remove supplier-identity opt-in privacy controls and `cpcc_managed_funds_schema_ready()`, used by managed funds. The reviewed port preserves both, local language behavior, UI dependency and newer services. Its adapted test now copies `ui.lib.php`, shuffles customer order and includes a no-shortfall customer. No ledger, posting, schema or financial arithmetic rewrite is included.

PR60's whole older JS would lose newer M116 account summaries and statement links. Its three runtime file deltas cannot independently install Boards into canonical: a bounded static 32-PHP-file dependency walk found **22 missing, 5 different, 5 identical** files. Dynamic/native/assets/schema dependencies require additional reconciliation. This static inventory is not a deployment allowlist. Actual priority-band layout alongside M116 finance text remains untested.

## Tests actually executed

Finance, PHP **8.3.21**: three candidate PHP lints PASS; adapted M121 currency/project fixture PASS; existing multicurrency PASS; M029 core funds PASS; privacy/helper probes PASS. Unchanged canonical portfolio fails the new currency assertion as expected; restored candidate passes. Existing M028-C04 fails on both baseline and candidate with `totals mobile labels`, because it asserts older bilingual labels. Preserve this failure; a source expectation mismatch does not prove mobile acceptance.

Boards temporary fixture: PHP lint, JS syntax, PR synthetic fixture, M116 summary regression and M116 block/call/gate preservation assertions PASS. Complete reconciled IIFE startup passed **24** mode/language/preference combinations plus API denial, including Shared transitions. DOM/fetch are synthetic; no real business mutation occurred. The mock permits only the existing visit request. These tests prove no actual CSS layout, browser authentication, physical touch or staging persistence.

Finance review-only patch `finance-reviewed-integration.patch` SHA256 `0a336a8a8dd4165d91e3a312ae021f084d0167799a5365c185682a368749669e`; nonmutating `git apply --check` passes against canonical. Boards review-only patch `boards-owner-scoped.patch` SHA256 `ce6df796e187ac06d4d858a15dfb7a438acadcd438dc1ff6abb65e5d89e74b2e`; applicability check passes against the named newer owner. Neither is a release artifact.

Exact commands, versions, baseline/candidate hashes and limits are retained under [Mac source evidence](../evidence/m121-2026-11-03-mac/): finance/Boards review and manifests, both patches, characterization probes and whole-script startup harness. The local preservation baseline records source hashes/index/HEAD; no private runtime/financial payloads or credentials are included.

## Existing finance semantics needing a contract

Synthetic query doubles reproduce the existing distinction between invoice credit consumption and actual cash. These are characterization observations, not approved accounting results or live SQL tests:

| Synthetic USD invoice100 | Available | Unpaid invoice | Required funding |
| --- | ---: | ---: | ---: |
| Paid25 | -25 | 75 | 100 |
| Fully paid100 | -100 | 0 | 100 |
| Payment25 after selected historical cutoff | 0 | 75 | 75 |

Project statements skip native receipt credits while retaining allocation debits. Unpaid-invoice SQL filters invoice dates but does not bound payment aggregates by payment date; current paid state also affects historical reconstruction. Do not relabel this as customer cash or change arithmetic without agreeing cash/credit/funding/as-of semantics. Equal-name sorting, reversed balances, actual SQL historical cases, authenticated rights/privacy and rendered acceptance remain pending.

## Operational access blocker

Exact intended action: restricted SSH `hostname` probe, account `codex-deploy`, documented shared VPS `31.97.158.178:22`, strict host-key checking, batch mode, eight-second connect timeout, existing dedicated key path only. Initial sandbox networking returned Operation not permitted. Requested escalation was **DENIED by automatic approval review before connection**: backup-only identity/isolation was not verified, and the shared host may expose production. No remote command executed. The denial was not bypassed with Termius, Portainer or another route. A specific owner clarification for shared-host identity plus erp-staging-only metadata was requested and is pending.

Codex app inspection was separately **DENIED** by computer-use safety, so other desktop task ownership remains unknown. No hidden app inspection was attempted.

Completed independently: local source reconciliation, fixture tests, prepared deltas, evidence preservation and handoff receipt. No current server identity, mounted hashes, schema/grants, outbound/mail/payment isolation, docstest target, backup/recovery or authenticated role acceptance has been established. No backup, deployment, restore, account creation, module activation, financial posting or ERP screenshot is claimed. **Production was not accessed or changed.**

## Next bounded steps

1. Resolve the denied shared-host identity preflight through new relevant owner evidence and verify erp-staging-only targets; a retry must remain bounded and cannot access production.
2. Confirm local scope ownership before application edits. Reconcile missing/different Boards dependencies into the owning canonical modules through a reviewed manifest; keep newest finance/Logistics/Office behavior.
3. Verify runtime/schema/grants, Docs ERP target and all outbound isolation, then code/DB/config backup hashes and recovery. Do not restore an old DB over subsequent financial writes.
4. Settle the cash/credit/funding/as-of contract and retained failure expectations; apply only pinned, dependency-complete changes after P1 exit evidence exists.
5. Run authenticated synthetic admin/staff/customer, negative/persistence/rollback and AR/EN desktop/phone tests; update the existing tracker/manual after each function. P1-06 and all integrated runtime gates remain open.

Shared handoff receipt committed as `d75ce09db7c300b78adce9f127dce24420c050b9` and read back exactly. Existing [tracker](https://docs.google.com/spreadsheets/d/1QZMWYIHbuigCW_40XdIFC4ZSknVrYGVsW-IL2rjF8HQ/edit) and [manual](https://docs.google.com/document/d/1pxjs7J7qACZGfhj8XIEdEvw1A5Rfz06IvYphX9G5bEQ/edit) retain prior results and will record this source checkpoint separately from runtime acceptance. No competing tracker, sharing change or application release is authorized by this report.
