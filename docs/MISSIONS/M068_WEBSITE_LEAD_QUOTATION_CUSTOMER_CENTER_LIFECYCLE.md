# M068 — Website Lead, Quotation Request, and Customer Center Lifecycle

## Mission control

- **Mission ID:** M068
- **Type:** IMPLEMENTATION / INTEGRATION / SECURITY / CUSTOMER_PORTAL
- **Status:** APPROVED
- **Architect and implementation owner:** Agent 007
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-30
- **Approved wording:** `APPROVE M068 AS WRITTEN FOR LOCAL DOLIBARR IMPLEMENTATION ONLY. Freeze contract cpo.catalog-order.v2. Use one Agent 007 code-writing owner in a new clean worktree from the then-current origin/main. No staging or production action is approved.`
- **Source baseline:** `057af0f205f819c685869b3ee6aa1aea31ea549e`
- **Branch:** `codex/m068-website-lead-quotation-portal`
- **Target:** local source and local verification only
- **Staging / production:** FORBIDDEN without later separate exact approvals

## Business outcome

Preserve one anonymous multi-product quotation request, qualify it safely into a reviewed Dolibarr Prospect/Lead and contact, complete missing packing and selling-price data without guessing, create one standard draft `Propal`, optionally invite the exact customer to Customer Center using an expiring one-time password-set link, and show only explicitly customer-visible request, reply, proposal, and file data under authenticated `entity` and `fk_soc` scope.

## Frozen architecture and ownership

`React browser -> secure Node -> Dolibarr website-requests`

- React owns guest discovery, one quotation list, and calculation of ready lines only.
- Secure Node re-resolves WordPress/WooCommerce/B2BKing identity, packing, and customer-safe price visibility before calling Dolibarr.
- WordPress/WooCommerce/B2BKing owns the public catalog and public price-visibility policy.
- Dolibarr Operations owns immutable intake, qualification, line review, audit, and standard draft proposal creation.
- Customer Center owns authenticated request/proposal/file visibility and portal activation.
- Native mobile UI is `PENDING`; web phone-width acceptance is in scope. Customer Center French is out of scope; Arabic and English are required.

## Frozen contract

The additive contract is `cpo.catalog-order.v2`, carried as `schema_version: 2` on the existing `catalog_order` request. Every line carries a request-unique `lineId`, WooCommerce product/variation identity, requested cartons, nullable verified packing fields, `packingComplete`, `calculationReady`, exact `missingFields`, packing source, and a customer-safe nested price projection. Unknown values are `null`, never guessed zeroes. Non-public price modes contain no amounts. The plan counts all requested cartons but only ready cartons as calculated and lists excluded lines explicitly.

Legacy requests without `schema_version` remain readable as v1. Identical retries return the original reference; a changed body with the same idempotency key returns `409 idempotency_conflict`. The response retains `request_ref`, `status`, and `duplicate`, with additive `schema_version`.

## Lifecycle

`received -> qualification_review -> match_ambiguous|waiting_customer_information|qualified_lead -> line_review -> waiting_packing_or_price|proposal_draft -> proposal_customer_visible -> proposal_validated/sent -> proposal_accepted -> order_created`

Portal access is parallel after qualification: `not_invited -> invite_pending -> portal_active`, with `email_conflict`, `expired`, and `revoked` stopping states. Ambiguous identity never auto-merges. Intake never creates a validated proposal/order, booking, account, or login.

## Scope and controls

1. Additive schema for lifecycle fields, working lines, append-only events, customer publications, and hashed portal invitations.
2. Unique reviewed email/phone matching inside the active entity; staff must select an exact existing Third Party/contact or explicitly create a Prospect/Lead and primary contact.
3. Working-line edits never rewrite immutable `request_json` or `snapshot_json`.
4. Every proposal line requires an active sale product, verified positive pieces per carton, and an approved Dolibarr/manual per-piece selling price. Standard `Propal` retains VAT, totals, currency, validation, PDF, send, acceptance, and order conversion.
5. Customer visibility is explicit and separate for request, proposal, replies, and files.
6. Invitation secrets are random, stored only as SHA-256 hashes, expire after 24 hours, are single-use, and are revoked on resend. The customer signs in with the normalized unique email through Dolibarr 23's native email-authentication branch; the framework-safe internal login excludes `@` and is not customer-facing. Both login and email conflicts are checked inside the entity before creation. Issue/resend is limited durably to 3 per request/email per hour, and activation POSTs to 5 per token/IP-HMAC per rolling 15-minute window. Passwords are set only through Dolibarr's password API and are never emailed, logged, or retained in the plaintext database field.
7. Every portal query/download enforces active authenticated external user, `entity`, `user->socid`, customer-visible state, safe path containment, MIME/size allowlists, and non-executable storage.
8. Unlinked disqualified/closed intake remains eligible for the existing 180-day anonymization; linked commercial records are not automatically anonymized by M068.

## Out of scope

- React, Node, WordPress, B2BKing, native mobile UI, n8n, accounting posting, automatic proposal validation/email, automatic order/booking/account creation, real customer contact, staging, and production.
- Supplier identity, costs, margins, bank data, private notes, raw internal IDs, credentials, and unapproved documents must never enter customer projections.

## Verification and reviews

- PHP lint for every changed PHP file; M068 contract, lifecycle, invitation, portal isolation, file-path, backward-compatibility, and source-security tests; existing related M043/M045/M055 and portal tests; `git diff --check`.
- Local runtime verification is required when the local database is available. A database connection failure must be reported rather than treated as acceptance.
- Required independent verdicts after evidence freeze: Customer Portal, Security, QA, UI/UX, and Accounting. Reviewers are read-only and may not expand scope.

## Rollback and gate

Code is reverted by exact local commit. Schema changes are additive; rollback disables the M068 routes/rights while retaining audit data, not destructive table drops. The next gate after local completion is a separate Ahmed approval for `backup-erp` staging; Mission approval never authorizes staging or production.

## Approved backup-ERP staging execution — M068-R02

Ahmed later approved one continuous **backup-ERP staging-only** execution: configure Dolibarr mail transport to the existing Mailpit container without sending mail; install a checksum-pinned restricted helper; then run `compare`, `preflight`, `apply`, `verify`, and `status`. The helper is limited to the canonical `erp-staging` containers, volumes, and localhost ports. It requires the exact deployed M062-R04 state, exact runtime and dependency baselines, the existing C01 HMAC configuration, Customer Center configuration, and a validated full database backup before either Mailpit configuration or M068 apply.

The release remains additive and fail-closed. It may create schema objects and rights but must not create Third Parties, contacts, proposals, orders, customer accounts, quotation requests, email messages, or other business records. Rollback restores the pre-deploy code snapshot while retaining additive schema, audit data, and the Mailpit isolation route. Any checksum, topology, baseline, dependency, configuration, backup/restore, schema, runtime, login-boundary, or health mismatch is a stopping gate. Production is not approved.
