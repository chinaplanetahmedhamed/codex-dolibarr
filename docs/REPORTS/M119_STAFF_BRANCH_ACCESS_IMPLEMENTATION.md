# M119 — Native staff and branch access: local implementation

2026-11-02 · LOCAL_READY_FOR_REVIEW · No deployment or real staff assignment

Ahmed approved local integration and isolation testing with “yes, do it”, following confirmation that Dolibarr has one company/entity and Egypt/China are finance branches. The [approved design](M119_STAFF_BRANCH_ACCESS_DESIGN.md) and [Mission amendment](../MISSIONS/M119_OFFICE_EXPENSES_LOCAL_FOUNDATION.md) record this scope.

## Result

Company Users now has a native authenticated PHP route for assigning an existing active internal Dolibarr user to a branch, editing that assignment and revoking branch access. Accounts, passwords and global native rights remain managed by Dolibarr. No actual Moaz/Rina account IDs or grants have been selected or changed.

The implemented permission matrix supports **Read and Create operational drafts**, independently for Expenses and Advances/returns, with own-record or branch-record visibility. Assignment edits/removal are supported. Financial record update, cancellation/deletion, posting and the other prototype sections remain unavailable. Their future lifecycle must preserve immutable source, receipt provenance and native links.

Each access decision combines fresh native identity/status/validity and direct/group rights with current module/entity, branch membership, section action and record scope. Read-only staff do not need native expense creation permission to read drafts. Native module Read is a capability ceiling; Create additionally requires the native module draft capability. The assignment UI does not grant these global rights automatically.

The configured pilot administrator is the explicit bootstrap owner. Other native administrators receive no automatic branch bypass. A delegated manager needs native manage-access permission plus branch access; they cannot change themselves, the bootstrap owner, a stronger membership, or grant more actions/record visibility than their branch ceiling. The native configuration-admin permission is not reused as access-management authority.

Revocation is a retained membership revision, not account/data deletion. Native identity and rights are fetched again for subsequent service operations/requests. No access state is cached in the session. Existing native owner-only APIs remain owner-only; new branch APIs apply entity, branch, section and optional owner predicates before retrieving rows. Totals/search operate on those authorized results. Private receipt reads use the same authorized draft resolution; uploads remain restricted to the creator with Create permission. No export/report endpoint is introduced.

## Source changes

| Area | Source |
| --- | --- |
| Native identity and branch policy | [OfficeExpenseStaffAccess.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffAccess.php), [OfficeExpenseNativeAccessStore.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeAccessStore.php) |
| Assignment route, validation and original component styles | [users.php](../../custom/chinaplanetofficeexpenses/users.php), [OfficeExpenseStaffController.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffController.php), [OfficeExpenseStaffPage.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffPage.php), [staff.css](../../custom/chinaplanetofficeexpenses/staff.css) |
| Dormant native binding and module gates | [OfficeExpenseStaffRuntime.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseStaffRuntime.php), [OfficeExpenseNativeStorageFactory.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeStorageFactory.php), [OfficeExpensePilotConfiguration.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpensePilotConfiguration.php), [OfficeExpenseModuleAccess.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseModuleAccess.php), [index.php](../../custom/chinaplanetofficeexpenses/index.php) |
| Scoped reads and receipt authorization | [OfficeExpenseDraftService.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseDraftService.php), [OfficeExpenseNativeDraftStore.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeDraftStore.php), [OfficeExpenseReceiptService.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseReceiptService.php) |
| Permission-aware employee controls | [OfficeExpenseEmployeeController.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeeController.php), [OfficeExpenseEmployeePage.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php), [OfficeExpenseReceiptPanel.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseReceiptPanel.php), [employee.js](../../custom/chinaplanetofficeexpenses/employee.js) |
| Explicit inactive migration | [access schema](../../custom/chinaplanetofficeexpenses/schema/access/), [OfficeExpenseNativeSchemaMigration.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeSchemaMigration.php) |
| Native capability/menu definitions | [module descriptor](../../custom/chinaplanetofficeexpenses/core/modules/modChinaPlanetOfficeExpenses.class.php), [OfficeExpenseNativeMenuSyncService.php](../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeMenuSyncService.php) |
| New acceptance cases and CI entry | [test_m119_staff_access.php](../../deploy/tests/test_m119_staff_access.php), [run_ci.sh](../../deploy/releases/m119/run_ci.sh); existing employee UI/menu/HTTP tests and synthetic router extended for dormant access |

The Operations compatibility entry still includes the canonical route; it cannot bypass staff policy. Dolibarr core is unchanged. Existing draft/receipt schema artifacts retain their prior identities and checksums.

## Data, permission and configuration impact

