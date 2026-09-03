# M074-R08 Staging Execution Record

## Scope

- **Target:** canonical `backup-erp` staging only
- **Approved actions:** exact input upload, checksum verification, restricted helper update, `compare`, conditional `preflight`, conditional `apply`, and `verify`
- **Production:** forbidden
- **Current status:** `APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED / R08_RETRY_FORBIDDEN / R09_PREPARATION_IN_PROGRESS`

## Expected release inputs

| Staging filename | SHA-256 |
|---|---|
| `m074-r08-dolibarr.tar.gz` | `2a37f3b13c74bbf98f033a00b2c9df02dea3ffc6108b100f01bbd78ef155c1ee` |
| `m074-r08-dolibarr.manifest.tsv` | `2613b9fc29deddc9b9e3bf812dca9cca1a7b427a7c783782aa6091ab5de0d7b2` |
| `m074-r08-dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` |
| `m074-r08-release-bundle.json` | `1d770e76c19850f13e2f3a1302b7dd3813ce2219ca84fd2f6efe781be032199f` |
| `m074-r08-release-pins.sha256` | `37e5434e675914114a347af2f4ef052a5347a31fceed5e06bbbc5b3bce4c8c1b` |

## Execution evidence

On 2026-09-03 Ahmed approved staging deployment for ERP, React and CMS; Production remains forbidden. The R07 baseline-bound helper, installer and sudoers were uploaded, checksum verified and installed. Restricted `compare` passed with the exact approved snapshot `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`; restricted `preflight` passed with plan `9b1734ea5ac98024b7540dc2937b16f455e00918c759341f33139e70760ef0c8`.

R07 `apply` failed closed at permission registration after the three additive QC tables were created. The controller automatically restored all 378 code targets from checksum-validated backup `ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708`; database backup `d9fba8aee17dbf4bc1db347386b6fa2748f16651831cee187813519a0ed28892` remains retained. Runtime status is `APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED`, Production was not accessed, and no real business records were created or changed.

Read-only diagnostics established that the three exact QC tables exist, rights `500528` through `500531` remain absent, the Operations module and an active internal administrator are present for entity 1, and a representative exact permission insert succeeds inside a transaction then disappears after rollback (`1`, then `0`). R08 therefore replaces the runtime-dependent Dolibarr `insert_permissions` path with a bounded database transaction that collision-checks and inserts only the four reviewed `chinaplanetoperations` QC definitions with `bydefault=0`.

The five exact R08 release inputs were uploaded to the private staging inbox and their remote SHA-256 values matched the table above. The initial R08 helper `10192397c88db2d4f8ab0dbc61623a40af62c3bce6ad9a21c21d1790570754f3`, installer `57cca8416da2dd1f50aa33403d1c9067a84c0695274b65b81a3241a91e5178ce`, and unchanged sudoers were also uploaded, checksum-verified, and installed through the approved root session.

Restricted `status` returned `NOT_DEPLOYED`, then `compare` failed closed with `DOLIBARR_BUNDLE_RECORD_INVALID` before preflight or apply. Read-only diagnosis found that the immutable bundle and manifest contain 559 exact members while the initial helper still required the prior 553-member contract. The local correction updates the three total-count gates to 559 and the classification gate to the exact immutable totals of 308 runtime, 218 support, and 33 evidence files; it also pins the installed initial R08 helper as the installer predecessor and adds regression assertions. Renewed independent Security and Operations/QA review of the corrected exact commit is required before installing it. A failed review, checksum, `compare`, or `preflight` prevents `apply`.

The corrected R08 helper `6227ad61ca65c99c135e56be7c22a0ea92f218f6fc08b935438184887c61be94` was subsequently installed. Restricted `compare` passed against baseline `39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`; restricted `preflight` passed with plan `2ffe23fc011399bed1fa654d2b819ab889d2b201cffe10eec83df52621b4a823`. One exact `apply` attempt failed closed at schema apply. Automatic rollback restored all code targets and retained the additive schema/data. The terminal request is `m074-r08-20260903T112506Z-ea6a6ab45a10`; code backup SHA-256 is `ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708`, and database backup SHA-256 is `ad1bb2acc6d4f13a49847f32eb2e57a2a4da8f5820c637401e47c12d264c5a79`.

R08 discarded the apply runner's fixed stderr and collapsed every failure to `SCHEMA_APPLY_FAILED`, so the exact live branch cannot be recovered from that request. Independent QA identified the apply-only `posix_geteuid()` dependency as the strongest immediate fit when the PHP POSIX extension is unavailable; independent Security and QA also confirmed that R08 permission readiness incorrectly omitted Dolibarr entity scoping. R08 must not be retried. A newly pinned successor must make root detection portable, make rights readiness target-entity exact, allow identical definitions across entities while rejecting mismatches globally, and retain only bounded allowlisted diagnostic codes. Production, DNS, proxy, email, and real business records were not accessed or changed.
