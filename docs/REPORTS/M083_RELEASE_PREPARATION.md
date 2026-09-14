# M083-R05 Production release preparation

- Runtime commit: `d21c0ca30150e1b885586f1c337773b6c71f7802`
- Target: `erp.visiontrading.biz` Production only
- State: local candidate being frozen; renewed independent Security and QA review pending
- Supersedes: M083-R01 through M083-R04, rejected locally and never uploaded, installed, or executed

## Frozen pins

- Artifact `m083-r05-staff-first-d21c0ca.tar.gz`: `547dfce263119fd5d82a36b526f2820b9a369f7c2f23ecf8a8c4980e11dbe6c8`
- Restricted helper: `72cadcb113e33fbf4aac3dd5d132780c12c259ff528773ad9f62165e95363cd6`
- Root installer: `33c28d1d8553ac4386d4d9f8861ee6109ff57bcae07948c2bfedfc34a7269b93`
- Sudoers: `03b11843b7f0e5780e02c4cf68b356c2c439cb2110c72d7b71cac8c4f7e76a91`

The normalized artifact contains exactly 67 regular files: 58 runtime files, six self-contained support files, and three release manifests. Every member has uid/gid 0, an empty owner/group name, mode derived only from source executability, and timestamp zero; the gzip header also has timestamp zero. It contains no configuration, credential, key, database dump, document, log, symlink, Dolibarr core file, or release control executable.

## R05 corrections

- Candidate tests are self-contained inside the artifact; missing cross-Mission dependencies from R01 were removed.
- The helper rejects every mixed baseline/release state. `preflight` and `apply` require all 58 files at the exact M075 baseline; `verify` requires all 58 at the exact M083 release.
- The six additive tables are checked for exact column set, type, nullability, default, extra attributes, exact index set/order/uniqueness, primary key, and InnoDB engine.
- Zero, complete, partial, or mismatched managed-funds trigger states are distinguished. Exact empty partial schema can be removed after interrupted creation; altered schema fails closed.
- Production identity is bound to Compose project `vision-os`, ERP service `dolibarr`, DB service `mariadb`, volume `vision-os_mariadb_data`, database `dolibarr_db`, and the database host/IP actually used by the ERP container.
- Apply retains a root-owned exact artifact alongside the code backup, absent-file manifest, and full database backup.
- Automatic rollback removes schema only after the original code is restored and verified. Emergency manual rollback does not need the inbox or healthy public HTTP, validates all retained materials before mutation, restores code before schema removal, and reapplies candidate code if schema removal fails.
- Emergency rollback stages the retained artifact inside the ERP container before any schema check or removal, correcting the R02 defect found by independent QA.
- External customer order/project routes enforce the active entity explicitly. Supplier identity is redacted by default and is exposed only to authenticated internal staff views, correcting the R03 findings from independent Security review.
- The customer funding page is explicitly included in the runtime manifest and baseline, correcting the R04 packaging omission.
- Disposable local schema tests prove exact-schema acceptance, altered type and extra-index rejection, and cleanup of exact empty partial tables/guards.

## Boundary

No file has been pushed, uploaded, installed, compared, preflighted, or applied to Production by this preparation. Production actions remain subject to exact approval after R05 pins and both renewed independent reviews pass.
