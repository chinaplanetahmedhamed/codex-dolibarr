# M047 completion report — clean baseline and refactoring preparation

## Mission and outcome

- **Mission:** M047.
- **Status:** COMPLETED on 2026-08-13.
- **Business outcome:** React and Dolibarr now have separate, reviewable documentation-only baseline branches. The stable runtime was not changed, the diverged Dolibarr permanent worktree was preserved, and future refactoring has explicit boundaries and evidence.
- **Production/staging mutation:** None.

## Git evidence

### React

- Repository: `chinaplanetahmedhamed/Moaz`.
- Parent/source baseline: `e8b253d5cc8e3ce766d2da43dc55d2cd67b0637d`.
- Branch: `codex/m047-react-baseline`.
- Documentation commit: `8f5efa9`.
- Draft PR: `https://github.com/chinaplanetahmedhamed/Moaz/pull/15`.
- Final worktree: clean and tracking the published branch.

### Dolibarr

- Repository: `chinaplanetahmedhamed/codex-dolibarr`.
- Remote baseline: `d5c08831b3390cdb125e8fb503d55ceb581fef31`.
- Branch/worktree: `codex/m047-clean-baseline` at `/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m047-clean-baseline`.
- Preserved local correction: `e5b3181`, cherry-picked from original local commit `63a310c`.
- Baseline documentation commit: `584d44f`.
- Draft PR: `https://github.com/chinaplanetahmedhamed/codex-dolibarr/pull/32`.
- Permanent worktree `/Users/mac/Documents/dev/chinaplanet-OS/codex-dolibarr` was not reset, switched, pulled, merged or cleaned.

## Files changed or moved

### React repository

- Added `AGENTS.md`.
- Added `docs/REFACTORING_BASELINE_2026-08-13.md`.

### Preserved outside source repositories

- Moved `عقد خدمات تجارية ثنائي اللغة - اليوسفي وفيجن.docx` to `/Users/mac/Documents/dev/chinaplanet-OS/documents/contracts/`; SHA-256 remained `15f30fc00ef7426d611bd49d9747c3cd98921408acb54aa8684098d19fd355c1`.
- Moved `عقد خدمات تجارية - اليوسفي وفيجن.docx` to `/Users/mac/Documents/dev/chinaplanet-OS/documents/contracts/`; SHA-256 remained `15ed17d8d1a503fe0ef60a6232d66f5a2df3b89bff9012004ee7ba2b600ca0e7`.

### Dolibarr repository

- Updated `docs/M041_GITHUB_DEPLOYMENT_MANUAL.md` with the canonical unified workspace path by preserving Ahmed's local commit.
- Added `AGENTS.md`.
- Added `docs/MISSIONS/M047_CLEAN_BASELINE_AND_REFACTORING_PREPARATION.md`.
- Added `docs/REPORTS/M047_REFACTORING_BASELINE_AUDIT.md`.
- Added `docs/REPORTS/M047_WORKTREE_BRANCH_INVENTORY.md`.
- Added this completion report.
- Updated `docs/CHANGELOG.md`.

## Runtime and release evidence

- React production and staging both reported `deploySha=e8b253d5cc8e3ce766d2da43dc55d2cd67b0637d`.
- ERP production and canonical `backup-erp` returned HTTP 200.
- Restricted M046 compare passed on both ERP environments for the exact six shipping-note runtime files at commit `15c19dda5842be992e6926db473878042a0a96d5` and artifact SHA-256 `79982ab606c958da9c7193c08464156c4e7e18f07d4ce5b65ba7b5af39164596`.
- Translation remained disabled and provider calls remained zero.
- This is exact M046-scope evidence, not a full all-module runtime/source parity claim.

## Verification

### React

- `npm run lint`: PASS.
- `npm test`: PASS, 45/45.
- `npm run test:ui`: PASS, 7/7.
- `npm run build`: PASS.
- `git diff --check`: PASS.
- Dependency audit recorded one transitive `nanoid@3.3.16` advisory; no automatic fix or lockfile change was made.

### Dolibarr

- PHP lint: PASS, 236/236 files.
- Bash syntax: PASS, 77/77 scripts.
- Focused M041–M046 source-policy regression suite: PASS, 12/12.
- `git diff --check`: PASS.
- Worktree/branch inventory completed with no deletion.

## Data, configuration, permission and visibility impact

- **Database/schema:** None.
- **Business/customer data:** None.
- **Accounting:** None.
- **Configuration/module state:** None.
- **Permissions/customer visibility:** None.
- **Documents on ERP:** None.
- **React/Dolibarr runtime:** None.

## Remaining risks and follow-ups

1. Both PRs remain draft and require review/merge before their documentation becomes `main`.
2. The permanent Dolibarr worktree remains intentionally diverged. Use the M047 clean worktree for review; do not reset the permanent worktree.
3. Two dirty historical worktrees and two clean unmerged historical worktrees require separate decisions. No cleanup is authorized by M047.
4. Full sanitized all-module ERP runtime/source/schema parity remains a future read-only baseline task.
5. React dependency remediation and functional refactoring require separate Missions.

## Rollback

- Close the draft PRs without merge to discard repository publication.
- Do not delete the published branches or M047 worktree without a separate exact cleanup decision.
- If required, move each contract DOCX back to the React root only after confirming its recorded SHA-256. No content conversion occurred.
- No runtime rollback exists because M047 made no runtime change.

## Acceptance

- Ahmed approval: PASS, 2026-08-13.
- Agent 007 architecture/scope review: PASS.
- QA evidence: PASS.
- Security review: not required for this documentation-only, no-runtime-change Mission.
