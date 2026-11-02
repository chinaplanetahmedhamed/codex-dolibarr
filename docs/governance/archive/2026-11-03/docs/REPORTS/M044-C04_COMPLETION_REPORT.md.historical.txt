# M044-C04 — Free automatic shipping-note translation completion report

## Report control

- **Mission:** M044-C04 — Free automatic shipping-note translation
- **Mission status received:** APPROVED
- **Report status:** DEVELOPER_REPORTED
- **Implementation owner:** Agent 007
- **Branch:** `codex/m044-c04-free-auto-translation`
- **Base commit:** `a633c00f835acfbe8e73121d19b49997cd94da88`
- **Result commit:** `d563edc1dc4dcd9d7242ecda8a5a77dd178de3fe`
- **Environment used:** local development plus one synthetic live-provider probe
- **Production touched:** No

## Business outcome

Shipping-agent Chinese notes now use a fixed, keyless MyMemory fallback when no
private provider is configured. Valid English output is accepted automatically,
while authorized staff can still edit the English text when correction is
needed. Draft batches retry pending notes automatically through bounded,
CSRF-protected POST requests and stop safely when the free service cannot make
progress. Publication remains blocked until every note has valid English text.

## Scope reconciliation

- **Completed:** keyless free provider, minimal external payload, automatic
  acceptance, bounded pending-note retry, no-progress stop, optional staff edit,
  corrected publication/UI wording, tests, mission, and parity documentation.
- **Not completed:** `backup-erp` deployment and Batch 21 runtime processing;
  those require an exact release approval and checksum-pinned staging artifact.
- **Scope deviations:** None.

## Files changed

| Action | File | Purpose |
|---|---|---|
| Modified | `custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php` | Add fixed MyMemory fallback, GET transport, response/quota handling, and fail-closed limits. |
| Modified | `custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php` | Add entity-scoped, permission-gated, bounded automatic retry and atomic persistence. |
| Modified | `custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php` | Replace stale manual-approval publication error with automatic-retry guidance. |
| Modified | `custom/chinaplanetshippingprices/admin/batch.php` | Add CSRF-protected automatic retry loop, safe pause, retry control, and optional-edit wording. |
| Modified | `custom/chinaplanetshippingprices/admin/import.php` | Explain automatic free translation without per-row approval. |
| Modified | `custom/chinaplanetshippingprices/admin/setup.php` | Display the active private provider or free MyMemory fallback and its boundary. |
| Modified | `deploy/tests/test_m041_shipping_note_translation.php` | Preserve M041 coverage and test the keyless fallback payload and byte bound. |
| Modified | `deploy/tests/test_m041_shipping_note_translation_source.php` | Update publication/retry source contracts. |
| Modified | `deploy/tests/test_m044_c03_note_review_ui.php` | Preserve the corrected UI contract with the new automatic POST action. |
| Added | `deploy/tests/test_m044_c04_free_auto_translation.php` | Test provider/source contracts and success/failure DB retry flows. |
| Added | `deploy/tests/test_m044_c04_free_provider.php` | Redacted live MyMemory acceptance probe. |
| Added | `docs/MISSIONS/M044-C04_FREE_AUTOMATIC_NOTE_TRANSLATION.md` | Record Ahmed-approved correction and data-transfer boundary. |
| Added | `docs/REPORTS/M044-C04_COMPLETION_REPORT.md` | Record implementation and evidence. |
| Modified | `mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md` | Record Admin impact and Portal/Mobile non-applicability. |

## Database, accounting, configuration, and permissions

- **Database/schema/data:** no schema or local/staging business-data change.
  Runtime retry will update only translation fields on pending rows in one draft
  batch after a separately approved deployment.
- **Accounting/multicurrency:** None.
- **Configuration/module state:** None. No API key is required. Existing private
  provider environment variables remain compatible; the free fallback is used
  only when endpoint and model are both absent.
- **Permissions/customer visibility:** automatic retry requires internal Import
  plus View Cost rights (or administrator). Customer/public projections remain
  allowlisted and expose only `public_notes` after status `approved`.

## External data boundary

- Fixed destination: `https://api.mymemory.translated.net/get`.
- Sent fields: normalized carrier/source note as `q`, fixed `langpair=zh-CN|en`,
  and `mt=1`.
