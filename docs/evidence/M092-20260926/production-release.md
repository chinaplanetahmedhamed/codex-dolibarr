# M092 production release — 2026-09-26

DEPLOYED. Independent production preflight, apply and verify PASS. Authenticated live browser acceptance is pending stable control of the existing ERP window; local authenticated persistence/lifecycle acceptance passed.

Ahmed's direct-live authorization and follow-up deadline/cancel request reused. No staging actions, new access grants, schema/hook migration or business-record writes during deployment. Runtime source `c3f47f110c12d64cb3d2bcec3c561ea95d6d80e5`, delivery `28b9373`, branch `codex/m091-task-agenda`.

- Thirteen task-control files; sixteen unchanged prerequisites verified against final M091-C02.
- Payload SHA-256 `a65c788c30b266e3fa2b95641e09cffc9a0e9d520a5736eb4d66cac05792b787`.
- Manifest SHA-256 `613e26e8d10d3cfdbcec969848b5629a04ffcce104b712c1837fad9edfbfb0ef`.
- Helper SHA-256 `401d67978bfa7a2fa4cd1e61594c1cbff9e271056d2a41b00e0773ec7597ff33`.
- Target `vision_erp`, `vision_db`, `vision-os_dolibarr_custom`, compose project `vision-os`.
- Root-only backup/state `/root/chinaplanetos-m092-production/`.
- Code backup SHA-256 `8fe13cd93aa5d74585d6e5fba0974f0c3508a49a2667ead9e7936922a50f5549`.
- Database backup SHA-256 `9708d4707fd49721a705f008f5aeace0caede58c62050cfe45d55a591a80b311`.

Verified helper bytes executed from existing authorized Termius root console; restricted SSH uploaded artifacts and retrieved non-sensitive result files. All three actions returned PASS, apply/verify status DEPLOYED. Post-deploy verification checked all file pins, native runtime/login health, existing staff Agenda projection, own Archive query, availability of atomic schedule API and cancellation authorization for staff-owned task 62. No business data was changed by these checks.

Fifteen release integrity/recovery tests pass, including baseline/dependency/metadata drift, tampering, backup failure, apply failure rollback, recovery failure recording, replay refusal and corrupted/post-drift rollback rejection. Rollback restores only code, preserving later task/financial records.

Local verification: 37 new controls assertions, 47 Agenda assertions, 145 existing task/stage/collaboration/own-board/personal-board assertions, plus syntax/diff checks and English/Arabic desktop/mobile UI. Initial local regression connection failure was resolved by running the generated suites directly; all suites passed. Stale legacy M059 source-shape expectations are documented in M091 and are not new runtime regressions.
