# M060 Local Completion Report — Internal Task Card Collaboration

## Control record

- Mission: M060
- Approved scope: local implementation only
- Baseline: `dccd67a317191565542a4d197c08efc935b9e1aa`
- Branch: `codex/m060-internal-task-collaboration`
- Implementation commit: `5fdebad`
- Local Dolibarr: 23.0.3 at `http://127.0.0.1:8088`
- Result: `DEVELOPER_REPORTED_LOCAL`
- Staging/production: not accessed
- Outbound messages: none

## Delivered capability

- Task-scoped plain-text comments with safe mentions, durable request replay protection, recipient-only notifications, cursor pagination, drafts and administrator redaction.
- Ordered checklists and checklist items with completion, rename, soft remove/restore, keyboard/touch reorder and stale-order fingerprints.
- Entity-scoped reusable labels with required Arabic, English and Chinese names, accessible colors, assignment and AND board filtering.
- Sequential private multi-file upload, drag/drop, local raster clipboard paste, per-file outcomes and authenticated image lightbox while retaining the M058 validator and task authorization.
- Card and board collaboration counters that expose only data for already-readable tasks.
- Accessible drawer and direct-card flows for collaboration, including localized busy, validation, denial and stale states.

## Schema, rights and module state

- Module version: `2.7.0`.
- New rights: `500523 task_comment` and `500524 task_checklist`; both disabled by default for non-administrators.
- Seven additive InnoDB tables: `cpo_task_comment`, `cpo_task_comment_mention`, `cpo_task_notification`, `cpo_task_checklist`, `cpo_task_checklist_item`, `cpo_task_label`, and `cpo_task_label_link`.
- Migration is named-lock protected, idempotent, checks absent-or-exact rights before DDL and fails closed on wrong module, permission or default state.
- Existing task status, stage, progress and task version do not change for collaboration mutations; the existing M058 asset version rule remains unchanged.

## Authorization and privacy

- All collaboration reads, counts and writes reuse the central active-internal-user, entity and readable-task boundary.
- `task_admin` does not bypass the new comment/checklist rights; only a verified Dolibarr administrator receives that operational bypass. Administrator redaction remains available to verified administrators and `task_admin` as approved.
- Mention candidates are evaluated with their own current task-read authorization. Revoked notification rows disappear from list and unread count and cannot be opened or marked read.
- Normal projections mask redacted bodies and inactive/external historical authors. Activity is generic and stores no comment, checklist, label, filename or mentioned-user content.
- Comment drafts use an opaque entity/actor/session/task scope and only the exact current draft is purged on success, logout or denial.

## Preservation and isolation

- Local activation retained all 14 existing internal tasks, existing task assets/activity and CP Kanban data.
- No implementation changes were made under CP Kanban, Customer Center, Logistics, public React/Node, or mobile application source.
- Customer, supplier, cost, margin and public portal data are not projected by M060.

## Verification evidence

- PHP lint: PASS for changed PHP files.
- JavaScript syntax: PASS.
- `git diff --check`: PASS.
- M058 source/UI regressions: PASS.
- M059 source regression: PASS.
- M060 source contract: PASS.
- M060 UI contract: PASS.
- Isolated MySQL runtime suite: `M060_RUNTIME=PASS`, covering rights collisions/rerun, role gates, hidden-task reads, replay/conflict, redaction, mentions/notifications/revocation, inactive lifecycle, reorder stale protection, label fingerprints/duplicates, text controls and activity privacy.
- Real Dolibarr acceptance as `admin-agent`: board and shell-free drawer opened; comment add and immediate administrator redaction passed without closing the drawer; comment counter reset passed; desktop, 390×844 and 360×844 layouts passed visually.
- A real-runtime incompatibility found during acceptance was corrected: Dolibarr's `affected_rows` requires the query result argument. All five collaboration CAS update paths now pass the result, with a source regression assertion.
- A local PHP/M060 size mismatch found during user acceptance was corrected: PHP now uses `upload_max_filesize=50M` and `post_max_size=64M`; the source presents and enforces localized preflight limits of 10 MB for images/text and 50 MB for PDF/Office before network upload. The previous PHP configuration is backed up at `/private/tmp/php.ini.m060-upload-before`.
- Card-thumbnail acceptance passed: the first active image is assigned as the cover atomically during upload, later images do not replace it, and manual cover selection remains available. The existing local image on `CP-TK-2026-000014` was selected as cover and the board rendered authenticated preview asset `1` as the card thumbnail.
- Corrected a valid Chrome/Skia PDF rejection found during local use. A compressed stream ended in a carriage-return byte that was included in its declared PDF `/Length`; the former delimiter matcher removed that data byte and reported an unsupported compressed stream. The validator now restores only the directly declared missing delimiter byte, retains its bounded zlib/raw-deflate inspection, and still rejects compressed active-content, array/chained, indirect and unsupported filter cases. The exact captured 551,428-byte user PDF passes the corrected local validator; no document content is logged.
- Independent final reviews: Internal Operations PASS; Security/source PASS.

