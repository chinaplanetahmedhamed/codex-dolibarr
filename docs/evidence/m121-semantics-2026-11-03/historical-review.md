# M121 actual-query historical-as-of investigation

Date: 2026-11-03. Source only; no canonical edits, network/server access, runtime database, private configuration, application records or Git refs. Root owns canonical scope. These findings are existing behavior in newest canonical library, not PR59 regressions.

## Method and reproducibility

Run `php -l /tmp/m121-finance-asof/test_actual_sql.php` and `php /tmp/m121-finance-asof/test_actual_sql.php`. Actual canonical `cpcc_financial_unpaid_invoices()` and `cpcc_financial_native_allocations()` functions generate SQL which is executed unchanged through PDO against SQLite `:memory:`. There are no fabricated result rows and no query rewriting. The adapter only implements query, escape and fetch_object. Each case starts with fresh synthetic tables and fixtures. Queries and returned rows are saved in actual-sql-results.json.

PHP 8.3.21, SQLite 3.54.1. Both commands succeeded; 24 characterization assertions passed. PASS means source behavior was reproduced, not financially accepted. Synthetic schema is a minimal subset of native Dolibarr24.0.1 DDL; only referenced columns are instantiated. This is not an installed-schema check or full native-object workflow test.

SQLite uses ISO-date TEXT comparisons in this fixture. Therefore the exact-midnight datetime case is explicitly a portability limitation: it must not be treated as MySQL midnight evidence. The cutoff-day noon case remains a clear source inconsistency: the unpaid aggregate includes the payment without a date predicate, while allocation SQL has `p.datep <= '2026-11-03'`. A date-only control is included. MySQL/MariaDB timezone, precision, SQL-mode and DATETIME boundary behavior require isolated native-engine testing.

## Correction to earlier query-double probe

The previous paid100 query double supplied an invoice row despite the generated `f.fk_statut=1 AND f.paye=0` predicate. That fixture did not represent a native paid invoice (normally status2/paye1). Do not use its required-funding100 output as actual-SQL proof. Actual SQL on status2/paye1, payment after cutoff, returns no unpaid invoice and no allocation row. This investigation supersedes that historical fully-paid characterization. The earlier double remains useful only as a narrowly stated hypothetical statement-arithmetic probe, not a faithful native lifecycle test.

## Findings

1. Later partial payment: invoice100 datedOct1, validatedOct1; payment25 datedNov5; cutoffNov3 => unpaid75, allocation0. The unpaid aggregation lacks payment date filtering. An invoice still unpaid100 at cutoff is reduced by future money.
2. Fully paid today: the same invoice closed/paidNov5 has current status2/paye1 => no row at cutoffNov3. Both current `fk_statut=1` and `paye=0` discard historical obligations before settlement. Testing the paye flag and closed-partial status separately confirms each predicate executes.
3. Cutoff-day noon payment: unpaid75 but allocation0. Related native receipt SQL uses the same date-only upper bound. An inclusive business-date contract must specify timezone and an exclusive next-day bound for DATETIME events consistently.
4. Later credit: native-credit allocation filters target invoice date, not credit creation/application date. A credit25 createdNov5 against an Oct1 invoice produces a debit25 at cutoffNov3. Its emitted row date is Nov5 even though cutoff is Nov3. The aggregate also consumes current credit links without a cutoff.
5. Direct credit versus invoice-line discount differ. Direct credit25 linked via fk_facture to invoice total100 => output total125, paid25, remaining100. Line discount25 linked via fk_facture_line, current invoice net75 => output total100, paid25, remaining75. The single add-credit-to-gross formula conflates native payment-credit and line-discount meanings. This predates PR59. Owner/accountant agreement is required before changing that formula.
6. Credit creation is not credit application. Native discount schema has `datec` (creation), current target links and no application timestamp. `DiscountAbsolute::link_to_invoice()` updates only target FK, not datec. An older credit createdOct20 and appliedNov5 has the same stored shape as one appliedOct20. This fixture labels the scenario explicitly; the later link date is not represented in stored columns. A datec-only patch cannot solve it honestly.
7. Current validation/cancellation also differ from historical state: an invoice datedOct1 but validatedNov5 is included at cutoffNov3; a current abandoned invoice closedNov5 is excluded. `date_valid`/`date_closing` support some reconstruction but are only current lifecycle fields, not complete history across reopening, edits or revalidation.
8. Current draft/entity/customer/project/type scope is retained by executed SQL. An intentionally inconsistent cross-entity payment-to-invoice link affects the unpaid aggregate (75) while allocation SQL excludes that payment (0), because the former lacks ppay.entity restriction. This is an integrity-hardening gap, not evidence native workflows can create such links or a demonstrated access leak.
9. Payment-validation setting is honored: pending status0 is excluded when validation enabled and included when disabled.

## Actual observations

All cases use entity2/customer7/project11, invoice100 USD datedOct1, cutoffNov3 unless named otherwise. Credit-note source invoices use native type2 and are excluded from target open-invoice results.

| Synthetic case | Unpaid remaining | Allocation debit |
| --- | ---: | ---: |
| unpaid_at_cutoff_baseline | 100 | 0 |
| later_partial_payment | 75 | 0 |
| fully_paid_today_closed_after_cutoff | 0 | 0 |
| paye_filter_independent | 0 | 0 |
| status_filter_closed_partial_after_cutoff | 0 | 0 |
| payment_before_cutoff | 75 | 25 |
| payment_cutoff_day_noon | 75 | 0 |
| payment_cutoff_day_midnight_SQLITE_TEXT_LIMIT | 75 | 0 |
| payment_cutoff_date_only_SQLITE_CONTROL | 75 | 25 |
| credit_direct_created_after_cutoff | 100 | 25 |
| credit_line_created_after_cutoff | 75 | 25 |
| credit_created_before_but_linked_after_cutoff_UNRECORDED_LINK_DATE | 100 | 25 |
| invoice_date_cutoff_inclusive | 100 | 0 |
| invoice_dated_after_cutoff | 0 | 0 |
| invoice_validated_after_cutoff | 100 | 0 |
| draft_excluded | 0 | 0 |
| cancelled_after_cutoff_excluded | 0 | 0 |
| foreign_entity_invoice_excluded | 0 | 0 |
| foreign_customer_invoice_excluded | 0 | 0 |
| other_project_invoice_excluded | 0 | 0 |
| other_project_allprojects_included | 100 | 0 |
| foreign_entity_payment_aggregate_scope_gap | 75 | 0 |
| payment_pending_validation_excluded | 100 | 0 |
| payment_pending_without_validation_included | 75 | 25 |

## Native source evidence

The inspected version is local Dolibarr24.0.1 source, not proven deployed source. DDL defines facture.datef/date_valid as DATE, date_closing/payment.datep/discount.datec as DATETIME; invoice paye is deprecated and status2 can represent paid or partial closure. Facture::setPaid() sets status2 and normally paye1, records date_closing; setCanceled() sets status3/date_closing. DiscountAbsolute::link_to_invoice() records only current links. File paths and SHA256s are in manifest.json.

## Minimum bounded proposal and gate

See proposal.md. A safe narrow first slice can align direct payment evidence with a chosen cutoff and retain current-mode semantics. It cannot honestly be advertised as historical reconstruction until invoice lifecycle and credit application history are solved. Do not rewrite financial arithmetic or silently reinterpret existing records. P1 runtime, current baseline, owner/session scope and authenticated backup-ERP acceptance gates remain pending.
