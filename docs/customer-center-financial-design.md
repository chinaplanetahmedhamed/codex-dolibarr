# ChinaPlanet Customer Center — Financial Design

## Native Dolibarr data retained

- Customers and portal identity: `societe` and external `user.fk_soc`.
- Projects: `projet.fk_soc`.
- Customer invoices and receipts: `facture`, `paiement`, and `paiement_facture`.
- Customer orders: `commande`.
- Supplier orders/invoices/payments: native supplier objects linked to the project.
- Shipments and documents: the existing ChinaPlanet Logistics module.

## Why two small custom tables are required

Dolibarr records company invoices and bank transactions, but it does not natively provide an auditable wallet for money held and spent on behalf of each customer. Supplier invoices belong to the supplier, not to the customer who funded them.

- `cpcc_financial_entry` records customer-visible credits, debits, allocations, and paired FX movements.
- `cpcc_project_charge` records the customer-approved project commitment and its currency.

Raw supplier cost, internal margin, private notes, bank account numbers, and staff-only documents are never read directly into the customer portal.

## Multi-currency rules

1. Every amount keeps its original currency.
2. USD, RMB, and EGP balances are calculated separately.
3. An FX conversion creates two entries with one `transfer_group`: debit the source currency and credit the destination currency.
4. The exchange rate is stored on both sides.
5. A supplier payment can have an actual payment currency and an allocated amount in the commitment currency.
6. No combined reporting total is shown unless a dated reporting exchange rate is explicitly recorded.

## Balance definitions

- Total received: customer receipts and native validated Dolibarr customer payments; excludes FX-in.
- Total spent: customer-approved supplier/service payments; excludes FX-out.
- Available balance: all posted credits minus all posted debits, calculated per currency.
- Remaining commitments: approved project charge minus allocated posted payments in the charge currency.

## Portal security

- External users always use `user.fk_soc`; URL customer IDs are ignored.
- Project access requires `projet.fk_soc = user.fk_soc`.
- Financial rows require the same `fk_soc`, `status = 1`, and `customer_visible = 1`.
- Supplier labels are explicitly customer-safe text; internal supplier records are not exposed automatically.

## Spreadsheet reference cases

- China Gate: received, spent, available cash, total project costs, and outstanding commitments.
- Vision Trading: separate EGP, USD, and RMB receipts/outflows with explicit conversion.
- Kareem: USD receipt converted to RMB at an explicit rate, then RMB supplier/service spending.
