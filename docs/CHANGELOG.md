# ChinaPlanetOS Change Log

## 2026-08-28 — M062-R03 Alexandria public-search staging package

- Added a deterministic code-only staging artifact and checksum-pinned replacement helper for the one-file public shipping gateway correction, with both unchanged PHP dependencies carried as support-only candidate-test closure.
- Preserved the deployed M062-R02 database/schema/runtime state while isolating R03 code backup, state and rollback evidence.
- Added fault-injection coverage proving failed apply-state publication restores the baseline and failed post-restore rollback validation re-applies the exact deployed release.
- Deployed and independently verified the exact one-file gateway correction on canonical `backup-erp`, then deployed the matching React tree to `new.chinaplanet.org`; live desktop and 390px Arabic/English acceptance passed with one Alexandria option and exact Old Port/TMT cards. No database or production change occurred.
- Enforced ERP gateway deployment and verification before the related React staging release; production remains forbidden.

## 2026-08-28 — M062 Alexandria public-search grouping

- Collapsed generic Alexandria, Old Port and TMT into one customer destination option while preserving three exact ERP pricing identities.
- Expanded the grouped search only inside the public-safe gateway, with exact terminal filters embedded in each encrypted booking token for submit-time revalidation.
- Added explicit terminal labels to public React price cards and a fail-safe `Terminal not specified` label for genuinely generic Alexandria rates.
- Suppressed mixed Alexandria terminal history instead of presenting a misleading combined trend; all other exact-route trends remain unchanged.
- Added focused PHP and React regressions; complete React lint, server tests, UI tests and build passed. Local source only; no database, staging or production change.

## 2026-08-25 — M062 port and country normalization

- Replaced prefix-only destination-country detection with one entity-scoped canonical port resolver that normalizes case, punctuation, Unicode variants and verified aliases before using a conservative fuzzy fallback.
- Added a unique normalized-alias table plus raw POD, canonical-port and match-evidence fields; the administrator workflow now rejects alias collisions, validates entity ownership and archives mappings instead of deleting them.
- Correctly separates recognized origin prefixes and all-or-nothing multi-destination values, including `SHEKOU-- SKIKDA, ANNABA`, `NANSHA-- SKIKDA, ANNABA`, `BEJAIA/SKIKDA`, `Oran/CASABLANCA`, `SFAX & SOUSSE` and `TUNIS / RADES`.
- Backed up the local Dolibarr database, restored it once while correcting visibility preservation, then applied the idempotent migration: 3,023 unmapped rows across 36 POD strings became zero; 791 destination rows were added for valid composite routes; all 1,422 resolved destination warnings were removed while 542 historical shipping-line warnings were retained.
- Focused resolver/importer/current-data tests, migration rerun/check, PHP lint, source regressions and authenticated desktop/390px administration checks passed. Existing published-rate integration remains time-blocked because the local rate validity window expired; no validity rule was bypassed.
- Local development database and source only. No Customer Portal/mobile UI, API contract, staging, production, outbound message or Git push was changed.

## 2026-08-15 — M060 internal task card collaboration

