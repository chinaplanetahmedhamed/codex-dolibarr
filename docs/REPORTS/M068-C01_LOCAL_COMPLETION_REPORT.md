# M068-C01 Local HMAC Configuration Release Preparation — Completion Report

## Verdict

`LOCAL_CORRECTION_PASS_SECURITY_QA_PASS`

The original artifact from commit `01dfc869720c765882f109f2917bf7ff55db7a29` was uploaded under a separate staging approval but stopped before installation. This corrected local artifact has not been uploaded, installed, or executed on any server.

M068-C01-R01 corrects the installer-only staging compatibility defect found after commit `01dfc869720c765882f109f2917bf7ff55db7a29`: the host has no `php` binary, because the canonical Dolibarr PHP runtime is inside `erp-staging-dolibarr-1`. The corrected installer lint uses only that exact container, distinguishes runtime unavailability from syntax failure, and still publishes nothing on either failure. No host PHP package or symlink is introduced.

## Control

- Parent commit: `d55439f5a0a3977bb92421b8f4d83fb3c4e56d40`
- Correction parent: `01dfc869720c765882f109f2917bf7ff55db7a29`
- Branch: `codex/m068-website-lead-quotation-portal`
- Release: `M068-C01-R01`
- Target encoded in source: canonical `erp-staging` / `backup-erp` only
- Existing required baseline: `M062-R04`, runtime `f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b`, scope `3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67`, artifact `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c`
- Existing required M068 preflight helper SHA-256: `e340e79ce56e88ad27f066788a85f67aa55a1cc2eef2f247390d1eb83ffa3256`

## Frozen release pins

