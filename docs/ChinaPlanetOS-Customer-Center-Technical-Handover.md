# ChinaPlanetOS Customer Center — Technical Handover

## Scope delivered

The existing `chinaplanetcustomercenter` module was extended in place. No second portal was created.

Delivered layers:

- Customer wallet ledger with original-currency balances.
- Supplier commitments and allocated payments.
- Cross-currency supplier settlement.
- Funding-required formula per currency.
- Native Dolibarr customer service invoices and payments.
- Native supplier orders, invoices, and payments.
- Project lifecycle Operations Center.
- Project financial view.
- Customer-safe orders, projects, invoices, shipments, documents, and export documents.
- Commercial Invoice and Packing List records.
- Customer dashboard cards and USD-equivalent total balance.
- Idempotent China Gate agency scenarios.
- Automated finance, integration, and scope tests.

## Architecture decision

Native Dolibarr objects remain the accounting and operational source for:

- `societe`: customer/supplier third parties.
- `projet`: managed customer order/project.
- `commande`: customer service order.
- `facture`: official Vision service invoice.
- `paiement` and `paiement_facture`: invoice settlements.
- `commande_fournisseur`: supplier purchase order.
- `facture_fourn`: supplier invoice.
- `paiementfourn`: native supplier allocation.
- `chinaplanetlogistics`: shipment operations.

Custom tables cover concepts not modeled safely by standard Dolibarr:

- `llx_cpcc_financial_entry`: customer wallet ledger and cross-currency allocation.
- `llx_cpcc_project_charge`: approved supplier/service commitment.
- `llx_cpcc_export_document`: customer-facing export document header.
- `llx_cpcc_export_document_line`: export-document lines.

## Financial rules

- `customer_receipt` + Credit increases wallet balance.
- `supplier_payment` + Debit decreases the actual payment currency.
- `allocated_amount/allocated_currency` reduces the linked supplier commitment in its currency.
- `invoice_allocation` + Debit consumes wallet funds for a Vision invoice.
- A native invoice payment linked by `source_type=payment` is suppressed from native receipt aggregation to avoid double counting.
- CNY is normalized to the customer label RMB.
- Balances are never summed without explicit USD conversion rates.

## Main files

- `custom/chinaplanetcustomercenter/board.php`
- `custom/chinaplanetcustomercenter/funding.php`
- `custom/chinaplanetcustomercenter/balance.php`
- `custom/chinaplanetcustomercenter/project.php`
- `custom/chinaplanetcustomercenter/invoices.php`
- `custom/chinaplanetcustomercenter/invoice.php`
- `custom/chinaplanetcustomercenter/commercial_documents.php`
- `custom/chinaplanetcustomercenter/export_document.php`
- `custom/chinaplanetcustomercenter/admin/operations.php`
- `custom/chinaplanetcustomercenter/admin/financial.php`
- `custom/chinaplanetcustomercenter/admin/export_document.php`
- `custom/chinaplanetcustomercenter/lib/financial.lib.php`
- `custom/chinaplanetcustomercenter/lib/operations.lib.php`
- `custom/chinaplanetcustomercenter/lib/export.lib.php`

## Local seed and module registration

Idempotent seed:

```text
deploy/tools/seed_china_gate_agency.php
```

Module menu/schema reload:

```text
deploy/tools/reload_customer_center_module.php
```

The seed can be rerun. It uses stable markers and does not create duplicate scenarios.

## Test suite

Pure financial tests:

```text
deploy/tests/test_customer_financial_multicurrency.php
```

China Gate native/custom integration:

```text
deploy/tests/test_china_gate_agency_integration.php
```

Customer isolation/security scope:

```text
deploy/tests/test_customer_portal_scope_integration.php
```

Free-time automation:

```text
deploy/tests/test_freetime_automation.php
```

## Verified security properties

- External customer scope comes from `user.fk_soc`, not the query string.
- A tampered `socid=2` remains scoped to China Gate for the China Gate portal user.
- Project, order, invoice, shipment, file, and export-document fetches validate customer ownership.
- The China Gate commercial invoice cannot be fetched using another customer's scope.
- The China Gate service invoice is absent from another customer's invoice collection.
- Admin write forms use CSRF tokens.
- Shipment downloads use allowlisted roots and ownership checks.

## Backup and rollback

Pre-upgrade backup:

`/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/backups/customer-center/20260714-before-full-erp-build/`

It contains:

- Customer Center module files.
- Logistics module files.
- Compressed local database dump.

Rollback procedure:

1. Stop MAMP web traffic.
2. Back up the current failed state for investigation.
3. Restore the module directories from the backup.
4. Restore `database.sql.gz` into the local development database.
5. Clear Dolibarr caches and restart the local PHP/MySQL services.
6. Re-run the smoke tests before allowing user access.

## Production checklist

- Replace local training references and credentials.
- Confirm company logo and legal shipper data.
- Confirm all supported currencies and rates.
- Confirm Dolibarr entity scope when multi-company is enabled.
- Review user rights after module enable/reload.
- Test with a copy of production data, not production itself.
- Back up code, documents, and database.
- Deploy during a maintenance window.
- Run integration and security tests.
- Verify one internal admin and one external customer session.
- Confirm no demo customer/supplier/project is deployed unintentionally.
- Do not push or deploy until the owner explicitly approves the tested local result.
