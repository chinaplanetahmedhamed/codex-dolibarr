# Standalone Office Expenses Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Refactor the approved Office Expenses foundation into `custom/chinaplanetofficeexpenses/` without losing drafts, receipts or the current UI work.

**Architecture:** A standalone Dolibarr descriptor owns its menu, default-denied rights and module lifecycle. The existing domain/storage classes move to that module. The old entry point and class paths become compatibility includes; existing table names, schema bytes, pilot configuration and private receipt paths remain unchanged.

**Tech Stack:** PHP compatible with Dolibarr 24.0.1/PHP 8.2, current PHP 8.3.21 CLI, existing synthetic PHP/Python/SQLite fixtures.

**Spec:** Ahmed approved the concrete standalone-module recommendation with “yes do that” on 2026-11-02; recorded in M119.

## Global Constraints

- Keep `chinaplanetoperations` independent and preserve unrelated uncommitted work.
- Keep admin/entity/office scope, native expense rights, ownership, CSRF and no financial posting.
- No database rename, migration, module activation, rights grants, production access, deployment, commit or push.
- Preserve `cpo_oe_*` tables, schema hashes and `/var/lib/chinaplanetos-office-expenses/` paths.
- Refuse use of the old fixed M119 release contract for compatibility shims requiring a new module.

## Review Focus

- New module disabled: direct new and legacy URLs must fail closed.
- Original Operations module disabled: standalone descriptor/menu must not depend on it.
- Legacy URLs/classes must resolve the canonical implementation once, without duplicate records or class declarations.
- Module lifecycle must preserve business data and avoid schema side effects.
- Historical release tooling must not package only shims and omit their new dependencies.

### Task 1: Establish the module and compatibility boundary

Files: canonical `custom/chinaplanetofficeexpenses/`, legacy `custom/chinaplanetoperations/office_expenses/`, descriptor, own languages and README, `deploy/tests/test_m119_standalone_module.php`.

- [x] Snapshot the affected pre-existing local source and hashes before changing it.
- [x] Add a failing descriptor/route/storage/legacy compatibility test.
- [x] Move domain implementation to the standalone folder and retain compatibility includes at old paths.
- [x] Add module ID 500600 (unused in current custom descriptors), own default-denied rights, no Operations dependency, and data-preserving lifecycle.
- [x] Require standalone module enablement at HTTP entry; retain current pilot authorization.
- [x] Remove only the Office Expenses menu addition from the Operations descriptor.
- [x] Run descriptor, existing draft/receipt/storage and UI tests.

### Task 2: Update local tooling and guard release compatibility

Files: preview/test fixtures and generators, menu sync tests, fixed M119 artifact builder guard, M119 report.

- [x] Route local previews and HTTP fixtures to the standalone module; verify new/legacy access and unchanged stored synthetic data.
- [x] Update menu/language/asset expectations to the standalone namespace.
- [x] Block a legacy fixed package containing relocation shims; preserve historical immutable releases.
- [x] Run syntax checks and all available M119 suites, recording native-engine/environment limitations separately.
- [x] Regenerate and visually inspect the existing original-design preview; record activation/release prerequisites.

Execution: current agent performs this local refactor; no additional approval or branch switch is needed for the already approved scope. No commits will be made during this task.


## Execution record

Task1 complete: source-only snapshot preserved42 files; standalone descriptor/access gate8 cases went RED→GREEN; existing UI/storage/source suites remained green. No database/schema/private path changes.

Task2 complete: canonical generators/fixtures updated;12 synthetic HTTP scenarios passed on the temporary loopback server and its own state was removed. Legacy artifact relocation guard went RED→GREEN; historical helper contracts were kept at the old prefix and29 helper tests passed.15 PHP suites passed (184 scenarios plus16 menu assertions),5 artifact and9 runtime-preparation tests passed;67 PHP files linted. Two native auth/session cases skipped; matching real module activation/MariaDB/private filesystem remain release gates.

Final review: independent reviewer found no Critical/Important issues. Its documentation ownership/stale-state finding was corrected; no deferred code finding remains. Current canonical README and M119 standalone report record local status accurately.

Ruling: Retain the historical fixed M119 package/installer and refuse new shim commits rather than repin or broaden a deployed privileged helper during a local refactor. A complete standalone package/installer remains a separate rollout prerequisite.

Ruling: Keep the isolated feature worktree and preserve the dirty permanent checkout; no merge, commit or push was requested for this correction.
