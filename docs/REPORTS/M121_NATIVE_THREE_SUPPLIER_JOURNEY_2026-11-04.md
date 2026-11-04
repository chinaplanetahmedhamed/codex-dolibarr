# M121 version two — actual native three-supplier journey

Recorded 2026-11-04T00:45:28.293571+00:00. Codex is the sole writer in the existing isolated m121-finance-office-integration worktree. Parent Work owns shared Drive/manual/handoff consolidation and coordination with the release owner. No remote access, installed ERP access, target deployment, real records or production was used. Version-one commit dc48bd8adc304947242c2899ec04faca0343c243 and its archive remain preserved.

## Actual results

Fresh socket-only MySQL5.7.39, PHP8.3.21 and unmodified Dolibarr24.0.1 core; the runner verifies socket/datadir/database/networking/empty database before writes, uses empty private configuration, and stops only its owned process. Final run **PASS:140 checks over29 journey steps**, retained38 native CFS lifecycle +17 native snapshot cases and9 baseline cash assertions. Owned exit0, socket/PID absent. Evidence: outputs/M121/local-assembly-2026-11-03/native-journey-v2-final/.

| Checkpoint | Actual available service calls | Observed |
|---|---|---|
| Customer, three suppliers, project | Societe.create/fetch and Project.create/setValid/fetch | Persisted native entities and customer/project ownership; unique synthetic codes |
| Customer order | Commande.create/addline/valid/fetch | Three distinct quantity1/VAT0 lines400+350+250=1000USD |
| Deposit and receipt | NativeAdvanceWorkflowService.createDepositFromOrigin/postBankReceipt, actual Facture/Paiement/bank methods, CFS and managed-credit guard triggers | Fixture term100% creates1000 deposit/receipt; identical request retries retain invoice/payment/receipt identities |
| Three supplier commitments | Three OperationsService.create operations with actual generated InternalTaskService tasks; supplier owner createOrder/validateOrder/createInvoice/validateInvoice | Actual orders/invoices300,250,200USD. Each uses a different customer line. One supplier chain per operation; no duplicated custom commitment ledger |
| Three payments | OperationSupplierWorkflowService.postPayment with actual PaiementFourn/bank and CFS attribution |200,150,100 paid;100 remaining on each invoice. Overpayment refused; same-key retries preserve identities |
| Customer/project projection before fault probes | Actual CustomerFundsService.currentPosition on native sources | Customer received1000,paid450,reserved50,held550,available500USD. Project paid450/reserved50 without duplicating the customer cash pool |
| Independent cash | Separate PDO durable reads of native payment, invoice allocation, bank and owning marker rows |1 customer payment,+1000;3 supplier payments,-200/-150/-100;4 distinct bank rows; signed net550.0 duplicate cpcc_financial_entry / cpcc_project_charge rows |
| Shipment | Fourth actual shipping_booking operation; OperationShipmentService.createLinkedDraft and native shipment reload/context | One persisted customer/project/order-linked draft with audit. Retry returns same shipment; payment never confirms booking/movement |
| Private documents | Actual ShipmentDocumentService DB locks, version/revision, metadata, private filesystem and content reads | Version2 retains old bytes; stale revision rejected; one document/two versions, mode0640. CLI source validation/move are explicit substituted seams; input is a tiny synthetic PNG, not an editor-generated invoice PDF |
| Roles | Real User.fetch/hasRight and actual owner authorization with synthetic persisted identities/rights | Own-customer read allowed; foreign customer, other entity, missing rights, reader write and external internal-Docs access denied. Authorized nonadmin writer56 executes fault/recovery payments; internal Documents workspace requires workspace_read |

## Reproduced defects and narrow corrections

The supplier owner service returned payment success after actual transport-injected failed BEGIN. RED evidence: native-journey-v2-owner-ack-red3/native-journey.json. Guided receipt retry likewise returned success after failed BEGIN: native-journey-v2-supplier-green-guided-red/native-journey.json. Neither is an inferred failure.

OperationSupplierWorkflowService now checks all6 BEGIN and9 retry/final COMMIT sites through two private helpers. NativeAdvanceWorkflowService checks all5 BEGIN and5 COMMIT sites. Started flags change only after acknowledged begin/commit; failure throws, existing cleanup/locks/authorization remain ordered, and native nested transaction counters remain unchanged. No amount, source identity, currency, permission or financial rule changed. Read-only source review found no concrete defect and mechanically reconstructed the prior HEAD by reversing only these replacements/helpers.

