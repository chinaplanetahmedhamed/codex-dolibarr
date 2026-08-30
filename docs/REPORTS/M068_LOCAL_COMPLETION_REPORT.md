# M068 Local Completion Report

Date: 2026-08-30

Mission: Website Lead, Quotation Request, and Customer Center Lifecycle

Contract: `cpo.catalog-order.v2` / `schema_version=2`
Approval used: `APPROVE M068 AS WRITTEN FOR LOCAL DOLIBARR IMPLEMENTATION ONLY.`

## Control record

- Repository baseline: `origin/main` at `057af0f205f819c685869b3ee6aa1aea31ea549e` when the clean worktree was created.
- Branch: `codex/m068-website-lead-quotation-portal`.
- Worktree: `/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m068-website-lifecycle`.
- Agent 007 remained the sole Dolibarr code-writing owner. Independent agents were read-only reviewers.
- The dirty/diverged permanent checkout was not switched, pulled, merged, reset, cleaned, or edited.
- No push, deployment, staging/production mutation, real-customer contact, or real-customer test data was used.
- The implementation commit is the local commit containing this report; its exact hash is recorded in the final handoff.

## Delivered locally

- The existing secure website intake now accepts the frozen v2 contract while preserving v1 behavior and strict idempotency. It retains every submitted line and the immutable customer-safe price/packing snapshot.
- Qualification provides normalized, same-entity contact and Third Party email/phone suggestions. Exact or ambiguous matches persist an audited stopping state; staff must explicitly link an active Third Party/contact. Reviewed lead creation creates a Prospect/Lead plus primary contact only when no exact match exists.
- Working quotation lines are separate from immutable intake. Staff can map products and record only complete, approved-source packing. No missing packing, price, supplier cost, or margin is guessed.
- The standard draft `Propal` flow uses the exact qualified customer/contact, selling prices only, explicit currency, separate private staff notes and approved public descriptions, and remains staff-owned for validation, sending, acceptance, and order conversion.
- Customer Center adds separate Orders and Quotation Requests journeys. Request references, submitted lines, approved replies/files, and explicitly customer-visible proposals are scoped by `entity + authenticated user fk_soc`. Public URLs use the website reference rather than a database row ID.
- Invitation uses an expiring, hashed, one-time token; checks email/login conflicts; issues at most 3 per request/email per hour; and limits activation to 5 attempts per token/IP-HMAC in a rolling 15-minute window. Dolibarr authenticates the normalized email through its native email branch while retaining a framework-safe internal login that is never customer-facing.
- Password reset uses the same one-time password-set mechanism and a non-enumerating public response. It never grants Core reset right 343, never emails a plaintext password, and cannot create or restore portal membership: the exact active external user must already have current Customers Portal membership and right 500201.
- Provisioning of rights, membership, landing page, user state, and invitation is fail-closed and transaction-safe. Failed new-user provisioning removes the incomplete user.
- Approved publications use allowlisted formats, bounded size, randomized customer tokens, canonical path containment, private filesystem modes, and tenant checks.
- Arabic RTL and English LTR labels, logical navigation arrows, responsive 360/390px source rules, 44px touch targets, 16px form inputs, and visible keyboard focus are present. Customer Center French remains explicitly out of M068 scope; public React French remains owned by the React mission.
- Mobile portal parity is recorded as pending/out of scope; React remains public discovery/intake and authenticated operations remain in Dolibarr Customer Center.

## Local verification evidence

- PHP syntax: all changed and new PHP files passed.
- `M068_SCHEMA_CHECK=PASS`; the additive local schema was previously applied with `GRANTS=EXPLICIT BUSINESS_RECORDS=NONE`.
- `M068_LIFECYCLE_SOURCE=PASS READY_UNITS_KEY=unitsPerCarton`.
- `M068_INVITATION_SECURITY_SOURCE=PASS CUSTOMER_LOGIN=email CORE_LOGIN=internal ISSUE_LIMIT=3/hour ATTEMPT_LIMIT=5/15m STORAGE=hash-only`.
- `M068_PORTAL_CURRENCY=PASS FOREIGN=multicurrency_total BASE=total_ht ISO=explicit`.
- Full synthetic runtime passed intake/dedupe, ready and incomplete lines, safe stop/link/create, packing completion, draft proposal, native proposal acceptance and draft-order conversion, tenant/file denial, invite, email authentication, activation, password reset, no-regrant, and cleanup:
  - `M068_RUNTIME=PASS ... RESET=one-time-email+no-regrant ...`
  - `M068_RUNTIME_CLEANUP=PASS SYNTHETIC_ONLY=YES`
