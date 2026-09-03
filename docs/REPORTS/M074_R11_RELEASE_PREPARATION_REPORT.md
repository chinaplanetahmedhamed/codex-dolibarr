# M074-R11 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R11
- **Status:** `PREPARED / FROZEN_LOCALLY / AWAITING_STAGING_UPLOAD_APPROVAL`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `ffa4983569511cf07be12be76c7637c12ac8049c`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r11`
- **Production:** forbidden and not accessed

R11 supersedes R10 after an approved bounded read-only diagnosis on canonical `backup-erp` staging (`erp-staging-mariadb-1`) verified that:
1. All three QC tables (`llx_cpo_qc_checkpoint`, `llx_cpo_qc_evidence`, `llx_cpo_qc_publication`) are present and use `InnoDB`.
2. All 95 columns and all 15 indexes match the frozen schema contract 100% bit-for-bit.
3. The root cause of `TABLE_CONTRACT_INVALID` was isolated to PHP property casing: `SELECT ENGINE FROM information_schema.tables` yields `$statusRow->ENGINE` in MariaDB, whereas code queried `$statusRow->Engine`, evaluating to `null` and falsely triggering `'collision'`.

## Correction scope

- `QcMigration::tableContractStatus()` now evaluates `$statusRow->ENGINE ?? $statusRow->Engine ?? ''`, accepting both MariaDB uppercase column metadata and mixed-case property fallback.
- Explicit regression test cases verify uppercase `ENGINE`, mixed-case `Engine`, and non-InnoDB rejection in `deploy/tests/test_m074_qc_contract.php`.
- Schema execution context is updated to `m074-r11-backup-erp-root-only`.
- Preflight helper and installer predecessor pins are rotated:
  - Installed predecessor helper: `7960d6e19a64855fa3cafa617630471df91401c9f194ae34f590345422066494` (R10)
  - Unchanged sudoers: `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99`
- No QC business rule, table definition, permission definition, customer visibility, runtime file target, React behavior, WordPress behavior, or Mobile behavior changed.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70` | 1,277,854 |
| `dolibarr.manifest.tsv` | `82b80c6778cf1744e10409dfdfd2ac9ca9314bbd06be68c8cceaab3e04b62931` | 93,354 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `3c433a90ec149ffdebf847945465bcf3064cc4d336ec89da3677c9eb114a577d` | 2,228 |
| `release-pins.sha256` | `2175a22cddf1fe066fd83187ea00f2755e09d282d8a79dfae1db06d4a8e6eb6f` | 951 |

The Dolibarr manifest contains exactly 563 members: 308 runtime, 218 support, and 37 evidence files. React (`44c3bd853e681f12dbdc2579de48f018aa2a739a962e38bceb9389742d5a9c9a`), WordPress (`3a91e08a96562925881187ba5c3719188781e3edf74066284fec1301e1bfb332`), Mobile (`61440cda9ca820659a6f9e33adc0d06a6204679caec50a53712959b02986116e`), and layout artifact hashes remain identical to R10.

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `4a3c70977669db6a65a1d6bbd4a418c9087afb993ded041f18727abc3de19bc3` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `8e97b12f136597a1ac47a1c88cc6f5ef2682299a455fccdf5856e2c7f15cc601` |

The installer accepts only the currently installed R10 helper `7960d6e19a64855fa3cafa617630471df91401c9f194ae34f590345422066494` with the unchanged reviewed sudoers as its predecessor.

## Test evidence

All 11 M074 test programs pass locally:
1. `php -l deploy/tests/test_m074_qc_contract.php` and `test_m074_qc_contract.php`: **PASS** (68 assertions including new uppercase `ENGINE` property assertions)
2. `php deploy/tests/test_m074_rights_transaction.php`: **PASS** (`M074_RIGHTS_TRANSACTION=PASS`)
3. `node deploy/tests/test_m074_qc_pwa.js`: **PASS** (`M074_QC_PWA=PASS`)
4. `python3 deploy/tests/test_m074_schema_contract.py`: **PASS** (3 tables, 95 columns, 16 indexes)
5. `python3 deploy/tests/test_m074_artifact_builder.py`: **PASS** (42 reviewed runtime files)
6. `python3 deploy/tests/test_m074_staging_candidate.py`: **PASS** (325 PHP lints, 13 candidate tests)
7. `python3 deploy/tests/test_m074_staging_helper_failures.py`: **PASS** (31 containment cases)
8. `python3 deploy/tests/test_m074_compatibility_layout.py`: **PASS** (378 deploy targets, 70 overlays)
9. `bash deploy/tests/test_m074_installer_recovery.sh`: **PASS** (partial publish restored, fail-closed)
10. `php deploy/tests/test_m074_staging_preflight_contract.php`: **PASS** (`M074_STAGING_PREFLIGHT_CONTRACT=PASS`)
11. `release-pins.sha256` verification: **PASS** (all 11 release files match their exact digest)

## Next safe gate

Request Ahmed's explicit approval for:
"uploading the five exact M074-R11 release inputs and three controller inputs to canonical `backup-erp` staging inboxes, verifying remote checksums, and installing the R11 helper through the approved root session."
