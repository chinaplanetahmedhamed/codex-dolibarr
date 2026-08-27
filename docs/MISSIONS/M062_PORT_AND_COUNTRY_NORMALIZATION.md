# M062 — Port and country normalization

## Mission control

- **Mission ID:** M062
- **Type:** CORRECTION | MIGRATION
- **Status:** APPROVED
- **Architect owner:** Agent 007 Central
- **Implementation owner:** Agent 007 Central
- **Required reviewers:** Shipping, Security, QA
- **Dependencies:** Existing `chinaplanetshippingprices` draft/review/publish workflow
- **Target environment:** Local development only
- **Production:** Forbidden
- **Ahmed approval:** APPROVED — direct request to fully correct Shipping Booking port/country mapping, add safe aliases/fuzzy matching, migrate current data, and report remaining warnings
- **Approval date:** 2026-08-25

## Business objective

Eliminate duplicate and unmapped destination names without hiding uncertainty. Shipping-price imports must resolve curated spelling/case/punctuation variants to one canonical port and country, safely split origin/destination and multi-destination cells, and leave genuinely ambiguous values as warnings instead of guessing.

## Current system context

- Dolibarr 23.0.3 and `chinaplanetshippingprices` 1.4.0 are enabled in local development.
- The current importer uses a built-in prefix-to-country array plus a free-text `aliases` column. It does not canonicalize the stored POD, detect alias conflicts, split composite destinations, or perform bounded fuzzy matching.
- Local read-only inspection found 3,023 unmapped rate rows across 36 distinct POD strings. The current warnings include Algeria, Morocco, Libya, Tunisia, UAE, Saudi Arabia, Guinea, Iran, Egypt and composite origin/destination strings.
- The permanent `main` checkout is intentionally preserved because it is divergent and dirty. M062 uses clean worktree `/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m062-port-normalization` from `origin/main` commit `95402e7f22665c9efd61d648f1e545094aaf47c9`.

## Scope

1. Add a canonical port resolver with Unicode/case/punctuation normalization.
2. Add a dedicated entity-scoped alias table with a unique normalized alias.
3. Seed verified canonical ports/countries and the current agent spellings.
4. Use exact aliases first, then conservative fuzzy matching with a configurable threshold, minimum length and winner margin.
5. Split supported origin/destination and multi-destination values into separate canonical rate rows.
6. Preserve the raw POD and match evidence on each imported rate.
7. Add an idempotent local migration that seeds aliases, normalizes existing rates, preserves raw values, avoids clone duplication and removes only resolved destination warnings from batch reports.
8. Prevent normalized alias/canonical-name collisions in the admin mapping workflow.
9. Add focused resolver, schema, importer and local-data verification.

## Out of scope

- Shipping-line mapping, pricing, publication state, accounting, customer ownership, email, WhatsApp or n8n.
- Any staging or production access, migration, deployment or business-data cleanup.
- Automatic acceptance below the safe fuzzy controls or mapping a partial/ambiguous composite.

## Architecture and data flow

`raw workbook POD → remove recognized origin prefix → exact full alias/qualifier match → safe composite split → exact alias per part → bounded fuzzy fallback → canonical port + ISO country + match evidence → draft rate`

The curated catalog and database alias table are the authoritative match sources. The legacy free-text alias column remains readable for compatibility but cannot override a curated conflicting alias. A composite is accepted only when every part resolves; otherwise the full input remains non-public and produces a warning.

## Permissions and visibility

- Import permissions and customer-safe rate projection remain unchanged.
- Alias administration remains internal and requires the existing module `admin` right plus CSRF.
- The change exposes no supplier, cost, margin, customer, bank, note or file data.
- All catalog, alias, migration and rate updates are entity-scoped.

## Database, accounting, and configuration impact

- **Database:** Add `llx_chinaplanetsp_port_alias`; add raw/match audit columns and indexes to `llx_chinaplanetsp_rate`; seed canonical port/alias reference data; normalize local rate POD/country values and resolved warning metadata.
- **Accounting:** None.
- **Configuration/module state:** Add `CHINAPLANETSP_PORT_FUZZY_THRESHOLD` default `0.90`; no module activation change.

