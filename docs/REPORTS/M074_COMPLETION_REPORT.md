# M074 Local Completion Report — Field QC Report

## Control record

- **Mission:** M074
- **Result:** `IMPLEMENTED_LOCAL_COMPOSITE / SOURCE_GATES_PASS / PENDING_RUNTIME_AND_DEVICE_ACCEPTANCE`
- **Branch:** `codex/m074-qc-field-report-composite`
- **Composite baseline:** M073 `1bc01214b13ace38d2cec98c7abd1d3b22355328`
- **Preserved M074 implementation commits:** `b77538bbb3974f9b24d60d2d44c30939350683ed`, `16eef9ec0535179f2d3c4dabe620faf4f20dc2cb`
- **Composite integration commit:** `a24f0d8ab4cf95277a5986594cc99e3dfe189002`
- **Composite portal-security correction:** `d94e3ab85b96c50b91c46434438000378795d54a`
- **Environment:** clean local worktree only
- **Database/schema/runtime changes:** none executed
- **External AI:** disabled; no contract or customer data was sent externally
- **Staging/production:** not accessed or changed

## Lineage correction

The first M074 implementation was locally correct in isolation but was based on cached `origin/main` (`057af0f`) instead of the accepted M069-M073 sequence. A direct release from that branch would therefore remove later accepted Customer Center, quotation-lifecycle and release-system work.

The old branch is retained unchanged for audit and is explicitly non-releasable. The corrected branch merges the full M073 head into M074, preserves the six overlapping Customer Center/mobile surfaces deliberately, and remaps the QC rights after discovering that M071 already owns `500525` and `500526`. The M074 contract now asserts that all six M071/M074 right IDs are uniquely assigned.

The M072-R01 and M073-R01 archives and checksum pins remain valid only as historical evidence for their original missions. They do not contain M074 and must not be reused for a staging deployment. M074 requires a new independently reviewed, checksum-pinned artifact after this composite source is accepted.

## Delivered scope

M074 extends the existing `chinaplanetoperations` quality-control operation. A QC plan can freeze a Sales Order/customer/project snapshot, generate points from order lines or a hash-pinned template, accept separately approved contract proposals, and create, edit, reorder, duplicate or remove fully manual checkpoints before the plan is locked.

The field PWA provides one-checkpoint-at-a-time inspection, encrypted local recovery, locally navigable unexpected findings, photo/video capture, idempotent synchronization, optimistic conflict detection, resumable chunk uploads and a pending-work gate before submission. Supervisor review separates evidence approval from publishing and supports Pass, Fail, Pending corrective action and Reinspection required.

Publication creates an immutable, hashed customer projection and PDF. Customer Center and the existing customer mobile app receive backward-compatible structured inspection data, approved customer-safe images, authenticated Range-capable video and an authenticated PDF/QR path. Drafts, originals, internal notes, suppliers, costs and margins remain outside customer projections.

## Architecture and data

- `cpo_operation` remains the entity/customer/order/project-owned QC header.
- `cpo_qc_checkpoint` stores requirements, findings, results, AQL observations, revisions and bounded mutation receipts.
- `cpo_qc_evidence` stores resumable-upload ownership/manifests, internal originals, sanitized derivatives, hashes, review state and revisions.
- `cpo_qc_publication` stores immutable versions, the current marker, overall result, canonical JSON hash and PDF metadata.
- `qc_source_snapshot` and `qc_plan_state` are stored as immutable/locked operation-data sections. A requirement revision creates a linked operation, copies the exact prior frozen snapshot and verifies every copied asset hash. Superseded plans reject execution, review and publication.
- Customer reads require matching operation, publication, `entity` and `fk_soc`; only current approved safe derivatives are routable.

## Rights and configuration

Four collision-checked rights are additive and default-disabled:

