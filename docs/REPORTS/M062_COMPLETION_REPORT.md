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

## Verified live-prestate correction — 2026-08-27

- Two independent canonical-staging localhost reads returned the same 1,369-byte ASCII SQL file, SHA-256 `13939d258df2c329616645f5ae7128e18e11df352d83bd6a93a27fc26f508831`. It is byte-identical to the Git version in commits `6cbd0ffbc6f7cc6030aaecad1d067c0969795c5e` and `2dff2fd76d8d4f6b5690c13828c3fcd58f2499b7`.
- The prior expected SHA `b2607e9d941bf13c14260777fe50253f428a8f8a9487d43e14d2efd943afe09d` includes the later shipping-note translation columns. Those database columns were deployed by the separate M041 migration, while the SQL install file itself remained on the older bytes; this is verified baseline provenance, not unexplained runtime drift.
- Corrected runtime/prestate commit: `d931a2be9e95167ecb08e9436913c765e52c1162`. The only payload-byte change from `c4cef25...` is the one prestate-manifest SHA; the seven candidate runtime files and five support files are unchanged.
- Corrected 15-file artifact `m062-port-normalization-d931a2b.tar.gz`: SHA-256 `cca7b7dab430d1e0e869764597e197fb258448d15ac0016d14ffd40abed3fdc3`. Corrected helper SHA-256: `32f62d30e783ae43b0353a9ce66fe10a9ec639788faf4fd6e791d2bde4e666fd`.
- Transactional helper-upgrade installer SHA-256: `dc22a1a0201b4138e68293f719bfda26a608d581f7328655785632050e4a9465`; it accepts only the currently installed helper SHA-256 `7fcafdcf379b9838846426b74460364f15521521c84f9e7862b2641a7cd7b2aa` or the new hash, leaves sudoers unchanged, and retains automatic restoration on validation failure.

## Live terminal-qualified aliases — 2026-08-27

- After corrected `compare` passed with `DRIFT_COUNT=0`, read-only `preflight` stopped at `DATA_PREFLIGHT_FAILED`. Its dry-run reported 447 still-unmapped rows across 16 strings; no database or runtime mutation occurred.
- Exact catalog aliases were added for the 16 reported strings. Verified canonical results are Shuaiba/Kuwait for `KUWAIT(SHUAIBA)`; Alexandria TMT/Egypt for TMT; Jeddah/Saudi Arabia for Red Sea Gateway, South and DPW variants; Khor Fakkan/UAE and Sharjah/UAE for their ICD variants; Umm Qasr/Iraq for North/South/berth qualifiers; and Istanbul/Turkiye for Mardas.

## Alexandria pricing-destination correction — local preparation, 2026-08-27