## Functional requirements

1. Exact alias matching must be deterministic and case/punctuation insensitive.
2. Alias conflicts must fail closed and cannot create two active mappings for one normalized alias.
3. Fuzzy matching requires normalized length at least six, the configured threshold, maximum two edits and a clear margin over the runner-up.
4. `SHEKOU-- SKIKDA, ANNABA` and `NANSHA-- SKIKDA, ANNABA` must resolve as two Algerian destination ports, not a country string.
5. `BEJAIA/SKIKDA`, `Oran/CASABLANCA`, `SFAX & SOUSSE`, `TUNIS / RADES` and `Rades,Tunis` must split only when every component resolves.
6. Known qualifier text such as sea/dry-port notes must not change the canonical port.
7. Existing and future rows must retain the raw input for audit.
8. Re-running the migration must not create duplicate aliases or cloned destination rows.

## Success criteria

- All 33 destination warnings in local batch 33 resolve; unrelated shipping-line warnings remain unchanged.
- The 36 currently unmapped stored POD values reduce to zero or a documented, genuinely ambiguous remainder.
- Requested canonical mappings use the correct countries and one canonical spelling.
- Resolver/schema/importer tests, PHP lint, regression tests and `git diff --check` pass.

## Risks and rollback concept

- **Risks:** false fuzzy match, alias conflict, composite row multiplication, public visibility change, incorrect country code.
- **Controls:** curated exact aliases, high threshold, short-name exclusion, edit cap, runner-up margin, all-or-nothing composites, entity scope, raw-value audit, idempotent clone guard and pre-migration full local database backup.
- **Rollback concept:** restore application files from the base commit and restore the pre-M062 local database backup. Additive schema may otherwise remain inert; production rollback requires a separately approved release plan.

## Testing requirements

- Resolver table tests for every current unmapped POD and every user-named example.
- Negative tests for short fuzzy guesses, partial composites and conflicting aliases.
- Schema/migration idempotency and duplicate checks on a backed-up local database.
- Existing shipping-price structure/privacy and upload-boundary regressions.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping | Independent Shipping reviewer | PASS | 2026-08-25 | Canonical countries, composite semantics, manual entry and fail-closed origin/qualifier behavior verified |
| Security | Independent Security reviewer | PASS | 2026-08-25 | Entity/CSRF/POST controls, atomic alias ownership, migration conflicts and customer-data boundaries verified |
| QA | Independent QA reviewer | PASS | 2026-08-25 | 45 resolver cases, importer negatives, two migration reruns, current data and rollback evidence verified |
| Architect | Agent 007 Central | APPROVED | 2026-08-25 | Narrow local correction contract |
| Ahmed | Ahmed Hamed | APPROVED | 2026-08-25 | Direct request in this task |

## Completion report contract

Create `docs/REPORTS/M062_COMPLETION_REPORT.md` with changed files, data/config/permission impact, tests, local before/after counts, unresolved warnings, rollback and reviewer results.

## Release authorization addendum — 2026-08-25

- Ahmed replied `ok, do it` after the proposed sequence: freeze M062, inspect and deploy to `backup-erp`, verify there, then consider the identical production release.
- Current approved gate is the local release freeze plus read-only canonical `backup-erp` discovery/compare preparation. It does not override the repository rule that helper installation, compare/preflight, apply/verify, authenticated business acceptance and production mutation are separate gates.
- Production remains unchanged and requires an exact post-staging approval naming the frozen commit/artifact and production action.

## Staging helper installation authorization — 2026-08-26

