# M061-R02-C03 completion report

## Outcome

- Target: canonical `backup-erp` only (`erp-staging`).
- Release state: `DEPLOYED` by the restricted helper.
- Acceptance verdict: `CORRECTION_REQUIRED`.
- Production: not accessed.
- Real email: not sent; staging Mailpit remained at zero messages.
- Rollback: available and not invoked.

C03 corrected the false deployed-baseline expectation for the absent Mobile API class, completed the checksum-pinned release sequence, installed the exact runtime and additive schema, and removed the former HTTP 501 include failure. Bounded runtime acceptance then exposed a new blocking fault: both an unauthenticated `GET /mobile/v1/me` and an empty validation-only `POST /mobile/v1/auth/login` return HTTP 500. The base Dolibarr `/api/index.php/status` route still returns the expected unauthenticated HTTP 401, isolating the failure to the Customer Center Mobile API runtime rather than the API subsystem as a whole.

## Frozen identities

- Runtime commit: `487b61e690d7d030ec25d54a3b62afdb7e78fb55`.
- Source support-tooling commit: `e320ac8c13abef8f4f95993b7fcbd3164e564346`.
- Corrected baseline manifest SHA-256: `9941f52fbb67d5333a6485da1e9d5bd66ce618c35a876595720d67a768f397ec`.
- Artifact: `m061-r02-c03-customer-center-487b61e.tar.gz`.
- Artifact SHA-256: `bf508b9e7b495092542160e4bb97ce1410c2013497d90bc6af70ff9a3498085f`.
- Artifact contract: deterministic metadata-free USTAR, 39 regular members, 19 runtime files, 17 support-only files and three manifests; server GNU tar emitted zero warnings.
- Builder SHA-256: `c1d0237a7d6b7e8b7941cd00b54019f71d34012f4f6fd61b3c7b46e0eeb06066`.
- Installed helper SHA-256: `fd4297da5ea877dc74e99f3f502c72dcfcfc7dde6d79ed3c18c3b050870f736f`.
- Installer SHA-256: `3da5b9ddfe7c754b78b83259e85dda8fbf892c1f4bbd50866c9f67cfe45e952e`.
- Sudoers SHA-256: `95967d58463448006f7b9f31bf3b971ef5a5664cc3cde4fe6829c77702750671`.
- Authorized helper surface: exactly `compare`, `preflight`, `apply`, `verify`, `rollback`, `status`.

## Local and upload gates

- The sole baseline correction changed `custom/chinaplanetcustomercenter/class/api_chinaplanetcustomercenter.class.php` from the false source hash to `ABSENT`; the runtime and support manifests remained unchanged.
- Repeated artifact builds were byte-identical. Isolated candidate execution, all six focused tests, PHP lint for every PHP member, package/helper contracts, shell syntax and `git diff --check` passed.
- All four uploaded inbox files matched the reviewed hashes and were regular `codex-deploy:codex-deploy 0644` files.
- Ahmed separately confirmed the root installation action. The installer replaced only the exact C02 helper SHA-256 `1eb6b15bafa3cd358293ec50d00283a572fbb8b041e11f06e82670e6433da01f` and reported no deploy, database or production action.
- Independent restricted verification confirmed the installed helper as `root:root 0755`, valid shell syntax and the exact six-command sudo surface.

## Release gates

- `compare`: PASS; `DRIFT_COUNT=0`, Mobile API class `BASELINE_ABSENT`, schema `MIGRATION_REQUIRED` because the additive tables were absent.
- `preflight`: PASS; exact artifact/candidate/baseline/environment checks repeated before mutation.
- `apply`: PASS under request `m061-r02-20260824T175052Z-e72dbcab850a` after code and full-database backups and database restore rehearsal.
- `verify`: PASS; helper state `DEPLOYED`, exact runtime/artifact identities and schema `READY`.
- `status`: PASS; durable state remains `DEPLOYED` for C03.

### Retained recovery evidence

- Code backup: `/root/backup-erp-code-backups/m061-r02-before-487b61e-m061-r02-20260824T175052Z-e72dbcab850a.tar.gz`.
- Code backup SHA-256: `8f6ceeb3d8af282a857bdfee08e0913da1ded41a83f1a7f91cefb0eb7f070dfc`.
- Prestate: `/root/backup-erp-code-backups/m061-r02-before-487b61e-m061-r02-20260824T175052Z-e72dbcab850a.prestate`.
- Prestate SHA-256: `a6b1ed0bad76e202cab2af33419f27670adba167b21054818f19cbeec23ba76e`.
- Database backup: `/root/backup-erp-db-backups/m061-r02-before-487b61e-m061-r02-20260824T175052Z-e72dbcab850a.sql`.
- Database backup SHA-256: `ffef12fc65f6eb09d7a2b821e0b9018a88954aa24fa3718af30f8dac56e8c80b`.
- Retained artifact: `/root/backup-erp-release-artifacts/m061-r02-c03-customer-center-487b61e.tar.gz.bf508b9e7b495092542160e4bb97ce1410c2013497d90bc6af70ff9a3498085f`.
- Schema prestate: `MIGRATION_REQUIRED`.

## Bounded runtime acceptance

Acceptance used the existing authenticated staging administrator session and the staging QA customers. The user-supplied `basem` credentials were not transmitted because that customer is not present in the canonical staging customer list; only `QA Customer A` and `QA Customer B` are available.

### Passed

- QA Customer A financial statement shows EUR `Total invoiced 3,000.00`, `Paid or funded 0.00` and `Outstanding 3,000.00` in the required order.
- The validated invoice list links `IN2608-0001` to its detail page. Detail renders issue/due dates, project/order references, subtotal, tax, total, funded/remaining amounts, and two service lines with quantity, unit price, tax and total.
- Documents exposes customer, project, shipment, source, type, date and text filters and the unified Linked Documents area. The QA fixture has zero explicitly published linked documents, so positive commercial/project-document projection remains unproven.
- Project list/detail renders the linked invoice and provides the staff-only project email-preview action. Opening the project list also triggered its existing automatic CP Kanban synchronization and created staging card `#3` for `PJ2608-0001`; this acceptance-side staging database mutation is retained and was not deleted.
- Financial email preview is formatted HTML with currency cards, invoice detail and full-statement link. The recipient is a `.invalid` staging address. The send/Agenda confirmation button was not clicked.
- Mailpit reported `total=0`, `messages_count=0` after acceptance.

### Not proven by the available fixtures

- Neither QA customer has a received payment with `cpcc_public_note`, so public payment-note display cannot be accepted from runtime data.
- No explicitly published commercial/project document exists for QA Customer A, so positive linked-document projection cannot be accepted from runtime data.
- No approved external-customer staging session is available, so customer-to-customer ownership denial and external phone-layout acceptance remain pending.

### Blocking failure

- `GET /api/index.php/chinaplanetcustomercenter/mobile/v1/me`: HTTP 500 with an empty response body.
- Empty validation-only `POST /api/index.php/chinaplanetcustomercenter/mobile/v1/auth/login`: HTTP 500 with no credential submission.
- Control `GET /api/index.php/status`: HTTP 401 as expected.
- The previous 501 include failure is corrected, but Mobile API parity is still unavailable. This is a release acceptance failure, not a successful Mobile API restoration.

## Required next correction

Prepare a separately approved C04 runtime correction that reproduces and diagnoses the Dolibarr 23.0.3 Mobile API fatal path, adds a fail-closed live route gate requiring the expected unauthenticated 401/structured validation response, and supplies bounded staging fixtures/session evidence for payment public notes, linked documents and cross-customer ownership. Do not promote C03 to production.