- Added task-scoped plain-text comments, authorized mentions and a recipient-only in-app notification inbox with request-key replay protection, stable cursor pagination and administrator logical redaction.
- Added named ordered checklists with item completion, rename, soft remove/restore and keyboard/touch Move up/down controls; collaboration never changes task progress, status, stage or task version.
- Added reusable administrator-managed Arabic/English/Chinese labels, multi-label assignment, retired-label history and board filters with AND semantics.
- Upgraded private task assets with drag/drop, local raster clipboard paste, a sequential multi-file queue, authenticated image lightbox and card cover/media handling through the existing validator.
- Added accessible card-front label chips and visible-task checklist/comment/asset counters, while retaining exact worker/team/entity isolation.
- Backed up, restored and activated module `2.7.0` in Ahmed's approved local Dolibarr runtime. Seven additive tables and disabled-by-default rights `500523`/`500524` were installed; all 14 tasks, 29 activities and 12/44 CP Kanban columns/cards were preserved.
- Authenticated local English desktop, 390×844 and 360px board/card/drawer checks passed visually, including immediate comment insertion/redaction and counter reset. The full AR/ZH and worker/supervisor fault matrix remains a release gate.
- Corrected a real Dolibarr runtime incompatibility found during browser acceptance: collaboration CAS updates now pass the update result to Dolibarr's `affected_rows`, fixing immediate comment redaction and the equivalent checklist/reorder/label update paths.
- Corrected local attachment-size handling: the PHP 8.3 runtime now accepts the module's 50 MB document ceiling (`upload_max_filesize=50M`, `post_max_size=64M`), while the card rejects oversized files before transmission with localized AR/EN/ZH limits (10 MB for images/text, 50 MB for PDF/Office) instead of a generic upload failure.
- The first image uploaded to a task now becomes its card cover automatically, so the Kanban card displays a thumbnail immediately; administrators can still replace, clear or choose another cover. The already-uploaded local acceptance image was set as the current cover and verified on card `CP-TK-2026-000014`.
- Corrected valid Chrome/Skia PDF uploads whose declared compressed stream ends with a carriage-return data byte. The validator now honors the direct stream length for that byte while continuing to reject compressed JavaScript/actions and unsupported filter encodings; the exact locally rejected 551,428-byte PDF now passes validation.
- Customer Center, mobile, public APIs, staging and production were not changed; no outbound message or Git push occurred.

## 2026-08-15 — M059 internal task navigation and configurable work stages

- Added one direct Team Tasks Dolibarr menu entry with role-aware All/Team/My defaults and a prominent New Task dialog that requires an authorized primary team and expected result.
- Added exact-entity configurable work stages with localized names, accessible colors, protected lifecycle columns, optimistic versions, audit activity and deterministic legacy-terminal fallback.
- Added administrator drag handles plus the same server-authorized keyboard/touch Move-to dialog for every role; waiting, review, done, archive and delete rules remain owned by the M058 lifecycle service.
- Added an administrator Manage Columns page for adding, renaming, recoloring, activating/deactivating and work-segment reordering with in-use/protected blocking.
- Backed up and activated module `2.6.0` in the Ahmed-approved local Dolibarr runtime. The authenticated Team Tasks menu/current state, board, New Task, Manage Columns and desktop/390/360 English responsive checks passed.
- Corrected the first local Move-to acceptance failure caused by Dolibarr's empty base URI: AJAX actions now resolve the literal same-origin form route against the current page and reject non-JSON responses with localized safe feedback instead of exposing a JSON parser error.
- Corrected local administrator acceptance: a verified active internal Dolibarr entity-zero superadministrator can open the entity board, while entity-zero non-admin, wrong-entity and external actors remain denied. Administrators can now move any incomplete card directly to any active column, including Done; worker/supervisor workflow restrictions are unchanged.
- Preserved all 14 existing local tasks and all CP Kanban data. Customer Center, mobile, staging and production were not changed; the complete authenticated AR/EN/ZH role/fault matrix remains a pre-release gate.

## 2026-08-14 — M058 secure internal work Kanban Phase 1

- Added an entity-scoped internal worker board with My Tasks, explicit supervisor-team grants, administrator views, Unassigned/Unclassified/Archive handling, card detail, progress, waiting, review, completion, cancellation, archive, restore and exceptional soft-delete workflows.
- Added least-privilege rights `500516`–`500522`, all disabled by default for non-admin users, and one server-side authorization boundary reused by board, card, counts, Operations projections, actions, history and file streams.
- Added additive/idempotent task team, waiting, version, supervisor-grant and task-asset schema; legacy and generated tasks without an explicit team remain administrator-only Unclassified work.
- Added internal-only images/files with strict allowlists and quotas, image normalization, OOXML/PDF/text validation, random private storage, versions, cover selection/removal, authenticated preview/download and no customer visibility state.
- Added Arabic, English and Chinese UI plus responsive single-stage phone navigation, semantic keyboard/touch actions, focused source/runtime/adversarial-file/UI tests, and parity documentation.
- After Ahmed's separate local-run approval, backed up and activated M058 in the local Dolibarr 23.0.3 runtime, preserving all 13 existing tasks. Corrected internal-user compatibility for Dolibarr accounts that represent no third party as `fk_soc=0` and expose a zero-valued user-object entity; exact database entity validation and external-user denial remain enforced. Authenticated `admin-agent` board access passed in the local browser.
- Customer Center, mobile API/app, CP Kanban, projects, orders, shipments, finance, staging and production were not changed; no Git push or remote deployment occurred.

