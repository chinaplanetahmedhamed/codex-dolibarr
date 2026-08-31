# M061-R01 — Shipment consistency release and historical repair

## Control record

- **Type:** RELEASE
- **Status:** STAGING DEPLOYED / VERIFY PASS — AUTHENTICATED ACCEPTANCE PENDING
- **Local preparation approval:** Ahmed Hamed, 2026-08-19, `ok do it`, bounded by Agent 007's announced read-only staging inspection and local artifact/helper preparation gate
- **Staging upload/install approval:** Ahmed Hamed, 2026-08-19, `ok push to backup`, bounded to the four pinned inbox files and helper installation only; no release command authorized
- **Staging compare/preflight/dry-run approval:** Ahmed Hamed, 2026-08-19, `نعم، شغّل compare وpreflight وdry-run لـM061-R01 على backup-erp فقط`; execution stopped after `compare` exposed baseline drift, so `preflight` and `dry-run` were not run
- **C01 staging apply/verify approval:** Ahmed Hamed, 2026-08-21, `continiue`, bounded by Agent 007's announced `backup-erp`-only apply/verify gate
- **Dependency:** accepted M061 runtime commit plus successful authenticated staging UI/security acceptance
- **Production:** prohibited until staging apply, repair verify and real customer acceptance pass, followed by a separate production approval

The local correction is tracked by `M061_R01_C01_RELEASE_DEPENDENCY_CORRECTION.md`. C01 was independently reviewed, installed and deployed; fresh compare, preflight, dry-run, apply and verify passed. Authenticated staging acceptance remains pending.

## Frozen scope

1. Package only the reviewed M061 runtime files and the source repair primitive `deploy/tools/m061_shipment_consistency_repair.php`.
2. Generate mission-specific, checksum-pinned staging and production wrappers only after the M061 implementation commit and artifact are frozen.
3. Expose exactly `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback`, and `status`; no generic shell, arbitrary path, database, entity, container or command argument is allowed.
4. `preflight` must verify environment identity, runtime baseline, artifact/helper/manifests, schema readiness, free space, mail capture on staging, unique code/database backups, and the exact dry-run plan SHA plus proposed/ambiguous IDs and counts.
5. `apply` must require a unique request token and the exact retained dry-run plan. It invokes the repair primitive as root with `M061_REPAIR_CONTEXT=m061-r01-root-only`, inside the fixed Dolibarr container and entity.
6. The repair is limited to unambiguous ATD/ATA forward status and container-number/count corrections. Impossible or drifted records fail closed. Customer-visible events and outbound email are forbidden.
7. `verify` must prove exact after-state, one private audit event per repaired row, unchanged ambiguous records, and authenticated web/mobile projection. HTTP health alone is insufficient.
8. `rollback` may restore only the exact plan prestate when current rows still match the repair after-state, preserve the private apply event, add one private rollback event per row, and preserve all later business changes by failing on drift.

## Current boundary

M061 provides and tests the transactional repair engine, deterministic plan/checksum, ambiguity report, idempotent apply/rollback, private audit events, and fail-closed drift behavior. This draft intentionally contains no artifact SHA, runtime commit pin, server path, sudoers rule or install command because those values do not exist until M061 is committed and accepted. Creating or installing a placeholder helper would weaken the release boundary.

## Frozen local release evidence — 2026-08-19

