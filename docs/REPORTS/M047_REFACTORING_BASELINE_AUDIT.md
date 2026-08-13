# M047 refactoring baseline audit — 2026-08-13

## Verdict

- **Runtime:** React production/staging and the ERP M046 scope are stable and aligned.
- **React source:** Ready for small behavior-preserving refactoring after the baseline PR is reviewed.
- **Dolibarr source:** Refactoring must start only from the clean M047 worktree; the permanent `main` remains intentionally untouched and diverged.
- **Deployment/database:** No change authorized or performed.

## React evidence

- Source and deployed SHA: `e8b253d5cc8e3ce766d2da43dc55d2cd67b0637d`.
- 162 tracked files; approximately 16,044 relevant lines under `src` and 3,164 under `server`.
- PASS: lint, 45 server tests, 7 UI tests and Vite production build.
- Main entry bundle at snapshot: `424.61 kB`, `126.02 kB` gzip.
- One transitive high-severity advisory: `nanoid@3.3.16` through `sanitize-html -> postcss`; remediation is reserved for a separate tested dependency change.
- Main hotspots: `src/index.css`, `src/dashboard/SiteEditorTab.jsx`, `src/App.jsx`, `src/pages/ProductsCatalogPage.jsx` and `server/http.js`.

## Dolibarr evidence

- Current remote baseline: `origin/main=d5c08831b3390cdb125e8fb503d55ceb581fef31`.
- Permanent local baseline before M047: `63a310c150b80087764605223e584b6e8b571cb9`, `ahead 1, behind 47`.
- Inventory: 610 tracked files, 19 worktrees, 47 local branches and 35 remote branches.
- Source composition: 235 custom files, 235 deploy files, 88 mobile files and 73 documentation files.
- Approximate relevant lines: 19,486 custom, 21,543 deploy, 6,179 docs and 3,448 mobile.
- PASS: PHP lint for 236 files, Bash syntax for 77 scripts, and 12 focused M041–M046 source-policy tests.
- Primary code hotspots: Customer Center portal/file/gallery library, mobile API data library, financial library, Shipping Price importer/service and public-request quotation service.
- Primary architectural risk: implicit module coupling. Customer Center libraries are reused as shared UI/security infrastructure, while Operations depends on Shipping Prices and a Shipping Prices service calls Operations in the reverse direction.
- Primary operational debt: 52 Mission-specific shell helpers and release-specific tests make deploy code larger than business custom code. Future consolidation must retain exact manifests, hashes, request states, backups, verification and rollback; the unsafe generic newest-artifact pattern remains forbidden.

## Required refactoring sequence

1. M047 baseline cleanup and draft PR review.
2. React characterization tests and dependency correction.
3. React route/state/editor/CSS/server-handler separation without contract change.
4. Dolibarr module dependency map and cycle removal.
5. Customer Center and shipping service decomposition behind regression tests.
6. Deployment-tooling rationalization only after every historical workflow/reference is mapped.

## Explicit limitations

- The ERP runtime comparison covers the exact M046 six-file shipping-note set, not every custom module file.
- HTTP 200 is routing evidence only; it is not full authenticated workflow acceptance.
- No branch or worktree is classified safe to delete by this audit alone.