- `ALEXANDRIA`, `ALEXANDRIA OLD PORT`, and `ALEXANDRIA TMT` are now distinct canonical pricing destinations under Egypt. All three retain Alexandria's parent UN/LOCODE `EGALY`; database identity and price-card labels remain distinct.
- The previous built-in Old Port/TMT aliases may move away from generic Alexandria only when their active owner is exactly the expected prior built-in `ALEXANDRIA|EG` record. Manual or unexpected ownership fails closed.
- Reapplication inspects preserved `pod_raw` and corrects already-stored generic rows only when the raw source resolves to exactly one specific terminal. Plain `ALEXANDRIA` remains generic.
- This correction is local and pending renewed Shipping/Security/QA review and a separate staging gate. No staging or production mutation is included in this preparation.
- An isolated database copy of the current local 16,705-rate dataset contained 23 unrelated unresolved `Misurata/Benghazi Tripoli /Khoms` rows; those rows were excluded only from the disposable test copy so the Alexandria migration could be exercised without expanding this correction's mapping scope.
- On the remaining 16,682 copied rates, first apply passed and produced two canonical ports, two bounded built-in alias-row transfers present in that stale local copy (both Old Port spellings), 281 Old Port rates, and 169 TMT rates. The reviewed transition allowlist contains three normalized keys across two terminal groups; a database that already has the former TMT alias can therefore transfer three rows. Check and the full current-data audit passed with 116 unique active aliases and zero duplicate composite destinations.
- The security-hardened migration wraps catalog seeding, alias transfer, rate correction, and verification in one database transaction. Alias rows are locked, their built-in provenance and active status are checked, the conditional update repeats that prestate, and the resulting owner is verified before commit.
- Raw-rate correction is limited to Alexandria spellings and the three reviewed transition keys. It accepts only the exact generic `ALEXANDRIA|EG` source ID and the exact terminal target ID; any unexpected stored state fails closed with `RAW_RATE_CORRECTION_PRESTATE_INVALID`.
- Historical terminal reassignment additionally requires an exact resolver match. Fuzzy near-spellings such as `ALEXANDRIA TMTX` and `ALEXANDRIA OLD P0RT` may assist ordinary matching but can never move an existing generic price to a specific terminal.
- On a fresh isolated copy, the hardened first apply passed with `raw_corrected_rows=450`; check and current-data verification passed. A second apply passed with `seeded_ports=0`, `transferred_builtin_aliases=0`, `raw_corrected_rows=0`, and `cloned_rows=0`.
- Two rollback regressions were exercised independently: an inactive built-in predecessor failed with `CATALOG_ALIAS_INACTIVE_CONFLICT`, and an active same-target manual alias failed with `CATALOG_ALIAS_PROVENANCE_CONFLICT`. In both cases the transaction rolled back the inserted terminal port and preserved the original alias owner, source, and status.
- The temporary databases and files were removed. The original local database was restored to its verified pre-test counts of 16,705 rates, 100 aliases, zero Old Port/TMT canonical rows, and 169 still-generic TMT raw rows.
- The earlier `b1bc2c1` correction artifact and its helper/installer pins are withdrawn and must not be installed. The replacement frozen pins are recorded below after the hardened runtime is committed and rebuilt.
- Hardened runtime commit: `1ba3087d3cf37607ced37589d9cefe48a944264a`. Reproducible 15-file artifact: `/private/tmp/m062-port-normalization-1ba3087.tar.gz`, SHA-256 `76a9f1481596c641e1c8b4e3e00d8465fc42937f1e5ef11a9ca8696674d5d356`.
- Prepared R02 helper SHA-256: `cac210d431715011cfab063978205148e0fc2602d09666e521a6ae6cc5856be9`. Transactional installer SHA-256: `08a38c7cca3ccb8aea477ea6482c398d640bc12266711f38b092def0782787b7`; it accepts only the currently installed helper `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b` (or the exact new helper) and leaves sudoers unchanged at `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231`.
- The R02 helper accepts only the exact deployed R01 state as an upgrade predecessor, requires `MIGRATION_REQUIRED`, and refuses R02 `verify` or `rollback` while the prior release remains active. The current deployed seven-file runtime is the exact compare baseline.
- Official evidence confirms Shuaiba is a distinct Kuwait port; TMT is inside Alexandria Port; RSGT and DP World South Container Terminal are at Jeddah Islamic Port; Sharjah ICD is the emirate's inland depot; Umm Qasr North/South are zones of Umm Qasr; and Mardas is at Istanbul/Ambarli. The implementation uses exact aliases only and does not relax fuzzy safety.
- Runtime commit: `4dbb0cc0150b78140f15bdca992ab1f59bba2150`. Resolver coverage increases from 45 to 61 cases and built-in aliases from 100 to 116.
- Corrected 15-file artifact `m062-port-normalization-4dbb0cc.tar.gz`: SHA-256 `dbe96641c2efe12d23c5c775c101e66dddfa70e793f1bcbb284ae16568324f63`. Helper SHA-256: `4635ddf52fd9ff4a79bb231ee85ef482ef266fe6a2ccebc08224a03c73c5085f`.
- Transactional installer SHA-256: `20d2884f103515bcc1b3ecfe8dc59825434dbef86b71357e51499b17020c57ad`; it accepts only installed helper SHA-256 `32f62d30e783ae43b0353a9ce66fe10a9ec639788faf4fd6e791d2bde4e666fd` or the new helper, and leaves sudoers unchanged.

## Composite-delimiter security correction — 2026-08-27

