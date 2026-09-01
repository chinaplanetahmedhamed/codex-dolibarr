# M061 Local Completion Report — Customer portal trust and operational consistency

## Result

- Mission: M061
- Baseline: `38493b775889f77015db174fb3e5c79d2b493669` (tree-identical to fetched `origin/main` `95402e7f22665c9efd61d648f1e545094aaf47c9`)
- Branch/worktree: `codex/m061-customer-portal-trust` at `/private/tmp/m061-customer-portal-trust`
- Verdict: `LOCAL_IMPLEMENTATION_COMPLETE_RUNTIME_ACCEPTANCE_PENDING`
- Staging/production: not accessed or changed
- Git publication/outbound messages: none

## Delivered behavior

- Web and Mobile API project status, percentage, current/next stage and timeline now share the existing eight evidence stages. Customer project lists no longer load or expose the CP Kanban modal/progress; staff behavior remains available.
- Customer completion requires all operational evidence and Dolibarr project closure. No-shipment projects show an explicit empty state and do not infer links.
- Shipment status uses the canonical business order. Valid ATD/ATA facts advance status without regression; impossible or future actual dates fail closed; container identity normalizes a count below one to one.
- Interactive normalization stays inside the existing shipment transaction. Status and automatic count corrections are audited; automatic count events are internal. Customer email remains explicit, permission-gated and post-commit.
- Invoice due dates and overdue state use standard Dolibarr invoice data. A validated invoice with no normal lines shows a safe customer message and a staff data-quality warning without inventing lines or changing totals/payments.
- Customer portal dates use unambiguous `YYYY-MM-DD`; Mobile API structure and opaque IDs remain compatible while project semantics are documented as M061 operational evidence.

## Repair primitive and release boundary

- Added a source-only M061-R01 repair engine and CLI supporting `dry-run`, `apply`, `verify`, `rollback`, and `status`.
- Candidate data hashes are deterministic; each execution document also has a unique identity and whole-plan SHA-256. Apply and rollback are transactional, drift-sensitive and idempotent; impossible rows are reported but never changed, and rollback retains the apply audit plus adds a private reversal event.
- Every repaired row receives exactly one `customer_visible=0` audit event. The engine has no email-service dependency.
- The checksum-pinned server wrapper that adds `compare` and `preflight`, full backup/rehearsal, request state and environment identity is intentionally deferred until this code has a frozen commit/artifact and authenticated staging acceptance. See `M061_R01_SHIPMENT_REPAIR_RELEASE.md`.

## Verification completed

- Changed-PHP lint: PASS.
- `git diff --check`: PASS.
- M061 source/logic test: PASS.
- Isolated MariaDB repair runtime: PASS for exact dry-run set, ambiguity exclusion, private audit, apply/verify, apply idempotency, rollback, rollback idempotency, stale-plan rejection and transaction rollback on audit failure.
- Regressions PASS: M010, M011, M017 contract/data/route/security/schema, M020 gallery/web gallery, M024, M031, M032, M033, M054 and M056 email/source/update/upload.
- Mobile app: 12 Vitest files / 39 tests PASS; TypeScript typecheck PASS; Expo lint PASS.
- OpenAPI remains 20 routes with unchanged response shape; approved M061 byte hash is `c9521d13696540d3c1827e3fa61eed9c10af758c686a3ba86236f53ff425f1bd`.

## Outstanding acceptance gates

- Authenticated external-customer runtime acceptance in Arabic and English at 390px and 1280px is not yet run against this worktree.
- Customer A/B isolation, forged `socid/id/token`, direct-download denial and the environment-dependent M017 HTTP download fixture remain staging runtime gates.
- No staging helper was installed and no staging database/business row was changed.
- M061-R01 must freeze a commit/artifact and independently reviewed wrappers before compare/preflight. Staging apply and production apply each require a separate explicit approval; production acceptance must verify corrected real records in a real customer account.
# M061 Feature Completion Report — Financial, invoice, document and email improvements

The trust and operational-consistency evidence above remains the active `backup-erp` baseline. The feature report below records the locally accepted portal additions that M061-R02 will layer on that baseline.

