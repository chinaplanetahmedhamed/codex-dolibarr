# M037 — Current Local Full Release to backup-erp

## Approved scope amendment — 2026-07-23

Ahmed explicitly changed the release scope from the M035/M036 subset to **all completed current local changes** and required a comparison between the live ERP and the current local version before release. This amendment supersedes every narrower M035/M036-only statement below.

- **Approved release content:** every reviewed application change currently present in the canonical worktree across Customer Center, Logistics, Operations, and Shipping Prices, plus its migrations, tests, missions, reports, changelog, and portal-parity record.
- **Verified live comparison baseline:** production M033 at runtime commit `0f8508b078b68ee13e04cc8924078d485dac5640` and production M034 at runtime commit `aa6e56ca5faf197cc92c79bd4cb5c45df88a7bd1`; their restricted live `compare` commands passed on 2026-07-23.
- **Current local application delta:** 44 runtime files compared with the verified M034 live source baseline: 24 Customer Center, 3 Logistics, 4 Operations, and 13 Shipping Prices.
- **Integrated staging runtime allowlist:** 52 files. It contains the 44 live-to-local differences plus 8 already-current M028/M033/M034 compatibility files required to test and run the integrated local state as one unit: the shared admin shell, Operations CSS/SOP route, CpKanban board/CSS, and Shipping Prices importer/batch surfaces. Those 8 are not described as new live differences; they are checksum-pinned dependency closure for staging parity.
- **Production authority for this Mission:** read-only `compare`, `status`, HTTP health, and approved diagnostics only. No production file, database, configuration, container, module state, document, or proxy mutation is authorized.
- **Deployment target:** canonical `backup-erp` staging only. A later production deployment requires Ahmed's separate explicit approval for the exact tested artifact and checksum.
- **Release exclusions:** local editor metadata, caches, logs, credentials, documents/uploads, database dumps, temporary browser screenshots, and any file outside the canonical repository.
- **Review requirement:** the comprehensive release must pass the full M029–M036 regression set and a new exact artifact/rollback review; prior focused acceptance does not waive the integrated staging test.

## Mission control

- **Mission ID:** M037
- **Type:** RELEASE_PREPARATION
- **Status:** CORRECTION_REQUIRED
- **Architect owner:** Agent 007 — ChinaPlanetOS Central Architect & Operations Agent
- **Implementation owner:** Agent 007 — Git Engineer and restricted staging operator
- **Required reviewers:** Shipping Operations, Security, QA
- **Dependencies:** completed current local M029–M036 changes, including authenticated customer booking and Shipping Orders administration
- **Target environment:** Canonical `backup-erp` staging only
- **Production:** Forbidden
- **Ahmed approval:** Approved in the active task with `ok, do it` after the staging-first release gate, M035 schema dependency, checksum, backup, tests, and rollback were stated
- **Approval date:** 2026-07-23

## Business objective

Prepare, deploy, and verify all completed current local ChinaPlanetOS changes on canonical `backup-erp` before any production decision.

## Current system context

- Verified local runtime includes customer finance corrections, generic portal navigation/labels, customer document counts, responsive admin/customer UI, consolidated Shipping and Customer centers, corrected shipping-rate publication state, authenticated customer booking, immutable accepted-price evidence, internal Shipping Orders, customer-scoped projections, and dashboard metrics.
- Verified Git state at approval: `integration/current-source` is based on `ca3540637dbe815fcd55bb4cfa461de93492f441` but contains mixed uncommitted M029–M036 work. The release must not be built from that dirty tree.
- Current recorded staging baseline predates M035/M036. No current evidence says the booking table, permission, routes, or Shipping Orders projections exist on `backup-erp`.
- Canonical staging is Compose project `erp-staging`, ERP container `erp-staging-dolibarr-1`, DB container `erp-staging-mariadb-1`, and custom volume `erp-staging_erp-staging_custom`.

## Scope

