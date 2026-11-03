# M121 Boards source reconciliation review

Decision: retain PR60 as a scoped candidate. Do not merge its older branch or replace the newer Boards JS wholesale. No source or runtime integration is approved by this review.

## Verified baselines

- Canonical: codex/m084-dashboard-repair @310deeaa70ced8d1a74aa84c802e2c705e54b2a1, existing dirty state preserved.
- Newest inspected local Boards owner: statement-ui-dev @0fb1dcc89d33760cca3a1b42c83979ba2fe89e77; AGENTS.md dirty only. Local runtime payload commit f6fefab780d14dd863ca8c2a3857dfa421e87fbb has no Operations source delta against this owner; this does not establish mounted parity.
- GitHub PR60: open draft, head422284e0b26d15ef866c1fb7a886d9d77425e9b3, base113ddc6855915061d14acc7ab61ea33fd31742f9; five-file exact delta read through GitHub connector.

## Reconciliation

The candidate adds per-user/entity local density preference, textual priority bands and task priority projection in search/shared. Its SQL keeps existing entity/readableSql/card-grant conditions. Shared search adds reference matching. It does not complete customer/project/name search across all views.

Owner CSS and Boards service are identical to PR base. Owner JS includes later M116 finance-authorized account summaries and statement links absent from PR60. The supplied five-file scoped patch applies PR presentation changes while retaining the entire M116 summary implementation, all summary call sites, accountStatements conditions and newer navigation. It includes the candidate CI workflow/test. It does not add the underlying Boards implementation to canonical.

Canonical lacks Boards entry pages and many supporting services. The manifest records a32-file static PHP dependency closure (only literal __DIR__ references) and exact owner/canonical hashes. Different existing files include task service, authorization, asset/collaboration services and Customer Center portal library; these require owner reconciliation rather than copying an older version. Also verify kanban_pointer.js, native bootstrap/admin_shell, assets/routes, module rights/feature flags and installed Boards/workspace/lifecycle/context/document schema. Do not install or enable modules to hide missing source.

## Executed checks

All executed commands and exit codes are in manifest.json. Temp fixture checks passed: PHP lint; whole JS syntax; PR60 selected-function/source fixture; M116 summary regression; full-file IIFE synthetic startup (24 mode/language/preference combinations plus denied API and Shared transitions); M116 block/call/gate preservation assertions. Whole startup executes the actual complete reconciled script, not extracted functions. Fetch/DOM are synthetic; no actual ERP/business write occurs.

## Remaining acceptance and risks

- Runtime P1 baseline/isolation and session ownership remain unresolved with root. This reviewer did not connect to a server.
- Actual CSS/layout is untested. Specifically check the workspace priority band negative bottom margin when the later M116 account summary follows it: PR60 styles were authored on a baseline without that trailing summary. Ensure no overlap, clipping or concealed finance text.
- Test comfortable/compact, light/dark, EN/AR, long titles/references, priority normal/high/urgent/low, covers, menus, financial amounts, keyboard focus and touch/drag at1440/1280/390 plus the plan's1366x768,1920x1080,390x844 at100% zoom. Record computed dimensions and sanitized screenshots.
- Verify full bootstrap and real gallery/customer/project/board/My Work/shared/search routes, storage unavailable/invalid preference, reload/user/entity isolation and API failure.
- Authenticated synthetic staging tests must verify admin/editor/viewer/card-only/unauthorized/foreign-entity access; correct priority source in card/search/shared/My Work; no sibling/private finance leakage; native source/task completion and M116 account behavior remain intact.
- Existing M095/M097/M099/M107 runtime tests require guarded synthetic local DB/runtime fixtures and cannot be pointed directly at backup-erp. Adapt narrowly only after isolated environment approval.
- Keep current account source/finance permissions and amounts unchanged; this UI delta cannot resolve M121 financial source decisions. New labels currently use existing inline wsText pattern; owning-module langs is the documented convention.

Artifacts are selected-file review fixtures only. No canonical files, Git refs, worktrees, database, flags, grants, server jobs or runtime were changed.