## 2026-08-14 — M056 shipment status email notification

- Added an explicit staff-controlled email option for committed, customer-visible shipment status changes, with server-resolved recipient/sender, Arabic/English/Chinese customer-safe content, authenticated shipment link and Dolibarr Agenda history.
- Preserved operational shipment truth when SMTP or Agenda fails and added a permission/CSRF-protected retry for only the latest entity/customer-scoped public status event.
- Reconciled staging's documented 200MB shipment upload correction as a complete two-file dependency and added a focused dependency contract test.
- Deployed the exact eight-file runtime from `606b54c852230b0674b0745084e107487cf3161d` to canonical `backup-erp`; artifact SHA-256 `19943d703ddfc0866aa79ffb003a58e82780b824162d9ac08e872c46bad54592`. Compare, preflight, backup, apply, independent verify, HTTPS health and anonymous-login boundary passed.
- Authenticated desktop/390px UI passed. A synthetic status transition committed safely, but staging email failed before Mailpit because Dolibarr staging uses PHP mail; no notification or email Agenda row was created and the retry action appeared.
- Ahmed declined changing staging mail settings and explicitly directed the same exact M056 artifact to production for actual-work acceptance. The exact artifact/tooling upload and checksum-locked helper installation passed: helper SHA-256 `6798f0d893b6a6b2683832f96962cb19cfc5565825c1bc2e058b7efeab531a16`, `root:root 0755`, six fixed sudo commands, and status `NOT_DEPLOYED`. Production ERP runtime and data remain unchanged; compare/preflight and apply remain separately gated.
- After Ahmed's separate confirmation, production compare and preflight passed for the exact M055 baseline, runtime commit `606b54c852230b0674b0745084e107487cf3161d`, eight-file scope and artifact SHA-256 `19943d703ddfc0866aa79ffb003a58e82780b824162d9ac08e872c46bad54592`. Independent status remained `NOT_DEPLOYED`; apply was not run and the production runtime/data remained unchanged.
- After Ahmed's final apply/verify confirmation, the restricted helper created and verified backup `m056-before-606b54c-20260814T112029Z.tar.gz` (SHA-256 `9263cb70a8d8b02c3a7c31c23bc7dd8b8c1376fb77510d7fc391116a6fe6c917`), deployed the exact eight files, and passed independent verify. Durable production state is `DEPLOYED`; deployment changed no database, configuration, mailbox, documents, module state, Compose, proxy, DNS, ports or business records. Ahmed retains the actual-work customer email/Agenda/link check.

## 2026-08-13 — M055 website-request email and WhatsApp replies

- Added a staff-only email composer to Website Requests, with the recipient reread from the durable request, sender taken from Dolibarr mail configuration, explicit send confirmation, dual permission checks and CSRF protection.
- Logged outgoing messages in the Dolibarr Agenda and used standard proposal tracking when a request is linked to a quotation so later email replies can be associated with it.
- Added a validated `wa.me` action that opens a protected new tab and prepares a locale-aware draft with the request reference; it never auto-sends or uses WhatsApp/Meta credentials.
- Added Arabic, English and Chinese copy, responsive phone behavior and focused source/security/normalization tests.
- Deployed the exact six-file M055 runtime from commit `991011e8db9a95f0fa98ce5d518d09fca551f4bc` to canonical `backup-erp` through the checksum-pinned fail-closed helper; compare, preflight, code backup, apply, independent verify, HTTPS health and anonymous-login boundary passed.
- After Ahmed accepted staging and explicitly approved the live release, deployed the identical artifact to `erp.visiontrading.biz`; production M045 baseline verify/compare, M055 compare, preflight, backup, apply, independent verify, HTTPS and authenticated desktop/390px acceptance all passed.
- No React, Customer Portal, Mobile App, schema, database, configuration, mailbox, documents, Compose, proxy, DNS or business-data change occurred. No deployment-test message was sent to a customer; Ahmed retains the actual-work send/reply check.

