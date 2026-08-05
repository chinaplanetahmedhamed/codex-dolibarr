# ChinaPlanetOS Change Log

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
