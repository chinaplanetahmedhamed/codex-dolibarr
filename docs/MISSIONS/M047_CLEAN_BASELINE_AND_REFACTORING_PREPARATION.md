> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M047 — Clean baseline and refactoring preparation

## Mission control

- **Mission ID:** M047
- **Type:** RELEASE_PREPARATION
- **Status:** COMPLETED
- **Architect owner:** Agent 007 / ChinaPlanetOS Architect
- **Implementation owner:** Agent 007
- **Required reviewers:** QA by evidence; Security not required because no runtime, secret, permission, schema or business-data change is allowed
- **Dependencies:** Stable React release `e8b253d5`; stable ERP M046 six-file runtime `15c19dda`
- **Target environment:** Local source and read-only runtime verification only
- **Production:** Modification forbidden; bounded read-only status/compare only
- **Ahmed approval:** Approved by “ok do it” after review of the 2026-08-13 refactoring audit
- **Approval date:** 2026-08-13

## Business objective

Create a safe, clean and auditable source baseline before functional refactoring starts. Preserve all current customer and staff behavior while resolving repository ambiguity, recording runtime/source evidence, and making future React and Dolibarr refactoring begin from explicit current branches.

## Current system context

- React production and staging both report `deploySha=e8b253d5cc8e3ce766d2da43dc55d2cd67b0637d`, which matches React `origin/main`.
- The permanent React worktree contained an untracked `AGENTS.md` and two unrelated contract DOCX files.
- Dolibarr production and canonical `backup-erp` both pass restricted M046 compare for the exact six shipping-note runtime files at commit `15c19dda5842be992e6926db473878042a0a96d5` and artifact SHA-256 `79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596`.
- The permanent Dolibarr `main` was `ahead 1, behind 47`; the single local commit only corrects the canonical workspace path in the M041 deployment manual.
- Dolibarr had 19 registered worktrees and 47 local branches. They are evidence and must not be deleted in this mission.

## Scope

1. Move the two contract DOCX files from the React source root to the unified non-repository contract folder while preserving their exact SHA-256 hashes.
2. Create `codex/m047-react-baseline` from the stable React commit and publish the repository `AGENTS.md` plus a source-only refactoring baseline report.
3. Create a clean Dolibarr worktree and branch `codex/m047-clean-baseline` from current `origin/main`.
4. Preserve the single local Dolibarr documentation commit by cherry-picking it onto the clean branch; do not rewrite or reset the permanent worktree.
5. Publish the Dolibarr repository `AGENTS.md`, this Mission, the baseline report and completion evidence.
6. Record worktree/branch inventory and current runtime/source evidence without deleting branches, worktrees, data or runtime resources.
7. Open draft pull requests only; merging remains a separate decision.

## Out of scope

- React or Dolibarr functional refactoring.
- UI redesign, API changes, module changes, schema changes, dependency upgrades or business-data cleanup.
- Worktree or branch deletion.
- Staging or production deployment, apply, rollback, container, proxy, Compose, Portainer or database actions.
- Publishing or deleting ERP Draft Batch 11.

## Components to inspect

- React source, server/API, tests, build output, dependency audit and deployed `deploySha`.
- Dolibarr `origin/main`, custom modules, deployment tests/tools, module descriptors, Mission status and restricted M046 status/compare.
- Git branches, worktrees, untracked files and local-only commit evidence.

## Architecture and data flow

No architecture or data-flow change is allowed. The retained boundary is `React -> secure Node API -> Dolibarr`; WordPress/WooCommerce remains the content/product/packing source and Dolibarr remains the operational source of truth.

## Permissions and visibility

No permission, customer visibility, supplier visibility, entity ownership, authentication or document access change.

## Database, accounting, and configuration impact

- **Database:** None.
- **Accounting:** None.
- **Configuration/module state:** None.

## Functional requirements

1. Preserve all tracked source and all unrelated user files.
2. Never reset, pull into, clean or delete the diverged permanent Dolibarr worktree.
3. Branches must originate from the fetched current remote baseline.
4. Runtime checks must be read-only and redact secrets/business data.
5. Documentation must distinguish whole-system evidence from the exact M046 six-file comparison scope.
6. Draft PRs must contain documentation/baseline changes only.

## Success criteria

- React baseline branch is clean except intentional files and passes lint, 45 server tests, 7 UI tests and production build.
- Contract files exist outside the React repository with unchanged hashes.
- Dolibarr clean branch starts from `origin/main`, contains the preserved local documentation correction, and leaves the permanent worktree untouched.
- All 236 PHP files and 77 shell scripts on the clean Dolibarr branch pass syntax checks.
- The 12-test M041–M046 source policy suite passes.
- Live/staging React SHA evidence and restricted ERP M046 compare evidence are recorded accurately.
- No runtime, database, configuration, document or business-data mutation occurs.
- Draft PRs are opened for explicit review before merge.

## Technical restrictions

- Extend existing Dolibarr; no standalone application or core edits.
- No production mutation or staging mutation.
- Do not expose secrets, private files, supplier costs, internal margin/profit, or cross-customer data.
- Do not expand scope; future refactoring requires separate approved Missions.

## Risks and rollback concept

- **Risk:** accidental loss of local source evidence. **Control:** preserve the permanent worktree, local commit, old branches and all worktrees.
- **Risk:** contract-file loss. **Control:** verify SHA-256 before and after the move; no deletion.
- **Risk:** confusing runtime stability with full source parity. **Control:** label M046 compare as exact six-file evidence and require a future full sanitized manifest.
- **Rollback:** branches and draft PRs can be closed without merge; contract files can be moved back using their exact recorded paths and hashes. No runtime rollback is needed because no runtime mutation is allowed.

## Testing requirements

- React lint, server tests, UI tests and build.
- React dependency audit recorded without automatic fix.
- Dolibarr PHP lint, Bash syntax and M041–M046 source policy regression suite.
- Git diff/status verification for both repositories.
- Restricted read-only runtime status/compare and public health checks.

## Review and approval record

| Role | Reviewer/task | Result | Date | Evidence/conditions |
|---|---|---|---|---|
| Domain | Agent 007 | PASS | 2026-08-13 | No business behavior change |
| Security | Agent 007 scope check | NOT_REQUIRED | 2026-08-13 | No security boundary or runtime mutation |
| QA | Agent 007 | PASS | 2026-08-13 | React 52/52 tests plus build; Dolibarr 236 PHP lint, 77 Bash syntax and 12/12 focused tests |
| Architect | Agent 007 | PASS | 2026-08-13 | Scope frozen to baseline cleanup |
| Ahmed | Ahmed | APPROVED | 2026-08-13 | “ok do it” |

## Completion report contract

Completed in `docs/REPORTS/M047_COMPLETION_REPORT.md`, with both draft PRs, file moves, Git evidence, runtime evidence, tests, no-impact statement and rollback recorded.
