# M053 custom-module dependency map — source baseline

## Scope and evidence

- **Status:** Source-only, 2026-08-13.
- **Baseline:** `origin/main` merge `8c3595cc84e6f87784b328bcf44e40ebe5e5c624`.
- **Modules inspected:** `chinaplanetcustomercenter`, `chinaplanetlogistics`, `chinaplanetoperations`, `chinaplanetshippingprices`, `cpkanban`, and `visiontradingui`.
- The map is based on module descriptors plus explicit cross-module PHP includes. It does not claim that every runtime hook or database relationship is represented.

## Declared descriptor dependencies

| Module | Declared ChinaPlanetOS dependency |
|---|---|
| ChinaPlanet Customer Center | None; depends on standard Dolibarr customer/order/invoice/supplier/bank/shipment/project modules |
| ChinaPlanet Logistics | None; depends on standard order/shipment/project modules |
| ChinaPlanet Operations | `modChinaPlanetShippingPrices` |
| ChinaPlanet Shipping Prices | None; depends on `modSociete` |
| CP Kanban | None; depends on `modSociete` |
| Vision Trading UI | None |

## Observed cross-module source edges

```text
Operations ───────────────→ Shipping Prices
   public website intake       public-rate token/snapshot service

Shipping Prices ──────────→ Operations
   customer direct booking     operation creation + immutable section

Customer Center ──────────→ Operations, CP Kanban
Logistics ────────────────→ Customer Center, Operations
CP Kanban ────────────────→ Customer Center, Logistics
Shipping Prices UI ───────→ Customer Center shared admin/portal shell
Operations UI ────────────→ Customer Center shared admin/portal shell
```

## First cycle selected

The first bounded change removes the hard `CustomerShippingBookingService -> OperationsService` service dependency. Shipping Prices owns the narrow gateway contract; Operations implements it; the existing page acts as the composition point.

This preserves the already-declared architecture direction `Operations -> Shipping Prices` used by public website shipping intake. It does **not** claim that the modules are fully independent: the customer-booking entry point still composes both modules, and Customer Center remains shared UI/security infrastructure.

## Follow-up order

1. Verify the M053 gateway slice with source and local runtime tests.
2. Decide whether cross-module composition belongs in Operations or a dedicated integration layer; this requires a separate Mission because routes/module state may change.
3. Characterize Customer Center shared shell/security functions before extraction.
4. Decompose large Customer Center and Shipping Prices services only behind regression tests.
5. Rationalize historical deployment helpers last, preserving immutable manifests, backup, verification, and rollback contracts.
