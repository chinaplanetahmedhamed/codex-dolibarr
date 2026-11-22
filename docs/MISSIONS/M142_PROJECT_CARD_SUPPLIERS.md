# M142 — Project/card suppliers and supplier project boards

Owner Ahmed Hamed; implementation Codex. 2026-11-23 Asia/Shanghai. Branch codex/project-card-suppliers.

## Agreed behavior

A customer project may have several cards; each card can link to several native Dolibarr suppliers. The project can also link suppliers directly. Ahmed explicitly selected supplier linking and immediate supplier-board availability before purchase-order approval, with draft orders created when needed. Supplier identities, costs and operational details remain internal. No automatic order validation, invoice, payment or customer publication.

## Current implementation and gap

Card ERP context currently supports customer/project/shipment through InternalTaskContextService and card_operation.js. SupplierWorkspaceService only lists native purchase orders and creates order-specific boards after approval (statuses2–5) via cpo_supplier_board. Existing native third parties, projects, task/Board identities, ACLs, cpo_activity and native supplier-order objects must be reused.

## Design

Use existing native element_element relationships, scoped by joined entity-owned records: cpo_task→societe for card suppliers, project→societe for project suppliers, cpo_board→societe and cpo_board→project for the new supplier-project board context. Owned tags are `cpo_supplier` and `cpo_supplier_project`; unrelated existing native tuples are preserved and cause a conflict if they occupy the same unique relationship. No new party/task/order registry or schema. The new project board layer coexists with unchanged approved-order boards; no conversion or data migration.

Linking from a card validates its current customer-project Board, project/customer access, supplier role/entity and native staff rights; edits additionally require task editing, existing administrator Board-creation capability and native project scope. Link/unlink uses a transaction, project/task locks, task-version comparison and activity event. Linking a card also adds the project supplier association; removing a card link does not remove the project association, orders or board. Linking at project level uses its Board manager/version scope. Supplier-project board creation is idempotent under the native project row lock, inherits the project Board team and creates a private staff Board using the existing Boards service. Board ACLs remain explicit; no supplier/customer login grant.

Supplier workspace lists linked projects immediately, including existing native purchase orders. Project boards display references to authorized linked customer cards instead of duplicating/moving them, plus native project/supplier order links. Order creation is an explicit confirmed action using `CommandeFournisseur::create()`: an empty draft retains the selected native supplier and customer project, then opens its native order card for products/review. The native creation form filters projects by supplier and cannot safely retain this customer project without a global setting, so no form workaround or global setting change is used. Native purchase-create rights are required separately from Operations task administration. A project row lock and scoped native request relation prevent duplicates on retry. Required native custom order fields block empty creation with a clear message rather than bypassing validation. New order entity, supplier, project and draft status are read back before commit. No approval, line, invoice or payment is automatic. Existing order approval rules remain effective for legacy order-board creation.

## Alternatives

Order-only linking cannot satisfy pre-order supplier work. Automatically creating/approving orders would invent purchasing details and expand the business action. The selected design adds only supplier relationships and staff workspaces, then uses the existing native order object for explicit draft creation.

## Implementation plan / acceptance

1. Test-first service for scoped native supplier search, multiple card/project suppliers, version conflict, idempotent Board reuse, read/write denial and cross-entity attempts. Exercise persistence/unlink/history and transaction rollback with synthetic records.
2. Extend owning SupplierWorkspaceService/Boards context integration while preserving existing order context and General Board staff logic. Never promote a supplier Board into a customer-project Board.
3. Expose scoped JSON through the existing Boards route and the owning supplier_links_api route, with CSRF and no supplier metadata in Customer Center. Add searchable supplier management to existing card and project Board controls; supplier workspace renders linked project boards, card references and native order shortcuts.
4. Verify PHP/JS, relevant supplier/staff regressions, authenticated local positive/negative cases, native draft persistence/readback and native order card GET and synthetic EN/AR desktop/phone UI. No live customer fixture writes. Commit/push each verified increment under Ahmed's sync rule. Remote deployment requires the current scope's release authorization and pinned source/backup/rollback checks.

## Verified implementation — 2026-11-23 Asia/Shanghai

Implemented locally on Dolibarr 24.0.1; PHP 8.3.21. No schema migration, new module, native core edit, feature flag/permission change or financial posting. Customer and supplier Board identities remain distinct. Source card/project links are internal and do not enter Customer Center publication data.