- Not sent: prices, costs, customers, suppliers, workbook files, source row,
  entity, users, credentials, tokens, or other ERP metadata.
- Ahmed explicitly approved this temporary external note-text transfer on
  2026-08-09.

## Tests and evidence

| Test | Expected | Actual | Result |
|---|---|---|---|
| PHP lint: six changed runtime PHP files | No syntax errors | No syntax errors | PASS |
| `test_m041_shipping_note_translation.php` | Existing bounds/SSRF/circuit plus free fallback | All contracts passed | PASS |
| `test_m041_shipping_note_translation_source.php` | Privacy, permissions, CSRF, projection | Passed | PASS |
| `test_m044_c03_note_review_ui.php` | Existing review UI remains complete | Passed | PASS |
| `test_m044_c04_free_auto_translation.php` | Minimal payload, bounded loop, DB success/failure behavior | Passed | PASS |
| `test_m044_c04_free_provider.php` | Live keyless translation; protected tokens; redacted output | `PROVIDER=api.mymemory.translated.net`, `KEY=NONE`, tokens preserved | PASS |
| `test_m035_customer_shipping_booking_source.php` | Customer-safe projection unchanged | Passed | PASS |
| `test_shipping_prices_module.php` | Module structure and privacy boundaries | Passed | PASS |
| `test_shipping_prices_upload_csrf.php` | Upload CSRF regression | Passed | PASS |
| `test_m044_c02_schema_correction.php` | Existing additive schema contract | Passed | PASS |
| `git diff --check` | No whitespace errors | Clean | PASS |
| M035 local DB integration | Requires configured Dolibarr runtime path/database | Local standalone worktree lacked `/var/www/html/master.inc.php`; source equivalent passed | NOT_RUN_ENVIRONMENT |

## Security and customer-isolation review

- **Entity and `fk_soc` enforcement:** automatic mutation filters by current
  entity, batch, and source row; batch and row state are rechecked under locks.
- **Internal cost/private data exposure:** no price/cost/customer payload is
  passed to MyMemory; failed translations set `public_notes=NULL`.
- **Files/external links:** no file is uploaded. HTTPS, TLS verification, no
  redirects, bounded response, DNS resolution, and public-IP checks remain.
- **Security reviewer result:** PASS for local source contracts; staging runtime
  verification remains pending.

## Known risks, blockers, and follow-ups

1. MyMemory has free usage and 500-byte per-query limits. Oversized, quota, or
   unavailable responses remain private and stop the automatic loop safely.
2. Batch 21 still needs approved staging deployment and authenticated runtime
   acceptance; no translation row was changed locally.
3. Desktop and 390px runtime verification must be repeated on `backup-erp` after
   deployment because automatic progress and pause messages are new.

## Deployment boundary

- Local actions performed: source changes, unit/source tests, and one synthetic
  redacted provider probe.
- Staging actions performed: None.
- Production actions: None.
- No batch was published or activated.

## Rollback instructions

Before staging deployment, restore the changed source from the parent of the
result commit. A future staging release must back up every affected runtime file
and provide a checksum-pinned rollback through a new M044-C04 restricted helper.
Do not use the M044-C03 helper because its fixed allowlist does not cover this
correction.

## Review record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping domain | Agent 007 | PASS | 2026-08-09 | Note-only translation; publication remains gated. |
| Security | Agent 007 | PASS | 2026-08-09 | Minimal external payload, SSRF/TLS bounds, failed output private. |
| QA | Agent 007 | PASS_WITH_CONDITIONS | 2026-08-09 | Local tests pass; staging Batch 21 and responsive acceptance pending. |
| Architect | Agent 007 | DEVELOPER_REPORTED | 2026-08-09 | Exact staging release approval still required. |
| Ahmed | Ahmed | APPROVED | 2026-08-09 | Approved free provider and carrier-note transfer. |

## Architect disposition

- **Decision:** QA_REVIEW
- **Reason:** local implementation and provider acceptance pass; staging release
  and Batch 21 verification remain separate gated work.
- **Documentation updated:** Mission, completion report, and Portal parity log.
- **Final mission status:** QA_REVIEW