- Ahmed explicitly approved a non-destructive rollback that restores the prior Shipping Prices code while retaining the additive M062 schema, aliases and normalized data.
- Ahmed explicitly approved installing the checksum-pinned M062 helper on canonical `backup-erp` only, without running `compare`, `preflight`, `apply`, `verify`, `rollback` or `status`.
- The installation gate may upload the frozen artifact/helper/installer/sudoers to `/home/codex-deploy/staging-inbox`, install only `/usr/local/sbin/chinaplanetos-backup-erp-m062-ports` and `/etc/sudoers.d/codex-deploy-backup-erp-m062-ports`, validate their hashes/ownership/modes and the six-command sudo surface, then stop.
- Database, schema, Shipping Prices runtime, documents, containers and business data must remain unchanged. Production remains forbidden and must not be accessed.
- `compare` and conditional `preflight` are the next separate approval gate after successful installation.

## Staging helper correction authorization — 2026-08-26

- After independent release reviews blocked the first helper, Ahmed explicitly approved correcting the M062 helper and package locally, repeating Shipping/Security/QA review, and then installing only the helper and sudoers on canonical staging.
- The correction may make future `apply` schema-first, support retained-schema reapply only from root-owned terminal states, fail closed to explicit database recovery when a non-transactional migration is not exactly `READY`, make current-data checks dataset-aware, and replace the artifact with a reproducible metadata-normalized build.
- The installer must create only `/usr/local/sbin/chinaplanetos-backup-erp-m062-ports` and `/etc/sudoers.d/codex-deploy-backup-erp-m062-ports`. Operational backup/state directories are deferred to a separately approved setup gate.
- Root execution must use a root-owned `0600` copy of the installer after independent SHA-256 verification; the inbox copy must not be executed directly as root.
- This authorization still forbids every helper command and every database, schema, runtime, document, container, configuration or business-data change. Production remains forbidden and must not be accessed.

## Staging helper installation result — 2026-08-26

- Final preparation commit `45d299a259b414b7775b434dac09fc17af7db09f` and runtime commit `f697431b4ebf646bc2196e131e98e3bd53568e3e` passed the required Shipping, Security and QA reviews with no findings.
- The four reviewed files were uploaded to the canonical staging inbox as `codex-deploy:codex-deploy 0644`; their server-side SHA-256 values matched the frozen artifact, helper, installer and sudoers pins.
- The root bootstrap used `/root/install-m062-helper-45d299a.sh`, created as a root-owned `0600` copy. Its SHA-256 check returned `OK` before execution.
- The installer returned `M062_DEPLOY_HELPER_INSTALL=PASS`, exact helper and sudoers hashes, the six-command surface, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.
- Independent restricted verification confirmed the installed helper is `root:root 0755`, size `45432`, SHA-256 `34fad8e6e04304b5661206a1b7562e09641431b82deb06f658aa1fae6efc2c87`, and sudo exposes exactly `compare`, `preflight`, `apply`, `verify`, `rollback` and `status` for this helper.
- No M062 helper command was executed. Stop after installation; compare/preflight and all later release or database actions require separate exact approval.

## Dependency-closure correction authorization — 2026-08-27

- The first approved staging `compare` stopped fail closed at `CANDIDATE_TEST_FAILED` because the isolated artifact omitted the unchanged `ShippingNoteTranslationService.class.php` required by `ShippingPriceImporter.class.php`. `preflight` did not run and no database, runtime or production resource changed.
- Ahmed explicitly approved adding the dependency as support-only, rebuilding and reviewing the package, replacing only the exact installed M062 helper on canonical staging, then rerunning `compare` and conditional `preflight` only.
- The dependency must not enter the seven-file runtime/apply manifest. The corrected helper must require the live staging dependency to match the pinned support SHA-256 before compare/preflight/apply can continue.
- The replacement installer must fail if the helper or sudoers is absent, may upgrade only the exact installed prior helper SHA-256, must retain a root-only verified backup and automatically restore it on validation failure, and must contain no sudoers mutation path. Sudoers remains unchanged.
- `apply`, `verify`, `rollback`, `status`, database/schema mutation, authenticated acceptance and production remain outside this authorization.

## Corrected staging compare result — 2026-08-27

