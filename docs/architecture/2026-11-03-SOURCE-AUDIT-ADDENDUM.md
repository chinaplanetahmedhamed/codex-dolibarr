# Read-only architecture audit addendum — 2026-11-03

Scope: accessible source and shared evidence only, continuing the existing M121 architecture-audit record. No application implementation, schema access, server command, staging write or production operation was performed.
Project manager: ChatGPT Work. Engineering inspection: Codex. Functional requirements remain subject to the full Master Improvement & Integration Instructions.

## Evidence baselines
- ERP application source: `chinaplanetahmedhamed/codex-dolibarr@317ef5e2eb00729aaa9f3b62427bead7273c25a5`. Governance-only merge `563838275f49ac73fcb468fe2ff0c6af6c0f9ba5` leaves application source unchanged.
- Boards variant: `codex/m107-customer-project-workspaces@113ddc6855915061d14acc7ab61ea33fd31742f9`.
- Office variant: `codex/m119-office-expenses-local@53ac3ca9ed5eccbaf8c879379bcff19c6683b51b`.
- M084 source referenced by M121: `codex/m084-dashboard-repair@310deeaa70ced8d1a74aa84c802e2c705e54b2a1`.
- Shared M121 progress/manual were read in their current form; source audit, runtime and functional acceptance remain distinct.

## Verified source inventory
| Module | Descriptor dependency declarations | Source role / inspection boundary |
| --- | --- | --- |
| chinaplanetcustomercenter | Societe, Commande, Facture, Fournisseur, Banque, Expedition, Projet | Customer-facing projection and native commercial/financial/project dependencies; hooks and triggers declared. Runtime permission behavior not tested. |
| chinaplanetlogistics | Commande, Expedition, Projet | Shipment/logistics module; order/shipping/project hooks declared. Installed state not verified. |
| chinaplanetoperations | Societe, Product, Propale, ChinaPlanetShippingPrices | Operation records, stages, tasks and public/customer shipping handoff. |
| chinaplanetshippingprices | Societe | Shipping-price source and booking service; API declared. |
| cpkanban | Societe | Existing Kanban module; main hook declared, triggers disabled in descriptor. |
| visiontradingui | No dependencies declared | UI hooks main/mainloginpage; not a new business-data owner. |

These are source declarations, not a verified list of enabled runtime modules.

## Observed integration boundary
`CustomerShippingBookingService` consumes `CustomerShippingOperationGatewayInterface`; the concrete gateway lives under Operations and calls its operation service. Operations declares a ShippingPrices dependency. This provides an existing extension seam; do not build a second booking/operation system before reviewing it.
Booking source stores entity/customer/rate/operation identity, idempotency key and request/rate/terms snapshots. Customer read queries bind entity and fk_soc and require customer-visible operations. These are observed source patterns, not proof of complete security acceptance.
`OperationsService` stores customer/project/order/shipment references, assignment, priority, stage, progress, waiting-on, next action, deadline and separate private/public notes. Retain these relationships during architecture decisions.

## Verified source divergence
| Capability | ERP main application baseline | Examined variant |
| --- | --- | --- |
| Operations tasks/boards.php | Absent | Present at Boards `113ddc6...` |
| Standalone chinaplanetofficeexpenses module | Absent | Present at Office `53ac3ca...` |
| M121 architecture/report/evidence files, missions-list.md, resources.md | Absent in inspected tree | Also absent in the inspected M084, Boards and Office trees |

Do not deploy main as if it were a complete snapshot of the owner's current system. Branches are evidence candidates, not interchangeable supersets. Preserve local dirty work and recorded deployments until pinned source/runtime reconciliation.

## Reported by M121 shared records, not newly verified here
- Six source roots and 137 DDL-source definitions inventoried.
- Local canonical checkout contains pre-existing changes and differs from newer Boards/account/Office variants.
- Native Dolibarr payments/bank, CPCC commitment/funding projections and M116 manual cash-book entries are distinct financial representations. M116 Due is reported as negative cash balance, not supplier/invoice debt.
- Current schema/grants/mounted-source parity, staging anonymization and Docs ERP-target isolation remain open. Prior restricted Docker metadata read was denied.
- M121 local deliverables and detailed evidence are referenced in the shared manual but unavailable in inspected published trees.

Do not convert these reported findings into freshly verified runtime facts, and do not invent or duplicate the missing M121 audit.

## Next read-only checks / decisions
1. Obtain the full master instructions and the current M121 report, implementation plan, workflow manual, source-evidence.json, schema-source-inventory.json, missions-list.md and resources.md from their verified local locations.
2. Inventory local source roots, dirty paths and exact commits without reset/cleanup; compare with published variants and recorded release manifests.
3. Obtain authorized non-secret runtime metadata, installed schema/version and permission grants; do not widen Docker/SSH access as a shortcut.
4. Verify staging data anonymization and Docs target isolation before integrations or fixtures can write.
5. Work prepares source-of-truth and financial deduplication decisions for Ahmed. Do not create a fourth cash ledger or silently equate paid/available/due/settled.
6. Record accepted ownership boundaries, exact test environment and evidence before functional implementation. Production release remains separately approved.

Status: SOURCE REVIEW STARTED; runtime baseline and full architecture acceptance BLOCKED/PENDING. No functional tests or live acceptance claimed.
