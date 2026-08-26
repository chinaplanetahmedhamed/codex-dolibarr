# M062 Local Completion Report — Port and Country Normalization

## Control record

- Mission: M062
- Approved scope: local implementation and local-data migration only
- Baseline: `95402e7f22665c9efd61d648f1e545094aaf47c9`
- Branch: `codex/m062-port-normalization`
- Local Dolibarr: 23.0.3
- Module version: 1.5.0
- Result: `LOCAL_IMPLEMENTED_MIGRATED_AND_VERIFIED`
- Staging/production: not accessed or changed
- Outbound messages: none

## Delivered behavior

- One canonical resolver now owns destination normalization for workbook imports and manual rates.
- Exact curated/database aliases run first. Case, spacing, punctuation, Unicode punctuation and diacritics normalize consistently.
- Fuzzy matching is fail-closed: threshold 0.90, minimum normalized length 6, maximum 2 edits, matching first character and a minimum 0.08 margin over the next distinct canonical port.
- Only recognized China origins are removed before `--`; unknown prefixes and destination-as-origin strings remain unmapped.
- Only known operational qualifiers are stripped. Unknown terminal qualifiers such as `PORT SAID (EAST)` remain unmapped.
- Multi-destination values split only when every part resolves. The raw source string is retained on every resulting row.
- Manual add/edit uses the same resolver, rejects country mismatch or composite/unmapped input, stores match evidence and never creates or rewrites a canonical port as a side effect.
- Alias ownership is unique per entity. Administrator changes lock/recheck aliases in a transaction, reject cross-entity IDs and archive rather than delete mappings.

## Canonical mappings requested by Ahmed

| Source examples | Canonical port | Country | Code |
|---|---|---|---|
| `UMM AL QAWAIN`, `UMM AL QAIWAIN` | UMM AL QUWAIN | United Arab Emirates | AE / AEQIW |
| `SKIKDA` | SKIKDA | Algeria | DZ / DZSKI |
| `ORAN`, `ORAN CONTAINER TERMINAL` | ORAN | Algeria | DZ / DZORN |
| `KHOMS`, `EL KHOMS`, `AL KHOMS` | AL KHUMS | Libya | LY / LYKHO |
| `BEJAIA`, `Bejaia`, `BEJAÏA` | BEJAIA | Algeria | DZ / DZBJA |
| `AGADIR Morocco` | AGADIR | Morocco | MA / MAAGA |
| `Nador` | NADOR | Morocco | MA / MANDR |
| `DJEN DJEN`, `DJENDJEN` | DJEN-DJEN | Algeria | DZ / DZDJE |
| `ALGER`, `ALGIERS` | ALGIERS | Algeria | DZ / DZALG |
| `JAZAN` | JIZAN | Saudi Arabia | SA / SAGIZ |
| `MSIURATA` | MISURATA | Libya | LY / LYMRA |
| `SUDAN` | PORT SUDAN | Sudan | SD / SDPZU |

`SHEKOU-- SKIKDA, ANNABA` and `NANSHA-- SKIKDA, ANNABA` now produce separate SKIKDA and ANNABA destination rows in Algeria. `BEJAIA/SKIKDA`, `Oran/CASABLANCA`, `SFAX & SOUSSE`, `TUNIS / RADES`, `Rades,Tunis`, `Alger/Annaba` and the verified China-origin/Port Said strings use the same all-or-nothing rule.

## Schema and configuration

- Added entity-scoped `llx_chinaplanetsp_port_alias` with unique `(entity, normalized_alias)` ownership.
- Added `pod_raw`, `fk_destination_port`, `port_match_method`, `port_match_score` and destination-port index to shipping rates.
- Added default `CHINAPLANETSP_PORT_FUZZY_THRESHOLD=0.90`.
- No new rights. Existing Shipping Prices administrator/import/customer projection boundaries remain unchanged.
- No accounting, customer, supplier, cost, margin, document, email, WhatsApp or n8n data was added or exposed.

## Local data result

### Before

