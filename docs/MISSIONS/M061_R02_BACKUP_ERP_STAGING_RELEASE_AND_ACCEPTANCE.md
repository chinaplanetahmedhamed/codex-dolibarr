# M061-R02 — Backup ERP staging release and acceptance

## Mission control

- Type: staging release and authenticated acceptance
- Status: CORRECTION_REQUIRED — C03 is deployed on staging, but Mobile API runtime acceptance returns HTTP 500
- Owner: Agent 007
- Approved by Ahmed Hamed: `OK， send to backup-erp , so we can test it well`
- Approval date: 2026-08-24 Asia/Shanghai
- Exact target: canonical `backup-erp` only (`erp-staging` stack)
- Production: prohibited
- Real customer email: prohibited during deployment and synthetic acceptance

## Objective

Layer the locally accepted M061 Customer Center financial, invoice, payment-note, linked-document and formatted section-email improvements on the already deployed M061-R01-C01 trust/operational baseline, restore the missing Mobile API runtime route, and provide representative staging data/session evidence for thorough acceptance.

## Frozen runtime

- Runtime commit: `487b61e690d7d030ec25d54a3b62afdb7e78fb55`
- Deployed baseline: `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`
- Runtime files: 19 exact Customer Center/Logistics files
- Additive schema: customer-document publication registry, `paiement.cpcc_public_note` extra field, and disabled-by-default Customer Center right `500206 email_write`
- Customer/mobile ownership remains derived from authenticated entity and customer scope.

## Release gates

1. Local package and helper review: no server mutation.
2. Upload artifact/helper/installer/sudoers and install the exact restricted helper: no deploy command.
3. `compare` then `preflight`: read-only runtime/schema inspection; stop on drift.
4. `apply` then independent `verify`: exact code plus additive schema, after code and full database backups with restore rehearsal.
5. Authenticated staging acceptance: staff and synthetic external-customer paths at desktop/390px/360px plus Mobile API ownership checks. Email preview may be inspected; the send action must not be submitted.

Each gate records exact hashes and terminal results. Production requires a new Mission and separate exact approval.

## Acceptance matrix

- Financial cards show total invoiced, paid/funded and outstanding per original currency.
- Invoice list opens customer-owned detail with line descriptions, quantity, price, tax, linked order/payments and public notes.
- Private payment notes never appear; only `cpcc_public_note` may project.
- Documents include approved commercial, project, operations, shipment and explicitly published core files with verified project/shipment context and filters.
- Financial, invoice, project and shipment email previews are formatted, server-addressed and staff-only; no live send occurs in automated acceptance.
- `/api/index.php/chinaplanetcustomercenter/mobile/v1` resolves to the installed API class rather than HTTP 501.
- Customer A cannot access Customer B invoices, projects, documents, payments, opaque IDs or direct-download content.
- Arabic/English desktop and phone layouts have no page-wide horizontal overflow.

## Rollback

- Restore the exact pre-release runtime files from the request-scoped code backup.
- Keep additive document/payment schema and any already-recorded publication evidence; rollback disables right `500206` and removes its assignments so the rolled-back code cannot invoke section email.
- Never delete customer documents, Agenda history or business data as an ordinary rollback step.

## Staging helper installation result — 2026-08-24

- Ahmed approved only the checksum-pinned M061-R02 helper installation on canonical `backup-erp`; no release command was authorized at this gate.
- Immediately before installation, the four inbox files were `codex-deploy:codex-deploy 0644` and matched the frozen artifact, helper, sudoers and installer SHA-256 pins. The new helper and sudoers targets were absent.
- The root installer completed through the existing Termius `vps` host entry. It installed `/usr/local/sbin/chinaplanetos-backup-erp-m061-r02-release` as `root:root 0755`, size `42649`, SHA-256 `738cca9f02a882063f0f5f29dc0045e173220ffb9a738f1ffc11a8e961754a23`.
- Independent restricted-account verification confirmed valid helper shell syntax and exactly six fixed sudo commands: `compare`, `preflight`, `apply`, `verify`, `rollback`, and `status`.
- The restricted account cannot read `/etc/sudoers.d` metadata directly, but successful global sudo parsing and the exact six-command surface confirm the installed rule is active; the installer itself pins sudoers SHA-256 `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671` and fails closed on mismatch.
- No M061-R02 helper command was executed. Runtime code, database, schema, documents, configuration, containers, customer data, events and email were unchanged. Production was not accessed.
- Next gate requires a new explicit approval for `compare` and `preflight` on `backup-erp` only.

## Staging compare gate result — 2026-08-24

