# M074 — Field QC Report

## Mission control

- **Mission ID:** M074
- **Type:** IMPLEMENTATION
- **Status:** IMPLEMENTED_LOCAL_PENDING_RUNTIME_ACCEPTANCE
- **Architect owner:** Agent 007
- **Implementation owner:** Agent 007
- **Required reviewers:** Internal Operations, Customer Portal, UI/UX, Security, QA, Architect
- **Dependencies:** Existing `chinaplanetoperations`, Customer Center operational reports, and customer mobile API
- **Target environment:** Local development only
- **Production:** Forbidden
- **Ahmed approval:** Approved in the 2026-09-01 implementation request, including manual QC points
- **Approval date:** 2026-09-01

## Business objective

Turn factory quality inspections into a controlled ChinaPlanetOS workflow. Staff prepare a requirement-based inspection from a customer Sales Order, add template, contract-assisted, or fully manual checkpoints, capture observations and media from a phone with offline recovery, submit the work for supervisor review, and publish one immutable customer-safe report to Customer Center and the customer mobile app.

## Current system context

- **Verified source:** Dolibarr 23.0.3 custom source already uses `cpo_operation` for `quality_control`, creates QC tasks, stores reviewed operation documents, and exposes customer-visible operational reports.
- **Verified source:** Customer Center and the customer mobile API already enforce `entity`, `fk_soc`, `customer_visible`, document visibility, approval and current-version filters.
- **Verified gap:** QC is currently flat operation data plus generic documents. It has no requirement-by-requirement model, immutable publication, checkpoint evidence, inspector PWA, offline synchronization, or inline report media.
- **Unknown runtime:** Local HTTP runtime at `127.0.0.1:8088` was unavailable on 2026-09-01. Module enablement and deployed table state must be verified before runtime/schema acceptance.

## Scope

1. Add an additive QC schema, migration service and four rights: plan, execute, review and publish.
2. Create and edit a QC plan linked to the existing customer, project and Sales Order; store an immutable order/specification snapshot.
3. Generate draft checkpoints from order lines and approved templates; accept human-reviewed contract suggestions; allow staff to add, edit, reorder, duplicate and remove manual checkpoints before field work starts.
4. Preserve checkpoint source as `order`, `template`, `contract_ai`, or `manual`. AI output is suggestion-only and the provider remains disabled unless separately approved.
5. Provide a mobile-first installable staff PWA with encrypted local drafts, one-checkpoint-at-a-time execution, explicit pending state, idempotent sync and safe conflict handling.
6. Add resumable chunked image/video evidence upload, internal originals, customer-safe derivatives, hashes, review state and private storage.
7. Add supervisor review, overall result, immutable publication snapshot and printable PDF summary with a QR code to the authenticated online report.
8. Extend Customer Center and the mobile API/app with structured checkpoints and approved inline image/video evidence while retaining the existing `fields` and `documents` contract.
9. Add source, security, contract, offline, media, responsive and regression tests plus parity and completion documentation.

## Out of scope

- Production or `backup-erp` deployment, module activation, database migration execution, server package installation, DNS, proxy or storage-volume changes.
- External AI-provider activation or sending contracts/customer documents to an external model.
- WhatsApp/email automation, customer comments/approval, customer live inspection progress, accounting, supplier payment, or shipping workflow changes.
- Automatic deletion of original QC evidence or edits to Dolibarr core.

## Components to inspect

- `chinaplanetoperations` module descriptor, operation service/routes, document storage, permissions and SQL installer.
- Dolibarr Sales Order lines/documents and the current `fk_soc`/project ownership contract.
- Customer Center operation-report queries, protected download routes, mobile DTO/API and customer app report screens.
- Existing upload validation, Range streaming, module migrations, synthetic fixtures and report-security tests.

## Architecture and data flow

`Sales Order + approved specifications -> cpo_operation(quality_control) -> immutable qc_source_snapshot -> editable checkpoints -> locked field package -> offline PWA mutations/evidence -> supervisor review -> immutable publication -> Customer Center/mobile projection`

