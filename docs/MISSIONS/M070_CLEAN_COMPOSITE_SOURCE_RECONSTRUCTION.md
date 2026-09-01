# M070 — Clean Composite Source Reconstruction

## Mission control

- **Mission ID:** M070
- **Type:** IMPLEMENTATION / SOURCE_RECONCILIATION / RELEASE_GOVERNANCE
- **Status:** LOCAL_COMPLETED — SECURITY PASS / QA PASS
- **Architect and implementation owner:** Agent 007 Central
- **Code-writing owner:** Agent 007 Central only
- **Required reviewers:** Security and QA, independently against one frozen snapshot
- **Dependency:** M069 at `4309634566601c79845aad00437708a60a969c90`
- **Target environment:** Fresh local Git worktrees only
- **Production:** Forbidden
- **Staging:** Forbidden
- **Approved by:** Ahmed Hamed
- **Approval date:** 2026-08-31

## Business objective

Create a clean, traceable source baseline for Dolibarr/ChinaPlanetOS, the public React/Node site, the canonical WordPress planner, and the customer mobile application. The result must represent accepted Production plus accepted Staging behavior without importing historical branch drift, failed M068-R04 code, expanded runtime duplicates, or unrelated user work.

## Frozen source identities

| System | Clean base | Accepted overlay |
|---|---|---|
| Dolibarr/ChinaPlanetOS | `057af0f205f819c685869b3ee6aa1aea31ea549e` | M061 runtime hashes at `92854682f9192b47f2bfdf9013b8c4e4dcb9e49e`, with two explicit M070 customer-safe unit-label corrections; accepted Logistics upload boundary at `851c231cc96f51d2c3fafcb32c0a172e4f9973d5` |
| React/Node | `cb639518a784ce709e4a45eee0bd55b0a477c57a` | accepted Staging commit `df5ae8bd8e7c352f435cf175e5b411c9c53ecdce` |
| WordPress planner | `fa34257fe9dfc276d85c16dae70b409224465c3c` | none; canonical version remains `1.9.4` |

The permanent Dolibarr checkout, permanent React checkout, archived dirty worktrees, and the three protected China Guide paths are evidence-only and must remain untouched.

## In scope

1. Reconstruct all 327 M069 custom-runtime union paths into one source map.
2. Select the exact final 39-file M061 effective runtime layer and its corresponding source tests, additive migration source, OpenAPI contract, and Mobile application parity source.
3. Preserve current main/M062 shipping behavior, including Alexandria grouping and separate Old Port/TMT history.
4. Preserve CP Kanban as the owner of project synchronization and Customer Center as a compatibility caller only.
5. Preserve canonical Git symlinks instead of importing the 54 expanded runtime duplicates.
6. Include the accepted Staging Logistics upload boundary with its exact traceable hash.
7. Reconstruct the accepted React Staging commit on top of the current Production head without touching the protected China Guide work.
8. Confirm the canonical WordPress planner repository and version without importing the duplicate `b2b-integration/plugin` copy or historical ZIP.
9. Produce deterministic source/runtime, React, WordPress, and Mobile manifests plus local regression evidence.
10. Create local commits only after independent Security and QA review of the same frozen candidate.
11. Correct the M061 invoice line projection on web and Mobile so a customer-safe unit label/code or `null` is returned instead of the internal `fk_unit` database identifier found by the first independent review.

## Explicitly out of scope

- Any M071 `cpo.catalog-order.v2` business-submission correction, Third Party creation, quotation creation, customer activation, password, email, or business record.
- Failed or rolled-back M068-R04 runtime code, including portal invitation and public request lifecycle additions.
- Applying the retained M068 schema/configuration state or the M061 migration to any database.
- Replacing WordPress/WooCommerce as product, packing, or price-policy source.
- Recreating authenticated Customer Center/ERP behavior in React.
- Staging or Production upload, helper, compare, preflight, apply, verify, status, rollback, DNS, proxy, container, configuration, or database action.
- Deleting old helpers, worktrees, plugin duplicates, ZIPs, artifacts, or the four files still present on current Staging.
- Implementing the website footer version indicator; that remains M072.

## Architecture and ownership

