# M061-R01 release preparation report

## Verdict

**DIAGNOSED — C01 READY LOCALLY AFTER DEVOPS/SECURITY/QA PASS**

Agent 007 completed the approved local release-preparation gate on 2026-08-19. The exact M061 runtime commit is packaged with fixed runtime, support and M060 baseline manifests. A staging-only helper, seven-command sudoers surface and checksum-pinned installer are prepared and locally validated. Nothing was uploaded or installed.

## Source and staging baseline

- Branch: `codex/m061-customer-portal-trust`.
- Runtime commit: `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`.
- Parent source baseline: `38493b775889f77015db174fb3e5c79d2b493669`.
- Canonical staging runtime observed read-only: M060 `4cb98b36eedfe932097f66822f48837b423d2337`, state `DEPLOYED`.
- Source verification proved the 28 affected M061 prestate files are identical between `4cb98b36...` and the M061 parent; the new repair library is correctly marked `ABSENT`.
- `https://backup-erp.visiontrading.biz/` returned HTTP 200. This is routing evidence only, not authenticated feature acceptance.

## Frozen package

- Artifact: `/private/tmp/m061-customer-trust-repair-805cf3d.tar.gz`.
- Artifact SHA-256: `fd834e5e5421b77ecc9db1b28e95ae7ee0676317a7714b8ca3b8f954dd7b3bac`.
- Contents: 28 runtime files, 8 support files and 3 manifests; 39 regular files total, with no directory, symlink, absolute, parent-traversal or macOS metadata entry.
- Runtime manifest SHA-256: `66306449b0efac20054fe658f2ca9801513a234cb17c25b4235a3a86e149cc3b`.
- Support manifest SHA-256: `d3094ee745e8f921a3b8cc081b48c32a506d076b23835f1442ea31cb62f30e1a`.
- M060 baseline manifest SHA-256: `72a8a36fc7f5d0fd8063dd49bcf7c5ead60bf0ca9bbdc49d3221e42efa91c0cb`.

## Restricted release controls

- Helper: `deploy/tools/chinaplanetos_backup_erp_m061_r01_release.sh`; SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7`.
- Sudoers: `deploy/config/codex-deploy-backup-erp-m061-r01.sudoers`; SHA-256 `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`.
- Installer: `deploy/tools/install_backup_erp_m061_r01_release_helper.sh`; SHA-256 `c10f6270159a7aba881601f9fc518ce9a06f12df7847426b1ef0f37689652af5`.
- Exact commands: `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback`, `status`.
- Fixed target: `erp-staging-dolibarr-1`, entity `1`, canonical custom/DB volumes and staging-only localhost ports.
- Apply requires a retained dry-run data hash, unique request ID, unique code backup, full database backup and restore rehearsal before code/data mutation.
- Repair execution is transaction-scoped, private-audit-only and email-free. Rollback uses the exact retained plan and fails on data drift.
- Helper verify deliberately reports `AUTHENTICATED_ACCEPTANCE=PENDING_EXTERNAL_GATE`; it does not treat HTTP success as customer acceptance.

## Local validation

- Helper and installer `bash -n`: PASS.
- PHP lint for the helper contract test: PASS.
- `test_m061_r01_release_helper_contract.php`: PASS; seven commands and no production identifier.
- `test_m061_r01_release_package.sh`: PASS; exact 39-file allowlist, runtime blobs, baseline blobs and artifact SHA.
- Artifact allowlist/count/hash inspection: PASS.
- M060 baseline hash verification against `4cb98b36...`: PASS.
- `git diff --check`: PASS at preparation time.

## Data, configuration and environment impact

- Local source: release manifests, staging helper, sudoers source, installer, contract test and this report added.
- Staging runtime/database/configuration/business data: unchanged.
- Staging inbox: the four pinned release files were uploaded with verified hashes and `codex-deploy:codex-deploy 0644` metadata.
- Staging helper/sudoers: installed and independently verified after explicit root-action confirmation; no release command executed.
- Email/customer-visible events: none.
- Production: not accessed or changed by M061-R01.

## Remaining risk and next approval

Authenticated staging UI/security acceptance is still pending because M061 is not deployed. The next safe gate is separate approval for staging `compare/preflight/dry-run`. `apply`, authenticated acceptance and any production action remain distinct later approvals.

## Initial staging upload result — 2026-08-19

Ahmed approved the previously stated upload-and-install-only gate with `ok push to backup`. Agent 007 uploaded the exact four frozen files to `/home/codex-deploy/staging-inbox`. Server-side hashes matched the artifact, helper, sudoers and installer pins, and all files are `codex-deploy:codex-deploy 0644`.

At this initial upload gate the restricted account had no M061-R01 sudo rule, and the installer invocation was denied with `sudo: a password is required` before execution. No release command or runtime/data change occurred. The subsequent explicitly confirmed root installation supersedes that temporary blocker and is recorded below.

## Staging helper installation result — 2026-08-19

After Ahmed's explicit action-time confirmation, the pinned installer ran through the existing Termius root session and returned `M061_R01_INSTALL=PASS`. Independent checks confirmed:

- helper `root:root 0755`, SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7`;
- sudoers `root:root 0440`, SHA-256 `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`;
- `visudo -cf`: `parsed OK`;
- restricted account: exactly seven fixed commands for `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback`, and `status`;
- deployment/database/customer data/email: unchanged;
- M061 release commands executed: none;
- production: not accessed or changed.

