# M074-R11 Staging Compare Execution Record

## Control record

- **Mission/release:** M074 / M074-R11
- **Target:** canonical `backup-erp` staging only
- **Command:** `sudo -n /usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight compare`
- **Execution timestamp:** 2026-09-07T03:58:41+08:00
- **Status:** `COMPARE_PASS / SCHEMA_MIGRATION_REQUIRED_RIGHTS_MISSING / PREFLIGHT_REQUIRED`

## Execution Summary

```text
M074_STAGING_COMPARE=PASS RELEASE=M074-R11 SOURCE_COMMIT=ffa4983569511cf07be12be76c7637c12ac8049c ARTIFACT_SHA=f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70 SNAPSHOT_SHA=39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094 BASELINE_MATCH=YES DEPLOY_FILES=378 CURRENT=99 DIFFERENT=222 ABSENT=57 LAYOUT_CURRENT=0 LAYOUT_OVERLAY_DIRECTORY=2 LAYOUT_DIFFERENT=0 LAYOUT_ABSENT=0 SCHEMA=MIGRATION_REQUIRED_RIGHTS_MISSING DATABASE_IDENTITY_BOUND=YES APPLY_READY=NO PREFLIGHT_REQUIRED=YES APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO
```

## Key Findings

1. **Schema Check Fixed & Validated:**
   - The MariaDB `ENGINE` casing issue that blocked R10 (`TABLE_CONTRACT_INVALID`) is completely resolved.
   - All 3 QC tables match the schema contract 100%.
   - `SCHEMA=MIGRATION_REQUIRED_RIGHTS_MISSING`: Confirms the 3 tables are present, and exactly the 4 required QC rights (500528..500531) are pending installation on staging.
2. **Baseline & Artifact Verification:**
   - Release: `M074-R11`
   - Source commit: `ffa4983569511cf07be12be76c7637c12ac8049c`
   - Artifact SHA-256: `f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70`
   - Baseline match: `YES` (`39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094`)
   - Layout tree hashes: exactly matched.
3. **Safety Invariants:**
   - `APPLY_EXECUTED=NO`
   - `DATABASE_CHANGED=NO`
   - `PRODUCTION_ACCESSED=NO`

## Next Gate

Request explicit approval to execute restricted read-only `preflight`:
`sudo -n /usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight preflight`
