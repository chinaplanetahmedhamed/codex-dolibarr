# M074-R11 Staging Execution & Verification Report

## Control record

- **Mission/release:** M074 / M074-R11 (Field QC Report)
- **Target:** canonical `backup-erp` staging only (`https://backup-erp.visiontrading.biz`)
- **Status:** `DEPLOYED / VERIFIED_PASS / HEALTH_PASS`
- **Source commit:** `ffa4983569511cf07be12be76c7637c12ac8049c`
- **Artifact SHA-256:** `f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70`
- **Request ID:** `m074-r10-20260906T204554Z-3f7b4d8f345f`
- **Production authority:** strictly forbidden and NOT accessed

---

## Direct Pipeline Execution Summary

All steps were executed consecutively under Ahmed's directive:

### 1. Preflight
```text
M074_STAGING_PREFLIGHT=PASS RELEASE=M074-R11 SOURCE_COMMIT=ffa4983569511cf07be12be76c7637c12ac8049c ARTIFACT_SHA=f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70 PLAN_SHA=7cfde5953cbfe395f11f7f75fc62d6dc6cffd376df7e951090a404a30a49e4ac CURRENT=99 DIFFERENT=222 ABSENT=57 SCHEMA=MIGRATION_REQUIRED_RIGHTS_MISSING DATABASE_IDENTITY_BOUND=YES BASELINE_BOUND=YES APPLY_READY=YES APPLY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO
```

### 2. Apply
```text
M074_STAGING_APPLY=PASS REQUEST_ID=m074-r10-20260906T204554Z-3f7b4d8f345f RELEASE=M074-R11 SOURCE_COMMIT=ffa4983569511cf07be12be76c7637c12ac8049c ARTIFACT_SHA=f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70 PLAN_SHA=7cfde5953cbfe395f11f7f75fc62d6dc6cffd376df7e951090a404a30a49e4ac CODE_BACKUP=/root/backup-erp-code-backups/m074-r10-before-m074-r10-20260906T204554Z-3f7b4d8f345f.tar.gz CODE_BACKUP_SHA=ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708 PRESTATE=/root/backup-erp-code-backups/m074-r10-before-m074-r10-20260906T204554Z-3f7b4d8f345f.prestate.json PRESTATE_SHA=89fe3627760abfa9c6bd3d1092bde7648cbdf5fde9b52de488b7cc53a37152cd DB_BACKUP=/root/backup-erp-db-backups/m074-r10-20260906T204554Z-3f7b4d8f345f.sql DB_BACKUP_SHA=9b2b3a7f143bb1dbc372b8aa5591b297b9264e9e1d0de3e21695cbeb554c6cb4 FILES=378 TABLES=3 RIGHTS=4 RIGHTS_DEFAULT=DISABLED DATABASE_CHANGED=YES PRODUCTION_ACCESSED=NO
```

### 3. Verify
```text
M074_STAGING_VERIFY=PASS STATE=DEPLOYED RELEASE=M074-R11 SOURCE_COMMIT=ffa4983569511cf07be12be76c7637c12ac8049c ARTIFACT_SHA=f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70 REQUEST_ID=m074-r10-20260906T204554Z-3f7b4d8f345f FILES=378 TABLES=3 RIGHTS=4 RIGHTS_DEFAULT=DISABLED CODE_BACKUP_SHA=ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708 DB_BACKUP_SHA=9b2b3a7f143bb1dbc372b8aa5591b297b9264e9e1d0de3e21695cbeb554c6cb4 BACKUPS_REVALIDATED=YES DATABASE_MUTATED_BY_COMMAND=NO PRODUCTION_ACCESSED=NO
```

### 4. Status
```text
M074_STAGING_STATUS=DEPLOYED RELEASE=M074-R11 SOURCE_COMMIT=ffa4983569511cf07be12be76c7637c12ac8049c ARTIFACT_SHA=f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70 REQUEST_ID=m074-r10-20260906T204554Z-3f7b4d8f345f CODE_BACKUP_SHA=ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708 DB_BACKUP_SHA=9b2b3a7f143bb1dbc372b8aa5591b297b9264e9e1d0de3e21695cbeb554c6cb4 SCHEMA_FAILURE_REASON=none PREFLIGHT_PLAN=READY COMMANDS=compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE=NO APPLY_REASON=PREFLIGHT_REQUIRED_AFTER_STATE PRODUCTION_ACCESSED=NO
```

---

## Cryptographic Artifact & Backup Evidence

- **Code Backup:** `/root/backup-erp-code-backups/m074-r10-before-m074-r10-20260906T204554Z-3f7b4d8f345f.tar.gz`
  - SHA-256: `ba4cc6ca21ecf9032d83f8eee4843214db7512bfb6982dd55e41e3c4458f0708`
- **Prestate Backup:** `/root/backup-erp-code-backups/m074-r10-before-m074-r10-20260906T204554Z-3f7b4d8f345f.prestate.json`
  - SHA-256: `89fe3627760abfa9c6bd3d1092bde7648cbdf5fde9b52de488b7cc53a37152cd`
- **Database Backup:** `/root/backup-erp-db-backups/m074-r10-20260906T204554Z-3f7b4d8f345f.sql`
  - SHA-256: `9b2b3a7f143bb1dbc372b8aa5591b297b9264e9e1d0de3e21695cbeb554c6cb4`

---

## Security & Operational Invariants

1. **Production Boundary:** Production was **strictly NOT accessed** or altered (`PRODUCTION_ACCESSED=NO`).
2. **Permission Safety:** Exactly 4 QC permission definitions installed (IDs `500528..500531`), registered as **disabled by default** (`RIGHTS_DEFAULT=DISABLED`). No users received unintended rights grants.
3. **Endpoint Health:** `https://backup-erp.visiontrading.biz/` returns clean HTTP `200` with standard session and security headers.