- `cpo_operation` remains the report header and owner relation.
- `cpo_qc_checkpoint` stores normalized editable checkpoints and optimistic revisions.
- `cpo_qc_evidence` stores evidence metadata, review/visibility state and private storage references.
- `cpo_qc_publication` stores the immutable customer projection, overall result, version, SHA-256 and reviewer/publish metadata.
- Publication reads the frozen snapshot, never mutable checkpoint rows. Post-publication correction requires a new publication revision; source requirements remain locked after execution starts.
- Same-origin staff JSON endpoints use Dolibarr session permissions and CSRF. Client mutation IDs and checkpoint revisions make retries idempotent and conflicts fail closed.
- Customer report endpoints remain backward compatible and add `overall_result`, `inspection`, and structured `sections` only for a current publication.
- For `quality_control`, customer list, count, project, detail and media paths require a current `cpo_qc_publication`; `cpo_operation.customer_visible` alone never exposes a draft QC report. Legacy non-QC behavior remains unchanged.
- Published QC cards and details derive overall result, inspection date, publication date and version from the immutable publication. They do not expose mutable stage, progress, next action or deadline.

### Plan lifecycle and revisions

- Persist `draft -> locked -> field_complete -> submitted -> reviewed -> published` in the `qc_plan_state` operation-data section. Every transition is server validated and performed under an operation row lock; transitions never rely on client state alone.
- Creating `qc_source_snapshot` atomically freezes `entity`, customer, Sales Order and optional project ownership. The generic operation editor must reject changes to `fk_soc`, `fk_commande` and `fk_project` after the snapshot exists.
- Assigning or reassigning the inspector before lock requires `qc_plan` or administrator authority; generic `operation_write` is insufficient. `fk_user_assign` freezes at lock, and a later inspector change requires a new plan revision.
- Locking freezes the source snapshot, checkpoint requirement text/scope/order, AQL configuration and field-package revision. Results and evidence remain editable only during execution by the assigned inspector.
- A post-lock requirement change or reinspection creates a new `quality_control` operation/plan revision linked to the prior operation/publication; it never unlocks or rewrites the prior field package.
- An inspector may append an unexpected `finding` record after lock. It is stored in `cpo_qc_checkpoint` with `record_kind=finding` and `source=manual`, scope `inspection`, and cannot alter any approved requirement. It may be submitted unreviewed with the field package; `qc_review` must disposition every finding before transition to `reviewed` or `published`.

### Snapshot and checkpoint scope

- Each requirement checkpoint has `scope_type=order_line` with the immutable snapshot-line key, or `scope_type=inspection` for whole-inspection requirements. A missing/foreign line key is rejected.
- Snapshot lines include order-line identity, product reference, label/description, model/specification text, quantity/unit and customer-safe approved image/specification copies. Every copied asset stores its source reference, version/date when available, SHA-256, MIME and size in private QC storage.
- Project is optional. When present, the project, Sales Order and QC operation must share the same `entity` and `fk_soc`.

### AQL contract

- AQL is optional per plan. Version 1 supports a versioned lookup adapter for ISO 2859-1:2026, single sampling, normal inspection, General Inspection Level II. The selected lookup dataset ID and SHA-256 are frozen in the plan and publication.
- Because the standard tables are licensed data, no values are guessed or scraped. An administrator must install an independently validated/licensed table dataset; without it the UI disables AQL calculation and manual inspection remains available.
- Inputs are lot size and an explicit AQL value for each enabled defect class. Lot-range endpoints are inclusive, the exact table code-letter/sample-size/accept/reject values are used, table-arrow escalation is followed exactly, and no interpolation or extrapolation is allowed. If the calculated sample exceeds the lot, inspect 100% of the lot.
- Critical zero-tolerance is an explicit plan rule (`Ac=0/Re=1`), not an implicit default. Major/minor thresholds come only from the selected table. The adapter returns standard/version, inputs, code letter, sample size and Ac/Re per class for supervisor verification.

## Permissions and visibility