1. Commit the complete reviewed current local application delta and its supporting tests/documentation from the canonical worktree without editor metadata, secrets, runtime data, or files outside the repository.
2. Build a deterministic artifact and manifest from that clean commit; reject unsafe archive entries, secrets, symlinks, absolute paths, traversal, and non-allowlisted files.
3. Run read-only canonical staging preflight through `codex-deploy`; stop on any target, helper, container, volume, mount, port, owner, checksum, or production mismatch.
4. Deploy only through a checksum-pinned, fail-closed M037 staging helper with:
   - exact code-file allowlist;
   - root-owned code backup and absent-file manifest;
   - additive M035 schema/permission pre-state backup;
   - automatic rollback on failure;
   - PHP lint, source tests, schema check, transactional runtime tests, HTTP health, and named route smoke checks.
5. Apply the additive `llx_chinaplanetsp_booking` schema and permission `500411` only on canonical staging. Existing external users with the customer rate-read right may receive the separately revocable booking-submit right exactly as defined and approved by M035.
6. Verify admin, external-customer, ownership/isolation, booking idempotency, accounting non-impact, responsive routes, and post-deploy hashes using synthetic data only.
7. Record artifact, commit, hashes, backups, tests, rollback, and explicit production non-access.

## Out of scope

- Any production URL, container, volume, database, documents, configuration, proxy, DNS, firewall, image, or deployment command.
- Legacy `/opt/codex-dolibarr-staging` and `codex_staging_*` resources.
- Copying local or production databases/documents/customer files to staging.
- Native Sales Order creation, accounting posting, notification integrations, mobile implementation, or customer-visible scope expansion beyond M035.
- Committing unrelated finance, document, portal, or mobile changes from the dirty canonical worktree.
- Installing or running a new root helper without a separately verified fixed-path installer and exact staging-only authorization.

## Components to inspect

- M035/M036 application files and focused tests.
- `deploy/migrations/m035_customer_shipping_booking_schema.php`.
- Canonical staging helper/sudoers surface and current `sudo -n -l`.
- `erp-staging-dolibarr-1`, `erp-staging-mariadb-1`, `erp-staging_erp-staging_custom`, localhost port `18088`, and private release inbox only.

## Architecture and data flow

`Clean commit → exact manifest/artifact → restricted staging inbox → M037 preflight → code/schema pre-state backup → atomic code apply → additive M035 migration → lint/tests/health → verify or automatic rollback`

- The artifact is built only from a clean committed tree.
- Runtime code remains under Dolibarr custom modules; no core files are changed.
- The booking table is additive. The helper records whether it and permission/grants existed before apply and must restore that exact pre-state on rollback.
- Transactional/synthetic acceptance rows must be cleaned. Business/customer rows and documents are never copied.

## Permissions and visibility

- Staging deployment access uses restricted `codex-deploy` and a fixed helper command surface only.
- M035 permission `booking_submit` remains external-customer-only and separate from rate read.
- Customer ownership derives from the authenticated `fk_soc`; route parameters cannot select another customer.
- No supplier cost, margin, internal note/file, raw snapshot, credential, or private data may appear in customer output or deployment logs.

## Database, accounting, and configuration impact

- **Database:** Additive staging-only booking table plus permission definition/grants. Exact pre-state backup and rollback are mandatory.
- **Accounting:** None; tests must prove no quotation, order, invoice, payment, or accounting row is created.
- **Configuration/module state:** No module activation/deactivation, Compose, proxy, DNS, image, port, or volume change.

## Functional requirements

1. One authenticated staging customer can search an eligible FCL rate and submit one idempotent booking.
2. The booking appears once in internal Shipping Orders and under that exact customer.
3. Dashboard totals reconcile with the register and preserve distinct Sales Orders.
4. Customer B, anonymous, forged customer, foreign entity, and mismatched ownership cases are denied or excluded.
5. All shared navigation routes resolve through `/custom/...` without 404.

## Success criteria

- Clean runtime commit, pushed approved branch, exact manifest and SHA-256.
- Canonical staging preflight identifies only the approved `erp-staging` resources.
- Code and schema backups are created and hashed before mutation.
- Apply and verify return PASS; first failure automatically rolls back.
- M035/M036 source/runtime/security/regression tests pass in staging.
- Staging routes render without fatal/PHP warnings and customer isolation holds.
- Synthetic rows are removed and production remains untouched.