## Backup and rollback

- Local backup directory: `/private/tmp/m060-local-9grC5x`.
- Database backup SHA-256: `06d67ebcca0b0b5c3165c278916a4f8ee2b5ab3c051f6cf1b44ea8799856018c`.
- Runtime-module backup SHA-256: `234942280829b5b45c46c86916cc6138f62c803af89f0ecfbc5d522437db0092`.
- Data-directory backup SHA-256: `904beb3e30f617a0e8e602970449bee6aa34760673eed8ef54fcabe165642c44`.
- Restore was exercised and verified before final activation. Rollback is restore of the local database/module/data backups followed by readiness verification.

## Remaining release gates

- This is local developer evidence, not staging or production acceptance.
- Before any release, run the complete authenticated Arabic/English/Chinese administrator, task-admin, supervisor and worker matrix; full upload/fault/concurrency cases; and the production release workflow under separate explicit approvals.
- No Git push, pull request, staging deployment or production deployment is included.

## Release-request read-only preflight — 2026-08-16

- Ahmed requested deployment to both `backup-erp` and live ERP. The request authorized current read-only environment inspection; no artifact upload, helper installation, apply or schema/module mutation was performed.
- Restricted SSH identity and public HTTPS checks passed. Existing restricted status reports both environments still deployed at M056 runtime commit `606b54c852230b0674b0745084e107487cf3161d`; both public roots returned HTTP 200.
- The cumulative delta from that deployed M056 runtime to M060 commit `579f17e319dc7ac0255e8ea2bb6594eec7aa01d1` contains 38 `chinaplanetoperations` module files, including 11 SQL files and additive schema/rights/module-state work from M058–M060. It is not a PDF-only release.
- `sudo -n -l` exposes no M060-specific staging or production helper. Existing generic/older Mission helpers are not authorized substitutes. The local M060 branch is not published to the private remote.
- Result: `FAIL_SAFE_RELEASE_BLOCK`. Staging and production were accessed read-only; runtime files, databases, schemas, module state, configuration, documents, customer/business data, containers, proxy, DNS and ports were not changed. No rollback was required.
- Next gate: freeze and independently review a dependency-closed cumulative M058–M060 artifact plus checksum-pinned staging/production helpers with code and full-database backups, migration/readiness verification and rollback. Run `backup-erp` compare/preflight first; apply and production remain separate approvals.

## Changed areas

- ChinaPlanet Operations authorization, migration, task projection and collaboration services.
- Module descriptor, seven SQL schemas, task board/card/asset plus collaboration/inbox/label routes.
- Internal task CSS/JavaScript and AR/EN/ZH language files.
- M058/M059/M060 contracts and runtime tests, change log and mobile parity documentation.

## Backup-ERP package preparation — 2026-08-16

- Approved gate: prepare the cumulative M058–M060 package and deployment helpers, then run `compare` and `preflight` on `backup-erp` only. `apply` and all live/production access were explicitly excluded.
- Frozen runtime commit: `579f17e319dc7ac0255e8ea2bb6594eec7aa01d1`; deployed comparison baseline: M056 `606b54c852230b0674b0745084e107487cf3161d`.
- Artifact: `m060-internal-tasks-579f17e.tar.gz`, 50 allowlisted members, initially SHA-256 `b8612e76a39da740c5bb9daada75c242c73946016984d01b2d6c2ce6d3927357`; the backup-PHP compatibility correction supersedes it with SHA-256 `169b6f3b6923fc9e86a85b1d1d49afd0c17458b3c128001bc620284152e8868c`.
- Initial read-only helper SHA-256: `438d6423c99e96a884729235428c72fb001b204fafff84c356abaafd8295054f`. The backup-PHP compatibility correction supersedes it with helper SHA-256 `665aeb461c0873045ab90699d4b9b626553498b11dbcf3e3012baaeae720eb78` and installer SHA-256 `3c5e50f1ccfcfba67e24ff3305e43838baccc6d88808550571d13b3a486ce181`; sudoers remains SHA-256 `6998e29aca8b3b6822ff6e31d33c0f0e659b10f8c5617ee3bc59dbbe16bec0fa`.
- The helper and sudoers expose only `compare`, `preflight`, and `status`. There is no `apply`, `verify`, or `rollback` command in this approved helper, and its output declares `APPLY_AVAILABLE=NO` and `PRODUCTION_ACCESSED=NO`.
- Local release contract, PHP/shell syntax, all bundled M058/M059/M060 static contracts, artifact membership and byte-for-byte content verification passed. Packaging commit: `87e604e`.
- The four pinned files were uploaded to `/home/codex-deploy/staging-inbox` on the backup host and their remote ownership, mode and SHA-256 values matched the local sources.
- `compare` and `preflight` have not run because the new checksum-pinned helper is not yet installed by root and does not appear in restricted `sudo -n -l`. No ERP runtime file, database, schema, module state, container or configuration changed. Live/production was not accessed during this gate.
- Required continuation: a server administrator runs the exact uploaded installer once; restricted verification then runs `status`, `compare`, and `preflight` only. Any `apply` requires a separately reviewed mutating helper and a new explicit approval.