- Customer portal scope integration passed, including forged-customer denial.
- Backward/regression checks passed for M003, M031, M032, M043 source/runtime, M045, M053, and M055 contact/reply behavior.
- `git diff --check` passed.

## Independent local reviews

- Security: PASS, including the final non-provisioning reset path.
- Customer Center/tenant lifecycle: PASS.
- UI/UX and Arabic/English source acceptance: PASS.
- Accounting/commercial proposal handling: PASS.
- QA/backward compatibility: PASS.

Earlier correction-required findings were resolved before these final verdicts: the v2 `unitsPerCarton` key, durable stopping state, company identity matching, non-secret invitation result, email authentication, rolling limits, transaction-safe provisioning, private/public proposal text separation, multicurrency totals, v2 promotion guards, visibility invariant, public-reference URLs, translated UI, and reset no-regrant control.

## Remaining gates and stopping conditions

Local source and synthetic runtime acceptance are complete. The following remain deliberately untested until a separate staging approval:

- real browser rendering and keyboard/touch behavior in authenticated Arabic and English sessions at desktop, 390px, and 360px;
- staging mail transport and receipt of invitation/reset links by a disposable test mailbox;
- logout, email login, reset, resend/revoke, file/photo display/download, and cross-customer denial in the deployed staging runtime;
- deployed migration/preflight, active runtime SHA, and rollback rehearsal.

Stop if staging preflight finds a schema difference, missing HMAC secret, ambiguous existing email/login, missing portal group/right, mail failure, tenant leak, file containment failure, mobile/RTL blocker, or any mismatch with `cpo.catalog-order.v2`. Production remains a later, separate approval after staging acceptance.

Risk after local completion: **medium** until authenticated browser and mail delivery acceptance are proven on staging. The local security and data-integrity risk is reduced by fail-closed qualification, publication, provisioning, and reset controls.

Rollback is code-first: revert the local M068 commit or discard the clean worktree/branch. The local migration is additive; leaving unused columns/tables is the safe rollback. Destructive local schema removal requires a separate exact cleanup approval. No staging or production rollback action exists because neither environment was touched.

## Local staging release-package preparation

Approval used: `APPROVE M068 FOR LOCAL STAGING RELEASE-PACKAGE PREPARATION ONLY. Create and commit from 32e82ffbd82248f2a84f468172195267fb6d37a7 the exact runtime/support manifests, metadata-free checksum-pinned artifact, M068 preflight-only helper exposing compare/preflight/status only, sudoers, installer, and local Security/QA tests. NO PUSH, NO UPLOAD, NO HELPER INSTALLATION, NO STAGING COMMAND OR MUTATION, AND NO PRODUCTION ACTION.`