- Security review correctly blocked the preceding package before staging upload: normalization made `KUWAIT(SHUAIBA)` collide with top-level composites such as `KUWAIT/SHUAIBA` when whole-string alias lookup ran first.
- The resolver now splits only top-level destination delimiters, preserves delimiters nested inside parentheses, and uses delimiter-preserving literal identity only for explicitly curated whole aliases such as `KHALIFA PORT, ABU DHABI`. Database aliases retain their literal spelling for this check.
- Five new negative-regression variants prove that slash, comma, ampersand, plus and semicolon inputs resolve to both `KUWAIT|KW` and `SHUAIBA|KW`; the parenthetical input still resolves exactly to `SHUAIBA|KW`. Resolver coverage is now 66 cases. Fuzzy threshold `0.90`, two-edit cap and winner margin `0.08` are unchanged.
- Corrected runtime commit: `525eca241a323d4ae073077a940de75d9d3b64e7`. Corrected 15-file artifact `m062-port-normalization-525eca2.tar.gz`: SHA-256 `3ec2073c80f8ea99ce01a8c1e975a9c6d0917c0a63b17253d672b43c37a7ac28`.
- Corrected helper SHA-256: `b6577abf57aeecba67476026b08ad0052931d7b7380fd421b19841768f241174`. Transactional installer SHA-256: `56cc432fb924dd014987a5a6eef47ae861c8e63b9fe3d5f4a31e208644f30ab9`; it accepts only the currently installed helper SHA-256 `32f62d30e783ae43b0353a9ce66fe10a9ec639788faf4fd6e791d2bde4e666fd` or the new helper and leaves sudoers unchanged.
- No corrected helper command has run. Staging runtime/database and production remain untouched pending renewed Shipping, Security and QA PASS.

## Mutable-alias delimiter hardening — 2026-08-27

- A second Security review blocked the package before upload because mutable legacy/database aliases could still populate the literal whole-alias allowlist.
- The allowlist is now built-in-only. A regression DB supplies the same `KUWAIT/SHUAIBA` text through both legacy and alias-table paths and proves it still resolves as the two-destination composite; curated `KHALIFA PORT, ABU DHABI` and parenthetical `KUWAIT(SHUAIBA)` remain exact single destinations.
- Final runtime commit: `7f67d0c688adc9aeb8d94f52ac41b85da26d02fa`. Final 15-file artifact `m062-port-normalization-7f67d0c.tar.gz`: SHA-256 `2275ffe1ba6fa0ea2e32bed43fa5c7b3dfaa5561691d50548a8f05609b078374`.
- Final helper SHA-256: `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`. Final installer SHA-256: `c44a9105aafff63c56458b61923cafb2eee9181d10353baf1d3ef704f154b959`; previous installed helper remains pinned to `32f62d30e783ae43b0353a9ce66fe10a9ec639788faf4fd6e791d2bde4e666fd` and sudoers remains unchanged.

## Final canonical-staging gate result — 2026-08-27

- Renewed Shipping, Security and QA reviews passed with no blockers for prep `69b52254b75044941e60aa8b80f8e3a490f495f3`, runtime `7f67d0c688adc9aeb8d94f52ac41b85da26d02fa`, artifact `2275ffe1ba6fa0ea2e32bed43fa5c7b3dfaa5561691d50548a8f05609b078374`, helper `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85` and installer `c44a9105aafff63c56458b61923cafb2eee9181d10353baf1d3ef704f154b959`.
- Root installation passed and independently verified the new helper as `root:root 0755`; artifact and installer remained `codex-deploy:codex-deploy 0644` with exact reviewed hashes. Sudoers stayed unchanged and the prior helper backup was retained.
- `compare`: PASS; `SCHEMA=MIGRATION_REQUIRED`; `DRIFT_COUNT=0`; support dependency current; `APPLY_EXECUTED=NO`; `PRODUCTION_ACCESSED=NO`.
- `preflight`: PASS. Its dry-run can return success only when the remaining destination list is empty, so the current candidate resolves every live destination value: unmapped rows `0`, unmapped values `0`, remaining destination warnings `0`. The prior dry-run total was 3,088 rows across 83 distinct destination values; this gate introduced no data change.
- No runtime file, schema, database, container configuration or business row was changed. Production was not accessed. The next gate is a separately approved staging `apply`, followed by `verify` and real UI/business acceptance; none was executed here.

## Staging apply failure — 2026-08-27

- Ahmed approved `apply` then conditional `verify` on canonical staging. The installed helper and artifact were independently rechecked at the reviewed SHA-256 pins immediately before apply.
- Apply repeated the preflight and `DRIFT_COUNT=0` comparison, created all backups, and ran the migration. Post-apply validation then failed on rate row `407`: `Unresolved or non-canonical port/country/id at rate row 407`.
- The helper stopped with `SCHEMA_POST_APPLY_INVALID`; automatic recovery could not certify the retained database state and recorded `APPLY_FAILED_DB_RECOVERY_REQUIRED`. Conditional `verify` was not executed.
- Read-only status and compare confirm: runtime code is back at the seven-file baseline, `DRIFT_COUNT=0`, schema is `READY`, staging HTTPS is `200`, and production was not accessed. Database changes from the attempted migration remain pending recovery.
- Code backup SHA-256: `dd1008fe64a5164d1dd7ecbcba3829913b2e393d0da071c380f7867636158023`; prestate SHA-256: `d9bfd6b5167f6fd3ae41fa255a86a8bdc912a98bf566e63f6980fb8619e44199`; full database backup SHA-256: `8d54253f375c5fc8d12d487b6c9c17924e4b9677ffe5403e57af013a701f72cc`.
- Next action is a bounded read-only diagnosis of row 407, then a separately approved choice between a checksum-validated full staging database restore or a reviewed corrective recovery. No reapply, verify or rollback is safe before that decision.

