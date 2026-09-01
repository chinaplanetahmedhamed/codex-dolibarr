# M073 Local Preparation Report

## Scope and state

- Mission: `M073-R01` isolated `backup-erp-rebuild` environment.
- Worktree: `/private/tmp/m073-isolated-rebuild-staging`.
- Branch: `codex/m073-isolated-rebuild-staging`.
- Base: `084ec05beaed8c85ec907b465eebcb862b0c7603`.
- Server mutation: **not started** at the local preparation checkpoint.
- Production access/data: **none**.
- Canonical Staging mutation: **none**.

## Prepared implementation

- Deterministic M073 bundle containing the exact four M072 artifacts and the isolated target definition.
- Digest-pinned Dolibarr 23.0.3, Node 22 Alpine, MariaDB 10.6, WordPress 7.0.4/PHP 8.3, and Mailpit 1.21.8 images for linux/amd64.
- Six-container Compose project, six named data volumes, one internal-only network, four localhost-only UI/API bindings, cron/business schedules disabled, WordPress outbound HTTP disabled, and no public hostname.
- Root-only server-generated credentials and a safe ledger that records presence/state but never credential values.
- Restricted helper and installer with exact fixed commands, source metadata/checksum validation, global lock, safe tar handling, fail-closed target checks, isolated rollback, and retained volumes.
- Runtime verification requires the exact per-container image reference and recorded image ID, mount source/destination/type/mode, port mapping, internal network, and `no-new-privileges` setting. `status` repeats these checks while ACTIVE.
- M072 custom Dolibarr source and compatibility symlinks, React M072 release metadata/noindex configuration, canonical WordPress planner source, and mobile artifact retention.

## Local verification

- M073 bundle integrity and reproducibility: PASS.
- M073 isolation/controller contract: PASS.
- M073 injected path/duplicate/active-state failures: PASS.
- Python and Bash syntax: PASS.
- Compose YAML parse and internal-network assertion: PASS.
- M072 controller/state regressions: PASS.
- M072 full artifact reproducibility from the accepted clean Dolibarr, React, and WordPress worktrees: PASS.
- Diff whitespace validation: PASS.

## Independent-review corrections

The first frozen Security/QA pass correctly rejected the candidate. Agent 007 then corrected the snapshot before any server mutation:

- Replaced the environment-resolved Python shebang with fixed `/usr/bin/python3`.
- Added exact image-ID/reference, mount, security-option, and host/container port-map verification; extra or missing values fail closed.
- Required `healthy` for every service healthcheck; `none` is no longer accepted.
- Made creation failures run candidate-scoped cleanup whenever the pinned Compose source exists, with an explicit cleanup terminal state.
- Made rollback verify the pinned active Compose source before privileged teardown and safely handle a failed pre-runtime attempt without deleting retained volumes.
- Added global exact-name collision checks for all candidate containers, volumes, network, and custom image tags before mutation; ledgerless `status` now fails closed if any candidate resource or runtime root exists.

The corrected checksum set requires fresh independent Security and QA PASS before any upload.

## Deliberate limitations at this gate

- No Production database or document copy exists.
- No real customer, quotation, order, shipment, file, or email fixture is created.
- WordPress planner source is present but is not activated because checksum-pinned WooCommerce/B2BKing dependencies are not part of the accepted M072 bundle.
- React-to-WordPress and React-to-Dolibarr credentials are absent. Public submission is therefore not enabled yet.
- Dolibarr migrations are included as support but are not silently applied during image construction.
- Full Arabic/English/French, RTL/LTR, mobile, accessibility, shipping/Alexandria, Customer Center, and `cpo.catalog-order.v2` browser acceptance remains deferred until the isolated stack has a separately approved sanitized-data/fixture gate.

## Next gates

1. Freeze exact control/package checksums.
2. Independent read-only Security and QA approval of the same frozen snapshot.
3. Upload the exact files to private M073 inboxes.
4. Human root-admin installation confirmation for the checksum-pinned helper/sudoers only.
5. Restricted `preflight`, followed by `create`, `verify`, and `status` under the current M073 approval if every prior gate passes.
6. Stop. Production export/sanitization/import and canonical Staging cutover require new exact approvals.