- The worktree was clean at exact implementation commit `32e82ffbd82248f2a84f468172195267fb6d37a7` before preparation edits. The dirty/diverged permanent checkout remained untouched.
- The deterministic USTAR/gzip staging candidate is `/private/tmp/m068-website-lifecycle-32e82ff.tar.gz`, SHA-256 `369ba510238edc1d67bf8f2d64b14a51c680070b717806fc4029527e2ff19e46`. It has 35 regular allowlisted members, canonical root/root IDs, mode `0644`, a fixed commit timestamp, no PAX headers, no symlinks/hardlinks, no Apple metadata, and reproducibly rebuilds byte-for-byte.
- Runtime manifest: 26 files, SHA-256 `5b3b8ef2e1cc714860df71b089d4dda0e73cc5eb58b11c41add936144b0fb15a`.
- Support manifest: 5 files, SHA-256 `f8c02594f11b3f0b377d42c39bb636fd27339a5230e0f434d27d4428cf389ad9`.
- M062-R04 baseline manifest: 26 entries (12 present and 14 absent), SHA-256 `dbcd63d986dc3862419e806c6b900d8e5fcc3cddf7909739ea8df16ae888d3cf`.
- Unchanged dependency manifest: 5 exact files, SHA-256 `926277d8cf5f89cdf1724b46ab83ed8949691e918681c5e3797fd7c28dac50b7`.
- Preflight-only helper SHA-256: `e340e79ce56e88ad27f066788a85f67aa55a1cc2eef2f247390d1eb83ffa3256`. Its only accepted commands are `compare`, `preflight`, and `status`; it has no apply, verify, rollback, dry-run, arbitrary-argument, request, state-creation, backup, schema-write, or runtime-write surface.
- Exact three-line sudoers SHA-256: `d939e20162ec13fa7a125bbd9ec40128a05615444140ef920aa0fcae8e27e018`.
- Checksum-pinned transactional installer SHA-256: `926189d3bb75115c55eb8f780838302ae0fb6032b001511bb81545ede5bf0ca3`. It stages both files, validates helper syntax and sudoers, atomically publishes exact pins, restores the prior exact pair with the required `0755`/`0440` metadata on injected failure, retains a private recovery copy and reports `RECOVERY_REQUIRED` if automatic restoration itself fails, requires existing non-symlink system target directories, and does not execute the helper.
- The helper is pinned only to canonical Compose project `erp-staging`, containers `erp-staging-dolibarr-1`, `erp-staging-mariadb-1`, and `erp-staging-mailpit-1`; volumes `erp-staging_erp-staging_custom`, `erp-staging_erp-staging_docs`, and `erp-staging_erp-staging_mariadb_data`; and localhost ports `18088`, `11025`, and `18025`.
- It requires the deployed M062-R04 state pins `f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b`, `3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67`, and `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c`. Read-only readiness covers exact code/dependency baseline, additive-schema status, HMAC presence without output, Mailpit-only SMTP transport (`mailpit:1025`) without sending, Customer Center enablement, the exact Customers Portal group/right, and absence of forbidden Core reset right `343` from that group.
- Privileged artifact intake uses a 2 MiB hard limit and a descriptor-based `O_NOFOLLOW` bounded copy before hashing. The container candidate uses an unpredictable root-only `mktemp` directory under `/root`, verifies root ownership/mode `0700` before and after copy, and removes only the fixed safe pattern.
- Local verification passed: PHP lint across all runtime/support PHP files; Bash and Python syntax; the three M068 source/unit checks; deterministic artifact rebuild and byte comparison; exact Git source/baseline/dependency closure; release-contract checks; corrupt, oversized, and symlink artifact denial; runtime-drift, dependency-drift, baseline-state, forbidden-command, and arbitrary-argument denial; installer checksum, idempotency, rollback metadata, recovery-backup retention, and rollback-after-each-publish-step tests; and `git diff --check`.
- Independent Security and QA verdicts for this release package are recorded as PASS after their final read-only review. Neither reviewer edited files.

This preparation did not push or upload anything, connect to any server, install or invoke a server helper, create staging request/state/backup files, run a staging command, migrate any database, send mail, create business records, restart a service, or access production. The installer was exercised only against a disposable local filesystem fixture.

Stopping gate: do not upload or install this package, and do not invoke `compare`, `preflight`, or `status`, without the next exact approval. Stop on any checksum mismatch, non-M062-R04 baseline, topology/binding difference, runtime or dependency drift, unsafe archive member, missing HMAC/mail/module/group/right prerequisite, unexpected schema-check result, secret/customer-data output, or inability to validate without mutation. Because nothing was installed or deployed, rollback is to revert the preparation commit or remove the disposable local artifact; no staging/production rollback is authorized or required.

Residual risk remains **medium**: source/package controls are locally proven, but the staging topology, secrets-by-presence, mail configuration, schema status, authenticated browser behavior, and disposable-mailbox lifecycle have not been revalidated with this package. Production remains entirely out of scope.

## Next exact approval gate

Use this phrase only when ready to allow staging filesystem preparation. This gate still does not authorize helper invocation or application changes:

`APPROVE M068 FOR BACKUP-ERP STAGING UPLOAD AND CHECKSUM-PINNED PREFLIGHT-ONLY HELPER INSTALLATION ONLY. DO NOT RUN COMPARE, PREFLIGHT, OR STATUS; DO NOT APPLY OR MIGRATE; DO NOT SEND EMAIL OR CREATE BUSINESS RECORDS; AND NO PRODUCTION ACTION.`

## M068-R02 full staging release addendum — 2026-08-30

After the earlier preflight-only package and the separately completed M068-C01 HMAC configuration, Ahmed approved completing the remaining M068 chain on **backup-ERP staging only**. The R02 package upgrades the same restricted helper path from its exact pinned predecessor; it does not create a parallel privileged surface.

- Deterministic artifact: `m068-website-lifecycle-32e82ff.tar.gz`, 36 allowlisted regular files, SHA-256 `449bb83039533bbd3ebb9eb8b69a6062cd0e800c8b071bfc5294a0cb2ce22dcd`.
- Runtime manifest: 26 files, SHA-256 `5b3b8ef2e1cc714860df71b089d4dda0e73cc5eb58b11c41add936144b0fb15a`.
- Support manifest: 6 files, SHA-256 `41687d9edfb829d5642c3e878908cd3094adb2a719e785b69ec4e5e883e9d2aa`.
- Restricted helper: SHA-256 `2905d15d4f4a2c1a9c49cbf45faf37049ef9d9040b807910f885299a32a2f2e7`.
- Transactional installer: SHA-256 `36a15c51d28c62a08d7807afa63ecee71c99814604e8dd886c28e85e2bb3855e`.
- Exact seven-command sudoers: SHA-256 `d59be9bd212a0602e1ce966e9d07d4f018e6fe6b0c9b15f280c1915e009a878e`.
- Mailpit-only configuration tool: SHA-256 `25cc25cd03d20e2b4a44c3070e25b4dea0c013d89f48ee11f24c31a6dbc7ecf3`.
- Consolidated release pins: `deploy/releases/m068-staging-deployment.sha256`.

