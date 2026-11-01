# M119 staging pilot release

Ahmed requests deployment to canonical backup-erp after the166-case foundation push and approves only the current administrator for Egypt/China. Existing release authorization covers preparation, backup, scoped application/runtime dependency changes, verification and rollback. No production or accounting posting.

Implementation choice: retain the current PHP/Dolibarr image as the immutable base and add BCMath reproducibly, rather than changing exact-money behavior or mutating packages in a running container. Verify the actual service definition before choosing its replacement mechanism. Preserve existing mounts, environment, labels, integrations, routes and ports. A dedicated private storage mount/configuration is preferable to native document/ECM storage; confirm actual mount support first.

1. Reverify restricted and separately authenticated administrative session identities and fixed canonical resources; do not contend with another task's terminal. Read selected service/build metadata only, no secret output.
2. TDD a server-only pilot configuration binder: exact allowed native admin ID/entity, current native rights, module state, trusted office currencies, entity base/precision and private root; absent/invalid/unapproved context fails closed. No requests can change scope and no schema/permission provisioning occurs on GET.
3. Prepare a pinned derived image with required official PHP build tools/source already present, then run bounded synthetic real-MariaDB migration/storage/receipt/locking acceptance in disposable isolated resources. No production networking, real account changes or accounting APIs.
4. Package only immutable M119 runtime and explicit release tools; review artifact allowlist/hashes and mission-specific helper. Verify backup/restore of the affected service/configuration/code and consistent new operational tables/blobs before pilot migration.
5. Apply only after all preflight checks pass. Enable the approved administrator binding and explicit pinned schemas/private root, with rollback that disables new writes and retains subsequent source/audit/receipt evidence. Never restore old database state over new records.
6. Run current authenticated Arabic/English desktop/mobile save/reload/retry and multipart/download acceptance, negative identity/entity/office/CSRF checks, source/byte hashes, exact money and health/integration isolation. Push final source/evidence and report the usable URL or a precise unresolved gate.

No new sudo grants, persistent credentials, public exposure, proxy/DNS changes or production inspection. If a platform action is denied, stop that action without bypassing it and preserve independently completed work.
