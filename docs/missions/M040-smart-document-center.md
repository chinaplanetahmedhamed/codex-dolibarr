# M040 — China Planet Smart Document Center

## Status
Approved to start. Development and testing only. No production deployment is authorized.

## Objective
Build a Dolibarr-native multilingual trade-document center that reuses customer, supplier, product, order, project, shipment, payment, and document data without duplicate entry.

## Phase 1 documents
- Quotation
- Proforma Invoice
- Commercial Invoice
- Packing List
- Sales Contract

## Mandatory discovery before Dolibarr integration
Verify actual custom-module names, descriptors, hooks, routes, permissions, tables, document storage, PDF libraries, portal visibility rules, and any existing overlapping work. Record findings in `M040-discovery-report.md`.

## Core requirements
- Extend the existing system; do not create a separate production application.
- Reuse Dolibarr third parties, contacts, products/services, proposals, orders, invoices, projects, currencies, taxes, and document storage.
- Support Arabic RTL, English LTR, and Simplified Chinese LTR.
- Keep canonical commercial data separate from translated display text.
- Enter shared data once and reuse it across document types.
- Generated documents must have immutable snapshots and version history.
- Regeneration must never overwrite an older version.
- Only approved/shared versions may appear in the customer portal.
- External users must never see internal cost, margin, supplier data, hidden files, or drafts.
- Enforce CSRF, entity isolation, object-level permissions, safe HTML escaping, and upload validation.

## First vertical slice
From one verified Dolibarr commercial object:
1. Select document type, language, brand profile, currency, and product images.
2. Review presentation-only fields without mutating the accounting object.
3. Preview HTML/PDF.
4. Generate a versioned PDF with snapshot, checksum, creator, source link, and status.
5. Approve and optionally expose it in the customer portal.

## Acceptance criteria
- All five document types can be generated from at least one supported source object.
- Arabic, English, and Chinese render correctly.
- Images do not stretch or break.
- Totals match source data and added charges are explicit.
- Versioning, permissions, entity isolation, portal visibility, and snapshot stability are tested.
- Existing proposal, order, invoice, shipment, and portal workflows do not regress.

## Non-goals for the first slice
AI translation APIs, OCR, e-signature, automatic WhatsApp/email sending, replacing Dolibarr accounting invoices, or production deployment.
