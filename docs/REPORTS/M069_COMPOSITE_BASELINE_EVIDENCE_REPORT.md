# M069 Composite Baseline Evidence Report

Date: 2026-08-31

Mission: Clean Composite Baseline Evidence Census

Result: **CORRECTION_REQUIRED**

## Executive finding

The desired clean baseline cannot be produced by merging the M061 or M068 branches wholesale. Production is represented by the accepted Dolibarr main history through M062-R04, while canonical Staging adds a 39-file M061 overlay and retains additive M068 schema/configuration after M068-R04 code automatically rolled back. React Production and Staging are also intentionally different by one commit.

The source and release ledger is now mapped, but M069 cannot be frozen until a complete current runtime hash inventory is obtained under a separate exact read-only Production approval. Existing helpers verify only their individual release scopes.

## Canonical repository pins

| System | Canonical repository | Current remote main | Local condition | Evidence |
|---|---|---|---|---|
| Dolibarr/ChinaPlanetOS | `chinaplanetahmedhamed/codex-dolibarr` | `057af0f205f819c685869b3ee6aa1aea31ea549e` | Permanent checkout dirty/diverged; M069 clean worktree created from remote main | Successful `git fetch` and remote ref |
| React/Node public site | `chinaplanetahmedhamed/Moaz` | `cb639518a784ce709e4a45eee0bd55b0a477c57a` | Local `origin/main` ref remains stale at `1762be4`; protected permanent checkout is dirty | Read-only GitHub API plus live health |
| WordPress planner | `chinaplanetahmedhamed/b2b-container-loading-planner` | `fa34257fe9dfc276d85c16dae70b409224465c3c` | Clean `main`, tag/version `1.9.4` | Read-only GitHub API and local equality |

Direct React `git fetch` timed out against GitHub. The remote head was therefore pinned through the authenticated read-only GitHub API; M070 must refresh the Git ref successfully before creating a React implementation worktree.

## Current runtime and release ledger

### React

| Environment | Live SHA | Classification |
|---|---|---|
| Production `chinaplanet.org` | `cb639518a784ce709e4a45eee0bd55b0a477c57a` | Accepted Production; includes M062 Alexandria terminal-series work and completed SEO/performance PR #29 |
| Staging `new.chinaplanet.org` | `df5ae8bd8e7c352f435cf175e5b411c9c53ecdce` | Staging-only candidate; exactly one commit ahead of Production |

Both public health endpoints reported `ok=true` with catalog, WordPress content, B2B integration, Dolibarr gateway, durable rate limiting, and admin configuration enabled. Health proves process/configuration identity, not the complete customer journey.

The existing task titled `Start CP-WEB-SEO-PERF-001` is completed, not pending engineering. Its work merged as Production commit `cb639518...`; Search Console ownership was verified. Remaining sitemap submission, URL Inspection, and 2–4 week monitoring are operational follow-up and must not be duplicated as a new code Mission.

### Dolibarr canonical `backup-erp`

Read-only restricted `status` returned:

| Layer | Status | Runtime commit | Artifact SHA-256 | Classification |
|---|---|---|---|---|
| M058–M060-R08 | `DEPLOYED` | `4cb98b36eedfe932097f66822f48837b423d2337` | `e438730431f759c581a0b361fab5c9f370b7ab9acbf00f8b5b95bd46efec1d00` | Accepted Production and Staging layer; commit is in main |
| M061-R01-C01 | `DEPLOYED` | `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a` | `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463` | Staging-only; not in main |
| M061-R02-C03 | `DEPLOYED` | `487b61e690d7d030ec25d54a3b62afdb7e78fb55` | `bf508b9e7b495092542160e4bb97ce1410c2013497d90bc6af70ff9a3498085f` | Staging-only; not in main |
| M061-UI01-R01 | Recorded `DEPLOYED` | `6032aae58d2067bff761b8556585f78f096e275f` | `50c217c3c2d25e688a71ffcf1eef821b70903ab93eee8e6f8a6bbd52890c0e4a` | Staging-only UI overlay; no restricted status command currently exposed |
| M062-R04 | `DEPLOYED` | `f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b` | `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c` | Accepted Production and Staging layer; commit is in main |
| M068-C01-R03 | `PASS`, config `READY` | ownership/config helper state | configuration state | Staging-only server configuration; secret not read or output |
| M068-R04 | `APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED` | candidate runtime `32e82ffbd82248f2a84f468172195267fb6d37a7` | `a1c4fc03d00cd0d22aac7ee4c75a35af71b1bf72e3d9f58d0cef14925a665391` | Code rejected/rolled back; additive schema retained |

No compare, preflight, apply, verify, rollback, email, or business-record action ran during M069. Production helper commands were not invoked.

## M061 effective runtime overlay

The union of the three deployed M061 runtime manifests contains exactly 39 paths. The exact final effective SHA-256 list is stored in `M069_M061_FINAL_EFFECTIVE_RUNTIME.sha256`.

Comparison against current Dolibarr `origin/main` and the M068-R04 source branch:

- 32 paths exist but differ.
- 6 paths are absent.
- Only `custom/cpkanban/lib/projectsync.lib.php` already matches.
- M068's runtime manifest overlaps M061 in only two paths: the Customer Center descriptor and `portal.lib.php`.
- Therefore 37 deployed M061 paths sit outside the M068 runtime manifest. R04's two-file merge did not create a complete M061 composite baseline.

