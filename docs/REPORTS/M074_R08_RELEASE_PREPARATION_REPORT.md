# M074-R08 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R08
- **Status:** `CHECKSUM_PINNED_LOCAL / RENEWED_REVIEW_PENDING / NOT_UPLOADED`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `e2ee6db449daacf6781765efc0078276f8ad1cb9`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r08`
- **Production:** forbidden and not accessed

R08 replaces failed-closed R07 after live staging established that the three additive QC tables were created but Dolibarr's runtime permission-registration API produced none of the four reviewed rights. R07 restored every code target automatically and retained its validated code and database backups. A rolled-back live transaction proved the exact permission row shape is accepted by the staging database. R08 uses a bounded transaction that inserts only IDs `500528` through `500531`, collision-checks IDs and codes in both directions, requires the exact module/family identity, and keeps every definition `bydefault=0`.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `dab77d8c850fbc15e0e4ee8e8f503cb9852d1a4b8ae702d68afd588015a458c2` | 1,270,656 |
| `dolibarr.manifest.tsv` | `21dccc9075b9f24884257be8aac13eb51ffa7a292739909abb75573b8a93c092` | 92,824 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `12c49e35f68c1b80e8c7d160c3482ca0214b18f7d2222b6eb893e71ba83e4bc3` | 2,228 |
| `release-pins.sha256` | `12f21a9f4d17ec850671248aeefe57f01145efa9b4dbad6bc54946d5669d0434` | 951 |

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `94c4ca043c3f8c1613bf499d30d9389e4baee0629584db588e04900e9107c986` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `fab4d505c58f20ba827e3406873387f41ff96bd07ae1d373ff9a2cfc2bdcc959` |

The controller remains baseline-bound to `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`, uses a separate R08 state/artifact namespace, and exposes only fixed `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status` commands. The rights transaction now checks failure returns from `begin`, `commit`, and `rollback`; its behavioral test injects each failure. The installer accepts only the currently installed R07 helper `91d6bfaca40dde9d0a17e20b825c0c7d265f85f4ee93c808fa587e7a095f53db` with unchanged reviewed sudoers as its predecessor.

## Gate

No R08 file has been uploaded or installed. Independent Security and Operations/QA must pass the exact frozen R08 commit before staging upload or mutation. Remote checksums, `compare`, and `preflight` must then pass before one retry of `apply`. Production remains forbidden.
