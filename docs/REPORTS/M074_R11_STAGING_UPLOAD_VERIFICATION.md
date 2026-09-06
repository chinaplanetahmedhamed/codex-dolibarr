# M074-R11 Staging Upload Verification

## Control record

- **Mission/release:** M074 / M074-R11
- **Target:** canonical `backup-erp` staging only
- **Upload date:** 2026-09-07T03:09:04+08:00
- **Account used:** `codex-deploy` (`~/.ssh/codex_vision_deploy`)
- **Status:** `REMOTE_CHECKSUMS_PASS / READY_FOR_ROOT_INSTALLER`

## Verified remote checksums

All 8 uploaded files match their exact reviewed pins bit-for-bit with permissions `codex-deploy:codex-deploy 0644`:

| File path on staging | SHA-256 | Verified |
|---|---|:---:|
| `/home/codex-deploy/staging-inbox/m074-r11-dolibarr.tar.gz` | `f06df2c656c7897b2f7cfe2192b6f7cdb2863eb91e99404247a9055a4db61f70` | PASS |
| `/home/codex-deploy/staging-inbox/m074-r11-dolibarr.manifest.tsv` | `82b80c6778cf1744e10409dfdfd2ac9ca9314bbd06be68c8cceaab3e04b62931` | PASS |
| `/home/codex-deploy/staging-inbox/m074-r11-dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` | PASS |
| `/home/codex-deploy/staging-inbox/m074-r11-release-bundle.json` | `3c433a90ec149ffdebf847945465bcf3064cc4d336ec89da3677c9eb114a577d` | PASS |
| `/home/codex-deploy/staging-inbox/m074-r11-release-pins.sha256` | `2175a22cddf1fe066fd83187ea00f2755e09d282d8a79dfae1db06d4a8e6eb6f` | PASS |
| `/home/codex-deploy/m074-qc-preflight-install/chinaplanetos_backup_erp_m074_qc_preflight.sh` | `4a3c70977669db6a65a1d6bbd4a418c9087afb993ded041f18727abc3de19bc3` | PASS |
| `/home/codex-deploy/m074-qc-preflight-install/codex-deploy-backup-erp-m074-qc-preflight.sudoers` | `3588366c71dfe7546c7ae58ff066a5b26e47d55645098d82034d628f149d4d99` | PASS |
| `/home/codex-deploy/m074-qc-preflight-install/install_backup_erp_m074_qc_preflight_helper.sh` | `8e97b12f136597a1ac47a1c88cc6f5ef2682299a455fccdf5856e2c7f15cc601` | PASS |

## Next Gate

Run the root installer in Termius:
`bash /home/codex-deploy/m074-qc-preflight-install/install_backup_erp_m074_qc_preflight_helper.sh`
