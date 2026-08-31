# M069 — Clean Composite Baseline Evidence Census

## Mission control

- **Mission ID:** M069
- **Type:** DOCUMENTATION / EVIDENCE / ARCHITECTURE / RELEASE_GOVERNANCE
- **Status:** CORRECTION_REQUIRED — runtime census complete; four Staging-only files remain without immutable provenance
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

M069-C02 completed the full bounded runtime census without reading core, configuration, databases, documents, uploads, logs, caches, secrets, credentials, or business data. Production contains 315 approved custom-runtime files and canonical `backup-erp` contains 325. The two immutable manifests are recorded in `docs/REPORTS/`.

All 315 Production file hashes and 321 of 325 Staging file hashes match Git blobs reachable from the canonical repository. Four Staging-only acceptance/demo scripts do not match any reachable Git blob. M070 must not start until those four hashes are traced to an approved immutable artifact or explicitly classified by a separately approved correction.

## Required correction approval

A later exact approval may authorize a bounded read-only provenance search for the following exact Staging hashes inside approved code-only release artifacts and code backups; databases, configuration, documents, logs, secrets, and business data remain forbidden:

- `custom/chinaplanetcustomercenter/admin/acceptance_demo.php` — `ef26675038d7e1878f461ae9133b1d5e291ef11bd63577ff8e9343672f50d884`
- `custom/chinaplanetcustomercenter/admin/demo/seed_customer_portal_demo.php` — `ffad67b0f14b46694581d55714f38a1c2e15338fd56de992036125f2ecfbafb8`
- `custom/chinaplanetcustomercenter/admin/demo/seed_full_order_cycles.php` — `d83931588f4c6589260dcb09ceba1f32f96685494168135d45f22a455183d01e`
- `custom/chinaplanetcustomercenter/admin/demo/seed_operations_sops.php` — `0d641c624638043750308060c5ec4f773b57475ea163429559bb202ef51f6abf`
