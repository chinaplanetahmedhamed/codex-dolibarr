# M139 — Local live module parity

Owner: Ahmed Hamed. Execution: Codex. Date: 2026-11-22 Asia/Shanghai.

Ahmed requested checking installed modules against live and installing the needed existing modules locally to match live. Target is the separate live-code lab at /Users/mac/Documents/Codex/2026-11-19/task/erp-live-sync/lab-20261121, HTTP 127.0.0.1:18121. Preserve old 8088 runtime and all dirty source.

Plan: verify source fingerprints and local socket-only DB isolation; obtain non-secret enabled module-name/entity metadata from production without any writes; compare; back up local DB and rehearse restore; review existing module initialization/dependencies; activate missing required modules through native Dolibarr activation under outbound network denial; verify persisted activation, expected tables and authenticated local routes.

Production scope is read-only module activation names/booleans plus pinned container/source identity. No customer/financial rows, config values, credentials, production migrations, restarts or settings changes. Local test state uses synthetic data only. Keep mail blocked, cron unstarted, no production integrations. Do not install disabled/obsolete packages merely because their source exists. Unknown live activation stays unknown until verified.

Status: completed locally; module activation and Office follow-up verified below. Full baseline parity is tracked separately in M140.

## Office Expenses follow-up

Ahmed reported Office Expenses still unavailable. Native module flag is active, but native routes return 503/403 because the code intentionally accepts only root-owned Linux live/backup profiles. The fresh Mac lab has no Office schema or private binding. Scope extends to installing the existing owned schema, preparing private lab-only storage and four empty synthetic cashbook mappings, and a bounded Darwin/loopback-only profile binding. Production profile guards and financial posting remain unchanged/default-disabled; no real balances, production configuration or staff grants are copied. One class is reconciled with the verified live baseline in the current worktree before the local binding extension; other source and dirty work are preserved.

## Verified local result

Completed: ten existing modules activated and nine local-only modules disabled using native lifecycle; 56 available modules enabled, with CPKanban deliberately disabled per Ahmed USE BOARDS. Live metadata was read only. 108 descriptor hashes matched local. Legacy disabled folders and orphan ExternalSite/Oblyon/WhatsApp flags were not revived.

Boards: enabled existing workspace/shortcut/native-project bridge; ran the owner's additive workspace/project migrations. Authenticated Boards page and four read API actions passed; anonymous request required login, GET mutation rejected. Sixteen native HTTP checks passed.

Office Expenses: original profile rejected Mac runtime, so a Darwin-only, explicitly configured loopback/private-root binding was added in one owner class, reconciled from actual live source. Production Linux profile checks retained. Private configuration is outside webroot, owner-only/read-only. Four owned schema stages installed and verified. Empty financial tables verified before changing the lab's default EUR to USD; four empty synthetic native cashbook metadata rows created without SOLD/bank movements. Staff/workspace activated for the native bootstrap administrator, no staff grants or production credentials copied. Forty branch/section/language pages passed, CSRF and anonymous denial passed. Native category save/read/retry/update/archive passed; test category remains archived with its audit. Posting remains disabled. Twelve local-profile security cases and seven existing pilot regressions passed.

Recovery: original local backup imported unchanged into an independent socket-only MySQL instance; 433 base-table row counts matched and three view names restored. Definer-dependent view execution was not tested. Temporary recovery server stopped. Before Office provisioning, 121 protected business tables matched exactly across module activation; later owner interaction added local workspace/board records, which were preserved. Native cash movements and GL entries remain zero; no old financial DB was restored over subsequent work.

Source: all 16,560 snapshot file hashes matched before the Office portability binding; that one local class is now an intentional source exception. No production/backup writes, deploys, restarts, grants or configuration changes. Old local 8088 runtime and unrelated dirty sources preserved.

Evidence: /Users/mac/Documents/Codex/2026-11-19/task/erp-live-sync/lab-20261121/m139-evidence; module-parity.json. Full code/configuration/statement parity and Git/archive/backup-ERP synchronization belong to the subsequent active baseline goal and are not claimed complete here.