## 2026-08-13 — M054 project-to-Kanban sync ownership

- Moved the project/card synchronization implementation to the CP Kanban module that owns its cards and columns.
- Kept the historical Customer Center include path as a thin compatibility wrapper and updated first-party call sites to the canonical owner.
- Added source characterization for ownership, compatibility, entity scope and unchanged function contracts.
- Local source only; no database, configuration, module state, route, UI, business-data, staging, React, or production change occurred.

## 2026-08-13 — M047 clean baseline and refactoring preparation

- Created a clean Dolibarr branch/worktree from current `origin/main` without resetting or modifying the diverged permanent worktree.
- Preserved Ahmed's local canonical-path documentation commit on the clean branch and added durable Agent instructions, Mission scope and refactoring-baseline evidence.
- Recorded React, Dolibarr, Git, test and restricted runtime evidence while keeping all branches, worktrees, databases, configuration, documents and business records unchanged.
- Production and staging received read-only health/status/compare checks only; no apply, deployment or runtime mutation occurred.

## 2026-08-08 — M044-C01 Shipping Prices import dependency correction

- Restored the authenticated Shipping Prices import page on canonical `backup-erp` by adding the one missing, source-matched `ShippingNoteTranslationService.class.php` dependency.
- Kept the importer and import page unchanged and pinned their verified staging hashes before apply; the first workflow stopped safely on a stale import-page assumption and changed nothing.
- The corrected checksum-pinned GitHub run passed compare, preflight, one-file backup/apply, workflow verify, and independent verify without rollback.
- Authenticated desktop and `390×844` acceptance passed with the workbook form, draft action, and batch history present and no fatal error or horizontal overflow.
- Database, application configuration, shipping rates, documents, business records, Customer Portal, Mobile App, React, and production were not changed.

## 2026-08-07 — M045-C01 piece pricing and controlled staging product sync

- Deployed the checksum-pinned M045-C01 correction to canonical `backup-erp` and synchronized only the allowlisted CMS product `105789` / `WP-9688B` with its Woo mapping and packing fields.
- Submitted synthetic request `WEB-20260807-182858-81C9DA` through `new.chinaplanet.org`; the ERP workspace shows the real product image and `2 cartons × 100 pieces/carton = 200 pieces`.
- Corrected the responsive quotation card after live 390px acceptance exposed Dolibarr's fixed table-cell height; desktop and `390×844` now show every product, packing, mapping, pricing, and notes value with no page-wide horizontal overflow.
- Verified the CMS selling price as `0.34 USD / piece` but did not write it into the EUR base price or create a false EUR quotation. Backup ERP exposes only EUR and has no approved USD rate, so the USD draft remains a documented blocker.
- Restricted compare, preflight, apply, verify, product check, backups, and authenticated browser acceptance passed. No validation, PDF, email, order, invoice, stock, accounting, bulk sync, or production access occurred.

## 2026-08-07 — M045 public-request quotation workspace on backup-erp

- Deployed the exact nine-file M045 Dolibarr runtime to canonical `backup-erp` from commit `8d919fd9f2164bca6cfd859e89df338e985c486a` through the checksum-pinned GitHub workflow.
- Added the nullable proposal link and entity-scoped unique index, explicit quotation-right definition without grants, and two WooCommerce product-mapping Extra Fields; no quotation or other business record was created.
- Restricted compare, preflight, apply, schema checks, file verification, ERP health, and anonymous-login boundary all passed; code and database backups were recorded.
- Authenticated desktop and 390px staff-workspace visual acceptance remains pending until a dedicated `backup-erp` staff session is available.
- Production, WordPress, React, n8n, email, proxy, DNS, Compose, documents, and customer/mobile surfaces were not changed.

## 2026-08-06 — M043-C02 professional website-request detail

- Replaced raw and historical double-encoded request/commercial JSON with responsive operational cards while preserving the stored evidence and all existing staff actions and permission boundaries.
- Added structured contact, cargo, route, equipment, price, validity, inclusion, exclusion, warning, and carrier-space sections plus English, Arabic, and Chinese labels.
- Deployed the checksum-pinned 26-file `M043-C02` artifact to canonical `backup-erp` through the manual GitHub Actions workflow; restricted preflight, backups, apply, runtime verification, and cleanup passed without rollback.
- Verified authenticated request `id=14` on desktop and at 390x844 with no raw JSON, escaped-newline leakage, or page-wide horizontal overflow.
- Removed the temporary Termius helper snippet after deployment; production and n8n were not accessed or changed.

