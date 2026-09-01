# M061-R02-C03 — Mobile API baseline correction

## Authority

- Approved by Ahmed Hamed on 2026-08-25: `prepare and deploy M061-R02-C03 baseline correction on backup-erp only, including all gates; no production or real email.`
- Target: canonical `backup-erp` only.
- Production and real outbound email are prohibited.
- Authorized sequence: local correction and tests, upload, checksum-pinned helper installation, independent verification, `compare`, `preflight`, `apply`, `verify`, `status`, and bounded no-send runtime acceptance.
- Stop fail closed on the first identity, checksum, archive, candidate, baseline, environment, backup, restore, schema, runtime, ownership, or acceptance mismatch.

## Verified correction basis

M061-R02-C02 passed its dependency-closed candidate tests but reported one runtime drift. The deployed `backup-erp` runtime does not contain `custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php`, while the R02 baseline manifest incorrectly records source SHA-256 `507dec6ff26b7d3886cd60b2a02202a1e206010f0996bca2634613b2cf3bafd8`.

Read-only M061-R01 verification still passes while that file is absent. Therefore the prior baseline entry describes the M061-R01 source commit, not its exact deployed runtime scope. The intended R02 release adds the missing API class and is the correction for the verified staging HTTP 501 Mobile API failure.

## Exact local change

- Change only the baseline value for `custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php` from the false source hash to `ABSENT`.
- Keep the runtime commit `487b61e690d7d030ec25d54a3b62afdb7e78fb55` unchanged.
- Keep the exact 19-file runtime manifest unchanged.
- Keep the exact 17-file dependency-closed support manifest unchanged.
- Keep the additive migration and customer behavior unchanged.
- Rebuild the 39-member deterministic metadata-free artifact and refresh every dependent artifact, helper, installer, builder and test pin.
- Permit the installer to replace only the exact installed C02 helper SHA-256 `1eb6b15bafa3cd358293ec50d00283a572fbb8b041e11f06e82670e6433da01f`.
- Preserve the exact six-command sudo surface: `compare`, `preflight`, `apply`, `verify`, `rollback`, `status`.

## Apply and acceptance boundaries

- Apply may begin only after zero-drift compare and passing preflight.
- Before runtime or schema mutation, apply must create and hash an exact code backup and full database backup, then pass database restore rehearsal.
- Any failed post-mutation check must use the helper's automatic recovery path and stop.
- Acceptance may inspect formatted email previews but must not submit a send action.
- Production resources, legacy staging resources, Compose, proxy, DNS, documents, unrelated modules and business records are outside scope.

## Completion evidence

Recorded in `docs/REPORTS/M061_R02_C03_COMPLETION_REPORT.md`.

## Final result — 2026-08-25

- Local build, upload, checksum-pinned helper installation, independent verification, `compare`, `preflight`, `apply`, `verify` and `status` passed on canonical `backup-erp`.
- Durable helper state is `DEPLOYED` for runtime commit `487b61e690d7d030ec25d54a3b62afdb7e78fb55` and artifact SHA-256 `bf508b9e7b495092542160e4bb97ce1410c2013497d90bc6af70ff9a3498085f`.
- Bounded Customer Center staff acceptance passed the financial summary, invoice-detail, unified document-filter and formatted no-send email-preview surfaces against QA data.
- Acceptance verdict is `CORRECTION_REQUIRED`: the Mobile API include failure changed from HTTP 501 to HTTP 500 instead of the required safe unauthenticated response. Payment-note, positive linked-document and external ownership acceptance also lack staging fixtures/session evidence.
- No real email was sent and Mailpit remained empty. Production was not accessed. Rollback remains available and was not invoked.