| ID | Code | Purpose |
|---:|---|---|
| 500527 | `publicrequest_invite` | Preserved accepted M068 Customer Center invitation right |
| 500528 | `qc_plan` | Prepare and freeze QC plans |
| 500529 | `qc_execute` | Execute the assigned field inspection |
| 500530 | `qc_review` | Review results and evidence |
| 500531 | `qc_publish` | Publish immutable customer reports |

Optional configuration is fail-closed:

- `CHINAPLANETOPERATIONS_QC_TEMPLATE_PATH` and `CHINAPLANETOPERATIONS_QC_TEMPLATE_SHA256` select an approved checkpoint-template dataset.
- `CHINAPLANETOPERATIONS_AQL_DATASET_PATH` and `CHINAPLANETOPERATIONS_AQL_DATASET_SHA256` select an independently validated/licensed ISO 2859-1:2026 dataset. Without it, manual inspection remains available and AQL calculation is disabled.
- `CHINAPLANETOPERATIONS_FFMPEG_PATH` and `CHINAPLANETOPERATIONS_FFPROBE_PATH` select approved absolute media-tool paths. Missing tooling blocks customer video publication; it never exposes the original.

No configuration value, right grant or module state was changed in a running environment.

## Security, offline and media controls

- Staff mutations are same-origin, internal-session, exact-right, entity/assignment and CSRF checked.
- Local records/files use versioned PBKDF2-HMAC-SHA-256 (310,000 iterations), AES-256-GCM, per-record IV and context AAD. The PIN verifier rejects a wrong PIN before online refresh; lock removes plaintext/key material.
- Definitive authentication loss clears the encrypted cache. Ambiguous redirect/non-JSON session responses lock plaintext while preserving encrypted unsynchronized work for safe recovery.
- Upload IDs and mutation IDs are idempotent and owner-bound. Chunks are capped at 20 MiB and complete files at 200 MiB; offsets, indexes, sizes, MIME/decode/probe and SHA-256 are verified.
- Images are decoded/re-encoded to strip metadata. Videos are processed without a shell, with memory/thread/time/output limits, metadata removal and fragmented MP4 global SIDX output for indexed authenticated Range seeking.
- Originals stay private. Customer media and PDF routes reauthorize every request, verify immutable hash/size/path, return private no-store responses and expose no bearer token in a URL.

## Customer parity

- Legacy `fields` and `documents` remain available; QC adds `overall_result`, `inspection` and `sections[].checkpoints[].evidence[]`.
- Customer web, project cards, CP Kanban and the native app use publication-owned result/version/identity and localized Arabic RTL or English LTR labels.
- Failure summaries navigate to exact checkpoint cards. The native video retry guard is bounded per incident and resets after successful playback; the web player uses immutable-size-bounded Range requests and SIDX time-to-byte mapping with a secure download fallback.
- The PDF contains result/version, summary, checkpoint rows, approved image thumbnails and a login-gated QR URL; video is not embedded.

## Verification

Passed local source gates:

- PHP lint for all `custom/**/*.php` files.
- JavaScript syntax check for the staff PWA.
- M074 behavioral/contract test, including manual/snapshot/revision, supersession, asset hashes, upload restart/quota, bounded media-process timeout, AQL zero tolerance, offline finding evidence, session handling and customer projection/Range checks.
- Customer operation-report security regression.
- M017 mobile data, exact 21-route, OpenAPI byte-hash and security regressions.
- M032 admin/customer responsive regression.
- M070 composite preservation and customer-unit projection regressions.
- M071 catalog-order-v2 source and country-contract regressions.
- M072 release-controller/state and M073 isolated-rebuild contract/failure regressions.
- Customer mobile app lint and TypeScript typecheck, run from the composite checkout with the preserved M074 dependency tree mounted temporarily and removed after verification.
- Customer mobile app: 14 test files / 45 tests, including bounded/final Range, SIDX mapping, video token retry and download safety.
- `git diff --check`.

