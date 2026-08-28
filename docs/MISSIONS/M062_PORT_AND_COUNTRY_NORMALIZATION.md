# M062 — Port and country normalization

## Alexandria terminal-history correction authorization — 2026-08-28

- Ahmed approved correcting the grouped Alexandria chart after confirming that the public selector should remain one `ALEXANDRIA` option while price cards identify `ALEXANDRIA OLD PORT` and `ALEXANDRIA TMT`.
- The public trend must query each exact pricing destination independently and return labelled terminal series. It must never average Old Port, TMT and generic Alexandria into one line.
- Generic historical `ALEXANDRIA` data may appear only as a separate terminal-not-specified series. Different currencies must use separate chart axes rather than being compared as one currency.
- Existing exact-route `points` remain backward compatible. Public output remains allowlisted and must not expose rate IDs, fingerprints, carrier identity fields, cost, supplier or margin data.
- Local ERP gateway and React implementation, tests and documentation are authorized. Staging and production deployment remain separate exact approval gates.

## Alexandria terminal-history R04 staging authorization — 2026-08-28

- Ahmed approved the announced ERP-first staging gate: deploy and verify the corrected public gateway on canonical `backup-erp`, then deploy React to `new.chinaplanet.org` through GitHub Actions and complete desktop/390px acceptance. Production remains forbidden.
- Frozen ERP runtime commit is `f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b`; frozen React commit is `53da1bfd6e8bc92bd1a5f73eda5ee2306d716d0f`.
- R04 is code-only and may replace exactly `custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php`. It must not run a migration or modify database, schema, configuration, module state, business data, documents, containers, volumes, ports, proxy or DNS.
- The accepted staging prestate is the independently rechecked R03 gateway SHA-256 `189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4`; installed R03 helper SHA-256 is `b8f945538e38bfef34eb4d83cbb39b83f0bf39eb6a8e77c3cb47c0aebf6af0ba` and durable status is `DEPLOYED`.
- Deterministic eight-file artifact SHA-256 is `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c`. R04 helper SHA-256 is `e849e93691481962085b5313917a3c10f21f2ebc7fe5366fadb5564ee48047ac`; installer SHA-256 is `ce100832a7035981d40e89be791a3ab20839deadd01ac3c6fd9cf5088c2a35fb`; existing sudoers remains unchanged at `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231`.
- Source Shipping/Portal and QA reviews returned `PASS`; Security returned `PASS_WITH_CONDITIONS` requiring exact Git pins, dependency-closed ERP-first staging deployment, payload/privacy checks, and real viewport acceptance. The frozen R04 package/helper/installer still requires independent release Security/QA review before upload.
- Required order is helper/artifact upload and checksum verification, helper-only replacement, `compare`, `preflight`, `apply`, independent `verify`, gateway payload/failure acceptance, then GitHub React staging deployment and desktop/390px Arabic/English acceptance. Stop and restore/reconcile on the first failure.

## Alexandria terminal-history R04 staging completion — 2026-08-29

- Independent frozen-package Security and QA reviews returned `PASS`. Uploaded artifact, helper and installer hashes matched the reviewed pins; the installed helper matched `e849e93691481962085b5313917a3c10f21f2ebc7fe5366fadb5564ee48047ac` and the existing sudoers remained unchanged.
- Restricted `compare`, `preflight`, `apply` and independent `verify` all passed on canonical `backup-erp`. Durable state is `DEPLOYED` for runtime `f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b`, scope `3d9aefed5e7b534cf4d4782a3ebc15fb73a3ab67` and artifact `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c`.
- Rollback evidence is `/root/backup-erp-code-backups/m062-r04-before-f0d8d33-20260828T170604Z.tar.gz` at SHA-256 `60c754d418b7cf0a0db28efc3cac3177c92f52dfd02e68f90fdde5b4f677a476`; prestate SHA-256 is `5a26fac12a8e1f69f904247fb52e080a7fedfc2b9cb63bb37dd11f294ed60501`.
- Live grouped API acceptance returned `points: []` plus exact `ALEXANDRIA OLD PORT` and `ALEXANDRIA TMT` series, each limited to `date`, `price` and `currency`. Exact TMT retained the backward-compatible flat `points` contract. No private cost, supplier, margin, rate ID, fingerprint or carrier field was exposed.
- GitHub Actions run `33193378034` deployed exact React commit `53da1bfd6e8bc92bd1a5f73eda5ee2306d716d0f` successfully to `new.chinaplanet.org`. Live desktop `1440x900` and mobile `390x844` acceptance passed in Arabic RTL and English LTR: cards name their exact terminal and the chart shows two labelled, non-averaged terminal lines without horizontal overflow or browser console errors.
- All ERP helper commands reported `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`. Production remained untouched. The only remaining deployment warning is GitHub's non-blocking deprecation notice for Node.js 20-based action versions forced onto Node.js 24.

## Alexandria terminal-history R04 production preparation — 2026-08-29