- Runtime commit: `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`.
- Verified staging baseline: deployed M060 commit `4cb98b36eedfe932097f66822f48837b423d2337`; all 28 M061 runtime prestate hashes match this baseline in source.
- Artifact: `m061-customer-trust-repair-805cf3d.tar.gz`; 39 regular files, metadata-clean structure, SHA-256 `fd834e5e5421b77ecc9db1b28e95ae7ee0676317a7714b8ca3b8f954dd7b3bac`.
- Runtime manifest: 28 files, SHA-256 `66306449b0efac20054fe658f2ca9801513a234cb17c25b4235a3a86e149cc3b`.
- Support manifest: 8 files, SHA-256 `d3094ee745e8f921a3b8cc081b48c32a506d076b23835f1442ea31cb62f30e1a`.
- Baseline manifest: 28 entries, SHA-256 `72a8a36fc7f5d0fd8063dd49bcf7c5ead60bf0ca9bbdc49d3221e42efa91c0cb`.
- Staging helper source: `deploy/tools/chinaplanetos_backup_erp_m061_r01_release.sh`, SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7`.
- Restricted sudoers source: SHA-256 `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`; exactly seven fixed commands.
- Checksum-pinned installer source: SHA-256 `c10f6270159a7aba881601f9fc518ce9a06f12df7847426b1ef0f37689652af5`.

Read-only staging inspection confirmed canonical `backup-erp` is healthy and M060 is `DEPLOYED`. No M061 file, helper, sudoers rule, plan, database row, event or email was created on staging. Production was not accessed by any M061 command.

## Staging upload evidence — 2026-08-19

- Uploaded the exact artifact, helper, sudoers source and installer to `/home/codex-deploy/staging-inbox` with final owner/mode `codex-deploy:codex-deploy 0644`.
- Independent server-side SHA-256 values match all four frozen local pins.
- The initial restricted-account installation attempt stopped before installer execution with `sudo: a password is required`; at that upload gate the helper/sudoers were absent. The later confirmed root installation is recorded below.
- No `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback` or `status` command was run. Runtime code, database, documents, configuration, containers and customer data are unchanged. Production was not accessed.

## Staging helper installation evidence — 2026-08-19

- Ahmed explicitly confirmed the security-sensitive root action with `نعم، ثبّت مساعد M061-R01 على backup-erp الآن.`
- The checksum-pinned installer returned `M061_R01_INSTALL=PASS`, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.
- Installed helper `/usr/local/sbin/chinaplanetos-backup-erp-m061-r01-release` is `root:root 0755`, size `34804`, SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7`.
- Installed sudoers `/etc/sudoers.d/codex-deploy-backup-erp-m061-r01` is `root:root 0440`, size `699`, SHA-256 `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`; `visudo -cf` returned `parsed OK`.
- Independent restricted `sudo -l` shows exactly the seven fixed M061-R01 commands. No release command was executed.

## Remaining approval gates

1. Complete authenticated Arabic/English, 390px/1280px, forged-ID/token/direct-download acceptance on staging.
2. Inspect the customer-visible web/Mobile parity for real workflow outcomes; HTTP success alone is insufficient.
3. Only after staging acceptance, prepare a separately pinned production wrapper and request exact production approval. This Mission and the staging approval do not authorize production.

## Local C01 preparation — independently reviewed

Ahmed approved local-only preparation and retesting of M061-R01-C01. The corrected local artifact contains 29 runtime files, including the canonical CP Kanban project-sync dependency, and uses the diagnosed projects-page hash plus an `ABSENT` library baseline. DevOps/VPS, Security and QA independently returned `PASS` against frozen commit `90f23d4d13b2e164eb75963431d7d10f57830955`. Exact checksums and test evidence are recorded in `docs/REPORTS/M061_R01_C01_RELEASE_PREPARATION_REPORT.md`. No upload, staging access, helper replacement or release command is authorized by this preparation.

## C01 staging helper installation — 2026-08-20

After a new exact approval, the four reviewed C01 files were uploaded to canonical `backup-erp`, and the checksum-pinned installer replaced only the exact known old helper. Installation and independent verification passed with helper SHA-256 `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`, unchanged sudoers SHA-256 `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`, `visudo` success and exactly seven fixed commands. No release command ran and no runtime/database/customer/email state changed. Fresh approval is required before staging `compare`, `preflight` and `dry-run`.

## C01 staging compare, preflight and dry-run — 2026-08-20

After a separate exact approval, the installed C01 helper identity was reverified and the three staging-only gates ran conditionally. Compare passed with `DRIFT_COUNT=0`; preflight passed; and the final dry-run passed with retained plan SHA-256 `5e228b7ad4426a88345fd08544ae0833eb8ce5bce74108e5d202f1ae17a29717`, plan-file SHA-256 `e3387bc8003340abe13df87ec3fd760cf8264cce8bcdf94b2c0a61b36889cd4c`, data SHA-256 `78c584bfea6a1389a1fe54f69ae51fd99d732b434b840057e861cc115bd78457`, zero candidate IDs, and ambiguous ID `1`. No database row, runtime file, customer event, email, or production resource changed. Apply and verify require a new separate approval.

## C01 staging apply and verify — 2026-08-21