- Ahmed approved `compare` followed conditionally by `preflight` on canonical `backup-erp` only.
- Immediately before execution, the installed helper remained `root:root 0755`, size `42649`, SHA-256 `738cca9f02a882063f0f5f29dc0045e173220ffb9a738f1ffc11a8e961754a23`. The inbox artifact remained `codex-deploy:codex-deploy 0644`, size `101160`, SHA-256 `7254d9713ddd2ffb372014fbb7808e259736d0af3ed7e04e5790ee6849671393`; the exact six-command sudo surface remained active.
- Restricted `compare` stopped fail closed with `M061_R02_FAIL=ARTIFACT_STRUCTURE_INVALID` before candidate extraction, runtime comparison or schema inspection.
- Read-only diagnosis confirmed GNU tar emits `Ignoring unknown extended header keyword 'LIBARCHIVE.xattr.com.apple.provenance'` for all 30 archive members. The helper requires a warning-free metadata-clean archive and therefore rejected the macOS extended metadata correctly.
- `preflight` was not run. No artifact/helper replacement, apply, verify, status or rollback command ran.
- Runtime code, database, schema, documents, configuration, containers, customer data, events and email were unchanged. Production was not accessed.
- Required correction: rebuild an actually metadata-free 30-file artifact, update every dependent artifact/helper/installer checksum pin, repeat local release/helper tests, then request separate approval to upload/install the corrected helper and repeat `compare`/`preflight`.

## M061-R02-C01 local correction result — 2026-08-25

- Ahmed approved local artifact correction and checksum/test refresh only. Server upload, helper replacement and release commands were outside this gate.
- The new deterministic builder reads the frozen runtime and tooling blobs directly from Git and emits a 30-member USTAR archive with fixed `0644`, `0:0`, `root:root`, `1980-01-01T00:00:00Z`, no PAX headers and no xattrs.
- Corrected artifact `m061-r02-c01-customer-center-487b61e.tar.gz` has SHA-256 `3273970c7caad1a4580f662d003cabf5ef1274ce3149c6e9fb83f4670680a870`.
- Corrected helper SHA-256 is `b93d60e91ec40e9ad57ebb79a49bf0531e9acfbca5107a49b426cdbd480a6ec7`; it retains the same installed path and exact six-command sudo surface.
- Upgrade installer SHA-256 is `4630420585ab732b1783312c16b8acaebe528868c28c1c0b9422f6c4ba366f7c`; it can replace only the exact installed helper SHA-256 `738cca9f02a882063f0f5f29dc0045e173220ffb9a738f1ffc11a8e961754a23`. Sudoers remains byte-identical at SHA-256 `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671`.
- Repeated builds were byte-identical. The package verified 30/30 member payloads against the frozen commits, and the full local M061 test set passed.
- No staging or production resource was accessed or changed. The next gate requires Ahmed's separate exact approval to upload the C01 files and transactionally replace the helper on canonical `backup-erp` only.

## M061-R02-C01 backup-erp upload result — 2026-08-25

- Ahmed separately approved pushing C01 to `backup-erp`; this gate uploaded the checksum-pinned files to the canonical staging inbox only.
- Remote hashes matched artifact `3273970c7caad1a4580f662d003cabf5ef1274ce3149c6e9fb83f4670680a870`, helper `b93d60e91ec40e9ad57ebb79a49bf0531e9acfbca5107a49b426cdbd480a6ec7`, installer `4630420585ab732b1783312c16b8acaebe528868c28c1c0b9422f6c4ba366f7c` and unchanged sudoers `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671`; all are regular `codex-deploy:codex-deploy 0644` files.
- Server GNU tar produced zero warnings and exactly 30 regular archive members.
- The installed helper remained the old SHA-256 `738cca9f02a882063f0f5f29dc0045e173220ffb9a738f1ffc11a8e961754a23`. No installer or helper command ran; runtime, database, schema, documents, configuration, containers, customer data, events and email were unchanged. Production was not accessed.
- Next gate requires separate approval to run the C01 installer and replace only the exact known old helper. `compare` and `preflight` remain a later separate gate.

## M061-R02-C01 combined staging gate result — 2026-08-25

- Ahmed authorized the remaining `backup-erp` stages together. Production and real customer email remained prohibited.
- The checksum-pinned C01 installer replaced only the exact known old M061-R02 helper. Independent restricted-account verification confirmed `/usr/local/sbin/chinaplanetos-backup-erp-m061-r02-release` as `root:root 0755`, size `42657`, SHA-256 `b93d60e91ec40e9ad57ebb79a49bf0531e9acfbca5107a49b426cdbd480a6ec7`, valid shell syntax and the six fixed M061-R02 commands.
- Inbox hashes still matched artifact `3273970c7caad1a4580f662d003cabf5ef1274ce3149c6e9fb83f4670680a870`, helper `b93d60e91ec40e9ad57ebb79a49bf0531e9acfbca5107a49b426cdbd480a6ec7`, installer `4630420585ab732b1783312c16b8acaebe528868c28c1c0b9422f6c4ba366f7c` and unchanged sudoers `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671`.
- Restricted `compare` stopped fail closed with `M061_R02_FAIL=CANDIDATE_TEST_FAILED`. The first static test required `custom/chinaplanetlogistics/lib/chinaplanetlogistics.lib.php`, but C01's frozen 30-member archive does not contain that unchanged M061-R01 dependency, so the isolated candidate could not execute the test.
- `preflight`, `apply`, `verify` and `status` were not run. No request, code backup, database backup, runtime/schema/data/configuration/customer/email change or production access occurred.
- Continuing C01 is prohibited. The next safe action is a separately approved M061-R02-C02 correction Mission that closes every isolated candidate-test dependency, refreshes all artifact/helper/installer pins and local tests, then repeats installation and the staging gates.

