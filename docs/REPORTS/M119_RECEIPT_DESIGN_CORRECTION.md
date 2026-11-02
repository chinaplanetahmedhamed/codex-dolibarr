# M119 receipt register and A4 correction

Status: LOCAL_VERIFIED; backup-ERP deployment pending. Scope is Ahmed's original-design correction, separate from the pending actual-payment policy.

The receipt register follows the observed Finens reference: branch branding banner, three summary cards, date/search/status filters, nine-column register and receipt editor dialog. The printed voucher has three copies (customer, accounts, archive), cut lines, branch logo, permanent reference/date, original amount/currency and amount words, payer/purpose/notes, signatures and a locally generated QR. QR opens the existing authenticated branch-scoped record; no public receipt capability or third-party QR request is introduced.

Existing IDs, records, audit, permissions, own/branch isolation, money and draft/archived semantics are retained. Printing does not post cash. Receipt branding is projected from existing authorized branch settings; the narrow read does not expose native account IDs. Save-and-preview reuses existing CSRF/idempotency/revision checks. No schema, role, credential, financial balance or production change.

Local checks: changed PHP syntax, JavaScript syntax, whitespace; 31 native component cases pass against official Dolibarr24.0.1 source using isolated synthetic SQLite. AR/EN register DOM checks at actual1778px and544px have nine columns, no page overflow and no unlabeled input. Local dialog click/close was observed in an earlier single case; a later browser click timed out on visibility, so physical-device acceptance is not claimed. Source detector found no regex issues; parsed accessibility/contrast detection is separate.

Actual Chrome PDF export and PDFKit visual inspection: normal AR and EN receipts each have three copies on one A4 page. Long notes expand to three pages and the end-of-text marker appears three times, preserving all copies without clipping. All PDF data is synthetic and kept outside Git. Extremely long receipts intentionally grow beyond one sheet.

Admin ERP: implemented locally. Customer Center: not applicable, no customer exposure. Native mobile app: not applicable; responsive web only. Staff credential login and final deployed browser acceptance are separate gates.

Rollback: exact six runtime source files to the R04 predecessor, retaining all records and native cash. Delivery must validate the complete59-file dependency closure and keep the five fixed helper actions unchanged. No database restore is appropriate for this code-only correction.
