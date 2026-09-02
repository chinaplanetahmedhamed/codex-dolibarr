# M074-R08 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R08
- **Status:** `CHECKSUM_PINNED_LOCAL / RENEWED_REVIEW_PENDING / NOT_UPLOADED`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `4abd0e90018fb04f5d9abde3259b4d4925f2847c`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r08`
- **Production:** forbidden and not accessed

R08 replaces failed-closed R07 after live staging established that the three additive QC tables were created but Dolibarr's runtime permission-registration API produced none of the four reviewed rights. R07 restored every code target automatically and retained its validated code and database backups. A rolled-back live transaction proved the exact permission row shape is accepted by the staging database. R08 uses a bounded transaction that inserts only IDs `500528` through `500531`, collision-checks IDs and codes in both directions, requires the exact module/family identity, and keeps every definition `bydefault=0`.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `b45d6f456e857e1e836da8f0738fde19137d30481f36d1cc3143850c84a3f94b` | 1,267,774 |
| `dolibarr.manifest.tsv` | `c522c3e9cf4dfc553e5db8812581fecd8af197da1fd5b9b83f881fc7a86c8d4b` | 92,429 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `61ee20299c626a2427b752e5c7e0ecfd86903949dbc8e030ec8a9ab59ccb2570` | 2,228 |
| `release-pins.sha256` | `96c509d45e7e0f10b44f625555240cfddf8f79754890ed016b2ae1e12dc61850` | 951 |

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `ca0bca49e1301f5eb5455ab81e3f378a5487dad35126a82cb76f1685024ede6a` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `cdd334dccb2045f57289b79812602d02a2d8262d8396873e1b7257e06a894eea` |

The controller remains baseline-bound to `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`, uses a separate R08 state/artifact namespace, and exposes only fixed `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` commands. The installer accepts only the currently installed R07 helper `91d6bfaca40dde9d0a17e20b825c0c7d265f85f4ee93c808fa587e7a095f53db` with unchanged reviewed sudoers as its predecessor.

## Gate

No R08 file has been uploaded or installed. Independent Security and Operations/QA must pass the exact frozen R08 commit before staging upload or mutation. Remote checksums, `compare`, and `preflight` must then pass before one retry of `apply`. Production remains forbidden.
