# M099 existing staff-document registration size limit

Production read-only diagnostics established 37 existing shipment files, including two valid XLSX files of 4,069,994 bytes rejected solely by the customer upload limit. The first deployment restored all 20 original hashes, preserved backups and left both flags off.

Registration now passes the existing 200 MiB staff limit explicitly to file metadata validation. Default metadata calls and customer upload limits are unchanged. MIME, filename, ownership, entity, path and fingerprint checks remain enforced. No files are copied, skipped, merged, deleted or published by this correction.

Local shared-document acceptance: 78 assertions passed, including a 4 MB existing document above a configured customer limit, idempotent identity/version, unchanged bytes, readable content, no publication, MIME mismatch rejection and staff-limit rejection. My Work 16, CSRF client 15, staging-CSRF helper 8 scenarios passed. PHP lint and diff check passed.

R2 helpers validate legacy documents using the staff limit before writing code, report numeric exit codes without exposing stderr or credentials, and use separate R2 state/backup directories. R1 backups remain intact. Staging and production R2 execution and browser acceptance are pending.
