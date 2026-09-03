# M074-R10 Release Preparation Report

## Control record

- **Mission/release:** M074 / M074-R10
- **Status:** `UPLOADED / REMOTE_CHECKSUMS_PASS / NOT_INSTALLED`
- **Target:** canonical `backup-erp` staging only
- **Source branch:** `codex/m074-qc-field-report-composite`
- **Pinned Dolibarr/mobile source:** `7d223fb7983fd2668826cb33587f1a4e8644f1df`
- **React source:** `01c9d872ccdf5664cf470019194156b5503dc74b`
- **WordPress source:** `ae273e2d15fa89111336ef74f746a86cc9ac57bd`
- **Artifact directory:** `deploy/releases/m074-r10`
- **Production:** forbidden and not accessed

R10 supersedes R09 after restricted R09 `compare` failed closed with `SCHEMA_CHECK_FAILED_SCHEMA_DIAGNOSTIC_INVALID`. R09 is not eligible for another retry.

## Correction scope

- The schema subprocess starts PHP with `display_errors=0`, `log_errors=0`, and `error_reporting=0`, preventing runtime/Dolibarr warnings from sharing the controller's fixed diagnostic channel.
- The migration runner still emits only one bounded ASCII `M074_SCHEMA=FAIL REASON=<ALLOWLISTED_CODE>` line for expected failures.
- The controller still rejects unknown, multiline, non-ASCII, or oversized stderr; a non-zero exit or unexpected stdout/stderr remains fail closed.
- Root verification, target-entity rights, global collision checks, table-contract classification, automatic rollback, database backup verification, exact six-command sudo surface, baseline binding, and Production prohibition remain unchanged.
- No QC business rule, schema/table definition, permission definition, customer visibility, runtime file target, React behavior, WordPress behavior, or Mobile behavior changed.

## Frozen input identity

| File | SHA-256 | Bytes |
|---|---|---:|
| `dolibarr.tar.gz` | `62775bcf60b3dfd65c7d641132b463abaa1fec02cd84154835f7fadce9c45c66` | 1,275,617 |
| `dolibarr.manifest.tsv` | `b9b49c50bcaf221024dfd9ff496c310b54e3acd672974cd7cc0c56dfed753d3c` | 93,089 |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | 328 |
| `release-bundle.json` | `69d806f11a4880decc03626b2708dc03fbf75f15e30e99e40e72d585525c5346` | 2,228 |
| `release-pins.sha256` | `9fce0da459269563dfd9d13c7b831c4fbd41142c3dc9ba8cae2ac5754691482e` | 951 |

The Dolibarr manifest contains exactly 561 members: 308 runtime, 218 support, and 35 evidence files. React, WordPress, Mobile, and layout artifact hashes remain identical to R09.

## Staging controller identity

| Local file | Intended server path/use | SHA-256 |
|---|---|---|
| `deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `/usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight` | `7960d6e19a64855fa3cafa617630471df91401c9f194ae34f590345422066494` |
| `deploy/config/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `/etc/sudoers.d/codex-deploy-backup-erp-m074-qc-preflight` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` |
| `deploy/tools/install_backup_erp_m074_qc_preflight_helper.sh` | one-time root installer input | `73e070a7d76fc608005c568c68f04538d6605e187a7693d779513bcbb31c948d` |

The installer accepts only the currently installed R09 helper `4a44d556e57212bccce7b1f950565e0b5a23cd71592a3f30c512ca3b0eb7d912` with the unchanged reviewed sudoers as its predecessor.

## Gate

Independent Security and Operations/QA reviewed exact frozen commit `a70bd2dd4ea55795eaff3c9fd33205e15aa98cf0` read-only and returned `PASS` with no actionable findings. Both independently verified the artifact/controller pins, 561-member contract, deterministic rebuild, R09 predecessor binding, exact six-command sudo surface, PHP diagnostic isolation, allowlisted stderr behavior, canonical staging targets, and Production exclusion. All 11 M074 test programs passed, including 325 PHP lints, 13 frozen candidate tests, 31 helper containment cases, schema/rights transactions, compatibility layout, PWA, and installer recovery.

After Ahmed's exact approval, the five R10 release inputs and three controller inputs were uploaded to the two private `codex-deploy` staging inboxes through temporary names and atomically published only after checksum verification. Final remote SHA-256 values match every pin above; all eight files are regular single-link `codex-deploy:codex-deploy` files with mode `0644`.

R10 is uploaded but not installed. The installed server helper remains R09. Exact R10 root installation requires a new approval, and restricted `compare` remains a later separate gate. `preflight` and `apply` remain unauthorized. Production remains forbidden and was not accessed.
