# Production baseline preflight — 2026-07-12

Status: `PREFLIGHT_INCOMPLETE`

This manifest records read-only observations. It is not a deployment authorization and does not certify the local release as verified.

## Runtime

- Host identity: verified against the approved ED25519 and RSA fingerprints.
- Dolibarr executing code: `23.0.3` (`DOL_MAJOR_VERSION=23`, `DOL_MINOR_VERSION=0.3`).
- PHP CLI in `vision_erp`: `8.2.7`.
- MariaDB reported by the approved technical handoff: `10.6.27` (not queried during this pass).
- Container: `vision_erp`.
- Custom path: `/var/www/html/custom`.

## First-party baseline inventory

- `chinaplanetlogistics`: 34 files observed.
- `cpkanban`: 34 files observed.
- Sanitized aggregate checksum for deployable ChinaPlanetLogistics candidates: `8c37b000d6a6d1f39d7173bc6803d4da004646f4f2649f7c6c0c990880f83689`.
- Aggregate checksum for CPKanban: `7b09ba8b33caf8b60c9066b86a472ff4933b57797dfe13c0621074d62976e3b8`.

The exact files were copied read-only into local staging for secret review and comparison. They have not yet been committed to this branch.

## Present on production but excluded from Git source import

The following legacy/diagnostic/backup paths remain on production and are represented here only as findings. No deletion is authorized:

- `custom/chinaplanetlogistics/*.bak`
- `custom/chinaplanetlogistics/core/**/*.bak`
- `custom/chinaplanetlogistics/core/hooks/*.emergency_off`
- `custom/chinaplanetlogistics/diag*.php`
- `custom/chinaplanetlogistics/check_*.php`
- `custom/chinaplanetlogistics/fix_menus_final.php`
- `custom/chinaplanetlogistics/reset_pass_doli.php`
- `custom/chinaplanetlogistics/reset_password.php`
- `custom/chinaplanetlogistics/set_home.php`
- `custom/chinaplanetlogistics/update_menus.php`

These paths require a separate security review and explicit removal approval before any production cleanup.

## External/disabled custom trees

Observed but not imported as first-party source during this pass:

- `ecommerceng`
- `ecommerceng_woosync_disabled`
- `kontava`
- `oblyon_disabled`
- `pdftemplates_disabled`
- `shipmentpackage_disabled`
- `whatsapp_disabled`

`ecommerceng` contains a nested `.git` directory. Vendor/external modules should be tracked by upstream version/ref and checksums, not blindly vendored into this repository.

## Initial comparison result

Production and local code differ in multiple runtime files in both first-party modules. Local CPKanban also contains new asset/cover functionality and schema files absent from production. Therefore local code must enter through a development branch and acceptance cycle; it must not replace production directly.

## Remaining gates

- Complete secret scan and source-level review of the staged live files.
- Obtain immutable Docker image reference/digest through an approved read-only command.
- Record active module/jobs/hooks metadata without copying business rows or secrets.
- Import the sanitized first-party production code to this branch.
- Create a separate development branch for local changes.
- Run integration and customer acceptance before `VERIFIED`.
