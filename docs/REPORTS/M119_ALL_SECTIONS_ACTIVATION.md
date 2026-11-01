# M119 all remaining sections — activation handoff, 2026-11-02

## Current result

**Source complete for the operational workspace; backup-ERP activation and authenticated acceptance remain pending.** No production action is authorized or performed. The authenticated backup-ERP expenses page still contains seven Pending navigation badges at this checkpoint.

The current application revision is `6fa6caf5dde3e3762354ae8faaf4947c49bdb52d`, pushed to `codex/m119-office-expenses-local`. [Matching runtime checks 41358979130](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41358979130) passed PHP8.2.32, Dolibarr24.0.1, MariaDB10.6.27, the full Mission suites, synthetic HTTP and real Linux private-filesystem checks. New targeted application totals: 14 document cases and28 workspace native-component cases. This establishes source/CI evidence, not activated server behavior or staff password login.

This report supersedes the older account-discovery paragraph and artifact pins in [earlier implementation evidence](M119_ALL_SECTIONS_IMPLEMENTATION.md). Four native branch/currency cash accounts and the native top-bar icon already exist; their verified evidence remains in [actual treasuries](M119_ACTUAL_BRANCH_TREASURIES.md) and [top-bar icon](M119_TOP_MENU_ICON.md). Do not recreate the accounts, repost openings or re-enable the older server descriptor.

## Available operational sections

- Expenses and advances/returns: branch-scoped versioned requests, original currency, edit/archive/restore where authorized; old immutable drafts and their private attachments remain accessible.
- Treasury: actual native EGP/USD or CNY/USD accounts, recorded/current balances and native statement entries; separate draft deposit requests.
- Receipts: original-currency draft receipt vouchers, validated payer/method/source/purpose, printing.
- Categories: create, edit, disable, reversible archive/restore; shared within the assigned branch.
- Employees: assigned native staff directory and optional employment profiles; no duplicate login identities.
- Payroll: one draft per employee/month, exact base+bonus=gross, separate deductions/loan reduction/net; subject-based personal payslip visibility.
- Reports: dates, kinds/status, own/branch constraints, safe request CSV, separate native cash-period CSV and print; openings excluded from operating cash inflow, currencies never combined.
- Settings: branch identity/contact, normalized private PNG/JPG logo and fixed primary currency; cash mappings restricted to the trusted native accounts.
- Users: native Dolibarr identities and audited granular CRUD/record scope; native capability ceilings, cross-branch denial and revocation retained.

Real cash receipts/payments, salary payments, employee loans and FX posting adapters are not implemented or approved by a draft save. The approval owner question remains unanswered. No setting silently converts a request into an accounting transaction. New workspace forms do not add new attachment-upload support; the existing immutable-draft attachment workflow is retained. Print uses browser Print/Save PDF.

## Exact release and data scope

[Workspace deployer](../../deploy/releases/m119/workspace_update_helper.py), [private native worker](../../deploy/releases/m119/workspace_native_operations.php), [bounded installer](../../deploy/releases/m119/install_workspace_helper.py), [fixed remote actions](../../deploy/releases/m119/run_workspace_remote_action.sh) and [release fault tests](../../deploy/tests/test_m119_workspace_deployer.py) prepare the existing five-action helper without changing sudoers, containers, network exposure, credentials or production.

Application package: 59 runtime files plus the release declaration, source `6fa6caf5dde3e3762354ae8faaf4947c49bdb52d`.

- Archive SHA256: `a367cea37de019749ece5a1d8a2016accad6d24d45efc169e06cd98d737f4192`.
- Manifest SHA256: `0e2a55e3a9edc624bf154a754c307a1c12ebfc6af8fd9b4c5cf0d3e18bf8f796`.
- Additive schema: only the two manifested operational workspace tables plus their existing schema-state entries.
- Configuration: `CPO_OE_WORKSPACE_ENABLED=1`, only after code/schema and assignments verify.
- Four exact active entity-1 identities, existing groups/native rights and rev1 memberships are preconditions. Moaz/Egypt and Rina/China become branch managers for all operational CRUD sections. Omar/Egypt and Shan Shan/China retain own expense/advance read/create, branch category read, own reports/profile/payslip read; no branch cash/configuration/directory access. Memberships advance to rev2 with native audited assignment; no global rights or accounts are granted.
- Before mutation: exact old runtime closure/inode, current container/network/private storage identity, complete consistent native database backup and source backups. Financial and native identity/right rows are hashed privately before/after; amounts, bank details and credentials are not emitted.
- After installation: exact59-file closure, native schema readiness, all four users' CRUD ceilings, other-branch denials, cash/report restrictions, native cash mappings and login walls. Staff password login and authenticated save/edit/archive/print/export/mobile acceptance remain separate gates.

## Administrative access blocker

Restricted SSH as `codex-deploy` to `srv1011043` works. Its installed helper is still the exact R03 checksum `b51706fff210aba0022502aff7b74b698ed6a6f11b5e0286bd9b5f1a3aaa1674`; that helper cannot accept the new schema-bearing package. No attempt was made to send it a mismatched package or broaden sudo.

The existing Termius root terminal can be observed, but attempted identity input did not produce visible command output and coordinate interaction reported `noWindowsAvailable`. Therefore root installation has not run. This is a tool/access blocker, not missing deployment authorization. A single checksum-pinned administrative installer command will be staged after its independent CI checks pass; it updates only this reviewed helper and performs preflight, not schema/role activation. The release job then performs backup/apply/verify through the existing restricted identity.

## Rollback, parity and unverified gates

Disable only the workspace flag; retain compatible code, operational schema, documents, grants and audit. Never restore the old grant parser or an old database over new submissions. A partial failure is retained with an explicit disabled/reconciliation state; no automatic retry or destructive cleanup is offered. Source backups and DB dump are private evidence/recovery inputs, not permission to erase subsequent records.

Admin ERP: operational code implemented, activation pending. Customer Center: not applicable and no data exposed. Mobile app: not implemented; responsive web AR/EN source/DOM checks exist, activated web acceptance remains pending. Financial native posting and staff password login remain untested/unimplemented as described above.