- 3,023 unmapped rate rows.
- 36 distinct unmapped POD strings.
- Batch 33 contained 33 destination warnings.

### First corrected apply

- 64 canonical ports seeded.
- 104 canonical alias operations.
- 3 duplicate legacy port records archived; none deleted.
- 16,546 existing rows normalized.
- 791 rows cloned for valid additional composite destinations.
- 1,422 resolved destination warnings removed.
- Remaining destination values: none.

### Final state and idempotency

- 16,705 rate rows.
- 0 unmapped destination rows.
- 1,456 rows carry composite audit evidence.
- 100 active unique normalized aliases; 0 duplicates.
- 0 destination warnings in all stored batch reports.
- Batch 33: 0 warnings.
- Two consecutive final migration reruns created 0 clones, removed 0 new warnings and ended with `M062_PORTS_CHECK=PASS`.

## Remaining warnings

There are 542 historical warnings across 23 distinct strings, all `Unmapped shipping line` warnings and outside M062. The latest batch has none. The names are variants of YANG MING, SEA LEGEND, JHS, AKKON, HYUNDAI, WANHAI, WHITE LINE, TSL, PEAK SHIPPING, RCL, FSCS and Zhonggu/中谷 with regional or Shenzhen/Guangzhou prefixes. They were deliberately retained because removing them would require a separate shipping-line normalization mission.

## Verification

- PHP lint: PASS for every changed/new PHP file.
- Resolver: PASS, 45 mapped/negative cases and 100 non-conflicting curated aliases.
- Import parser: PASS for two-destination split, unsafe short typo, and mapped POD with no usable price.
- Static shipping structure/privacy, CSRF, upload-limit, Palestine policy, import dependency, translation-source and publication-state regressions: PASS.
- Migration: final apply twice PASS; check PASS; current-data verification PASS.
- Composite duplicate query: 0 duplicate clones.
- `git diff --check`: PASS.
- Authenticated administration UI: PASS on desktop and 390×844; canonical mappings and Archive actions render, the unmapped table is empty, and no fatal/CSRF error appears.
- Independent Shipping review: PASS.
- Independent Security review: PASS.
- Independent QA review: PASS.

The existing public-search integration test is time-blocked because all local published rates are outside their 30-day validity window as of 2026-08-25. M062 did not bypass or extend the commercial validity rule. A current eligible test rate is required for later staging/production acceptance.

## Backup and rollback

- Full pre-M062 local database backup: `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/backups/m062-before-port-normalization-20260825T1035CST.sql`
- Size: 11,945,576 bytes.
- SHA-256: `200db83ce7d3c2b39a72bd699c10f8960addf223e0765bbaabe8d5bdea5c6441`.
- Restore was exercised once after the first migration attempt while correcting visibility preservation; the verified prestate returned to 3,023 unmapped rows before the corrected migration was applied.
- Local rollback is: restore the source baseline and the verified full database backup, then rerun the readiness/current-data checks. Any staging or production rollback needs a separately reviewed release package and approval.

## Changed areas

- Port resolver, workbook importer, manual-rate manager and port mapping administration.
- Module descriptor, rate schema and new alias schema.
- Idempotent M062 migration and focused tests.
- M044 compatibility regression, shipping module regression, Mission, changelog and portal/mobile parity documentation.

## Remaining release gates

- This is local evidence, not staging or production acceptance.
- Before external release: freeze a checksum-pinned artifact, take environment-specific code/database backups, run compare/preflight under separate approval, apply first to staging, and test with a currently valid published rate.
- No Git push, pull request, staging deployment or production deployment is included.

## Frozen staging release preparation — 2026-08-26

