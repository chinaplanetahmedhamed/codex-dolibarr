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

## Changed areas

- ChinaPlanet Operations authorization, migration, task projection and collaboration services.
- Module descriptor, seven SQL schemas, task board/card/asset plus collaboration/inbox/label routes.
- Internal task CSS/JavaScript and AR/EN/ZH language files.
- M058/M059/M060 contracts and runtime tests, change log and mobile parity documentation.
