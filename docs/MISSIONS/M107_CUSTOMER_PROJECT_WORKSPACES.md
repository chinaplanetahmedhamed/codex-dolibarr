# M107 — Customer project workspaces and local core update

Status: COMPLETED.
Authorization: Ahmed's explicit request on 2026-10-24 (Asia/Shanghai): "OK, NOW PUBLISH TO LIVE, AND UPDATE LOCAL DOLIBARR TO LATEST". Earlier approved feature: Customer workspace > Project board > existing Lists/Tasks at erp.visiontrading.biz/custom/chinaplanetoperations/tasks/boards.php.

Scope: release only the tested bounded workspace feature, additive native customer option and project-board association; preserve existing generic boards, object identities, business rows, attachments/comments, memberships and stored native rights. Update the existing local M097 installation with official native core migration and backups to latest stable 24.0.2. Production core stays 24.0.1. No M106 credential step, new user accounts/credentials, access expansion, integrations or unrelated pending source changes.

Source baseline: 2249e28ab71c86398afb24c0061c6392ab1374a2, feature-specific files matched production read-only. Isolated release worktree; no shared branch reset or switch. Recheck live file/schema baseline before apply.

Gates: official artifact/commit pins, local full DB/core/documents backup, native 24.0.1 acceptance, local 24.0.2 acceptance, original board regression, workspace persistence/ownership/isolation/CSRF and compatible rollback, scoped production preflight, private code/full-DB backup, exact file manifest, protected-record preservation, additive migration/activation, exact runtime hashes and authenticated native read/UI acceptance. No synthetic production records or native grants. Stop on failed required gate.

Compatible production rollback: disable only CPO_CUSTOMER_WORKSPACES_ENABLED and restore exact prior affected code, retaining schema and all operational writes. Never restore an old production DB over new records. Local core rollback uses the pre-upgrade local DB/core/documents snapshot, only while local service is stopped.
