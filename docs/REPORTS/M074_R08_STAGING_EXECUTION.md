# M074-R08 Staging Execution Record

## Scope

- **Target:** canonical `backup-erp` staging only
- **Approved actions:** exact input upload, checksum verification, restricted helper update, `compare`, conditional `preflight`, conditional `apply`, and `verify`
- **Production:** forbidden
- **Current status:** `R08_LOCAL / RENEWED_REVIEW_PENDING / NOT_UPLOADED`

## Expected release inputs

| Staging filename | SHA-256 |
|---|---|
| `m074-r08-dolibarr.tar.gz` | `dab77d8c850fbc15e0e4ee8e8f503cb9852d1a4b8ae702d68afd588015a458c2` |
| `m074-r08-dolibarr.manifest.tsv` | `21dccc9075b9f24884257be8aac13eb51ffa7a292739909abb75573b8a93c092` |
| `m074-r08-dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` |
| `m074-r08-release-bundle.json` | `12c49e35f68c1b80e8c7d160c3482ca0214b18f7d2222b6eb893e71ba83e4bc3` |
| `m074-r08-release-pins.sha256` | `12f21a9f4d17ec850671248aeefe57f01145efa9b4dbad6bc54946d5669d0434` |

## Execution evidence

On 2026-09-03 Ahmed approved staging deployment for ERP, React and CMS; Production remains forbidden. The R07 baseline-bound helper, installer and sudoers were uploaded, checksum verified and installed. Restricted `compare` passed with the exact approved snapshot `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`; restricted `preflight` passed with plan `9b1734ea5ac98024b7540dc2937b16f455e00918c759341f33139e70760ef0c8`.

R07 `apply` failed closed at permission registration after the three additive QC tables were created. The controller automatically restored all 378 code targets from checksum-validated backup `ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708`; database backup `d9fba8aee17dbf4bc1db347386b6fa2748f16651831cee187813519a0ed28892` remains retained. Runtime status is `APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED`, Production was not accessed, and no real business records were created or changed.

Read-only diagnostics established that the three exact QC tables exist, rights `500528` through `500531` remain absent, the Operations module and an active internal administrator are present for entity 1, and a representative exact permission insert succeeds inside a transaction then disappears after rollback (`1`, then `0`). R08 therefore replaces the runtime-dependent Dolibarr `insert_permissions` path with a bounded database transaction that collision-checks and inserts only the four reviewed `chinaplanetoperations` QC definitions with `bydefault=0`.

No R08 input or control file has been uploaded or installed. Renewed independent Security and Operations/QA review of the exact frozen R08 commit is required before any further server mutation. A failed review, checksum, `compare`, or `preflight` prevents retrying `apply`.
