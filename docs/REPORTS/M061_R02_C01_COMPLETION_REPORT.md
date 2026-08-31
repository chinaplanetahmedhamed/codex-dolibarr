# M061-R02-C01 — Completion Report

## Report control

- **Mission:** M061-R02-C01 — Metadata-free artifact correction
- **Mission status received:** APPROVED by Ahmed on 2026-08-25
- **Report status:** DEVELOPER_REPORTED
- **Implementation owner:** Agent 007
- **Branch:** `codex/m061-r02-staging-release`
- **Base commit:** `59177fc`
- **Result commit:** `e2a58636083c67c724e69296c03675b0d104cd10`
- **Environment used:** local release worktree only
- **Production touched:** No

## Business outcome

The C01 package removes the macOS provenance metadata that GNU tar rejected on `backup-erp`, without changing any runtime, support or manifest payload. The helper and installer now pin the corrected artifact and the installer permits only the exact known old-helper-to-C01 upgrade.

## Scope reconciliation

- **Completed:** deterministic metadata-free builder, corrected artifact, dependent artifact/helper/installer pins, installer upgrade boundary and local regression tests.
- **Not completed:** upload, installation, compare, preflight, apply, authenticated acceptance and production; all require later explicit gates.
- **Scope deviations:** None.

## Files changed

| Action | File | Purpose |
|---|---|---|
| Added | `deploy/tools/build_m061_r02_c01_artifact.sh` | Build exact USTAR/gzip bytes from frozen Git blobs without host metadata. |
| Modified | `deploy/tools/chinaplanetos_backup_erp_m061_r02_release.sh` | Pin C01 identity, artifact name and artifact hash. |
| Modified | `deploy/tools/install_backup_erp_m061_r02_release_helper.sh` | Pin corrected inputs and constrain transactional upgrade to the installed R02 helper hash. |
| Modified | `deploy/tests/test_m061_r02_release_package.sh` | Assert exact content, metadata normalization, empty PAX headers and no extended-metadata markers. |
| Modified | `deploy/tests/test_m061_r02_release_helper_contract.php` | Assert C01 identity, builder contract and all dependent pins. |
| Added | `docs/MISSIONS/M061_R02_C01_METADATA_FREE_ARTIFACT_CORRECTION.md` | Record Ahmed's narrow correction approval and result. |
| Modified | `docs/MISSIONS/M061_R02_BACKUP_ERP_STAGING_RELEASE_AND_ACCEPTANCE.md` | Record the local C01 result and next approval gate. |
| Added | `docs/REPORTS/M061_R02_C01_COMPLETION_REPORT.md` | Record local completion evidence and boundaries. |
| Modified | `docs/CHANGELOG.md` | Add the C01 correction entry. |

## Database, accounting, configuration, and permissions

- **Database/schema/data:** None.
- **Accounting/multicurrency:** None; no business payload changed.
- **Configuration/module state:** None.
- **Permissions/customer visibility:** No change. The sudoers file remains byte-identical and still exposes only the six existing fixed helper commands.
- **Product parity:** Admin ERP `NOT_APPLICABLE`; Customer Portal `NOT_APPLICABLE`; Mobile App `NOT_APPLICABLE`. This correction changes release packaging only, not UI, data contracts or runtime behavior.

## Frozen hashes

| Item | SHA-256 |
|---|---|
| Corrected artifact | `3273970c7caad1a4580f662d003cabf5ef1274ce3149c6e9fb83f4670680a870` |
| Deterministic builder | `11e5c6a42c10cdf062852299e36ab8a4b5770a64013fe833d0ee827d317031df` |
| Corrected helper | `b93d60e91ec40e9ad57ebb79a49bf0531e9acfbca5107a49b426cdbd480a6ec7` |
| Upgrade installer | `4630420585ab732b1783312c16b8acaebe528868c28c1c0b9422f6c4ba366f7c` |
| Unchanged sudoers | `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671` |
| Runtime manifest | `928f9050bf656be2a62307d7c6a8be259cccb30856d9cd6086765a198ba81e64` |
| Support manifest | `3997ac41049dc3329a12b370447555d279ee34aef24e3fd3e7d667db979bebdc` |
| Baseline manifest | `81d0a463b0dea91409db3365550808da8ebae2a7ded5d57a38c09d1743c60629` |