M061 is deployed but not fully accepted for Production promotion. Existing evidence still identifies incomplete external-customer/payment/document coverage, and historical Mobile API failures must be retested against the final deployed C03 state. M070 may reconstruct this behavior, but M073 must prove it before any Production proposal.

## Source and architecture condition

- M068 branch is seven commits ahead of Dolibarr main with 61 changed files, 5,319 insertions, and 54 deletions.
- M068 runtime code is not active on Staging after automatic rollback; its additive schema and C01 configuration are retained.
- Customer Center, Logistics, Operations, Shipping Prices, and CP Kanban still have documented cross-module cycles. CP Kanban owns project sync; Customer Center retains only its compatibility entry point.
- Current M068 worktree contains 80 Customer Center files, 26 Logistics files, 74 Operations files, 44 Shipping Prices files, 42 CP Kanban files, and 86 mobile-app files.
- Release debt is material: 103 deployment tools, 163 deployment tests, and 16 migrations in the inspected M068 tree.
- Historical helper grants are highly fragmented. M072 must deprecate them only after the new controller and rollback retention are proven; M069 authorizes no removal.

## Cross-system B2B contract gaps

The frozen target remains `cpo.catalog-order.v2`, but current React Staging is not compatible:

- `schema_version: 2` is nested only inside `snapshot`; it is absent at the request top level, so Dolibarr can interpret the request as legacy v1.
- Node rejects any authoritative line with incomplete packing instead of retaining it for quotation review.
- The React product card prevents incomplete products from entering the multi-product list.
- Submission is disabled whenever the plan reports excluded lines.
- Country remains free text and can become an empty country code.
- Current row keys collapse repeated identical product/variation selections instead of preserving request-unique `lineId` values.

The canonical WordPress plugin already calculates ready lines and reports exclusions, but its REST response still serializes some unknown numeric values as zero and does not provide the full `calculationReady`/`missingFields` v2 projection. Its `create_request` endpoint creates a pending WooCommerce order; the React-to-Dolibarr path must continue to bypass that endpoint.

WordPress source ownership is also duplicated locally:

- Canonical clean Git repository: version `1.9.4` at `fa34257...`.
- Noncanonical copy: `b2b-integration/plugin`, version `1.9.0`, with differing main, REST, readme, and test files.
- Historical ZIP: `b2b-container-loading-planner.zip`.

No duplicate was deleted or modified.

## Mission and worktree census

- Management workspace: 66 Mission files covering M001–M060 plus template/index; many status labels are historical and conflict with later runtime evidence.
- Canonical Dolibarr Git history across local and remote refs: 47 unique Mission documents covering M030–M068, including branch-only M061 and M068 records.
- React repository: five Mission documents; CP-WEB-SEO-PERF-001 is already completed/deployed.
- WordPress plugin repository: no numbered Mission documents.

Protected dirty state discovered and preserved:

- Permanent Dolibarr checkout: mobile parity modification and untracked `AGENTS.md`.
- Archived `local-runtime-m011`: eight modified and three untracked module files.
- `m040-smart-document-center`: six modified and nine untracked files.
- Permanent React checkout: one modified and two untracked China Guide files.

Prunable registrations and clean historical worktrees were inventoried but not removed.

## Regression inventory frozen for later Missions

The clean rebuild must preserve and test:

- Arabic, English, and French public copy; Arabic RTL and English/French LTR.
- Desktop, tablet, 390px, and 360px layouts; keyboard, screen-reader labels, focus, touch targets, loading, empty, error, and success states.
- Public rate search → selected rate → review request, never confirmed booking.
- Alexandria grouped search with separate Old Port/TMT trend series and a no-data state when evidence is insufficient.
- Eight service pages, China Guide, products/catalog, blog, SEO metadata, structured data, sitemap, legal/consent links, WhatsApp/contact routes, and Customer Center navigation.
- Admin ERP, Customer Center, and mobile parity with customer/entity isolation and no supplier, cost, margin, private note, internal ID, secret, or private-document exposure.
- No automatic email, password, proposal validation, order, booking, invoice, or other business record during release verification.

## Why M069 is not frozen

The evidence proves release-layer identity but not a complete current file-by-file runtime tree for Production or Staging. M047 explicitly limited its comparison to six files, and the current helper surface remains release-scoped. Advancing would risk rebuilding from an inferred source tree while silently omitting live-only or staging-only files—the exact failure M069 exists to prevent.

## Next exact gate

Request a separate approval for a bounded read-only runtime manifest of the approved ChinaPlanetOS custom-module roots on canonical Staging and Production. No configuration, database, document, upload, log, cache, secret, core file, email, or business record may be read or changed. The output must be metadata and SHA-256 only.

After that evidence is compared with this ledger, M069 can either become `ARCHITECT_ACCEPTED` or name an exact correction. M070 remains blocked.

## Change and environment statement

- Application code changed: **NO**
- Database/schema/configuration changed: **NO**
- Staging runtime changed: **NO**
- Production accessed through a production helper: **NO**
- Production changed: **NO**
- Email/business records created: **NO**
- Git branch/worktree created: **YES**, documentation-only M069 branch from refreshed Dolibarr main