## Technical restrictions

- Extend existing Dolibarr only; no core edit.
- No production access or action.
- No legacy staging wrappers, reset, volume deletion, Docker prune, public proxy change, or unrestricted root shell.
- Stop on any mismatch or unexpected scope.

## Risks and rollback concept

- **Risks:** mixed-source contamination, schema/permission drift, wrong staging stack, partial code/schema apply, customer leakage, and rollback that destroys pre-existing staging data.
- **Rollback concept:** restore exact code backup and absent-file state; restore the exact booking-table/permission/grant pre-state; re-run lint, schema check, isolation, and HTTP health. Preserve all backups and fail closed if pre-state cannot be proven.

## Testing requirements

- Local: PHP lint, M035/M036 source and runtime tests, portal isolation, unified admin shell, admin accessibility, artifact allowlist/hash, helper static/adversarial tests, and `git diff --check`.
- Staging: helper preflight, candidate lint/tests, migration check/apply/check, runtime M035/M036 tests with cleanup, anonymous/external/admin route checks, ownership isolation, dashboard/register reconciliation, and verify/status.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Shipping Operations | M036 accepted review reused for unchanged workflow; M037 release review | PASS | 2026-07-23 | FCL, accepted-price, ownership, idempotency, eligibility, accounting, canonical project and port guards unchanged |
| Security | M037 release/helper review | PASS | 2026-07-23 | Exact allowlist, immutable input copies, authenticated in-process checks, schema/code/DB pre-state recovery and production boundary passed |
| QA | M037 staging release-readiness review | PASS | 2026-07-23 | Artifact `824fa12` / `32037f6a…`, cleanup, routes, interrupted-apply recovery and rollback state machine passed; runtime acceptance still required |
| Architect | Agent 007 | APPROVED | 2026-07-23 | Scope frozen to canonical backup-erp and M035/M036 dependency closure |
| Ahmed | Ahmed Hamed | APPROVED | 2026-07-23 | `ok, do it` after staging-first gates and production prohibition |

## Completion report contract

Create `docs/REPORTS/M037_COMPLETION_REPORT.md` with branch/commits, artifact/manifest/SHA, exact deployed files, DB/permission pre-state and outcome, helper/installer evidence, staging backups, tests, rollback, reviewers, and explicit production non-access.

## Current release gate — 2026-07-23

- Runtime artifact commit: `824fa129ff3f4445ba2c469728fef8245957f05e`.
- Artifact: `m037-full-824fa12.tar.gz`.
- Artifact SHA-256: `32037f6a0a6a1b7e8cc579f6eba25d4569e66f6e27e28b0d4b87c31bc375c30e`.
- Restricted helper SHA-256: `b87c646320620a4779e39b99273cbb5bdf147d5f54f3a7434ed3568a157e9e55`.
- Installer SHA-256: `d221293502f9d61017855d049d50473ec1a71cda6fc2d5590f0214ac256221a5`.
- All files are uploaded to the canonical private staging inbox with matching hashes.
- The helper is not installed yet; `compare → preflight → apply → verify → status` has not run. Installation requires the fixed root-only installer and is the only remaining gate before restricted staging execution.

## Correction gate — 2026-07-24

- The original M037 apply failed at the authenticated admin route and rolled back successfully.
- A user-scope correction was built at runtime commit `58b3ca35273bc05e61b5c2abf7afe48c29ec1fa1`, artifact SHA-256 `c0be577c1247c30d54e19b87eaf412da98ded4c8a5d650b6081d9fc822493b7f`, and helper SHA-256 `aa09f1db34a4e8f0a12c569e7615cc22ef0b3aa0e0f1a39eba9cf2298e48f074`.
- Its canonical staging preflight passed, but apply again failed at `AUTHENTICATED_ADMIN_ROUTE ROUTE=admin_dashboard`.
- Automatic rollback passed for code, database/schema pre-state, verification, and staging HTTP health; recorded state is `APPLY_FAILED_ROLLED_BACK`.
- M037 is therefore `CORRECTION_REQUIRED`. No further apply is authorized until M037-C01 produces an actionable read-only diagnosis and a separately reviewed correction.
