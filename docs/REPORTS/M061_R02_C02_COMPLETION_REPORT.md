# M061-R02-C02 — Completion Report

## Control and disposition

- Mission: candidate dependency-closed correction and canonical `backup-erp` gates.
- Approval: Ahmed Hamed, 2026-08-25.
- Result: `CORRECTION_REQUIRED` at compare; no deployment occurred.
- Target accessed: canonical `backup-erp` only.
- Production accessed: No.
- Real email sent: No.

## Completed correction

C02 closes the isolated candidate-test dependency graph while leaving the runtime allowlist unchanged at 19 files. Nine frozen support-only files were added, producing a 39-member deterministic archive: 19 runtime files, 17 support files and three manifests. The helper never copies support files into the live custom volume.

## Frozen identities

| Item | SHA-256 |
|---|---|
| Runtime commit | `487b61e690d7d030ec25d54a3b62afdb7e78fb55` |
| Deployed baseline commit label | `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a` |
| C02 artifact | `e4ce5e8cfdcab1905d863a2cd026e31daee86ec04c70d69882c7eda4766c69dd` |
| Deterministic builder | `aa9e8d90f3dc3a56ee8d71935718888936b094be722bb3c0e20c76eb1191ec82` |
| Installed helper | `1eb6b15bafa3cd358293ec50d00283a572fbb8b041e11f06e82670e6433da01f` |
| Upgrade installer | `e884d090bca3e1e0feac7e7079a70371bbb6f2ee9d1df3458ba94403cf02b7b2` |
| Unchanged sudoers | `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671` |
| Runtime manifest | `928f9050bf656be2a62307d7c6a8be259cccb30856d9cd6086765a198ba81e64` |
| Support manifest | `41240214c370260074d0db669c2b27f8f7557703446445587176e048be08df43` |
| Baseline manifest | `81d0a463b0dea91409db3365550808da8ebae2a7ded5d57a38c09d1743c60629` |

## Local and upload evidence

- Two C02 builds were byte-identical.
- Package verification passed exact 39-member allowlist and payload hashes, regular `0644` members, `0:0`, `root:root`, fixed 1980 timestamp, no PAX headers, xattrs, unsafe paths or duplicates.
- The package test extracted the archive into an isolated directory and executed `test_m061_operational_consistency.php` successfully.
- All six candidate tests, all PHP-member lint, release-helper contract, shell syntax and `git diff --check` passed.
- Server-side hashes and `codex-deploy:codex-deploy 0644` metadata matched all four uploads. GNU tar listed 39 members with zero warnings.
- The root installer returned `M061_R02_C02_HELPER_INSTALL=PASS`, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, and `PRODUCTION_ACCESSED=NO`.
- Independent restricted verification confirmed the installed helper is `root:root 0755`, 42,730 bytes, with exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` exposed.

## Compare result and blocker

Candidate lint and all static tests passed on the server. Schema inspection correctly returned `MIGRATION_REQUIRED REASON=TABLE_MISSING`. Eighteen runtime paths matched either the deployed baseline or an explicitly absent baseline. One path did not:

| Path | Live | R02 baseline | R02 release |
|---|---|---|---|
| `custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php` | `ABSENT` | `507dec6ff26b7d3886cd60b2a02202a1e206010f0996bca2634613b2cf3bafd8` | `9bbe32546723a6f4547e119abca66aca72f5c378f89d966f2a0ab0aa38b170df` |

The compare command ended with `M061_R02_COMPARE=PASS ... DRIFT_COUNT=1`, which is a successful diagnostic result but prohibits preflight. A separate read-only M061-R01 verify returned PASS while the file remained absent. Therefore, the baseline entry was inferred from the M061-R01 source commit and is not an exact description of its deployed runtime scope.

## Mutation and rollback accounting

- Helper/inbox mutation: C02 helper installed; C01 inbox files preserved under checksum-suffixed names.
- Runtime code/schema/database/business data/documents/configuration/containers: unchanged.
- Release state: `M061_R02_STATUS=NOT_DEPLOYED`.
- Release request, code backup, database backup and restore rehearsal: not created because apply was never entered.
- Email and Agenda: unchanged; no real email or preview submission.
- Rollback command: not invoked because no release mutation occurred.

## Next safe gate

Do not continue C02. Prepare a separately approved C03 baseline correction that changes only the Mobile API class baseline from the false source hash to the verified `ABSENT` state, regenerates all dependent hashes/tests, permits only the exact C02-helper upgrade, and repeats upload, install, compare, preflight, apply, verify, status and bounded no-send acceptance on canonical `backup-erp`.
