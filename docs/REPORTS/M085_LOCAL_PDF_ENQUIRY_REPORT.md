# Customer PDF enquiry capture — M085

Status: local implementation verified; ERP runtime baseline and coordinated release pending.

## Behavior
Customers browse and calculate freely. Download focuses the contact form; name, international WhatsApp, destination and privacy consent are required, email/company optional. Submission recalculates the catalogue snapshot on the server, prepares a Dolibarr enquiry/prospect, renders the existing approved pages, and archives a raster-only PDF before requesting its download. The customer and ERP receive the same bytes. No confirmed order, invoice, account or outgoing email is created.

An in-session receipt survives reload and failed image/upload/download attempts. Identical retries reuse their request and immutable document; quantity/contact/container/language changes get a fresh reference. Activity means download_requested, not proof of saving/opening the file. File capabilities expire after seven days, grant no other record access, and are transmitted only in POST bodies. Private ERP documents remain subject to staff/entity authorization.

## Architecture
React → same-origin Node → restricted Dolibarr intake. Existing CMS signed plan validation is reused. Browser sends only JPEG page renders; Node creates the PDF, so arbitrary PDF scripts/attachments are not accepted. The authoritative server product snapshot is stored separately from the customer-rendered copy. 6 MB file / 60-page caps, same-origin checks, rate limits, no-store responses and bounded payloads apply. No browser ERP credential, supplier/cost data, public attachment URL or database migration.

## Verified locally, 2026-09-19
- Lint, 79 server tests, 75 UI/unit tests and production build passed.
- Eight browser cases passed: AR/EN/FR desktop/phone; nine-page large order with long names/incomplete packing; failed-image retry and stale quantity guard.
- Native Dolibarr 23.0.3 / PHP 8.4 isolated database: real API submission, native prospect, persisted enquiry and protected attachment. Downloaded PDF equals staff-downloaded ERP copy; reload persists.
- Real browser error cases: image failure, archive succeeded but download response failed, retry, reload, changed-quantity new reference.
- Native negative tests: wrong capability, cross entity, traversal, anonymized request and pre-archive download denied; immutable archive resubmission retains original bytes. Customer and no-access staff UI cannot read the attachment.
- No live enquiry or production business data was written. CMS calculation/catalog responses were fixtures for native local end-to-end tests; public product photos were real.

## Release dependency / boundary
Do not merge/deploy this caller before the ERP receiver is ready. Production ERP is cumulatively deployed and its checked-out main branch is not a complete runtime manifest. An unmerged M068/M074 receiver variant enforces required email and reviewed contact qualification. Its current presence must be determined before release; if present, the candidate needs a bounded compatibility correction and repeat native acceptance. Do not overwrite its intake/lifecycle/UI with older main files. A reviewed read-only baseline script is included in the ERP branch. Current restricted SSH sudo surface contains no M085 release helper.

Rollback website first, then only the accepted scoped ERP changes. Preserve all new enquiry/prospect/document records. Customer Center/mobile publication contracts, product prices, variants and packing remain unchanged.
