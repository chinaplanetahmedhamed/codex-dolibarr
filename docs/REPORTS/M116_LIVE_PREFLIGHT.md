# M116 live preflight — blocked before apply

Ahmed explicitly approved the live customer-statement release and then the existing saved root connection for srv1011043 / 31.97.158.178. Boards owner confirmed deployment hold and sole-writer ownership in this task. No concurrent release was started by this task.

## Verified 2026-10-26 22:49 UTC

Existing root session identity and existing pinned M107 helper hash were verified. Current live target is vision_erp, Dolibarr24.0.1, database dolibarr_db, canonical existing ERP/custom/documents/DB topology and image. All93 module source pins/expected absences and the preserved native core pins passed against the owner-supplied final Boards baseline. Public Boards JavaScript independently matches that baseline and existing authenticated Boards renders.

All five cpo_account tables are absent. CPO_ACCOUNT_STATEMENTS_ENABLED is absent/disabled. ZipArchive is present. Native PHP bcadd/bcmul/bcdiv/bccomp are absent: the tested exact-decimal ledger requires BCMath and cannot be activated on the current runtime. No extension installation, image rebuild, restart, PHP/security/configuration change or arithmetic substitution was attempted.

The trial's proposed rights IDs500525–500529 are already publicrequest_qualify, publicrequest_publish, publicrequest_invite, qc_plan and qc_execute. These legacy IDs have six group-grant rows; these are NOT finance grants. Nothing in those definitions/grants was changed. A read-only query found no existing definition in500580–500584. The local descriptor has been corrected to the unused range and an executable descriptor regression verifies the mapping and default-off behavior. The test failed on the original candidate and passed after the correction. No live registration/grant/reassignment or module reinitialization occurred.

## Validation and disposition

Independent release review found a rights_def ordering bug in the prepared helper; fixed to order that table by id before any helper execution. Seven local release recovery/manifest tests pass. Full M116 regression was rerun successfully before preflight, then rerun successfully after the permission-ID correction; core arithmetic20, service29, hardening/deleted-card/native-mapping, concurrency, export precision/formula safety, begin/audit failures, scoped audit, UI assets, new definition regression, M107 persistence28, Boards DOM and syntax checks pass. Earlier native24.0.2 acceptance is retained; this preflight does not certify production functionality.

The prepared original b7a4acd artifact is BLOCKED and superseded. Do not apply it. A future candidate must contain the corrected IDs, a rebuilt manifest/artifact and a helper checking the new IDs while preserving the legacy permissions. Recheck the new range for orphan user/group grants as well as definitions before use.

Remote writes were limited to the authorized deployment inbox, a checksum-pinned /root/m116_release.py and private release-inspection metadata/lock directory. No deployed module file, database table, feature flag, financial/business row, credential or grant changed. No full backup, migration or apply ran; no rollback was needed. The sanitized inspection receipt is local at release-m116/inspection-observed.json under the task root, with the root copy under /root/chinaplanetos-m116-statements-20261026/. No Library upload, Git push or merge.

## Blocking scope decision

Providing BCMath would require a separately reviewed runtime/image change and possibly a controlled restart, beyond this module/schema-only release. Alternatively, an application-only exact-decimal implementation would need separate engineering and differential/native verification before replacing the tested BCMath path. No floating-point workaround is acceptable. The live release remains blocked until that prerequisite is resolved; do not enable the feature or run the old candidate. Once resolved, repeat fresh source/schema/access preflight, then verified scoped code/full-DB backups, guarded additive install, apply, independent verification and read-only UI/ACL/export/attachment regression. Preserve all existing/new business writes during any compatible rollback.
