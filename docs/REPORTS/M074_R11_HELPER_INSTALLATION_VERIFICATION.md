# M074-R11 Helper Installation Verification

## Control record

- **Mission/release:** M074 / M074-R11
- **Target:** canonical `backup-erp` staging only
- **Installation date:** 2026-09-07T03:11:15+08:00
- **Status:** `HELPER_INSTALLED / VERIFIED_RESTRICTED`

## Installer Execution Output

```text
M074_HELPER_INSTALL=PASS HELPER_SHA=4a3c70977669db6a65a1d6bbd4a418c9087afb993ded041f18727abc3de19bc3 SUDOERS_SHA=3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99 COMMANDS=compare,preflight,apply,verify,rollback,status APPLY_AVAILABLE=YES DEPLOY_EXECUTED=NO DATABASE_CHANGED=NO PRODUCTION_ACCESSED=NO
```

## Independent Restricted Verification

Verified through restricted `codex-deploy` account:
- **Helper SHA-256:** `4a3c70977669db6a65a1d6bbd4a418c9087afb993ded041f18727abc3de19bc3` (matches R11 pin exactly)
- **File Metadata:** `root:root 755`
- **Sudo surface:** exactly the 6 approved commands (`compare`, `preflight`, `apply`, `verify`, `rollback`, `status`)

Safety invariants confirmed:
- `DEPLOY_EXECUTED=NO`
- `DATABASE_CHANGED=NO`
- `PRODUCTION_ACCESSED=NO`

## Next Gate

Request explicit user approval to execute restricted read-only `compare` command:
`sudo -n /usr/local/sbin/chinaplanetos-backup-erp-m074-qc-preflight compare`
