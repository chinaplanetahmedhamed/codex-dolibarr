# M074-R09 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R09
- **Status:** `COMPARE_FAILED_CLOSED / R09_RETRY_FORBIDDEN / R10_PREPARATION_IN_PROGRESS`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `7af85bbb32c50833e909ba68dc73b6b6290dbc65`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r09`
- **Production:** forbidden and not accessed

R09 supersedes R08 after its exact staging apply failed closed and automatically restored code while retaining the additive schema. R08 is not eligible for another retry.

## Correction scope

- Root verification uses `posix_geteuid()` when available and a Linux `/proc/self/status` effective-UID fallback otherwise. The same read-only root assertion now runs during schema `check`, so `compare` and `preflight` fail before mutation if the container cannot prove UID 0.
- QC right definitions are checked globally for mismatched ID/code/default collisions, while exact existence and readiness are bound to the active Dolibarr entity. Identical reviewed definitions in another entity are allowed and cannot satisfy the target entity.
- Read-only schema classification distinguishes `TABLES_MISSING` from `RIGHTS_MISSING`; invalid table contracts and right collisions fail closed.
- The controller accepts only one bounded ASCII diagnostic line from a fixed allowlist. Unknown, multiline, non-ASCII, or oversized stderr becomes `SCHEMA_DIAGNOSTIC_INVALID`; raw SQL, database, exception, customer, or configuration text is never emitted or stored.
- Automatic code rollback, database backup verification, the exact six-command sudo surface, baseline binding, and Production prohibition remain unchanged.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `893a72ca12f06bca0721b88dead4e36ff84353d1dbdc57400cad0c1d68cdf27c` | 1,272,526 |
| `dolibarr.manifest.tsv` | `483919801ee7c0803b822ae279328e57568c2e04910ea7964c323b780e095fb1` | 92,824 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `25cf437d764327bf5d6727674f7ec6030c40d621850bcb6cdec7e8f11e6f8725` | 2,228 |
| `release-pins.sha256` | `287fb4d33c5e8a9464343f9fbced000771d365a52ea8027c9133f2b79d20ed65` | 951 |

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `4a44d556e57212bccce7b1f950565e0b5a23cd71592a3f30c512ca3b0eb7d912` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `197e14312390a6bb2131eeef99ddcc849607408e93d36ed2463643575728eac9` |

The installer accepts only the currently installed R08 helper `6227ad61ca65c99c135e56be7c22a0ea92f218f6fc08b935438184887c61be94` with unchanged reviewed sudoers as its predecessor.

## Gate

The immutable R09 release and controller passed renewed independent Security and Operations/QA review, were uploaded with matching remote checksums, and the exact helper and unchanged sudoers were installed after Ahmed's approval. Restricted `compare` failed closed with `SCHEMA_CHECK_FAILED_SCHEMA_DIAGNOSTIC_INVALID`; neither `preflight` nor `apply` ran. The R09 controller must not be retried. R10 preparation may suppress PHP runtime warning/log channels for the schema subprocess while retaining the single fixed allowlisted stderr failure contract. R10 requires new immutable pins and renewed independent Security and Operations/QA review before any server action. Production remains forbidden and was not accessed.