### Backup-ERP compare/preflight result

- The corrected helper was installed with SHA-256 `665aeb461c0873045ab90699d4b9b626553498b11dbcf3e3012baaeae720eb78` and independently matched before execution.
- `status` returned `PREFLIGHT_ONLY` with `APPLY_AVAILABLE=NO` and the corrected artifact SHA-256 `169b6f3b6923fc9e86a85b1d1d49afd0c17458b3c128001bc620284152e8868c`.
- `compare` passed all 38 runtime paths against the deployed M056 baseline: existing files matched their exact M056 hashes, expected new files were absent, and `DRIFT_COUNT=0`.
- Schema inspection returned `MIGRATION_REQUIRED`, the expected pre-apply state for additive M058–M060 tables, columns, indexes, rights and menu registration.
- Final result: `M060_STAGING_PREFLIGHT=PASS`, with `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, and `PRODUCTION_ACCESSED=NO`.
- No code, schema, module state, configuration, documents, business data, containers, proxy, DNS or ports were changed. The next gate is preparation and independent review of a mutating backup/apply/verify/rollback helper, followed by a new explicit backup-ERP apply approval.

### Backup-ERP mutating-helper ownership correction — 2026-08-18

- Ahmed approved deployment to `backup-erp` and live ERP. Production remains gated behind successful staging acceptance and a separate production-only helper; the staging helper has no production targets or grants.
- The reviewed mutating staging helper was installed, but its first `compare` stopped before candidate extraction or mutation with `TARGET_ROOT_INVALID`. Independent `status` remained `M060_DEPLOY_STATUS=NOT_DEPLOYED`.
- A root metadata check supplied by Ahmed proved the canonical custom-volume root is exactly `www-data:www-data 755`, not `root:root`. The helper now requires that exact observed owner, group and mode while retaining the fixed volume/container/mount, symlink, parent identity and path-boundary gates. The Dolibarr volume ownership was not changed.
- Corrected helper SHA-256: `4e006f19221b1a1427067d302de3a40bce68f57277b1067d6f520e63ea4fac64`. Corrected transactional-upgrade installer SHA-256: `20653d1acd226b9900a31c5eb62f4e98d63eba2d43fb0a88c0e3bfa19d710cff`. It accepts only the installed previous helper SHA-256 `fef8f45d32aed25d76a68c1e34dc7ee7174a88c29ae63c5a657777d49e7f6949` or the corrected hash and restores the previous helper on a failed upgrade.
- Sudoers remains SHA-256 `b68a8abf54acc6070aab543733f94f36ace5a8745f2373f7706c2996ca55fa4c`; artifact remains SHA-256 `a71764050597dfd7b42ffd8b9689505b5e37e754f42186e54fc5895b9b55990c`. Helper contract, executable recovery/fault suite, M058/M059/M060 source/UI regressions, shell/PHP syntax and diff checks pass.
- The correction is prepared locally only at this record point. Corrected upload/install and all staging `compare`, `preflight`, `apply`, `verify` remain pending. Live ERP has not been accessed or changed.
- After installing that correction, the next `compare` stopped before mutation with `DB_NAME_MISMATCH`; `status` again proved `NOT_DEPLOYED`. Ahmed's root-only non-secret check confirmed the active canonical staging database name is exactly `dolibarr_db`, consistent with the M040-C02 staging record. The helper now pins both its environment gate and database-backup command to `dolibarr_db`; it does not rename or modify the database. Corrected helper SHA-256 is `432899fe10b6910c638706cabaa11c732368337634aff56f8c94694280f26c61`; transactional installer SHA-256 is `7f25a9171ffeaaac88efe936586e89250eeb37c7b0f081887636108315b13158`, accepting only the installed immediate predecessor `4e006f19221b1a1427067d302de3a40bce68f57277b1067d6f520e63ea4fac64`. All named helper, fault/recovery, M058/M059/M060 regression, syntax and diff gates pass; upload/install and deployment remain pending at this record point.
