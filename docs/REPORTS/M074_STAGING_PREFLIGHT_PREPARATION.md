# M074 Staging Preflight Helper Preparation

## Control record

- **Mission/release:** M074 / M074-R01
- **Status:** `READ_ONLY_HELPER_INSTALLED_AND_VERIFIED / RELEASE_NOT_UPLOADED / COMPARE_NOT_RUN / PREFLIGHT_NOT_RUN`
- **Target:** canonical `backup-erp` staging only
- **Authorized command surface:** `compare`, `preflight`, `status`
- **Apply/verify/rollback:** unavailable
- **Database/schema mutation:** unavailable
- **Production:** not accessed or changed

## Why a mission-specific helper is required

The frozen M074 bundle is deliberately unbound. Before this gate, the canonical staging server did not have the unified M072 release controller or an M074 helper. The installed historical `chinaplanetos-backup-erp-deploy` helper is pinned to M004 and has no `compare` command; the M073 helper targets the separate isolated rebuild environment. Neither is safe or authorized for M074. The legacy general staging wrappers target a noncanonical duplicate stack and remain forbidden.

The local M074 helper therefore exposes read-only inspection only. It cannot install application files, run schema migration, change the database, create a backup, restart a container or access production.

## Frozen release inputs

The helper accepts only these exact files in `/home/codex-deploy/staging-inbox`:

| Staging filename | Frozen source | SHA-256 |
|---|---|---|
| `m074-r01-dolibarr.tar.gz` | `deploy/releases/m074-r01/dolibarr.tar.gz` | `2f7e368dc5c4c1455166a5b6c56c55b33bb32c0ec0a3c14f5d55023f71e68830` |
| `m074-r01-dolibarr.manifest.tsv` | `deploy/releases/m074-r01/dolibarr.manifest.tsv` | `b621576dab047a76188914e9e053752ed3753e06cc685f52854acda5b43f80c7` |
| `m074-r01-dolibarr.layout.tsv` | `deploy/releases/m074-r01/dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` |
| `m074-r01-release-bundle.json` | `deploy/releases/m074-r01/release-bundle.json` | `ffc8a3d00c50a90be0dc7d2e2a64d4eccc14ac9da8aec1c46082b436a2313bfb` |
| `m074-r01-release-pins.sha256` | `deploy/releases/m074-r01/release-pins.sha256` | `09fbb1f3ad18f43596ebfc09a1e4970932969678949f37839e168ebefe78dd77` |

All five must be regular `codex-deploy:codex-deploy` files with mode `0644`. The helper copies them into a private root-owned temporary directory before validation.

## Helper identity and boundaries

| Local file | Intended server path | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `2f1f9f24ed2f0a1bda121190f41d5a6c73bf6c01fab209c877b03bfd4ef16e39` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `777217564edf5a9b1dd67617e73cdf4e8f7fe3f90c3095ccc1930d1016fdbf38` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time human/root installer input | `94bb5a839cb93f89e5d3d541a4aa6ac3c9c1f79e2102f98fc4d165d1a5430c1c` |

The installer reads only from `/home/codex-deploy/m074-qc-preflight-install`, requires that directory to be private (`0700`) and the two inputs to be regular mode-`0644` files, checks their exact hashes, validates both existing targets and global sudoers before any target write, refuses unexpected pre-existing state, stages every missing target before publishing either one, and removes only newly created hash-matching targets if a post-write check fails. It installs no other command.

## Preflight behavior

The helper fails closed unless it sees the canonical staging identities:

- ERP: `erp-staging-dolibarr-1`, compose project/service `erp-staging/dolibarr`, localhost port `18088`.
- Database: `erp-staging-mariadb-1`, compose project/service `erp-staging/mariadb`.
- Mailpit: `erp-staging-mailpit-1`, localhost ports `11025` and `18025`.
- Custom volume: `erp-staging_erp-staging_custom` mounted read/write at `/var/www/html/custom` and rooted at `/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data`.

It validates the bundle identity and unbound release gate, every archive path/type/mode, all 514 manifest rows and file hashes, the 298 runtime / 193 support / 23 evidence classification counts, and the two compatibility-link records. It copies the candidate into a temporary path in the ERP container, performs PHP lint, reconstructs the two candidate-only compatibility links, and runs nine archive-contained M061/M070/M071/M074/customer-report/mobile-data/mobile-security/responsive source regressions. It forcibly unsets all M017 database/HTTP integration variables for every candidate test.