The M070 preservation test was advanced narrowly for the seven Customer Center files intentionally superseded or security-hardened by M074. It continues to verify the remaining M061 hashes, M070 security corrections, M071 successors, excluded residue, compatibility symlinks and CP Kanban ownership; the M061 document and M074 contracts protect the new behavior on those successor surfaces.

Known local limitations are not M074 source failures:

- Database-backed Operations/workflow tests cannot connect to the unavailable local database at `127.0.0.1:8889`.
- Existing dependency audit findings remain baseline-only; no forced dependency upgrade was made in M074.

## Independent review

- **Customer Portal/UI/UX:** `PASS_WITH_CONDITIONS`; no source blocker. Requires authenticated database/browser, Safari/iPhone and Android playback/seek/token-refresh, exact failure navigation, 390px RTL/LTR, PDF/QR and cross-customer acceptance before release.
- **Operations/QA:** `PASS_WITH_CONDITIONS`; no source blocker after queued-package rehydration and superseded-upload locking corrections. Database migration/readiness, authenticated end-to-end and real-device offline/upload/video acceptance remain required.
- **Security:** `PASS`; no remaining source finding after binary chunks were routed through the shared definitive/ambiguous authentication handler.

Composite integration re-review initially found one release-blocking metadata exposure: the M073 unified web document aggregator could list mutable generic QC document cards even though their download was denied. M074 now excludes `quality_control` from that shared aggregator for both unified and project document views before and after publication, and dedicated M061/M074 regressions protect the boundary. The independent composite verdicts are recorded in the Mission review table.

- **Composite Security:** `PASS`; the corrected permission ranges, customer publication/hash/ownership gates, generic QC exclusion and staff-original authorization remain fail closed.
- **Composite Customer Portal/UI/UX:** `PASS`; the initial P1 is closed, non-QC document behavior remains intact, and no source-level portal/mobile blocker remains.
- **Composite Operations/QA:** `PASS_WITH_CONDITIONS`; ancestry and regressions pass, while database, authenticated browser, offline/device and new-artifact acceptance remain required.

## Changed-file manifest

### Operations module and staff PWA

- `custom/chinaplanetoperations/class/OperationsService.class.php`
- `custom/chinaplanetoperations/class/QcAqlService.class.php`
- `custom/chinaplanetoperations/class/QcContractProposalAdapter.class.php`
- `custom/chinaplanetoperations/class/QcEvidenceService.class.php`
- `custom/chinaplanetoperations/class/QcMigration.class.php`
- `custom/chinaplanetoperations/class/QcPublicationService.class.php`
- `custom/chinaplanetoperations/class/QcService.class.php`
- `custom/chinaplanetoperations/class/QcTemplateService.class.php`
- `custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php`
- `custom/chinaplanetoperations/css/operations.css`
- `custom/chinaplanetoperations/langs/ar_SA/chinaplanetoperations.lang`
- `custom/chinaplanetoperations/langs/en_US/chinaplanetoperations.lang`
- `custom/chinaplanetoperations/langs/zh_CN/chinaplanetoperations.lang`
- `custom/chinaplanetoperations/operations/view.php`
- `custom/chinaplanetoperations/qc/api.php`
- `custom/chinaplanetoperations/qc/app.php`
- `custom/chinaplanetoperations/qc/manifest.php`
- `custom/chinaplanetoperations/qc/media.php`
- `custom/chinaplanetoperations/qc/plan.php`
- `custom/chinaplanetoperations/qc/qc-field.css`
- `custom/chinaplanetoperations/qc/qc-field.js`
- `custom/chinaplanetoperations/qc/qc-icon.svg`
- `custom/chinaplanetoperations/qc/qc-support.js`
- `custom/chinaplanetoperations/qc/review.php`
- `custom/chinaplanetoperations/qc/service-worker.js`
- `custom/chinaplanetoperations/sql/llx_cpo_qc_checkpoint.sql`
- `custom/chinaplanetoperations/sql/llx_cpo_qc_evidence.sql`
- `custom/chinaplanetoperations/sql/llx_cpo_qc_publication.sql`

