# M083-R02 Production release preparation

- Runtime commit: `ecf151802ca8396c51214d758c6150cf1db49a9d`
- Target: `erp.visiontrading.biz` Production only
- State: local candidate being frozen; renewed independent Security and QA review pending
- Supersedes: M083-R01, rejected locally and never uploaded, installed, or executed

## Frozen pins

- Artifact `m083-r02-staff-first-ecf1518.tar.gz`: `9913967555a9a88b0c9e9dd3ab3f74dbe6474c8d4942c3f695c2d687c5447c1f`
- Restricted helper: `3c7579dd870898c06d5c03aa2c8fc2da242e0056aeb66cad54a5460a10efa5ca`
- Root installer: `a5bb0ae9f76fbcb4eac8f2b5e3bdde60cbbf459700de39a204ddd9b2d77109b7`
- Sudoers: `03b11843b7f0e5780e02c4cf68b356c2c439cb2110c72d7b71cac8c4f7e76a91`

The normalized artifact contains exactly 66 regular files: 57 runtime files, six self-contained support files, and three release manifests. Every member has uid/gid 0, an empty owner/group name, mode derived only from source executability, and timestamp zero; the gzip header also has timestamp zero. It contains no configuration, credential, key, database dump, document, log, symlink, Dolibarr core file, or release control executable.

## R02 corrections

- Candidate tests are self-contained inside the artifact; missing cross-Mission dependencies from R01 were removed.
- The helper rejects every mixed baseline/release state. `preflight` and `apply` require all 57 files at the exact M075 baseline; `verify` requires all 57 at the exact M083 release.
- The six additive tables are checked for exact column set, type, nullability, default, extra attributes, exact index set/order/uniqueness, primary key, and InnoDB engine.
- Zero, complete, partial, or mismatched managed-funds trigger states are distinguished. Exact empty partial schema can be removed after interrupted creation; altered schema fails closed.
- Production identity is bound to Compose project `vision-os`, ERP service `dolibarr`, DB service `mariadb`, volume `vision-os_mariadb_data`, database `dolibarr_db`, and the database host/IP actually used by the ERP container.
- Apply retains a root-owned exact artifact alongside the code backup, absent-file manifest, and full database backup.
- Automatic rollback removes schema only after the original code is restored and verified. Emergency manual rollback does not need the inbox or healthy public HTTP, validates all retained materials before mutation, restores code before schema removal, and reapplies candidate code if schema removal fails.
- Disposable local schema tests prove exact-schema acceptance, altered type and extra-index rejection, and cleanup of exact empty partial tables/guards.

## Boundary

No file has been pushed, uploaded, installed, compared, preflighted, or applied to Production by this preparation. Production actions remain subject to exact approval after R02 pins and both renewed independent reviews pass.