- The corrected helper installation and independent SHA/sudo-surface verification passed. Sudoers was not changed and no deploy command was executed by the installer.
- `compare` passed candidate tests and dependency parity but returned `DRIFT_COUNT=1` for `custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql`; schema state was `MIGRATION_REQUIRED`.
- Per the approved gate, `preflight` was not run. The release remains blocked pending a separate, read-only provenance review and a reviewed baseline decision for that exact file. No staging runtime or database mutation and no production access occurred.

## Verified staging prestate correction — 2026-08-27

- Ahmed approved the bounded read-only provenance review and requested the correction be finished quickly.
- Two independent reads through canonical staging localhost returned identical bytes for `llx_chinaplanetsp_rate.sql`, SHA-256 `13939d258df2c329616645f5ae7128e18e11df352d83bd6a93a27fc26f508831`.
- Those bytes are identical to Git commits `6cbd0ffbc6f7cc6030aaecad1d067c0969795c5e` and `2dff2fd76d8d4f6b5690c13828c3fcd58f2499b7`. The source-derived M062 baseline SHA `b2607e9d...` belongs to the later translation-schema source revision, which was never deployed as this SQL install file; the translation database columns were delivered separately by migration.
- The M062 prestate manifest is therefore corrected to the verified live SHA only. Runtime behavior remains unchanged. The artifact/helper/installer must be rebuilt, independently reviewed and checksum-rotated before repeating `compare` and conditional `preflight`; apply and production remain forbidden.

## Live terminal-qualifier correction — 2026-08-27

- The zero-drift compare passed after the verified prestate correction. Read-only preflight then reported 447 rows across 16 additional live destination spellings, all retained fail-closed rather than fuzzily guessed.
- Curated exact aliases now cover those 16 strings: Shuaiba for `KUWAIT(SHUAIBA)`; Alexandria TMT; Jeddah Red Sea Gateway/South/DPW variants; Khor Fakkan ICD; Sharjah ICD; the listed Umm Qasr North/South/berth variants; and Istanbul Mardas.
- The terminal relationships were verified from official port/terminal sources. No fuzzy threshold, edit cap or winner margin was relaxed. Partial and unknown composites remain rejected.
- Runtime commit `4dbb0cc0150b78140f15bdca992ab1f59bba2150` must be rebuilt/reviewed and may repeat only helper rotation, compare and conditional preflight. Apply and production remain forbidden.

## Composite-delimiter safety correction — 2026-08-27

- Security review blocked the first terminal-alias package before upload because punctuation-insensitive whole matching could collapse a top-level composite such as `KUWAIT/SHUAIBA` into the single parenthetical alias `KUWAIT(SHUAIBA)`.
- Parsing is now parenthesis-depth aware. Top-level `/`, `,`, `&`, `+` and `;` delimiters split destinations before normalized matching; delimiters inside parentheses remain qualifier text. Explicit literal aliases such as `KHALIFA PORT, ABU DHABI` remain supported without granting punctuation-equivalent composites the same identity.
- Regression coverage requires `KUWAIT(SHUAIBA)` to resolve to one Shuaiba destination while all five top-level delimiter variants resolve to both Kuwait and Shuaiba. Fuzzy thresholds and fail-closed composite behavior remain unchanged.
- Corrected runtime commit `525eca241a323d4ae073077a940de75d9d3b64e7` must receive renewed Shipping, Security and QA PASS before staging upload. Authorization remains helper rotation, `compare`, and conditional `preflight` only; apply, database mutation and production are forbidden.

## Mutable-alias delimiter hardening — 2026-08-27

- Renewed Security review found that a mutable legacy/database alias containing a top-level delimiter could still enter the literal whole-alias allowlist and bypass composite splitting.
- Literal whole-alias exceptions are now restricted to the reviewed built-in catalog. Legacy, manual and database aliases remain available for normal single-candidate matching but can never authorize bypass of top-level composite parsing.
- A database-plus-legacy regression fixture supplies `KUWAIT/SHUAIBA` as a same-owner mutable alias and requires the resolver to return both Kuwait and Shuaiba. Runtime commit `7f67d0c688adc9aeb8d94f52ac41b85da26d02fa` requires renewed three-role PASS before staging upload.
