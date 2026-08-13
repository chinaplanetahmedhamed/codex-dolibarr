# M047 Dolibarr worktree and branch inventory — 2026-08-13

## Safety verdict

No branch or worktree was deleted, pruned, reset, switched or cleaned. This inventory identifies future cleanup candidates; it is not deletion approval.

## Counts

- Registered worktrees before M047: 19.
- Registered worktrees after adding the clean M047 worktree: 20.
- Local branches after adding M047: 48.
- Local branches already merged into `origin/main`: 40.
- Local branches not merged into `origin/main`: 8, including the preserved permanent `main` and the new M047 branch.
- Remote branches: 35 after `git fetch --prune origin`.

## Must preserve — local changes or unmerged evidence

| Worktree | Branch/state | Reason |
|---|---|---|
| `/Users/mac/Documents/dev/chinaplanet-OS/codex-dolibarr` | `main`, ahead 1 / behind 47 | Permanent worktree; contains untracked historical `AGENTS.md`. Its local commit was preserved on M047, but the worktree itself remains untouched. |
| `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/git-work/archive/2026-07-20/local-runtime-m011` | detached, 8 modified + 3 untracked | Dirty archived runtime evidence; must be reviewed and backed up before any cleanup. |
| `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/git-work/m040-smart-document-center` | `feature/m040-smart-document-center-local`, 6 modified + 9 untracked | Active/uncommitted M040 source; do not move or delete. |
| `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/git-work/archive/2026-07-20/m014-m010-m011-release` | `release/m014-m010-m011-customer-portal`, clean, unmerged | Requires historical decision before archive/removal. |
| `/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/git-work/m039-shipment-upload-200mb` | `urgent/m039-shipment-upload-200mb`, clean, unmerged | Requires Mission/status review before archive/removal. |
| `/Users/mac/Documents/dev/chinaplanet-OS/.worktrees/m047-clean-baseline` | `codex/m047-clean-baseline` | Current clean baseline worktree. |

## Merged clean worktree candidates — future cleanup only

The following worktree branches are merged into `origin/main` and had clean working trees during the inventory. They may become cleanup candidates only after their release artifacts, rollback evidence, open PRs and documentation references are checked:

- `/private/tmp/m044-c03-worktree` — `codex/m044-c03-note-review-ui`.
- `/private/tmp/m044-c04-free-translation` — `codex/m044-c05-disable-translation`; remote branch already gone.
- `/private/tmp/m046-production-preflight` — `codex/m046-production-identity-fix`.
- `.worktrees/m046-c01` — `codex/m046-c01-translation-policy`.
- `.worktrees/m046-c01-ci` — `codex/m046-c01-ci-runner`.
- `.worktrees/m046-c01-helper-r2` — `codex/m046-c01-helper-time`.
- archived `m004-shipment-status` — `urgent/m004-pre-sailing-status`.
- archived `m011-shipment-command-center` — `feature/m011-shipment-customer-command-center`.
- archived `m017-customer-mobile-api` — `release/m020-production-mobile-api`.
- archived `m020-c01-shipment-gallery` — `feature/m020-c01-shipment-gallery`.
- archived `m021-mobile-develop` — `mobile-develop`.
- archived `m021-register-workflows` — `ops/m021-register-internal-workflows`.
- archived `superseded-m020-c02-customer-web-gallery` — `feature/m020-c03-m025-shipment-documents`.
- archived `superseded-m021-c02-corrected-gallery-ota` — `feature/m021-c02-corrected-gallery-ota`.

## Future cleanup gate

Before removing any candidate:

1. Confirm the branch is merged into current `origin/main` after a fresh fetch.
2. Confirm the worktree is clean, not locked and not referenced by an active task.
3. Confirm no unique artifact, backup hash, report or rollback file exists only in the worktree.
4. Close or record any PR/release reference.
5. Remove through `git worktree remove`, never Finder or recursive deletion.
6. Delete a local branch only after the worktree is safely removed and Ahmed approves the exact cleanup set.
