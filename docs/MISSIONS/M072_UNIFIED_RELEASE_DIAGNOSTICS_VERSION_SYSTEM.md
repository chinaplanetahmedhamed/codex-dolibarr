# M072 — Unified release, diagnostics, rollback, and website version system

## Control

- **Approval:** Ahmed Hamed, 2026-09-01 — local implementation only.
- **Code-writing owner:** Agent 007 Central only.
- **Accepted inputs:** Dolibarr `46b0fa78fcec98ac0242f334a4996d3c889226ea`, React `dea74056522d918ab012ed2a25baa990e1412327`, WordPress `ae273e2d15fa89111336ef74f746a86cc9ac57bd`.
- **Release identity:** `M072-R01`.
- **Environment authority:** none. M073 must separately approve and create an isolated target binding.

## Business outcome

Replace Mission-specific deployment ambiguity with one auditable release contract. Every candidate has a complete component manifest, deterministic checksum-pinned artifact, independent code/schema/configuration/data states, an exact failing component and recovery instruction, and one immutable release identity. The public website displays `ChinaPlanetOS M###-R## · <short deploy SHA>` beside the copyright while the safe health response retains the complete deploy SHA.

## In scope

- Separate artifacts and manifests for Dolibarr runtime/support/migrations, React/Node build source, the canonical WordPress planner, and Customer Mobile source.
- Canonical USTAR metadata, zero timestamps, fixed owners/groups/modes, gzip header time zero, sorted paths, and SHA-256 pins.
- One restricted controller surface: `compare`, `preflight`, `apply`, `verify`, `status`, and `rollback` only.
- Root-owned release/target descriptors and adapter binaries, all checksum-pinned before any adapter is invoked.
- Exclusive locking, request IDs, atomic/fsynced state and audit files, terminal failure states, reverse-order recovery, and exact module/check recovery output.
- Independent code, schema, configuration, and data state reporting. Code rollback must never claim additive schema or retained data was removed.
- An installation package for the controller/library/sudoers only. It deliberately does not install a release descriptor, target profile, component adapter, or execute the controller.
- A generated deprecation ledger retaining every historical helper without authorizing or deleting it.
- Automatic, validated, localized website version presentation in Arabic, English, and French.

## Explicitly out of scope

- Any push, upload, installation, helper/controller/adapter execution, server access, runtime comparison, preflight, apply, verify, status, or rollback.
- Any Staging or Production target binding, container/stack change, database/schema/configuration mutation, DNS/proxy action, email, webhook, or business record.
- M073 environment implementation and M074 Production promotion.
- Deleting, rewriting, or authorizing historical helpers, branches, worktrees, ZIPs, or duplicated plugin copies.
- Changing `cpo.catalog-order.v2`, public booking semantics, price visibility, packing authority, Customer Center ownership, or M071 business behavior.

## Ownership and security boundaries

- WordPress/WooCommerce remains catalog, packing, and B2BKing price-policy authority.
- React owns public UI and uses the secure Node layer. Browsers never receive an ERP credential or call Dolibarr directly.
- Dolibarr owns leads, quotation requests, customers, documents, shipments, authenticated operations, schema, and business data.
- Mobile remains an independently packaged source component and is not copied into the Dolibarr runtime tree.
- Release descriptors may contain only public release metadata and SHA-256 values. Target paths, credentials, secrets, HMAC values, tokens, and infrastructure identities remain external root-owned configuration.
- Component adapters are a required M073 binding. The M072 source bundle is intentionally fail-closed and cannot become an environment release merely because artifacts exist.

## Automated acceptance

- Failure injection covers checksum mismatch, target/component mismatch, apply failure, reverse rollback, rollback failure, invalid adapter output, unsafe state values, and lock/contention source contracts.
- Artifact reconstruction is byte-identical across two independent local builds and every archive member matches its manifest hash, size, mode, and safe path.
- The four M069-C04 residue paths are absent from every manifest and archive.
- All migrations are included as support, while support files never become runtime copy targets implicitly.
- The sudoers file has exactly six fixed commands and the controller executes adapters without a shell or inherited environment.
- Installer pins controller, library, and sudoers, validates Python/sudoers before replacement, and restores the prior set on failure.
- React lint, server tests, UI tests, build, health metadata, localized accessible footer label, RTL/LTR, and 360/390px source checks pass.
- WordPress and M071/M070/M062/Dolibarr regressions pass unchanged.

## Live/runtime acceptance deferred to M073

- Bind exact isolated `backup-erp-rebuild` component adapters and root-owned target profile under separate approval.
- Prove compare/preflight do not mutate application state; apply/verify/status/rollback require their own authorized actions.
- Verify the complete mixed quotation lifecycle, Customer Center, shipping/Alexandria history, Arabic/English/French, RTL/LTR, accessibility, and mobile breakpoints.
- Verify the footer version equals the running release and short deploy SHA, and health reports the full exact SHA.
- HTTP 200, health alone, artifact construction, or local tests are never runtime acceptance.

## Rollback and stopping gates

- Before local commit: discard only the isolated M072 worktrees after preserving review evidence.
- After local commit: create a successor commit or branch; never reset/clean the protected permanent worktrees.
- Stop on any incomplete manifest, unpinned artifact/controller/adapter, unsafe target path, residue inclusion, unexplained M070/M071 regression, non-terminal request, ambiguous recovery, or Security/QA disagreement.
- Runtime code rollback restores code only. Additive schema/data and external configuration follow their explicitly reported policies and require separate approval for destructive reversal.

## Risk

**High.** The local source is non-mutating, but the later controller is privileged. No environment binding or installation is releasable until independent Security/QA approve one frozen checksum set and M073 supplies an exact isolated target implementation.
