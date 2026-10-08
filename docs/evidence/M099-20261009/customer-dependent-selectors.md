# M099 customer-dependent ERP selectors

Scope: code-only correction to the existing card link dialog. No schema, flags, permissions, document migration or publication changes.

Customer selection refreshes projects and shipments using customer_id; project selection additionally filters shipments using project_id. Parent changes immediately clear incompatible child selection, options and search text. The backend's existing entity, record-rights and relationship checks remain authoritative. Shipment selection still derives native customer/project.

Option response success, failure and busy-state completion all require the current dialog epoch and request round. Deferred response tests cover A-to-B changes, old success, old failure, A arriving after B, current errors and immediate child clearing. Existing strict-CSRF client 15 assertions and staging helper 8 safety scenarios pass; JS syntax and git diff check pass.

2026-10-09 live read-only browser: existing authenticated admin session opens boards and native shipment details. Shipments 20 and 18 render an empty document state. This does not establish nonempty document read, cross-interface writes, publication or customer-role acceptance. Staging shows Login; no credentials, auth provisioning or security changes attempted.

Deployment status: correction source only. Authenticated staging browser acceptance is required before production promotion. Retain the deployed M099 R2 canonical storage and 37 registered production documents. Do not rerun the broad M099 migration/release. Use fresh runtime hashes and a scoped one-file code backup/apply/verify/rollback when staging access is available.
