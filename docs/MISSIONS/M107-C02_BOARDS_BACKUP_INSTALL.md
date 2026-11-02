# M107-C02 — existing Boards feature set and scrolling on backup only

Owner: Ahmed Hamed. User approval: after the backup source mismatch was explained, Ahmed requested “ok install the new board, and the new edits as well, do it now”, then offered a dedicated administrative Termius session (“ready”). Production deployment is not authorized by this request.

Implementer/deployment writer: this existing Boards task. Reviewer: read-only `scroll_review`. Office Expenses and Family Talk have separate owners/resources. The offered session was freshly verified as root on srv1011043/31.97.158.178. Never infer authority to alter grants, sudoers, credentials, networking, images, core, finance or other modules.

## Scope

Install the already implemented bounded customer workspace → project board → lists/tasks feature set M107–M115, existing two-second polling/touch controls/sounds, and scrolling correction48856ac on canonical backup-ERP. The independent task-hub/calendar candidate1bad0cc remains outside this release. No cpkanban removal. No supplier features or M116 finance installation/activation. No module init/reload or new role/permission registration.

Current verified target: erp-staging-dolibarr-1, erp-staging project, erp-staging_erp-staging_custom volume, localhost18088. Image sha256:36e95f5a6ae4308c3fda7ea7b1b93bb845ce45cbdd0ddd0a990131bc6ffadcb9. Native24.0.1/PHP8.2.32/MariaDB10.6.27. Configured application DB was independently checked against canonical database-container IP before the successful native read transaction. Do not target production or the duplicate staging stack.

## Evidence and preservation

80-path stable source/dependency inventory: SHA2565fdf6a68c3f67e338396b9b88c4ba3f903b88a3ff014933dacdcb5e32a42abf6. Native read-only metadata/fingerprints: SHA256a316a8bbb15589e697e1e2b27872fc58ada66f0069a5cdd1b78a0604c75e495a. Dated receipts are outside Git at boards-scroll-20261102/backup-release. A Docker mount-array ordering false mismatch was diagnosed; sorting by Destination preserves value checks. An initial native probe failed at the actual rights_def.id key; no receipt/application/database change occurred. The corrected inspected probe also pins DB host/name/port/prefix and canonical DNS/IP before connection. Seven local probe/resource tests and independent source reviews passed.

Backup Boards matches the pre-M107 source c0a6c691^ in the relevant changed files. 32 inspected paths already match candidate,44 match base,4 differ from both. Preserve existing divergent admin_dashboard.css, operations.css and inbox.php. Preserve the exact Chinese translation source; append only two Boards labels. Shared descriptor/hook overlays add only leftblock and menuLeftMenuItems, retaining all existing rights and excluding unrelated supplier hooks. Never copy the current full shared files over this target.

Before installation the six workspace flags and all seven new tables are absent; account statements are disabled/absent. Existing four boards,18 cards/tasks,12 lists, five memberships and all native customer/project/access records remain authoritative. New schema: cpo_project_board, cpo_project_task, cpo_customer_workspace_meta/pref/appearance, cpo_board_deleted/completion, and the customer boolean extra field. No record backfill, customer auto-enablement, task recreation or native project closure.

## Release gates

1. Freeze a minimal backup-specific application source with five shared-file overlays; immutable allowlist/manifest and artifact. Preserve all external prerequisites at observed hashes.
2. Run applicable full Boards regressions, native-version/core compatibility checks and deployment failure/rollback tests. Keep physical touch explicitly unverified.
3. Use a distinct Boards deployment path and the existing backup-erp GitHub environment/concurrency gates. Do not modify/reuse Office helper/request/state or previous production helper. No new sudoers grants. A current immutable CI receipt and sole-writer lease are required before administrative apply.
4. Reconcile fresh source/image/core/DB identity. Back up exact affected code with existence/hash/UID/GID/mode and the backup DB privately before DDL. Never download private DB contents or expose credentials.
5. Migrations: Workspace, ProjectTask, WorkspaceGallery, BoardLifecycle, Completion, WorkspaceAppearance, BoardsShortcut. Verify idempotence and schema constraints, then enable only the six scoped flags. Do not reinitialize the module or grant rights.
6. Verify preserved business/access fingerprints and independent deployed hashes. Authenticated Arabic/English desktop/phone board/card/menu acceptance; keep Office changes and shared services untouched.

Rollback must retain all later data. Before exposing the feature, exact old-code restoration is allowed only if no new feature records/business changes exist. After feature use, never restore a parser that ignores workspace/project isolation or deleted-board records: retain compatible backend/schema/flags and use the known compatible pre-scroll UI fallback, or stop for explicit reconciliation. No database restoration over subsequent writes.

Current phase: source/schema reconciliation complete; package/deployer and release testing in progress. No application, schema, configuration, permissions or service changes have been applied. Remote writes so far are scoped inbox source probes and non-secret metadata receipts only.
