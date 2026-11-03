# M121 historical evidence design — 2026-11-03

Owner: Ahmed Hamed. Assignment: Work; engineering: Codex. Status: source audit and bounded executable design, not runtime acceptance. The master requirement is an accessible, trustworthy historical statement. The existing transaction-cutoff diagnostic cannot satisfy it.

## What the inspected sources establish

Current invoice, payment, discount and custom-entry rows are current observations. Closed/reopened invoices and changed invoice amounts require retained versions. Payment dates can be edited without a retained financial trigger in the inspected native path. Discount creation is not its application time; current direct and invoice-line links do not retain that time. Neither an old date nor a current unpaid flag reconstructs earlier debt.

Native selected-event signed logs may retain validation snapshots and customer payment create/delete snapshots. A signature proves a retained payload only after native verification. It does not prove historical event coverage, a trusted chain start/end, numeric customer binding, original currency or a full invoice lifecycle. Validation snapshots are successive observations, never additive charges. Supplier event coverage is not assumed from the inspected source. Generic security events and optional mutable agenda entries are corroboration only.

Managed-funds events retain workflow chronology but not a full monetary before/after snapshot. Reservations remain commitments; settlement and native cash identities remain distinct. Guided receipts and supplier workflow markers provide source links for their paths, not a new ledger.

The existing M116 manual-account source retains before/input/result audit, entry versions, immutable posted payloads, reversal entries and allocation batches. This can support bounded manual-source reconstruction if the complete retained stream and its scope, ordering and versions are proved. Its existing latest-200 audit response cannot prove complete history. That source remains outside canonical application parity and is not imported here.

## Bounded implementation direction

Reuse Customer Center read projections and existing source-owner services. No new ledger, module, table, migration, native-core change, financial posting or public endpoint is proposed by this slice. A future internal per-source adapter must verify authorized history access and produce retained observations plus coverage and provenance evidence. The reader must default to unsupported when an adapter or required proof is absent.

Keep recorded time separate from business-effective time. An as-recorded view includes only evidence known by the requested recording boundary; a backdated later posting cannot appear as knowledge available earlier. A business-effective restatement is a different explicit policy and requires all relevant versions. Calendar/zone provenance and stable tie ordering must be verified before comparing timestamps.

For each source and metric, return status and missing evidence. A value is null when historical scope, identity, currency, retained versions, continuity, start/end or coverage are unproved. An empty interval is zero only with positively verified coverage. Do not fill gaps with current totals, drop currently closed invoices, infer missing application times, match records by amount/date, or add independent representations of the same cash fact.

Verified native payment create/delete history can at most support an explicitly named, per-currency as-recorded payment-event window change until further lifecycle and opening evidence exists. It does not establish customer cash balance or historical unpaid invoices. Manual-source replay remains separately labelled manual; no sum with native cash is permitted. Complete historical receivables stays unsupported until invoice versions, payment/deletion/date history, credit applications and lifecycle coverage are established.

## Required adapter evidence

- Historical entity/customer/project authorization, retained stable identity bindings and permitted payload fields.
- Exact source/build and payload format; verified integrity and trusted chain/window anchors where applicable.
- Complete authorized pagination, known universe and version continuity; explicit handling of deleted sources, truncation, conflicts and logging gaps.
- Original currency and exact decimal provenance, with no conversion from a current configuration or a float assumption.
- Separate recorded/effective times, verified time basis and supported mutation paths. Current settings do not prove past logging.
- Source-specific signs, conservation, nonduplicate identities and immutable opening evidence if a closing balance is requested.

These are evidence requirements for a future trusted server-side adapter. They are not user-submitted complete=true flags. Synthetic tests can state and exercise their assumptions; they cannot certify a real installation.

## Validation and remaining work

The source audit covers eleven source categories and retains local source hashes/function anchors. A test-only replay reference uses synthetic retained M116 audit shapes to exercise the design; its final results are recorded in the companion source report and evidence manifest. It is not an installed backend or UI.

The corrected cutoff fixture now uses canonical custom-entry DATETIME precision and retains all inserted timestamps: 122 assertions pass on a new local MySQL5.7.39 instance, including twelve additional intraday/boundary assertions. The previous110-check DATE fixture remains historical evidence with its limitation disclosed. Generated financial queries were unmodified. The owned instance stopped and its socket/PID disappeared. This does not prove target MariaDB/schema parity or historical completeness.

Next implementation gate: reconcile the existing manual/native evidence adapters and canonical source ownership; establish genuinely retained coverage and identity/currency proof on permitted anonymized staging; implement the authorized read surface with explicit partial/unsupported states; then test historical closed/paid, later partial payment, later credit application, backdating, reversal, gaps, permissions and AR/EN desktop/phone behavior. Staging metadata access, isolation and recovery verification remain blocked. No production access, deployment or database change occurred.
