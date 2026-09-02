# M074-R07 Staging Execution Record

## Scope

- **Target:** canonical `backup-erp` staging only
- **Approved actions:** exact input upload, checksum verification, restricted helper update, `compare`, conditional `preflight`, conditional `apply`, and `verify`
- **Production:** forbidden
- **Current status:** `GENERAL_BASELINE_BOUND_LOCAL / RENEWED_REVIEW_PENDING / APPLY_BLOCKED`

## Expected release inputs

| Staging filename | SHA-256 |
|---|---|
| `m074-r07-dolibarr.tar.gz` | `3c022220ef7d6d2540d54eedd5d6bac2b2c5c0892d7e5024c5989f25329fbedc` |
| `m074-r07-dolibarr.manifest.tsv` | `b31db80b8a4e9b725d60b0c22dd3cdf1b8aea1814a28ba98d5b90dae5177969f` |
| `m074-r07-dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` |
| `m074-r07-release-bundle.json` | `99da1d088fccc8c4c94c335a3809624b70f6c1637e928699b0b0065c65a128eb` |
| `m074-r07-release-pins.sha256` | `e86898770a1c809a5749f4950da67cea1dbdbe97b4516814da2bac66755d8153` |

## Execution evidence

On 2026-09-03 Ahmed approved staging deployment for ERP, React and CMS; Production remains forbidden. The canonical ERP R07 inputs were already present and reverified against all five pins. The installed diagnostic helper matched local pre-binding hash `369c86325a303342680598aa8916f23e3a420f66b42a4612862b72e1e412e315`.

Fresh restricted `compare` returned `PASS`, source commit `fc6f0fe1bfba303ab45ed011d634ec7712b6ccda`, artifact `3c022220ef7d6d2540d54eedd5d6bac2b2c5c0892d7e5024c5989f25329fbedc`, snapshot `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`, 378 deployment files, 99 current, 222 different, 57 absent, two exact compatibility overlay directories, and `SCHEMA=MIGRATION_REQUIRED`; it reported `DATABASE_CHANGED=NO` and `PRODUCTION_ACCESSED=NO`.

That exact snapshot is now bound in the local successor helper. No corrected control file has been uploaded or installed and `preflight`/`apply` have not run. Renewed Security and Operations/QA review of the frozen correction is required first. A failed review, remote checksum, `compare`, or `preflight` prevents `apply`. Apply must record the request ID, code backup/prestate hashes, database dump hash, successful restore validation, three additive QC tables, four disabled rights, and production non-access. Verify must independently revalidate the retained release, backups, runtime hashes/ownership/modes, schema and protected QC route.
