# M072 Local Completion Report

Date: 2026-09-02

Release: `M072-R01`

Scope: local implementation and immutable release-package preparation only

## Accepted source inputs

- Dolibarr/controller/mobile framework: `a6f6f88e2702686c6cd2e99544074804f644a837`
- React automatic website version: `01c9d872ccdf5664cf470019194156b5503dc74b`
- WordPress planner, unchanged accepted M071 source: `ae273e2d15fa89111336ef74f746a86cc9ac57bd`

## Delivered controls

- One controller contract with exact, separately authorized `compare`, `preflight`, `apply`, `verify`, `status`, and `rollback` actions.
- Exact four-component ownership set: Dolibarr, React, WordPress, and mobile.
- Independent code, schema, configuration, and data state reporting.
- Fail-closed component pins, descriptor pins, root-owned path and mode checks, non-blocking release lock, adapter file-descriptor execution, atomic request/state/audit evidence, exact rollback phase reporting, and recovery-required terminal states.
- Atomic generation-based installer with checksum pins, safe first-install directory persistence, verified link rollback, and no release or target profile installation.
- Deterministic metadata-free artifacts, complete source/runtime manifests, pinned Dolibarr compatibility-link layout, and a cross-repository historical-helper/workflow deprecation ledger.
- Immutable React release identifier plus validated full deploy SHA. The footer shows `ChinaPlanetOS M072-R01 · <short SHA>` with Arabic, English, French, RTL/LTR, mobile, and accessible-label support. Missing, malformed, or conflicting release metadata fails health closed.

## Frozen local artifacts

The authoritative checksum list is `deploy/releases/m072-r01/release-pins.sha256`.

| File | SHA-256 |
|---|---|
| `dolibarr.tar.gz` | `1e2ea0b7f8e1eefbe898fc52b1c61aaf33413dc9d65d40a391c9914d04e814f9` |
| `react.tar.gz` | `44c3bd853e681f12dbdc2579de48f018aa2a739a962e38bceb9389742d5a9c9a` |
| `wordpress.tar.gz` | `3a91e08a96562925881187ba5c3719188781e3edf74066284fec1301e1bfb332` |
| `mobile.tar.gz` | `315d3c5df193cfa5d4b655115c48a12131e4054342d791454840174161ad2964` |
| `release-bundle.json` | `d6c11bdd3e31f5703a1e9b2dd19b2cc6882e7ce51a5e18b56b235a0c6168abcf` |
| `dolibarr.layout.tsv` | `a6a4e8f7828c4f9d5d91c0b2154b1c3ecc6b6ee152eefc4bcdbb3cbaade62756` |
| `historical-helper-deprecation-ledger.tsv` | `0aea79a17fb4f51f9c15fa7b3318bb8ba7d0d459ffb30c6a4ae0ada2818b97b2` |

The package is deliberately unbound: `TARGET_ADAPTERS_AND_ENVIRONMENT_REQUIRE_M073_APPROVAL`. It is not an installed or executable environment release descriptor.

## Local acceptance evidence

- M072 failure-injection and controller/builder contract suites passed, including request collision, storage failure, rollback terminalization, tampered status pins, unsafe adapter fields, M069-C04 residue exclusion, and first-install durability checks.
- Two independent artifact builds were byte-identical.
- Frozen-package reproduction validates every archive member, type, mode, size, SHA-256, zero UID/GID/mtime metadata, non-runtime support targets, compatibility links, residue exclusions, and the deprecation ledger.
- React lint, server tests, UI tests, and production build passed.
- M070/M071/M054/M062/Alexandria, WordPress planner, and mobile regression checks remained accepted in independent QA.
- Independent Security and QA approved the frozen framework source before the two framework commits were created.

## Boundary and next gate

No push, upload, helper/controller/installer/adapter execution, secret generation, database or configuration change, staging or production mutation, email, or business record occurred.

This report and the generated package must receive independent read-only Security and QA approval as one unchanged snapshot before the local packaging commit. Environment adapters, release/target profiles, sanitized rebuild data, installation, and runtime acceptance belong to separately approved M073.