After Ahmed continued from the exact announced next gate, the checksum-pinned helper revalidated the environment, artifact, baseline and retained plan, created and restore-validated code/database backups, and deployed the 29 reviewed runtime files. Apply request `m061-r01-20260821T061643Z-20c457318419` passed; code-backup SHA-256 is `623b81be3c357dd278b2bda95c0ba763b8cdfec4ab052ea910cd3ab7be572daf`, and database-backup SHA-256 is `a8917af9a1215e3e8656da786e0ee5850c825128b08cd07b4c47b73dd8a753b8`. The retained plan had zero candidates, so no shipment row or repair event changed; ambiguous record `1` remained excluded. Independent verify returned `STATE=DEPLOYED` with exact release/artifact/plan match, no email and no production access. Authenticated staging acceptance remains pending.

## Staging compare result — 2026-08-19

- The approved restricted `compare` command completed its inspection with the pinned runtime commit `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a` and artifact SHA-256 `fd834e5e5421b77ecc9db1b28e95ae7ee0676317a7714b8ca3b8f954dd7b3bac`.
- Twenty-seven runtime paths matched the reviewed M060 baseline (including the expected absent new repair library); `custom/chinaplanetcustomercenter/projects.php` reported `STATE=DRIFT`.
- Expected M060 baseline SHA-256 for that file is `3ca0889a46b875ec21a4ca6fdc4af45d75a5e2bcbca85b941dad5e8f98b96ea2`; the frozen M061 release SHA-256 is `7c608f7dc7b129fa9aeaad2d727bb4776adbb41a03f4c3aea97a3331723af307`.
- The helper summary reported `DRIFT_COUNT=1`, `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, and `PRODUCTION_ACCESSED=NO`. Its `COMPARE=PASS` token means the inspection command itself completed; it does not override the non-zero drift count. The helper's `preflight` and `dry-run` paths fail closed on any non-zero drift count.
- Execution stopped at this gate. `preflight`, `dry-run`, `apply`, `verify`, and all production commands were not run. No retained plan was created, no candidate or ambiguous shipment IDs were produced, and staging runtime/database/customer data/email were unchanged.

## Read-only root diagnosis — 2026-08-19

- Ahmed explicitly approved a root diagnosis of the one drifted `backup-erp` file in read-only mode.
- Root SFTP inspected only the staging custom-volume paths needed for this dependency diagnosis. The live `chinaplanetcustomercenter/projects.php` is a regular read-only file, size `12678` bytes, staging timestamp `2026-07-18 23:25`, SHA-256 `0e3878ffcc3122ea13f8e5b409a7dfebef50cb46673f2dca4134b8ebd5fb5cc4`.
- The downloaded read-only copy is byte-for-byte identical to the pre-M054 Git blob present at commits `2dff2fd76d8d4f6b5690c13828c3fcd58f2499b7`, `63c0a2cb4d62d13224d564cf74d75517de8ba0e9`, and `01d405e80f30598cd81e22f59683ca998c9f366d`. Its only difference from the M054/M060 source baseline is that it includes the historical Customer Center `lib/projectsync.lib.php` path instead of the canonical CP Kanban path.
- M054 was explicitly source-only and never changed staging. M060's 38-file runtime manifest does not include `chinaplanetcustomercenter/projects.php`, `cpkanban/lib/projectsync.lib.php`, or the Customer Center compatibility include. Therefore the M061 baseline incorrectly treated the M060 source commit as proof that this unrelated runtime file had been deployed.
- Root SFTP also confirmed that live `custom/cpkanban/lib/` contains `main.lib.php`, `customer_project_center.lib.php`, and `cpkanban.lib.php`, but not `projectsync.lib.php`.
- The frozen M061 candidate `projects.php` requires `custom/cpkanban/lib/projectsync.lib.php` for internal users, while the current M061 runtime manifest and artifact omit that file. Merely changing the baseline hash would therefore create a dependency-incomplete release and is prohibited.
- Required correction: create reviewed `M061-R01-C01` release inputs that add the canonical CP Kanban project-sync library with `ABSENT` staging baseline, replace the `projects.php` staging baseline with the verified live SHA above, regenerate all manifests/artifact/helper/installer checksums and count assertions, and rerun local package/helper tests. Installing or running that correction requires new explicit staging approvals.
- Diagnosis changed no server file, runtime, database, configuration, documents, container, customer data, event, or email. Production was not accessed.
