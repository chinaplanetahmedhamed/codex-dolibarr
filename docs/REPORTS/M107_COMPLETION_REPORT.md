# M107 release checkpoint — 2026-10-24

Status: local implementation and core upgrade tested; production release pending.

Approved feature: bounded customer workspace > native project > existing board/list/task. Native Societe extra-field boolean cpo_board_workspace defaults to zero. Additive cpo_project_board maps one board per native project with entity-scoped uniqueness. Existing generic boards remain available separately. No recursive boards, customer access grants or integrations.

Release baseline 2249e28ab71c86398afb24c0061c6392ab1374a2. Seven prior files modified, three new module files. Live source/schema must be checked afresh before any apply. Feature flag CPO_CUSTOMER_WORKSPACES_ENABLED defaults off; migration is explicit and idempotent.

Local original M097 core updated from 23.0.3 to official latest stable 24.0.2 (commit 7e927764767343334aaa067ca5ec054cfea0a26b; archive SHA-256 5aa6b22914768457b647cd6a77a3807df02d3e33220617922f850768249cd641), preserving conf/custom/documents. Native three-stage migration completed on isolated local database. The native upgrader changed rights automatically: 79 added rows removed and seven removed baseline rows restored; original 38 permission rows, account security fields and group rights match saved baseline hashes. No new credentials or rights remain. Private full DB and original core/documents backups retained outside Git.

Verification: seven PHP lints, JS syntax, diff whitespace, SQLite persistence (28), real renderer harness. Against production-version official core 24.0.1: workspace native 25, original M097 58, authenticated HTTP 20, desktop/mobile UI navigation. Against local 24.0.2: same native/HTTP suites pass; physical compatible rollback restores seven original files and flag zero while retaining all mapping, operational writes, accounts, password hashes and rights. Arabic RTL/English and responsive views inspected on actual application. Existing test accounts lack native customer/project creation rights; those grants are preserved, so positive native create-form submission is a coverage gap. Native option persistence and permission-conditioned links are covered; no production synthetic records will be created for acceptance.

Visibility: all customer/project routes, counts/search/task/file paths supplement existing membership permissions with native entity/customer/project scope. Direct card grants do not bypass scope. Customer Center/Mobile gain no new public access; responsive internal Boards is affected.

Production target: erp.visiontrading.biz/custom/chinaplanetoperations/tasks/boards.php, vision_erp/vision_db on srv1011043. Production core stays 24.0.1; only ten approved module paths plus additive field/map/feature flag may change. Require exact pinned immutable artifact, fresh metadata/source preflight, private full DB and old-code backup, protected-record digests, authenticated native acceptance and rollback. Production receipts will be appended after verification.

Compatible rollback: flag zero plus restore exact previous seven files; retain additive schema and all business writes. Never restore an old production DB over newer work.
