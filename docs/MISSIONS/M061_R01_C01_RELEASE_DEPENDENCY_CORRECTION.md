# M061-R01-C01 — Release dependency and staging-baseline correction

## Mission control

- **Type:** RELEASE CORRECTION
- **Status:** STAGING DEPLOYED / VERIFY PASS — AUTHENTICATED ACCEPTANCE CORRECTION REQUIRED
- **Owner:** Agent 007 Central
- **Required reviewers:** DevOps/VPS, Security, QA
- **Ahmed approval:** `نعم، جهّز M061-R01-C01 محليًا فقط وأعد الاختبارات والمراجعة.`
- **Approval timestamp:** Wednesday 14:22, supplied in the approval message
- **Target:** Local source plus separately approved `backup-erp` inbox upload and helper replacement
- **Staging install approval:** Ahmed Hamed, `ok , do it`, following Agent 007's exact announced gate to upload the four C01 files and replace the old helper only
- **Staging read-only approval:** Ahmed Hamed, `ok , do it`, following Agent 007's exact announced gate for `compare`, conditional `preflight`, and conditional `dry-run` on `backup-erp` only
- **Staging apply/verify approval:** Ahmed Hamed, 2026-08-21, `continiue`, following Agent 007's exact announced next gate for `apply` and `verify` on `backup-erp` only
- **Authenticated staging acceptance approval:** Ahmed Hamed, 2026-08-23, `نعم، نفّذ القبول المصادق لـM061 على backup-erp فقط، دون الإنتاج.`
- **Staging and production:** `rollback` and `status` were not authorized or run; production remains prohibited

## Problem statement

The original M061-R01 package assumed that the M060 source commit represented the complete `backup-erp` runtime baseline. Approved read-only diagnosis proved that staging still has the pre-M054 `custom/chinaplanetcustomercenter/projects.php` and does not have `custom/cpkanban/lib/projectsync.lib.php`. The M061 candidate page imports the canonical CP Kanban library for internal users, so changing only the page baseline would leave the release dependency-incomplete.

## Approved correction

1. Add `custom/cpkanban/lib/projectsync.lib.php` to the exact runtime manifest and artifact.
2. Mark that library `ABSENT` in the verified staging baseline.
3. Pin `custom/chinaplanetcustomercenter/projects.php` to the diagnosed live SHA-256 `0e3878ffcc3122ea13f8e5b409a7dfebef50cb46673f2dca4134b8ebd5fb5cc4`.
4. Keep all other runtime and support files byte-identical to runtime commit `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`.
5. Regenerate the artifact, manifest hashes, helper pins, count assertions and installer pins under release identity `M061-R01-C01`.
6. Permit the installer to replace only the exact previously reviewed M061-R01 helper and unchanged sudoers file. Reject any other installed state and preserve the reviewed prior files for automatic failure recovery.
7. Repeat local package, helper-contract, PHP, Mission regression and Mobile tests, then obtain independent DevOps/VPS, Security and QA review of the frozen local evidence.

## Acceptance criteria

- The package contains exactly 29 runtime files, 8 support files and 3 manifests, all regular allowlisted entries.
- The canonical CP Kanban project-sync implementation is present and matches the frozen runtime commit.
- The mixed staging baseline is explicit and testable: 26 M060 runtime hashes, the verified pre-M054 projects page hash and two reviewed `ABSENT` paths.
- The helper retains exactly seven fixed staging commands and contains no production target or identifier.
- The installer accepts only a clean install or the exact old helper SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7` with the exact unchanged sudoers SHA-256.
- Local tests and all three independent reviews pass with no unresolved safety condition.
- The local preparation gate performs no upload or server action; any later upload/install requires and records a separate exact approval.

## Release boundary

Completing this local correction does not authorize uploading or installing C01. A new exact staging approval must name the corrected artifact/helper/installer checksums. Installation does not authorize `compare`, `preflight`, `dry-run`, `apply`, `verify` or `rollback`; those gates remain separately approved in sequence.

## Independent review result

- DevOps/VPS: **PASS** against frozen commit `90f23d4d13b2e164eb75963431d7d10f57830955`; dependency closure, mixed baseline, pins, installer upgrade path and staging-only boundary verified.
- Security: **PASS**; exact allowlist/types, checksum/metadata/symlink controls, seven-command sudo surface, unknown-state rejection, prior-state failure recovery and production exclusion verified.
- QA: **PASS**; package/helper tests, 33 PHP lints, 20 ERP regression files, 12 Mobile test files / 39 tests, typecheck, lint and clean diff reverified.
- Non-blocking follow-up: add a local executable fault-injection test for installer failure recovery when an appropriate isolated root harness is available. The current path was reviewed statically; actual installation remains a later staging gate.

## Staging helper installation — 2026-08-20

- The four exact reviewed files were uploaded atomically to `/home/codex-deploy/staging-inbox` as `codex-deploy:codex-deploy 0644`; server-side SHA-256 values matched the local pins.
- Before installation, the existing helper was `root:root 0755` with the exact reviewed prior SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7`, and the restricted account exposed exactly the seven expected commands.
- The checksum-pinned root installer returned `M061_R01_INSTALL=PASS`, `RELEASE=M061-R01-C01`, `UPGRADED_PRIOR_RELEASE=1`, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.
- Installed helper: `/usr/local/sbin/chinaplanetos-backup-erp-m061-r01-release`, `root:root 0755`, SHA-256 `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`.
- Installed sudoers: `/etc/sudoers.d/codex-deploy-backup-erp-m061-r01`, `root:root 0440`, SHA-256 `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`; `visudo -cf` returned `parsed OK`.
- Independent restricted verification again showed exactly `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback` and `status` for the M061-R01 helper.
- No helper command was executed. Runtime code, database, documents, configuration, containers, customer data, events and email were unchanged. No production resource was accessed.