## 2026-08-06 — M043-C01 Website Requests dashboard navigation

- Added `Website Requests / طلبات الموقع` to the shared Customer Center admin navigation and to the Admin Dashboard Quick Actions.
- Kept the existing staff permission boundary and request queue unchanged; no new Dolibarr top-level module icon, API, schema, portal, mobile, accounting, or business-record behavior was added.
- Deployed the checksum-pinned 17-file `M043-C01` artifact to canonical `backup-erp` staging after code and database backups; restricted preflight, apply, and verify all passed.
- Verified the authenticated dashboard, queue, and request `id=14` on desktop, and verified the responsive navigation at 390×844.
- Replaced the obsolete M004 `backup-erp` workflow with a manually confirmed, M043-C01-pinned staging workflow backed by the exact GitHub prerelease artifact and environment-scoped secrets.
- Production and n8n were not accessed or changed.

## 2026-08-05 — M043 public website intake and FCL booking request

- Added restricted public-site permissions and protected REST actions for exact published FCL rate search and anonymous website intake.
- Added encrypted, authenticated, ten-minute rate tokens; submit-time repricing; a customer-safe public DTO; and immutable commercial snapshots without cost, margin, source or database identifiers.
- Added an entity-scoped public-request queue with strict idempotency, separated PII, staff classification, same-entity customer linking, manual Operations promotion and 180-day restart-safe anonymization.
- Changed the Moaz architecture to `React → same-origin Node → Dolibarr`; WordPress/WooCommerce remains the catalog and packing-plan source but no longer creates the React B2B operational order.
- Added the public `/shipping-rates` page for Arabic, English and French, supporting 20GP, 40GP and 40HQ and clearly describing submission as a booking request rather than carrier confirmation.
- Local source, Node, UI-contract, build, database and synthetic browser acceptance passed. Runtime installation of the source branch and authenticated Dolibarr staff-page browser acceptance remain pending.
- Local only; no n8n, staging, production, VPS, deployment, external message, accounting entry or validated commercial document was created.

## 2026-07-24 — M037-C04 MariaDB-safe shipping classification

- Removed shipping lifecycle classification from MariaDB JSON expressions and now decodes the allowlisted service payload in PHP.
- Preserved exact entity, customer, booking-ownership, and shipment-ownership filters while treating malformed legacy JSON as an active order instead of zeroing the complete result.
- Kept bucket-specific searches complete by applying their limit after PHP classification; unfiltered searches remain database-limited.
- Verified the correction transactionally against the real local MariaDB runtime with the expected `7 / 4 / 1 / 1 / 1` lifecycle result and clean rollback.
- Built the allowlisted correction artifact `m037-full-806df74.tar.gz` with SHA-256 `c0e5741f45018302983d6449a2e2392571162fbcd636e1484148f4dc932906e5` and pinned both restricted release helpers locally.
- Local correction only; no `backup-erp` or production access occurred. Separate exact deployment approval is required before another live attempt.

## 2026-07-24 — M037-C03 production-data-safe shipping runtime

- Made shipping lifecycle JSON reads tolerate malformed or empty legacy values by validating the stored JSON before extraction and falling back to an empty object.
- Extended the transactional runtime acceptance with a malformed legacy record while preserving exact lifecycle totals, entity/customer isolation, and clean rollback.
- Verified the correction against the real local MariaDB runtime with `legacy_json=SAFE` and the expected `7 / 4 / 1 / 1 / 1` lifecycle result.
- Built the allowlisted 71-file correction artifact `m037-full-04bf778.tar.gz` with SHA-256 `6a55707932d683e33cd2ad96c085be7fefa432ee17a34a4d7fed3d38366f4f99`, and pinned restricted backup and production helpers without installing or running them.
- Local correction and Git release preparation only; no `backup-erp` or production access occurred. Separate exact deployment approval is required.