# M061 — Local Runtime Acceptance Report

## Control

- Mission: M061 — Customer Center financial clarity, unified documents and section emails
- Approved scope: local implementation only
- Baseline: `95402e7f22665c9efd61d648f1e545094aaf47c9`
- Branch: `codex/m061-customer-center-financial-documents-email`
- Worktree: isolated clean implementation worktree; the user's existing checkout was preserved
- Status: `M061-C01 LOCAL_QA_PASS — AWAITING_AHMED ACCEPTANCE; MOBILE DEVICE PRESENTATION CHECK PENDING`
- Outbound messages: none
- Staging/production: not accessed and not changed

## Delivered source capability

- Financial statement and mobile account now lead with total validated invoices, direct payment plus applied-credit funding, and outstanding amount in each original currency. Existing wallet, obligations and additional-funding views remain separate.
- Invoice list rows have an explicit bilingual detail action. The scoped detail projection shows dates, verified project/order relation, subtotal, discount, tax, gross, direct payment, applied credit, funded total, remaining amount, service lines and linked payments.
- Customer-visible payment text is opt-in through module-managed payment extra field `cpcc_public_note`. Private/legacy payment notes are never a fallback.
- The Documents Center normalizes explicitly published commercial, project, operations, shipment and registered Dolibarr files. It exposes verified project/shipment context plus project, shipment, source, document-type, date and text filters.
- The additive core-document publication registry stores customer/entity/source scope, a relative path, immutable size/hash and publication state. Download re-resolves ownership and root containment and rechecks the fingerprint. Unpublishing never deletes the source file.
- Authorized staff receive preview-and-confirm email actions in the financial, invoice, project and shipment sections. Recipient and sender are server-owned, the confirmed send is CSRF-protected, and a successful transport is recorded in Agenda.
- Mobile/API parity includes the same financial summary, public payment comments, a scoped invoice-detail endpoint/screen and one linked-documents view containing commercial records and protected published files with project/shipment context.

## Security and compatibility controls

- External customer scope is derived from the authenticated portal/mobile context; invoice, project, document and email lookups retain entity/customer ownership checks.
- Draft invoices, unapproved/current-invalid documents and unregistered core files remain excluded.
- Core publication requires Customer Center write permission; section email requires a dedicated disabled-by-default permission `500206 email_write`; external customers cannot invoke either action. Permission `500205` remains reserved for the local M040 smart-document approval right.
- Browser requests cannot post an arbitrary email address or sender. Preview has no send side effect and confirmed send uses POST plus CSRF.
- No supplier cost, margin, private payment note, arbitrary file path, credential or internal customer selector was added to customer DTOs.

## Local verification completed

- PHP lint: PASS for changed and new PHP files.
- `git diff --check`: PASS.
- M061 financial, document, section-email source and captured-service contracts: PASS.
- Existing finance M029 and multi-currency regressions: PASS.
- Existing M020 shipment gallery, M031 documents and M032 responsive contracts: PASS.
- M055 customer-reply and M056 shipment-email source/service regressions: PASS.
- Mobile API contract/data/route/security plus M028 financial parity: PASS.
- OpenAPI: valid JSON, 21 routes, SHA-256 `e6f1ee60e6485cfe37769f288a3b2a0be246b813e9ed508deab7479f0f9e48b3`.
- Mobile TypeScript: PASS; Expo lint: PASS; Vitest: 12 files and 39 tests PASS.

## Local runtime acceptance — 2026-08-24

