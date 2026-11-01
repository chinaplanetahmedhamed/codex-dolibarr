# M119 — Native staff and finance-branch access proposal

Status: **APPROVED LOCAL IMPLEMENTATION — LOCAL_READY_FOR_REVIEW (2026-11-02).**

Ahmed approved the presented local integration and isolation-test plan with “yes, do it”. This authorizes local source, additive inactive schema and synthetic identity tests only. It does not authorize deployment, activation against a real database, or changing real staff rights/assignments. Existing pilot remains the default. Unsupported lifecycle actions stay disabled.

Ahmed's confirmed requirement (2026-11-02): Dolibarr has **one company/entity**. Egypt and China are **branches inside the finance module**. Moaz is assigned to Egypt and sees only permitted Egypt expenses; Rina is assigned to China and sees only permitted China expenses. These are not new legal companies, external customer third parties or separate logins. Real Dolibarr user IDs/logins have not been looked up; preview names illustrate Ahmed's examples.

The local visual proposal is authorized as a continuation of the current design work. No real account, membership, rights, database, pilot access or deployment is changed by this proposal. The existing administrator-only runtime restriction remains.

## Confirmed current source

- Canonical module: `custom/chinaplanetofficeexpenses/`; the Operations entry is a compatibility path.
- Native login is already provided by `main.inc.php` in the module entry.
- `OfficeExpenseModuleAccess` checks active internal identity/module access. The descriptor currently declares only own-draft and configuration rights; it is not a per-branch CRUD system.
- `OfficeExpensePilotConfiguration::scope()` restricts normal native storage binding to its configured administrator and supplies both offices. Replacing this with staff assignment is a material access expansion, not a menu fix.
- `OfficeExpenseDraftService` accepts trusted server-supplied office scope, but its existing read API and native store are **owner-only**, and even reading currently requires native expense creation permission. Branch-wide read-only access therefore needs a new authorized service/store path; merely removing creator predicates would be unsafe.
- Existing draft records already persist `entity`, `fk_user_creat` and the stable `office` code. Reuse `egypt` / `china` as branch identifiers; do not change historical record classification or infer branch from currency/URL.
- Local native source inspected: Dolibarr 24.0.1. Module declares PHP >=8.2 / Dolibarr >=24.0. Actual target installation, enabled modules, live user IDs, native group rights and schema remain unverified in this task.
- Local native `User` supports account lookup, status, direct/group rights and native user lifecycle. Native user-card permissions distinguish read/write/disable/password management. Use those original flows for global account creation/deactivation; do not duplicate password/account storage in the finance module.

## Proposed user journey

1. Authorized administrator opens Company Users (label: Dolibarr staff & branch permissions).
2. Select an **existing active internal Dolibarr user**, by a directory filtered and serialized to allowed fields only: user ID, display name, login and status. Never serialize a whole `User` object or password hashes/secrets.
3. Select Egypt or China; choose a permission template or customize section actions and record visibility.
4. Save the native-user-to-branch assignment, with transactional audit and readback. A second branch requires a second explicit assignment; permissions may differ by branch.
5. Staff continue using the same Dolibarr login. Their branch selector contains only authorized branches. No assignment means no finance-module access, even when they have unrelated native rights.
6. Remove from branch revokes that membership and its grants immediately. It preserves the Dolibarr account and historical business/audit records. Global deactivation remains in native user administration under its native rights.

Moaz/Egypt and Rina/China membership requirements are confirmed. Their exact role/grants are **not chosen yet**; the preview's data-entry/viewer presets are illustrative and grant nothing. The proposed branch-record read scope follows Ahmed's examples. Own-record visibility remains an optional narrower setting; no role automatically grants HR/payroll access.

## Approved local permission model

Effective access is an **intersection**, evaluated server-side:

`active internal native identity AND current native entity AND enabled module AND required native/module capability AND active branch membership AND section/action grant AND record/lifecycle policy`.

- Create, Read, Update and Delete/cancel are independent. Write implies read for the same section. Native broad/group permissions alone must not leak Egypt rights into China or vice versa.
- Native groups may provide templates/capability ceilings. They are not the only branch-security boundary: ordinary additive native group rights do not express different permissions per branch by themselves.
- A small module-owned branch-membership/grant store is proposed if no existing suitable assignment mechanism is confirmed. Membership uniqueness: `(entity, fk_user, office)`; action grants attach to that assignment. No second staff-account table. Additive versioned installation only; no module lifecycle data deletion.
- Grants default to denied. Existing administrator remains the explicit bootstrap access owner. Delegated access managers may only operate within their authorized branches and delegation ceiling. Staff cannot grant their own access, change native administrator flags, or promote themselves by changing request fields.
- Configuration permission is not silently repurposed as access-management authority. Add an explicit manage-branch-access capability, with unique native permission IDs checked before implementation.
- Reports, charts, totals, searches, direct IDs, downloads, attachments and exports use the same authorized branch scope **before querying/aggregating**. Server derives the actor/entity and resolves the branch; URL/POST fields never grant scope.
- Read-only staff must not need native expense creation rights merely to read approved module data. Native expense creation rights remain required only for operations that actually create native expense records.
- Branch-wide viewing is a new explicit authorized query/service, separate from existing owner-only methods. Preserve current owner checks for paths not approved for wider visibility.
- Refresh membership/capability state per request; revocation or native disablement must affect an already-open session on the next request, including downloads.

### Action/lifecycle boundaries