- Ahmed requested pushing the staging-accepted terminal-history correction live. This authorizes read-only production discovery and local production-package preparation; helper installation, `compare`/`preflight`, `apply`/`verify`, and live business acceptance remain separate production gates.
- Restricted production `status` and `compare` reconfirmed base release `M062-PROD-R01` is `DEPLOYED` at runtime `8dab3b90ba335ad99b5d61d12c731021fc92f1d7`, artifact `add7cbc918c1af489f3b107bf2156eb6b9debb0c43f76a936e782c4cfe21aae7`, schema `READY`, all eight files current and `DRIFT_COUNT=0`. The exact production gateway prestate is SHA-256 `189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4`.
- Production React remains on deploy SHA `234d9efa68b9197bbce2982156a67c67ff0d0178`; accepted R04 staging and branch remain on exact React SHA `53da1bfd6e8bc92bd1a5f73eda5ee2306d716d0f`.
- Production R04 reuses the byte-identical staging-accepted eight-member artifact as `/private/tmp/m062-production-terminal-trend-f0d8d33.tar.gz`, SHA-256 `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c`. Runtime scope remains exactly one file: `custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php`; the other members are support-only manifests/tests.
- The new production-only helper is a separate path and state/rollback domain, leaving the base M062 production helper and database state untouched. Frozen helper SHA-256 is `24648c70da56cb105aa2679269cd8e1f532c0e0f345d08826b56af1a40f21b85`; sudoers SHA-256 is `db06230225a8ae124ffcdb6b05c73f043859e1568863579aa9e81510a2d68abd`; transactional installer SHA-256 is `3f6cb66aa3dd967a762139b7a6c57884b3bd6187697ae998c206e96b840daba7`. Every helper invocation now independently requires the exact root-owned sudoers file and hash.
- GitHub workflow `.github/workflows/deploy-m062-r04-production.yml` SHA-256 is `9645ca7f6c5549551dda7ccaaf2fc11a62696fe5daace14b026283fc17e28794`. It separates helper-package upload from deployment, verifies local and uploaded artifact/helper/installer/sudoers hashes, pins the accepted staging and base-production helper hashes, and runs production `compare`, `preflight`, `apply`, independent `verify`/`status`, with rollback after a successful apply if post-apply verification fails.
- Local deterministic rebuild, syntax, production-target isolation, exact six-command sudo surface, candidate PHP/token/Alexandria tests, staging regression, apply auto-restore and rollback-reapply fault tests all pass. The release contains no migration or database command and every production helper result declares `DATABASE_CHANGED=NO`.
- The first frozen preparation commit `a0a3111c3868e556a3a1997c886071adf0343508` was blocked by Security/QA because the deployed workflow/helper did not independently bind the installed sudoers hash and did not pin both prerequisite helper hashes. The rotated pins above correct those blockers and require renewed Shipping, Security and QA review before any upload or installation. Production runtime and React remain unchanged at this checkpoint.

## Production release preparation — 2026-08-28

- After successful ERP and React staging acceptance, Ahmed requested that M062 be pushed live. This records production intent and authorizes read-only production discovery plus local release preparation; it does not authorize installing an unreviewed helper or mutating production before the required production review and deployment gates pass.
- Read-only public inspection found production still exposes legacy Egypt POD values including `ALEXANDIRA OLD PORT`, `ALEXANDIRA OLD PORT （calling AICT）` and `Alexandria（TMT）`. Production React reports deploy SHA `234d9efa68b9197bbce2982156a67c67ff0d0178`; accepted staging reports `41257e59ec4b44869960ddfbf0d7b66f9cf670b2`.
- Restricted `sudo -n -l` confirms no production M062 helper or sudoers surface is installed. The staging M062 helper remains staging-only and must not be reused against production.
- The first prepared production package was rejected before upload by all three reviewers because its prestate manifest incorrectly used staging post-R01 hashes. The corrected prestate is derived from the production pre-M062 source lineage shared by commits `95402e7`, `606b54c` and the active M060 source `4cb98b3`: six exact hashes plus `ABSENT` for the resolver and alias SQL. All derived pins are rotated and the rejected package must never be installed.
- Corrected production release `M062-PROD-R01` combines the eight current M062 runtime files, including the Alexandria public gateway, with the idempotent M062 migration and exact supporting tests. The metadata-free 19-file artifact `/private/tmp/m062-production-1d42fdb.tar.gz` has SHA-256 `694b029d61777c4b542f5d9f56f0586e936f8b161fd939af3149c8b171b9f3f3` and runtime commit `1d42fdb77214ca5f7b8613bc16fde8a04cc5dc64`.
- Final production helper SHA-256 is `59aa892b00fa06f31c747abd84d494f960dbd1b7b91f918906310f334ec37306`; sudoers SHA-256 is `4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf`; final transactional installer SHA-256 is `bb8538d678c4183d9e4c1dbd8465ba6108e62d7555a6f96b9df2db61fa760922`. The surface is exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
- Local artifact reproducibility, helper/installer syntax, production-target isolation, fixed sudo surface, dependency pins, real restore/reapply primitives, restart reconciliation and fault-injection recovery tests pass. Shipping, Security and QA must independently review these exact pins before upload or installation.
- Security rejected the first corrected helper before upload because per-file baseline/current acceptance allowed a partial mixed prestate. The final helper counts exact states and requires all eight runtime paths to be the verified baseline (six hashes plus two `ABSENT`) for preflight and every apply/reapply entry. Any baseline/current mixture is classified as drift; all-current is accepted only through recorded deployed-state verification/reconciliation. The final pins below must be rotated and re-reviewed.
- Required live order is gated: (1) reviewed upload and helper/sudoers install only; (2) separately approved `compare` and conditional read-only `preflight`; (3) separately approved ERP `apply` and `verify` with full code/database backups; (4) public ERP API acceptance; (5) merge the exact accepted React tree to `main`, redeploy and reaccept staging from `main`; (6) dispatch the checksum-resolved production React workflow and verify desktop/390px RTL/LTR. Any failure stops the sequence and preserves the last verified live state.

## Production helper installation — 2026-08-28