Actual native fresh supplier payments run BEGIN-before and COMMIT-before/after acknowledgement probes. Refusal, independently observed durable state and same-key recovery pass; after-commit lost acknowledgement retains its original payment, and retry settles that allocation without recreating cash. These use an actual native driver with a narrowly substituted mysqli acknowledgement seam; they are not network loss. Actual wire-loss testing remains in the retained55-case native suite.

Three explicit1USD supplier fault/recovery payments run after the main journey, using nonadmin writer56. Final live projection is **received1000,paid453,reserved50,held547,available497**, with native supplier outstanding297 and6 total supplier payment rows (3 primary+3 explicit probes). The earlier450 checkpoint must not be presented as the post-probe state. Guided receipt retry faults BEGIN-before/COMMIT-before/after recreate no receipt cash and preserve original identities. Fresh guided receipt failure transitions beyond the checked happy path/retry matrix remain a separate acceptance boundary.

## Source closure and verification

Four missing existing schema source files were restored byte-for-byte from reviewed Boards55a9b5204e3ccbb3207d3b02c815cab5c3dcf18a (also identical at deployed7bb0686): Logistics llx_cpl_shared_document.sql (includes cpo_task_context), Operations llx_cpo_account.sql, llx_cpo_boards.sql and llx_cpo_task_details.sql. They were omitted source definitions, not new schema designs. Only required definitions/migration were executed in the previously guarded empty fixture; no target module/schema/right action occurred. The separate manual account ledger remains separate from native customer cash.

Final24 focused commands PASS after owner changes (v2-post-owner-workflow-regression.json), including actual SQLite financial integration and alias, snapshot154, finance/Office boundary, Office25local lifecycle, private upload/concurrency, entry31, role/context and retired503 AR/EN tests. The two changed runtime PHP files, scenario and journey lint clean; retained runtime source236 lint/reference evidence applies to otherwise unchanged files. Earlier182 paired editor tests remain recorded; editor source/history is unchanged. Do not sum overlapping test counts into a unique total.

Earlier fixture attempts are retained, including explicit rights/key/type/stage configuration corrections. native-journey-v2-owner-ack-red/ is **INVALID and excluded**: a test assembly error printed PHP text instead of running it. The runner now requires executable PHP and structured passed journey evidence, independently checked after process completion. That run proves no journey behavior. Its server stopped; version-one/earlier valid88-check evidence was preserved.

## Remaining gates and incomplete paths

- This is CLI/service acceptance with synthetic configuration/rights, not native authenticated browser/session/CSRF/Admin/Customer/mobile acceptance. Native AR/EN desktop/phone, keyboard/touch and the existing internal-English presentation policy remain separate.
- Supplier UI creates a fresh request key on render and has no explicit same-token recovery control for an already-confirmed partial payment without outstanding allocation settlement. Service same-key recovery is proved; browser retry continuity is not. No UI fix is claimed.
- Target MariaDB/PHP/core/schema/config/flags/rights/storage identity and authenticated acceptance remain unverified; MySQL does not prove target compatibility. Existing installed CSS/compatibility links and Office finance settings must be preserved. No blanket module copy or stale DB restore.
- Backup apply remains **pending/not attempted**. Prior Docker denial is not bypassed. Restricted R13-only helper cannot establish fresh isolation, current scoped backup, actual MariaDB restore proof or M121 apply. The release owner needs supported staging-only administrative evidence and a reviewed exact M121 delta/apply route.
- Complete historical credit/lifecycle reporting, foreign currency/partial source-line splitting, Office native five-case CNY/mappings/reversal/approval identities, native editor PDF creation/saved-draft integration and global shared publication/version lifecycle remain unproved or incomplete. No manual/native ledgers or currencies were merged; no flags enabled on an installed ERP.
- Parent must consolidate sanitized workflow/progress into shared tracker/manual and coordinate the new immutable candidate. No source publication or deployment is implied by local packaging.

Primary version-one gap report remains docs/REPORTS/M121_IMMUTABLE_CANDIDATE_GAPS_2026-11-04.md; this report supersedes its pending local full owning-flow and missing schema observations only to the tested extent above.
