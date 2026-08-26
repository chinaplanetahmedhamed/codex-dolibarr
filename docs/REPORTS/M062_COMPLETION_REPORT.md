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

- Runtime/payload commit: `b8fb049082d5ee11ed20c529d5474ed24a07a93a`.
- Canonical metadata-normalized artifact: `m062-port-normalization-b8fb049.tar.gz`; 14 regular files; SHA-256 `7ae30289096a6b903c5c52ea01c5361c0955363d22e37368645923b059cd8dab`.
- `deploy/tools/build_m062_artifact.py` rebuilds the artifact only from the pinned Git commit with USTAR format, root/root numeric identity, mode `0644`, one commit timestamp and gzip timestamp zero. A clean rebuild must match byte-for-byte.
- Runtime allowlist: 7 Shipping Prices files. Support allowlist: migration plus 3 focused tests. The artifact also carries the three pinned allowlist/prestate manifests.
- Mission-specific helper target: `/usr/local/sbin/chinaplanetos-backup-erp-m062-ports`; exact six-command surface: `compare`, `preflight`, `apply`, `verify`, `rollback`, `status`.
- Approved rollback policy: restore prior runtime files atomically while retaining additive schema and normalized/alias data; no database rewind.
- Future initial apply is schema-first; code is installed only after the candidate migration reports exact `READY`. Reapply after approved non-destructive rollback accepts `READY` only from the matching root-owned retained-schema states and skips the already-complete migration.
- A partial/non-transactional migration must pass both the exact migration check and the complete candidate current-data audit. Otherwise it enters `APPLY_FAILED_DB_RECOVERY_REQUIRED`, preserves the validated full database backup and refuses further apply. A crashed rollback likewise accepts retained schema only after the full audit; otherwise it enters `ROLLBACK_FAILED_DB_RECOVERY_REQUIRED`.
- The live-data test is dataset-aware: it requires zero unmapped rows, every normalized catalog alias key to exist actively in the database, and canonical port name, correct country and correct destination-port ID for every stored rate; unresolved pre-mapped rows are rejected too. It also requires complete canonical destinations for every individual composite source identity, zero resolved destination warnings and zero duplicate composite clones; it does not require named sample ports or composites to exist. Negative fixtures reject a wrong non-empty country, a stored alias instead of the canonical name, a missing/wrong port ID and a missing composite clone.
- Helper SHA-256: `cdd33b28974fc95714e31dcbd5b4bd6ff82014de510854b1488d1bb987826340`. Sudoers SHA-256: `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231`.
- Installer SHA-256: `70ec96f29081d7313b30a2600876e8653525bf44c8e72ef8e6f063fd464e6d1d`. It installs only the helper and sudoers; it does not create operational backup/state directories and does not execute any helper command.
- Root bootstrap policy: copy the installer from the staging inbox to a new root-owned `0600` path, verify this externally recorded installer SHA-256 on that root-owned copy, then execute only the verified root copy. Never execute the writable inbox copy directly as root.
- Helper contract and fault/restart/recovery tests include command-level initial apply → rollback → retained-schema reapply, auto-rollback reapply, schema-before-code ordering and partial-migration classification. Production identifiers and the noncanonical duplicate staging stack are rejected.
- Ahmed approved helper installation on canonical `backup-erp` only without executing any helper command. Compare/preflight, apply/verify, authenticated acceptance and production remain separate gates.
- This installation gate creates only the two approved installed files. Required operational backup/state directories remain absent/deferred unless already present and must be provisioned under a later exact approval before `compare` or `preflight` can succeed.
