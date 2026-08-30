# M068-C01 — Backup-ERP Portal Invitation HMAC Configuration Helper

## Mission control

- **Mission ID:** M068-C01
- **Type:** CORRECTION / SECURITY / STAGING_PREPARATION
- **Status:** ARCHITECT_ACCEPTED
- **Owner:** Agent 007
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-30
- **Approved wording:** `APPROVE M068 FOR LOCAL BACKUP-ERP STAGING HMAC-CONFIGURATION RELEASE PREPARATION ONLY. PREPARE AND COMMIT A CHECKSUM-PINNED, FAIL-CLOSED, STAGING-ONLY HELPER, INSTALLER, AND SUDOERS THAT GENERATE CHINAPLANET_PORTAL_INVITATION_HMAC_KEY SERVER-SIDE, NEVER OUTPUT OR EXPORT THE SECRET, WRITE ONLY THE EXACT ENTITY-SCOPED DOLIBARR CONSTANT AFTER VERIFYING IT IS ABSENT, CREATE A RECOVERABLE DATABASE BACKUP, VERIFY CONFIGURATION BY PRESENCE/LENGTH ONLY, SUPPORT EXACT ROLLBACK, AND INCLUDE LOCAL SECURITY, QA, AND FAILURE-PATH TESTS. NO PUSH, NO UPLOAD, NO SERVER CONNECTION OR MUTATION, NO HELPER INSTALLATION OR EXECUTION, NO COMPARE, PREFLIGHT, OR STATUS, NO EMAIL OR BUSINESS RECORDS, AND NO PRODUCTION ACTION.`
- **Source branch:** `codex/m068-website-lead-quotation-portal`
- **Preparation parent:** `d55439f5a0a3977bb92421b8f4d83fb3c4e56d40`
- **Correction parent:** `01dfc869720c765882f109f2917bf7ff55db7a29`
- **Correction approval date:** 2026-08-30
- **Correction approval:** `APPROVE M068-C01-R01 FOR LOCAL INSTALLER CORRECTION ONLY. VALIDATE PHP USING THE CANONICAL BACKUP-ERP CONTAINER RUNTIME, REGENERATE PINS, TEST, REVIEW, AND CREATE ONE LOCAL COMMIT. NO UPLOAD, INSTALLATION, HELPER EXECUTION, SECRET GENERATION, STAGING MUTATION, OR PRODUCTION ACTION.`

## Defect and business outcome

The approved M068 `compare` stopped safely on canonical `backup-erp` with `HMAC_SECRET_MISSING`. Prepare a checksum-pinned, mission-specific helper that can later configure the portal invitation HMAC prerequisite without exposing the secret or broadening staging authority. This Mission does not authorize using the helper.

The first approved installation attempt from commit `01dfc869720c765882f109f2917bf7ff55db7a29` also stopped safely before publication. The staging host intentionally has no host-level `php` command, while the canonical `erp-staging-dolibarr-1` container owns the Dolibarr PHP runtime. M068-C01-R01 corrects only the installer lint boundary: it must validate the pinned PHP configuration through `docker exec -i erp-staging-dolibarr-1 php -l /dev/stdin`, distinguish unavailable runtime from invalid syntax, and never install host PHP or execute the C01 helper.

## Frozen design