### Customer Center and project surfaces

- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php`
- `custom/chinaplanetcustomercenter/css/operations.css`
- `custom/chinaplanetcustomercenter/lib/customer_documents.lib.php`
- `custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php`
- `custom/chinaplanetcustomercenter/lib/operation_reports.lib.php`
- `custom/chinaplanetcustomercenter/operation_document.php`
- `custom/chinaplanetcustomercenter/operation_report.php`
- `custom/chinaplanetcustomercenter/operation_reports.php`
- `custom/chinaplanetcustomercenter/project.php`
- `custom/chinaplanetcustomercenter/qc_media.php`
- `custom/chinaplanetcustomercenter/qc_pdf.php`
- `custom/chinaplanetcustomercenter/qc_report.php`
- `custom/cpkanban/lib/customer_project_center.lib.php`

### Customer mobile app

- `mobile/chinaplanet-customer-app/app/(tabs)/more/report/[id].tsx`
- `mobile/chinaplanet-customer-app/app/(tabs)/more/reports.tsx`
- `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`
- `mobile/chinaplanet-customer-app/package-lock.json`
- `mobile/chinaplanet-customer-app/package.json`
- `mobile/chinaplanet-customer-app/src/api/types.ts`
- `mobile/chinaplanet-customer-app/src/components.tsx`
- `mobile/chinaplanet-customer-app/src/components/QcEvidenceItem.tsx`
- `mobile/chinaplanet-customer-app/src/files/authenticatedDownload.ts`
- `mobile/chinaplanet-customer-app/src/files/authenticatedPreview.ts`
- `mobile/chinaplanet-customer-app/src/files/fragmentedMp4.ts`
- `mobile/chinaplanet-customer-app/src/files/videoAuthRetry.ts`
- `mobile/chinaplanet-customer-app/tests/downloadSafety.test.ts`
- `mobile/chinaplanet-customer-app/tests/fragmentedMp4.test.ts`
- `mobile/chinaplanet-customer-app/tests/videoAuthRetry.test.ts`

### Mission and tests

- `deploy/tests/test_m061_customer_documents_contract.php`
- `deploy/tests/test_m070_composite_source.php`
- `deploy/tests/test_m074_qc_contract.php`
- `deploy/tests/test_m074_qc_pwa.js`
- `docs/MISSIONS/M074_FIELD_QC_REPORT.md`
- `docs/REPORTS/M074_COMPLETION_REPORT.md`

The public React website has no M074 source change.

The numerous M069-M073 paths present in the composite merge are inherited lineage, not newly authored M074 scope. They are intentionally excluded from the M074 changed-file manifest above and remain attributable to their original mission commits.

## Rollback and release gates

M074-R07 is frozen locally under `deploy/releases/m074-r07` from source commit `fc6f0fe1bfba303ab45ed011d634ec7712b6ccda`. It merges accepted M068, preserves permission `500527` for `publicrequest_invite`, and assigns QC permissions `500528` through `500531`. Artifact identity, reproducibility, helper controls and the staging-only gate are recorded in `docs/REPORTS/M074_R07_RELEASE_PREPARATION_REPORT.md`; live execution evidence is recorded in `docs/REPORTS/M074_R07_STAGING_EXECUTION.md`.

Source rollback is to revert the M074 commits. Additive QC tables and any later QC records remain dormant; they must not be dropped or deleted without separate destructive-data approval. No automatic evidence deletion is introduced.

Before staging, freeze and independently review a checksum-pinned artifact, verify module/schema readiness and configured private storage/tooling, grant the four rights deliberately, then exercise a synthetic multi-product order end to end. Required acceptance includes full offline close/resume/reconnect, duplicate/conflict/chunk tamper, Pass/Fail publication, customer A/B isolation, PDF/QR, Arabic/English desktop and 390px, and real iPhone/Android video seek and token refresh. Production requires a separate approval after staging acceptance.