## Staging compare, preflight and dry-run — 2026-08-20

- Immediately before execution, the installed helper was independently rechecked as `root:root 0755`, size `34812`, SHA-256 `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`; the seven fixed M061-R01 sudo commands remained present.
- `compare` returned `M061_R01_COMPARE=PASS`, release `M061-R01-C01`, artifact SHA-256 `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463`, and `DRIFT_COUNT=0` for the explicit mixed baseline.
- Conditional `preflight` returned `M061_R01_PREFLIGHT=PASS`. Its embedded dry-run found `CANDIDATES=0`, `AMBIGUOUS=1`, `AMBIGUOUS_IDS=1`, with stable data SHA-256 `78c584bfea6a1389a1fe54f69ae51fd99d732b434b840057e861cc115bd78457`.
- The separately invoked final `dry-run` returned `M061_R01_DRY_RUN=PASS`, retained plan SHA-256 `5e228b7ad4426a88345fd08544ae0833eb8ce5bce74108e5d202f1ae17a29717`, plan-file SHA-256 `e3387bc8003340abe13df87ec3fd760cf8264cce8bcdf94b2c0a61b36889cd4c`, the same data SHA-256, zero candidate IDs and the single ambiguous ID `1`.
- Every command reported `DATABASE_CHANGED=NO`, `EMAIL_SENT=NO` where applicable, and `PRODUCTION_ACCESSED=NO`. No runtime file, business row, customer-visible event, email, document, configuration, container, or production resource changed.

## Staging apply and verify — 2026-08-21

- Immediately before apply, the helper identity still matched reviewed SHA-256 `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`, `root:root 0755`, size `34812`.
- The helper repeated environment, artifact, baseline and retained-plan freshness checks, then created and restore-validated the required backups before applying runtime code.
- `M061_R01_APPLY=PASS` with request `m061-r01-20260821T061643Z-20c457318419`, release `M061-R01-C01`, runtime commit `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`, artifact SHA-256 `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463`, and retained plan SHA-256 `5e228b7ad4426a88345fd08544ae0833eb8ce5bce74108e5d202f1ae17a29717`.
- Code backup: `/root/backup-erp-code-backups/m061-before-805cf3d-m061-r01-20260821T061643Z-20c457318419.tar.gz`, SHA-256 `623b81be3c357dd278b2bda95c0ba763b8cdfec4ab052ea910cd3ab7be572daf`.
- Database backup: `/root/backup-erp-db-backups/m061-before-805cf3d-m061-r01-20260821T061643Z-20c457318419.sql`, SHA-256 `a8917af9a1215e3e8656da786e0ee5850c825128b08cd07b4c47b73dd8a753b8`.
- The helper conservatively reported `DATABASE_CHANGED=YES` for the apply command path. The retained plan contained `CANDIDATES=0`, so no shipment-row repair or private per-row audit event was generated; ambiguous record `1` remained excluded and unchanged. `CUSTOMER_VISIBLE_EVENTS=0`, `EMAIL_SENT=NO`, and `PRODUCTION_ACCESSED=NO`.
- Independent `verify` returned `M061_R01_VERIFY=PASS`, `STATE=DEPLOYED`, matching release/runtime/artifact/plan pins, `CANDIDATES=0`, `AMBIGUOUS=1`, `DATABASE_MUTATED_BY_COMMAND=NO`, `EMAIL_SENT=NO`, and `PRODUCTION_ACCESSED=NO`.
- `rollback` and `status` were not invoked.

## Authenticated staging acceptance result — 2026-08-23

- Existing admin-authenticated staff views passed Arabic/English inspection at `1280px` and `390px` for Customer Center, shipments, projects, invoices and documents without page-wide horizontal overflow.
- Anonymous forged `socid`/`token` checks for projects, documents, shipment detail and direct download stopped at the login boundary; the direct download returned HTML rather than file bytes.
- External-customer authenticated checks could not run because no approved external staging session was available. Local-only test credentials were not reused, and no password, token or account was created or changed.
- Both QA customers have zero projects and zero approved invoices, so project-stage progress and invoice due/overdue/no-lines behavior lack representative runtime fixtures.
- The documented Mobile API route returned HTTP `501` with `API not found (failed to include API file)`, while the base API status route returned the expected unauthenticated HTTP `401`. Live web/Mobile parity is blocked.

Verdict: **CORRECTION_REQUIRED**. The next safe step is a separately planned local correction for Mobile API runtime dependency/route restoration and synthetic acceptance fixtures/session. Upload, staging mutation, repeated acceptance and production each remain separately approved gates.