- The server-side key name is `CHINAPLANET_PORTAL_INVITATION_HMAC_KEY`.
- A future approved `apply` generates 48 random bytes inside the canonical staging Dolibarr container and stores the base64 value as a hidden, entity-scoped Dolibarr configuration constant.
- The secret is never accepted as an argument, environment input, request file, artifact member, Git content, stdout/stderr field, or audit/state value.
- Output may report only `ABSENT`, `CONFIGURED`, `OWNED`, entity ID, command state, and hashes of non-secret helper/install files or prestate manifests.
- Apply is allowed only when the environment value and every row in every entity for the exact constant are absent. An existing, invalid, duplicate, global, foreign-entity, or unowned value stops the operation; it is never overwritten or adopted.
- The database write is exactly one entity-scoped constant. Dolibarr's native `_KEY` handling must encrypt the value at rest. No marker constant, second configuration value, schema row, or environment export is created.
- The same row's ownership note is one exact static M068-C01 marker. No value digest is computed, stored, output, or copied into helper state; verification is limited to the exact entity/name/type/visibility/marker plus encrypted presence and decrypted logical length.
- Before that write, a database-root full SQL backup is created root-owned mode `0600` under a root-owned mode `0700` backup directory. Its SHA-256 is retained in non-secret state only after a disposable validation database restores with matching base-table data/schema plus view, routine, trigger, and event definitions. An incomplete, unrestorable, key-containing, or otherwise unvalidated dump is deleted and the backup directory is fsynced before the helper exits.
- The restored backup is explicitly queried across all entities for zero key rows. The backup is accepted only while the exact key is absent, so a concurrent insertion cannot export the key into the retained dump. No new full dump is taken after configuration.
- Rollback is ownership-bound by the exact constant name, entity, hidden string metadata, static M068-C01 marker, logical length, deployed state, and intact pre-secret backup pin. It transactionally removes only that owned constant and verifies the recorded absent prestate; it never restores the full database automatically over intervening data.
- Database configuration writes use Dolibarr's constant APIs inside a transaction. Canonical Dolibarr 23.0.3 DoliDB transaction methods are treated as truthy-success/falsy-error APIs, so integer `0` from begin, commit, or rollback stops closed and cleanup still attempts verified advisory-lock release. A deterministic, non-secret MariaDB advisory lock scoped to this constant/entity is acquired before the transaction and exact-name all-entity `FOR UPDATE` absence/ownership read, then held through the write and post-write verification. Lock contention or release failure stops closed. No schema, module state, permission, customer, quotation, order, document, email, runtime file, container, Compose, proxy, DNS, or production change is permitted.

## Prepared command surface

The future root-owned helper may expose exactly:

1. `preflight` — read-only canonical topology and key-state assessment.
2. `apply` — future separately approved full pre-secret database backup/restore test and creation of the one absent entity-scoped key.
3. `verify` — read-only ownership metadata, presence/length, state, and backup-pin verification.
4. `rollback` — future separately approved removal of only the exact owned constant, restoring the recorded absent prestate.
5. `status` — read-only preparation/deployment state.

No command is authorized by this local-preparation approval.

## In scope

- One fixed helper, one fixed non-web PHP configuration tool, an exact sudoers file, and a transactional checksum-pinned installer.
- Exact container-runtime PHP lint in the canonical `erp-staging-dolibarr-1` container, with fail-closed `CONFIG_RUNTIME_INVALID` and `CONFIG_SYNTAX_INVALID` paths and no host-PHP dependency.
- Local fixtures and tests for absence, generation, non-disclosure, presence/length verification, unowned/invalid/duplicate refusal, backup creation/restore gating, injected failure cleanup, backup/state integrity, exact rollback, sudo surface, installer rollback, and recovery evidence.
- Security and QA read-only review of the frozen local evidence.
- A local completion report and local commit.

## Out of scope

- Upload, installation, VPS/SSH access, helper invocation, staging configuration, compare, preflight, status, apply, verify, rollback, migration, mail, business records, browser acceptance, push, pull request, staging release, and production.
- Storing or transporting a real secret outside the future server-side generation path.
- Modifying the existing installed M068 preflight helper or its three-command sudo surface.

## Security and stopping gates

- Stop if the target is not canonical `erp-staging`, if the M062-R04 baseline identity or the installed M068 preflight helper pin changes, if any production identifier is selected, or if the secret exists in the environment, globally, in another entity, ambiguously, unencrypted, or outside exact C01 ownership.
- Stop if the pre-secret full database backup is empty, unhashed, not private, or fails its disposable restore/fingerprint test; if output/state/backup contains the generated secret; if rollback cannot prove ownership; or if an installer fault cannot restore the previous exact files and modes.
- Staging upload, installation, configuration apply, M068 compare/preflight, and production each require later separate exact approvals.

## Acceptance

- Bash/PHP syntax, source contract, functional single-constant lifecycle, full-backup/restore-test contract, failure injection, installer transaction/rollback, exact sudo surface, and `git diff --check` pass locally.
- Installer tests prove host PHP is never called, exact container/runtime arguments are enforced, inspect/runtime loss, exec/runtime loss, and PHP syntax failure publish no target, and the helper remains unexecuted.
- Security and QA return PASS on the final unchanged evidence.
- Final report records exact helper, configuration tool, sudoers, and installer SHA-256 values and confirms that no server was accessed.
