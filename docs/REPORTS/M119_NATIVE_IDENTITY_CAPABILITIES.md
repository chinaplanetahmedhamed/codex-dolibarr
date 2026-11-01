# M119 — Native identity and capability checkpoint

Historical auth checkpoint below. Current default identity command is credential-free: six cases pass and two password/session cases are skipped unless `--with-auth` is explicitly selected with authorization. Capability/provenance helpers also default to no credentials. See [2026-11-01 integration evidence](M119_CREDENTIAL_FREE_INTEGRATION.md) for the current run and remaining gates.

Scope: isolated synthetic tests only, on `codex/m119-office-expenses-local`. No runtime configuration, actual credentials, users, rights or business rows were read or changed. No deployment, financial posting or production factory was enabled.

## Native identity evidence

`php deploy/tests/test_m119_native_identity.php /absolute/path/to/native/htdocs` — **7 scenarios pass**.

The test calls native `checkLoginPassEntity`/`check_user_password_dolibarr`, `User::fetch`, `User::loadRights`, `User::hasRight` and `User::isNotIntoValidityDateRange`. It imports empty official table definitions into a fresh owned temporary SQLite database. Synthetic users/rights are inserted only there. A random per-run password is bcrypt-hashed with the fixture's `MAIN_SECURITY_HASH_ALGO=password_hash`; neither secret nor hash is emitted or committed. Native password checking retains its failed-login delay.

Evidence covers valid versus wrong password, disabled account, wrong entity, direct and group expense creation rights, fresh-object permission revocation, module-disabled rejection, external users, expired/future-valid accounts, PHP session save/restore of login/entity/date, and an unpaid native draft owned/created by the fetched native employee. No rights are serialized into the session. The synthetic native writer also rejects disabled-user retries.

The new test initially failed because the domain service accepted a fetched disabled user with creation rights. The service and synthetic writer now require enabled status and honor the native validity-date method. Existing anonymous test actors explicitly declare enabled status. This is a defensive check after trusted context creation; it does not replace native authentication.

**Proof boundary:** PHP session storage plus native identity methods are tested together; the complete native `main.inc.php` login/logout/CSRF/session-revocation HTTP lifecycle is not exercised. The employee HTTP suite still uses synthetic session actors. Native source is included by path without loading real `main.inc.php` or `conf.php`. Test fixtures have inert hooks; no installed-runtime authentication acceptance is claimed.

The SQLite fixture uses the existing constructor/result-count workarounds plus a connection-local `GREATEST` helper for native user fetch. No native source or global PHP installation is modified. No production database engine has been tested.

## Observed currency and tax behavior

`php deploy/tests/test_m119_native_capabilities.php /absolute/path/to/native/htdocs` — **5 scenarios pass**.

| Probe | Readback from the synthetic native tables | Consequence |
| --- | --- | --- |
| Assign CNY code, rate 7.25 and foreign totals before `ExpenseReport::create` | Code remains NULL, rate defaults to 1, foreign gross defaults to 0 | Schema fields alone do not establish functional native expense multicurrency persistence. |
| Assign CNY code and original amounts before `ExpenseReportLine::insert` | Code remains NULL, foreign unit/gross default to 0 | The tested line insert also does not preserve supplied foreign fields. |
| Tax-inclusive gross 114.00, sample rate 14% | Native calculation, persisted line and recalculated header read net 100.00, tax 14.00, gross 114.00 | Simple nonzero VAT arithmetic works in this fixture. |
| Tax-inclusive gross 106.00, sample rate 6% | Net 100.00, tax 6.00, gross 106.00 | A second arithmetic case works. |
| Tax-inclusive gross 0.05, sample rate 14% | Net 0.04, tax 0.01, gross 0.05 | One small-amount rounding case works. |

The sample rates are mathematical examples, not Egyptian/Chinese tax advice or approved rates/categories. Tax probes invoke native `calcul_price_total`, `ExpenseReportLine::insert` and header `update_price`, with local taxes explicitly absent, inert hooks and triggers suppressed per call. They do not exercise the full `addline` category/dictionary path, recoverability, exemptions, local taxes, multiple-line rounding, receipts or reconciliation. All reports remain draft/unpaid. The adapter still permits only base-currency claims with explicitly verified zero tax, and rejects foreign currency and other tax policies.

## Exact source evidence

Read-only source tree: `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1/htdocs`. The directory name is not proof of the target installation version; these hashes identify the files actually examined:

| Source path relative to htdocs | SHA-256 |
| --- | --- |
| `expensereport/class/expensereport.class.php` | `983b272115e743a0374b03c84470ae1707ee8c48aeeb04bfa384b2686ba5a1ad` |
| `user/class/user.class.php` | `ee9e9105d0b8619029ca53f7a0d5f8526700993b447fea1b9e85c9b28691e6e1` |
| `core/login/functions_dolibarr.php` | `7c62d536810776a2404ed555806bc2768edda84ac9fb1611548cdc2b19d2c837` |
| `main.inc.php` (read only; never executed) | `48378aa4c4d9dbb187b416f028297817015246f62f8852662663f46752e12612` |

## Remaining gates

1. Prepare an authorized disposable runtime matching the selected release and database engine. Prove complete native login/session expiry, disabled/revoked users, `flagdelsessionsbefore`, CSRF, logout and entity transitions through `main.inc.php`; do not reuse production configuration.
2. Verify intended office/entity assignments and enabled module/category/tax configuration with synthetic equivalents. Supply a native-backed draft store/factory without permitting client identity or office scope.
3. Decide and test a native-supported foreign-currency representation preserving immutable original amount/currency and dated rate provenance; the tested create/insert paths currently discard supplied foreign metadata. Do not compensate by silently converting or by raw database patches.
4. Review expense-category, tax, receipt, rounding, recoverability and reconciliation mappings before any nonzero-tax adapter or approvals/payments/settlements. Advances and transfers remain separate accounting intents.
5. Reconcile the additive branch with the selected ERP release base and pass installed-runtime tests before activation or deployment.

Total M119 verification at this checkpoint: **62 scenarios pass**: 16 domain/store + 13 native drafts + 12 employee controller + 9 synthetic HTTP + 7 native identity + 5 native capability observations. Full-runtime gates remain pending; no action was denied or left with an uncertain execution outcome.