- Ahmed approved upload and installation of the reviewed `M062-PROD-R01` helper/sudoers only, with no helper command and no database change.
- All four inbox files were uploaded as `codex-deploy:codex-deploy 0644` and independently matched their reviewed hashes: artifact `694b029d61777c4b542f5d9f56f0586e936f8b161fd939af3149c8b171b9f3f3`, helper `59aa892b00fa06f31c747abd84d494f960dbd1b7b91f918906310f334ec37306`, sudoers `4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf`, and installer `bb8538d678c4183d9e4c1dbd8465ba6108e62d7555a6f96b9df2db61fa760922`.
- Independent restricted verification after the human root install confirms `/usr/local/sbin/chinaplanetos-production-m062-ports` is `root:root 0755`, size `48357`, at the exact reviewed helper hash. `sudo -n -l` exposes exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` for that path.
- No M062 helper command has run. Production runtime, schema, database, business data, documents, configuration and containers remain unchanged. The next gate is separately approved `compare` followed by conditional read-only `preflight` only.

## Production compare drift diagnosis — 2026-08-28

- Ahmed approved production `compare` followed by conditional read-only `preflight`. The installed helper and inbox artifact matched their reviewed pins before execution.
- `compare` stopped the gate with `BASELINE_COUNT=7`, `CURRENT_COUNT=0`, `MIXED_STATE=0`, and `DRIFT_COUNT=1`; conditional `preflight` did not run. The only drifted path was `custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql`. No runtime, schema, database or business-data mutation occurred.
- Two independent cache-bypassed HTTPS reads returned identical live bytes for that SQL file, size `1369`, SHA-256 `13939d258df2c329616645f5ae7128e18e11df352d83bd6a93a27fc26f508831`, with public `Last-Modified` dated 2026-07-16. The bytes match Git commits `6cbd0ffbc6f7cc6030aaecad1d067c0969795c5e` and `2dff2fd76d8d4f6b5690c13828c3fcd58f2499b7`.
- The rejected manifest SHA `b2607e9d941bf13c14260777fe50253f428a8f8a9487d43e14d2efd943afe09d` is the later translation-schema source revision from `f0b722183419ca2cfc38cb4939ae6b9d12b6d1fa`; that SQL install file was not deployed to production because the translation columns were delivered separately by migration. The production prestate manifest is corrected to the verified live SHA only; runtime behavior remains unchanged.
- The artifact, helper and installer must now be rebuilt with rotated pins, tested and independently reviewed. Any upload, helper replacement, repeated `compare`/`preflight`, `apply`, `verify`, database change or React production deployment remains outside this local correction gate.

## Corrected production prestate package — 2026-08-28

- Corrected source commit is `d6c2953bda1ab6f7c74c99385782542fd853299a`. The 19-file deterministic artifact is `/private/tmp/m062-production-d6c2953.tar.gz`, SHA-256 `e64dae7c857bc77071368393ea4d5cb50ecc63474798e344a2c35c2554924696`; only the reviewed production prestate manifest changed inside the artifact and the eight runtime payload files remain byte-identical to the previously accepted release.
- Corrected helper SHA-256 is `9bb10f73b8ccb1928c15bf2d0b23af17cd9aa1bda7f5578cec70f8b951f56b9f`. It keeps the exact production targets and six-command sudo surface, requires the verified `8 baseline / 0 current / 0 drift` state, and pins the corrected prestate manifest SHA-256 `b89d37ec17e96d11963470d296868b1a66e0ee12ec4a3c2121398786a587bbe0`.
- Upgrade installer SHA-256 is `631b232bdc36057724e078cbaefe90969c0bef6cd5e6cd448e190802a1136e87`. It accepts only the installed predecessor helper SHA-256 `59aa892b00fa06f31c747abd84d494f960dbd1b7b91f918906310f334ec37306`, requires the existing unchanged sudoers SHA-256 `4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf`, retains a root-only predecessor backup, and automatically restores it on post-replacement validation failure.
- Local syntax, deterministic rebuild, production-only contract, exact baseline/mixed-state rejection, dependency-pin, path-isolation, no-clobber audit, restart reconciliation, apply-fault recovery, rollback reapply and static resolver/importer/Alexandria/token suites pass. The environment-backed current-data test is intentionally deferred to helper preflight/verify because the local database is not the accepted staging/production dataset.
- Shipping, Security and QA must independently review these exact pins before any upload or installation. Production remains unchanged; helper rotation and rerunning `compare`/conditional `preflight` require a new exact approval.

## Corrected production package review — 2026-08-28

- Shipping, Security and QA independently returned `PASS` with no conditions or blockers for frozen preparation commit `8c9e49c73afd238963a7be3020d3e3a16876b877`, corrected source commit `d6c2953bda1ab6f7c74c99385782542fd853299a`, artifact/helper/installer pins `e64dae7c...` / `9bb10f73...` / `631b232b...`, and unchanged sudoers `4ae95799...`.
- Independent rebuild and member comparison confirmed the 19-file artifact is deterministic, exactly one payload member changed from the previously accepted artifact (the production prestate manifest), and all eight runtime plus eight support files remain byte-identical.
- Reviewers verified the corrected SQL prestate provenance, exact predecessor-helper gate, root-only durable backup, automatic restore, absence of a sudoers mutation path, production-only fixed targets, strict `8 baseline / 0 current / 0 drift` gate, path isolation, no-clobber audit, restart reconciliation and fail-closed recovery behavior.
- No reviewer accessed the server or changed runtime/database state. The corrected package is ready for a separately approved upload and helper-only replacement; no helper command may run under that installation approval.

## Corrected production helper replacement — 2026-08-28

- Ahmed approved uploading the corrected artifact/helper/installer/sudoers to the production inbox and replacing only the installed M062 helper, without running any helper command or changing the database.
- All four inbox files were independently verified as `codex-deploy:codex-deploy 0644` at the reviewed hashes: artifact `e64dae7c857bc77071368393ea4d5cb50ecc63474798e344a2c35c2554924696`, helper `9bb10f73b8ccb1928c15bf2d0b23af17cd9aa1bda7f5578cec70f8b951f56b9f`, installer `631b232bdc36057724e078cbaefe90969c0bef6cd5e6cd448e190802a1136e87`, and unchanged sudoers `4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf`.
- Independent restricted verification after the root installation confirms `/usr/local/sbin/chinaplanetos-production-m062-ports` is `root:root 0755`, size `48357`, at the exact corrected helper hash. `sudo -n -l` exposes exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` for that helper path. The inbox artifact remains at its exact reviewed hash and production ERP returned HTTPS `200`.
- No M062 helper command ran during this gate. Runtime files, schema, database, business data, documents and configuration were not changed. The next gate is separately approved `compare` followed by conditional read-only `preflight`; `apply` and `verify` remain forbidden.