- Additive inactive tables: membership keyed by `(entity, fk_user, office)` and access audit keyed by membership/revision. No duplicate staff-account table. Grants, record scope, revision and active status are stored with actor/target/entity/branch and complete before/after audit. Native account rows are read only.
- Membership, audit and readback share one owned transaction. Stale revisions, audit failures and false/lost commit acknowledgement cannot return confirmed success. An uncertain handle is quarantined; reconciliation uses a fresh physical connection. No destructive retry or recovery is attempted.
- Access schema has a separate pinned version/manifest and depends on the unchanged draft schema. Partial installation stops without adoption/overwrite. Module init/remove performs no domain-schema install or deletion.
- New default-denied native rights: `500603` Read assigned branches; `500604` Manage branch access. Existing `500601` draft and `500602` configuration retain their identities. No collision was found in inspected local custom/native module source. The target rights registry must still be checked before activation.
- `CPO_OE_STAFF_ACCESS_ENABLED` defaults off. The private root-owned pilot configuration remains required; its approved administrator supplies the bootstrap ID and its entity/currency must match. Source deployment alone does not activate staff access.
- The descriptor contains a separately gated staff menu expression. The pilot menu synchronizer accepts only the exact old pilot or reviewed dormant expression and never broadens arbitrary existing menu rows. Pilot menu synchronization is unavailable in active staff mode; menu refresh belongs to the reviewed activation step.
- Directory output is explicitly limited to ID, login, name and status. No whole native user object, password hash, API key, salary or private field is rendered or serialized. Mutation routes validate CSRF, action/field allowlists, branch, identity and revision; file uploads are not accepted by assignment management. Responses use generic errors, no-store and native-compatible CSP.

## Verification

Current local PHP: **8.4.7**. Native library/schema source: **Dolibarr 24.0.1**. Synthetic tests use actual native User/direct/group-right loading with isolated SQLite state, plus the existing test-only mysqli metadata/lock adapter. No real configuration, account, database or service is used as test data.

Final local run: **18 available safe suites passed, 0 failed**:

| Suite | Passed checks/scenarios |
| --- | ---: |
| Office expense domain | 16 |
| Standalone module | 8 |
| Employee interface/controller | 18 |
| Native menu synchronization | 18 assertions |
| New staff access | 20 |
| Native draft adapter | 13 |
| Native identity, credential-free mode | 6; 2 auth/session cases intentionally skipped |
| Native capability probe | 5 |
| Native provenance | 3 |
| Native workflow | 10 |
| Native operational storage | 22 |
| Private receipt domain | 21 |
| Receipt interface/controller | 15 |
| Native migration | 17 |
| Native receipt storage/binding | 23 |
| Pilot configuration | 7 |
| Synthetic cookie/CSRF HTTP | 13 |
| Historical release artifact contract | 5 |

The 20 new cases cover missing membership, Moaz/Egypt versus Rina/China, different grants in two branches, branch/own visibility, native direct/group ceilings, removal of native rights, expiry/disablement, external/wrong-entity identities, self-promotion and delegation limits, safe directory fields, forged/CSRF/stale requests, successful assignment/edit/revoke/reactivation through the controller, branch receipt downloads and forbidden uploads, unchanged source history, audit rollback, uncertain commits and independent/partial access migration.

PHP syntax: **28 PHP files checked, 0 failures**. JavaScript syntax and `git diff --check` passed. Arabic/English PHP-rendered synthetic review DOM fits effective widths **433, 853 and 1600px**; form fields fit and wide phone tables scroll within their container. No password fields appear. The review fixture has disabled submission controls and an explicit synthetic banner; it is not a live native directory. The browser screenshot transport again returned a narrow raster on a wide canvas despite current layout reads, so this is not claimed as fresh pixel-perfect desktop/phone acceptance.

The HTTP fixture additionally confirms guest rejection and default-off GET/POST denial on the exact new staff route. Its sessions are synthetic. The activated staff route has **not** been accepted through real Dolibarr login/main.inc.php on a matching target runtime.

Matching PHP 8.2.32/MariaDB tests and root-owned Linux/www-data filesystem tests were **not run** on this Mac: their disposable GitHub Linux/MariaDB fixtures are absent. No dependencies, container, database, credential or remote service was installed to substitute for them. The old artifact contract passing does not package these standalone changes; its existing shim rejection correctly prevents an incomplete legacy release.

## Remaining activation gates

1. Review a complete standalone package pinned to a new source commit, including new policy/route/schema dependencies. Current code is uncommitted local work; there is no deployable/published package from this task.
2. In an explicitly authorized matching disposable/staging environment, verify installed version, module state, native rights IDs and actual native staff identities. Agree Moaz/Rina's exact per-branch grants; preview role examples are not authorization to grant them.
3. Run actual MariaDB migration, native directory/direct/group permission tests and authenticated staff GET/POST/CSRF/direct-ID/private-download tests. Verify totals/search and fresh desktop/phone Arabic/English pages with the installed native shell.
4. Before separately approved activation, back up the exact affected schema/config/menu/data, install only the pinned additive access migration, grant reviewed native capabilities through Dolibarr, save reviewed memberships, refresh the reviewed menu and explicitly enable staff mode. Reaccept Moaz/Egypt and Rina/China, negative cross-branch requests and session revocation. No such actions were performed here.

Admin ERP: local integration implemented, target runtime acceptance pending. Customer Center: not applicable; external identities denied. Mobile App: no new API or integration exposed; pending if a future mobile surface is added. Responsive web DOM was checked as described above.

## Rollback and source evidence

Branch: `codex/m119-office-expenses-local`. Existing HEAD: `1b3f92f3e7f4ef825b8779da809c86dc8b029b13`. No new commit, push, deployment or runtime activation. Existing unrelated dirty work was retained.

For the local change, revert only the reviewed scoped patch if needed. If separately activated later, disable staff mode first and restore the administrator-only binder/menu/config from its reviewed backup. Retain membership revisions, audit, draft/receipt data and schema; do not drop tables or delete historical records as a rollback shortcut. Real grants/configuration currently need no rollback because none were changed in this task.
