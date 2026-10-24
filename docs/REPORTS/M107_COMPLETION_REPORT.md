# M107 release checkpoint — 2026-10-24

Status: COMPLETED — production feature deployed and authenticated acceptance verified; original local M097 core updated and verified at 24.0.2.

Approved feature: bounded customer workspace > native project > existing board/list/task. Native Societe extra-field boolean cpo_board_workspace defaults to zero. Additive cpo_project_board maps one board per native project with entity-scoped uniqueness. Existing generic boards remain available separately. No recursive boards, customer access grants or integrations.

Release baseline 2249e28ab71c86398afb24c0061c6392ab1374a2. Seven prior files modified, three new module files. Live source/schema must be checked afresh before any apply. Feature flag CPO_CUSTOMER_WORKSPACES_ENABLED defaults off; migration is explicit and idempotent.

Local original M097 core updated from 23.0.3 to official latest stable 24.0.2 (commit 7e927764767343334aaa067ca5ec054cfea0a26b; archive SHA-256 5aa6b22914768457b647cd6a77a3807df02d3e33220617922f850768249cd641), preserving conf/custom/documents. Native three-stage migration completed on isolated local database. The native upgrader changed rights automatically: 79 added rows removed and seven removed baseline rows restored; original 38 permission rows, account security fields and group rights match saved baseline hashes. No new credentials or rights remain. Private full DB and original core/documents backups retained outside Git.

Verification: seven PHP lints, JS syntax, diff whitespace, SQLite persistence (28), real renderer harness. Against production-version official core 24.0.1: workspace native 25, original M097 58, authenticated HTTP 20, desktop/mobile UI navigation. Against local 24.0.2: same native/HTTP suites pass; physical compatible rollback restores seven original files and flag zero while retaining all mapping, operational writes, accounts, password hashes and rights. Arabic RTL/English and responsive views inspected on actual application. Existing test accounts lack native customer/project creation rights; those grants are preserved, so positive native create-form submission is a coverage gap. Native option persistence and permission-conditioned links are covered; no production synthetic records will be created for acceptance.

Visibility: all customer/project routes, counts/search/task/file paths supplement existing membership permissions with native entity/customer/project scope. Direct card grants do not bypass scope. Customer Center/Mobile gain no new public access; responsive internal Boards is affected.

Production target: erp.visiontrading.biz/custom/chinaplanetoperations/tasks/boards.php, vision_erp/vision_db on srv1011043. Production core stays 24.0.1; only ten approved module paths plus additive field/map/feature flag may change. Require exact pinned immutable artifact, fresh metadata/source preflight, private full DB and old-code backup, protected-record digests, authenticated native acceptance and rollback. Production receipts will be appended after verification.

Compatible rollback: flag zero plus restore exact previous seven files; retain additive schema and all business writes. Never restore an old production DB over newer work.

## Confirmed live release

Applied 2026-10-24T11:43:18Z to the exact approved URL. Source commit c0a6c69109e2771a54fe8772d74f71d3c2c96de2 is pushed to codex/m107-customer-project-workspaces. Artifact SHA-256 bb3ba56d04192d502c1a540127fd5d95d8db654673b868ea1925b680f211a65b. Root-owned mission helper SHA-256 b0e0eda8bc1cda77c14ebf1e8451ed05ae492519376e83329359109092289662; no sudo/access expansion.

Fresh preflight matched seven prior files and 20 unchanged dependencies; three additive source paths and workspace schema were absent. Full private database backup SHA-256 b97754416ad30182419bdd311d6a78fd0681937e903520e784bef842fcc186f7, with exact prior code and file metadata, is held at /root/chinaplanetos-m107-workspaces-20261024. Applied all ten files, linted production PHP, ran the explicit additive migration twice, enabled entity1 feature flag and verified runtime file pins/schema. Production remains24.0.1 with the original image, volumes, config and running containers. No restart/recreation, native grants, credentials or security settings changed.

Protected-record hashes matched across deployment and verify: customers, projects, boards/lists/cards/members/grants, tasks/contexts/comments/assets, account security fields, user and group rights. Authenticated UI confirmed customer gallery, ten preserved generic boards and existing board6 list/task rendering. The default-off customer option exists in native creation under More. A forged customer route is rejected through the actual token-aware UI. Arabic and English views were checked. No production business records were created or automatically grouped; initial customer gallery is intentionally empty until an admin enables selected customer spaces.

Original local M097 is available at http://127.0.0.1:8107 with official24.0.2 and the tested module source. Private QA8117 stopped; existing DB8889 remains loopback-only. Task-created synthetic UI fixtures were removed transactionally only after original record/security/right hashes matched saved baseline (native-added societe.euid column excluded from its original-column comparison). Original core/DB/docs backups retained. New workspace schema stays additive.

Live overview screenshot (cropped to exclude personal account header) is confirmed in Library: libfile_dcd15b9589dc8191983ce34a658f285b, file_000000003b3882309b16418bc75d3776. Sanitized production preflight/apply/verify and final acceptance receipts are retained with the release. No unresolved release blocker.