- `qc_plan`: create/freeze plans and manage pre-field checkpoints, including manual points.
- `qc_execute`: download assigned field packages, record observations and upload evidence.
- `qc_review`: review results and approve/reject individual customer evidence.
- `qc_publish`: select the overall result and publish an immutable customer version.
- The four new right IDs must be collision-free and default-disabled; only Dolibarr administrators have implicit override.
- `qc_execute` is limited to the internal user assigned in `cpo_operation.fk_user_assign`. `qc_plan`, `qc_review` and `qc_publish` are entity-scoped for internal users with the exact corresponding right; no Team Tasks manager grant is reused.
- `qc_review` may approve evidence and record review conclusions but cannot alter raw inspector observations/results. `qc_publish` cannot approve evidence on the reviewer's behalf.
- An inspector cannot self-review a finding. Approved customer-visible findings enter the publication; rejected or internal findings remain immutable internal records and never enter the customer projection.
- Internal original evidence is readable only by the assigned inspector, a `qc_review` user or an administrator. `read`, `operation_write` or `qc_publish` alone never grants original-file access.
- Staff routes require internal users, `entity`, operation rights, assignment where required, CSRF for mutations and direct-object verification.
- Customer web/mobile queries derive ownership from the authenticated session and require matching `entity` + `fk_soc`, active operation, current publication and approved customer evidence.
- `cpo_qc_publication` stores the frozen `entity` and `fk_soc`; customer reads require publication, operation and authenticated customer ownership to match.
- Supplier identity, cost, margin, internal notes, original metadata and unapproved files never enter customer DTOs or generated reports.
- The customer projection also excludes checkpoint source, AI source page/confidence, editable revision fields, storage keys and original device/file metadata. Manual and accepted AI checkpoints render as ordinary requirements without source badges.

## Database, accounting, and configuration impact

- **Database:** Three additive `cpo_qc_*` tables with entity/operation indexes and an idempotent migration. No existing column is removed or reinterpreted. `cpo_operation_data` also stores the uniquely keyed `qc_source_snapshot` and `qc_plan_state` sections.
- **Persistence allocation:** `cpo_qc_checkpoint` stores plan requirements/findings, stable client UUIDs, optimistic revision and a bounded mutation receipt ledger; `cpo_qc_evidence` stores evidence plus upload-session ownership/token hash/expiry, chunk manifest and finalize state; `cpo_qc_publication` stores immutable customer versions/current marker/PDF metadata. Plan state/package revision and plan-level mutation receipts are in `qc_plan_state` under row lock. This allocation intentionally avoids an unapproved fourth table.
- **Accounting:** None.
- **Configuration/module state:** Module version and permissions change in source. No module enable/disable or runtime constant mutation is authorized in this mission.

## Functional requirements

