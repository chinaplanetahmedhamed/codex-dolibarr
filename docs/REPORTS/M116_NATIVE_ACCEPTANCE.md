# M116 native acceptance — isolated local trial

Result: tested native cases PASS. Implementation commit: `b7a4acd5b6c8a1742c173bb001e7cc0d08d44443`. No runtime application-code fixes were required during this native phase. This is not a production release or full ERP regression certification.

## Explicit approval and target

Ahmed explicitly approved a new dedicated synthetic database/runtime and synthetic native test identities, restricted to this trial. Database: `m116_test_account_stmt_20261026`, on the pre-existing local MySQL service `127.0.0.1:8889`. Runtime: `/Users/mac/Documents/Codex/2026-10-26/task-4/native-m116/htdocs`. Data and sessions are under `native-m116/var`. Tested versions: Dolibarr 24.0.2, PHP 8.3.21, MySQL 5.7.39. Temporary HTTP bound only to `127.0.0.1:8766`; the existing MySQL bind remained loopback. No MySQL grants, new MySQL credentials, shared database writes, shared symlinks, production data, upload, push, merge or deployment.

Before creating the target, its absence and the existing local CREATE capability were verified. A private creation marker identifies this exact database/runtime. Core code was copied without shared custom/conf/document symlinks; custom modules came from the isolated branch. The new config references the existing local connection privately and overrides database/runtime/data paths. No existing database was cloned. Schema came from the core table DDL and source custom schema, with selected public dictionary defaults. M116's guarded installer created its five tables. Existing internal-task migration and default-off right-definition registration were completed inside the trial DB to satisfy normal Board readiness. Synthetic users received only trial-local application rights; one has finance rights and the board-capable denied identity has none. Passwords use Dolibarr's configured native hash API and remain private, outside the source repository.

This is a minimal synthetic native test installation, not a copy or certification of the complete production module/configuration inventory. Initial setup retries resolved SQL loader comment/entity placeholders and required fixture fields. They were confined to the marked new DB; no application security checks were disabled to obtain acceptance.

## Native evidence

| Check | Verified result |
| --- | --- |
| Native ledger/rights script | 10 assertions passed: real User rights, draft exclusion, same-request replay, exact cash/card allocation, no-finance rejection, posted immutability, reallocation, reversal and wrong-customer project rejection |
| MySQL competing posts | Two independent native PHP connections: one success and one version conflict |
| MySQL duplicate saves | Two independent processes returned the same canonical result |
| Explicit customer row lock | Second native save remained running while the first session held the row lock; completed after release |
| Real native browser session | Synthetic user authenticated through Dolibarr login |
| Real native CSRF | Invalid token returned 403 and statement entry count stayed unchanged |
| Contextual UI | Card-scoped CNY draft/save/post; 70 CNY at 0.14 became 9.80 USD |
| Exports | Authenticated XLSX downloaded; independent ZIP/XML parsing and 9.80 scoped value check passed; native print/PDF visually inspected |
| Mobile | Authenticated 390px contextual draft entry saved; single account DOM and no horizontal page overflow; viewport and saved-draft captures visually inspected |
| Native Board/card integration | Board → project statement and card details → card account click-through passed |
| Board visibility vs finance | Board-capable identity without finance rights saw no statement shortcut and direct account request returned 403 |
| Prior regressions | Full M116 runner (including existing M107 SQL/DOM tests and PHP/JS/diff checks) reran green |

A full-page mobile capture showed a Chrome stitching artifact. Subsequent viewport-only captures and DOM checks established a single rendered account and correct narrow layout; the artifact was not treated as passing visual evidence.

Private evidence remains only on the Mac: `native-m116/private/native-tests.log`, `mysql-race-results.log`, `native-browser-results.json`, `board-browser-results.log`, `native-desktop.png`, `native-mobile-viewport.png`, `native-mobile-draft.png`, `native-browser.xlsx` and `native-print.pdf`. These paths contain no report-visible credentials. Do not upload the private runtime directory: it also holds protected test configuration and identity material. The source/report upload consent remains unresolved and no upload was attempted.

## Remaining limits and cleanup

Only the cases listed above are certified natively. The exhaustive private-project/entity/audit-scope fault matrix remains covered by the local automated suite, rather than a complete new native browser matrix. Actual physical phone/WebView, Dolibarr 24.0.1/23, native payment adapters, customer portal access, production data migration and live acceptance are outside this result. PDF is explicitly browser Print/Save PDF, not a server-generated native document attachment. No statutory-accounting posting is implemented.

Retain the isolated DB/runtime for local review unless cleanup is requested. Stop only the temporary trial listener when not testing. Cleanup must verify the private creation marker and exact target name, then remove only this database/runtime; never drop/restore M097 or alter the shared MySQL service, users or grants. No production rollout is authorized.