## Row-407 root cause — 2026-08-27

- The approved read-only SQL inspection showed rate row `407` as `AGADIR|MA`, raw value `AGADIR, Morocco`, method `exact`, score `1.0000`, and `fk_destination_port=NULL`. Active port row `48` is the correct `AGADIR|MA` owner and has its active built-in alias.
- A local database-backed resolver reproduction returned `fk_port=0` for the same literal input. Root cause: delimiter-preserving whole-literal matching correctly limits bypass to built-ins, but returns the built-in catalog object (`fk_port=0`) instead of the database-enriched alias owner (`fk_port=48`).
- The defect class is limited in the catalog to the two top-level-delimiter literals `AGADIR, MOROCCO` and `KHALIFA PORT, ABU DHABI`. No staging mutation was performed during diagnosis.
- Required source correction: use the built-in literal entry only as an expected-owner allowlist, obtain the resolved object from the normalized alias map, verify that its port/country equals the expected built-in owner, and otherwise fail closed. Add non-zero database FK tests for both literals plus the existing mutable-alias composite negatives.
- Because the helper terminal state is `APPLY_FAILED_DB_RECOVERY_REQUIRED`, correction alone cannot be reapplied. The recommended safe sequence is separately approved checksum-validated full staging database restore, corrected frozen package and renewed reviews, helper rotation, compare/preflight, then a new apply/verify approval.

## Approved staging recovery and corrected package — 2026-08-27

- Ahmed approved only the staging database restore from exact backup SHA-256 `8d54253f375c5fc8d12d487b6c9c17924e4b9677ffe5403e57af013a701f72cc`, local resolver/package correction and renewed reviews. Reapply, verify and production were excluded.
- The reviewed recovery tool is commit `a6deb93b9b65e28a16b6f9ed234d753db13b215c`, SHA-256 `77f6dcc0f1c1b688c67137fed40a65a02c4e6a04729b2316382678b635ed8584`. Its syntax, static contract and fault-injection suites pass. Security and QA returned PASS before execution.
- Independent restricted checks after the human root execution returned `M062_DEPLOY_STATUS=NOT_DEPLOYED` and compare `PASS` with `SCHEMA=MIGRATION_REQUIRED`, `DRIFT_COUNT=0`, all seven runtime paths at baseline and the support dependency current. This confirms the operational prestate needed for a future fresh apply. The human root output containing the new safety-backup path and hash was not supplied, so those values remain unverified here. Production was not accessed.
- Corrected runtime commit `e052d8bbc0607b1998007fa5088cf11197477c25` resolves approved delimiter literals through the authoritative normalized alias map after verifying the built-in expected owner. AGADIR now retains database `fk_port=48`; KHALIFA PORT retains database `fk_port=22`; mismatched mutable ownership is ambiguous and fails closed. Composite and fuzzy safety controls are unchanged.
- Frozen artifact `m062-port-normalization-e052d8b.tar.gz` contains exactly 15 allowlisted regular files and has SHA-256 `87a82a8069e82a240ca25d3cd63bb5503cac28e56965b592cef47beca7e5b825`.
- Prepared helper SHA-256: `ca1012d9d17fbf2efd20d6d0bdb010aece1f56c5864dd3daac76db0384540337`. Prepared transactional installer SHA-256: `afe0b497708334472d881ff928b9b437381cd110eff2ec8ebfb55f6ba8a3da43`; it accepts only the installed helper SHA-256 `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`. Sudoers remains SHA-256 `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231` and is not modified.
- Corrected helper/artifact upload, helper replacement, compare, preflight, apply and verify remain pending separate gates. No corrected deployment command ran.

## Database literal-link fail-closed correction — 2026-08-27

