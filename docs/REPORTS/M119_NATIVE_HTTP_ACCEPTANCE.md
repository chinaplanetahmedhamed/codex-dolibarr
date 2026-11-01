# M119 — Native Dolibarr HTTP acceptance, 2026-11-01

Outcome: **16 native HTTP cases pass,0 fail,0 blocked**, including direct native login into the employee interface. All owned test processes, database files, accounts, secrets, generated keys and sessions were removed; cleanup was independently verified. Production, real accounts, the existing MAMP database and stopped Colima profiles were untouched. No commit, push or deployment.

## Tested environment and boundaries

Final run: `20261101T145432-d60c056a`.

- Actual native Dolibarr24.0.1 source, Homebrew PHP8.3.21 and MAMP MySQL5.7.39 binary. Installed software only; no downloads, containers or VM start. This is **not** PHP8.2.7/MariaDB10.6.27 or production parity.
- Separate0700 temporary root `/private/tmp/m119-login-c2c0b2e85c38e9d1`, now deleted. MySQL used its own datadir/socket/tmpdirs and `skip_networking=1`; no database TCP listener. PHP used only127.0.0.1:18849, now closed. Owned MySQL2393 and PHP2422 exited and were waited on.
- Native installer definitions:275 base tables,224 key files and45 standard dictionary/config files. Accounting charts and accounting module were not installed. Seven synthetic native accounts and a schema-local SQL runtime account existed only in the disposable database.
- Actual native `main.inc.php`, password verification, fetched User, direct/group rights, validity checks, sessions and CSRF. No forced identity, NOLOGIN, CSRF bypass or core edit. A trusted test-only factory bound actual User/entity to the synthetic draft store; it never connected the native financial writer.
- Source copying excluded real conf.php, existing custom/config/data/uploads and secret files. Child environments and MySQL login-file handling were isolated. No actual configuration or credentials were read. Only redacted JSON/request metadata/source hashes remain.

## Product correction proven RED → GREEN

Directly opening the module while logged out presents native login. Previously native authentication succeeded but the resulting login POST reached the draft-only controller and returned403. A manual refresh worked. The first runner incorrectly hid that response with an extra GET; its earlier apparent16-pass result is **superseded**.

The corrected runner checks the immediate login response and follows only actual HTTP redirects. It reproduced the403 defect in run `20261101T145241-7355fbc8`:13 positive-flow failures,3 other cases passing, cleanup complete.

`custom/chinaplanetoperations/office_expenses/index.php` now recognizes a native login POST only after ordinary bootstrap, with string login fields and a matching authenticated native User/session. The controller authorizes the read view; permitted staff receive a fixed local303 redirect. Login credentials are never submitted as an expense draft. Denied accounts still fail authorization; native CSRF runs first. No draft or financial write is added to login. Independent code review found no material issue in the fix.

Final run records **login POST303 → employee GET200**, with **no manual recovery GET**. The native core hashes remained unchanged. Test-only PHP development-server normalization supplies an absent empty `QUERY_STRING`, matching normal web-server behavior; the final run has **zero native PHP warnings**. No core/security setting was changed to suppress the earlier fixture warning.

## Case results

| Case | Verified result |
| --- | --- |
| NHTTP01 guest | Native login page, no employee form/private draft data or writes |
| NHTTP02 native login | Password/session/rights work; immediate303 landing and employee200; no finance records |
| NHTTP03 bad password | Native rejection, no authenticated form or new draft |
| NHTTP04 employee save | One exact EGP700.10 immutable draft owned by native User7/entity1; redirect and readback |
| NHTTP05 missing token | Native403, zero draft writes |
| NHTTP06 stale token | Native sanitization followed by module403; zero writes or PHP diagnostics |
| NHTTP07 current/next token and tabs | Effective per-call renewal0; two native-valid tabs work; back/retry retains one source |
| NHTTP08 disabled/validity | Existing sessions lose access for disabled, expired and future-valid fixture users |
| NHTTP09 revoked rights | Fresh requests deny revoked direct and group grants |
| NHTTP10 invalidation | Earlier timestamp accepted; later fixture invalidation loses session access |
| NHTTP11 logout/missing session | Native rendered logout; old-cookie replay denied; deleting only owned session state denies access |
| NHTTP12 ownership/entity/office | External, no-right, admin-without-expense-right and wrong-entity fixtures denied; forged fields/actions and other employee ID blocked |
| NHTTP13 retries/failure | Same key maps to one source; changed amount409; audit failure rolls back; same-key retry recovers without partial draft/audit |
| NHTTP14 cookies/headers | Native HttpOnly, SameSite=Lax, path=/; no-store and expected CSP; Secure=false on loopback HTTP is recorded, not HTTPS acceptance |
| NHTTP15 bootstrap/factory | Actual native User/root bootstrap; absent or mismatched factory503 with no write; normal factory recovers |
| NHTTP16 evidence/cleanup | Versions/hashes and per-case trace preserved; owned processes stopped, root removed, listener closed and shared state unchanged |