## 2026-07-24 — M037-C02 proc-open-free authenticated route harness

- Replaced the staging-only authenticated-route test's disabled `proc_open()` launcher with two fixed helper-controlled `--issue` and `--render` invocations.
- Added an opaque one-time context handoff in a `www-data`-owned `0700` directory using an exclusively created `0600` file that is deleted before route rendering.
- Kept login, entity, route output, SQL, credentials, and customer data out of argv, environment, and helper output; only fixed result classes and aggregate hashes may be emitted.
- Preserved the exact 52-file M037 application runtime payload, six-command staging sudo surface, customer/entity checks, and external-customer admin denial.
- Authorized scope ends after canonical `backup-erp` compare/preflight. No `apply` or production action is included.

## 2026-07-24 — M037-C01 authenticated admin route diagnostic

- Added safe parent-bootstrap and child-render failure classification to the M037 authenticated route test.
- Added a read-only current-runtime `admin_dashboard` gate to canonical `backup-erp` preflight and before any future apply.
- Restricted diagnostic output to fixed reason codes, aggregate counts, exit status, output byte count, and SHA-256; raw HTML, usernames, SQL, credentials, and customer data remain hidden.
- Kept the existing fixed staging sudo command surface and made no application, database, permission, module, container, port, proxy, document, customer-portal, or mobile change.
- M037 remains `CORRECTION_REQUIRED`; M037-C01 authorizes preflight diagnosis only and does not authorize another apply or any production action.
- Rebuilt the frozen 71-file artifact as metadata-free `ustar` after Linux rejected macOS provenance xattrs, and made the helper fail explicitly on any tar metadata warning.
- Allowed `www-data` to read only the temporary authenticated-route test inside the root-created candidate and added safe PHP/permission failure classes; no application runtime permission changed.

## 2026-07-23 — M036 Shipping Orders admin integration

- Added an internal `Shipping Orders / طلبات الشحن` register backed by existing Operations booking rows, with entity/customer scoping, accepted-price evidence, carrier SO/booking state, linked shipment, and explicit lifecycle filters.
- Added the same Total Current, Active, Confirmed/SO Received, Closed, and Cancelled counts to the main admin dashboard and added a permission-gated launcher in Shipping Center.
- Added an internal selected-customer Shipping Orders card and recent list in Customer Center without changing the external customer portal contract.
- Kept shipping bookings separate from native Sales Orders and made no accounting, schema, permission-grant, or module-state change.
- Verified source contracts, transactional runtime buckets and isolation, filtered KPI/list reconciliation, external-user denial, dashboard customization save/reload/reset, responsive layouts from 1440px through 320px, and real Chrome 200% zoom locally.
- Used deterministic local browser fixtures for all lifecycle and ownership-warning states, then removed them and verified zero remaining fixture rows.
- Local only; no Git push, `backup-erp`, staging, or production deployment performed.
- Corrected the sparse local runtime package after acceptance by syncing only missing, already-versioned Customer Center and Logistics files without deletion or overwrite. The top Customer Center entry now redirects to `board.php`, and Documents, Reports, SOP Library, Operations, Financial Center, and New Shipment resolve through `/custom/...` and render instead of returning 404.

## 2026-07-22 — M034 shipping price publication-state correction

- Made exact duplicate workbook uploads explain whether the reused batch is a draft, the current published list, or published history instead of claiming a new draft was prepared.
- Added authoritative stored, published, current, and customer-search price counts to import review.
- Replaced state-changing GET publication links with CSRF-protected POST actions.
- Added a guarded repair action for the latest published batch only when it has accepted prices but no current rates.
- Local only; no Git push, `backup-erp`, or production deployment performed.

## 2026-07-22 — M033 Shipping Center and Customer Center

- Consolidated the three internal shipping navigation entries into one bilingual Shipping Center with direct launch cards for tracking, shipment management, and shipping prices.
- Renamed the internal customer-preview destination to Customer Center and added an entity-scoped all-customer operational overview when no customer is selected.
- Added customer, active-order, active-shipment, attention-shipment, and per-currency finance summaries without mixing currencies.
- Preserved the selected-customer view and the external customer portal contract unchanged.
- Verified the affected admin pages on desktop and at 390px, with no horizontal overflow or PHP warnings.
- Local only; no Git push, `backup-erp`, or production deployment performed.

