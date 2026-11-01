# M119 — Release evidence and native HTTP acceptance preparation

Historical preparation record. **The16 cases now pass in an explicitly authorized disposable local native HTTP environment**, with a direct-login correction and verified cleanup: [current execution evidence](M119_NATIVE_HTTP_ACCEPTANCE.md). Target PHP/MariaDB, HTTPS/proxy behavior and production acceptance remain pending. Statements below describe the earlier preparation-only checkpoint.

Latest credential-free checkpoint: [68 passing local/library scenarios](M119_CREDENTIAL_FREE_INTEGRATION.md). Current identity runs skip two password/session cases; historical counts below are not the current execution total. All16 full-native HTTP cases still remain not_run.

## Evidence matrix

Parent-supplied read-only research reports the following historical receipts. They are context for candidate preparation, not fresh production observations or a jointly verified stack.

| Component/evidence | Receipt | Treatment |
| --- | --- | --- |
| Dolibarr 24.0.1 | Oct 27 | Freshest reported deployed-version receipt; candidate source version, still needs fresh release confirmation. |
| PHP 8.2.7 | Oct 27 | Candidate runtime version, not available in the checked local Homebrew PHP8.2 locations. |
| MariaDB 10.6.27 | Oct 2 | Older separate receipt; engine/version still require fresh confirmation. |
| Older README target 23.0.3 | Older documentation | Must not override the later receipt or serve as target-parity evidence. |
| M116 Dolibarr24.0.2 / MySQL5.7 | Prior environment | Does not match the candidate release/engine and must not be counted as parity. |
| Current local source24.0.1 / PHP8.3.21 / synthetic SQLite | Verified in this task | Useful library/domain evidence; not PHP8.2.7 or MariaDB runtime acceptance. |

Local checks: `php -r 'echo PHP_VERSION, PHP_EOL;'` returns8.3.21. No `php@8.2` directory exists at the two checked Homebrew locations; this is not an exhaustive machine inventory. No ready database runtime was found through the previously recorded routes: SQL executables absent on PATH, no3306 listener, default and `colima-m073` Docker sockets absent and both Colima profiles stopped. No attempt was made to start them or infer production credentials/configuration.

## Source-derived acceptance details

Read-only native source identified by the hashes in `M119_NATIVE_IDENTITY_CAPABILITIES.md`:

- `main.inc.php` lines343–359 rolls `newtoken` into `token`, and conditionally generates the next token. `currentToken` and `newToken` in `core/lib/functions.lib.php` return these separate session values. They do not themselves rotate tokens. The employee entrypoint passes current token for request validation and next token for form rendering.
- The module's `CSRFCHECK_WITH_TOKEN` makes its native POST token mandatory. Native missing-token handling returns403; stale-token handling clears POST/action parameters and adds `errorcode=InvalidToken`. Those paths must be tested through native bootstrap, including native response behavior before the controller is reached.
- Existing-session handling fetches login/entity and checks disabled status, validity range and `flagdelsessionsbefore` relative to native login time. `main.inc.php` loads native rights later in the request. The planned fixture changes only temporary users/grants in its separately owned database.
- `user/logout.php` has its explicit `CSRFCHECK_WITH_TOKEN` declaration inside a comment. Tests must record effective native logout behavior and verify old-session replay fails; do not claim a mandatory logout-token check based on a text search alone.
- A session-regeneration helper exists in `filefunc.inc.php`, but no caller was found in the examined native main/core/user paths. Actual session-cookie behavior and any fixation concern require complete-runtime verification and review. No core/security change is proposed or performed here.
- PHP garbage-collection lifetime is not a deterministic idle-expiry assertion. Record effective behavior; test disappearance of only the owned synthetic session state without changing global PHP/security settings. Native HTTPS cookie behavior cannot be certified from a loopback HTTP test.
- Verify module bootstrap resolution for the actual selected hosting prefix/document root. The current entrypoint uses `$_SERVER['DOCUMENT_ROOT'].'/main.inc.php'`; an alias/subdirectory deployment must be proven, not assumed.

These observations define test expectations, not completed HTTP tests. They do not replace native security checks or justify bypass flags.

## Prepared deliverable

`deploy/tests/fixtures/m119_native_acceptance_plan.json` contains **16 cases**, all explicitly `not_run`: guest, login/wrong password, employee draft save, missing/stale tokens, current/next tokens and multiple tabs, disabled/validity checks, revoked rights, session invalidation, logout/expiry, identity/entity scope, retries/storage failure, cookies/headers, bootstrap/factory and cleanup. It is inert data, not a runner or provisioning script, and contains no credentials.

The existing native identity suite now has **8 passing scenarios**. Its additional test calls actual native `currentToken()`/`newToken()` with distinct modeled post-roll values and verifies the controller rejects the next-form token for the current request, then persists one draft with the correct request token. This verifies the module boundary only; it does not establish one-use tokens, execute native `main.inc.php` token rotation/CSRF or prove deployment parity.

Current M119 evidence totals **66 passing scenarios**:16 domain/store +13 native drafts +12 employee controller +9 synthetic HTTP +8 native identity +5 native capabilities +3 provenance. The 16 prepared full-native HTTP cases are excluded from that count.

## Exact remaining gates

1. Freshly establish intended release, PHP and database engine/version, including enabled modules/settings in an authorized test context. The Oct27 and Oct2 receipts alone cannot establish current parity.
2. Obtain the required approval for any installation, service start or security-setting change needed to prepare a disposable runtime. The checked local environment cannot execute the full acceptance suite as-is. No such change was attempted, denied or left uncertain.
3. Run the prepared cases through unmodified native bootstrap in that isolated runtime with ephemeral synthetic access and no real data, outbound mail or financial posting. Record measured results/source versions and clean up owned resources.
4. Resolve the proposed FX/tax/reconciliation decisions and prove native behavior before enabling any financial adapter; this preparation changes none of those gates.