## Corrected production compare and preflight — 2026-08-28

- Ahmed separately approved production `compare` followed by conditional read-only `preflight`, with `apply` and database mutation forbidden. Immediately before execution, the installed helper and inbox artifact matched exact reviewed SHA-256 values `9bb10f73b8ccb1928c15bf2d0b23af17cd9aa1bda7f5578cec70f8b951f56b9f` and `e64dae7c857bc77071368393ea4d5cb50ecc63474798e344a2c35c2554924696`.
- `compare` returned `PASS` for `M062-PROD-R01`: `BASELINE_COUNT=8`, `CURRENT_COUNT=0`, `MIXED_STATE=0`, `DRIFT_COUNT=0`; both pinned support dependencies were current and schema was the expected `MIGRATION_REQUIRED`.
- Conditional `preflight` returned `PASS` for runtime commit `d6c2953bda1ab6f7c74c99385782542fd853299a` and the exact artifact. The migration dry-run passed, candidate tests passed, baseline remained `8/0/0`, and the command reported `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO`, `PRODUCTION_ACCESSED=YES`.
- The reviewed helper intentionally exposes the dry-run as a pass/fail gate and does not print row-level production data or mutation counts on success; no unsupported production count is claimed. Runtime, schema, database and business data remain unchanged. Work stops before separately approved production `apply` and conditional `verify`, which will create and validate full code and database backups before mutation.

## Production apply failure and recovery gate — 2026-08-28

- Ahmed approved completing all remaining M062 production deployment gates. Immediately before mutation, helper and artifact pins matched and helper `status` was `NOT_DEPLOYED`; production ERP returned HTTPS `200`.
- `apply` repeated zero-drift `8 baseline / 0 current` validation, created and validated code/database backups, then the migration failed with `M062_PORTS=FAIL REASON=LEGACY_ALIAS_CONFLICT`. Automatic recovery could not return the database to its exact prestate and stopped fail-closed with `DATABASE_RECOVERY_REQUIRED=YES`.
- Durable helper state is `APPLY_FAILED_DB_RECOVERY_REQUIRED`. Runtime code was restored to all eight reviewed baseline states with `DRIFT_COUNT=0`, and ERP remained HTTPS `200`. The partially changed database reports 46 unresolved rows across two exact literals: `KHALIFA PORT, ABU DHABI` (24) and `AGADIR, Morocco` (22), both `literal_alias_owner_unavailable`; `catalog_alias_ownership_drift=116` and `raw_rate_drift=0`.
- Full pre-apply database backup is `/root/chinaplanetos-production-db-backups/m062-before-d6c2953-m062-20260828T070839Z-4f2d6bf8b151.sql`, SHA-256 `c9e94e44f375100c586264241269cbf1521894a2a2d4df5aba0b3dc79602f578`. Code backup is `/root/chinaplanetos-production-code-backups/m062-before-d6c2953-m062-20260828T070839Z-4f2d6bf8b151.tar.gz`, SHA-256 `8f6af6126dca3404a393d08e7796fdb89e1b44dc87e4e3cb92b6c384127874d8`.
- `verify` and all React merge/deploy/acceptance steps did not run. Do not invoke M062 `apply`, `verify` or `rollback` in this state. The next action is a separately checksum-pinned production database recovery using the recorded backup, with a validated safety backup of the partial state, exact state archival, rollback-on-recovery-failure and post-restore prestate/HTTP verification.

## Production database recovery and corrected alias package — 2026-08-28

- Ahmed approved completing the production recovery and remaining deployment gates. Recovery R03 validated the exact source backup SHA-256 `c9e94e44f375100c586264241269cbf1521894a2a2d4df5aba0b3dc79602f578`, prestate schema absence, row `407 = CASABLANCA`, the 24 Khalifa and 22 Agadir source rows, and a full database fingerprint before touching the active database.
- Recovery created and restore-tested `/root/chinaplanetos-production-db-backups/m062-recovery-20260828T101809Z-8d55404b-partial-state-safety.sql` with SHA-256 `9187d306976eb5408a27756d97f04c55a1c0009d3f99ba975f6094b2c5a99b8b`, restored the original database, matched source fingerprint `52e5135d4a25e7938a16f76348ef1017a43697e1b7067b22c9bcd6dbb911f322`, archived the failed state, and returned ERP HTTPS `200`. Independent restricted checks show `M062_DEPLOY_STATUS=NOT_DEPLOYED`, schema `MIGRATION_REQUIRED`, exact runtime baseline `8/0/0`, and no drift.
- Corrected runtime commit is `8dab3b90ba335ad99b5d61d12c731021fc92f1d7`. It adds a punctuation-preserving, owner/country-bounded transition only for the reviewed literal `KHALIFA PORT, ABU DHABI`, keeps `/`, `;`, `+`, `&` and Unicode punctuation variants fail-closed for ownership transfer, and shares one legacy parser between read-only preflight and mutation. Exact `ALEXANDIRA OLD PORT （calling AICT）` and `...ACCHC）` spellings belong to canonical Alexandria Old Port so legacy duplicate rows are archived before alias import. Alexandria generic, Old Port and TMT remain distinct.
- Deterministic 19-file production artifact `/private/tmp/m062-production-8dab3b9.tar.gz` has SHA-256 `add7cbc918c1af489f3b107bf2156eb6b9debb0c43f76a936e782c4cfe21aae7`. Rotated production helper SHA-256 is `a1a769dbdb4f4cdfb0f304a54566a98aad73710ba1a2054d8c026919a8892cc4`; installer SHA-256 is `94b33fb3b9f76e1a6c986de2a7b772fd95117d35f035ebf5cee6484af9ed1e37`; sudoers remains `4ae95799f8bd69744d5e09ec4ab8f598052f9a2482952ab41e7d3ee1b2b0ebbf`.
- Recovery, deterministic artifact, production helper, resolver/importer, Alexandria grouping, token, legacy-conflict and adversarial delimiter tests pass. Shipping, Security and QA independently approved the corrected migration/recovery logic. The environment-backed current-data audit remains a post-apply verify gate rather than a local-data claim.

