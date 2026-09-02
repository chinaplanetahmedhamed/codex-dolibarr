# M074-R07 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R07
- **Status:** `CHECKSUM_PINNED_LOCAL / REVIEW_PENDING / NOT_DEPLOYED`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `fc6f0fe1bfba303ab45ed011d634ec7712b6ccda`
- **Accepted M068 lineage merged:** `7afb9415de5108b708b2bc3cb071a09b0b645512`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r07`
- **Production:** forbidden and not accessed

R07 replaces the rejected R06 candidate. R06 failed closed during read-only schema comparison because accepted M068 already owns permission ID `500527` as `publicrequest_invite`. No R06 preflight, apply, schema, database or runtime mutation occurred. R07 merges accepted M068, preserves `500527`, and assigns the four disabled-by-default QC rights to `500528` through `500531`.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `3c022220ef7d6d2540d54eedd5d6bac2b2c5c0892d7e5024c5989f25329fbedc` | 1,261,699 |
| `dolibarr.manifest.tsv` | `b31db80b8a4e9b725d60b0c22dd3cdf1b8aea1814a28ba98d5b90dae5177969f` | 92,033 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `99da1d088fccc8c4c94c335a3809624b70f6c1637e928699b0b0065c65a128eb` | 2,228 |
| `release-pins.sha256` | `e86898770a1c809a5749f4950da67cea1dbdbe97b4516814da2bac66755d8153` | 951 |

The Dolibarr manifest contains 553 rows: 308 runtime, 216 support and 29 evidence records. A clean detached worktree at the pinned source commit reproduced the frozen archive byte-for-byte.

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `cc653f486f5d637c93b04d8d4c8585017ca4157304c42de18b350c2d3d2cd361` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `beb5e27a309aeaea4edf45cd4f7e260062d94c7dbd4123b9aeee41ca2a8ce958` |

The current controller is a fail-closed second diagnostic build: it exposes the fixed six-command sudo surface but `preflight` and `apply` stop at `BASELINE_NOT_PINNED`. `compare` discloses the canonical snapshot hash plus a separate tree hash for each legacy compatibility directory. It models the accepted directory deployment as 308 canonical runtime targets plus 70 compatibility-copy targets, while still refusing the directory layout until the two tree hashes are explicitly pinned. After the read-only result is reviewed, the exact general/tree hashes must be bound, the controller must be repinned and independently reviewed, and only then can preflight/apply become reachable. The final controller also binds terminal-state time so an old preflight cannot be reused after failure or rollback.

The two legacy Customer Center compatibility paths may be real directories on the accepted staging baseline. R07 treats either path as convertible only when its complete regular-file/directory tree is semantically identical to the canonical target. Apply then atomically replaces it with the expected relative symlink. The exact directory metadata/tree is recorded in prestate and reconstructed during rollback. Symlinks, special files or any content/mode mismatch fail closed.

## Verification completed locally

- PHP lint passed for the combined M068/M071/M074 runtime.
- M068 lifecycle, invitation security and portal-currency tests passed.
- M061/M070/M071/M074 source, schema, PWA and customer safety tests passed.
- Exact candidate reconstruction and its 12 archive-contained tests passed.
- Artifact reproducibility passed with 308 runtime files and two clean rebuilds.
- Staging helper contract and 22 containment/fault cases passed, including unbound/mismatched baseline and stale-preflight rejection.
- Transactional installer partial-publish restoration passed.
- Compatibility layout conversion, exact rollback and mismatch rejection passed.
- Mobile `npm run lint` and `npm run typecheck` passed; Vitest passed 45/45 tests.

Dependency installation reported 20 moderate and 14 high npm dependency advisories under the existing lockfile. No dependency was force-upgraded because that would be an unreviewed scope change; the M074 application checks themselves passed.

## Gate

This record freezes local release inputs only. Staging upload and installation may proceed only after independent Security and Operations/QA review of this exact commit. `apply` is allowed only if remote hashes, `compare`, and `preflight` all pass. Production remains forbidden.