## M061-R02-C02 combined staging gate result — 2026-08-25

- Ahmed approved preparation and deployment of dependency-closed C02 on canonical `backup-erp` only, including all gates, with production and real email prohibited. He separately confirmed the root installer at action time.
- C02 preserved the 19-file runtime allowlist and added nine frozen candidate-only dependencies. The resulting deterministic 39-member artifact has SHA-256 `e4ce5e8cfdcab1905d863a2cd026e31daee86ec04c70d69882c7eda4766c69dd`; repeated builds were byte-identical and the isolated candidate test set passed locally.
- Uploaded hashes matched helper `1eb6b15bafa3cd358293ec50d00283a572fbb8b041e11f06e82670e6433da01f`, installer `e884d090bca3e1e0feac7e7079a70371bbb6f2ee9d1df3458ba94403cf02b7b2`, unchanged sudoers `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671` and the artifact above. GNU tar listed exactly 39 members with zero warnings.
- The installer replaced only the exact installed C01 helper. Independent restricted verification confirmed the C02 helper as `root:root 0755`, 42,730 bytes, with the exact reviewed SHA and six-command sudo surface.
- Restricted `compare` passed candidate lint/tests and schema inspection, then reported `SCHEMA=MIGRATION_REQUIRED REASON=TABLE_MISSING` and one runtime drift. The live Mobile API class is absent, but the R02 baseline manifest expects SHA-256 `507dec6ff26b7d3886cd60b2a02202a1e206010f0996bca2634613b2cf3bafd8`; release SHA is `9bbe32546723a6f4547e119abca66aca72f5c378f89d966f2a0ab0aa38b170df`.
- Read-only M061-R01 verify still returns PASS with the Mobile API class absent, proving this baseline entry does not describe the deployed R01 runtime. C02 `status` returned `M061_R02_STATUS=NOT_DEPLOYED`.
- Execution stopped at compare. `preflight`, `apply`, `verify`, authenticated runtime acceptance and rollback did not run. No release request or backup was created and no runtime/schema/database/customer/document/email/configuration/container change occurred. Production was not accessed.
- Required correction: C03 must pin this exact live baseline as `ABSENT`, regenerate artifact/helper/installer hashes and tests, then repeat installation and all staging gates after separate approval.

## M061-R02-C03 deployment and acceptance result — 2026-08-25

- Ahmed approved the C03 baseline correction and all canonical `backup-erp` gates, with production and real email prohibited, and separately confirmed the checksum-pinned root installer at action time.
- The corrected 39-member metadata-free artifact SHA-256 is `bf508b9e7b495092542160e4bb97ce1410c2013497d90bc6af70ff9a3498085f`; installed helper SHA-256 is `fd4297da5ea877dc74e99f3f502c72dcfcfc7dde6d79ed3c18c3b050870f736f`. The helper retains exactly six commands.
- Local tests, upload verification, server GNU-tar validation, helper installation, independent verification, zero-drift `compare`, `preflight`, request-scoped `apply`, `verify` and `status` passed. Durable state is `DEPLOYED` with schema `READY` under request `m061-r02-20260824T175052Z-e72dbcab850a`.
- Staff acceptance passed the financial Total/Paid-or-funded/Outstanding summary, invoice drill-down with service prices, document filters/context, project detail, and formatted financial email preview. The send button was not clicked and Mailpit remained at zero messages.
- The available QA data contains no payment public note and no published linked commercial/project document; there is no approved external-customer staging session. Those positive and ownership cases remain unproven. Opening the project list invoked its existing automatic sync and created staging Kanban card `#3` for project `PJ2608-0001`; it was retained.
- Mobile API acceptance failed: `/mobile/v1/me` and an empty validation-only login request return HTTP 500, while the base Dolibarr API status route returns HTTP 401. The previous 501 include failure is gone, but Mobile API parity is not operational.
- Final verdict remains `CORRECTION_REQUIRED`. Rollback is available and uninvoked. Production was not accessed and no real email was sent. A separately approved C04 runtime correction is required before production consideration.