## Alexandria public-search staging deployment authorization — 2026-08-28

- Ahmed explicitly approved deploying the ERP gateway to staging and verifying it, then deploying React staging only after ERP verification, followed by desktop/390px acceptance. Production remains forbidden.
- ERP release `M062-R03` is code-only: exactly `custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php`. It must not run a migration or change database, schema, configuration, module state, business data, documents, containers, proxy, DNS or ports.
- The frozen artifact must include the gateway's price-service and note-translation dependencies as support-only candidate-test closure; neither dependency enters the one-file runtime/apply manifest.
- The installed M062-R02 helper cannot deploy this new file. R03 therefore uses a checksum-pinned replacement at the same already-approved six-command path and the unchanged sudoers surface. It keeps R02 state/backups intact and uses independent R03 state and rollback paths.
- Any apply failure after mutation must restore the exact baseline before exit. Any rollback failure after restoring the baseline must re-apply the exact R03 file and preserve deployed state; deterministic fault tests are a release gate.
- Required order: install/verify the R03 helper, `compare`, `preflight`, `apply`, `verify`, and public ERP API acceptance; only then may the React commit be deployed to `new.chinaplanet.org` and accepted at desktop/390px in RTL/LTR.
- If ERP apply or verify fails, stop and use only the R03 recorded code rollback. React must not deploy. No production command, URL, container, file, database or workflow is authorized.

## Alexandria public-search grouping authorization — 2026-08-28

- Ahmed approved showing one public destination option, `ALEXANDRIA`, while returning separate price cards for generic Alexandria, Alexandria Old Port and Alexandria TMT when eligible rates exist.
- Canonical ports, aliases, rate rows and terminal-specific prices remain distinct. This is a public-search projection only; it must not merge database records or assume equal prices.
- Each returned card and booking token must retain the exact priced destination. Generic Alexandria must be labelled as terminal not specified rather than guessed as Old Port or TMT.
- A mixed historical trend across Alexandria terminals is forbidden because it could misrepresent terminal-specific prices.
- Local Dolibarr gateway and React implementation, tests, documentation and renewed Shipping/Security/QA review are approved. Any ERP staging update, React staging deployment or production action remains a separate exact approval gate.
- Staging release order is mandatory: deploy and verify the Dolibarr public gateway first, then deploy React, then run desktop/390px RTL/LTR search and exact-terminal booking acceptance. React-first is forbidden because the grouped request is not understood by the old gateway and could temporarily hide Old Port/TMT prices.

## Alexandria terminal correction authorization — 2026-08-27

- Ahmed approved correcting the model so `ALEXANDRIA`, `ALEXANDRIA OLD PORT`, and `ALEXANDRIA TMT` remain three distinct pricing destinations in Egypt.
- Generic `ALEXANDRIA` remains generic when the source does not identify a terminal; it must not be guessed as Old Port or TMT.
- Local implementation, migration correction, tests, release packaging, and renewed Shipping/Security/QA review are authorized.
- Staging upload, helper replacement or commands, and all production access remain separate, unapproved gates.

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
| Shipping correction | Independent Shipping reviewer | PASS_WITH_CONDITIONS | 2026-08-28 | One public option, three exact terminal cards/tokens and equal-price non-merging verified; gateway-first staging order required |
| Security correction | Independent Security reviewer | PASS_WITH_CONDITIONS | 2026-08-28 | Public allowlist, EG-only expansion, encryption/revalidation, dedupe/cap and trend suppression verified; direct-API country validation retained as P2 follow-up |
| QA correction | Independent QA reviewer | PASS | 2026-08-28 | PHP lint/regressions, 48 server tests, 45 UI/library tests, build and diff checks passed; real staging viewport acceptance remains pending |

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

## Final staging compare and preflight — 2026-08-27