- Runtime/payload commit: `f697431b4ebf646bc2196e131e98e3bd53568e3e`.
- Canonical metadata-normalized artifact: `m062-port-normalization-f697431.tar.gz`; 14 regular files; SHA-256 `6200712277c4af51fb81bb40ea3ead9ad811035547d179128f029ea028b55e0e`.
- `deploy/tools/build_m062_artifact.py` rebuilds the artifact only from the pinned Git commit with USTAR format, root/root numeric identity, mode `0644`, one commit timestamp and gzip timestamp zero. A clean rebuild must match byte-for-byte.
- Runtime allowlist: 7 Shipping Prices files. Support allowlist: migration plus 3 focused tests. The artifact also carries the three pinned allowlist/prestate manifests.
- Mission-specific helper target: `/usr/local/sbin/chinaplanetos-backup-erp-m062-ports`; exact six-command surface: `compare`, `preflight`, `apply`, `verify`, `rollback`, `status`.
- Approved rollback policy: restore prior runtime files atomically while retaining additive schema and normalized/alias data; no database rewind.
- Future initial apply is schema-first; code is installed only after the candidate migration reports exact `READY`. Reapply after approved non-destructive rollback accepts `READY` only from the matching root-owned retained-schema states and skips the already-complete migration.
- A partial/non-transactional migration must pass both the exact migration check and the complete candidate current-data audit. Otherwise it enters `APPLY_FAILED_DB_RECOVERY_REQUIRED`, preserves the validated full database backup and refuses further apply. A crashed rollback likewise accepts retained schema only after the full audit; otherwise it enters `ROLLBACK_FAILED_DB_RECOVERY_REQUIRED`.
- The live-data test is dataset-aware: it requires zero unmapped rows, every normalized catalog alias key to exist actively in the database, and canonical port name, correct country and correct destination-port ID for every stored rate; unresolved pre-mapped rows are rejected too. It also requires complete canonical destinations for every individual composite source identity, zero resolved destination warnings and zero duplicate composite clones; it does not require named sample ports or composites to exist. Negative fixtures reject a wrong non-empty country, a stored alias instead of the canonical name, a missing/wrong port ID and a missing composite clone.
- Helper SHA-256: `34fad8e6e04304b5661206a1b7562e09641431b82deb06f658aa1fae6efc2c87`. Sudoers SHA-256: `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231`.
- Installer SHA-256: `b3c542155e4557c80989a14b3df2c76fbedbf0efe54b38949eec9e814e2408c8`. It installs only the helper and sudoers; it does not create operational backup/state directories and does not execute any helper command.
- Root bootstrap policy: copy the installer from the staging inbox to a new root-owned `0600` path, verify this externally recorded installer SHA-256 on that root-owned copy, then execute only the verified root copy. Never execute the writable inbox copy directly as root.
- Helper contract and fault/restart/recovery tests include command-level initial apply → rollback → retained-schema reapply, auto-rollback reapply, schema-before-code ordering and partial-migration classification. Production identifiers and the noncanonical duplicate staging stack are rejected.
- Ahmed approved helper installation on canonical `backup-erp` only without executing any helper command. Compare/preflight, apply/verify, authenticated acceptance and production remain separate gates.
- This installation gate creates only the two approved installed files. Required operational backup/state directories remain absent/deferred unless already present and must be provisioned under a later exact approval before `compare` or `preflight` can succeed.

## Staging helper installed — 2026-08-26

- Final preparation commit: `45d299a259b414b7775b434dac09fc17af7db09f`; runtime/payload commit remains `f697431b4ebf646bc2196e131e98e3bd53568e3e`.
- Final Shipping, Security and QA reviews: `PASS`, with no findings. A clean checkout reproduced the 14-file artifact byte-for-byte.
- Server-side inbox hashes matched artifact `6200712277c4af51fb81bb40ea3ead9ad811035547d179128f029ea028b55e0e`, helper `34fad8e6e04304b5661206a1b7562e09641431b82deb06f658aa1fae6efc2c87`, installer `b3c542155e4557c80989a14b3df2c76fbedbf0efe54b38949eec9e814e2408c8` and sudoers `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231`; each inbox file was `codex-deploy:codex-deploy 0644`.
- The root-owned `0600` installer copy passed the externally pinned SHA-256 check. Installer result: `M062_DEPLOY_HELPER_INSTALL=PASS`, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, `PRODUCTION_ACCESSED=NO`.
- Independent restricted verification: installed helper `/usr/local/sbin/chinaplanetos-backup-erp-m062-ports` is `root:root 0755`, size `45432`, with the exact reviewed helper SHA-256. `sudo -n -l` exposes exactly the six reviewed M062 commands and no additional M062 command.
- No helper command was executed. Shipping Prices runtime and database were not deployed or changed by this gate. Compare/preflight, operational-directory setup, apply/verify, authenticated acceptance and production remain separate approvals.

