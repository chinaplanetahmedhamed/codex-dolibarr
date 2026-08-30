# M068-C01 Local HMAC Configuration Release Preparation — Completion Report

## Verdict

`LOCAL_PREPARATION_PASS_INTERNAL_REVIEWS_PASS`

The local-only `backup-erp` staging preparation is dependency-closed and fail-closed. It has not been uploaded, installed, or executed on any server.

## Control

- Parent commit: `d55439f5a0a3977bb92421b8f4d83fb3c4e56d40`
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
| `deploy/tools/install_backup_erp_m068_c01_hmac_helper.sh` | `6cfa83912e32590023cbea3b1a6dc1086c91d1bd771ddfbf3f93612fa57e553e` |
| `deploy/config/codex-deploy-backup-erp-m068-c01-hmac.sudoers` | `dc551abe7abad810f409b22abbe69c727a949893217579a4ba43f2a8d4c2c4f4` |

The four-entry checksum allowlist is `deploy/releases/m068-c01-hmac-configuration.sha256`.

## Prepared future installation surface

- Helper: `/usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac`, `root:root 0755`
- Non-web PHP tool: `/usr/local/libexec/chinaplanetos-m068-c01/m068_c01_portal_hmac_config.php`, `root:root 0644`
- Sudoers: `/etc/sudoers.d/codex-deploy-backup-erp-m068-c01-hmac`, `root:root 0440`
- Exact sudo commands: `preflight`, `apply`, `verify`, `rollback`, `status`
- Installer takes no arguments, invokes no helper command, accepts only the three pinned inbox sources, and transactionally restores exact predecessor files on failure.

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
- `test_m068_c01_hmac_installer.sh`: PASS — checksum pins, bounded config directory, three-file transaction, idempotency, injected rollback at every publication boundary, retained recovery evidence, helper not executed.
- `test_m068_c01_hmac_contract.sh`: PASS — five-command sudo surface, four-command PHP surface, exact single constant, staging-only topology, release manifest, installer-to-source pins, no arbitrary execution or production identifiers.
- Existing non-mutating M068 source tests: lifecycle source PASS, portal currency PASS, portal invitation security source PASS.
- PHP/Bash syntax and `git diff --check`: PASS.
- ShellCheck: not installed locally; equivalent Bash syntax, executable failure-path, and static contract tests passed.
- DB-backed M068 runtime acceptance was not run because it creates disposable business records and is outside this preparation approval. One invocation stopped at `DOLIBARR_ROOT_INVALID` before loading Dolibarr or accessing/changing a database.

## Independent reviews

- Internal Security: `PASS` on helper `752df39e6f9f7f679feba22afa08c3a94b4ccdfe00055984d6c327c5ef677e54`, config `20d82fec7a4b95c6a843bfcce4b0a27415c7215d7cb807df21a10e7dc714c2bf`, installer `6cfa83912e32590023cbea3b1a6dc1086c91d1bd771ddfbf3f93612fa57e553e`, and sudoers `dc551abe7abad810f409b22abbe69c727a949893217579a4ba43f2a8d4c2c4f4`; transaction cleanup and lock-release paths are fail-closed and non-recursive.
- Internal QA: `PASS` on the same candidate pins, canonical integer `1/0` fixture semantics, injected begin/commit/rollback failures, full local suites, and zero unstaged/untracked delta.
- Agent 007 Central's final independent post-freeze Security and QA reviews remain pending and must not trigger any file mutation without first revoking the freeze.

## Stopping and rollback gates

- This commit is preparation source only. Upload, helper installation, every helper command, staging configuration, M068 compare/preflight, and production each remain separately gated.
- A future installation rollback restores or removes only the three installed files and the bounded newly-created libexec directory; it never executes the helper.
- A future configuration rollback removes only the exact owned constant and retains the recoverable pre-secret full database backup. Full-database restore is manual emergency recovery only and is not exposed through sudo.
- Stop on any checksum, owner/mode, baseline, container/volume/port, database-name, backup/restore, entity, constant-state, output-disclosure, or recovery mismatch.

## Non-action evidence

`PUSH=NO UPLOAD=NO SERVER_CONNECTION=NO STAGING_COMMAND=NO HELPER_INSTALL=NO HELPER_EXECUTION=NO COMPARE=NO PREFLIGHT=NO STATUS=NO APPLY=NO MIGRATION=NO EMAIL=NO BUSINESS_RECORDS=NO PRODUCTION_ACCESS=NO`