1. Only a `quality_control` operation with matching customer/order ownership may own a QC plan.
2. Order snapshot creation copies customer-safe order line identity, description, quantity/unit and linked references without supplier/commercial-confidential data.
3. Manual checkpoints are first-class: create, edit, reorder, duplicate and remove before lock; their source is always `manual`.
4. Checkpoints record record kind, scope, section, requirement, expected value/tolerance, observation, result (`pending/pass/fail/not_applicable`), severity (`none/critical/major/minor`), `mandatory`, optional AQL counts, public/internal note, minimum required evidence count, position and revision.
5. Starting field execution locks the plan. All mandatory checkpoints must be completed and all uploads synchronized before submission.
6. Server submission requires every mandatory checkpoint to be `pass`, `fail` or `not_applicable`; pass/fail requires an observation, fail requires severity other than `none`, N/A requires a rationale and severity `none`, required evidence count must be met by finalized files, AQL counts must reconcile, no upload may be pending, and the submitted package revision must be current. “Completed locally” never means “submitted to server.”
7. Offline records/files use AES-256-GCM with a fresh random IV per ciphertext, PBKDF2-HMAC-SHA-256 with a random 128-bit salt and a strong recorded iteration count, and AAD binding user/entity/operation/record. The field PIN is at least eight digits or equivalent entropy; the key exists in memory only. Credentials and access/refresh/CSRF tokens are never cached or queued.
8. The Service Worker caches only versioned public app-shell assets; staff JSON, media and session responses are network-only with `no-store`. The PWA locks on inactivity/background and clears local QC data on logout, account/entity change and confirmed submission. PIN loss means unsynced encrypted evidence is unrecoverable; camera-roll copies remain outside PWA control and are disclosed to staff.
9. Evidence uploads use a cryptographically random opaque upload ID stored only as a hash, bound to user/entity/operation with expiry and single-finalize semantics. Chunks are no larger than 20 MiB, the total file cap is 200 MiB, and the effective chunk size must also fit local PHP limits.
10. Upload validation covers chunk index/count/offset/length and per-chunk SHA-256, then full size/hash/server-side `finfo` MIME/decode/probe. Unique client evidence IDs and locked manifests prevent duplicates; reuse of any mutation ID with a different payload returns `409`.
11. Server-generated temporary/final paths stay beneath a private root with canonical-path and symlink checks and atomic rename. Concurrent-session, temporary-byte and rate limits apply; expired abandoned upload sessions/chunks may be cleaned, but finalized QC evidence is never auto-deleted.
12. Images are decoded with dimension/pixel limits then re-encoded to remove EXIF/GPS/device metadata. Video is probed and remuxed/re-encoded to an approved customer format with metadata removed. SVG, HTML and polyglots are rejected. If required video tooling is absent, the original remains internal and publication is blocked; package installation is out of scope. Original and safe derivative have separate hashes/sizes/MIMEs.
13. Originals remain internal. Only reviewer-approved customer-safe derivatives are published. Unsupported sanitization fails publication rather than exposing original metadata.
14. Supervisor may publish Pass, Fail, Pending corrective action, or Reinspection required. Publishing Fail is valid and never converted into Pass. Publishing Pass while any checkpoint failed or AQL rejected requires a non-empty supervisor override rationale frozen into the publication.
15. Publication is transactional under operation/publication row locks: generate and validate canonical customer JSON and PDF artifacts first, insert a unique version, atomically switch exactly one current publication, then commit. Any failure leaves the prior version current.
16. Publication SHA-256 is computed over deterministic canonical JSON bytes and verified when read. Public note, legacy fields/documents, structured sections, approved derivative metadata and PDF all come from that immutable snapshot—not mutable operation data, notes, generic documents or checkpoints.
17. Web and mobile reports render requirement versus observation, result/severity, notes, approved images and authenticated Range-capable video.
18. Existing report consumers continue receiving `fields` and `documents`. Optional `overall_result`, `inspection`, and `sections` are additive and absent for legacy reports. Each published checkpoint projection contains only ordered section/checkpoint, requirement, expected value/tolerance, observation, result, severity, public note and opaque approved-evidence metadata.
19. AI extraction uses a disabled-by-default server adapter and produces proposals with source/page/confidence. Human acceptance is mandatory; manual entry always remains available.
20. Customer web/mobile UI shows a prominent text-and-icon overall-result badge, summary counts, ordered section cards and failure anchors. Meaning never depends on color alone.
21. Requirement/observation cards wrap long Arabic and English text, media is lazy loaded, and empty/error/download-fallback states remain usable without horizontal overflow or undersized controls.
22. Locale direction and labels come from the active Arabic RTL or English LTR locale; customer report views do not hard-code RTL or mixed-language labels.
23. Protected customer media/PDF routes re-authorize every full/Range request against authenticated ownership, current publication and approved derivative using opaque IDs only. They verify canonical path/hash/size and return mismatch as `404`, with `private, no-store`, `nosniff` and `no-referrer`; originals are never routable to customers.
24. Protected video uses controls, metadata preload, byte-range seeking and a secure authenticated-download fallback. The mobile player preserves authentication across token refresh and never auto-downloads a large video.
25. The PDF displays publication version and overall result, includes summary/checkpoint rows and approved image thumbnails, excludes embedded video, and uses a stable authenticated QR URL with no token or secret, labelled as the latest online report.
26. Checkpoint/failure anchors, the overall-result badge, image captions, video controls and secure-download fallback have localized accessible names, logical focus order and visible focus, and remain operable by keyboard and screen reader without touch or mouse.
27. Staff mutations accept bounded JSON over POST/PUT only, require same-origin plus a fresh `X-CSRF-Token`, and obtain a new token after reconnect; CSRF is never stored offline. Session/right/entity/assignment checks run before idempotency lookup. Customer bearer/opaque-ID APIs remain separate from staff session/CSRF routes.

## Success criteria

- A multi-line Sales Order produces a stable QC snapshot and editable draft checklist.
- Staff can add manual points and complete a locked inspection offline, resume after closing, and synchronize without duplicates.
- Interrupted/duplicate/tampered/cross-user uploads fail closed or resume safely.
- A supervisor can publish either a passing or failing immutable report.
- Customer A sees only A's current approved report/evidence on web and mobile; customer B and forged/direct IDs are denied.
- Approved videos stream with byte ranges; printable output includes no embedded video and links only to authenticated report access.
- Existing legacy report and document contracts remain passing.
- A 25+ checkpoint report with long Arabic/English requirements, mixed results and mixed media remains readable on desktop, 390px and a second phone width.

