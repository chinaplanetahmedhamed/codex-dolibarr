# M061-R02-C02 — Candidate dependency-closed correction

## Authority

- Approved by Ahmed Hamed on 2026-08-25: `Approved: prepare and deploy M061-R02-C02 dependency-closed correction on backup-erp only, including all gates; no production or real email.`
- Target: canonical `backup-erp` only.
- Production and real outbound email are forbidden.
- The authorized gate sequence is local preparation and review, upload, checksum-pinned helper installation, independent verification, `compare`, `preflight`, `apply`, `verify`, `status`, and bounded runtime acceptance.
- Stop fail closed on the first identity, checksum, candidate, environment, backup, restore, schema, drift, or runtime mismatch.

## Correction objective

M061-R02-C01 fixed the archive metadata but its isolated candidate failed before compare because `deploy/tests/test_m061_operational_consistency.php` referenced frozen M061-R01 source dependencies that were not included in the candidate archive. C02 closes that candidate-only dependency graph without changing the 19-file runtime allowlist, the runtime commit, the database migration, or customer behavior.

## Candidate-only dependency closure

The support manifest adds the nine frozen files required by the operational consistency test and its direct includes:

- `custom/chinaplanetcustomercenter/lib/export.lib.php`
- `custom/chinaplanetcustomercenter/lib/operations.lib.php`
- `custom/chinaplanetcustomercenter/projects.php`
- `custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php`
- `custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php`
- `custom/chinaplanetlogistics/lib/m061_repair.lib.php`
- `custom/chinaplanetlogistics/lib/shipment_customer.lib.php`
- `custom/cpkanban/card.php`
- `deploy/tools/m061_shipment_consistency_repair.php`

These are support files only. The release helper must never copy them to the live custom volume. Runtime comparison, backup, apply, rollback, and verification remain limited to the unchanged 19-file runtime manifest.

## Artifact contract

- Release: `M061-R02-C02`.
- Runtime commit: `487b61e690d7d030ec25d54a3b62afdb7e78fb55`.
- Deployed baseline commit: `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`.
- Expected archive members: 39 total = 19 runtime + 17 support + 3 manifests.
- Every member must be a regular `0644` file with `uid:gid 0:0`, `root:root`, timestamp `1980-01-01T00:00:00Z`, no PAX records, no extended attributes, no duplicates, and no unsafe path.
- The package test must extract the artifact into an isolated directory and execute the operational consistency test there, proving that candidate validation is dependency-closed.

## Apply safety

- Install only through the reviewed root installer pinned to the already installed C01 helper hash.
- Preserve the exact six-command sudo surface: `compare`, `preflight`, `apply`, `verify`, `rollback`, `status`.
- `apply` must create and hash a code backup and a full database backup, successfully rehearse the database restore, and retain rollback evidence before code or schema mutation.
- No email send action is allowed; acceptance is preview/read-only only.
- Production resources, legacy staging resources, Compose configuration, proxy, DNS, and unrelated modules remain out of scope.

## Completion evidence

Exact artifact, helper, installer and gate evidence is recorded in `docs/REPORTS/M061_R02_C02_COMPLETION_REPORT.md`.

## Execution result — 2026-08-25

- Local dependency closure passed: two byte-identical 39-member builds, metadata/allowlist verification, isolated operational-consistency execution, all six candidate tests, helper contract, PHP lint, shell syntax and `git diff --check`.
- The exact four files were uploaded to canonical `backup-erp`; server hashes, regular-file metadata and GNU-tar 39-member warning-free listing passed.
- After Ahmed's action-time confirmation, the checksum-pinned installer replaced only the exact C01 helper. Independent restricted verification confirmed the C02 helper hash, `root:root 0755`, valid shell syntax and the same six fixed sudo commands.
- Restricted `compare` executed every C02 candidate test successfully and reached runtime comparison, proving the C02 dependency-closure objective.
- Compare then reported `DRIFT_COUNT=1`: `custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php` is absent in the live runtime, while the R02 baseline manifest incorrectly expects SHA-256 `507dec6ff26b7d3886cd60b2a02202a1e206010f0996bca2634613b2cf3bafd8`.
- Read-only M061-R01 verify still passes while the file is absent, confirming the R02 baseline was inferred from the source commit rather than the exact deployed R01 runtime scope.
- Execution stopped fail closed. `preflight`, `apply`, `verify`, runtime acceptance and rollback did not run. `status` returned `M061_R02_STATUS=NOT_DEPLOYED`.
- No release request, code backup, database backup, code/schema/data/configuration/customer/email mutation or production access occurred.
- C02 is complete for candidate dependency closure but cannot deploy. The next safe gate is a separately approved C03 baseline correction that changes only this path's baseline to `ABSENT`, regenerates every dependent pin, reinstalls the exact helper and repeats the gates.
