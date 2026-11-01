# M119 — Staging release preflight, 2026-11-01

## Authorization and release boundary

Ahmed requested push and deployment, selected `backup-erp.visiontrading.biz` first, and approved a pilot limited to the current administrator with Egypt and China office choices. These instructions supersede earlier local-only/no-push restrictions for this scoped release. They do not authorize production access or automatic accounting posting. The pilot restriction is approved intent, not an installed permission or runtime binding.

The prepared source is on `codex/m119-office-expenses-local`, based on `310deeaa70ced8d1a74aa84c802e2c705e54b2a1`. Only additive Office Expenses runtime files, synthetic tools/tests and M119 documentation belong to the release. Do not deploy the old parent module or unrelated work from that baseline. The separately sanitized Finens repository remains the visual reference; its database, credentials and real uploads are excluded.

## Completed native storage continuation

- Added separately pinned receipt schema and dependency on the unchanged draft schema, native receipt metadata/audit storage, strict private filesystem checks, and a per-request native storage factory. No automatic installer, global factory registration, private-root provisioning or native accounting writer is enabled.
- Receipt creation locks the owned source row before retry/quota reads. Originals and normalized previews remain private and immutable; exact hashes, creator/entity/office checks and audited downloads are enforced. Orphans count toward quotas and are retained for reconciliation.
- Uncertain transactions quarantine the shared native handle across draft, receipt and migration adapters. Unknown commits retain evidence; no bytes are released without a confirmed access audit. Fresh physical connection and reconciliation are required after uncertainty.
- Fixed captured-identity drift: reassigning an already bound native `User` object to another employee invalidates both cached draft services and the native factory. The employee UI test now uses a fresh legitimately authorized service for its independent cross-owner 404 assertion, and separately asserts denial of the stale service.

## Current verification

All 12 safe CLI suites pass: domain16, employee UI15, native drafts13, native identity6, capabilities5, provenance3, native workflow10, native storage22, private receipts21, receipt interface15, migration17 and native receipts23: **166 passed, 0 failed**. The identity command used `--without-auth`; two login/session cases are explicitly skipped. Forty scoped PHP files pass syntax checks; `employee.js` passes `node --check`; `git diff --check HEAD` passes. A bounded source scan found no private-key/token/hash patterns or database/key/environment files in the scoped release.

These results use synthetic state and local Dolibarr24.0.1 source with PHP8.3.21. Migration/receipt fixtures use SQLite with simulated mysqli metadata, advisory locks and SQL adaptations. They do not prove real MariaDB locking, DDL metadata, disconnect recovery, pooling, or the changed multipart/binary/native-session transport. Historical 16-case native HTTP acceptance predates the receipt integration and is not current deployment acceptance. The existing local static preview is synthetic and read-only.

## Fresh staging observations

Read-only checks verified host `srv1011043` / `31.97.158.178`, the restricted `codex-deploy` identity, and the existing authorized administrative terminal identity. Selected container metadata confirms:

- `erp-staging-dolibarr-1`: running, Compose project `erp-staging`, image `local/dolibarr:24.0.1`.
- `erp-staging-mariadb-1`: running, same project, image `mariadb:10.6`; binary reports **10.6.27-MariaDB-ubu2204**.
- Runtime **Dolibarr24.0.1 / PHP8.2.32**, GD present, **BCMath absent**. The source money constructor explicitly requires BCMath and cannot safely run without it.
- Custom mount: named volume `erp-staging_erp-staging_custom`, host `/var/lib/docker/volumes/erp-staging_erp-staging_custom/_data`, container `/var/www/html/custom`, writable. Parent module and documents directory are `www-data:www-data` mode0755. The `office_expenses` target directory is absent.
- Existing canonical generic deployment helper is a fixed older release with an incompatible allowlist. It was inspected, not executed. No helper, sudoers, credentials or network/access settings were changed.

No production resource, business database record, credential value or session secret was read. No artifact was applied, database migrated, root provisioned, application container rebuilt/restarted or pilot permission activated.

## Blocking checks and next bounded release

**Activation is stopped at preflight:** the required exact-money extension is missing. Do not substitute floating-point arithmetic or call a copied-but-unconfigured endpoint a usable deployment.

1. Prepare and review a staging-only reproducible application-image update with BCMath, preserving the current image for rollback and existing Compose configuration. Do not install packages ad hoc in a running container. Verify the image/service backup, target mount/owner/port invariants and unrelated staging features before and after any replacement.
2. Bind the pilot to the verified current administrator's actual ID and entity; supply explicit Egypt/China currencies and independently verified entity base/precision. No all-admin or all-staff fallback. Current globals remain unwired and otherwise yield503.
3. Exercise pinned draft/receipt schema, real mysqli metadata, transaction outcomes and locks in a disposable matching-engine database before any pilot migration. Provision a marked private root outside the webroot with application ownership and0700/0600 limits, and verify consistent code/schema/source/audit/blob backup and restore.
4. Produce a mission-specific immutable allowlisted artifact and reversible deployment path. Back up scoped existing state first. Code rollback must disable new writes while retaining all subsequent drafts, receipts, schema and audit; no destructive down migration.
5. Accept the pilot with current native login, positive/negative ID/entity/office/CSRF cases, real multipart upload, exact original/preview download hashes, retry/quota/failure handling, and Arabic/English desktop/mobile views. Verify public TLS/authentication and integration isolation without altering proxy/DNS/security.

Financial posting, tax/FX treatment, settlement and bank reconciliation remain disabled and separately gated. Advancing the staging runtime is not approval to post accounting transactions.