- SQLite: General Board72 + M14246; supplier picker14. The same General Board72/M14246 passed against the isolated lab MySQL database using guarded synthetic tables, which were removed afterward. Native MySQL caught and resolved an outer-alias JOIN compatibility issue absent on SQLite.
- Actual lab native services created a disposable customer/project/card, two native supplier relations, two Boards through BoardsService and one empty CommandeFournisseur draft. Readback retained supplier/project/status0 and retry returned the same order. Native order-card browser view showed the correct project and supplier.
- Signed-in local API14: card/project lists, supplier options, source-card/order projection, unlink/relink persistence and Board reuse; stale write409, denied project403 and missing-token403; card assets in EN/AR. No production customer fixtures modified. The disposable native parties/project/card/Boards/draft were removed after readback using exact identity guards and a transaction; no test records are kept in Git. Native numbering counters were not rewound.
- Browser observed: project Suppliers dialog; searchable bounded supplier chooser; two card supplier links; supplier Board source-card references and draft; EN desktop and AR390px phone layout. A native browser confirmation stalled the UI automation during an unlink click. Browser-confirm completion and keyboard click-through are not accepted; unlink/relink persistence was verified through the authenticated API. Temporary viewport reset was attempted but browser control timed out; the affected local review tab may still show a confirmation.
- Independent read-only review found and prompted fixes for native purchase-create rights, deleted-board gallery scope/relink recovery, and nested native exception rollback. Tests cover those failures. Unrelated work remains preserved.

Deleted supplier Boards are never silently recreated/reused: relinking requires restoring the mapped Board. Card unlink retains the project association/Board/orders. Project unlink is denied while cards use that supplier; existing Board history remains accessible through supplier workspace mappings. Supplier-linked cards cannot move to another project until their card supplier links are explicitly removed.

Status at implementation handoff: source/local verified. Backup/live were then authorized and deployed as recorded below; signed-in remote browser acceptance remains pending. See M142_WORKFLOW_ACCEPTANCE.md for the workflow record.

## Release approval — 2026-11-23 Asia/Shanghai

Ahmed explicitly requested “ok, push to both” after the concrete local/PR66 result. This authorizes this prepared feature on backup-ERP and live, including preflight, scoped source backup, verification and data-preserving rollback. PR66 merged as d9f704f60df4aad2a4af7df0483a5f4e40f9985e, with both GitHub checks passing. Release allowlist is its15 application paths; no production test fixtures, schema, configuration, rights, proxy or service changes. Native supplier relationships block reversion to older visibility code once users create links; preserve records and fix forward in that case. Release controller recovery checks pass5 locally. Actual remote results are recorded separately after execution.

## Release result — 2026-11-23 Asia/Shanghai

Both targets independently verified from the same15-file artifact/source d9f704f60df4aad2a4af7df0483a5f4e40f9985e (PR66). Artifact SHA-256 dec04e32ff9597a8d0a3d7c1e16e0212c52816386f1fa21dab8a7c45c8875ca5. Controller /root/m142-release.py SHA-256 b68cd271a2978213ae89972f638d9e0e03af771b75f38ef7b6a41a0c76310518. Immutable inventory/source/proxy/config/owner guards passed, as did PHP syntax,72 staff+46 supplier-link SQLite tests inside each target runtime, five native project panels, five native card panels, external denial and complete supplier paging (backup1 supplier/1page; live1,643/33pages). Both require anonymous login, and all three changed HTTPS JS/CSS hashes matched per target. An independent Mac fetch rechecked all six served assets. No runtime restart, configuration, rights, schema or persistent business-record writes. No rollback was required.

Scoped originals and absence metadata are preserved per target, with archive hashes and exact resource identities in [sanitized receipts](../evidence/m142-release-2026-11-23.json). Code rollback refuses later source drift and refuses old visibility code after supplier relationships exist; no database restoration. Original backup/live container IDs, images, volumes, proxy paths and settings remained unchanged. Source/helper/evidence remain published to GitHub under Ahmed's sync rule.

Observed limits: signed-in hosted browser click-through and the browser confirmation path remain unaccepted. Native scoped actor tests passed; no real customer was linked to a supplier or used for a draft-order test on either hosted target. The earlier local synthetic draft/native persistence test remains the evidence for those writes.