| Section | Proposed configurable actions | Boundary |
| --- | --- | --- |
| Expenses | C / R / U / cancel eligible draft | Immutable/current native-linked or posted records need their own approved workflow; no hard deletion |
| Advances/returns | C / R / U / cancel eligible draft | No settlement, reimbursement or cash movement enabled by assignment |
| Treasury | Read | Deposit/payment/transfer/posting are separately governed financial actions |
| Cash vouchers | Create / Read (future capability) | Current voucher page is a design; issuance/voiding requires its own implementation/lifecycle approval |
| Categories | C / R / U / archive unused category | No removal that breaks historical references |
| Reports | Read; export separately | Same branch filter for datasets, totals and output |
| Employee directory | C / R / U / archive | Not native login creation/deletion; no automatic payroll visibility |
| Payroll | Read only where explicitly granted | Approval/payment remains separate and disabled |
| Branch assignments | Explicit access-management permission | Not ordinary CRUD inherited from business-record permissions |

No financial posting or historical record deletion is part of this amendment. For immutable drafts, the future U/cancel workflow must use audited revisions/logical cancellation, preserving original payload, receipt provenance and retry identity; do not mutate existing signed/idempotent content in place. Unsupported actions remain disabled rather than advertising effective rights for unimplemented runtime pages.

## Proposed implementation sequence after approval

1. Recheck the current standalone source/version and existing work; keep one writer. Confirm the actual native user IDs for Moaz/Rina through an authorized native environment before any real assignment.
2. Implement an isolated access-policy service, branch membership/grant repository and audit contract with synthetic identities. Resolve the new native rights and migration design; reuse core identity/rights. Existing pilot remains default until explicit activation.
3. Add authenticated, CSRF-protected assignment management and safe directory serialization. Prevent duplicate membership, stale-update loss and self-escalation; transactional mutation + audit + readback with rollback on failure.
4. Bind the server-resolved branch/capability scope into the standalone route, storage factory, expense controller and eligible read queries. Cover the compatibility route too; neither may bypass the same gate.
5. Extend supported draft/read workflows only as specified. Wire CRUD/cancellation operations only after their lifecycle policy and native integration are proven; unsupported preview pages and financial actions remain unavailable.
6. Run syntax and focused/full Mission checks locally and on an authorized matching disposable native/MariaDB runtime. A local ACL test is not deployed acceptance.
7. Present a pinned staging package/activation plan for the exact environment and intended staff assignments. This amendment authorizes local development only; deployment and actual rights grants need a separately reviewed activation.

## Required acceptance cases

- Moaz/Egypt: allowed Egypt read/create as explicitly granted; China menus, list, totals, direct record IDs, receipts/downloads and forged office fields denied without record-existence leakage.
- Rina/China: the mirror cases; no Egypt data in counts, search, reports or exports.
- Same user with different grants on both branches: no union of the stronger branch role into the weaker branch.
- Read-only user cannot create/edit/cancel, even by POST; native creation permission alone cannot bypass module/branch denies.
- Manager: explicit authorized branches only; ordinary user cannot view staff directory or edit assignments; no self-promotion/delegation escalation.
- Missing/revoked branch membership, disabled/expired native user, external user, wrong entity, disabled module, stale role and old session all denied appropriately.
- Removal from one branch preserves other explicitly assigned branch, native account and historical data. Audit stores actor/target/entity/branch and before/after rights without secrets.
- Invalid CSRF, forged identity/entity/branch/capability, duplicate/stale changes and transaction/audit/readback failures do not leave partial grants.
- UI permissions, direct-route enforcement, desktop/phone Arabic/English and underlying persistence agree.

## Local design result and boundaries

The Company Users preview now illustrates native staff selection, a per-branch assignment dialog, CRUD matrix, record-scope choice, and revoke-branch-access dialog. It preserves the original visual style and removes the independent username/password creation concept. All save/revoke actions remain disabled. The local preview does **not** read Dolibarr's directory or enforce runtime staff isolation.

Admin ERP: planned integration, not implemented. Customer Center: no access; external users remain excluded. Mobile App runtime integration: pending any future affected API surface; local responsive preview only.

Rollback proposal: disable new staff entry/management, restore the administrator-only binder and revoke affected grants without deleting historical data; schema retention/removal must follow its reviewed migration rollback. No rollback is needed for actual grants in this task because none were changed.

## Verification of the proposal preview

- Python and generated JavaScript syntax and `git diff --check` passed; generated manifest hashes and unique IDs passed, and the revised user page contains no password fields.
- Impeccable detector reported no findings. Arabic/English at effective CSS widths 433, 853 and 1600px had no page overflow; the assignment dialog fit each viewport and displayed 21 supported action checkboxes.
- Script-dispatched preview interactions verified Rina/China prefill, the viewer/data-entry template change, removing Write when Read is unchecked, and the named branch-removal dialog. Save and revoke remained disabled.
- Native pointer/keyboard automation on the shared background page timed out before opening a dialog; DOM-dispatched checks were used for the proposal controls, not as proof of authenticated runtime operation. A paused background animation-frame wait was cancelled by navigation and replaced by direct layout reads; no application change was needed for that tool condition.
- Desktop overview screenshot was inspected. The shared background browser returned a stale/cropped phone raster despite current DOM viewport-fit checks; fresh visible phone screenshot acceptance remains pending. These checks establish only a static prototype, not native directory access, staff authentication, permission persistence or branch isolation.


## Implementation result — 2026-11-02

The local native membership repository, policy, assignment route/controller/page, dormant runtime binding and explicit branch-read queries are implemented. The pilot remains the default. Supported effective grants are expenses/advances Read and Create operational drafts; editing/revoking assignments is implemented. Financial record update/cancel and the remaining preview sections stay unavailable. See [completion and activation boundaries](M119_STAFF_BRANCH_ACCESS_IMPLEMENTATION.md) for source, verification, data impact and pending target-runtime acceptance.
