# M083-R01 Production release preparation

- Runtime commit: `ecf151802ca8396c51214d758c6150cf1db49a9d`
- Preparation base commit: `57202dea973c7fc95e784ad43bb466cd48bdcb5f`
- Target: `erp.visiontrading.biz` Production only
- State: immutable local candidate; independent Security and QA review pending

## Frozen pins

- Artifact `m083-r01-staff-first-ecf1518.tar.gz`: `c71cd69c5547dfdda0941dd2759b7c67b5fdf94d313a42e802586fcacdddcb91`
- Restricted helper: `6c67eb3955cb5c2b09d76f794f421039d5abf6318b9ec695bbfcf1a0944c92ba`
- Root installer: `12c39eaba32376d54c557b76e59ab31cf195d19468e90c80ca48cc9f401d24ca`
- Sudoers: `03b11843b7f0e5780e02c4cf68b356c2c439cb2110c72d7b71cac8c4f7e76a91`

The artifact is metadata-free and contains exactly 71 regular files: 57 runtime files, 11 support files, and three release manifests. It contains no configuration, credential, key, database dump, document, log, symlink, or Dolibarr core file.

## Gate behavior

- `compare` and `preflight` are read-only and require the exact M075-derived file baseline plus fully absent M083 schema.
- `apply` creates a code backup, absent-file manifest, and full database dump before creating six additive tables, installing four managed-funds guards, or replacing runtime files.
- Automatic failure recovery restores code and drops only empty newly created schema. It never restores a full Production database automatically.
- Manual rollback is fail-closed once any M083 table contains a business row. Data-preserving recovery after real use requires a separately approved plan.
- No permission, module, business row, email, external integration, proxy, DNS, image, Compose, or document-volume change is part of the helper.

No file has been pushed, uploaded, installed, compared, preflighted, or applied to Production by this preparation.
