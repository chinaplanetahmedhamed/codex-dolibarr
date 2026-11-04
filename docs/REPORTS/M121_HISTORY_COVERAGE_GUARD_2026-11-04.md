# M121 v5 — suppress unsupported historical roll-ups

Recorded 2026-11-04T03:07:29.056557+00:00. Codex is the sole writer in the existing integration worktree; parent Work coordinates acceptance, shared tracker/manual/handoff and release. Frozen v4 commitd8a49cf31ccc8956eff37280858551d6cb229a03 and every prior candidate remain preserved. The later Documents saved-draft website package stays separate; no website changes are included here.

**This increment prevents false historical presentation. It does not reconstruct reliable historical statements or complete that requirement.** No deployment, remote/production access, financial posting, database/schema/config/module/grant change or new ledger occurred.

## Reproduced problem and bounded correction

Financial Center's selected-date roll-up combined payments through the cutoff with current invoice status/paye and current credit links. A fully paid invoice today disappears from a prior date even if it was outstanding then; a later partial payment can leave a different earlier-looking amount; credit creation date does not prove application/unapplication date. Current invoice totals/links/statuses and mutable custom records cannot establish complete past lifecycle, opening/closing or zero balances. Existing lower-level cutoff functions remain current-source diagnostics; their selected-date filtering is not a provenance certificate.

The existing Customer Center financial library now exposes a pure reporting-period classification with one captured application-local calendar day. Current mode remains explicit for absent/empty/today. Strict valid past, future and malformed dates cannot use the current roll-up. Historical totals remain null and completeness false; no user input or complete=true flag can certify them. Calendar validation rejects rollover/leap errors, year bounds, whitespace/LF/CRLF, arrays/nonstrings and HTML; invalid internal clock input refuses.

Financial Center classifies before financial schema readiness, customer enumeration, summary/unpaid/overdue/managed reads and FX valuation. Unsupported modes render localized unavailable/reason/current-view navigation with no monetary/count KPIs, tables, no-customers fallback or zero placeholders. Current calculations, native rights, currency separation and ordering are retained. Labels live in owning EN/AR files. No arithmetic or accounting rule changed; double-entry impact is none, native records remain authoritative, and original-currency treatment/customer privacy are unchanged.

Native review exposed a second concrete input issue: GETPOST(asof,none) still restores saved values/substitutes placeholders. An actual unmodified native GETPOST regression reproduced malformed raw GET becoming today's valid date via saved-search restoration. The page now uses GETPOST(asof,none,1,null,null,1,1): GET-only, no substitutions/defaults/search restoration. This is local native input behavior evidence, not authenticated session acceptance.

## Actual tests and evidence

| Steps / role / environment | Expected and observed | Failure, persistence and permission evidence |
|---|---|---|
| Pure date/reporting coverage contract |38 assertions PASS; empty/today current, strict past/future/invalid unavailable, historical totals null/completefalse |No DB dependency or guessed past amounts; captured-day rollover/leap tests |
| Actual unmodified Dolibarr24 GETPOST/sanitizeVal and actual page retrieval expression, synthetic GET/POST/session/object defaults |20 assertions across11 cases PASS after native RED reproduction |Malformed/saved/past/placeholder/POST/default/array requests cannot normalize into today's current view; no DB/bootstrap/server |
| Actual page and financial SQL on fresh synthetic SQLite, bootstrap/native shell/formatting seams |16 render/access scenarios PASS, including10 unsupported date scenarios |Unsupported pages execute0 schema/customer/financial queries and0 managed reads; no KPI/table/empty portfolio rendering; later-paid-full, later-partial and earlier-created ambiguous credit shapes remain unavailable |
| Current page regressions |Mixed USD/RMB/EGP units, stable funded/name/tie ordering, reversal/empty behavior and AR/EN escaping remain passing |Existing external/no-right refusals preserved; SELECT-only adapter rejects writes |
| Existing generated date-cutoff SQL |110 checks PASS |Diagnostic DATE/DATETIME/payment/custom charge scope behavior retained; this is explicitly not full historical acceptance |
| Financial connection retirement |10 preheader AR/EN cases PASS |Retired financial handles produce503/Unknown before later query/header; no regression |
| Existing native finance journey after financial-library addition |140 journey checks,177 actor/statement checks,46 native document checks and55 native finance cases PASS |Fresh socket-only MySQL5.7.39/PHP8.3.21/unmodified Dolibarr24.0.1; synthetic identities/rights, HTTP provenance seams; owned server exit0 and socket/PID absence verified; no target MariaDB claim |

Counts overlap and must not be added into a unique combined total. Final29-command focused regression receipt is final-regression.json. Native evidence is ../local-assembly-2026-11-03/native-v5-history-guard/. Pure/native-input/renderer RED receipts remain preserved. Read-only reviewer found no further concrete issue in the four-runtime-file delta and did not execute tests.

## Visible local source result

The existing owned synthetic loopback preview was refreshed on its same127.0.0.1:18780 binding after verifying PID20142/UID501/exact script/cwd; only that owned preview was stopped. The fixed added route `/financial-history.html` serves generated read-only actual page snapshots. CSP/sandbox disable form/script execution; this is not a backend/server login or a historical ledger. No unknown listener, remote service or access permission was changed.

CUA observed EN/AR warning states at actual390x844 with outer scrollWidth390 and desktop1728x919/scrollWidth1728. Viewport override was reset and final dimensions1728x919 recorded. Screenshots and ui-observations.json are in outputs/M121/history-coverage-2026-11-04/. Native top bar/base theme/session are intentionally absent; module-only preview CSS has different font/link styling and is not native visual parity acceptance. Native phone/desktop/keyboard/touch/CSRF navigation remain pending. The original v1 screenshot was restored byte-for-byte after local capture.

## Remaining historical work and release gate

Complete historical statements still require retained invoice versions/lifecycle, dated credit application/unapplication, native payment deletion/date-edit coverage, historical source/customer/project identity and original-currency proof, and a trustworthy opening/coverage interval. Current statuses, missing rows, credit creation date, current module-enabled flags or unanchored audit logs cannot provide that proof. Do not turn current-source observations into past totals, combine independent ledgers or create inferred application dates. The earlier bounded native/manual history-reader contract remains a source-backed next-step design, not an implemented adapter or runtime attestation.

The new classification currently protects Financial Center's selected-date roll-up. Existing lower-level asOf parameters and current statement/project/mobile paths are retained for compatibility and must not be presented elsewhere as verified historical balances. Customer Center balance/current invoice-credit history is not complete historical receivables.

Backup remains pending/not attempted: fresh privileged isolation, current scoped backup, actual backup-ERP MariaDB restore proof, supported pinned M121 apply capability and authenticated target acceptance are still unavailable. Prior Docker denial is not bypassed. Existing R13 helper cannot apply this source candidate. Source-only rollback restores this bounded code/UI delta while retaining all records, audit, schema and private document bytes; never restore an old financial database. Shared progress/manual updates remain parent-owned; HISTORY_GUARD_WORKFLOW.md and progress.csv provide sanitized function/role/environment/expected/observed/failure evidence.
