# M074-R07 Staging Execution Record

## Scope

- **Target:** canonical `backup-erp` staging only
- **Approved actions:** exact input upload, checksum verification, restricted helper update, `compare`, conditional `preflight`, conditional `apply`, and `verify`
- **Production:** forbidden
- **Current status:** `DIAGNOSTIC_HELPER_LOCAL / BASELINE_CAPTURE_PENDING / APPLY_BLOCKED`

## Expected release inputs

| Staging filename | SHA-256 |
|---|---|
| `m074-r07-dolibarr.tar.gz` | `3c022220ef7d6d2540d54eedd5d6bac2b2c5c0892d7e5024c5989f25329fbedc` |
| `m074-r07-dolibarr.manifest.tsv` | `b31db80b8a4e9b725d60b0c22dd3cdf1b8aea1814a28ba98d5b90dae5177969f` |
| `m074-r07-dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` |
| `m074-r07-release-bundle.json` | `99da1d088fccc8c4c94c335a3809624b70f6c1637e928699b0b0065c65a128eb` |
| `m074-r07-release-pins.sha256` | `e86898770a1c809a5749f4950da67cea1dbdbe97b4516814da2bac66755d8153` |

## Execution evidence

To be completed from restricted staging output after each gate. A failed `compare` or `preflight` prevents `apply`. Apply must record the request ID, code backup/prestate hashes, database dump hash, successful restore validation, three additive QC tables, four disabled rights, and production non-access. Verify must independently revalidate the retained release, backups, runtime hashes/ownership/modes, schema and protected QC route.
