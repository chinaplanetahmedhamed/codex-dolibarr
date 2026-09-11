# M075 — Vision-China Planet OS production UI and login branding

## Objective

Publish the accepted ChinaPlanetOS UI corrections to the production ERP at `erp.visiontrading.biz`: replace visible login branding with `Vision-China Planet OS`, expose the shared top navigation on Operations, Tasks, and SOP pages, remove the duplicate module sidebar from those surfaces, and correct unreadable card/stat contrast.

## Fixed scope

- White-label assets, hooks, settings UI, and the exact visible brand name `Vision-China Planet OS`.
- Shared ChinaPlanet Customer Center top navigation and dropdown behavior.
- Shared shell wrappers on the ten named Operations, Tasks, SOP, and settings pages.
- Contrast-only CSS corrections for statistics, cards, tables, forms, and notices.
- One visible label correction from `Dolibarr Sales Orders` to `ERP Sales Orders`.
- Production configuration constants required to enable the white-label layer.

The current M060 Team Tasks implementation must remain intact. Experimental finance, supplier, CargoX, DHL, workspace, schema, and business-workflow changes are excluded.

## Environment and safety boundary

- Target: production ERP only (`vision_erp`, `vision-os_dolibarr_custom`, `erp.visiontrading.biz`).
- No DNS, proxy, mail, public React, WordPress, staging, schema, customer record, order, shipment, document, or other business-data mutation.
- Release files and helper/control files must be immutable and checksum-pinned.
- Compare and preflight are read-only.
- Apply must create a root-only code backup and an exact snapshot of every branding constant before the first mutation.
- Apply must automatically restore both code and configuration if verification fails.
- Rollback must restore the exact pre-release code and prior constant presence/values.

## Acceptance

1. Source and release-contract tests pass, including preservation assertions for M060 Team Tasks.
2. Production preflight confirms exact container, volume, artifact, file set, ownership, free space, and database connectivity.
3. Apply records protected backup paths and hashes before mutation.
4. Anonymous login visibly shows `Vision-China Planet OS` and no visible `Dolibarr` label or version.
5. Authenticated Operations, Tasks, and SOP pages show the shared top navigation without the module sidebar and retain working core behavior.
6. `verify` and `status` report the pinned release deployed; rollback remains available.

## Approval gates

Preparing, committing, pushing, and uploading pinned files does not authorize production mutation. After exact artifact/helper/installer/sudoers hashes are known, production compare/preflight and production apply/verify require Ahmed's explicit approval for M075-R01.
