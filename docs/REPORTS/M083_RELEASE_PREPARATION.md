# M083-R06 Production release preparation

- Runtime commit: `d21c0ca30150e1b885586f1c337773b6c71f7802`
- Target: `erp.visiontrading.biz` Production only
- State: corrected R06 candidate frozen locally; renewed independent Security and QA review pending
- Supersedes: M083-R01 through M083-R04 rejected locally, and R05 stopped safely during Production compare before mutation

## Frozen pins

- Artifact `m083-r06-staff-first-d21c0ca.tar.gz`: `ad500e2a4c933bff8068b119784bc57d1da7bbdccea3adceab89c7ad94e3af6e`
- Restricted helper: `a3edfeb615f7a6a3a9c7a2eee7c1d3fa8d5af139d0fdb6fcc410c65ddf80a309`
- Root installer: `3c2772897187d243fa35b5e25aea41795eb873e4c926b10d6dd08bdba856c4ac`
- Sudoers: `03b11843b7f0e5780e02c4cf68b356c2c439cb2110c72d7b71cac8c4f7e76a91`

The normalized artifact contains exactly 67 regular files: 58 runtime files, six self-contained support files, and three release manifests. Every member has uid/gid 0, an empty owner/group name, mode derived only from source executability, and timestamp zero; the gzip header also has timestamp zero. It contains no configuration, credential, key, database dump, document, log, symlink, Dolibarr core file, or release control executable.

## R06 corrections

- Candidate tests are self-contained inside the artifact; missing cross-Mission dependencies from R01 were removed.
- The helper rejects every mixed baseline/release state. `preflight` and `apply` require all 58 files at the exact deployed M076-R07 baseline; `verify` requires all 58 at the exact M083 release.
- The six additive tables are checked for exact column set, type, nullability, default, extra attributes, exact index set/order/uniqueness, primary key, and InnoDB engine.
- Zero, complete, partial, or mismatched managed-funds trigger states are distinguished. Exact empty partial schema can be removed after interrupted creation; altered schema fails closed.
- Production identity is bound to Compose project `vision-os`, ERP service `dolibarr`, DB service `mariadb`, volume `vision-os_mariadb_data`, database `dolibarr_db`, and the database host/IP actually used by the ERP container.
- Apply retains a root-owned exact artifact alongside the code backup, absent-file manifest, and full database backup.
- Automatic rollback removes schema only after the original code is restored and verified. Emergency manual rollback does not need the inbox or healthy public HTTP, validates all retained materials before mutation, restores code before schema removal, and reapplies candidate code if schema removal fails.
- Emergency rollback stages the retained artifact inside the ERP container before any schema check or removal, correcting the R02 defect found by independent QA.
- External customer order/project routes enforce the active entity explicitly. Supplier identity is redacted by default and is exposed only to authenticated internal staff views, correcting the R03 findings from independent Security review.
- The customer funding page is explicitly included in the runtime manifest and baseline, correcting the R04 packaging omission.
- The 58-file baseline now derives from the exact deployed M076-R07 source commit `526976f21c609bc30482624719afb092905da810`; R05 incorrectly expected the earlier M075 state and therefore failed closed during compare.
- The root installer accepts only the exact installed R05 helper as its predecessor, retains a root-only checksum-locked backup, and restores it if the R06 upgrade does not complete.
- Disposable local schema tests prove exact-schema acceptance, altered type and extra-index rejection, and cleanup of exact empty partial tables/guards.

## Production gate record

- `2026-09-14T10:54:55Z`: commit `cb92f22fa675764d188c4c589f69fb3520dc0eab` was pushed to `origin/codex/m078-local-shipment-customer-flow`.
- The four pinned M083-R05 files were uploaded to `/home/codex-deploy/staging-inbox`; their remote SHA-256 values matched the former R05 pins.
- With Ahmed's separate approval, the pinned installer was run from the existing Termius `root@srv1011043` session. It returned `M083_HELPER_INSTALL=PASS`, with `HELPER_EXECUTED=NO` and `PRODUCTION_MUTATED=NO`.
- Restricted `sudo -n -l` verification exposed exactly the M083 helper actions `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
- R05 `compare` then failed closed with `PRODUCTION_DRIFT_HASH`; read-only status proved Production is on M076-R07, deployed at `2026-09-12T14:10:31Z`. No R05 preflight or apply ran.

## Current boundary

No mutating M083 helper action has been executed. R05 `compare` ran read-only and stopped on the stale baseline; `preflight`, `apply`, `verify`, and `rollback` have not run. R06 has not been pushed, uploaded, installed, or executed, and no Production runtime or database content was changed by M083.