- Shipping, Security and QA renewed reviews all passed for preparation commit `69b52254b75044941e60aa8b80f8e3a490f495f3`, runtime commit `7f67d0c688adc9aeb8d94f52ac41b85da26d02fa` and artifact SHA-256 `2275ffe1ba6fa0ea2e32bed43fa5c7b3dfaa5561691d50548a8f05609b078374`.
- The transactional installer upgraded only the exact prior helper to SHA-256 `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`, retained the verified prior-helper backup and reported no sudoers, deployment, database or production change. Independent restricted verification matched the installed helper, artifact and installer pins.
- `compare` passed with `DRIFT_COUNT=0`, all seven runtime paths in their expected baseline states, the support dependency current, and schema classified `MIGRATION_REQUIRED`.
- The read-only migration dry-run inside `preflight` passed, which requires an empty remaining list and therefore zero unmapped rows and zero unmapped values. `preflight` finished `PASS` with `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.
- Work stops at the approved gate. Runtime code, schema and business data remain unchanged; `apply` requires a separate explicit approval.

## Staging apply failure and recovery gate — 2026-08-27

- Ahmed explicitly approved staging `apply` followed by `verify`. Exact helper and artifact pins were reverified before mutation; the helper repeated preflight and zero-drift compare, then created code, prestate and full-database backups.
- The additive migration ran, but post-apply current-data validation failed at `rate row 407` with `Unresolved or non-canonical port/country/id`. The helper stopped fail closed and returned `M062_DEPLOY_FAIL=SCHEMA_POST_APPLY_INVALID` followed by `M062_DEPLOY_AUTO_ROLLBACK=FAIL DATABASE_RECOVERY_REQUIRED=YES`.
- Durable state is `APPLY_FAILED_DB_RECOVERY_REQUIRED`. Runtime compare confirms all seven files were restored to their baseline states with `DRIFT_COUNT=0`; schema is `READY`, meaning the additive/partially normalized database state remains. Staging HTTPS remains `200`. `verify` did not run and production was not accessed.
- Full database backup is `/root/backup-erp-db-backups/m062-before-7f67d0c-m062-20260827T104336Z-f36ab1403e42.sql`, SHA-256 `8d54253f375c5fc8d12d487b6c9c17924e4b9677ffe5403e57af013a701f72cc`. Full restore is destructive and requires separate explicit approval after a bounded read-only row-407 diagnosis; do not reapply, verify or invoke rollback meanwhile.

## Row-407 read-only diagnosis — 2026-08-27

- Ahmed approved a bounded read-only diagnosis. Row `407` contains canonical `pod='AGADIR'`, raw `pod_raw='AGADIR, Morocco'`, country `MA`, exact score `1.0000`, but `fk_destination_port=NULL`; active canonical port `AGADIR|MA` exists as row `48` with alias `AGADIR` owned by that port.
- Deterministic local reproduction with a database-backed resolver returned the correct owner but `fk_port=0` for `AGADIR, Morocco`. The top-level curated-literal path returns the built-in catalog object directly before consulting the database-enriched normalized alias, dropping the real port ID.
- Only two built-in literal exceptions contain top-level delimiters: `AGADIR, MOROCCO` and `KHALIFA PORT, ABU DHABI`. The correction must retain the built-in-only literal allowlist but resolve its expected owner through the authoritative normalized alias and fail closed if the owner differs. Add database-backed non-zero-FK regression coverage for both values.
- Current helper state forbids reapply from `APPLY_FAILED_DB_RECOVERY_REQUIRED`. Safest recovery remains an exact full staging DB restore from the recorded backup, followed by a newly reviewed correction package and fresh compare/preflight/apply/verify gates. Restore and correction are not yet authorized.

## Approved staging database recovery and literal-FK correction — 2026-08-27

- Ahmed approved a checksum-pinned staging-only full restore from database backup SHA-256 `8d54253f375c5fc8d12d487b6c9c17924e4b9677ffe5403e57af013a701f72cc`, plus local correction and renewed reviews. Reapply, verify and production remained forbidden.
- Recovery tool commit `a6deb93b9b65e28a16b6f9ed234d753db13b215c` was independently reviewed by Security and QA. Tool SHA-256 `77f6dcc0f1c1b688c67137fed40a65a02c4e6a04729b2316382678b635ed8584`; syntax, contract and fault-injection suites passed.
- Independent post-recovery checks returned `M062_DEPLOY_STATUS=NOT_DEPLOYED`, `SCHEMA=MIGRATION_REQUIRED`, all seven runtime paths at baseline, support dependency current and `DRIFT_COUNT=0`. Production was not accessed. The root command output containing the recovery safety-backup path/hash was not supplied to Agent 007 and is therefore not claimed here.
- Runtime correction commit `e052d8bbc0607b1998007fa5088cf11197477c25` treats built-in delimiter literals only as expected-owner allowlists, resolves the authoritative database object, preserves non-zero `fk_port`, and fails closed on owner mismatch. Database-backed regressions cover AGADIR row `48`, KHALIFA PORT row `22`, and conflicting ownership.
- Corrected 15-file artifact `m062-port-normalization-e052d8b.tar.gz` has SHA-256 `87a82a8069e82a240ca25d3cd63bb5503cac28e56965b592cef47beca7e5b825`. Prepared helper SHA-256 is `ca1012d9d17fbf2efd20d6d0bdb010aece1f56c5864dd3daac76db0384540337`; transactional installer SHA-256 is `afe0b497708334472d881ff928b9b437381cd110eff2ec8ebfb55f6ba8a3da43`, accepting only installed helper SHA-256 `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`. Sudoers remains unchanged.
- Upload, helper replacement, compare, preflight, apply and verify are not authorized by this correction gate and have not run.

## Database literal-link fail-closed hardening — 2026-08-27

- Renewed Security review blocked the preceding corrected package before upload: with a database connection but a missing/inactive/unreadable literal alias row, the normalized map could fall back to the same-owner built-in object and again return `fk_port=0`.
- Final runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc` records alias-table state explicitly. Runtime/apply/check resolvers require a loaded active database alias, `source=database`, non-zero `fk_port`, and exact expected port/country ownership for delimiter literals. Missing rows and query failures return `literal_alias_owner_unavailable` and remain ambiguous.
- Only the migration's read-only `dry-run`, before the alias table exists, may opt into built-in literal resolvability without a port ID. No importer, admin, apply or check caller uses that mode. Regression fixtures cover missing alias rows, failed alias queries and the explicit pre-migration-only path.
- Final 15-file artifact `m062-port-normalization-fef1734.tar.gz` SHA-256 is `5ab2c46df2a01fffbdbaac4df2e7a27f88bc721c386133e064e425a353bc011d`. Prepared helper SHA-256 is `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b`; installer SHA-256 is `80c64cb56639c2bcc6cb9bccc5c8347ca7f7c4046ab168d24823a78ae557e435`, still accepting only installed helper SHA-256 `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`. Sudoers remains unchanged.
- Shipping, Security and QA independently returned PASS for preparation commit `ff81e1a0a04c05e94b7ce7d33bd5cd993f03dfc7`, runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc`, the exact artifact/helper/installer pins above, unchanged sudoers and installed-predecessor gate.
- The blocked `e052d8b` package was never uploaded or installed.

## Final staging package upload and helper replacement — 2026-08-27

- Ahmed approved upload of the final artifact/helper/installer to canonical `backup-erp` staging and replacement of the M062 helper only. No helper command or production access was authorized.
- Uploaded artifact `m062-port-normalization-fef1734.tar.gz`, helper and installer matched the reviewed SHA-256 pins and were independently confirmed as `codex-deploy:codex-deploy 0644` in the staging inbox.
- The transactional installer reported `PASS`, upgraded only predecessor helper SHA-256 `9cc8ecd3323d43be0f15865f1aff73faa008ba135316867087e3589068f73e85`, retained its verified root-only backup, and installed helper SHA-256 `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b`. Sudoers SHA-256 remained `958ad27c1e7aae16c6b7a8b297e23fcb2a5847666085b0f82493e31f2b651231` and was not changed.
- Independent restricted verification confirmed `/usr/local/sbin/chinaplanetos-backup-erp-m062-ports` is `root:root 0755` at the exact final helper hash and exposes only `compare`, `preflight`, `apply`, `verify`, `rollback` and `status` through sudo.
- No M062 helper command ran, no runtime/schema/business data changed and production was not accessed. `compare`, conditional `preflight`, `apply` and `verify` remain separate authorization gates.

## Final staging compare and preflight after recovery — 2026-08-27

- Ahmed approved `compare` followed by conditional `preflight` on canonical `backup-erp` staging only. `apply`, `verify` and production remained forbidden.
- Immediately before execution, the installed helper and inbox artifact independently matched final SHA-256 values `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b` and `5ab2c46df2a01fffbdbaac4df2e7a27f88bc721c386133e064e425a353bc011d`.
- `compare` returned `PASS`: all seven runtime paths matched their reviewed baseline states, the support dependency matched SHA-256 `1b95c85261868d56ee4bf9b25bbd2eae30876cf74b6ff8be6093f23e337fbe54`, schema was `MIGRATION_REQUIRED`, and `DRIFT_COUNT=0`.
- Conditional `preflight` returned `PASS` for release `M062-R01`, runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc` and the exact final artifact. It reported `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.
- Both successful commands emitted the same non-fatal GNU awk escape warning before their PASS markers. No runtime/schema/business-data mutation occurred. Work stops before `apply`, which requires separate explicit approval.

## Final staging apply and verify after recovery — 2026-08-27

- Ahmed approved `apply` followed by conditional `verify` on canonical `backup-erp` staging only. Production remained forbidden.
- Immediately before mutation, installed helper and artifact hashes matched the final reviewed pins and helper `status` returned `M062_DEPLOY_STATUS=NOT_DEPLOYED`.
- `apply` returned `PASS` for request `m062-20260827T130509Z-6a3b15720ab1`, release `M062-R01`, runtime commit `fef173463ef6c2031f0f37c83a006b6802c8c3fc` and artifact SHA-256 `5ab2c46df2a01fffbdbaac4df2e7a27f88bc721c386133e064e425a353bc011d`.
- The helper created code backup `/root/backup-erp-code-backups/m062-before-fef1734-m062-20260827T130509Z-6a3b15720ab1.tar.gz`, SHA-256 `dd1008fe64a5164d1dd7ecbcba3829913b2e393d0da071c380f7867636158023`, and full database backup `/root/backup-erp-db-backups/m062-before-fef1734-m062-20260827T130509Z-6a3b15720ab1.sql`, SHA-256 `cf87acb47bae28bcabc23e1c64536a27cc119ef8b53880f5c2cdfdd11929349b`.
- Conditional `verify` returned `PASS` with state `DEPLOYED`, exact runtime/artifact pins and `DATABASE_SCHEMA=READY`. Current-data validation passed, so the verified M062 unmapped destination country/port remainder is zero; unrelated shipping-line normalization remains outside scope.
- The non-fatal GNU awk escape warning remains in helper output. Rollback was not invoked, authenticated business/UI acceptance remains pending, and production was not accessed.

## Alexandria R02 helper, compare and preflight — 2026-08-27

- Ahmed explicitly approved uploading the exact Alexandria R02 artifact/helper/installer to canonical `backup-erp`, replacing only the M062 helper, then running `compare` and conditional `preflight`.
- Uploaded artifact SHA-256 `2ed9848435086c5be6f385db03d6035836832cc11d28a2e9d3a0c41732cdf201`, helper SHA-256 `6cb72966c1a6e88dcd0f7071727f524a7259eae2f0b337ffabadc24cc86435f4`, and installer SHA-256 `9c3b2fcb484a8af4aafbc580ed7fd66432f2eb98a577ff40ea5304af948f0c0a` matched locally and in the staging inbox.
- The installer replaced only exact predecessor helper SHA-256 `03413c0143cea33925f9faa4994d2034febc5933ee84e2d00494f413d78cd43b`. Independent restricted verification confirmed the installed helper is `root:root 0755` at the new hash and exposes exactly the existing six commands. Sudoers was not changed.
- `compare` passed for `M062-R02`, runtime commit `ce6e87afe0058d1b4939f3c5f0f0090d14db2972`, with `DRIFT_COUNT=0` and expected `SCHEMA=MIGRATION_REQUIRED`. It reported zero unmapped rows/values, three catalog-alias ownership transitions and 123 historical Alexandria rate rows requiring the reviewed exact-only correction.
- Conditional `preflight` passed with `APPLY_EXECUTED=NO`, `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`. Work stops before `apply`, which requires a separate explicit approval.

## Alexandria R02 staging apply and verify — 2026-08-27

- After being informed that the R02 migration would change three catalog-alias owners and 123 historical Alexandria rate rows, Ahmed explicitly approved staging `apply` followed by conditional `verify`.
- Immediately before mutation, the installed helper and inbox artifact matched their exact reviewed SHA-256 pins. The helper repeated zero-drift compare and successful preflight before creating backups.
- Apply request `m062-20260827T155220Z-1fe6e4575dfe` passed for release `M062-R02`, runtime commit `ce6e87afe0058d1b4939f3c5f0f0090d14db2972`, and artifact SHA-256 `2ed9848435086c5be6f385db03d6035836832cc11d28a2e9d3a0c41732cdf201`.
- Code backup `/root/backup-erp-code-backups/m062-before-ce6e87a-m062-20260827T155220Z-1fe6e4575dfe.tar.gz` has SHA-256 `7816781f0f329fb0071a846e8e163da78b92c799fb1a94bb123d4c825373369d`. Full database backup `/root/backup-erp-db-backups/m062-before-ce6e87a-m062-20260827T155220Z-1fe6e4575dfe.sql` has SHA-256 `e80651d780b26d820364134c3c97925a0a2c830274e9dca1dce211d806db1dbd`.
- Conditional `verify` passed with `STATE=DEPLOYED`, exact runtime/artifact pins and `DATABASE_SCHEMA=READY`. The helper reported `DATABASE_CHANGED=YES` for apply, `DATABASE_MUTATED_BY_COMMAND=NO` for verify, and `PRODUCTION_ACCESSED=NO` for both.
- Rollback was not invoked. Authenticated price-card/UI acceptance remains a separate business check; production remains unchanged and forbidden without a new exact approval.

## R04 production package upload and helper installation — 2026-08-29

- Ahmed approved only the production GitHub environment protection attempt, checksum-pinned `prepare` upload, and installation of the R04 helper/sudoers. No helper command or runtime/database change was authorized.
- GitHub rejected the required-reviewer rule with HTTP `422` because the private-repository billing plan does not support it; the existing `production` environment remained unchanged with no protection rule.
- Initial prepare run `33199525293` stopped before SSH/upload because the contract tests used macOS-only `/private/tmp` on a Linux runner. Commit `98b9b4a1d8c7eb1da5d8b31b8623cc1aab208977` changed only the two test temp roots to portable, helper-allowlisted `/tmp`; Shipping, Security and QA re-reviewed it `PASS`. PR `#41` merged as `4ca0f87173a759a715f55cd4937daae402700884` without changing any frozen release pin.
- GitHub prepare run `33199956743` passed and uploaded the artifact, helper, installer and sudoers only. Independent restricted checks confirmed `codex-deploy:codex-deploy 0644` and exact SHA-256 values `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c`, `24648c70da56cb105aa2679269cd8e1f532c0e0f345d08826b56af1a40f21b85`, `3f6cb66aa3dd967a762139b7a6c57884b3bd6187697ae998c206e96b840daba7`, and `db06230225a8ae124ffcdb6b05c73f043859e1568863579aa9e81510a2d68abd`.
- The root installer returned `M062_R04_PRODUCTION_HELPER_INSTALL=PASS`, with the exact helper/sudoers hashes, six-command surface, `DEPLOY_EXECUTED=NO`, and `DATABASE_CHANGED=NO`. Independent restricted verification confirms `/usr/local/sbin/chinaplanetos-production-m062-r04-terminal-trend` is `root:root 0755`, size `24812`, at the reviewed helper hash, and exposes exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
- The existing base production helper remains unchanged at SHA-256 `964347ae88550901a10f9f72d56d89804d891ac5c818f0ba7f0172dd29a3687f`. No R04 helper command ran; production runtime, schema, database and business data remain unchanged. The next gate is separately approved `compare` followed by conditional read-only `preflight`; `apply` and `verify` remain forbidden.