## 2026-07-22 — M032 admin Reports scope correction

- Replaced the internal Reports page's false access-denied response when no customer was selected with a customer selector and a safe guidance state.
- Kept external customer access locked to the authenticated third party and prevented report queries until a valid customer scope exists.
- Local only; no database, permission, Git, staging, or production change.

## 2026-07-22 — M032 admin and customer UX correction

- Clarified admin navigation for customer preview, documents, reports, SOP, and the Financial Center.
- Replaced the implicit `LIVE` claim with a configured environment label and a separate generated timestamp.
- Limited New Shipment prominence to shipping-related pages and improved shared admin touch targets and responsive navigation.
- Clarified Financial Account and Profile access in the customer portal.
- Rebuilt shipment filters and result containment with fluid responsive layouts verified at desktop, 390px, and 430px.
- Preserved customer/entity ownership and all accounting behavior; mobile implementation remains pending.
- Local only; no Git push or ERP deployment performed. Authenticated admin visual acceptance remains pending.

## 2026-07-22 — Cross-surface parity instruction

- Added a permanent requirement to assess related changes across Admin ERP, Customer Portal, and Mobile App.
- Required real-data and design verification, including desktop and 390px phone checks for affected web pages.
- Added the canonical mobile parity log at `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md`.
- Recorded the current M030/M031 mobile parity backlog so future mobile work can align terminology, documents, reports, finance, project milestones, and design without exposing admin-only data.
- Documentation only; no application code, Git push, or ERP deployment performed.

## 2026-07-22 — M031 phone document-card correction

- Corrected the external Documents page where a fixed-width download action squeezed long filenames into a one-character-wide column on phones.
- Kept the file icon and information in a readable row and moved document actions to a full-width row below on narrow screens.
- Updated the mobile parity notes so the future native Documents screen follows the same readable filename and touch-target behavior.
- Local only; no Git push or ERP deployment performed.

## 2026-07-22 — M031 fluid documents layout correction

- Replaced the customer Documents table's fixed multi-column behavior with a fluid shipment-card grid driven by available container width.
- Shipment metadata and file groups now reflow automatically across desktop, tablet, and phone layouts; long filenames keep a readable content width and actions move below file information.
- Kept the administration table unchanged and made no Dolibarr core changes.
- Local only; no Git push or ERP deployment performed.

## 2026-07-21 — M031 local correction

- Made the dashboard Documents card total the customer's published commercial documents and shipment publications.
- Added customer-visible commercial documents to the main Documents page while preserving strict entity and customer scope.
- Replaced the compressed commercial-document text block with a responsive, readable document-card grid.
- Simplified each document card to the requested name, type, and related-shipment fields only.
- Applied the same compact card presentation and generic Documents title to the external customer portal while keeping management controls admin-only.
- Fixed customer Documents-page warnings when shipment records provide `rowid` instead of `id`.
- Temporarily hid the legacy Commercial Documents block from the external customer portal without deleting its data or admin view.
- Improved the external Documents search, file cards, and action controls for phone-width layouts.
- Local only; no Git push or ERP deployment performed.

## 2026-07-21 — M030 local correction

- Generalized customer-facing invoice and report terminology.
- Routed the customer Documents card to scoped shipment publications.
- Generalized project invoice, document, and completion milestones.
- Hid the legacy Export Documents feature from navigation without deleting it.
- Made Financial Center the main Finance landing and promoted the allocation action inside it.
- Restored the shared admin layout on Reports list/detail and every linked Shipping SOP management page.
- Local only; no Git push or ERP deployment performed.
# 2026-08-14 — M056 shipment status email notification

- Added an explicit, permission-gated shipment-status email option to the existing logistics edit form.
- Email is sent only after a committed, customer-visible status change; the recipient is resolved server-side from the entity-scoped customer and content follows the customer's default Arabic, Chinese, or English language.
- Reused Dolibarr SMTP and Agenda history without core edits or schema changes. Email failure never rolls back shipment status.
- Local implementation only. Staging and production were not changed.