The next gate remains a separate approval for staging `compare/preflight/dry-run` only.

## Staging compare gate — 2026-08-19

Ahmed approved `compare`, `preflight` and `dry-run` on `backup-erp` only. The restricted `compare` command inspected the exact pinned artifact and all 28 runtime paths. It reported `DRIFT_COUNT=1`: `custom/chinaplanetcustomercenter/projects.php` did not match either the reviewed M060 baseline SHA-256 `3ca0889a46b875ec21a4ca6fdc4af45d75a5e2bcbca85b941dad5e8f98b96ea2` or the frozen M061 release SHA-256 `7c608f7dc7b129fa9aeaad2d727bb4776adbb41a03f4c3aea97a3331723af307`.

The sequence therefore stopped before `preflight` and `dry-run`. No plan, candidate IDs, ambiguous IDs, runtime change, database change, customer-visible audit event or email was produced. Production was not accessed. The next safe action is a read-only root-level identification and review of the current staging file, followed by a newly pinned drift-resolution artifact/helper if appropriate; continuing with the existing release is prohibited.

## Root diagnosis result — 2026-08-19

Ahmed approved root read-only diagnosis of the drifted staging file. Root SFTP showed `custom/chinaplanetcustomercenter/projects.php` as a regular `0444` file, `12678` bytes, timestamped `2026-07-18 23:25`. A read-only downloaded copy has SHA-256 `0e3878ffcc3122ea13f8e5b409a7dfebef50cb46673f2dca4134b8ebd5fb5cc4` and is byte-identical to the known pre-M054 Git blob.

This is not an unknown hotfix. M054 changed the include to canonical `custom/cpkanban/lib/projectsync.lib.php`, but its completion report explicitly records that staging was not accessed. The later M060 runtime manifest did not deploy `projects.php` or either project-sync library path. M061 release preparation incorrectly promoted the M060 source commit into a whole-runtime baseline claim for this file.

The diagnosis found a second, release-blocking issue: live staging does not contain `custom/cpkanban/lib/projectsync.lib.php`, yet frozen M061 `projects.php` requires it for internal users, and the M061 artifact does not include it. Re-pinning only the drifted file would be unsafe. A correction package must include that canonical library with `ABSENT` baseline, use the verified live `projects.php` hash as its baseline, and regenerate/review every checksum and count assertion before any new staging command approval.

No server file, application runtime, database, configuration, document, container, business record, event, email, or production resource was changed or accessed by the diagnosis.

## M061-R01-C01 local correction

The separately approved local-only correction is documented in `M061_R01_C01_RELEASE_PREPARATION_REPORT.md`. It adds the missing canonical CP Kanban dependency, uses the verified live projects-page baseline, creates a new 40-file artifact and adds an exact-old-state installer upgrade path. Independent DevOps/VPS, Security and QA reviews returned `PASS` against frozen commit `90f23d4d13b2e164eb75963431d7d10f57830955`. The old artifact/helper remain installed on staging and must not be used. Upload, installation and all staging commands require fresh exact approvals.