| Release file | SHA-256 |
|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m068_c01_hmac.sh` | `752df39e6f9f7f679feba22afa08c3a94b4ccdfe00055984d6c327c5ef677e54` |
| `deploy/tools/m068_c01_portal_hmac_config.php` | `20d82fec7a4b95c6a843bfcce4b0a27415c7215d7cb807df21a10e7dc714c2bf` |
| `deploy/tools/install_backup_erp_m068_c01_hmac_helper.sh` | `c45915fbf23542bc4c7b8840dabd5a068bc4ca555e13b89a6581216ec47d10b6` |
| `deploy/config/codex-deploy-backup-erp-m068-c01-hmac.sudoers` | `dc551abe7abad810f409b22abbe69c727a949893217579a4ba43f2a8d4c2c4f4` |

The four-entry checksum allowlist is `deploy/releases/m068-c01-hmac-configuration.sha256`.

## M068-C01-R01 correction files

- `deploy/tools/install_backup_erp_m068_c01_hmac_helper.sh` — replace host-PHP lint with exact canonical container-runtime lint and fail-closed error classification.
- `deploy/tests/test_m068_c01_hmac_installer.sh` — prove no host PHP use, exact container command, zero publication on inspect/exec/runtime or syntax failure, and existing transactional behavior.
- `deploy/tests/test_m068_c01_hmac_contract.sh` — pin the exact container lint contract and forbid regression to host-PHP lint.
- `deploy/releases/m068-c01-hmac-configuration.sha256` — pin the corrected installer.
- `docs/MISSIONS/M068-C01_BACKUP_ERP_HMAC_CONFIGURATION_HELPER.md` — record Ahmed's correction approval and acceptance boundary.
- `docs/REPORTS/M068-C01_LOCAL_COMPLETION_REPORT.md` — record trigger, files, tests, reviews, pins, and stopping gate.

The helper, PHP configuration tool, and sudoers content are unchanged.

## Prepared future installation surface

- Helper: `/usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac`, `root:root 0755`
- Non-web PHP tool: `/usr/local/libexec/chinaplanetos-m068-c01/m068_c01_portal_hmac_config.php`, `root:root 0644`
- Sudoers: `/etc/sudoers.d/codex-deploy-backup-erp-m068-c01-hmac`, `root:root 0440`
- Exact sudo commands: `preflight`, `apply`, `verify`, `rollback`, `status`
- Installer takes no arguments, invokes no helper command, accepts only the three pinned inbox sources, and transactionally restores exact predecessor files on failure.
- Installer syntax-checks the PHP configuration only through `docker exec -i erp-staging-dolibarr-1 php -l /dev/stdin`; host PHP is neither required nor accepted as the validation boundary.

## Security and recovery behavior

- The PHP tool accepts only `check`, `apply`, `verify`, or `rollback`; direct `status` is not a second PHP privilege surface.
- `apply` generates 48 random bytes inside the Dolibarr process and stores exactly one 64-character logical base64 value as hidden `CHINAPLANET_PORTAL_INVITATION_HMAC_KEY` for the active entity. Dolibarr encrypts the `_KEY` value at rest; verification decrypts it only in process and reports presence/length, never value.
- No secret argument, environment export, response field, stdout/stderr field, state field, manifest, source file, or post-secret database dump exists.
- Environment configuration, global rows, foreign-entity rows, duplicate rows, plaintext-at-rest values, wrong ownership metadata or length, an existing value, lock contention/release failure, baseline drift, topology drift, backup inability, and unexpected installed files stop closed.
- The exact row's note is a static M068-C01 ownership marker. No value digest is computed, stored, output, or retained in helper state; verification is limited to exact entity/name/type/visibility/marker, encrypted presence, and decrypted logical length.
- Before configuration, the helper uses database-root coverage to create a root-private full SQL backup while the key is absent, hashes and fsyncs it, restores it into a disposable database, verifies zero key rows across all entities, compares base tables plus views/routines/triggers/events and base-table data fingerprints, and drops the validation database. Apply cannot proceed unless all steps pass; any incomplete, unrestorable, key-containing, or otherwise unvalidated dump is deleted and the backup directory is fsynced before exit.
- Apply and rollback acquire a deterministic, non-secret MariaDB advisory lock scoped to this constant/entity before beginning a transaction and exact-name, all-entity `FOR UPDATE` absence/ownership read. Canonical Dolibarr 23.0.3 `DoliDB::begin()`, `commit()`, and `rollback()` semantics were verified locally: falsy integer `0` is failure and is now rejected explicitly, with transaction cleanup and verified lock release attempted before exit. Both locks are retained through the constant API write and post-write verification; contention or release failure stops closed. This closes the helper precheck-to-write overwrite window.
- State contains only release/status/request/entity, the recorded absent prestate, database-backup path/hash, and timestamp.
- Exact rollback requires the deployed state, intact pre-secret backup pin, exact entity/name/type/visibility/static M068-C01 ownership marker, encrypted presence, and valid decrypted length. It transactionally deletes only that constant and verifies absence. It does not overwrite intervening business data with a full database restore.
- Unexpected post-write failure automatically attempts exact rollback. A failed recovery is retained and reported as `RECOVERY_REQUIRED`.

## Local evidence

- `test_m068_c01_hmac_config.php`: PASS — one constant, advisory-lock contention denial, locked all-entity absence/ownership gate, canonical integer `1/0` DoliDB semantics, explicit begin/commit/rollback integer-zero injections with cleanup/lock-release evidence, encrypted-at-rest compatibility, server generation, no disclosure or value digest, presence/length verification, ownership-bound exact rollback, global/foreign/duplicate conflict denial, and set/delete failure paths.
- `test_m068_c01_hmac_helper.sh`: PASS — read-only preflight/status, database-root full backup contract, restored-key absence gate, deletion of every unvalidated dump, view-safe complete-object restore fingerprint, apply/verify/rollback, drifted-length denial, backup/restore/config lost-ack failure paths, auto-rollback, no secret in output/state/backup.
- `test_m068_c01_hmac_installer.sh`: PASS — checksum pins, bounded config directory, exact container PHP lint, host-PHP rejection, inspect-unavailable, exec-runtime-failure, and syntax-failure zero-publication paths, three-file transaction, idempotency, injected rollback at every publication boundary, retained recovery evidence, helper not executed.
- `test_m068_c01_hmac_contract.sh`: PASS — five-command sudo surface, four-command PHP surface, exact single constant, staging-only topology, release manifest, installer-to-source pins, no arbitrary execution or production identifiers.
- Existing non-mutating M068 source tests: lifecycle source PASS, portal currency PASS, portal invitation security source PASS.
- PHP/Bash syntax and `git diff --check`: PASS.
- ShellCheck: not installed locally; equivalent Bash syntax, executable failure-path, and static contract tests passed.
- DB-backed M068 runtime acceptance was not run because it creates disposable business records and is outside this preparation approval. One invocation stopped at `DOLIBARR_ROOT_INVALID` before loading Dolibarr or accessing/changing a database.

## Independent reviews

- M068-C01-R01 Security: `PASS` on the frozen six-file candidate. The exact staging-only container runtime, exit-code classification, zero-publication failure paths, unchanged five-command sudo surface, non-execution of the helper, and manifest pins were verified; the snapshot remained unchanged throughout review.
- M068-C01-R01 QA: `PASS` on the same frozen candidate. All four C01 suites, three existing M068 source suites, syntax checks, manifest verification, cached/full diff checks, zero-publication runtime/syntax failures, idempotency, and transactional restoration passed; the snapshot remained unchanged throughout review.

## Stopping and rollback gates

- This commit is preparation source only. Upload, helper installation, every helper command, staging configuration, M068 compare/preflight, and production each remain separately gated.
- A future installation rollback restores or removes only the three installed files and the bounded newly-created libexec directory; it never executes the helper.
- A future configuration rollback removes only the exact owned constant and retains the recoverable pre-secret full database backup. Full-database restore is manual emergency recovery only and is not exposed through sudo.
- Stop on any checksum, owner/mode, baseline, container/volume/port, database-name, backup/restore, entity, constant-state, output-disclosure, or recovery mismatch.
- Staging trigger state observed before this local correction: `/usr/local/libexec` exists as `root:root 0755`; the C01 helper, configuration directory, and sudoers target remained absent after both fail-closed installer attempts.
- Local rollback is an exact revert of the single M068-C01-R01 correction commit after it exists. No server rollback is needed because this corrected artifact has not been uploaded or installed.

## Data, configuration, permissions, and parity

- Database/schema/business records: no impact.
- Secret/configuration value: not generated or inspected.
- Sudo command surface: unchanged at the same five fixed commands.
- Admin ERP: `NOT_APPLICABLE` — release tooling only; no UI/runtime feature change.
- Customer Center: `NOT_APPLICABLE` — no customer-visible behavior or data change.
- Mobile App: `NOT_APPLICABLE` — no API, terminology, navigation, or customer contract change.

## Non-action evidence

Under the M068-C01-R01 local-correction approval: `PUSH=NO UPLOAD=NO SERVER_CONNECTION=NO STAGING_COMMAND=NO HELPER_INSTALL=NO HELPER_EXECUTION=NO COMPARE=NO PREFLIGHT=NO STATUS=NO APPLY=NO MIGRATION=NO EMAIL=NO BUSINESS_RECORDS=NO PRODUCTION_ACCESS=NO`.

## M068-R04 C01 compatibility correction — 2026-08-31

- Executable release: `M068-C01-R03`; immutable ownership-state release: `M068-C01-R01`.
- The existing state is not migrated or rewritten. It is accepted only when its exact release, status, entity, absent prestate, private backup path/hash, and current owned encrypted-key presence/length all validate; any different release fails closed.
- Helper SHA-256: `22f4413e10059350467191bbe462144e710ab5e297af9a89e857a070c123ec6d`; unchanged PHP config SHA-256: `20d82fec7a4b95c6a843bfcce4b0a27415c7215d7cb807df21a10e7dc714c2bf`; installer SHA-256: `5701981d7b2e64656fa37c01b3e4c655754896e2e71845f57bff34c05e7f6aa1`; unchanged sudoers SHA-256: `dc551abe7abad810f409b22abbe69c727a949893217579a4ba43f2a8d4c2c4f4`.
- The helper pins only the R04 M068 successor helper SHA-256 `649888ffca725bcf8cc1406a0ff383dffe2688754e7e720ecd5f64cfac053c2c`; the installer accepts only the currently installed R02 helper SHA-256 `5b867120ff1b6aaaaa1303f133bc23125f1d200a0bbed9caad2fc14a193b826f` as predecessor.
- All four C01 suites pass, including a state written as `M068-C01-R01` and verified by executable release `M068-C01-R03`. No secret value or digest is output or stored in Git.
