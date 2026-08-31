# M069 — Clean Composite Baseline Evidence Census

## Mission control

- **Mission ID:** M069
- **Type:** DOCUMENTATION / EVIDENCE / ARCHITECTURE / RELEASE_GOVERNANCE
- **Status:** CORRECTION_REQUIRED — full current runtime manifests remain unverified
- **Architect and documentation owner:** Agent 007
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-31
- **Approval source:** Ahmed approved implementation of the complete `ChinaPlanetOS Clean Composite Rebuild Program`, whose first mandatory gate is M069.
- **Source baseline:** refreshed Dolibarr `origin/main` at `057af0f205f819c685869b3ee6aa1aea31ea549e`
- **Branch:** `codex/m069-composite-baseline`
- **Target:** local evidence and documentation plus approved read-only `backup-erp` status inspection
- **Production mutation:** FORBIDDEN
- **Staging mutation:** FORBIDDEN

## Business outcome

Create one evidence-backed boundary for rebuilding ChinaPlanetOS from the verified union of Production and Staging without merging stale histories, copying live files into Git, losing accepted functionality, or overwriting unrelated user work.

## Evidence precedence

1. Current narrowly scoped read-only runtime evidence.
2. Immutable deployed helper state plus exact artifact/commit pins.
3. Current canonical remote branch identity.
4. Checksum-pinned release manifests and completion reports.
5. Historical Mission status and local branches, labelled as such.

Mission labels, HTTP 200 responses, source ancestry, and local tests do not independently prove current runtime parity.

## Approved scope

- Pin the canonical Dolibarr, React, and WordPress plugin remote heads.
- Inventory Mission sources, release layers, manifests, helpers, worktrees, modules, migrations, and tests.
- Trace the final effective M061 staging overlay and its relationship to `origin/main` and M068.
- Separate accepted, staging-only, failed/rolled-back, unpublished, duplicate, and protected user-owned state.
- Record the frozen cross-system ownership and `cpo.catalog-order.v2` gaps that M070/M071 must resolve.
- Define the exact remaining evidence needed before M069 can be frozen.

## Explicitly out of scope

- Application source changes, cherry-picks, merges, rebases, resets, cleans, or worktree deletion.
- Database, schema, configuration, module, business-record, document, email, DNS, proxy, Compose, container, staging, or production mutation.
- Creating the M074 reusable skill before successful runtime acceptance.
- Treating the failed M068-R04 code as deployed or accepted.
- Treating Mission-document status as proof that a feature is currently active.

## Protected state

The following must remain untouched:

- Permanent Dolibarr checkout at `63a310c150b80087764605223e5848b6e8b571cb9`, ahead 1/behind 204, including its modified mobile parity document and untracked `AGENTS.md`.
- Permanent React checkout and its modified `src/pages/ChinaGuidePage.jsx` plus untracked `src/lib/chinaGuideFilters.js` and `src/lib/chinaGuideFilters.test.js`.
- Dirty archived `local-runtime-m011` and `m040-smart-document-center` Dolibarr worktrees.
- Old helpers, prunable registrations, plugin duplicates, ZIPs, artifacts, backups, and retained M068 schema until a later approved cleanup Mission.

## Acceptance and stopping gate

M069 can advance only when:

- each current Production and Staging runtime file in the approved ChinaPlanetOS module roots has a safe path, SHA-256, and provenance classification;
- all accepted runtime layers are traceable to canonical source or an immutable deployed artifact;
- M061's incomplete authenticated acceptance is explicitly resolved or carried as a named release blocker;
- React and WordPress canonical remote heads are current and pinned;
- no protected user work overlaps the future clean reconstruction.

The current census stops at `CORRECTION_REQUIRED` because the restricted helpers expose release-scoped status only. They do not provide a complete current runtime-tree manifest, and no exact Production read-only runtime-inventory approval was granted. M070 must not start.

## Required correction approval

A later exact approval may authorize a bounded, non-secret, read-only hash inventory of the approved ChinaPlanetOS runtime roots on canonical `backup-erp` and Production. It must output relative paths, file type, mode, owner/group identifiers, size, and SHA-256 only; it must exclude configuration, documents, uploads, caches, database data, logs, secrets, and core Dolibarr files. It must not install a helper or change either environment unless that installation is separately approved.