`compare` reports `CURRENT`, `DIFFERENT` or `ABSENT` for each of the 298 runtime files, comparing both SHA-256 and Unix mode, plus the state of both compatibility links. `preflight` repeats the validation and candidate checks, then reports the same comparison summary. `deploy/tests/test_m074_staging_candidate.py` reconstructs the exact frozen candidate and successfully executes the complete helper test list even when malicious M017 integration variables are inherited by the outer test process.

Because no accepted hash baseline has yet been captured for the current canonical staging volume, both commands deliberately return:

```text
APPLY_READY=NO BASELINE_REVIEW_REQUIRED=YES
```

This is a successful read-only diagnostic result, not deployment authorization. A later apply helper must not be prepared or installed until the remote prestate is reviewed and Ahmed grants a separate staging-apply approval.

## Gate status

Ahmed approved the Git push and read-only staging-helper installation on 2026-09-02. Preparation commit `46d84233c251bef883c93196847930666f2aac3e` is pushed to `origin/codex/m074-qc-field-report-composite`.

Only the three installer inputs were uploaded to `/home/codex-deploy/m074-qc-preflight-install`; their remote hashes matched the table above and their metadata was `codex-deploy:codex-deploy`, directory `0700`, files `0644`. The checksum-pinned transactional root installer completed. Independent restricted verification established:

- helper `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight`: `root:root 0755`, 16,956 bytes, SHA-256 `2f1f9f24ed2f0a1bda121190f41d5a6c73bf6c01fab209c877b03bfd4ef16e39`;
- sudoers `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight`: `root:root 0440`, 304 bytes, SHA-256 `777217564edf5a9b1dd67617e73cdf4e8f7fe3f90c3095ccc1930d1016fdbf38`;
- active M074 sudo surface: exactly `compare`, `preflight`, and `status`;
- non-mutating `status`: `PREFLIGHT_ONLY`, `APPLY_AVAILABLE=NO`, `DATABASE_CHANGED=NO`, `PRODUCTION_ACCESSED=NO`.

No M074 release artifact, manifest, layout, bundle or pin file has been uploaded to the staging inbox. `compare` and `preflight` have not run. Application runtime, database, schema, documents, containers and customer data were unchanged; production was not accessed. The next action requires a separate exact approval for the five checksum-pinned release inputs followed by remote verification, `compare`, and conditional `preflight` only.

## Independent review

- **Security: PASS.** The initial review found that inherited M017 variables could activate integration branches and that installer failure could leave partial state. Both findings are closed: the helper forcibly unsets all three integration variables, the exact candidate passes with hostile outer values, and the installer prevalidates/stages/rolls back newly created hash-matching targets transactionally. No apply, database, restart or production command is exposed.
- **Operations/QA: PASS.** The initial review identified two tests whose external dependencies are not in the Dolibarr archive, missing mode comparison, and the same installer partial-state issue. The remote list now contains nine self-contained tests proven against the reconstructed archive; hash and Unix mode are both compared; installer transaction guards pass review. Counts, pins, syntax, contract and diff checks pass.
- **Customer Portal/release scope: PASS.** The helper consumes and inspects only the ERP/Dolibarr artifact and canonical staging custom volume. React, WordPress and customer mobile artifacts are unchanged; customer/runtime acceptance remains a later gate.
- **Installed-state Security: PASS.** Independent restricted verification confirmed the exact helper hash/metadata, the three-command sudo surface and `APPLY_AVAILABLE=NO`; neither `compare` nor `preflight` ran and no runtime, database or production state changed.
- **Installed-state Operations/QA: PASS.** Independent restricted verification confirmed all three remote install-input hashes/metadata, helper size/hash, exact sudo surface, non-mutating status and absence of M074 release files from the staging inbox.

These are the pre-install source verdicts. Ahmed separately approved the completed upload/root-install gate recorded above. They do not approve release upload, `compare`, `preflight`, apply, schema mutation or production and do not prove staging acceptance.