## Tests and evidence

| Test | Actual | Result |
|---|---|---|
| Repeated artifact build | Both builds produced artifact SHA `3273970c…a870`. | PASS |
| Payload equivalence | 30/30 new members equal both the old archive payload and frozen Git blobs. | PASS |
| Archive structure/metadata | 30 regular files; USTAR; `0644`; `0:0`; fixed time; no PAX headers/xattr markers; local tar warnings `0`. | PASS |
| M061 PHP lint | Every PHP member in the runtime/support manifests parsed successfully. | PASS |
| Focused M061 tests | Operational consistency, finance/payment note, documents, email source/service all passed. | PASS |
| Mobile API contract | 21 paths and pinned OpenAPI hash passed. | PASS |
| Package/helper contracts | Both passed with C01 and production exclusion asserted. | PASS |
| Shell syntax / whitespace | All changed shell files passed `bash -n`; `git diff --check` passed. | PASS |

GNU tar is not installed in the local macOS worktree. After the separately approved upload-only gate, GNU tar on `backup-erp` listed all 30 members with zero warnings, confirming that the original extended-metadata failure is resolved.

## Security and customer-isolation review

- **Entity and `fk_soc` enforcement:** Runtime code is byte-identical; no authorization path changed.
- **Internal cost/private data exposure:** Runtime and API payloads are unchanged.
- **Files/external links:** Only the local release archive and tooling changed.
- **Security reviewer result:** PENDING for the later release gate; local correction introduces no broader command or data surface.

## Known risks, blockers, and follow-ups

- The corrected C01 helper was subsequently installed and independently verified on canonical `backup-erp` with the frozen hash and six-command surface.
- Its first `compare` stopped fail closed with `M061_R02_FAIL=CANDIDATE_TEST_FAILED`: the isolated candidate test requires the unchanged M061-R01 Logistics library, but that dependency is outside C01's frozen 30-member archive.
- No preflight/apply/verify/status command ran and no runtime, schema, data or email changed. C01 cannot advance. A separately approved C02 dependency-closure correction is required before another staging attempt.

## Deployment boundary

- Local actions performed: deterministic build, checksum refresh, static tests and documentation.
- Staging actions performed: upload-only to `/home/codex-deploy/staging-inbox`, remote checksum/metadata/GNU-tar verification, and checksum-suffixed preservation of the prior inbox helper/installer.
- Staging actions subsequently performed: C01 installer, independent helper/hash/sudo verification and `compare` only.
- Staging actions not performed: preflight, apply, verify, status, rollback, runtime/schema/data/configuration change or email.
- Production actions: None.
- Production readiness is not approval to deploy.

## Rollback instructions

Revert the local C01 commit(s) and stop using `/private/tmp/m061-r02-c01-customer-center-487b61e.tar.gz`. This correction made no database, configuration, runtime, staging or production change, so no operational or data rollback applies.

## Review record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Dev/Test | Agent 007 | PASS | 2026-08-25 | Local deterministic build and full focused test set. |
| Security | — | PENDING | — | Required before release advancement. |
| QA | — | PENDING | — | Required before release advancement. |
| Architect | Agent 007 | PENDING | — | Await frozen-commit review. |
| Ahmed | Ahmed | APPROVED_LOCAL_ONLY | 2026-08-25 | No server or production action authorized. |

## Architect disposition

- **Decision:** PENDING
- **Reason:** Local implementation is developer-reported; release gates remain separate.
- **Documentation updated:** correction Mission, parent release Mission, completion report and changelog.
- **Final mission status:** DEVELOPER_REPORTED
