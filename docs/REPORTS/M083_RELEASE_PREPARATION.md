# M083-R04 Production release preparation

- Runtime commit: `d21c0ca30150e1b885586f1c337773b6c71f7802`
- Target: `erp.visiontrading.biz` Production only
- State: local candidate being frozen; renewed independent Security and QA review pending
- Supersedes: M083-R01 through M083-R03, rejected locally and never uploaded, installed, or executed

## Frozen pins

- Artifact `m083-r04-staff-first-d21c0ca.tar.gz`: `91d2954e78c6e7861b96212722ca4e775cbb7b12d44854be0cbf409b1737708c`
- Restricted helper: `fb6c4d0e25364a9f19c1ef38ca72c2afc33f866749b06773db7aee30f790c8a8`
- Root installer: `e28fd9018dbeea7c7eed6c56302799e9657f099820f49050629ac2ed22ddd1bb`
- Sudoers: `03b11843b7f0e5780e02c4cf68b356c2c439cb2110c72d7b71cac8c4f7e76a91`

The normalized artifact contains exactly 66 regular files: 57 runtime files, six self-contained support files, and three release manifests. Every member has uid/gid 0, an empty owner/group name, mode derived only from source executability, and timestamp zero; the gzip header also has timestamp zero. It contains no configuration, credential, key, database dump, document, log, symlink, Dolibarr core file, or release control executable.

## R04 corrections

- Candidate tests are self-contained inside the artifact; missing cross-Mission dependencies from R01 were removed.
- The helper rejects every mixed baseline/release state. `preflight` and `apply` require all 57 files at the exact M075 baseline; `verify` requires all 57 at the exact M083 release.
- The six additive tables are checked for exact column set, type, nullability, default, extra attributes, exact index set/order/uniqueness, primary key, and InnoDB engine.
- Zero, complete, partial, or mismatched managed-funds trigger states are distinguished. Exact empty partial schema can be removed after interrupted creation; altered schema fails closed.
- Production identity is bound to Compose project `vision-os`, ERP service `dolibarr`, DB service `mariadb`, volume `vision-os_mariadb_data`, database `dolibarr_db`, and the database host/IP actually used by the ERP container.
- Apply retains a root-owned exact artifact alongside the code backup, absent-file manifest, and full database backup.
- Automatic rollback removes schema only after the original code is restored and verified. Emergency manual rollback does not need the inbox or healthy public HTTP, validates all retained materials before mutation, restores code before schema removal, and reapplies candidate code if schema removal fails.
- Emergency rollback stages the retained artifact inside the ERP container before any schema check or removal, correcting the R02 defect found by independent QA.
- External customer order/project routes enforce the active entity explicitly. Supplier identity is redacted by default and is exposed only to authenticated internal staff views, correcting the R03 findings from independent Security review.
- Disposable local schema tests prove exact-schema acceptance, altered type and extra-index rejection, and cleanup of exact empty partial tables/guards.

## Boundary

No file has been pushed, uploaded, installed, compared, preflighted, or applied to Production by this preparation. Production actions remain subject to exact approval after R04 pins and both renewed independent reviews pass.