## R04 production compare and preflight — 2026-08-29

- Ahmed separately approved production `compare` followed by conditional read-only `preflight` for the exact R04 package; `apply`, `verify` and database/runtime mutation remained forbidden.
- Immediately before execution, installed helper SHA-256 `24648c70da56cb105aa2679269cd8e1f532c0e0f345d08826b56af1a40f21b85` and inbox artifact SHA-256 `a295723c4b1646ca8794494a46bede7ae21ac7296edd40d50f6c6d2cb13eb03c` matched the reviewed pins. Metadata remained `root:root 0755` for the helper and `codex-deploy:codex-deploy 0644` for the artifact; sudo exposed exactly the six reviewed commands.
- `compare` returned `PASS` for release `M062-PROD-R04`. The only runtime file was in the expected `UPDATE_REQUIRED` state: production baseline SHA-256 `189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4`, candidate SHA-256 `e8f83e15178811a83430e742d47e9477de92f33c8a135e15f858ec0dd536098b`.
- Conditional `preflight` returned `PASS` for runtime commit `f0d8d33c2efd83cf981516a24ab9c9e07b1bc05b`, exact artifact, and one-file scope. It reported `DATABASE_CHANGED=NO`; no apply, verify, rollback or status command ran.
- Production runtime, database, schema, business data and React remain unchanged. The next gate is separately approved production `apply` followed by conditional independent `verify`; rollback may run only if the reviewed helper automatically requires it after a failed post-apply validation.