- Security review blocked the `e052d8b` package before upload because a DB-backed resolver could still return a same-owner built-in literal with `fk_port=0` when the active literal alias row was missing/inactive or the alias query failed.
- Final runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc` requires DB-backed delimiter literals to come from a successfully loaded active alias row with `source=database`, a positive `fk_port`, and the reviewed port/country owner. Missing rows, inactive rows, query failure and owner mismatch all fail closed.
- The only unlinked-literal mode is the explicit read-only migration `dry-run` while the alias table is absent. Apply, check, importer and admin resolution retain the strict linked-ID requirement. New regressions prove missing and unreadable alias paths are ambiguous and never return `fk_port=0`.
- Final reproducible 15-file artifact `m062-port-normalization-fef1734.tar.gz`: SHA-256 `5ab2c46df2a01fffbdbaac4df2e7a27f88bc721c386133e064e425a353bc011d`.
- Final prepared helper SHA-256: `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b`. Final prepared installer SHA-256: `80c64cb56639c2bcc6cb9bccc5c8347ca7f7c4046ab168d24823a78ae557e435`; prior installed helper remains `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`; sudoers remains unchanged.
- Renewed Shipping, Security and QA reviews all returned PASS for preparation commit `ff81e1a0a04c05e94b7ce7d33bd5cd993f03dfc7`, runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc` and the exact frozen artifact/helper/installer pins above.
- The blocked `e052d8b` candidate was not uploaded or installed.

## Final staging helper installed — 2026-08-27

- The final artifact, helper and transactional installer were uploaded only to canonical `backup-erp` staging. Inbox SHA-256 and `codex-deploy:codex-deploy 0644` metadata matched the reviewed pins before installation.
- Human-root installer output reported `M062_DEPLOY_HELPER_INSTALL=PASS`, predecessor upgrade `1`, retained backup `/root/m062-ports-helper-upgrade-backups/chinaplanetos-backup-erp-m062-ports.9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85.bak`, unchanged sudoers and no deployment/database/production activity.
- Independent restricted verification matched installed helper SHA-256 `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b`, metadata `root:root 0755`, and exactly the reviewed six-command sudo surface.
- No M062 helper command ran. Compare, conditional preflight, apply and verify remain pending separate authorization gates; production was not accessed.

## Final post-recovery staging compare/preflight — 2026-08-27

- Ahmed approved final `compare` and conditional `preflight` on canonical `backup-erp` only. Installed helper and inbox artifact hashes were independently rechecked immediately before execution.
- `M062_DEPLOY_COMPARE=PASS`: `SCHEMA=MIGRATION_REQUIRED`, `DRIFT_COUNT=0`, all seven runtime files at their reviewed baselines, and the live support dependency at its pinned hash.
- `M062_DEPLOY_PREFLIGHT=PASS`: release `M062-R01`, runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc`, artifact SHA-256 `5ab2c46df2a01fffbdbaac4df2e7a27f88bc721c386133e064e425a353bc011d`, `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, `PRODUCTION_ACCESSED=NO`.
- A non-fatal GNU awk escape warning preceded both successful results. No apply/verify/rollback/status command ran and no runtime, schema or business data changed. Apply remains a separate approval gate.

## Final post-recovery staging deployment — 2026-08-27

- Ahmed approved staging `apply` followed by conditional `verify`. Pinned helper/artifact identities and `NOT_DEPLOYED` prestate were rechecked before mutation.
- Apply request `m062-20260827T130509Z-6a3b15720ab1` returned `PASS` for release `M062-R01`, runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc`, and artifact SHA-256 `5ab2c46df2a01fffbdbaac4df2e7a27f88bc721c386133e064e425a353bc011d` with `DATABASE_CHANGED=YES` and `PRODUCTION_ACCESSED=NO`.
- Code backup: `/root/backup-erp-code-backups/m062-before-fef1734-m062-20260827T130509Z-6a3b15720ab1.tar.gz`, SHA-256 `dd1008fe64a5164d1dd7ecbcba3829913b2e393d0da071c380f7867636158023`.
- Full database backup: `/root/backup-erp-db-backups/m062-before-fef1734-m062-20260827T130509Z-6a3b15720ab1.sql`, SHA-256 `cf87acb47bae28bcabc23e1c64536a27cc119ef8b53880f5c2cdfdd11929349b`.
- Independent helper verify returned `PASS`, `STATE=DEPLOYED`, exact runtime/artifact pins, and `DATABASE_SCHEMA=READY`. Current-data validation passed with no remaining M062 unmapped destination country/port value.
- Remaining warnings/acceptance: the helper emits a non-fatal GNU awk escape warning; unrelated shipping-line normalization remains out of scope; authenticated staff/UI business acceptance has not yet run. Rollback and production were not invoked.