```text
WordPress/WooCommerce catalog + packing + price policy
                    |
                    v
React public UI -> secure Node API -> Dolibarr operational intake
                                      |
                                      +-> Customer Center authenticated views
                                      +-> CP Kanban project-sync owner
                                      +-> Logistics / Operations / Shipping Prices

Customer Mobile App -> Customer Center Mobile API -> Dolibarr-owned data
```

- Browser code contains no Dolibarr credential and performs no direct Dolibarr call.
- Public shipping and catalog submissions remain review requests, never confirmed carrier bookings, validated orders, or final quotations.
- Customer Center and Mobile remain entity/customer scoped; supplier, cost, margin, internal note, credential, and private-document fields remain server-side.
- Mobile is reconciled as source separately; its historical Staging HTTP 500 acceptance blocker is carried to M073 rather than hidden or reclassified.

## Database, accounting, and configuration impact

- **Database/schema/data:** No database was opened or changed. The accepted M061 additive migration is preserved as source only and was not executed.
- **Accounting:** No invoice, payment, currency, balance, or posting behavior was executed or changed. The only invoice correction is a read-only presentation projection from internal unit ID to safe unit label/code.
- **Configuration/module state:** No configuration or module state was read or changed. The accepted `.htaccess` source is inventoried only and not installed.
- **Runtime:** No local, Staging, or Production runtime was changed.

## Acceptance criteria

- M069 evidence freeze verifies byte-for-byte.
- Thirty-seven M061 runtime source paths match the exact accepted Staging SHA-256 values; the two invoice projection paths match explicit M070 correction hashes and tests proving the internal unit ID is absent.
- All four M069-C04 residue paths are absent from source and classified excluded in the runtime map.
- Rejected M068-only paths are absent.
- M062 shipping source remains unchanged from the canonical base and Alexandria tests pass with separate terminal series.
- CP Kanban project-sync owner and Customer Center compatibility include remain exact.
- React contains the exact accepted `df5ae8b` overlay and no protected China Guide delta.
- WordPress remains clean at version `1.9.4` with all plugin tests passing.
- React passes lint, server tests, UI tests, and build; Mobile passes typecheck, lint, and tests; changed PHP passes lint and focused M054/M061/M062 regressions pass.
- Arabic/English/French, RTL/LTR, desktop/mobile, accessibility, shipping, legal, SEO, Customer Center link, and public request boundary source contracts remain present.
- Security and QA both return `PASS` on the same frozen hashes before the local result commits are finalized.

## Risks and stopping gates

- **High — divergent history:** stop on any runtime path that cannot be mapped to a canonical source hash.
- **High — M061 incomplete runtime acceptance:** retain the known Mobile API and positive customer-document/payment-note evidence blockers for isolated M073 runtime acceptance.
- **High — M068 contamination:** stop if any rejected M068-only path or M071 behavior enters the candidate.
- **Medium — expanded duplicates:** stop if nested deployed copies replace the canonical top-level module plus symlink layout.
- **Medium — public regression:** stop if Alexandria terminal trends, rate-selection-to-review, language direction, legal/consent, or protected China Guide files regress.

Rollback is local branch/worktree abandonment after preserving the manifests and review evidence. Permanent worktrees are never reset, cleaned, or rewritten.

## Review record

| Role | Reviewer | Result | Date | Evidence |
|---|---|---|---|---|
| Security | Independent frozen reviewer | PASS | 2026-09-01 | Dolibarr tree `7690968a...`, React tree `ad9e6994...`, WordPress tree `05ec2b03...`; no actionable security finding |
| QA | Independent frozen reviewer | PASS | 2026-09-01 | Same frozen trees; 327/221/35/90 manifests and full local suites verified |
| First frozen snapshot | Security and QA | CORRECTION_REQUIRED | 2026-09-01 | Internal unit ID exposure, parity/provenance/test completeness, and EOF findings; rejected snapshot is not releasable |
| Architect | Agent 007 Central | ACCEPTED_LOCAL | 2026-09-01 | Reviewer PASS received; local commits only |
| Ahmed | Ahmed Hamed | APPROVED_FOR_LOCAL_IMPLEMENTATION | 2026-08-31 | Exact M070 approval; no deployment authority |
