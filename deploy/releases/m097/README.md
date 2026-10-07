# M097 accepted Kanban release

Ahmed authorized production replacement on 2026-10-07 after accepting the local preview. This release contains the accepted Boards gallery, full canvas, independent lists, card menus/details, native attachments/covers/rich description, private raster backgrounds and board/task access controls. Advanced Trello integrations/automation are outside this release.

Build source: existing `codex/m091-task-agenda` worktree, baseline b47d690. The 26 changed runtime files are listed in runtime-files.json. The remaining 20 immutable payload files preserve existing production dependencies for staging compatibility. In particular the production language files are preserved byte for byte rather than overwritten with unrelated local changes. Prestate snapshots contain only the 52 listed module code files, not configuration, credentials, documents or business data.

Targets: staging `erp-staging-dolibarr-1` / `erp-staging-mariadb-1` / `erp-staging_erp-staging_custom`, production `vision_erp` / `vision_db` / `vision-os_dolibarr_custom`. Both currently use Dolibarr 24.0.1. No core/container rebuild is included.

The pinned helper validates payload, per-environment baselines/dependencies, containers, mounts, file metadata, PHP and HTTP health. Before mutation it creates root-private code and full DB backups. Eight additive board tables are installed; migration creates placements and translates existing native team/assignee access into board memberships. It verifies canonical tasks/details/assets and native user/group/team rights remain unchanged. No local synthetic data or local attachment grant is deployed. Feature CPO_BOARDS_ENABLED activates legacy All Tasks / My Tasks redirects and navigation.

Rollback action disables/restores the old feature/hook constants and restores exactly the backed-up code. Additive tables/placements remain recoverable. Never restore the old DB over later user/accounting writes. A failed first apply automatically restores code/config; replay is refused. Staging failure blocks production promotion.

Tests: 58 board/service permission assertions; 6 migration integrity/idempotence/rollback assertions; 16 release integrity/rollback tests; 7 rich text sanitizer assertions; native legacy agenda/control/member/file/personal regressions. Local HTTP acceptance covers attachment/background upload, persisted reload, CSRF, stale versions and denied users. Native service acceptance runs on each server with actual active internal actors; browser visual acceptance must be reported separately.

Commands must pin the reviewed helper SHA before invoking `python3 m097-r1-deploy.py staging preflight|apply|verify` then the same artifact for production. Use the authorized existing Termius root session, not a new credential or broader sudo grant. All backups remain on the VPS under `/root/chinaplanetos-m097-r1-{environment}`. Safe result metadata is written to staging-inbox.

Revision R1 fixes the native Dolibarr 24 public CKEditor asset path. The original staging deployment passed migration/integrity checks and was rolled back before production; its immutable artifacts are retained separately. R1 uses a separate state namespace and the same baseline guards. Runtime source is pinned in manifest.json.