Native expense reports:0. Native expense payments:0. Native bank rows:0. `accounting_bookkeeping` is explicitly **not installed because accounting is disabled**; this is not counted as a tested posted-journal workflow. The employee interface creates only synthetic review drafts. It does not expose approval, payment or bank posting.

## Evidence and cleanup

All deliverables below are in `/Users/mac/Documents/Codex/2026-11-01/task/`:

- `m119-native-http-evidence/20261101T145432-d60c056a.json`: authoritative16-case result, redacted request trace, native factory observations, versions/core hashes and owned teardown results. `latest.json` is a convenience copy.
- `m119-native-http-evidence/post-run-verification.json`: independent current PHP version, artifact/harness hashes, all attempted roots absent,18849 absent, existing MAMP listener unchanged and both Colima profilesStopped.
- `m119-native-http-harness/{run.py,database.php,factory.php}`: reproducible runner/initializer/factory source, no credentials. Run only with explicit disposable-service/account authorization and any required platform confirmation.
- `m119-native-http-evidence/index-before-native-login-fix.php`: local code backup for the one product-file correction. Earlier failure/RED JSON files remain for audit; they are not the final acceptance result.

Both owned processes were terminated and waited on before collecting fallible evidence/removing files. Every attempted temporary root is absent, including unsuccessful setup attempts. PHP18849 has no listener. Existing MAMP PID28713 remains on127.0.0.1:8889; no SQL connection was made to it. m073 and shipping-docs remainStopped. No task credentials/private keys/session data remain. ERP's five protected money/domain/bridge/store/writer hashes match the prior checkpoint, and the existing Git index/work remains preserved. The entrypoint correction is unstaged; no history/index mutation occurred.

The platform originally denied the escalated service/socket operation by citing the old stop instruction. Following the user's new confirmation of the exact socket/port/accounts operation, the platform accepted the retry. That authorization blocker is **resolved**; no denied operation was bypassed.

## Remaining gates for real use

1. Implement/review runtime draft persistence, trusted factory and private receipt storage. The shipped entrypoint still fails503 without a factory; only a guarded test factory was supplied here. Do not wire the synthetic stores into production.
2. Confirm intended release/engine/configuration and repeat relevant acceptance in an authorized matching environment. Native SQL/PHP compatibility proof here is specific to24.0.1/8.3.21/5.7.39. Legitimate multicompany switching and alias/subdirectory hosting were not enabled/tested.
3. Verify HTTPS/reverse-proxy cookie behavior and actual session policy. The measured native session cookie identifier did **not** change during login; logout/replay/invalidation tests passed. This observed native behavior was recorded, not altered or certified against session-fixation threats. Missing owned session-file tests do not establish real idle-expiry timing.
4. Resolve foreign-currency, tax/category/account mappings, advances/returns/reimbursements/transfers, rounding and existing-cash-movement reconciliation before enabling any financial adapter. Current native source drops supplied foreign fields on report/line insertion; these login tests do not resolve that behavior.
5. Reconcile the additive isolated branch with current source before release; separately authorize deployment. No production readiness or actual employee/accounting workflow is claimed by this checkpoint.

Changed product PHP syntax, harness PHP/Python syntax and staged/unstaged whitespace checks pass. The earlier68 credential-free scenarios are historical evidence and were not rerun for this focused native HTTP correction.