The only helper commands are `configure-mailpit`, `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`. `configure-mailpit` changes only `MAIN_MAIL_SENDMODE=smtp`, `MAIN_MAIL_SMTP_SERVER=mailpit`, and `MAIN_MAIL_SMTP_PORT=1025` after a full root-coverage database dump and restore-fingerprint validation; it never sends mail. `apply` requires zero runtime/dependency drift and the exact additive-schema prestate, then creates validated code/prestate and full database backups before installing the 26 runtime files and applying the additive migration. Live verification checks exact hashes/metadata, schema, configuration, ERP health, and authenticated boundaries on the staff and Customer Center routes. No command creates business records.

Local release tests passed for deterministic rebuild, artifact metadata and allowlist, pinned manifests, Mailpit-only/no-send contract, restricted sudo surface, exact predecessor upgrade, transactional installer recovery, corrupt/oversized/symlink artifact denial, runtime/dependency/baseline drift denial, synthetic apply/verify/rollback, PHP/Bash/Python syntax, and the existing M068 source suites. Production identifiers are absent from the release surface.

Execution must stop on the first failed gate. Staging acceptance still requires the exact live outputs from Mailpit configuration, compare, preflight, apply, verify, and status. Authenticated browser and disposable-mailbox delivery tests remain separate because this approval forbids sending mail or creating real business records. Production remains entirely out of scope and requires a later explicit approval.

## M068-R03 correction package — 2026-08-31

R02 installed successfully on canonical `backup-erp`. Its Mailpit command created and restore-validated a full database backup, configured and verified `mailpit:1025` without sending mail, then stopped at `PORTAL_GROUP_OR_RIGHT_AMBIGUOUS`. `status` returned `M068_DEPLOY_STATUS=NOT_DEPLOYED`; no M068 runtime or schema was applied. The C01 verifier then reported `M068_PREFLIGHT_HELPER_INVALID` because it correctly still pinned the pre-R02 helper hash; this did not indicate HMAC value loss.

Ahmed approved M068-R03 to correct these exact stopping findings and complete staging. The local package is frozen as follows:

- Artifact: 37 canonical allowlisted files, SHA-256 `f47a4b51a3964ab3a65f5c4531e72fef120f3736198afecd4073e0b12681fa21`.
- M068-R03 helper: SHA-256 `11b3e0e9e566be2722bc831e097ab67d04d93a6e4f708b4795494407df71940a`.
- M068-R03 installer: SHA-256 `b197fbd6efd4aa8a4236a8df6946efae955d1b7bf5a8e72eb2636dec154a0a72`.
- Eight-command sudoers: SHA-256 `a9970f4e1788b3efb513bd2558170e5765dd3c8067b4c3b1b5cdaf62e520a45f`.
- Entity-scoped portal repair: SHA-256 `6eef8ac2bfbffa155eab7d869ab8d2f1554faf96283d610172b687a111ae5c7e`.
- C01-R02 verifier helper: SHA-256 `5b867120ff1b6aaaaa1303f133bc23125f1d200a0bbed9caad2fc14a193b826f`.
- C01-R02 transactional installer: SHA-256 `f67ebd874c3e8e1749a973a7410fdab4e384191aa6c815f92b5b4135ea2f0527`.

The portal repair exposes counts and scope only, never row IDs or customer data. It stops on same-scope group ambiguity, preserves all groups/memberships/users, and changes only the exact active-entity portal authorization rows described in the Mission. It uses an entity-scoped MariaDB advisory lock, `FOR UPDATE`, transaction checks, a post-state proof, and independent lock release. The R03 helper creates and restore-validates a full root-coverage database backup before invoking it. The Mailpit no-op path now also runs the complete configuration gate.

All local M068 helper/installer/release suites, C01 helper/installer/contract suites, existing M068 lifecycle/currency/invitation checks, PHP/Bash/Python syntax, deterministic rebuild, manifest pins, predecessor-upgrade paths, and `git diff --check` pass. No server change was made during R03 local preparation. Production remains forbidden.