- Runtime: Dolibarr 23.0.3 on PHP 8.4.7 with MySQL 5.7.39 at the approved localhost-only endpoint.
- Pre-change safety: full database dump plus Customer Center and Logistics module archives were created in `/private/tmp/m061-runtime-backup.lwWuoJJS`. The database dump is 11,940,458 bytes with SHA-256 `c1224527c5e69b528b9cf177d0d924d5d3ec535a3204cec97748142c110e3a1d`; module archive SHA-256 values are `fb315ab68eee8d64324b4d4598c833c1097591278dbeadb14e50818ef3c8d145` and `cd227f2f0aef41c8fee2080b13a08934a03c8dca254a4b9918ee29190e6e0dd3`.
- Restore rehearsal: PASS in a separate temporary MySQL 5.7.39 server with networking disabled. All 439 pre-change tables restored, and the external-user, validated-invoice, payment, project and shipment counts matched the source database exactly. The temporary server was stopped after verification.
- Runtime drift was preserved: local M040 smart-document permission `500205 approve`, schema helpers and menu remain; M061 uses separate disabled-by-default permission `500206 email_write`. Only the M061 shipment-summary link was merged into the locally older Logistics card instead of replacing the file with unrelated M056 code.
- Runtime schema: publication table present; `llx_paiement_extrafields.cpcc_public_note` present; exact `paiement` extra-field definition present; rights `500205` and `500206` present without collision. The permission migration was rerun and returned `EXACT_EXISTING`.
- Business records: validated invoice, payment, project and shipment counts were unchanged after activation. The only table-count change was the additive M061 publication table, from 439 to 440.
- A runtime-discovered source defect was corrected before acceptance: the payment extra field originally targeted `payment`, which would create a nonexistent `llx_payment_extrafields` table. The accepted source/runtime now targets Dolibarr's exact `paiement` table element. The email permission was also moved from the locally reserved `500205` to `500206`.
- Runtime payment-note projection: PASS inside a rollback-only transaction. An explicit public marker appeared for its owning customer, a private payment marker never appeared, a foreign customer never received the public marker, and both original database values were restored after rollback.
- Authenticated browser acceptance: PASS for internal staff and an external customer on desktop, `390×844` and `360×844`. Financial total/funded/outstanding cards, invoice list/detail/service lines/linked payments, project/shipment document context, filters and staff email previews rendered without page-wide horizontal overflow.
- Customer isolation: PASS. A forged query-string customer ID was ignored in favor of the authenticated customer scope; foreign company content did not appear. External customers saw no statement/invoice email send actions and no core-document publication control.
- Staff email entry points: PASS for financial statement, invoice, project and shipment. The detailed financial preview rendered from the server-owned customer record. The send button was deliberately not submitted. Captured service tests proved preview has no side effect, one confirmed fake send produces one mail call and Agenda record, foreign scope fails closed, and SMTP failure stops before Agenda.
- No real email, WhatsApp message, staging deployment or production action occurred.

## Known local evidence limits

- Ahmed rejected the financial email presentation during local acceptance because the preview exposed visible `\\n` tokens and an unstructured transaction wall. The narrowly approved M061-C01 correction now passes local source, service, runtime, desktop, 390px, and 360px checks and awaits Ahmed's acceptance; no email was sent.

- The exact pre-M061 baseline test `test_m017_mobile_api_data.php` already fails on this long-lived local dataset for synthetic Customer A document counts and synthetic Customer C transaction count. M061 intentionally adds approved commercial documents to the unified list, so the same legacy exact-count test also reports the new B/C commercial rows. The M061 source contracts, scoped database integration and authenticated foreign-customer checks pass; no legacy fixture data was reset or rewritten merely to force an old exact count.
- A real mobile phone/simulator presentation check is not available on this Mac: `simctl` is unavailable and neither `adb` nor an Android emulator is installed. Mobile unit, repository, API contract, TypeScript and Expo lint checks pass, but the Mission's real-device presentation criterion remains open.
- The Customer Center is bilingual Arabic/English and its accepted portal content is Arabic-first RTL. A separate English-first LTR presentation mode was not introduced or independently accepted by M061.

## Remaining gates

- Run the mobile app on a real phone or installed simulator and record the financial, invoice-detail and linked-document screens in both orientation/language directions required by the Mission.
- Refresh or isolate the M017 synthetic fixture dataset before treating the old exact-count integration suite as green; do not rewrite ordinary local business records for this purpose.
- No Git push, pull request, staging/backup-ERP deployment or production deployment is included. Each remains a separate approval gate.

## Rollback concept

- Before any runtime activation, create full code and database backups and verify restore readiness.
- Code rollback restores the pre-M061 module/app files. The additive table and payment extra field may remain inert; do not delete customer, publication or Agenda evidence as an ordinary rollback step.
