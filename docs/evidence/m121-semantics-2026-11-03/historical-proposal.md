# Proposed correction boundaries — not applied

No application patch is applied or approved here. The existing actual-query test is a characterization fixture. Change expected outcomes only after the owner/accountant approves a contract.

## Contract required

Decide whether As of means (A) full historical position at the end of a named business day, or (B) today's open invoices issued on/before that date. Current UI suggests historical position, but SQL mixes current state and dated movements. If B is intended, label it explicitly and do not claim historical accounting. If A is intended, specify business timezone; effective payment date versus recording/validation date; reversals; closure/write-off/cancellation; credit creation versus application; original-currency/net/gross treatment; reopened/revalidated invoice history.

## Smallest defensible implementation slice after agreement

1. Introduce one validated cutoff boundary for event DATETIME values, e.g. `[start, next local day)` translated consistently to stored DB time. Use `< next_day_start`, not `<= date-only`, in native receipt and native payment-allocation reads. Add the same upper bound and entity scope inside the unpaid payment aggregate. Preserve validation-setting predicate, invoice/customer/project scope and per-currency values.
2. Keep default/current mode behavior explicitly separated from historical mode. Do not merely remove `paye=0`: `fk_statut=1` still excludes native closed-paid invoices. Historical candidate invoice selection must include invoices settled after the cutoff, exclude invoices not yet validated then and treat closure/write-off/cancellation based on dated evidence. Current date_valid/date_closing alone do not prove arbitrary past lifecycle state. Reopening/revalidation/edits require audit/history or an explicit accuracy limitation; no invented history.
3. Credit changes require separate review. `r.datec < cutoff` blocks future creation, but does not prove application before cutoff. Source/target invoice dates do not supply application time. Use existing audited application events if verified; otherwise expose unresolved historic applications as exceptions or document that full historical credit reconstruction is unsupported. Do not invent timestamps, migrate ambiguous rows, delete links or overwrite financial history.
4. Distinguish direct invoice-payment credits (`fk_facture`) from line discounts (`fk_facture_line`) before changing invoice gross/remaining calculations. Add lifecycle tests and obtain accounting acceptance; no blanket arithmetic formula rewrite.

## Acceptance needed for corrected slice

Execute native-engine synthetic tests for later partial/full payments, status2/paye1 historical selection, closed partial/write-off, validation after cutoff, cancellation/reopening, midday/midnight/end-of-day, timezone/DST, later reversal, direct credits and line discounts created/applied at different times, source/target entities/customers, project/all-project, disabled/enabled validation and per-currency totals. Ensure rows emitted after cutoff are absent and identical event sets drive unpaid/receipt/allocation views. Then authenticated isolated backup-ERP checks and existing-source regressions. No production access is part of this work.