## Technical restrictions

- Extend existing Dolibarr; no standalone replacement or core edits.
- Keep one code writer. Reviewers are read-only and cannot expand scope.
- No production, staging, runtime, schema or business-data mutation.
- Do not expose credentials, suppliers, cost, margin, private notes, original metadata or private files.
- No external AI call without a new explicit provider/privacy approval.

## Risks and rollback concept

- **Risks:** Offline-device confidentiality, large-media reliability/storage, customer metadata leakage, optimistic-sync conflicts, schema/runtime mismatch and backward compatibility.
- **Rollback:** Revert source files and leave additive tables/data intact but unused. Migration must be idempotent and have a documented down procedure that refuses to drop non-empty QC tables without separate destructive-data approval.

## Testing requirements

- PHP lint, module/M074 tests, `git diff --check`, customer operation-report security regressions and mobile lint/typecheck/tests.
- Order/customer mismatch, forged IDs, missing rights, CSRF, incomplete checkpoints, stale revisions, replayed mutation IDs, chunk tamper/size/MIME/hash/path and unpublished evidence.
- Inspector reassignment authority/freeze/new revision and denial of original evidence to read/operation-write/publish-only users.
- State-transition and lock races; frozen customer/order/project ownership; snapshot stability and asset hashes; optional-project ownership; new-plan revision/reinspection behavior; unexpected finding behavior.
- Submission with unreviewed findings, publication blocked until reviewer disposition, inspector/reviewer separation, raw finding preservation and exclusion of internal/rejected findings from customer JSON/PDF/media.
- Manual create/edit/reorder/duplicate/remove and order-line/inspection scoping; source preservation; lock rejection; field-package and publication inclusion.
- AQL dataset/version/hash validation, inclusive lot boundaries, table-arrow cases, 100% inspection fallback, per-class Ac/Re boundaries and missing-dataset fail-closed behavior.
- Submission invariants for pass/fail/N/A, observation/rationale/severity/evidence, pending uploads/mutations, AQL reconciliation and supervisor Pass override rationale.
- Publication transaction failure, unique/current switching, canonical hash verification, prior-version immutability and proof that all QC customer fields/documents/media come only from publication JSON.
- Offline close/resume/reconnect/duplicate/conflict/purge behavior, Arabic RTL/English LTR, desktop, 390px and a second phone width.
- Legacy reports omit the new optional DTO keys; QC list/count/project/detail routes reject unpublished reports even when `customer_visible=1`.
- Token refresh plus Range seek, lazy media, secure download fallback, localized screen-reader labels, keyboard-only navigation, visible focus, logical focus order, result accessibility, 25+ checkpoint pagination/layout and authenticated PDF QR behavior.
- Synthetic customer A/B only; no production data or credentials.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Internal Operations | Independent implementation review | PASS_WITH_CONDITIONS | 2026-09-02 | No source blocker after queued-package rehydration and superseded-upload corrections; database/runtime and real-device acceptance remain required |
| Customer Portal + UI/UX | Independent implementation review | PASS_WITH_CONDITIONS | 2026-09-02 | No source blocker; authenticated browser/database, Safari/iPhone, Android, 390px RTL/LTR, PDF/QR and cross-customer acceptance remain required |
| Security | Independent implementation review | PASS | 2026-09-02 | No remaining source finding after shared definitive/ambiguous session handling was applied to binary chunks; focused security tests pass |
| QA | Independent operations/QA review | PASS_WITH_CONDITIONS | 2026-09-02 | Source gates pass; database migration/readiness, authenticated end-to-end and real offline/upload/video device tests remain unavailable locally |
| Architect | Agent 007 | IMPLEMENTED_LOCAL_PENDING_RUNTIME_ACCEPTANCE | 2026-09-02 | Full local implementation and source verification complete; no staging or production action authorized or performed |
| Ahmed | Ahmed Hamed | APPROVED | 2026-09-01 | Requested full implementation and added manual points |

## Completion report contract

Agent 007 must create `docs/REPORTS/M074_COMPLETION_REPORT.md` covering scope, every changed file, schema/config/rights, tests, isolation, offline/media limitations, parity, rollback, branch/commit evidence and independent reviewer verdicts.
