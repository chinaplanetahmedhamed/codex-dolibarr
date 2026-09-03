# M074-R08 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R08
- **Status:** `CHECKSUM_PINNED_LOCAL / RENEWED_REVIEW_PENDING / NOT_UPLOADED`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `a2cd4cefac1f3389a535c055d112f537954bb62b`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r08`
- **Production:** forbidden and not accessed

R08 replaces failed-closed R07 after live staging established that the three additive QC tables were created but Dolibarr's runtime permission-registration API produced none of the four reviewed rights. R07 restored every code target automatically and retained its validated code and database backups. A rolled-back live transaction proved the exact permission row shape is accepted by the staging database. R08 uses a bounded transaction that inserts only IDs `500528` through `500531`, collision-checks IDs and codes in both directions, requires the exact module/family identity, and keeps every definition `bydefault=0`.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `2a37f3b13c74bbf98f033a00b2c9df02dea3ffc6108b100f01bbd78ef155c1ee` | 1,270,724 |
| `dolibarr.manifest.tsv` | `2613b9fc29deddc9b9e3bf812dca9cca1a7b427a7c783782aa6091ab5de0d7b2` | 92,824 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `1d770e76c19850f13e2f3a1302b7dd3813ce2219ca84fd2f6efe781be032199f` | 2,228 |
| `release-pins.sha256` | `37e5434e675914114a347af2f4ef052a5347a31fceed5e06bbbc5b3bce4c8c1b` | 951 |

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `6227ad61ca65c99c135e56be7c22a0ea92f218f6fc08b935438184887c61be94` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `261f3798bc0522415fd9ad065c40d5eec8c6bb2fe986d205e5142ffe38b7b89f` |

The controller remains baseline-bound to `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`, uses a separate R08 state/artifact namespace, and exposes only fixed `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` commands. The rights transaction now checks failure returns from `begin`, `commit`, and `rollback`; its behavioral test injects each failure. After the first restricted R08 `compare` safely rejected an obsolete 553-member contract, the controller was corrected to require the immutable bundle's exact 559 members and exact classification totals of 308 runtime, 218 support, and 33 evidence files. The installer accepts only the currently installed fail-closed R08 helper `10192397c88db2d4f8ab0dbc61623a40af62c3bce6ad9a21c21d1790570754f3` with unchanged reviewed sudoers as its predecessor.

## Gate

The immutable R08 release inputs and the initial fail-closed controller were uploaded and verified. Independent Security and Operations/QA must pass the exact corrected commit before the corrected controller may be uploaded or installed. Its remote checksums, `compare`, and `preflight` must then pass before one retry of `apply`. Production remains forbidden.