## Dependency-closed staging correction — 2026-08-27

- The first approved `compare` after operational-directory setup stopped before schema/data inspection with `M062_DEPLOY_FAIL=CANDIDATE_TEST_FAILED`: the isolated candidate lacked `custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php`, which the importer requires. `preflight` did not run.
- Baseline and corrected runtime commits contain identical dependency bytes, SHA-256 `1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54`.
- Corrected runtime/payload commit: `c4cef25a8dc6bd2ffedd180d6ac8a68428057746`. The dependency is support-only; the runtime/apply manifest remains seven files.
- Corrected metadata-normalized artifact: `m062-port-normalization-c4cef25.tar.gz`; 15 regular files; SHA-256 `90ac84416a9c0d7b8b8f8aee0849607e829db252ada036a142caa69ad9008ce4`.
- Corrected helper SHA-256: `7fcafdcf379b9838846426b74460364f15521521c84f9e7862b2641a7cd7b2aa`. It requires the live support dependency to be `www-data:www-data 0644` and match the pinned SHA before reporting compare/preflight success.
- Replacement installer SHA-256: `13841f57b297439e82375819ad1f59568cdc6307a5651dd0d1187298ab92313a`. It requires the helper and sudoers to already exist in their exact reviewed states, upgrades only prior helper SHA-256 `34fad8e6e04304b5661206a1b7562e09641431b82deb06f658aa1fae6efc2c87`, creates the root-only exact backup atomically and restores it automatically if post-install validation fails. It has no sudoers creation, replacement or removal path and reports `SUDOERS_CHANGED=NO`; sudoers remains SHA-256 `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231`.
- Approved next gate after renewed Shipping/Security/QA PASS: upload corrected artifact/helper/installer, replace helper only on canonical staging, independently verify it, run `compare`, and run `preflight` only if compare is zero-drift. No apply, database change or production access is authorized.

## Corrected staging compare — 2026-08-27

- Final preparation commit `f5f5b96e249dde88186f0a6596a8a0e71a6b49cb` passed renewed Shipping, Security and QA reviews with no blockers. Artifact, helper, installer and unchanged sudoers were verified server-side at their frozen SHA-256 values before installation.
- The root-owned installer copy passed SHA verification and upgraded only the prior helper. It reported `UPGRADED_PRIOR_HELPER=1`, `SUDOERS_CHANGED=NO`, `DEPLOY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.
- Independent restricted verification confirmed helper SHA-256 `7fcafdcf379b9838846426b74460364f15521521c84f9e7862b2641a7cd7b2aa`, artifact SHA-256 `90ac84416a9c0d7b8b8f8aee0849607e829db252ada036a142caa69ad9008ce4`, installer SHA-256 `13841f57b297439e82375819ad1f59568cdc6307a5651dd0d1187298ab92313a`, and exactly six M062 sudo commands.
- Corrected `compare` passed candidate lint/tests and the live support dependency parity gate. Schema classification was `MIGRATION_REQUIRED`. Six runtime files matched their expected baseline states, while `custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql` was `DRIFT`; final result was `M062_DEPLOY_COMPARE=PASS ... DRIFT_COUNT=1 APPLY_EXECUTED=NO PRODUCTION_ACCESSED=NO`.
- The approved zero-drift condition was not met, so `preflight` did not run. No code, schema, database, container, configuration or business data changed. The next gate is a bounded read-only investigation of the exact live schema-file provenance; the drift must not be accepted or overwritten without a reviewed baseline decision and separate approval.
